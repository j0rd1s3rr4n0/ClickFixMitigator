<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/clickfix_core.php';

clickfix_apply_api_headers('GET, OPTIONS', 'Content-Type, Authorization, X-API-Key');

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
if (
    clickfix_jwt_secret() === 'CHANGE_ME_CLICKFIX_API_JWT_SECRET'
    && clickfix_api_key_token() === ''
) {
    clickfix_api_json(503, ['status' => 'error', 'message' => 'jwt_secret_not_configured']);
}

$pdo = clickfix_open_db(true);
$ip = clickfix_client_ip();
$claims = clickfix_authenticate_api_request($pdo, []);
if (!is_array($claims)) {
    clickfix_api_json(401, ['status' => 'error', 'message' => 'unauthorized']);
}
if (
    !clickfix_token_has_scopes($claims, ['stats:read'])
    && !clickfix_token_has_scopes($claims, ['intel:read'])
    && !clickfix_token_has_scopes($claims, ['config:read'])
) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'insufficient_scope']);
}

$clientId = (int) ($claims['sub'] ?? 0);
$tier = (string) ($claims['tier'] ?? 'basic');
$rpm = (int) ($claims['rpm'] ?? 120);
$rateBucket = (string) ($claims['rate_bucket'] ?? ('client:' . $clientId));
if (!clickfix_api_rate_limit($pdo, 'stats:ip:' . $ip, 180, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}
if (!clickfix_api_rate_limit($pdo, 'stats:' . $rateBucket, max(30, min(2000, $rpm)), 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}

$metrics = clickfix_live_metrics($pdo);
$topCountries = is_array($metrics['countries'] ?? null) ? array_values((array) $metrics['countries']) : [];

clickfix_api_json(200, [
    'status' => 'ok',
    'generated_at' => gmdate('c'),
    'client_id' => $clientId,
    'tier' => $tier,
    'metrics' => $metrics,
    'top_countries' => $topCountries,
]);
