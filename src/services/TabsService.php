<?php

require_once __DIR__ . '/../db/Db.php';

class TabsService
{
    public static function listTabs(string $portalId, string $entityTypeId): array
    {
        $pdo = Db::pdo();

        $stmt = $pdo->prepare("
            SELECT id, title, link, order_index
            FROM tabs
            WHERE portal_id = :portal_id
              AND entity_type_id = :entity_type_id
            ORDER BY order_index ASC, id ASC
        ");

        $stmt->execute([
            ':portal_id' => $portalId,
            ':entity_type_id' => $entityTypeId,
        ]);

        return $stmt->fetchAll();
    }
}