<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = null;

if (!empty($rawBody)) {
    $payload = json_decode($rawBody, true);
}

if (!is_array($payload)) {
    $payload = $_POST;
}

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

$url = isset($payload['url']) ? trim((string) $payload['url']) : '';
$hostname = isset($payload['hostname']) ? trim((string) $payload['hostname']) : '';
$message = isset($payload['message']) ? trim((string) $payload['message']) : '';
$timestamp = isset($payload['timestamp']) ? (string) $payload['timestamp'] : '';
$signals = isset($payload['signals']) && is_array($payload['signals']) ? $payload['signals'] : [];

if ($url === '' && $hostname === '' && $message === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$entry = [
    'received_at' => gmdate('c'),
    'url' => $url,
    'hostname' => $hostname,
    'message' => $message,
    'timestamp' => $timestamp,
    'signals' => $signals,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
];

$logFile = __DIR__ . '/clickfix-report.log';
$logLine = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

if (file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to write report']);
    exit;
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
