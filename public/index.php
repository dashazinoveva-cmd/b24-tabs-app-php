<?php

require_once __DIR__ . '/../src/services/Logger.php';
require_once __DIR__ . '/../src/services/EntitiesService.php';
require_once __DIR__ . '/../src/services/TabsService.php';
require_once __DIR__ . '/../src/services/PortalRepository.php';
require_once __DIR__ . '/../src/services/PortalService.php';
require_once __DIR__ . '/../src/services/BitrixApi.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];


// --------------------
// HEALTHCHECK
// --------------------
if ($uri === '/health') {
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK";
    exit;
}


// --------------------
// INSTALL (Bitrix)
// --------------------
if ($uri === '/install') {
    require_once __DIR__ . '/../src/controllers/InstallController.php';
    (new InstallController())->handle($method);
    exit;
}


// --------------------
// API: TABS
// --------------------
if (str_starts_with($uri, '/api/tabs')) {
    require_once __DIR__ . '/../src/controllers/TabsController.php';
    (new TabsController())->handle($method, $uri);
    exit;
}


// --------------------
// SETTINGS PAGE
// --------------------
if ($uri === '/settings') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/static/settings.html');
    exit;
}


// --------------------
// CRM TAB PAGE
// --------------------
if ($uri === '/crm-tab') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/static/crm_tab.html');
    exit;
}


// --------------------
// API: ENTITIES (PRODUCTION)
// --------------------
if ($uri === '/api/entities') {
    header('Content-Type: application/json; charset=utf-8');

    $portalId = $_GET['portal_id'] ?? '';

    if ($portalId === '') {
        http_response_code(400);
        echo json_encode([
            "error" => "portal_id is required"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $entities = EntitiesService::getEntities($portalId);

        http_response_code(200);
        echo json_encode([
            "portal_id" => $portalId,
            "entities" => $entities
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        Logger::log("entities error", [
            "portal_id" => $portalId,
            "error" => $e->getMessage()
        ]);

        http_response_code(500);
        echo json_encode([
            "error" => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}


// --------------------
// API: PORTAL SYNC
// --------------------
if ($uri === '/api/portal/sync') {
    header('Content-Type: application/json; charset=utf-8');

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = [];

    try {
        PortalService::upsertPortal($data);

        http_response_code(200);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {
        Logger::log("portal sync error", [
            "error" => $e->getMessage()
        ]);

        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}


// --------------------
// DEFAULT
// --------------------
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not found";