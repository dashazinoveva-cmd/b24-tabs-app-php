<?php

require_once __DIR__ . '/../src/services/Logger.php';
require_once __DIR__ . '/../src/services/EntitiesService.php';
require_once __DIR__ . '/../src/services/TabsService.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// healthcheck
if ($uri === '/health') {
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK";
    exit;
}

// install (Bitrix24 будет дергать этот url)
if ($uri === '/install') {
    require_once __DIR__ . '/../src/controllers/InstallController.php';
    (new InstallController())->handle($method);
    exit;
}

// -------- API: tabs --------
if (str_starts_with($uri, '/api/tabs')) {
    require_once __DIR__ . '/../src/controllers/TabsController.php';
    (new TabsController())->handle($method, $uri);
    exit;
}

// settings page
if ($uri === '/settings') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/static/settings.html'); // ✅ теперь внутри public/static
    exit;
}

// crm tab page
if ($uri === '/crm-tab') {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/static/crm_tab.html'); // ✅ теперь внутри public/static
    exit;
}

// api/entities
if ($uri === '/api/entities') {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');

    $portalId = $_GET['portal_id'] ?? 'LOCAL';

    try {
        $entities = EntitiesService::getEntities($portalId);

        echo json_encode([
            "portal_id" => $portalId,
            "entities" => $entities,
            "source" => "bitrix"
        ], JSON_UNESCAPED_UNICODE);

    } catch (Throwable $e) {

        Logger::log("entities error", [
            "portal_id" => $portalId,
            "error" => $e->getMessage()
        ]);

        http_response_code(500);

        echo json_encode([
            "portal_id" => $portalId,
            "error" => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}

// default
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not found";