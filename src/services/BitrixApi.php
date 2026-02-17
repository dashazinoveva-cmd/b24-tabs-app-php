<?php

class BitrixApi
{
    /**
     * @param array $portal row из БД portals (member_id, access_token, server_endpoint, ...)
     * @param string $method например: user.current, crm.type.list
     * @param array $params параметры метода
     */
    public static function call(array $portal, string $method, array $params = []): array
    {
        $token = (string)($portal['access_token'] ?? '');
        $endpoint = (string)($portal['server_endpoint'] ?? '');

        if ($endpoint === '') {
            throw new RuntimeException("server_endpoint is empty");
        }
        if ($token === '') {
            throw new RuntimeException("access_token is empty");
        }

        // server_endpoint у тебя: https://oauth.bitrix24.tech/rest/
        // НУЖНО: https://oauth.bitrix24.tech/rest/user.current.json
        $base = rtrim($endpoint, "/");              // https://oauth.bitrix24.tech/rest
        $url  = $base . "/" . $method . ".json";    // .../rest/user.current.json

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

        if ($http !== 200) {
            // Bitrix иногда возвращает 404 + {error:..., error_description:...}
            $msg = $data['error_description'] ?? ($data['error'] ?? $raw);
            throw new RuntimeException("bitrix http {$http}: " . $msg);
        }

        if (isset($data['error'])) {
            $msg = $data['error_description'] ?? $data['error'];
            throw new RuntimeException("bitrix error: " . $msg);
        }

        return $data['result'] ?? $data;
    }
}