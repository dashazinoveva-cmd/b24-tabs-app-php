<?php

class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;

        $config = require __DIR__ . '/../config/app.php';
        $dbPath = $config['db_path'];

        self::$pdo = new PDO('sqlite:' . $dbPath);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        self::migrate();

        return self::$pdo;
    }

    private static function migrate(): void
    {
        // 1) создаём таблицы, если их ещё нет
        $sql = "
        CREATE TABLE IF NOT EXISTS portals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            member_id TEXT NOT NULL UNIQUE,
            domain TEXT NOT NULL,
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

        CREATE INDEX IF NOT EXISTS ix_tabs_portal_entity ON tabs (portal_id, entity_type_id);
        ";
        self::$pdo->exec($sql);

        // 2) если tabs уже существовала раньше — добавляем колонку placement_id через ALTER
        //    (CREATE TABLE IF NOT EXISTS НЕ добавляет новые колонки)
        $cols = self::$pdo->query("PRAGMA table_info(tabs)")->fetchAll(PDO::FETCH_ASSOC);
        $hasPlacement = false;

        foreach ($cols as $c) {
            if (($c['name'] ?? '') === 'placement_id') {
                $hasPlacement = true;
                break;
            }
        }

        if (!$hasPlacement) {
            self::$pdo->exec("ALTER TABLE tabs ADD COLUMN placement_id TEXT NULL");
        }
    }
}