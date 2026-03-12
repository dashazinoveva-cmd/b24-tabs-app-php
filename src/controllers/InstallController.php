<?php

require_once __DIR__ . '/../services/PortalService.php';
require_once __DIR__ . '/../services/Logger.php';

class InstallController
{
    public function handle(string $method): void
    {
        if ($method === 'HEAD') {
            http_response_code(200);
            exit;
        }

        if ($method === 'GET') {
            http_response_code(302);
            header('Location: /settings');
            exit;
        }

        if ($method !== 'POST') {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(405);
            echo 'Method not allowed';
            exit;
        }

        $data = $_REQUEST;

        $domain = $data['DOMAIN'] ?? $data['domain'] ?? null;

        if (!$domain) {
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            $host = parse_url($referer, PHP_URL_HOST);
            if ($host) {
                $domain = $host;
            }
        }

        if (!$domain) {
            $domain = 'unknown';
        }

        try {
            PortalService::upsertPortal([
                'member_id'       => $data['member_id'] ?? null,
                'access_token'    => $data['AUTH_ID'] ?? null,
                'refresh_token'   => $data['REFRESH_ID'] ?? null,
                'server_endpoint' => $data['SERVER_ENDPOINT'] ?? null,
                'domain'          => $domain,
            ]);

            Logger::log("INSTALL SUCCESS", [
                'member_id' => $data['member_id'] ?? null,
                'domain'    => $domain,
            ]);

            http_response_code(302);
            header('Location: /settings');
            exit;

        } catch (Throwable $e) {
            Logger::log("INSTALL ERROR", [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data),
            ]);

            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(500);
            echo 'ERROR';
            exit;
        }
    }
}