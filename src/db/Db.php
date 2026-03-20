<?php

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $configPath = __DIR__ . '/../config/app.php';
        if (!file_exists($configPath)) {
            throw new RuntimeException("DB config not found: {$configPath}");
        }

        $config = require $configPath;

        $host = $config['db_host'] ?? null;
        $port = $config['db_port'] ?? '3306';
        $name = $config['db_name'] ?? null;
        $user = $config['db_user'] ?? null;
        $pass = $config['db_pass'] ?? null;

        if (!$host || !$name || !$user) {
            throw new RuntimeException('MySQL config is incomplete in config/app.php');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $host,
            $port,
            $name
        );

        self::$pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$pdo;
    }
}