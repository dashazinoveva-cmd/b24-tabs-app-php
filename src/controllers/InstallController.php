<?php

require_once __DIR__ . '/../services/BitrixApi.php';
require_once __DIR__ . '/../services/PortalService.php';
require_once __DIR__ . '/../services/PortalRepository.php';
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
            header('Location: /settings');
            exit;
        }

        if ($method !== 'POST') {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(405);
            echo 'Method not allowed';
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');

        $data = $_REQUEST;

        $memberId = trim((string)($data['member_id'] ?? ''));
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
                'member_id'       => $memberId,
                'access_token'    => $data['AUTH_ID'] ?? null,
                'refresh_token'   => $data['REFRESH_ID'] ?? null,
                'server_endpoint' => $data['SERVER_ENDPOINT'] ?? null,
                'domain'          => $domain,
            ]);

            $portal = PortalRepository::findByMemberId($memberId);
            if (!$portal) {
                throw new RuntimeException("Portal was not saved for member_id={$memberId}");
            }

            $config = require __DIR__ . '/../config/app.php';
            $appUrl = rtrim((string)($config['app_url'] ?? ''), '/');

            if ($appUrl !== '') {
                try {
                    BitrixApi::call($portal, 'placement.bind', [
                        'PLACEMENT' => 'LEFT_MENU',
                        'HANDLER'   => $appUrl . '/settings',
                        'TITLE'     => 'Настройки табов',
                    ]);
                } catch (Throwable $e) {
                    if (stripos($e->getMessage(), 'Handler already binded') === false) {
                        throw $e;
                    }
                }
            }

            Logger::log('INSTALL SUCCESS', [
                'member_id' => $memberId,
                'domain' => $domain,
            ]);

            http_response_code(200);
            echo 'OK';
            exit;

        } catch (Throwable $e) {
            Logger::log('INSTALL ERROR', [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($data),
                'member_id' => $memberId,
                'domain' => $domain,
            ]);

            http_response_code(500);
            echo 'ERROR';
            exit;
        }
    }
}