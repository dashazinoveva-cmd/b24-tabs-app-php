<?php

require_once __DIR__ . '/../db/Db.php';

class PortalService
{
    public static function upsertPortal(array $payload): void
    {
        $pdo = Db::pdo();

        $memberId = (string)($payload['member_id'] ?? $payload['MEMBER_ID'] ?? '');
        if ($memberId === '') {
            throw new RuntimeException('member_id missing');
        }

        $accessToken = (string)($payload['access_token'] ?? $payload['AUTH_ID'] ?? '');
        $refreshToken = (string)($payload['refresh_token'] ?? $payload['REFRESH_ID'] ?? '');

        $serverEndpoint = (string)($payload['server_endpoint'] ?? $payload['SERVER_ENDPOINT'] ?? '');
        $clientEndpoint = (string)($payload['client_endpoint'] ?? $payload['CLIENT_ENDPOINT'] ?? '');
        $domain = (string)($payload['domain'] ?? $payload['DOMAIN'] ?? '');

        if ($domain === '' && $clientEndpoint !== '') {
            $host = parse_url($clientEndpoint, PHP_URL_HOST);
            if (is_string($host)) {
                $domain = $host;
            }
        }

        if ($clientEndpoint === '' && $domain !== '') {
            $clientEndpoint = 'https://' . $domain . '/rest/';
        }

        $existing = null;
        $stmtCheck = $pdo->prepare("
            SELECT access_token, refresh_token
            FROM portals
            WHERE member_id = :member_id
            LIMIT 1
        ");
        $stmtCheck->execute([
            ':member_id' => $memberId,
        ]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            INSERT INTO portals (
                member_id,
                domain,
                access_token,
                refresh_token,
                application_token,
                scope,
                user_id,
                client_endpoint,
                server_endpoint,
                created_at,
                updated_at
            ) VALUES (
                :member_id,
                :domain,
                :access_token,
                :refresh_token,
                :application_token,
                :scope,
                :user_id,
                :client_endpoint,
                :server_endpoint,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                domain = COALESCE(VALUES(domain), domain),
                access_token = COALESCE(VALUES(access_token), access_token),
                refresh_token = COALESCE(VALUES(refresh_token), refresh_token),
                application_token = COALESCE(VALUES(application_token), application_token),
                scope = COALESCE(VALUES(scope), scope),
                user_id = COALESCE(VALUES(user_id), user_id),
                client_endpoint = COALESCE(VALUES(client_endpoint), client_endpoint),
                server_endpoint = COALESCE(VALUES(server_endpoint), server_endpoint),
                updated_at = NOW()
        ");

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
            ':server_endpoint' => $serverEndpoint !== '' ? $serverEndpoint : null,
        ]);
    }
}