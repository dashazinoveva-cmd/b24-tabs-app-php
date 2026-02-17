<?php

class BitrixApi
{
    public static function call(array $portalRow, string $method, array $params = []): array
    {
        $endpoint = trim((string)($portalRow['server_endpoint'] ?? ''));
        $token    = trim((string)($portalRow['access_token'] ?? ''));

        if ($endpoint === '') {
            throw new RuntimeException("server_endpoint is empty");
        }
        if ($token === '') {
            throw new RuntimeException("access_token is empty for member_id=" . ($portalRow['member_id'] ?? ''));
        }

        $base = rtrim($endpoint, '/'); // https://oauth.bitrix24.tech/rest
        $url  = $base . '/' . $method . '.json';

        // Bitrix принимает auth либо в params, либо query — надёжнее в params
        $params['auth'] = $token;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException("curl error: " . $err);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException("bitrix bad json (http {$code}): " . $raw);
        }

        if ($code >= 400) {
            $msg = $data['error_description'] ?? ($data['error'] ?? 'HTTP ' . $code);
            throw new RuntimeException("bitrix http {$code}: {$msg}");
        }

        if (isset($data['error'])) {
            $msg = $data['error_description'] ?? $data['error'];
            throw new RuntimeException("bitrix error: {$msg}");
        }

        return $data;
    }
}