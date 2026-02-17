<?php

require_once __DIR__ . '/../db/Db.php';

class PortalRepository
{
    public static function findByMemberId(string $memberId): ?array
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare("SELECT * FROM portals WHERE member_id = :m LIMIT 1");
        $stmt->execute([':m' => $memberId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}