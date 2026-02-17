<?php

require_once __DIR__ . '/../db/Db.php';

class PortalService
{
    public static function upsertPortal(array $payload): void
    {
        $pdo = Db::pdo();

        // 1) member_id (у тебя он точно приходит)
        $memberId = (string)($payload['member_id'] ?? '');
        $memberId = trim($memberId);

        if ($memberId === '') {
            throw new RuntimeException("member_id missing");
        }

        // 2) ДОМЕН ПОРТАЛА (важно!)
        // Bitrix может прислать по-разному: DOMAIN / domain
        // В твоих логах обычно НЕТ domain => тогда берём из PLACEMENT_OPTIONS (там есть "any":"8/"),
        // то есть домена портала там тоже нет, поэтому fallback = HTTP_HOST (домен твоего сервера).
        $domain = (string)($payload['DOMAIN'] ?? '');
        $domain = trim($domain);

        if ($domain === '') {
            $domain = (string)($payload['domain'] ?? '');
            $domain = trim($domain);
        }

        // если вдруг прилетает full url — вытащим host
        if ($domain !== '' && str_contains($domain, '://')) {
            $parsed = parse_url($domain);
            $domain = (string)($parsed['host'] ?? $domain);
        }

        // fallback: домен твоего сервера (лучше чем member_id — не ломает curl)
        if ($domain === '') {
            $domain = (string)($_SERVER['HTTP_HOST'] ?? '');
            $domain = trim($domain);
        }

        if ($domain === '') {
            throw new RuntimeException("domain missing");
        }

        // 3) Токены из Bitrix (в install POST они приходят как AUTH_ID/REFRESH_ID)
        $accessToken  = isset($payload['AUTH_ID']) ? trim((string)$payload['AUTH_ID']) : '';
        $refreshToken = isset($payload['REFRESH_ID']) ? trim((string)$payload['REFRESH_ID']) : '';

        // SERVER_ENDPOINT тоже приходит (у тебя в логах есть)
        $serverEndpoint = isset($payload['SERVER_ENDPOINT']) ? trim((string)$payload['SERVER_ENDPOINT']) : null;

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

            // Если токены вдруг пустые — всё равно сохраним запись,
            // но EntitiesService потом сможет это проверить и сказать "token empty"
            ':access_token' => ($accessToken !== '' ? $accessToken : null),
            ':refresh_token' => ($refreshToken !== '' ? $refreshToken : null),

            ':application_token' => $payload['APPLICATION_TOKEN'] ?? null,
            ':scope' => $payload['scope'] ?? null,
            ':user_id' => isset($payload['user_id']) ? (int)$payload['user_id'] : null,
            ':client_endpoint' => $payload['client_endpoint'] ?? null,
            ':server_endpoint' => $serverEndpoint,
        ]);
    }
}