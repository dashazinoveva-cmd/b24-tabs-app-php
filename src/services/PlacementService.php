<?php

require_once __DIR__ . '/BitrixApi.php';
require_once __DIR__ . '/Logger.php';

class PlacementService
{
    public static function placementName(string $entityTypeId): ?string
    {
        return match ($entityTypeId) {
            'deal'    => 'CRM_DEAL_DETAIL_TAB',
            'lead'    => 'CRM_LEAD_DETAIL_TAB',
            'contact' => 'CRM_CONTACT_DETAIL_TAB',
            'company' => 'CRM_COMPANY_DETAIL_TAB',
            default   => null,
        };
    }

    private static function getBaseUrl(): string
    {
        // Учитываем прокси (часто в проде HTTPS “снаружи”, но PHP видит HTTP)
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
        $scheme = $proto
            ? strtolower(trim(explode(',', $proto)[0]))
            : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');

        // Хост тоже может приходить через прокси
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '');
        $host = trim(explode(',', $host)[0]);

        if (!$host) {
            throw new RuntimeException("Cannot build handler URL: host is empty");
        }

        return $scheme . '://' . $host;
    }

    public static function buildHandlerUrl(int $tabId): string
    {
        $config = require __DIR__ . '/../config/app.php';
        $raw = trim((string)($config['app_url'] ?? ''));

        if ($raw === '') {
            throw new RuntimeException("app_url is not set in config/app.php");
        }

        // ВАЖНО: берём только scheme+host(+port), отбрасываем /settings и любые пути
        $u = parse_url($raw);
        if (!is_array($u) || empty($u['scheme']) || empty($u['host'])) {
            throw new RuntimeException("Invalid app_url: " . $raw);
        }

        $origin = $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? (':' . $u['port']) : '');

        return $origin . '/crm-tab?tab_id=' . urlencode((string)$tabId);
    }

        public static function bindTab(array $portal, string $entityTypeId, int $tabId, string $title): string
    {
        $placement = self::placementName($entityTypeId);
        if (!$placement) {
            throw new RuntimeException("Unsupported entity_type_id={$entityTypeId} for placement.bind");
        }

        $handler = self::buildHandlerUrl($tabId);
        Logger::log("placement.debug", [
            "entityTypeId_type" => gettype($entityTypeId),
            "entityTypeId_value" => $entityTypeId,
            "placement_value" => $placement,
            "placement_type" => gettype($placement),
        ]);
        $resp = BitrixApi::call($portal, 'placement.bind', [
            'PLACEMENT' => $placement,
            'HANDLER'   => $handler,
            'TITLE'     => $title,
        ]);

        Logger::log("placement.bind raw", [
            "entity_type_id" => $entityTypeId,
            "tab_id" => $tabId,
            "title" => $title,
            "handler" => $handler,
            "resp" => $resp,
        ]);

        $result = $resp['result'] ?? null;

        // ✅ ВАЖНО: Bitrix возвращает true/false
        if ($result === true) {
            // вернём handler — его можно хранить и по нему же удалять
            return $handler;
        }

        throw new RuntimeException("placement.bind failed: " . json_encode($resp, JSON_UNESCAPED_UNICODE));
    }

    public static function unbind(array $portal, string $entityTypeId, string $handlerOrEmpty): void
    {
        $placement = self::placementName($entityTypeId);
        if (!$placement) return;

        // если handler пустой — можно удалить все хендлеры приложения в этом placement
        $params = ['PLACEMENT' => $placement];
        if ($handlerOrEmpty !== '') {
            $params['HANDLER'] = $handlerOrEmpty;
        }

        BitrixApi::call($portal, 'placement.unbind', $params);
    }
}