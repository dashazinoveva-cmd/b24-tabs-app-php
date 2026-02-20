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
        // Если client_endpoint нет, но есть токен — попробуем получить через app.info
        if ($clientEndpoint === '' && $accessToken !== '' && $serverEndpoint !== '') {
            $url = rtrim($serverEndpoint, '/') . '/app.info.json';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(['auth' => $accessToken]),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
            ]);

            $raw = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($raw, true);
            if (isset($data['result']['client_endpoint'])) {
                $clientEndpoint = $data['result']['client_endpoint'];
                $domain = parse_url($clientEndpoint, PHP_URL_HOST) ?? '';
            }
        }
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
                domain = COALESCE(excluded.domain, portals.domain),
                access_token = COALESCE(excluded.access_token, portals.access_token),
                refresh_token = COALESCE(excluded.refresh_token, portals.refresh_token),
                application_token = COALESCE(excluded.application_token, portals.application_token),
                scope = COALESCE(excluded.scope, portals.scope),
                user_id = COALESCE(excluded.user_id, portals.user_id),
                client_endpoint = COALESCE(excluded.client_endpoint, portals.client_endpoint),
                server_endpoint = COALESCE(excluded.server_endpoint, portals.server_endpoint),
                updated_at = datetime('now')
        ");
        $existing = null;
        $stmtCheck = $pdo->prepare("SELECT access_token, refresh_token FROM portals WHERE member_id = :m LIMIT 1");
        $stmtCheck->execute([':m' => $memberId]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        $stmt->execute([
            ':member_id' => $memberId,
            ':domain' => $domain !== '' ? $domain : null,
            ':access_token' => $accessToken !== '' 
                ? $accessToken 
                : ($existing['access_token'] ?? null),

            ':refresh_token' => $refreshToken !== '' 
                ? $refreshToken 
                : ($existing['refresh_token'] ?? null),

            ':application_token' => $payload['application_token'] ?? $payload['APPLICATION_TOKEN'] ?? null,
            ':scope' => $payload['scope'] ?? $payload['SCOPE'] ?? null,
            ':user_id' => isset($payload['user_id']) ? (int)$payload['user_id'] : null,

            ':client_endpoint' => $clientEndpoint !== '' ? $clientEndpoint : null,
            ':server_endpoint' => $clientEndpoint !== '' ? $clientEndpoint : ($serverEndpoint !== '' ? $serverEndpoint : null),
        ]);
    }
}