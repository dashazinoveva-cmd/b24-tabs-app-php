<?php

require_once __DIR__ . '/../src/services/Logger.php';
require_once __DIR__ . '/../src/services/EntitiesService.php';
require_once __DIR__ . '/../src/services/TabsService.php';
require_once __DIR__ . '/../src/services/PortalRepository.php';
require_once __DIR__ . '/../src/services/PortalService.php';
require_once __DIR__ . '/../src/services/BitrixApi.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

function buildFrameAncestors(): string
{
    $allowed = [
        "'self'",
        "https://*.bitrix24.ru",
        "https://*.bitrix24.com",
        "https://*.bitrix24.site",
    ];

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer !== '') {
        $scheme = parse_url($referer, PHP_URL_SCHEME);
        $host = parse_url($referer, PHP_URL_HOST);
        $port = parse_url($referer, PHP_URL_PORT);

        if ($scheme && $host) {
            $origin = $scheme . '://' . $host;
            if ($port) {
                $origin .= ':' . $port;
            }
            $allowed[] = $origin;
        }
    }

    return implode(' ', array_unique($allowed));
}

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
if ($uri === '/crm-tab' || $uri === '/crm-tab/') {
    require_once __DIR__ . '/../src/services/TabsService.php';
    require_once __DIR__ . '/../src/services/Logger.php';

    $tabId = (int)($_GET['tab_id'] ?? 0);

    Logger::log("CRM TAB OPEN", [
        "tab_id" => $tabId,
        "request_uri" => $_SERVER['REQUEST_URI'] ?? null,
        "query" => $_GET,
        "referer" => $_SERVER['HTTP_REFERER'] ?? null,
        "ua" => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
    if ($tabId <= 0) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo "tab_id is required";
        exit;
    }

    $tab = TabsService::getTabById($tabId);

    if (!$tab) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Tab not found: " . $tabId;
        exit;
    }

    $link = trim((string)($tab['link'] ?? ''));

    Logger::log("CRM TAB LINK RESOLVED", [
        "tab_id" => $tabId,
        "title" => $tab['title'] ?? null,
        "link" => $link,
    ]);

    if ($link === '') {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        $safeTitle = htmlspecialchars((string)($tab['title'] ?? 'Вкладка'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo <<<HTML
    <!doctype html>
    <html lang="ru">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle}</title>
    <style>
        html, body {
        height: 100%;
        margin: 0;
        font-family: Arial, sans-serif;
        background: #f6f8fb;
        color: #2f3b52;
        }
        .wrap {
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        box-sizing: border-box;
        }
        .card {
        max-width: 420px;
        width: 100%;
        background: #fff;
        border: 1px solid #e6ebf0;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.06);
        text-align: center;
        }
        .icon {
        font-size: 32px;
        margin-bottom: 12px;
        }
        .title {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 8px;
        }
        .text {
        font-size: 14px;
        line-height: 1.5;
        color: #5f6b7a;
        }
    </style>
    </head>
    <body>
    <div class="wrap">
        <div class="card">
        <div class="icon">🔗</div>
        <div class="title">Для вкладки пока не задана ссылка</div>
        <div class="text">
            Открой приложение, выбери вкладку <b>{$safeTitle}</b> и добавь URL в настройках.
        </div>
        </div>
    </div>
    </body>
    </html>
    HTML;
        exit;
    }

    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    header("Content-Security-Policy: frame-ancestors " . buildFrameAncestors());

    $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo <<<HTML
    <!doctype html>
    <html lang="ru">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview</title>
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        iframe { width: 100%; height: 100%; border: 0; display: block; }
    </style>
    </head>
    <body>
    <iframe src="{$safeLink}" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </body>
    </html>
    HTML;

    exit;
}

if ($uri === '/menu-item' || $uri === '/menu-item/') {
    $tabId = (int)($_GET['tab_id'] ?? 0);

    if ($tabId <= 0) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'tab_id is required';
        exit;
    }

    $tab = TabsService::getTabById($tabId);

    if (!$tab) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Menu item not found';
        exit;
    }

    $link = trim((string)($tab['link'] ?? ''));
    $safeTitle = htmlspecialchars((string)($tab['title'] ?? 'Пункт меню'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    if ($link === '') {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');

        echo <<<HTML
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$safeTitle}</title>
  <style>
    html, body {
      height: 100%;
      margin: 0;
      font-family: Arial, sans-serif;
      background: #f6f8fb;
      color: #2f3b52;
    }
    .wrap {
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      box-sizing: border-box;
    }
    .card {
      max-width: 420px;
      width: 100%;
      background: #fff;
      border: 1px solid #e6ebf0;
      border-radius: 16px;
      padding: 24px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.06);
      text-align: center;
    }
    .icon {
      font-size: 32px;
      margin-bottom: 12px;
    }
    .title {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: 8px;
    }
    .text {
      font-size: 14px;
      line-height: 1.5;
      color: #5f6b7a;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="icon">📌</div>
      <div class="title">Для пункта меню пока не задана ссылка</div>
      <div class="text">
        Открой приложение, выбери пункт <b>{$safeTitle}</b> и добавь URL в настройках.
      </div>
    </div>
  </div>
</body>
</html>
HTML;
        exit;
    }

    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    header("Content-Security-Policy: frame-ancestors " . buildFrameAncestors());

    $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo <<<HTML
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$safeTitle}</title>
  <style>
    html, body { height: 100%; margin: 0; padding: 0; }
    iframe { width: 100%; height: 100%; border: 0; display: block; }
  </style>
</head>
<body>
  <iframe src="{$safeLink}" referrerpolicy="no-referrer-when-downgrade"></iframe>
</body>
</html>
HTML;
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
if ($uri === '/api/debug/portal') {
    require_once __DIR__ . '/../src/services/PortalRepository.php';
    header('Content-Type: application/json; charset=utf-8');

    $memberId = $_GET['member_id'] ?? '';
    $row = PortalRepository::findByMemberId($memberId);

    if ($row) {
        $row['access_token']  = $row['access_token'] ? '***' : null;
        $row['refresh_token'] = $row['refresh_token'] ? '***' : null;
    }

    echo json_encode(["found" => (bool)$row, "row" => $row], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($uri === '/api/debug/log') {
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');

    $config = require __DIR__ . '/../src/config/app.php';
    $logPath = $config['log_path'] ?? (__DIR__ . '/../storage/app.log');

    if (!file_exists($logPath)) {
        echo "log not found: " . $logPath;
        exit;
    }

    $lines = file($logPath, FILE_IGNORE_NEW_LINES);
    $tail = array_slice($lines ?: [], -300);
    echo implode("\n", $tail);
    exit;
}
// --------------------
// DEFAULT
// --------------------
if ($uri === '/api/debug/migrate') {
    $pdo = Db::pdo();
    $pdo->exec("ALTER TABLE tabs ADD COLUMN placement_id TEXT");
    echo "OK";
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not found";