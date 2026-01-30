<?php

require_once __DIR__ . '/../services/PortalService.php';
require_once __DIR__ . '/../services/Logger.php';

class InstallController
{
    public function handle(string $method): void
    {
        header('Content-Type: text/html; charset=utf-8');

        // Bitrix при проверках может слать HEAD — ответим 200
        if ($method === 'HEAD') {
            http_response_code(200);
            exit;
        }

        // Для удобства: GET показывает страницу "install работает"
        if ($method === 'GET') {
            http_response_code(200);
            echo "<b>/install работает</b><br><small>Жду POST от Bitrix24 при установке приложения.</small>";
            exit;
        }

        if ($method !== 'POST') {
            http_response_code(405);
            echo "Method not allowed";
            exit;
        }

        $post = $_POST;

        try {
            PortalService::upsertPortal($post);
            Logger::log("Portal saved", [
                'member_id' => $post['member_id'] ?? null,
                'domain' => $post['domain'] ?? null,
            ]);
        } catch (Throwable $e) {
            Logger::log("Portal save error", [
                'error' => $e->getMessage(),
                'post_keys' => array_keys($post),
            ]);
            // Можно вернуть 500, но лучше 200/302 чтобы установка не падала
        }

        Logger::log("INSTALL POST received", [
            'remote' => $_SERVER['REMOTE_ADDR'] ?? '',
            'post_keys' => array_keys($post),
        ]);

        // ✅ После установки — редирект в настройки
        http_response_code(302);
        header('Location: /settings');
        exit;
    }
}