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
        $driver = (string)($config['db_driver'] ?? 'sqlite');

        if ($driver === 'sqlite') {
            self::$pdo = self::connectSqlite($config);
            self::migrateSqlite();
            return self::$pdo;
        }

        if ($driver === 'pgsql') {
            self::$pdo = self::connectPostgres($config);
            return self::$pdo;
        }

        throw new RuntimeException("Unsupported db_driver: {$driver}");
    }

    private static function connectSqlite(array $config): PDO
    {
        $dbPath = $config['db_path'] ?? null;
        if (!$dbPath || !is_string($dbPath)) {
            throw new RuntimeException('db_path is not set in config/app.php');
        }

        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    private static function connectPostgres(array $config): PDO
    {
        $host = $config['db_host'] ?? null;
        $port = $config['db_port'] ?? '5432';
        $name = $config['db_name'] ?? null;
        $user = $config['db_user'] ?? null;
        $pass = $config['db_pass'] ?? null;

        if (!$host || !$name || !$user) {
            throw new RuntimeException('PostgreSQL config is incomplete in config/app.php');
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $host,
            $port,
            $name
        );

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private static function migrateSqlite(): void
    {
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS portals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                member_id TEXT NOT NULL UNIQUE,
                domain TEXT NULL,
                access_token TEXT NULL,
                refresh_token TEXT NULL,
                application_token TEXT NULL,
                scope TEXT NULL,
                user_id INTEGER NULL,
                client_endpoint TEXT NULL,
                server_endpoint TEXT NULL,
                created_at TEXT NOT NULL DEFAULT (datetime('now')),
                updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            );
        ");

        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS tabs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                portal_id TEXT NOT NULL,
                entity_type_id TEXT NOT NULL,
                title TEXT NOT NULL,
                link TEXT NOT NULL DEFAULT '',
                order_index INTEGER NOT NULL DEFAULT 0,
                placement_id TEXT NULL,
                UNIQUE(portal_id, entity_type_id, title)
            );
        ");

        self::$pdo->exec("
            CREATE INDEX IF NOT EXISTS ix_tabs_portal_entity
            ON tabs (portal_id, entity_type_id);
        ");

        $tabsCols = self::$pdo->query("PRAGMA table_info(tabs)")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $hasPlacement = false;

        foreach ($tabsCols as $col) {
            if (($col['name'] ?? '') === 'placement_id') {
                $hasPlacement = true;
                break;
            }
        }

        if (!$hasPlacement) {
            self::$pdo->exec("ALTER TABLE tabs ADD COLUMN placement_id TEXT NULL");
        }

        self::$pdo->exec("
            CREATE TRIGGER IF NOT EXISTS trg_portals_updated_at
            AFTER UPDATE ON portals
            FOR EACH ROW
            BEGIN
                UPDATE portals
                SET updated_at = datetime('now')
                WHERE id = OLD.id;
            END;
        ");
    }
}