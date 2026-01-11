<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$dbPath = __DIR__ . '/data/clickfix.sqlite';
$schemaPath = null;
$preferredSchema = __DIR__ . '/data/clickfix.sql';
if (is_readable($preferredSchema)) {
    $schemaPath = $preferredSchema;
} else {
    $schemaCandidates = glob(__DIR__ . '/data/*.sql') ?: [];
    foreach ($schemaCandidates as $candidate) {
        if (is_readable($candidate)) {
            $schemaPath = $candidate;
            break;
        }
    }
}

$defaultSchemaSql = <<<SQL
CREATE TABLE IF NOT EXISTS reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    url TEXT,
    hostname TEXT,
    message TEXT,
    detected_content TEXT,
    full_context TEXT,
    signals_json TEXT,
    blocked INTEGER DEFAULT 0,
    user_agent TEXT,
    ip TEXT,
    country TEXT
);

CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    enabled INTEGER,
    alert_count INTEGER,
    block_count INTEGER,
    manual_sites_json TEXT,
    country TEXT
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS appeals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    domain TEXT NOT NULL,
    reason TEXT NOT NULL,
    contact TEXT,
    status TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS list_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    list_type TEXT NOT NULL,
    domain TEXT NOT NULL,
    reason TEXT NOT NULL
);
SQL;

$logFile = __DIR__ . '/clickfix-report.log';

function writeDebugLog(string $debugFile, array $entry): void
{
    $entry['logged_at'] = gmdate('c');
    @file_put_contents(
        $debugFile,
        json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function respondWithError(int $statusCode, string $message, string $debugFile, array $context = []): void
{
    http_response_code($statusCode);
    echo json_encode(['status' => 'error', 'message' => $message]);
    writeDebugLog($debugFile, ['status' => 'error', 'code' => $statusCode, 'message' => $message] + $context);
    exit;
}

function ensureReportsSchema(PDO $pdo, string $debugFile): void
{
    try {
        $columns = $pdo->query('PRAGMA table_info(reports)')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage()]);
        return;
    }

    $existing = [];
    foreach ($columns as $column) {
        $existing[(string) ($column['name'] ?? '')] = true;
    }

    $updates = [];
    if (!isset($existing['full_context'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN full_context TEXT';
    }
    if (!isset($existing['blocked'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN blocked INTEGER DEFAULT 0';
    }

    foreach ($updates as $statement) {
        try {
            $pdo->exec($statement);
        } catch (Throwable $exception) {
            writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => $statement]);
        }
    }
}

function ensureAdminSchema(PDO $pdo, string $debugFile): void
{
    $statements = [
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS appeals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            domain TEXT NOT NULL,
            reason TEXT NOT NULL,
            contact TEXT,
            status TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS list_actions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            user_id INTEGER,
            action TEXT NOT NULL,
            list_type TEXT NOT NULL,
            domain TEXT NOT NULL,
            reason TEXT NOT NULL
        )'
    ];
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
        } catch (Throwable $exception) {
            writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => $statement]);
        }
    }

    try {
        $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
        $existing = [];
        foreach ($columns as $column) {
            $existing[(string) ($column['name'] ?? '')] = true;
        }
        if (!isset($existing['created_at'])) {
            $pdo->exec('ALTER TABLE users ADD COLUMN created_at TEXT');
        }
        if (!isset($existing['role'])) {
            $pdo->exec('ALTER TABLE users ADD COLUMN role TEXT');
        }
    } catch (Throwable $exception) {
        writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => 'ALTER users']);
    }

    try {
        $columns = $pdo->query('PRAGMA table_info(appeals)')->fetchAll(PDO::FETCH_ASSOC);
        $existing = [];
        foreach ($columns as $column) {
            $existing[(string) ($column['name'] ?? '')] = true;
        }
        if (!isset($existing['contact'])) {
            $pdo->exec('ALTER TABLE appeals ADD COLUMN contact TEXT');
        }
        if (!isset($existing['status'])) {
            $pdo->exec('ALTER TABLE appeals ADD COLUMN status TEXT');
        }
    } catch (Throwable $exception) {
        writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => 'ALTER appeals']);
    }
}

function openDatabase(string $dbPath, ?string $schemaPath, string $schemaSqlFallback, string $debugFile): ?PDO
{
    $dataDir = dirname($dbPath);
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0775, true);
    }

    if (!file_exists($dbPath)) {
        try {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $schemaSql = null;
            if ($schemaPath !== null) {
                $schemaSql = file_get_contents($schemaPath);
            }
            if (!is_string($schemaSql) || $schemaSql === '') {
                $schemaSql = $schemaSqlFallback;
            }
            $pdo->exec($schemaSql);
        } catch (Throwable $exception) {
            writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'db_path' => $dbPath]);
            return null;
        }
    }

    if (!is_readable($dbPath)) {
        return null;
    }

    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Throwable $exception) {
        writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'db_path' => $dbPath]);
        return null;
    }

    ensureReportsSchema($pdo, $debugFile);
    ensureAdminSchema($pdo, $debugFile);
    return $pdo;
}

$debugFile = __DIR__ . '/clickfix-debug.log';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondWithError(405, 'Method not allowed', $debugFile, ['method' => $_SERVER['REQUEST_METHOD'] ?? '']);
}

$maxBytes = 32768;
$rawBody = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
if ($rawBody === false || strlen($rawBody) > $maxBytes) {
    respondWithError(413, 'Payload too large', $debugFile, ['bytes' => $rawBody === false ? null : strlen($rawBody)]);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') === false) {
    respondWithError(415, 'Unsupported content type', $debugFile, ['content_type' => $contentType]);
}

try {
    $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    respondWithError(400, 'Invalid JSON', $debugFile, ['error' => $exception->getMessage()]);
}

if (!is_array($payload)) {
    respondWithError(400, 'Invalid payload', $debugFile);
}

$type = isset($payload['type']) ? strtolower(trim((string) $payload['type'])) : 'alert';
if (!in_array($type, ['alert', 'stats'], true)) {
    respondWithError(400, 'Invalid type', $debugFile, ['type' => $type]);
}

$url = isset($payload['url']) ? trim((string) $payload['url']) : '';
$hostname = isset($payload['hostname']) ? trim((string) $payload['hostname']) : '';
$message = isset($payload['message']) ? trim((string) $payload['message']) : '';
$reason = isset($payload['reason']) ? trim((string) $payload['reason']) : '';
$timestamp = isset($payload['timestamp']) ? (string) $payload['timestamp'] : '';
$signals = isset($payload['signals']) && is_array($payload['signals']) ? $payload['signals'] : [];
$detectedContent = isset($payload['detectedContent'])
    ? trim((string) $payload['detectedContent'])
    : '';
$fullContext = isset($payload['full_context'])
    ? trim((string) $payload['full_context'])
    : '';
$statsData = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
$manualReport = filter_var($payload['manualReport'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
$blocked = filter_var($payload['blocked'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

$url = substr($url, 0, 2048);
$hostname = substr($hostname, 0, 255);
$message = substr($message !== '' ? $message : $reason, 0, 2000);
$timestamp = substr($timestamp, 0, 100);
$detectedContent = substr($detectedContent, 0, 4000);
$fullContext = substr($fullContext, 0, 50000);

if ($url !== '' && preg_match('/\s/', $url)) {
    respondWithError(400, 'Invalid url', $debugFile, ['url' => $url]);
}

if ($url !== '') {
    $parsedUrl = parse_url($url);
    $scheme = strtolower((string) ($parsedUrl['scheme'] ?? ''));
    $host = (string) ($parsedUrl['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        respondWithError(400, 'Invalid url', $debugFile, ['url' => $url]);
    }
    if ($hostname === '') {
        $hostname = $host;
    }
}

if ($url !== '' && preg_match('/\s/', $url)) {
    respondWithError(400, 'Invalid url', $debugFile, ['url' => $url]);
}

if ($url !== '') {
    $parsedUrl = parse_url($url);
    $scheme = strtolower((string) ($parsedUrl['scheme'] ?? ''));
    $host = (string) ($parsedUrl['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        respondWithError(400, 'Invalid url', $debugFile, ['url' => $url]);
    }
    if ($hostname === '') {
        $hostname = $host;
    }
}

if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
    respondWithError(400, 'Invalid url', $debugFile, ['url' => $url]);
}

if ($hostname !== '' && !preg_match('/^[a-z0-9.-]+$/i', $hostname)) {
    respondWithError(400, 'Invalid hostname', $debugFile, ['hostname' => $hostname]);
}

if ($type === 'alert' && $url === '' && $hostname === '' && $message === '') {
    respondWithError(400, 'Missing required fields', $debugFile);
}

$normalizedStats = [];
if ($type === 'stats') {
    $normalizedStats = [
        'enabled' => filter_var($statsData['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        'alert_count' => (int) ($statsData['alertCount'] ?? 0),
        'block_count' => (int) ($statsData['blockCount'] ?? 0),
        'manual_sites' => [],
        'alert_sites' => []
    ];
    $manualSites = $statsData['manualSites'] ?? [];
    if (is_array($manualSites)) {
        foreach (array_slice($manualSites, 0, 200) as $site) {
            $site = substr(trim((string) $site), 0, 255);
            if ($site !== '' && preg_match('/^[a-z0-9.-]+$/i', $site)) {
                $normalizedStats['manual_sites'][] = $site;
            }
        }
    }
    $alertSites = $statsData['alertSites'] ?? [];
    if (is_array($alertSites)) {
        foreach (array_slice($alertSites, 0, 400) as $site) {
            $site = substr(trim((string) $site), 0, 255);
            if ($site !== '' && preg_match('/^[a-z0-9.-]+$/i', $site)) {
                $normalizedStats['alert_sites'][] = $site;
            }
        }
    }
}

$normalizedSignals = [];
foreach ($signals as $key => $value) {
    if (!is_string($key)) {
        continue;
    }
    $normalizedSignals[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
}

$country = $_SERVER['HTTP_CF_IPCOUNTRY']
    ?? $_SERVER['HTTP_X_COUNTRY']
    ?? $_SERVER['HTTP_GEOIP_COUNTRY_CODE']
    ?? '';
$country = substr(preg_replace('/[^A-Z]/', '', (string) $country), 0, 2);

$entry = [
    'type' => $type,
    'received_at' => gmdate('c'),
    'url' => $url,
    'hostname' => $hostname,
    'message' => $message,
    'timestamp' => $timestamp,
    'signals' => $normalizedSignals,
    'detected_content' => $detectedContent,
    'full_context' => $fullContext,
    'blocked' => $blocked ? 1 : 0,
    'stats' => $normalizedStats,
    'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'country' => $country
];
$logLine = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

$pdo = openDatabase($dbPath, $schemaPath, $defaultSchemaSql, $debugFile);
$inserted = false;
if ($pdo instanceof PDO) {
    try {
        if ($type === 'stats') {
            $statement = $pdo->prepare(
                'INSERT INTO stats (received_at, enabled, alert_count, block_count, manual_sites_json, country)
                 VALUES (:received_at, :enabled, :alert_count, :block_count, :manual_sites_json, :country)'
            );
            $statement->execute([
                ':received_at' => $entry['received_at'],
                ':enabled' => $normalizedStats['enabled'] ? 1 : 0,
                ':alert_count' => $normalizedStats['alert_count'],
                ':block_count' => $normalizedStats['block_count'],
                ':manual_sites_json' => json_encode($normalizedStats['manual_sites'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':country' => $entry['country']
            ]);
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO reports (received_at, url, hostname, message, detected_content, full_context, signals_json, blocked, user_agent, ip, country)
                 VALUES (:received_at, :url, :hostname, :message, :detected_content, :full_context, :signals_json, :blocked, :user_agent, :ip, :country)'
            );
            $statement->execute([
                ':received_at' => $entry['received_at'],
                ':url' => $entry['url'],
                ':hostname' => $entry['hostname'],
                ':message' => $entry['message'],
                ':detected_content' => $entry['detected_content'],
                ':full_context' => $entry['full_context'],
                ':signals_json' => json_encode($entry['signals'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':blocked' => $entry['blocked'],
                ':user_agent' => $entry['user_agent'],
                ':ip' => $entry['ip'],
                ':country' => $entry['country']
            ]);
        }
        $inserted = true;
    } catch (Throwable $exception) {
        writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'db_path' => $dbPath]);
    }
}

if (file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX) === false) {
    respondWithError(500, 'Failed to write report', $debugFile, ['log_file' => $logFile]);
}

if ($manualReport && $hostname !== '') {
    $listFile = __DIR__ . '/clickfixlist';
    $existing = [];
    if (is_readable($listFile)) {
        $lines = file($listFile, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $existing[strtolower($line)] = true;
        }
    }
    $normalized = strtolower($hostname);
    if (!isset($existing[$normalized])) {
        if (!is_writable($listFile) && !is_writable(__DIR__)) {
            respondWithError(500, 'Blocklist is not writable', $debugFile, ['blocklist' => $listFile]);
        }
        $lineToAdd = $hostname . PHP_EOL;
        if (file_put_contents($listFile, $lineToAdd, FILE_APPEND | LOCK_EX) === false) {
            respondWithError(500, 'Failed to update blocklist', $debugFile, ['blocklist' => $listFile]);
        }
    }
}

if ($type === 'stats' && !empty($normalizedStats['alert_sites'])) {
    $listFile = __DIR__ . '/alertsites';
    $existing = [];
    if (is_readable($listFile)) {
        $lines = file($listFile, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $existing[strtolower($line)] = true;
        }
    }
    foreach ($normalizedStats['alert_sites'] as $site) {
        $normalized = strtolower($site);
        if (isset($existing[$normalized])) {
            continue;
        }
        if (!is_writable($listFile) && !is_writable(__DIR__)) {
            respondWithError(500, 'Alertsites list is not writable', $debugFile, ['alertsites' => $listFile]);
        }
        $lineToAdd = $site . PHP_EOL;
        if (file_put_contents($listFile, $lineToAdd, FILE_APPEND | LOCK_EX) === false) {
            respondWithError(500, 'Failed to update alertsites list', $debugFile, ['alertsites' => $listFile]);
        }
        $existing[$normalized] = true;
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
writeDebugLog($debugFile, [
    'status' => 'ok',
    'type' => $type,
    'url' => $url,
    'hostname' => $hostname,
    'manualReport' => $manualReport
]);
