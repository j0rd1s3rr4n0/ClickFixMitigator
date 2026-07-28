<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/clickfix_core.php';

clickfix_apply_api_headers('GET, POST, OPTIONS', 'Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (!clickfix_is_request_origin_allowed(true)) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'origin_not_allowed']);
}

$pdo = clickfix_open_db(true);
$ip = clickfix_client_ip();
if (!clickfix_api_rate_limit($pdo, 'lists:ip:' . $ip, 180, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    $claims = clickfix_authenticate_api_request($pdo, ['config:read']);
    if (!is_array($claims)) {
        clickfix_api_json(401, ['status' => 'error', 'message' => 'unauthorized']);
    }
    if (($claims['auth'] ?? '') === 'api_key' && clickfix_role_rank((string) ($claims['user_role'] ?? 'analyst_jr')) < clickfix_role_rank('analyst_sr')) {
        clickfix_api_json(403, ['status' => 'error', 'message' => 'forbidden']);
    }
    clickfix_api_json(200, [
        'status' => 'ok',
        'lists' => [
            'allowlist' => clickfix_load_list_file('allowlist'),
            'blocklist' => clickfix_load_list_file('blocklist'),
            'investigatelist' => clickfix_load_list_file('investigatelist'),
        ],
        'recent_actions' => clickfix_recent_list_actions($pdo, 60),
    ]);
}
if ($method !== 'POST') {
    clickfix_api_json(405, ['status' => 'error', 'message' => 'method_not_allowed']);
}

$claims = clickfix_authenticate_api_request($pdo, ['lists:write']);
if (!is_array($claims)) {
    clickfix_api_json(401, ['status' => 'error', 'message' => 'unauthorized']);
}
if (($claims['auth'] ?? '') === 'api_key' && clickfix_role_rank((string) ($claims['user_role'] ?? 'analyst_jr')) < clickfix_role_rank('analyst_sr')) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'forbidden']);
}
$bucket = 'lists:actor:' . (string) ($claims['rate_bucket'] ?? ($claims['sub'] ?? 'unknown'));
$rpm = max(30, min(2000, (int) ($claims['rpm'] ?? 120)));
if (!clickfix_api_rate_limit($pdo, $bucket, $rpm, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}

$rawBody = file_get_contents('php://input');
$payload = [];
if (is_string($rawBody) && trim($rawBody) !== '') {
    try {
        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        clickfix_api_json(400, ['status' => 'error', 'message' => 'invalid_json']);
    }
}
if (!is_array($payload)) {
    $payload = [];
}

$listType = (string) ($payload['list_type'] ?? 'blocklist');
$operation = (string) ($payload['operation'] ?? 'add');
$reason = (string) ($payload['reason'] ?? 'api');
$userId = (int) ($claims['user_id'] ?? 0);

if (!empty($payload['domains']) && is_array($payload['domains'])) {
    $domains = [];
    foreach ($payload['domains'] as $entry) {
        $domain = trim((string) $entry);
        if ($domain !== '') {
            $domains[] = $domain;
        }
    }
    $result = clickfix_apply_list_bulk_action($pdo, $userId, $listType, $operation, implode("
", $domains), $reason);
    clickfix_api_json(200, ['status' => 'ok', 'mode' => 'bulk', 'result' => $result]);
}

$domain = (string) ($payload['domain'] ?? '');
if (trim($domain) === '') {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'domain_required']);
}
$result = clickfix_apply_list_action($pdo, $userId, $listType, $operation, $domain, $reason);
clickfix_api_json(200, ['status' => $result ? 'ok' : 'error', 'mode' => 'single', 'applied' => $result]);
