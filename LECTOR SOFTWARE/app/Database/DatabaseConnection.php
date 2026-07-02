<?php

namespace App\Database;

use PDO;
use PDOException;

class DatabaseConnection {
    private static ?PDO $connection = null;

    public static function get(): PDO {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $configFile = dirname(dirname(__DIR__)) . '/config/database.php';
        $config = file_exists($configFile) ? require $configFile : [];

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? '5432';
        $database = $config['database'] ?? 'lector_resoluciones';
        $username = $config['username'] ?? 'postgres';
        $password = $config['password'] ?? '';

        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";

        try {
            self::$connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new PDOException('No se pudo conectar a PostgreSQL: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        return self::$connection;
    }
}
