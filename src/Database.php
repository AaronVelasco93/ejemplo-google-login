<?php
declare(strict_types=1);

namespace Example;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) return self::$connection;

        $host = \env_value('DB_HOST', '127.0.0.1');
        $port = \env_value('DB_PORT', '3306');
        $name = \env_value('DB_NAME');
        $user = \env_value('DB_USER');
        $password = \env_value('DB_PASSWORD');
        if ($name === '' || $user === '') throw new RuntimeException('Falta configurar la base de datos.');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        self::$connection = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$connection;
    }
}
