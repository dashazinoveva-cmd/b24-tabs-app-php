<?php

require_once __DIR__ . '/../services/PortalService.php';
require_once __DIR__ . '/../services/Logger.php';

class InstallController
{
    public function handle(string $method): void
    {
        header('Content-Type: text/html; charset=utf-8');

        if ($method === 'HEAD') {
            http_response_code(200);
            exit;
        }

        if ($method === 'GET') {
            http_response_code(200);
            echo "<b>/install работает</b>";
            exit;
        }

        if ($method !== 'POST') {
            http_response_code(405);
            echo "Method not allowed";
            exit;
        }

        $post = $_POST;

        try {
            PortalService::upsertPortal([
                'member_id'      => $post['member_id'] ?? null,
                'access_token'   => $post['AUTH_ID'] ?? null,
                'refresh_token'  => $post['REFRESH_ID'] ?? null,
                'expires_in'     => $post['AUTH_EXPIRES'] ?? 0,
                'server_endpoint'=> $post['SERVER_ENDPOINT'] ?? null,
            ]);

            Logger::log("INSTALL SUCCESS", [
                'member_id' => $post['member_id'] ?? null,
            ]);

        } catch (Throwable $e) {
            Logger::log("INSTALL ERROR", [
                'error' => $e->getMessage(),
                'post_keys' => array_keys($post),
            ]);
        }

        http_response_code(302);
        header('Location: /settings');
        exit;
    }
}