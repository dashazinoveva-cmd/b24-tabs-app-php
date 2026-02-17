<?php

require_once __DIR__ . '/../db/Db.php';

class PortalService
{
    public static function upsertPortal(array $payload): void
    {
        $pdo = Db::pdo();

        $memberId = (string)($payload['member_id'] ?? '');

        $serverEndpoint = (string)($payload['SERVER_ENDPOINT'] ?? '');
        $domain = '';

        if ($serverEndpoint) {
            $parsed = parse_url($serverEndpoint);
            $domain = $parsed['host'] ?? '';
        }

        if ($memberId === '' || $domain === '') {
            throw new RuntimeException("member_id/domain missing");
        }

        if ($memberId === '' || $domain === '') {
            throw new RuntimeException("member_id/domain missing");
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
            ':domain' => $domain,
            ':access_token' => $payload['access_token'] ?? null,
            ':refresh_token' => $payload['refresh_token'] ?? null,
            ':application_token' => $payload['application_token'] ?? null,
            ':scope' => $payload['scope'] ?? null,
            ':user_id' => isset($payload['user_id']) ? (int)$payload['user_id'] : null,
            ':client_endpoint' => $payload['client_endpoint'] ?? null,
            ':server_endpoint' => $payload['server_endpoint'] ?? null,
        ]);
    }
}