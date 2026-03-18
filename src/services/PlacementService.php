<?php

require_once __DIR__ . '/BitrixApi.php';
require_once __DIR__ . '/Logger.php';

class PlacementService
{
    public static function placementName(string $entityTypeId): ?string
    {
        if (str_starts_with($entityTypeId, 'sp_')) {
            $dynamicEntityTypeId = (int)substr($entityTypeId, 3);
            if ($dynamicEntityTypeId <= 0) {
                return null;
            }

            return 'CRM_DYNAMIC_' . $dynamicEntityTypeId . '_DETAIL_TAB';
        }

        return match ($entityTypeId) {
            'menu'    => 'LEFT_MENU',
            'deal'    => 'CRM_DEAL_DETAIL_TAB',
            'lead'    => 'CRM_LEAD_DETAIL_TAB',
            'contact' => 'CRM_CONTACT_DETAIL_TAB',
            'company' => 'CRM_COMPANY_DETAIL_TAB',
            default   => null,
        };
    }

    public static function buildHandlerUrl(int $tabId, string $entityTypeId): string
    {
        $config = require __DIR__ . '/../config/app.php';
        $raw = trim((string)($config['app_url'] ?? ''));

        if ($raw === '') {
            throw new RuntimeException('app_url is not set in config/app.php');
        }

        $parsed = parse_url($raw);
        if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new RuntimeException('Invalid app_url: ' . $raw);
        }

        $origin = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port'])) {
            $origin .= ':' . $parsed['port'];
        }

        if ($entityTypeId === 'menu') {
            return $origin . '/menu-item?tab_id=' . urlencode((string)$tabId);
        }

        return $origin . '/crm-tab?tab_id=' . urlencode((string)$tabId);
    }

    public static function bindTab(array $portal, string $entityTypeId, int $tabId, string $title): string
    {
        $placement = self::placementName($entityTypeId);
        if ($placement === null) {
            throw new RuntimeException("Unsupported entity_type_id={$entityTypeId} for placement.bind");
        }

        $handler = self::buildHandlerUrl($tabId, $entityTypeId);

        try {
            $resp = BitrixApi::call($portal, 'placement.bind', [
                'PLACEMENT' => $placement,
                'HANDLER' => $handler,
                'TITLE' => $title,
                'PLACEMENT_OPTIONS' => [
                    'tab_id' => (string)$tabId,
                ],
            ]);
            Logger::log('PLACEMENT BIND OK', [
                'entity_type_id' => $entityTypeId,
                'tab_id' => $tabId,
                'title' => $title,
                'placement' => $placement,
                'handler' => $handler,
                'resp' => $resp,
            ]);
        } catch (Throwable $e) {
            Logger::log('PLACEMENT BIND ERROR', [
                'entity_type_id' => $entityTypeId,
                'tab_id' => $tabId,
                'title' => $title,
                'placement' => $placement,
                'handler' => $handler,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if (($resp['result'] ?? null) === true) {
            return $handler;
        }

        throw new RuntimeException(
            'placement.bind failed: ' . json_encode($resp, JSON_UNESCAPED_UNICODE)
        );
    }

    public static function unbind(array $portal, string $entityTypeId, string $handlerOrEmpty): void
    {
        $placement = self::placementName($entityTypeId);
        if ($placement === null) {
            return;
        }

        $params = [
            'PLACEMENT' => $placement,
        ];

        if ($handlerOrEmpty !== '') {
            $params['HANDLER'] = $handlerOrEmpty;
        }

        BitrixApi::call($portal, 'placement.unbind', $params);
    }
}