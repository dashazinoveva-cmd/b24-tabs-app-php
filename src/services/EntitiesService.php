<?php

class EntitiesService
{
    public static function getMockEntities(): array
    {
        return [
            ["id" => "deal",    "name" => "Сделки"],
            ["id" => "lead",    "name" => "Лиды"],
            ["id" => "contact", "name" => "Контакты"],
            ["id" => "company", "name" => "Компании"],
            ["id" => "sp_1032", "name" => "Смарт-процесс: Проекты"],
            ["id" => "sp_1040", "name" => "Смарт-процесс: Клиенты"],
        ];
    }
}