<?php

require_once __DIR__ . '/BitrixApi.php';

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

        // BitrixApi у тебя возвращает ВЕСЬ ответ
        $result = $resp['result'] ?? null;

        if (is_array($result) && isset($result['ID'])) return (string)$result['ID'];
        if (is_scalar($result)) return (string)$result;

        throw new RuntimeException("placement.bind: unexpected response: " . json_encode($resp, JSON_UNESCAPED_UNICODE));
    }

    public static function unbind(array $portal, string $placementId): void
    {
        if ($placementId === '') return;

        BitrixApi::call($portal, 'placement.unbind', [
            'ID' => $placementId,
        ]);
    }
}