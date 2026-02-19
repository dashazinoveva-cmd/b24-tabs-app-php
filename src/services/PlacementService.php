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

    public static function buildHandlerUrl(array $portal, int $tabId): string
    {
        $appDomain = $portal['domain'] ?: ($_SERVER['HTTP_HOST'] ?? '');
        if ($appDomain === '') {
            throw new RuntimeException("Cannot build handler url: app domain empty");
        }
        return "https://{$appDomain}/crm-tab?tab_id={$tabId}";
    }

    // ✅ порядок аргументов как в TabsController: ($portal, $entityTypeId, $tabId, $title)
    public static function bindTab(array $portal, string $entityTypeId, int $tabId, string $title): string
    {
        $placement = self::placementName($entityTypeId);
        if (!$placement) {
            throw new RuntimeException("Unsupported entity_type_id={$entityTypeId} for placement.bind");
        }

        $handler = self::buildHandlerUrl($portal, $tabId);

        $data = BitrixApi::call($portal, 'placement.bind', [
            'PLACEMENT' => $placement,
            'HANDLER'   => $handler,
            'TITLE'     => $title,
        ]);

        // Bitrix обычно вернёт ['result' => <id>] или ['result'=>['ID'=>...]]
        $result = $data['result'] ?? null;

        if (is_array($result) && isset($result['ID'])) return (string)$result['ID'];
        if (is_scalar($result)) return (string)$result;

        throw new RuntimeException("placement.bind: unexpected response");
    }

    // ✅ имя как ты используешь в контроллере
    public static function unbindTab(array $portal, string $placementId): void
    {
        if ($placementId === '') return;

        BitrixApi::call($portal, 'placement.unbind', [
            'ID' => $placementId,
        ]);
    }
}