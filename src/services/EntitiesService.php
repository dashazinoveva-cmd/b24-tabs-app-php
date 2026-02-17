<?php

require_once __DIR__ . '/../db/Db.php';

class EntitiesService
{
    public static function getEntities(string $portalId): array
    {
        // 1) Берём токен портала из БД
        $pdo = Db::pdo();
        $stmt = $pdo->prepare("SELECT access_token, domain FROM portals WHERE member_id = :mid LIMIT 1");
        $stmt->execute([':mid' => $portalId]);
        $portal = $stmt->fetch();

        if (!$portal) {
            throw new RuntimeException("Portal not found in DB for member_id=" . $portalId);
        }
        if (empty($portal['access_token'])) {
            throw new RuntimeException("access_token is empty for member_id=" . $portalId);
        }
        if (empty($portal['domain'])) {
            throw new RuntimeException("domain is empty for member_id=" . $portalId);
        }

        $accessToken = $portal['access_token'];
        $domain = $portal['domain'];

        // 2) Делаем REST запрос в Bitrix24 (crm.entity.types)
        $url = "https://{$domain}/rest/crm.entity.types.json";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['auth' => $accessToken]),
            CURLOPT_TIMEOUT => 20,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException("curl error: " . $err);
        }
        if ($code < 200 || $code >= 300) {
            throw new RuntimeException("bitrix http {$code}: " . $raw);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException("Invalid JSON from Bitrix: " . $raw);
        }
        if (!empty($data['error'])) {
            throw new RuntimeException("Bitrix error: " . $data['error'] . " " . ($data['error_description'] ?? ""));
        }

        $result = $data['result'] ?? [];
        $entities = [];

        foreach ($result as $item) {
            // crm.entity.types возвращает список типов, нам нужны базовые + смарты
            // entityTypeId: 1=lead 2=deal 3=contact 4=company ... + динамические
            $id = $item['entityTypeId'] ?? null;
            $name = $item['title'] ?? null;

            if (!$id || !$name) continue;

            // базовые сущности
            if ($id == 1) $entities[] = ['id' => 'lead', 'name' => 'Лиды'];
            if ($id == 2) $entities[] = ['id' => 'deal', 'name' => 'Сделки'];
            if ($id == 3) $entities[] = ['id' => 'contact', 'name' => 'Контакты'];
            if ($id == 4) $entities[] = ['id' => 'company', 'name' => 'Компании'];

            // смарт-процессы (обычно начинаются от 128+)
            if ($id >= 128) {
                $entities[] = ['id' => 'sp_' . $id, 'name' => 'Смарт-процесс: ' . $name];
            }
        }

        // Уберём дубли (если базовые пришли несколько раз)
        $uniq = [];
        $out = [];
        foreach ($entities as $e) {
            $k = $e['id'];
            if (!isset($uniq[$k])) {
                $uniq[$k] = true;
                $out[] = $e;
            }
        }

        return $out;
    }
}