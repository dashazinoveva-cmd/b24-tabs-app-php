<?php

require_once __DIR__ . '/../db/Db.php';

class PortalService
{
    public static function upsertPortal(array $payload): void
    {
        $pdo = Db::pdo();

        $memberId = (string)($payload['member_id'] ?? '');
        if ($memberId === '') {
            throw new RuntimeException("member_id missing");
        }

        $accessToken  = (string)($payload['AUTH_ID'] ?? '');
        $refreshToken = (string)($payload['REFRESH_ID'] ?? '');

        if ($accessToken === '') {
            throw new RuntimeException("AUTH_ID missing");
        }

        // 👉 ВАЖНО: правильный REST endpoint строим из домена портала
        // Домен можно получить из BX24.getAuth().domain
        // Но при install его нет — поэтому строим из member_id
        // В твоем случае портал messenger-test.bitrix24.ru

        // Если хочешь жестко зафиксировать:
        $domain = "messenger-test.bitrix24.ru";

        $serverEndpoint = "https://{$domain}/rest/";

        $stmt = $pdo->prepare("
            INSERT INTO portals (
                member_id, domain, access_token, refresh_token,
                server_endpoint, updated_at
            ) VALUES (
                :member_id, :domain, :access_token, :refresh_token,
                :server_endpoint, datetime('now')
            )
            ON CONFLICT(member_id) DO UPDATE SET
                domain=excluded.domain,
                access_token=excluded.access_token,
                refresh_token=excluded.refresh_token,
                server_endpoint=excluded.server_endpoint,
                updated_at=datetime('now')
        ");

        $stmt->execute([
            ':member_id'      => $memberId,
            ':domain'         => $domain,
            ':access_token'   => $accessToken,
            ':refresh_token'  => $refreshToken,
            ':server_endpoint'=> $serverEndpoint,
        ]);
    }
}