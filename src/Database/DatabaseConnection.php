<?php

declare(strict_types=1);

namespace Palacios\Framework\Database;

use PDO;

final class DatabaseConnection
{
    public static function create(DatabaseOptions $options): PDO
    {
        return new PDO(
            $options->getDsn(),
            $options->getUsername(),
            $options->getPassword(),
            $options->getOptions(),
        );
    }
}
