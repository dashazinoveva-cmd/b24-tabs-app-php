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

        $entities = [
            ["id" => "deal",    "name" => "Сделки"],
            ["id" => "lead",    "name" => "Лиды"],
            ["id" => "contact", "name" => "Контакты"],
            ["id" => "company", "name" => "Компании"],
        ];

        $resp = BitrixApi::call($portal, 'crm.type.list', [
            'order' => ['id' => 'asc'],
        ]);

        $types = $resp['result']['types'] ?? [];

        foreach ($types as $t) {
            $isDynamic = $t['isDynamic'] ?? null;

            if (!in_array($isDynamic, ['Y', '1', 1, true], true)) {
                continue;
            }

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