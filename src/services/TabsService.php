<?php

require_once __DIR__ . '/../db/Db.php';
require_once __DIR__ . '/Logger.php';

class TabsService
{
    public static function listTabs(string $portalId, string $entityTypeId): array
    {
        $pdo = Db::pdo();

        $stmt = $pdo->prepare("
            SELECT id, portal_id, entity_type_id, title, link, order_index, placement_id
            FROM tabs
            WHERE portal_id = :portal_id
              AND entity_type_id = :entity_type_id
            ORDER BY order_index ASC, id ASC
        ");

        $stmt->execute([
            ':portal_id' => $portalId,
            ':entity_type_id' => $entityTypeId,
        ]);

        $rows = $stmt->fetchAll();

        Logger::log("TabsService.listTabs", [
            "portal_id" => $portalId,
            "entity_type_id" => $entityTypeId,
            "rows_count" => count($rows),
            "rows" => $rows,
        ]);

        return $rows;
    }

    public static function getTabById(int $tabId): ?array
    {
        $pdo = Db::pdo();

        $stmt = $pdo->prepare("
            SELECT id, portal_id, entity_type_id, title, link, order_index, placement_id
            FROM tabs
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $tabId]);
        $row = $stmt->fetch() ?: null;

        Logger::log("TabsService.getTabById", [
            "tab_id" => $tabId,
            "row" => $row,
        ]);

        return $row;
    }
}