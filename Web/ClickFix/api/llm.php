<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/clickfix_core.php';
require_once dirname(__DIR__) . '/src/clickfix_llm.php';

clickfix_bootstrap();

clickfix_apply_api_headers('POST, OPTIONS', 'Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = clickfix_open_db(true);
clickfix_llm_ensure_table($pdo);
$ip = clickfix_client_ip();

if (!clickfix_api_rate_limit($pdo, 'llm:ip:' . $ip, 60, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}
$isSession = (clickfix_current_user() !== null);
if (!$isSession && !clickfix_is_request_origin_allowed(true)) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'origin_not_allowed']);
}

$isAuth = false;
$claims = clickfix_authenticate_api_request($pdo, []);
if (is_array($claims)) {
    $isAuth = true;
}
if (!$isAuth) {
    $sessionUser = clickfix_current_user();
    if ($sessionUser !== null && clickfix_user_has_min_role($sessionUser, 'analyst_jr')) {
        $isAuth = true;
    }
}
if (!$isAuth) {
    clickfix_api_json(401, ['status' => 'error', 'message' => 'unauthorized']);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    clickfix_api_json(400, ['status' => 'error', 'message' => 'invalid_json']);
}

$action = strtolower(trim((string) ($input['action'] ?? 'chat')));

if ($action === 'models') {
    $profileId = max(0, (int) ($input['profile_id'] ?? 0));
    $result = clickfix_llm_list_models($pdo, $profileId);
    clickfix_api_json($result['ok'] ? 200 : 400, ['status' => $result['ok'] ? 'ok' : 'error', 'models' => $result['models'] ?? [], 'error' => $result['error'] ?? '']);
}

if ($action === 'chat') {
    $profileId = max(0, (int) ($input['profile_id'] ?? clickfix_llm_default_profile_id($pdo)));
    $messages = is_array($input['messages'] ?? null) ? $input['messages'] : [];
    $options = is_array($input['options'] ?? null) ? $input['options'] : [];
    $graphId = max(0, (int) ($input['graph_id'] ?? 0));
    if ($graphId > 0) {
        $lastMsg = '';
        foreach (array_reverse($messages) as $msg) {
            if (($msg['role'] ?? '') === 'user') {
                $lastMsg = (string) ($msg['content'] ?? '');
                break;
            }
        }
        $result = clickfix_llm_chat_investigation($pdo, $graphId, $lastMsg !== '' ? $lastMsg : 'Analyze this investigation', array_merge($options, ['profile_id' => $profileId, 'history' => array_slice($messages, 0, -1)]));
    } else {
        $result = clickfix_llm_call($pdo, $profileId, $messages, $options);
    }
    clickfix_api_json($result['ok'] ? 200 : 500, [
        'status' => $result['ok'] ? 'ok' : 'error',
        'content' => $result['content'] ?? '',
        'tokens' => $result['tokens'] ?? null,
        'model' => $result['model'] ?? '',
        'error' => $result['error'] ?? '',
    ]);
}

if ($action === 'summarize') {
    $graphId = max(0, (int) ($input['graph_id'] ?? 0));
    $profileId = max(0, (int) ($input['profile_id'] ?? clickfix_llm_default_profile_id($pdo)));
    $options = is_array($input['options'] ?? null) ? $input['options'] : [];
    if ($graphId <= 0) {
        clickfix_api_json(400, ['status' => 'error', 'message' => 'graph_id_required']);
    }
    $result = clickfix_llm_summarize_investigation($pdo, $graphId, array_merge($options, ['profile_id' => $profileId]));
    clickfix_api_json($result['ok'] ? 200 : 500, [
        'status' => $result['ok'] ? 'ok' : 'error',
        'content' => $result['content'] ?? '',
        'tokens' => $result['tokens'] ?? null,
        'error' => $result['error'] ?? '',
    ]);
}

if ($action === 'extract_iocs') {
    $text = trim((string) ($input['text'] ?? ''));
    $profileId = max(0, (int) ($input['profile_id'] ?? clickfix_llm_default_profile_id($pdo)));
    $graphId = max(0, (int) ($input['graph_id'] ?? 0));
    $options = is_array($input['options'] ?? null) ? $input['options'] : [];
    if ($text === '' && $graphId > 0) {
        $inv = clickfix_get_investigation_any($pdo, $graphId);
        if ($inv !== null) {
            $graph = is_array($inv['graph'] ?? null) ? $inv['graph'] : ['nodes'=>[],'edges'=>[]];
            $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
            $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
            $parts = [];
            $parts[] = "Title: " . ((string)($inv['title'] ?? ''));
            $parts[] = "Domain: " . ((string)($inv['site_domain'] ?? ''));
            $parts[] = "Verdict: " . ((string)($inv['verdict'] ?? ''));
            $parts[] = "Summary: " . ((string)($inv['summary'] ?? ''));
            $parts[] = "Notes: " . ((string)($inv['notes'] ?? ''));
            $parts[] = "Tags: " . ((string)($inv['tags_json'] ?? ''));
            $parts[] = "Nodes (" . count($nodes) . "):";
            foreach ($nodes as $n) {
                $parts[] = "- [" . ((string)($n['type']??'?')) . "] " . ((string)($n['label']??$n['id']??'')) . (isset($n['notes']) ? ': ' . ((string)$n['notes']) : '');
            }
            $parts[] = "Edges (" . count($edges) . "):";
            foreach ($edges as $e) {
                $parts[] = "- " . ((string)($e['source']??'')) . " -> " . ((string)($e['target']??'')) . " [" . ((string)($e['label']??'')) . "]";
            }
            $text = implode("\n", $parts);
        }
    }
    if ($text === '') {
        clickfix_api_json(400, ['status' => 'error', 'message' => 'text_required']);
    }
    $result = clickfix_llm_extract_iocs($pdo, $text, array_merge($options, ['profile_id' => $profileId]));
    clickfix_api_json($result['ok'] ? 200 : 500, [
        'status' => $result['ok'] ? 'ok' : 'error',
        'iocs' => $result['iocs'] ?? [],
        'tokens' => $result['tokens'] ?? null,
        'error' => $result['error'] ?? '',
    ]);
}

if ($action === 'profiles') {
    $profiles = clickfix_llm_configured_providers($pdo);
    $safe = array_map(static function (array $p): array {
        unset($p['api_key']);
        return $p;
    }, $profiles);
    clickfix_api_json(200, ['status' => 'ok', 'profiles' => $safe]);
}

clickfix_api_json(400, ['status' => 'error', 'message' => 'unknown_action', 'supported' => ['models', 'chat', 'summarize', 'extract_iocs', 'profiles']]);
