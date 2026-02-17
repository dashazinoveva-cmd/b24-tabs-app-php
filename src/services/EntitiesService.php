<?php

require_once __DIR__ . '/PortalRepository.php'; // если нет — см. ниже
require_once __DIR__ . '/Logger.php';

class EntitiesService
{
    /**
     * Главный метод: вернёт сущности портала через REST.
     * Если REST не сработал — вернёт базовый список без смартов.
     */
    public static function getEntities(string $portalId): array
    {
        $portal = self::getPortalRow($portalId);
        if (!$portal) {
            return self::getBaseEntities("no_portal");
        }

        $domain = $portal['domain'] ?? null;
        $accessToken = $portal['access_token'] ?? null;

        if (!$domain || !$accessToken) {
            return self::getBaseEntities("missing_domain_or_token");
        }

        // 1) базовые сущности CRM (есть всегда)
        $entities = [
            ["id" => "deal",    "name" => "Сделки"],
            ["id" => "lead",    "name" => "Лиды"],
            ["id" => "contact", "name" => "Контакты"],
            ["id" => "company", "name" => "Компании"],
        ];

        // 2) смарт-процессы: crm.type.list
        try {
            $resp = self::b24Call($domain, $accessToken, 'crm.type.list', [
                'order' => ['id' => 'ASC'],
                'filter' => ['isDynamic' => 'Y'],
            ]);

            $result = $resp['result']['types'] ?? $resp['result'] ?? null;

            if (is_array($result)) {
                foreach ($result as $t) {
                    $id = $t['id'] ?? null;
                    $title = $t['title'] ?? null;
                    $isDynamic = $t['isDynamic'] ?? null;

                    if (!$id || !$title) continue;
                    // на всякий случай фильтр
                    if ($isDynamic !== null && (string)$isDynamic !== 'Y') continue;

                    // ✅ ключ: "sp_{$id}" как у тебя в фронте
                    $entities[] = ["id" => "sp_" . $id, "name" => "Смарт-процесс: " . $title];
                }
            }

            return $entities;
        } catch (Throwable $e) {
            Logger::log("EntitiesService REST error", [
                "portal_id" => $portalId,
                "error" => $e->getMessage(),
            ]);
            return self::getBaseEntities("rest_error");
        }
    }

    /**
     * Используется твоим index.php сейчас. Оставим, но лучше перейти на getEntities()
     */
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

    // ---------------- helpers ----------------

    private static function getBaseEntities(string $reason): array
    {
        // базовый список без смартов (не вводит в заблуждение)
        return [
            ["id" => "deal",    "name" => "Сделки"],
            ["id" => "lead",    "name" => "Лиды"],
            ["id" => "contact", "name" => "Контакты"],
            ["id" => "company", "name" => "Компании"],
            ["id" => "base_reason", "name" => "Источник: " . $reason],
        ];
    }

    private static function getPortalRow(string $portalId): ?array
    {
        // Если у тебя нет PortalRepository — можно достать напрямую из Db
        require_once __DIR__ . '/../db/Db.php';
        $pdo = Db::pdo();

        $st = $pdo->prepare("SELECT * FROM portals WHERE member_id = :id LIMIT 1");
        $st->execute([':id' => $portalId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    private static function b24Call(string $domain, string $accessToken, string $method, array $params = []): array
    {
        $url = "https://{$domain}/rest/{$method}.json";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(array_merge($params, [
                'auth' => $accessToken,
            ])),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException("curl error: " . $err);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException("bad json (http {$code}): " . mb_substr($raw, 0, 300));
        }

        if (isset($data['error'])) {
            throw new RuntimeException("b24 error: " . $data['error'] . " / " . ($data['error_description'] ?? ''));
        }

        return $data;
    }
}