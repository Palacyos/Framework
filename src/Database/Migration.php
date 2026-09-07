<?php

declare(strict_types=1);

namespace Palacios\Framework\Database;

use PDO;

interface Migration
{
    public function up(PDO $connection): void;

    public function down(PDO $connection): void;
}
