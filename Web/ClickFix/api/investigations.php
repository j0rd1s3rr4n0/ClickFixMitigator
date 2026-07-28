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
if (!clickfix_api_rate_limit($pdo, 'investigations:ip:' . $ip, 180, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if ($method === 'GET') {
    $claims = clickfix_authenticate_api_request($pdo, ['investigations:read']);
    if (!is_array($claims)) {
        clickfix_api_json(401, ['status' => 'error', 'message' => 'unauthorized']);
    }
    if (($claims['auth'] ?? '') === 'api_key' && clickfix_role_rank((string) ($claims['user_role'] ?? 'analyst_jr')) < clickfix_role_rank('analyst_jr')) {
        clickfix_api_json(403, ['status' => 'error', 'message' => 'forbidden']);
    }
    $userId = (int) ($claims['user_id'] ?? 0);
    $isAdmin = clickfix_normalize_role((string) ($claims['user_role'] ?? 'analyst_jr')) === 'admin';
    $limit = max(1, min(120, (int) ($_GET['limit'] ?? 40)));
    $graphId = max(0, (int) ($_GET['graph_id'] ?? 0));
    if ($graphId > 0) {
        $row = clickfix_get_investigation($pdo, $graphId, $userId, $isAdmin);
        clickfix_api_json($row ? 200 : 404, ['status' => $row ? 'ok' : 'error', 'investigation' => $row]);
    }
    clickfix_api_json(200, ['status' => 'ok', 'investigations' => clickfix_recent_investigations($pdo, $userId, $isAdmin, $limit)]);
}
if ($method !== 'POST') {
    clickfix_api_json(405, ['status' => 'error', 'message' => 'method_not_allowed']);
}

$claims = clickfix_authenticate_api_request($pdo, ['investigations:write']);
if (!is_array($claims)) {
    clickfix_api_json(401, ['status' => 'error', 'message' => 'unauthorized']);
}
if (($claims['auth'] ?? '') === 'api_key' && clickfix_role_rank((string) ($claims['user_role'] ?? 'analyst_jr')) < clickfix_role_rank('analyst_mid')) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'forbidden']);
}
$bucket = 'investigations:actor:' . (string) ($claims['rate_bucket'] ?? ($claims['sub'] ?? 'unknown'));
$rpm = max(30, min(2000, (int) ($claims['rpm'] ?? 120)));
if (!clickfix_api_rate_limit($pdo, $bucket, $rpm, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}

$rawBody = file_get_contents('php://input');
try {
    $payload = json_decode((string) $rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'invalid_json']);
}
if (!is_array($payload)) {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'invalid_payload']);
}

$userId = (int) ($claims['user_id'] ?? 0);
$isAdmin = clickfix_normalize_role((string) ($claims['user_role'] ?? 'analyst_jr')) === 'admin';
$graphId = isset($payload['graph_id']) ? (int) $payload['graph_id'] : null;
$title = (string) ($payload['title'] ?? '');
$domain = (string) ($payload['site_domain'] ?? $payload['domain'] ?? '');
$verdict = (string) ($payload['verdict'] ?? 'investigating');
$summary = (string) ($payload['summary'] ?? '');
$tags = (string) ($payload['tags'] ?? '');
$graph = isset($payload['graph']) && is_array($payload['graph']) ? $payload['graph'] : ['nodes' => [], 'edges' => []];
$sourceReportId = isset($payload['source_report_id']) ? (int) $payload['source_report_id'] : null;
$nextId = clickfix_investigation_save($pdo, $userId, $graphId, $title, $domain, $verdict, $summary, $tags, $graph, $isAdmin, $sourceReportId);
if ($nextId === null) {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'save_failed']);
}
clickfix_api_json(200, ['status' => 'ok', 'graph_id' => $nextId, 'investigation' => clickfix_get_investigation_any($pdo, $nextId)]);
