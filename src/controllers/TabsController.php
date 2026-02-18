<?php

require_once __DIR__ . '/../db/Db.php';
require_once __DIR__ . '/../services/PortalRepository.php';
require_once __DIR__ . '/../services/PlacementService.php';

class TabsController
{
    public function handle(string $method, string $uri): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // Bitrix иногда шлёт HEAD-проверки
        if ($method === 'HEAD') {
            http_response_code(200);
            exit;
        }

        $id = $this->extractId($uri);

        try {
            if ($id === null) {
                // collection: /api/tabs
                if ($method === 'GET')  { $this->listTabs();  exit; }
                if ($method === 'POST') { $this->createTab(); exit; }
            } else {
                // item: /api/tabs/{id}
                if ($method === 'PATCH')  { $this->updateTab($id); exit; }
                if ($method === 'DELETE') { $this->deleteTab($id); exit; }
            }

            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            exit;

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Internal error',
                'message' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function listTabs(): void
    {
        $portalId = $_GET['portal_id'] ?? 'LOCAL';
        $entityTypeId = $_GET['entity_type_id'] ?? '';

        if ($entityTypeId === '') {
            http_response_code(400);
            echo json_encode(['error' => 'entity_type_id is required'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare("
            SELECT id, title, link, order_index, placement_id
            FROM tabs
            WHERE portal_id = :portal_id
            AND entity_type_id = :entity_type_id
            ORDER BY order_index ASC, id ASC
        ");
        $stmt->execute([
            ':portal_id' => $portalId,
            ':entity_type_id' => $entityTypeId,
        ]);

        $rows = $stmt->fetchAll();

        http_response_code(200);
        echo json_encode(['tabs' => $rows], JSON_UNESCAPED_UNICODE);
    }

    private function createTab(): void
    {
        $portalId = $_GET['portal_id'] ?? 'LOCAL';

        $body = $this->readJson();
        $entityTypeId = trim((string)($body['entity_type_id'] ?? ''));
        $title = trim((string)($body['title'] ?? ''));

        if ($entityTypeId === '' || $title === '') {
            http_response_code(400);
            echo json_encode(['error' => 'entity_type_id and title are required'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pdo = Db::pdo();

        // next order_index
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(order_index), -1) AS max_order
            FROM tabs
            WHERE portal_id = :portal_id
              AND entity_type_id = :entity_type_id
        ");
        $stmt->execute([
            ':portal_id' => $portalId,
            ':entity_type_id' => $entityTypeId,
        ]);
        $maxOrder = (int)($stmt->fetchColumn() ?? -1);
        $nextOrder = $maxOrder + 1;

        // insert
        $ins = $pdo->prepare("
            INSERT INTO tabs (portal_id, entity_type_id, title, link, order_index)
            VALUES (:portal_id, :entity_type_id, :title, '', :order_index)
        ");

        try {
            $ins->execute([
                ':portal_id' => $portalId,
                ':entity_type_id' => $entityTypeId,
                ':title' => $title,
                ':order_index' => $nextOrder,
            ]);
        } catch (PDOException $e) {
            // уникальность title в рамках portal/entity
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                http_response_code(409);
                echo json_encode(['error' => 'Tab title already exists'], JSON_UNESCAPED_UNICODE);
                return;
            }
            throw $e;
        }

        $id = (int)$pdo->lastInsertId();
        $portal = PortalRepository::findByMemberId($portalId);
        if (!$portal) {
            throw new RuntimeException("Portal not found for member_id={$portalId}");
        }

        $placementId = PlacementService::bindTab($portal, $entityTypeId, $id, $title);

        $upd = $pdo->prepare("UPDATE tabs SET placement_id = :pid WHERE id = :id AND portal_id = :portal_id");
        $upd->execute([':pid' => $placementId, ':id' => $id, ':portal_id' => $portalId]);
        http_response_code(200);
        echo json_encode([
            'id' => $id,
            'title' => $title,
            'placement_id' => $placementId
        ], JSON_UNESCAPED_UNICODE);
        }

    private function updateTab(int $tabId): void
    {
        $portalId = $_GET['portal_id'] ?? 'LOCAL';
        $body = $this->readJson();
        if (array_key_exists('title', $body) && trim((string)$body['title']) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'title cannot be empty'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $fields = [];
        $params = [':id' => $tabId, ':portal_id' => $portalId];

        if (array_key_exists('title', $body)) {
            $fields[] = "title = :title";
            $params[':title'] = trim((string)$body['title']);
        }
        if (array_key_exists('link', $body)) {
            $fields[] = "link = :link";
            $params[':link'] = (string)$body['link'];
        }
        if (array_key_exists('order_index', $body)) {
            $fields[] = "order_index = :order_index";
            $params[':order_index'] = (int)$body['order_index'];
        }

        if (!$fields) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $pdo = Db::pdo();

        $rowStmt = $pdo->prepare("SELECT * FROM tabs WHERE id = :id AND portal_id = :portal_id");
        $rowStmt->execute([':id' => $tabId, ':portal_id' => $portalId]);
        $tabRow = $rowStmt->fetch(PDO::FETCH_ASSOC);

        if (!$tabRow) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $sql = "UPDATE tabs SET " . implode(", ", $fields) . " WHERE id = :id AND portal_id = :portal_id";
        $upd = $pdo->prepare($sql);

        try {
            $upd->execute($params);
            // если меняли title — нужно пересоздать placement
            if (array_key_exists('title', $body)) {

                $portal = PortalRepository::findByMemberId($portalId);
                if (!$portal) {
                    throw new RuntimeException("Portal not found for member_id={$portalId}");
                }

                $oldPlacementId = (string)($tabRow['placement_id'] ?? '');

                // 1) удалить старый placement
                if ($oldPlacementId !== '') {
                    try {
                        PlacementService::unbindTab($portal, $oldPlacementId);
                    } catch (Throwable $e) {}
                }

                // 2) создать новый
                $newTitle = trim((string)$body['title']);
                $placementId = PlacementService::bindTab(
                    $portal,
                    $tabRow['entity_type_id'],
                    $tabId,
                    $newTitle
                );

                // 3) сохранить новый placement_id
                $save = $pdo->prepare("UPDATE tabs SET placement_id = :pid WHERE id = :id AND portal_id = :portal_id");
                $save->execute([
                    ':pid' => $placementId,
                    ':id' => $tabId,
                    ':portal_id' => $portalId
                ]);
            }
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                http_response_code(409);
                echo json_encode(['error' => 'Tab title already exists'], JSON_UNESCAPED_UNICODE);
                return;
            }
            throw $e;
        }

        http_response_code(200);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    private function deleteTab(int $tabId): void
    {
        $portalId = $_GET['portal_id'] ?? 'LOCAL';

        $pdo = Db::pdo();
        $tab = $pdo->prepare("SELECT placement_id FROM tabs WHERE id=:id AND portal_id=:portal_id");
        $tab->execute([':id'=>$tabId, ':portal_id'=>$portalId]);
        $placementId = (string)($tab->fetchColumn() ?: '');

        if ($placementId !== '') {
            $portal = PortalRepository::findByMemberId($portalId);
            if ($portal) {
                try { PlacementService::unbindTab($portal, $placementId); } catch(Throwable $e) {}
            }
        }
        $del = $pdo->prepare("DELETE FROM tabs WHERE id = :id AND portal_id = :portal_id");
        $del->execute([':id' => $tabId, ':portal_id' => $portalId]);

        if ($del->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Not found'], JSON_UNESCAPED_UNICODE);
            return;
        }

        http_response_code(200);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }

    private function extractId(string $uri): ?int
    {
        // /api/tabs/123
        if (preg_match('~^/api/tabs/(\d+)$~', $uri, $m)) {
            return (int)$m[1];
        }
        // /api/tabs
        if ($uri === '/api/tabs') return null;

        // Если вдруг пришло /api/tabs?.... (parse_url в index.php уже убирает query обычно,
        // но если нет — подстрахуемся)
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === '/api/tabs') return null;
        if (preg_match('~^/api/tabs/(\d+)$~', $path, $m)) return (int)$m[1];

        return null;
    }

    private function readJson(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
}