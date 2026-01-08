<?php
declare(strict_types=1);

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

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
$statsData = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
$manualReport = filter_var($payload['manualReport'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

$url = substr($url, 0, 2048);
$hostname = substr($hostname, 0, 255);
$message = substr($message !== '' ? $message : $reason, 0, 2000);
$timestamp = substr($timestamp, 0, 100);
$detectedContent = substr($detectedContent, 0, 4000);

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

http_response_code(200);
echo json_encode(['status' => 'ok']);
writeDebugLog($debugFile, [
    'status' => 'ok',
    'type' => $type,
    'url' => $url,
    'hostname' => $hostname,
    'manualReport' => $manualReport
]);
