<?php
declare(strict_types=1);
namespace App\Core\Routing;
final readonly class EndpointMetadataCollection
{
    /** @param list<object> $items */
    public function __construct(private array $items = []) {}
    /** @return list<object> */
    public function all(): array { return $this->items; }
    public function get(string $type): ?object
    {
        foreach (array_reverse($this->items) as $item) if ($item instanceof $type) return $item;
        return null;
    }
    public function has(string $type): bool { return $this->get($type) !== null; }
}
