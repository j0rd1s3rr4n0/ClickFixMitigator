<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/clickfix_core.php';

clickfix_apply_api_headers('POST, OPTIONS', 'Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    clickfix_api_json(405, ['status' => 'error', 'message' => 'method_not_allowed']);
}
if (!clickfix_is_request_origin_allowed(true)) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'origin_not_allowed']);
}

$pdo = clickfix_open_db(true);
$ip = clickfix_client_ip();
if (clickfix_jwt_secret() === 'CHANGE_ME_CLICKFIX_API_JWT_SECRET') {
    clickfix_api_json(503, ['status' => 'error', 'message' => 'jwt_secret_not_configured']);
}
if (!clickfix_api_rate_limit($pdo, 'refresh:ip:' . $ip, 80, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}

$rawBody = file_get_contents('php://input', false, null, 0, 8192);
if (!is_string($rawBody) || $rawBody === '') {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'empty_body']);
}
try {
    $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'invalid_json']);
}

$refreshToken = trim((string) ($body['refresh_token'] ?? ''));
$deviceId = trim((string) ($body['device_id'] ?? ''));
if ($refreshToken === '' || $deviceId === '') {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'refresh_token_and_device_id_required']);
}

$tokens = clickfix_refresh_api_tokens($pdo, $refreshToken, $deviceId, $ip);
if (!$tokens) {
    clickfix_api_json(401, ['status' => 'error', 'message' => 'invalid_refresh_token']);
}
clickfix_api_json(200, ['status' => 'ok'] + $tokens);
