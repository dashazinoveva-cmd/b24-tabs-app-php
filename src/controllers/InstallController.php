<?php
require_once __DIR__ . '/../services/BitrixApi.php';
require_once __DIR__ . '/../services/PortalService.php';
require_once __DIR__ . '/../services/Logger.php';

class InstallController
{
    public function handle(string $method): void
    {
        // Bitrix иногда делает HEAD
        if ($method === 'HEAD') {
            http_response_code(200);
            exit;
        }

        // Если install открыли вручную в браузере
        if ($method === 'GET') {
            header('Location: /settings');
            exit;
        }

        // Install должен быть POST
        if ($method !== 'POST') {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(405);
            echo 'Method not allowed';
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');

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
            $portal = \PortalRepository::findByMemberId($data['member_id'] ?? '');

            $appUrl = rtrim((require __DIR__ . '/../config/app.php')['app_url'], '/');

            \BitrixApi::call($portal, 'placement.bind', [
                'PLACEMENT' => 'LEFT_MENU',
                'HANDLER'   => $appUrl . '/settings',
                'TITLE'     => 'Настройки табов',
            ]);

            Logger::log("INSTALL SUCCESS", [
                'member_id' => $data['member_id'] ?? null,
                'domain'    => $domain,
            ]);

            // ВАЖНО: Bitrix ждёт просто OK
            http_response_code(200);
            echo 'OK';
            exit;

        } catch (Throwable $e) {

            Logger::log("INSTALL ERROR", [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data),
            ]);

            http_response_code(500);
            echo 'ERROR';
            exit;
        }
    }
}