<?php
declare(strict_types=1);
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Execute composer install antes da suíte PHPUnit.');
}
require $autoload;
