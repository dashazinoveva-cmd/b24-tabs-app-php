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

    public static function buildHandlerUrl(int $tabId): string
    {
        $config = require __DIR__ . '/../config/app.php';
        $base = rtrim((string)($config['app_url'] ?? ''), '/');

        if ($base === '') {
            throw new RuntimeException("app_url is not set in config/app.php");
        }

        return $base . "/crm-tab?tab_id=" . urlencode((string)$tabId);
    }

    // ✅ единая сигнатура: portal, entityTypeId, tabId, title
    public static function bindTab(array $portal, string $entityTypeId, int $tabId, string $title): string
    {
        $placement = self::placementName($entityTypeId);
        if (!$placement) {
            throw new RuntimeException("Unsupported entity_type_id={$entityTypeId} for placement.bind");
        }

        $handler = self::buildHandlerUrl($tabId);

        $resp = BitrixApi::call($portal, 'placement.bind', [
            'PLACEMENT' => $placement,
            'HANDLER'   => $handler,
            'TITLE'     => $title,
        ]);

        Logger::log("placement.bind raw", [
            "entity_type_id" => $entityTypeId,
            "tab_id" => $tabId,
            "title" => $title,
            "resp" => $resp,
        ]);

        // ✅ ВАЖНО: в Bitrix ID бинда обычно лежит прямо в result (число/строка)
        if (!array_key_exists('result', $resp)) {
            throw new RuntimeException("placement.bind: no result: " . json_encode($resp, JSON_UNESCAPED_UNICODE));
        }

        return (string)$resp['result']; // ✅ вот ключевая строка
    }

    public static function unbind(array $portal, string $placementId): void
    {
        if ($placementId === '') return;

        BitrixApi::call($portal, 'placement.unbind', [
            'ID' => $placementId,
        ]);
    }
}