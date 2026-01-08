<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$maxBytes = 32768;
$rawBody = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
if ($rawBody === false || strlen($rawBody) > $maxBytes) {
    http_response_code(413);
    echo json_encode(['status' => 'error', 'message' => 'Payload too large']);
    exit;
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') === false) {
    http_response_code(415);
    echo json_encode(['status' => 'error', 'message' => 'Unsupported content type']);
    exit;
}

try {
    $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$type = isset($payload['type']) ? strtolower(trim((string) $payload['type'])) : 'alert';
if (!in_array($type, ['alert', 'stats'], true)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
    exit;
}

$url = isset($payload['url']) ? trim((string) $payload['url']) : '';
$hostname = isset($payload['hostname']) ? trim((string) $payload['hostname']) : '';
$message = isset($payload['message']) ? trim((string) $payload['message']) : '';
$timestamp = isset($payload['timestamp']) ? (string) $payload['timestamp'] : '';
$signals = isset($payload['signals']) && is_array($payload['signals']) ? $payload['signals'] : [];
$detectedContent = isset($payload['detectedContent'])
    ? trim((string) $payload['detectedContent'])
    : '';
$statsData = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];

$url = substr($url, 0, 2048);
$hostname = substr($hostname, 0, 255);
$message = substr($message, 0, 2000);
$timestamp = substr($timestamp, 0, 100);
$detectedContent = substr($detectedContent, 0, 4000);

if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid url']);
    exit;
}

if ($hostname !== '' && !preg_match('/^[a-z0-9.-]+$/i', $hostname)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid hostname']);
    exit;
}

if ($type === 'alert' && $url === '' && $hostname === '' && $message === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$normalizedStats = [];
if ($type === 'stats') {
    $normalizedStats = [
        'enabled' => filter_var($statsData['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
        'alert_count' => (int) ($statsData['alertCount'] ?? 0),
        'block_count' => (int) ($statsData['blockCount'] ?? 0),
        'manual_sites' => []
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
    'stats' => $normalizedStats,
    'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'country' => $country
];

$logFile = $type === 'stats'
    ? __DIR__ . '/clickfix-stats.log'
    : __DIR__ . '/clickfix-report.log';
$logLine = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

if (file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to write report']);
    exit;
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
