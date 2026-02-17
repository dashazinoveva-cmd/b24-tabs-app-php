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
            "error" => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE);
    }

    exit;
}
// DEBUG: показать последние строки лога (только для отладки!)
if ($uri === '/api/debug/log') {
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');

    $config = require __DIR__ . '/../src/config/app.php';
    $logPath = $config['log_path'] ?? (__DIR__ . '/../storage/app.log');

    if (!file_exists($logPath)) {
        echo "log not found: " . $logPath;
        exit;
    }

    // tail ~200 строк
    $lines = file($logPath, FILE_IGNORE_NEW_LINES);
    $tail = array_slice($lines ?: [], -200);

    echo implode("\n", $tail);
    exit;
}
if ($uri === '/api/debug/portal') {
    header('Content-Type: application/json; charset=utf-8');
    $memberId = $_GET['member_id'] ?? '';
    $row = PortalRepository::findByMemberId($memberId);

    if ($row) {
        // маскируем токены
        $row['access_token']  = $row['access_token'] ? '***' : null;
        $row['refresh_token'] = $row['refresh_token'] ? '***' : null;
    }

    echo json_encode(["found" => (bool)$row, "row" => $row], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($uri === '/api/debug/b24') {
    header('Content-Type: application/json; charset=utf-8');

    $portalId = $_GET['portal_id'] ?? '';
    require_once __DIR__ . '/../src/services/PortalRepository.php';
    require_once __DIR__ . '/../src/services/BitrixApi.php';

    $portal = PortalRepository::findByMemberId($portalId);
    if (!$portal) {
        http_response_code(404);
        echo json_encode(['error' => 'portal not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // самый простой метод — получение текущего пользователя
        $data = BitrixApi::call($portal, 'user.current', []);
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
// default
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not found";