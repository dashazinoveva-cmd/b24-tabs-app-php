<?php

require_once __DIR__ . '/PortalRepository.php';
require_once __DIR__ . '/BitrixApi.php';
require_once __DIR__ . '/Logger.php';

class EntitiesService
{
    public static function getEntities(string $portalId): array
    {
        $portal = PortalRepository::findByMemberId($portalId);
        if (!$portal) {
            throw new RuntimeException("Portal not found in DB for member_id={$portalId}");
        }

        $entities = [
            ["id" => "deal",    "name" => "Сделки"],
            ["id" => "lead",    "name" => "Лиды"],
            ["id" => "contact", "name" => "Контакты"],
            ["id" => "company", "name" => "Компании"],
        ];

        $resp = BitrixApi::call($portal, 'crm.type.list', [
            'order' => ['id' => 'asc'],
        ]);

        Logger::log("crm.type.list raw", $resp);

        $types = $resp['result']['types'] ?? [];

        Logger::log("crm.type.list parsed types", [
            "count" => count($types),
            "types" => $types,
        ]);

        foreach ($types as $t) {
            $title = $t['title'] ?? 'Без названия';
            $dynamicEntityTypeId = $t['entityTypeId'] ?? null;

            Logger::log("crm.type.list item", $t);

            if ($dynamicEntityTypeId) {
                $entities[] = [
                    "id" => "sp_" . $dynamicEntityTypeId,
                    "name" => "Смарт-процесс: " . $title,
                ];
            }
        }

        Logger::log("entities final", $entities);

        return $entities;
    }
}