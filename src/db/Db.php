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
        $dbPath = $config['db_path'] ?? null;
        if (!$dbPath || !is_string($dbPath)) {
            throw new RuntimeException("db_path is not set in config/app.php");
        }

        // гарантируем, что папка для sqlite файла существует
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        self::$pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // на всякий — включаем внешние ключи (если понадобятся)
        self::$pdo->exec("PRAGMA foreign_keys = ON");

        self::migrate();

        return self::$pdo;
    }

    private static function migrate(): void
    {
        // --- БАЗОВЫЕ ТАБЛИЦЫ (создаём, если нет) ---
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

        self::$pdo->exec("CREATE INDEX IF NOT EXISTS ix_tabs_portal_entity ON tabs (portal_id, entity_type_id);");

        // --- FIX 1: если portals.domain когда-то был NOT NULL — пересоберём корректно ---
        $portalCols = self::$pdo->query("PRAGMA table_info(portals)")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $domainWasNotNull = false;
        foreach ($portalCols as $col) {
            if (($col['name'] ?? '') === 'domain' && (int)($col['notnull'] ?? 0) === 1) {
                $domainWasNotNull = true;
                break;
            }
        }

        if ($domainWasNotNull) {
            self::$pdo->beginTransaction();
            try {
                self::$pdo->exec("
                    CREATE TABLE IF NOT EXISTS portals_new (
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

                // ВАЖНО: копируем по именам колонок (а не SELECT *)
                self::$pdo->exec("
                    INSERT INTO portals_new (
                        id, member_id, domain, access_token, refresh_token, application_token, scope,
                        user_id, client_endpoint, server_endpoint, created_at, updated_at
                    )
                    SELECT
                        id, member_id, domain, access_token, refresh_token, application_token, scope,
                        user_id, client_endpoint, server_endpoint, created_at, updated_at
                    FROM portals;
                ");

                self::$pdo->exec("DROP TABLE portals;");
                self::$pdo->exec("ALTER TABLE portals_new RENAME TO portals;");

                self::$pdo->commit();
            } catch (Throwable $e) {
                self::$pdo->rollBack();
                throw $e;
            }
        }

        // --- FIX 2: если tabs была старой версией без placement_id — добавим колонку ---
        $tabsCols = self::$pdo->query("PRAGMA table_info(tabs)")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $hasPlacement = false;
        foreach ($tabsCols as $c) {
            if (($c['name'] ?? '') === 'placement_id') {
                $hasPlacement = true;
                break;
            }
        }
        if (!$hasPlacement) {
            self::$pdo->exec("ALTER TABLE tabs ADD COLUMN placement_id TEXT NULL");
        }

        // --- (опционально) триггер на updated_at для portals ---
        // если хочешь автообновление updated_at при update (SQLite не умеет ON UPDATE)
        self::$pdo->exec("
            CREATE TRIGGER IF NOT EXISTS trg_portals_updated_at
            AFTER UPDATE ON portals
            FOR EACH ROW
            BEGIN
                UPDATE portals SET updated_at = datetime('now') WHERE id = OLD.id;
            END;
        ");
    }
}