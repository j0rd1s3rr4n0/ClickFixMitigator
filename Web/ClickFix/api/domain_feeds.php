<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/clickfix_core.php';
require_once dirname(__DIR__) . '/src/clickfix_domain_feeds.php';

clickfix_apply_api_headers('GET, POST, OPTIONS', 'Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$pdo = clickfix_open_db(true);
clickfix_domain_feeds_ensure_table($pdo);
$ip = clickfix_client_ip();

if (!clickfix_api_rate_limit($pdo, 'domain_feed:ip:' . $ip, 60, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}

$claims = clickfix_authenticate_api_request($pdo, []);
$isAuth = is_array($claims);
$userId = $isAuth ? (int) ($claims['sub'] ?? 0) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = strtolower(trim((string) ($_GET['action'] ?? 'list')));
    if ($action === 'list') {
        $limit = max(1, min(500, (int) ($_GET['limit'] ?? 200)));
        $source = (string) ($_GET['source'] ?? '');
        $search = (string) ($_GET['q'] ?? '');
        $entries = clickfix_domain_feeds_get_entries($pdo, $limit, $source, $search);
        clickfix_api_json(200, ['status' => 'ok', 'entries' => $entries, 'count' => count($entries)]);
    }
    if ($action === 'stats') {
        $stats = clickfix_domain_feeds_get_stats($pdo);
        $log = clickfix_domain_feeds_log_recent($pdo, 10);
        clickfix_api_json(200, ['status' => 'ok', 'stats' => $stats, 'recent_log' => $log]);
    }
    if ($action === 'sources') {
        clickfix_api_json(200, ['status' => 'ok', 'sources' => clickfix_domain_feeds_sources()]);
    }
    clickfix_api_json(400, ['status' => 'error', 'message' => 'unknown_action']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAuth || !clickfix_token_has_scopes($claims, ['lists:write'])) {
        clickfix_api_json(403, ['status' => 'error', 'message' => 'unauthorized_or_insufficient_scope']);
    }
    $input = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $action = strtolower(trim((string) ($input['action'] ?? 'fetch')));
    if ($action === 'fetch') {
        $results = clickfix_domain_feeds_fetch_all($pdo);
        clickfix_api_json(200, ['status' => 'ok', 'results' => $results]);
    }
    if ($action === 'fetch_details') {
        $limit = max(1, min(20, (int) ($input['limit'] ?? 5)));
        $results = clickfix_domain_feeds_fetch_carson_detail_for_recent($pdo, $limit);
        clickfix_api_json(200, ['status' => 'ok', 'details_fetched' => count($results), 'results' => $results]);
    }
    if ($action === 'import_one') {
        $entryId = max(0, (int) ($input['entry_id'] ?? 0));
        if ($entryId <= 0) { clickfix_api_json(400, ['status' => 'error', 'message' => 'entry_id_required']); }
        $ok = clickfix_domain_feeds_import_to_blocklist($pdo, $entryId, $userId);
        clickfix_api_json(200, ['status' => $ok ? 'ok' : 'error', 'message' => $ok ? 'imported' : 'import_failed']);
    }
    if ($action === 'import_all') {
        $source = (string) ($input['source'] ?? '');
        $result = clickfix_domain_feeds_import_all_new($pdo, $userId, $source);
        clickfix_api_json(200, ['status' => 'ok', 'result' => $result]);
    }
    clickfix_api_json(400, ['status' => 'error', 'message' => 'unknown_action']);
}

clickfix_api_json(405, ['status' => 'error', 'message' => 'method_not_allowed']);
