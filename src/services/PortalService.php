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

        $stmt = $pdo->prepare("
            INSERT INTO portals (
                member_id, domain, access_token, refresh_token, application_token,
                scope, user_id, client_endpoint, server_endpoint, updated_at
            ) VALUES (
                :member_id, :domain, :access_token, :refresh_token, :application_token,
                :scope, :user_id, :client_endpoint, :server_endpoint, datetime('now')
            )
            ON CONFLICT(member_id) DO UPDATE SET
                domain=excluded.domain,
                access_token=excluded.access_token,
                refresh_token=excluded.refresh_token,
                application_token=excluded.application_token,
                scope=excluded.scope,
                user_id=excluded.user_id,
                client_endpoint=excluded.client_endpoint,
                server_endpoint=excluded.server_endpoint,
                updated_at=datetime('now')
        ");

        $stmt->execute([
            ':member_id' => $memberId,

            // domain временно = member_id
            ':domain' => $memberId,

            // правильные поля Bitrix
            ':access_token' => $payload['AUTH_ID'] ?? null,
            ':refresh_token' => $payload['REFRESH_ID'] ?? null,

            ':application_token' => null,
            ':scope' => null,
            ':user_id' => null,
            ':client_endpoint' => null,

            ':server_endpoint' => $payload['SERVER_ENDPOINT'] ?? null,
        ]);
    }
}