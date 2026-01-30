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
            UNIQUE(portal_id, entity_type_id, title)
        );

        CREATE INDEX IF NOT EXISTS ix_tabs_portal_entity ON tabs (portal_id, entity_type_id);
        ";
        self::$pdo->exec($sql);
    }
}