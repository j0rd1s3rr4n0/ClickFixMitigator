<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/clickfix_core.php';

clickfix_apply_api_headers('POST, OPTIONS', 'Content-Type, Authorization, X-API-Key');

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
if (!clickfix_api_rate_limit($pdo, 'msgdispatch:ip:' . $ip, 120, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}
$claims = clickfix_authenticate_api_request($pdo, ['messages:write']);
if (!is_array($claims)) {
    clickfix_api_json(401, ['status' => 'error', 'message' => 'unauthorized']);
}
if (($claims['auth'] ?? '') === 'api_key' && clickfix_role_rank((string) ($claims['user_role'] ?? 'analyst_jr')) < clickfix_role_rank('analyst_sr')) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'forbidden']);
}
$bucket = 'msgdispatch:actor:' . (string) ($claims['rate_bucket'] ?? ($claims['sub'] ?? 'unknown'));
$rpm = max(30, min(2000, (int) ($claims['rpm'] ?? 120)));
if (!clickfix_api_rate_limit($pdo, $bucket, $rpm, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}

try {
    $payload = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'invalid_json']);
}
if (!is_array($payload)) {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'invalid_payload']);
}

$scope = strtolower(trim((string) ($payload['scope'] ?? 'all')));
$title = (string) ($payload['title'] ?? '');
$body = (string) ($payload['body'] ?? '');
$severity = (string) ($payload['severity'] ?? 'info');
$expiresDays = max(1, min(30, (int) ($payload['expires_days'] ?? 7)));
$expiresAt = (string) ($payload['expires_at'] ?? '');
$createdBy = (int) ($claims['user_id'] ?? 0);

if ($scope === 'clients') {
    $clientIds = isset($payload['client_ids']) && is_array($payload['client_ids']) ? $payload['client_ids'] : [];
    $sent = clickfix_send_extension_message_to_clients($pdo, $createdBy, $clientIds, $title, $body, $severity, $expiresDays, $expiresAt);
    clickfix_api_json(200, ['status' => 'ok', 'sent' => $sent]);
}

$targetClientId = $scope === 'client' ? (string) ($payload['client_id'] ?? '') : null;
$ok = clickfix_send_extension_message($pdo, $createdBy, $scope, $targetClientId, $title, $body, $severity, $expiresDays, $expiresAt);
clickfix_api_json($ok ? 200 : 400, ['status' => $ok ? 'ok' : 'error', 'sent' => $ok ? 1 : 0]);
