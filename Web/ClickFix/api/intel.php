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
    !clickfix_token_has_scopes($claims, ['intel:read'])
    && !clickfix_token_has_scopes($claims, ['config:read'])
) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'insufficient_scope']);
}

$clientId = (int) ($claims['sub'] ?? 0);
$tier = (string) ($claims['tier'] ?? 'basic');
$rpm = (int) ($claims['rpm'] ?? 120);
$rateBucket = (string) ($claims['rate_bucket'] ?? ('client:' . $clientId));
$apiAuthType = strtolower(trim((string) ($claims['auth'] ?? 'bearer')));
$userRole = clickfix_normalize_role((string) ($claims['user_role'] ?? 'analyst_sr'));

if (!clickfix_api_rate_limit($pdo, 'intel:ip:' . $ip, 180, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}
if (!clickfix_api_rate_limit($pdo, 'intel:' . $rateBucket, max(30, min(2000, $rpm)), 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}

$view = strtolower(trim((string) ($_GET['view'] ?? 'iocs')));
if (!in_array($view, ['alerts', 'iocs', 'events', 'stix'], true)) {
    $view = 'iocs';
}
$format = strtolower(trim((string) ($_GET['format'] ?? 'json')));
$limitDefault = ($view === 'events') ? 120 : 200;
$limit = (int) ($_GET['limit'] ?? $limitDefault);
$limit = max(1, min(2000, $limit));
$sinceId = max(0, (int) ($_GET['since_id'] ?? 0));
$since = (string) ($_GET['since'] ?? '');
$review = (string) ($_GET['review_status'] ?? 'all');
$blocked = (string) ($_GET['blocked'] ?? 'all');
$iocType = (string) ($_GET['type'] ?? 'all');
$graphId = max(0, (int) ($_GET['graph_id'] ?? 0));
$includeContext = ((string) ($_GET['include_context'] ?? '0')) === '1';
if ($includeContext && $apiAuthType === 'api_key' && clickfix_role_rank($userRole) < clickfix_role_rank('analyst_sr')) {
    $includeContext = false;
}

if ($view === 'alerts') {
    $rows = clickfix_api_fetch_alert_rows($pdo, $limit, $sinceId, $since, $review, $blocked);
    $alerts = [];
    $maxId = $sinceId;
    foreach ($rows as $row) {
        $alerts[] = clickfix_api_shape_alert_row($row, $includeContext);
        $maxId = max($maxId, (int) ($row['id'] ?? 0));
    }
    clickfix_api_json(200, [
        'status' => 'ok',
        'view' => 'alerts',
        'generated_at' => gmdate('c'),
        'client_id' => $clientId,
        'tier' => $tier,
        'count' => count($alerts),
        'next_since_id' => $maxId,
        'alerts' => $alerts,
    ]);
}

if ($view === 'events') {
    $events = clickfix_api_fetch_investigation_events_feed($pdo, $limit, $sinceId, $graphId);
    $maxId = $sinceId;
    foreach ($events as $event) {
        $maxId = max($maxId, (int) ($event['id'] ?? 0));
    }
    clickfix_api_json(200, [
        'status' => 'ok',
        'view' => 'events',
        'generated_at' => gmdate('c'),
        'client_id' => $clientId,
        'tier' => $tier,
        'count' => count($events),
        'next_since_id' => $maxId,
        'events' => $events,
    ]);
}

$sourceLimit = max(100, min(4000, $limit * 4));
$sourceRows = clickfix_api_fetch_alert_rows($pdo, $sourceLimit, $sinceId, $since, $review, $blocked);
$iocs = clickfix_api_build_ioc_feed($sourceRows, $iocType, $limit);

if ($view === 'stix' || $format === 'stix' || $format === 'stix2') {
    $bundle = clickfix_api_iocs_to_stix_bundle($iocs, 'ClickFix Mitigator');
    clickfix_api_json(200, [
        'status' => 'ok',
        'view' => 'stix',
        'generated_at' => gmdate('c'),
        'client_id' => $clientId,
        'tier' => $tier,
        'ioc_count' => count($iocs),
        'bundle' => $bundle,
    ]);
}

clickfix_api_json(200, [
    'status' => 'ok',
    'view' => 'iocs',
    'generated_at' => gmdate('c'),
    'client_id' => $clientId,
    'tier' => $tier,
    'count' => count($iocs),
    'iocs' => $iocs,
]);
