<?php

require_once __DIR__ . '/../db/Db.php';

class PortalService
{
    /**
     * Принимает payload как из install (AUTH_ID/REFRESH_ID/SERVER_ENDPOINT и т.п.),
     * так и “нормализованный” (access_token/refresh_token/domain/client_endpoint).
     */
    public static function upsertPortal(array $payload): void
    {
        $pdo = Db::pdo();

        // 1) обязательное
        $memberId = (string)($payload['member_id'] ?? $payload['MEMBER_ID'] ?? '');
        if ($memberId === '') {
            throw new RuntimeException("member_id missing");
        }

        // 2) токены (Bitrix в install шлёт AUTH_ID / REFRESH_ID)
        $accessToken  = (string)($payload['access_token'] ?? $payload['AUTH_ID'] ?? '');
        $refreshToken = (string)($payload['refresh_token'] ?? $payload['REFRESH_ID'] ?? '');

        // 3) endpoints
        $serverEndpoint = (string)($payload['server_endpoint'] ?? $payload['SERVER_ENDPOINT'] ?? '');

        // client_endpoint Bitrix иногда присылает, иногда нет.
        $clientEndpoint = (string)($payload['client_endpoint'] ?? $payload['CLIENT_ENDPOINT'] ?? '');

        // 4) domain — НЕ берём из server_endpoint (это oauth.*), нужен домен портала
        $domain = (string)($payload['domain'] ?? $payload['DOMAIN'] ?? '');

        // если домен не пришёл, попробуем вытащить из client_endpoint
        if ($domain === '' && $clientEndpoint !== '') {
            $host = parse_url($clientEndpoint, PHP_URL_HOST);
            if (is_string($host)) $domain = $host;
        }

        // если client_endpoint не пришёл, но домен есть — соберём его сами
        if ($clientEndpoint === '' && $domain !== '') {
            $clientEndpoint = 'https://' . $domain . '/rest/';
        }

        // Нормально сохранять даже если domain/client_endpoint пока пустые (потом допишем)
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
            ':domain' => $domain !== '' ? $domain : null,
            ':access_token' => $accessToken !== '' ? $accessToken : null,
            ':refresh_token' => $refreshToken !== '' ? $refreshToken : null,

            ':application_token' => $payload['application_token'] ?? $payload['APPLICATION_TOKEN'] ?? null,
            ':scope' => $payload['scope'] ?? $payload['SCOPE'] ?? null,
            ':user_id' => isset($payload['user_id']) ? (int)$payload['user_id'] : null,

            ':client_endpoint' => $clientEndpoint !== '' ? $clientEndpoint : null,
            ':server_endpoint' => $clientEndpoint !== '' ? $clientEndpoint : null,
        ]);
    }
}