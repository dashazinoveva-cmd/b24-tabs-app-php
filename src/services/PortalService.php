<?php

require_once __DIR__ . '/../db/Db.php';

class PortalService
{
    public static function upsertPortal(array $payload): void
    {
        $pdo = Db::pdo();

        $memberId = trim((string)($payload['member_id'] ?? ''));
        if ($memberId === '') {
            throw new RuntimeException("member_id missing");
        }

        // В install-посте у тебя домена портала НЕТ — это ок.
        // Поэтому храним nullable / пусто.
        $domain = trim((string)($payload['DOMAIN'] ?? ($payload['domain'] ?? '')));
        $domain = $domain !== '' ? $domain : null;

        $accessToken  = trim((string)($payload['AUTH_ID'] ?? ''));
        $refreshToken = trim((string)($payload['REFRESH_ID'] ?? ''));

        $serverEndpoint = trim((string)($payload['SERVER_ENDPOINT'] ?? ''));
        $serverEndpoint = $serverEndpoint !== '' ? $serverEndpoint : null;

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
            ':domain' => $domain,

            ':access_token' => ($accessToken !== '' ? $accessToken : null),
            ':refresh_token' => ($refreshToken !== '' ? $refreshToken : null),

            ':application_token' => null,
            ':scope' => null,
            ':user_id' => null,
            ':client_endpoint' => null,

            ':server_endpoint' => $serverEndpoint,
        ]);
    }
}