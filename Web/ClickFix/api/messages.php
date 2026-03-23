<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/clickfix_core.php';

clickfix_apply_api_headers('GET, OPTIONS', 'Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    clickfix_api_json(405, ['status' => 'error', 'message' => 'method_not_allowed']);
}
if (!clickfix_is_request_origin_allowed(true)) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'origin_not_allowed']);
}

if (clickfix_jwt_secret() === 'CHANGE_ME_CLICKFIX_API_JWT_SECRET') {
    clickfix_api_json(503, ['status' => 'error', 'message' => 'jwt_secret_not_configured']);
}

$pdo = clickfix_open_db(true);
$ip = clickfix_client_ip();
$claims = clickfix_authenticate_api_request($pdo, ['report:write']);
if (!is_array($claims)) {
    clickfix_api_json(401, ['status' => 'error', 'message' => 'unauthorized']);
}

$clientId = (int) ($claims['sub'] ?? 0);
$rpm = (int) ($claims['rpm'] ?? 120);
$deviceId = substr(trim((string) ($claims['device'] ?? '')), 0, 120);
$requestedClientId = substr(trim((string) ($_GET['client_id'] ?? '')), 0, 120);
if ($requestedClientId !== '' && $deviceId !== '' && !hash_equals($deviceId, $requestedClientId)) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'client_id_mismatch']);
}
if ($requestedClientId === '') {
    $requestedClientId = $deviceId !== '' ? $deviceId : ('client-' . $clientId);
}

if (!clickfix_api_rate_limit($pdo, 'msg:ip:' . $ip, 180, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}
if (!clickfix_api_rate_limit($pdo, 'msg:client:' . $clientId, max(30, min(2000, $rpm)), 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}

$messages = clickfix_extension_messages_for_client($pdo, $requestedClientId, 40);
clickfix_api_json(200, [
    'status' => 'ok',
    'client_id' => $requestedClientId,
    'messages' => $messages,
]);
