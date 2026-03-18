<?php

require_once __DIR__ . '/PortalRepository.php';
require_once __DIR__ . '/BitrixApi.php';

class EntitiesService
{
    public static function getEntities(string $portalId): array
    {
        $portal = PortalRepository::findByMemberId($portalId);
        if (!$portal) {
            throw new RuntimeException("Portal not found for member_id={$portalId}");
        }

        $entities = [
            ['id' => 'menu', 'name' => 'Меню'],
            ['id' => 'deal',    'name' => 'Сделки'],
            ['id' => 'lead',    'name' => 'Лиды'],
            ['id' => 'contact', 'name' => 'Контакты'],
            ['id' => 'company', 'name' => 'Компании'],
        ];

        $resp = BitrixApi::call($portal, 'crm.type.list', [
            'order' => ['id' => 'asc'],
        ]);

        $types = $resp['result']['types'] ?? [];
        $seen = [];

        foreach ($types as $type) {
            $dynamicEntityTypeId = (int)($type['entityTypeId'] ?? 0);
            if ($dynamicEntityTypeId <= 0) {
                continue;
            }

            if (isset($seen[$dynamicEntityTypeId])) {
                continue;
            }
            $seen[$dynamicEntityTypeId] = true;

            $title = trim((string)($type['title'] ?? ''));
            if ($title === '') {
                $title = 'Без названия';
            }

            $entities[] = [
                'id' => 'sp_' . $dynamicEntityTypeId,
                'name' => 'Смарт-процесс: ' . $title,
            ];
        }

        return $entities;
    }
}