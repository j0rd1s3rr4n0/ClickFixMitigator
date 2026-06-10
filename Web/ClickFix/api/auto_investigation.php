<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/clickfix_core.php';
require_once dirname(__DIR__) . '/src/clickfix_auto_investigation.php';

clickfix_apply_api_headers('GET, POST, OPTIONS', 'Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = clickfix_open_db(true);
clickfix_llm_ensure_table($pdo);
$ip = clickfix_client_ip();

if (!clickfix_api_rate_limit($pdo, 'auto_inv:ip:' . $ip, 60, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}
if (!clickfix_is_request_origin_allowed(true)) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'origin_not_allowed']);
}

$claims = clickfix_authenticate_api_request($pdo, []);
if (!is_array($claims)) {
    clickfix_api_json(401, ['status' => 'error', 'message' => 'unauthorized']);
}
if (!clickfix_token_has_scopes($claims, ['intel:read', 'investigations:write'])) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'insufficient_scope']);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $action = strtolower(trim((string) ($_GET['action'] ?? 'status')));
    if ($action === 'status') {
        clickfix_api_json(200, [
            'status' => 'ok',
            'enabled' => clickfix_auto_investigation_is_enabled($pdo),
            'min_score' => (int) clickfix_auto_investigation_setting($pdo, 'min_score', '60'),
            'max_depth' => (int) clickfix_auto_investigation_setting($pdo, 'max_depth', '3'),
            'llm_enrich' => clickfix_auto_investigation_setting($pdo, 'llm_enrich', '0') === '1',
            'llm_profile_id' => (int) clickfix_auto_investigation_setting($pdo, 'llm_profile_id', '0'),
            'schedule_interval_minutes' => (int) clickfix_auto_investigation_setting($pdo, 'schedule_interval_minutes', '15'),
        ]);
    }
    if ($action === 'jobs') {
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 30)));
        $jobs = clickfix_auto_investigation_recent_jobs($pdo, $limit);
        clickfix_api_json(200, ['status' => 'ok', 'jobs' => $jobs, 'count' => count($jobs)]);
    }
    if ($action === 'pending_alerts') {
        $alerts = clickfix_auto_investigation_scan_new_alerts($pdo);
        clickfix_api_json(200, ['status' => 'ok', 'alerts' => $alerts, 'count' => count($alerts)]);
    }
    clickfix_api_json(400, ['status' => 'error', 'message' => 'unknown_action']);
}

if ($method === 'POST') {
    $input = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $action = strtolower(trim((string) ($input['action'] ?? ($_POST['action'] ?? 'run'))));
    if ($action === 'run') {
        $result = clickfix_auto_investigation_worker_batch($pdo);
        clickfix_api_json(200, ['status' => 'ok', 'result' => $result]);
    }
    if ($action === 'settings') {
        $settings = is_array($input['settings'] ?? null) ? $input['settings'] : [];
        $ok = clickfix_auto_investigation_set_settings($pdo, $settings);
        clickfix_api_json(200, ['status' => $ok ? 'ok' : 'error', 'message' => $ok ? 'settings_updated' : 'update_failed']);
    }
    if ($action === 'toggle') {
        $enabled = clickfix_auto_investigation_is_enabled($pdo);
        clickfix_auto_investigation_set_settings($pdo, ['enabled' => $enabled ? '0' : '1']);
        clickfix_api_json(200, ['status' => 'ok', 'enabled' => !$enabled]);
    }
    if ($action === 'run_job') {
        $jobId = max(0, (int) ($input['job_id'] ?? 0));
        if ($jobId <= 0) {
            clickfix_api_json(400, ['status' => 'error', 'message' => 'job_id_required']);
        }
        $result = clickfix_auto_investigation_run_job($pdo, $jobId);
        clickfix_api_json(200, ['status' => 'ok', 'result' => $result]);
    }
    clickfix_api_json(400, ['status' => 'error', 'message' => 'unknown_action']);
}

clickfix_api_json(405, ['status' => 'error', 'message' => 'method_not_allowed']);
