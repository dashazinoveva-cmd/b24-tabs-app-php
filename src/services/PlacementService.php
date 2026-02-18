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

    private static function appBaseUrl(): string
    {
        $cfg = require __DIR__ . '/../config/app.php';
        $url = (string)($cfg['app_url'] ?? '');
        $url = rtrim($url, '/');

        // fallback на текущий хост (если вдруг открылось локально)
        if ($url === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $url = $scheme . '://' . $host;
        }
        return $url;
    }

    public static function buildHandlerUrl(int $tabId): string
    {
        // URL вкладки, которую откроет Битрикс во фрейме
        return self::appBaseUrl() . "/crm-tab?tab_id=" . $tabId;
    }

    // ✅ Сигнатура теперь совпадает с твоим TabsController:
    // bindTab($portal, $entityTypeId, $id, $title)
    public static function bindTab(array $portal, string $entityTypeId, int $tabId, string $title): string
    {
        $placement = self::placementName($entityTypeId);
        if (!$placement) {
            throw new RuntimeException("Unsupported entity_type_id={$entityTypeId} for placement.bind");
        }

        $handler = self::buildHandlerUrl($tabId);

        $result = BitrixApi::call($portal, 'placement.bind', [
            'PLACEMENT' => $placement,
            'HANDLER'   => $handler,
            'TITLE'     => $title,
        ]);

        // Bitrix обычно возвращает {result: ID} или {result: {ID: ...}} — подстрахуемся
        if (is_array($result) && isset($result['ID'])) return (string)$result['ID'];
        if (is_scalar($result)) return (string)$result;

        throw new RuntimeException("placement.bind: unexpected response");
    }

    public static function unbindTab(array $portal, string $placementId): void
    {
        if ($placementId === '') return;

        BitrixApi::call($portal, 'placement.unbind', [
            'ID' => $placementId,
        ]);
    }
}