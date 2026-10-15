<?php

declare(strict_types=1);

use Palacios\Framework\App;
use Palacios\Framework\Http\HttpRequest;
use Palacios\Framework\Results\Results;
use Palacios\Framework\Security\Antiforgery\CsrfTokenGenerationException;
use Palacios\Framework\Views\ViewOptions;
use Palacios\Framework\Views\ViewRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

final class ViewRendererTest extends TestCase
{
    private const string VIEWS_PATH = __DIR__ . '/fixtures/views';

    private int $initialBufferLevel;

    protected function setUp(): void
    {
        $this->initialBufferLevel = ob_get_level();
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialBufferLevel) {
            ob_end_clean();
        }
    }

    public function testViewFailureDiscardsPartialHtmlAndPropagatesTheOriginalException(): void
    {
        $failure = new RuntimeException('view failure');

        $caught = $this->captureFailure(fn () => $this->renderer()->render('throwing', ['failure' => $failure]));

        self::assertSame($failure, $caught);
        self::assertSame($this->initialBufferLevel, ob_get_level());
    }

    public function testLayoutFailureDiscardsPartialHtmlAndPropagatesTheOriginalException(): void
    {
        $failure = new RuntimeException('layout failure');

        $caught = $this->captureFailure(fn () => $this->renderer()->render('normal', [
            'name' => 'Ada',
            'failure' => $failure,
        ], 'throwing'));

        self::assertSame($failure, $caught);
        self::assertSame($this->initialBufferLevel, ob_get_level());
    }

    #[DataProvider('failingTemplates')]
    public function testFailurePreservesAnExistingOuterBuffer(string $view, string $layout): void
    {
        $failure = new RuntimeException('template failure');
        ob_start();
        echo 'external prefix';
        $externalLevel = ob_get_level();

        $caught = $this->captureFailure(fn () => $this->renderer()->render($view, [
            'name' => 'Ada',
            'failure' => $failure,
            'initialBufferLevel' => 0,
        ], $layout));

        self::assertSame($failure, $caught);
        self::assertSame($externalLevel, ob_get_level());
        self::assertSame('external prefix', ob_get_contents());
        echo '|external suffix';
        self::assertSame('external prefix|external suffix', ob_get_clean());
        self::assertSame($this->initialBufferLevel, ob_get_level());
    }

    /** @return iterable<string, array{string, string}> */
    public static function failingTemplates(): iterable
    {
        yield 'view' => ['throwing', 'normal'];
        yield 'layout' => ['normal', 'throwing'];
    }

    #[DataProvider('nestedFailingTemplates')]
    public function testFailureCleansEveryNestedBufferOpenedByTheTemplate(string $view, string $layout): void
    {
        $failure = new RuntimeException('nested template failure');
        ob_start();
        echo 'external content';
        $externalLevel = ob_get_level();

        $caught = $this->captureFailure(fn () => $this->renderer()->render($view, [
            'name' => 'Ada',
            'failure' => $failure,
        ], $layout));

        self::assertSame($failure, $caught);
        self::assertSame($externalLevel, ob_get_level());
        self::assertSame('external content', ob_get_clean());
    }

    /** @return iterable<string, array{string, string}> */
    public static function nestedFailingTemplates(): iterable
    {
        yield 'nested buffers in view' => ['nested-throwing', 'normal'];
        yield 'nested buffers in layout' => ['normal', 'nested-throwing'];
    }

    public function testCleanupCallbackFailureDoesNotReplaceTheTemplateException(): void
    {
        $failure = new RuntimeException('original template failure');
        ob_start();
        echo 'external content';
        $externalLevel = ob_get_level();

        $caught = $this->captureFailure(fn () => $this->renderer()->render('cleanup-throwing', ['failure' => $failure]));

        self::assertSame($failure, $caught);
        self::assertSame($externalLevel, ob_get_level());
        self::assertSame('external content', ob_get_clean());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[DataProvider('failingTemplates')]
    public function testGlobalHandlerSendsOnlyProblemJsonAfterTemplateFailure(string $view, string $layout): void
    {
        $previous = new RandomException('entropy failure');
        $failure = new CsrfTokenGenerationException('Token generation failed.', previous: $previous);
        $builder = App::createBuilder();
        $builder->services()->addViews(static function (ViewOptions $options): void {
            $options->path(self::VIEWS_PATH)->defaultLayout('normal');
        });
        $app = $builder->build();
        $app->mapGet('/home', static fn () => Results::view($view, [
            'name' => 'Ada',
            'failure' => $failure,
        ], $layout));

        ob_start();
        $response = $app->handle(new HttpRequest('GET', '/home'));
        $response->send();
        // A leaked renderer buffer would put partial HTML in this captured output.
        $sent = ob_get_clean();

        self::assertSame(500, $response->statusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->headers()['Content-Type']);
        self::assertSame($response->body(), $sent);
        self::assertSame($this->initialBufferLevel, ob_get_level());
        $problem = json_decode($sent, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(500, $problem['status']);
        self::assertArrayHasKey('traceId', $problem);
        self::assertStringNotContainsString('<', $sent);
        self::assertSame($previous, $failure->getPrevious());
    }

    public function testNormalRenderingPreservesContentAndDoesNotLeaveBuffersOpen(): void
    {
        $html = $this->renderer()->render('normal', ['name' => 'Ada & Linus', 'title' => 'Page']);

        self::assertSame('<main title="Page"><p>Ada &amp; Linus</p></main>', $html);
        self::assertSame($this->initialBufferLevel, ob_get_level());
    }

    public function testNormalRenderingPreservesAnExistingOuterBuffer(): void
    {
        ob_start();
        echo 'outer content';
        $externalLevel = ob_get_level();

        $html = $this->renderer()->render('normal', ['name' => 'Ada', 'title' => 'Page']);

        self::assertSame('<main title="Page"><p>Ada</p></main>', $html);
        self::assertSame($externalLevel, ob_get_level());
        self::assertSame('outer content', ob_get_clean());
    }

    public function testSuccessfulRenderingCollectsUnclosedNestedBuffersInOutputOrder(): void
    {
        ob_start();
        echo 'outer content';
        $externalLevel = ob_get_level();

        $html = $this->renderer()->render('nested', layout: 'nested');

        self::assertSame(
            '<main>layout:outer|layout:inner|view:outer|view:inner|view:deep|layout:deep</main>',
            $html,
        );
        self::assertSame($externalLevel, ob_get_level());
        self::assertSame('outer content', ob_get_clean());
    }

    public function testLayoutCanStillReadVariablesDefinedByTheView(): void
    {
        $html = $this->renderer()->render('shared-scope', ['name' => 'Ada']);

        self::assertSame('<main title="Title from view"><p>Ada</p></main>', $html);
        self::assertSame($this->initialBufferLevel, ob_get_level());
    }

    private function renderer(): ViewRenderer
    {
        return new ViewRenderer((new ViewOptions())->path(self::VIEWS_PATH)->defaultLayout('normal'));
    }

    private function captureFailure(callable $render): Throwable
    {
        try {
            $render();
        } catch (Throwable $failure) {
            return $failure;
        }

        self::fail('A falha do template deveria ser propagada.');
    }
}
