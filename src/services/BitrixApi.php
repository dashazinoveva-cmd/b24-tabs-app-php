<?php

require_once __DIR__ . '/../db/Db.php';

class BitrixApi
{
    public static function call(array $portal, string $method, array $params = []): array
    {
        return self::callWithRetry($portal, $method, $params, true);
    }

    private static function callWithRetry(array $portal, string $method, array $params, bool $allowRefresh): array
    {
        $token = (string)($portal['access_token'] ?? '');
        $endpoint = (string)($portal['client_endpoint'] ?? $portal['server_endpoint'] ?? '');

        if ($endpoint === '') throw new RuntimeException("client_endpoint/server_endpoint is empty");
        if ($token === '') throw new RuntimeException("access_token is empty");

        $base = rtrim($endpoint, "/");           // https://xxx.bitrix24.ru/rest
        $url  = $base . "/" . $method . ".json"; // .../rest/crm.type.list.json

        $payload = $params;
        $payload['auth'] = $token;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException("curl error: " . $err);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException("bitrix invalid json (http {$http}): " . $raw);
        }

        // если токен истек — пробуем refresh и повтор
        if ($http === 401 && $allowRefresh) {
            $msg = (string)($data['error_description'] ?? $data['error'] ?? '');
            if (stripos($msg, 'expired') !== false) {
                self::refreshToken($portal);
                // перечитаем портал из БД и повторим
                $pdo = Db::pdo();
                $stmt = $pdo->prepare("SELECT * FROM portals WHERE member_id = :m LIMIT 1");
                $stmt->execute([':m' => (string)$portal['member_id']]);
                $fresh = $stmt->fetch() ?: $portal;
                return self::callWithRetry($fresh, $method, $params, false);
            }
        }

        if ($http !== 200) {
            $msg = $data['error_description'] ?? ($data['error'] ?? $raw);
            throw new RuntimeException("bitrix http {$http}: " . $msg);
        }

        if (isset($data['error'])) {
            $msg = $data['error_description'] ?? $data['error'];
            throw new RuntimeException("bitrix error: " . $msg);
        }

        // ВАЖНО: возвращаем ВЕСЬ ответ (чтобы EntitiesService мог читать result/types)
        return $data;
    }

    private static function refreshToken(array $portal): void
    {
        $refresh = (string)($portal['refresh_token'] ?? '');
        $memberId = (string)($portal['member_id'] ?? '');
        if ($refresh === '') throw new RuntimeException("refresh_token is empty");
        if ($memberId === '') throw new RuntimeException("member_id is empty");

        $config = require __DIR__ . '/../config/app.php';
        $clientId = (string)($config['b24_client_id'] ?? $config['client_id'] ?? '');
        $clientSecret = (string)($config['b24_client_secret'] ?? $config['client_secret'] ?? '');

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException("client_id/client_secret not set in config/app.php");
        }

        // стандартный OAuth Bitrix24
        $oauthUrl = "https://oauth.bitrix.info/oauth/token/";

        $payload = [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refresh,
        ];

        $ch = curl_init($oauthUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException("oauth curl error: " . $err);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException("oauth invalid json (http {$http}): " . $raw);
        }
        if ($http !== 200) {
            $msg = $data['error_description'] ?? ($data['error'] ?? $raw);
            throw new RuntimeException("oauth http {$http}: " . $msg);
        }

        $newAccess = (string)($data['access_token'] ?? '');
        $newRefresh = (string)($data['refresh_token'] ?? '');
        if ($newAccess === '') throw new RuntimeException("oauth: access_token missing");

        $pdo = Db::pdo();
        $stmt = $pdo->prepare("
            UPDATE portals
               SET access_token = :a,
                   refresh_token = :r,
                   updated_at = datetime('now')
             WHERE member_id = :m
        ");
        $stmt->execute([
            ':a' => $newAccess,
            ':r' => $newRefresh !== '' ? $newRefresh : $refresh,
            ':m' => $memberId,
        ]);
    }
}