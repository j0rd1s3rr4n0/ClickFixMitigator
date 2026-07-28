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
    !clickfix_token_has_scopes($claims, ['report:write'])
    && !clickfix_token_has_scopes($claims, ['reviews:write'])
) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'insufficient_scope']);
}

$clientId = (int) ($claims['sub'] ?? 0);
$tier = (string) ($claims['tier'] ?? 'basic');
$rpm = (int) ($claims['rpm'] ?? 120);
$rateBucket = (string) ($claims['rate_bucket'] ?? ('client:' . $clientId));
if (!clickfix_api_rate_limit($pdo, 'review:ip:' . $ip, 120, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}
if (!clickfix_api_rate_limit($pdo, 'review:' . $rateBucket, max(20, min(600, $rpm)), 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}

$rawBody = file_get_contents('php://input', false, null, 0, 8192);
if (!is_string($rawBody) || trim($rawBody) === '') {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'empty_body']);
}
try {
    $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'invalid_json']);
}

$reportId = max(0, (int) ($body['report_id'] ?? 0));
$reviewStatus = clickfix_api_normalize_report_status((string) ($body['review_status'] ?? 'pending'));
if ($reportId <= 0) {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'report_id_required']);
}
if (!in_array($reviewStatus, ['pending', 'accepted', 'rejected', 'allowlisted'], true)) {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'invalid_review_status']);
}

$reviewerId = max(0, (int) ($claims['user_id'] ?? 0));
$updated = clickfix_update_report_review($pdo, $reportId, $reviewStatus, $reviewerId);
if (!$updated) {
    clickfix_api_json(404, ['status' => 'error', 'message' => 'report_not_updated']);
}

$report = clickfix_report_by_id($pdo, $reportId);
if (!is_array($report)) {
    clickfix_api_json(200, [
        'status' => 'ok',
        'report_id' => $reportId,
        'review_status' => $reviewStatus,
    ]);
}

$reportRow = clickfix_enrich_report_rows([$report]);
$shaped = is_array($reportRow[0] ?? null) ? clickfix_api_shape_alert_row($reportRow[0], true) : null;

clickfix_api_json(200, [
    'status' => 'ok',
    'generated_at' => gmdate('c'),
    'client_id' => $clientId,
    'tier' => $tier,
    'report_id' => $reportId,
    'review_status' => $reviewStatus,
    'alert' => $shaped,
]);
