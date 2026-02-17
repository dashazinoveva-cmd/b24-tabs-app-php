<?php

require_once __DIR__ . '/PortalRepository.php';
require_once __DIR__ . '/BitrixApi.php';

class EntitiesService
{
    public static function getEntities(string $portalId): array
    {
        $portal = PortalRepository::findByMemberId($portalId);
        if (!$portal) {
            throw new RuntimeException("Portal not found in DB for member_id={$portalId}");
        }

        // База (стандартные сущности всегда есть)
        $entities = [
            ["id" => "deal",    "name" => "Сделки"],
            ["id" => "lead",    "name" => "Лиды"],
            ["id" => "contact", "name" => "Контакты"],
            ["id" => "company", "name" => "Компании"],
        ];

        // Смарты (crm.type.list)
        $resp = BitrixApi::call($portal, 'crm.type.list', [
            'order' => ['id' => 'asc'],
        ]);

        // В ответе обычно result.types
        $types = $resp['result']['types'] ?? [];

        foreach ($types as $t) {
            // isDynamic = Y у смартов
            $isDynamic = (string)($t['isDynamic'] ?? 'N');
            if ($isDynamic !== 'Y') continue;

            $id = $t['id'] ?? null;
            $title = $t['title'] ?? ('ID ' . $id);

            if ($id) {
                $entities[] = [
                    "id" => "sp_" . $id,
                    "name" => "Смарт-процесс: " . $title,
                ];
            }
        }

        return $entities;
    }
}