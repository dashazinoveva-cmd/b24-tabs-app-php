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

    private function getBaseUrl(): string
    {
        // 1) схема (https почти всегда)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        // 2) хост
        $host = $_SERVER['HTTP_HOST'] ?? '';

        // 3) если хоста нет (редко), fallback на APP_URL как было
        if (!$host) {
            $base = rtrim($this->config['app_url'] ?? '', '/');
            return $base;
        }

        return $scheme . '://' . $host;
    }

    public function buildHandlerUrl(int $tabId): string
    {
        $base = rtrim($this->getBaseUrl(), '/');
        return $base . '/crm-tab?tab_id=' . $tabId;
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

        // BitrixApi возвращает ВЕСЬ ответ, ID почти всегда в result
        $result = $resp['result'] ?? null;

        // ✅ вариант 1: result = число (ID)
        if (is_int($result) || (is_string($result) && ctype_digit($result))) {
            return (string)$result;
        }

        // ✅ вариант 2: result = массив с ID
        if (is_array($result) && isset($result['ID'])) {
            return (string)$result['ID'];
        }

        // ❌ иначе — ошибка
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