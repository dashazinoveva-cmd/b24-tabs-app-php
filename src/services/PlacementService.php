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
        // handler ДОЛЖЕН быть на домене приложения
        $appHost = $_SERVER['HTTP_HOST']; // dev.calendar.consult-info.ru
        return "https://{$appHost}/crm-tab?tab_id={$tabId}";
    }

    // ✅ единая сигнатура
    public static function bindTab(array $portal, string $entityTypeId, int $tabId, string $title): string
    {
        $placement = self::placementName($entityTypeId);
        if (!$placement) {
            throw new RuntimeException("Unsupported entity_type_id={$entityTypeId} for placement.bind");
        }

        $handler = self::buildHandlerUrl($portal, $tabId);

        $result = BitrixApi::call($portal, 'placement.bind', [
            'PLACEMENT' => $placement,
            'HANDLER'   => $handler,
            'TITLE'     => $title,
        ]);

        if (is_array($result) && isset($result['ID'])) return (string)$result['ID'];
        if (is_scalar($result)) return (string)$result;

        throw new RuntimeException("placement.bind: unexpected response");
    }

    public static function unbind(array $portal, string $placementId): void
    {
        if ($placementId === '') return;

        BitrixApi::call($portal, 'placement.unbind', [
            'ID' => $placementId,
        ]);
    }
}