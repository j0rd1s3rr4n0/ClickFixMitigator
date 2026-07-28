<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/clickfix_core.php';
require_once dirname(__DIR__) . '/src/clickfix_blog_feed.php';

clickfix_bootstrap();

clickfix_apply_api_headers('GET, OPTIONS', 'Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    clickfix_api_json(405, ['status' => 'error', 'message' => 'method_not_allowed']);
}

$pdo = clickfix_open_db(true);
$ip = clickfix_client_ip();

if (!clickfix_api_rate_limit($pdo, 'blog:ip:' . $ip, 120, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}
$isFromDashboard = (clickfix_current_user() !== null);
if (!$isFromDashboard && !clickfix_is_request_origin_allowed(true)) {
    clickfix_api_json(403, ['status' => 'error', 'message' => 'origin_not_allowed']);
}

$action = strtolower(trim((string) ($_GET['action'] ?? 'feed')));

if ($action === 'refresh') {
    $claims = clickfix_authenticate_api_request($pdo, []);
    if (!is_array($claims)) {
        clickfix_api_json(401, ['status' => 'error', 'message' => 'unauthorized']);
    }
    if (!clickfix_token_has_scopes($claims, ['intel:read', 'config:read'])) {
        clickfix_api_json(403, ['status' => 'error', 'message' => 'insufficient_scope']);
    }
    $results = clickfix_blog_feed_refresh($pdo);
    clickfix_blog_feed_cache_cleanup($pdo);
    clickfix_api_json(200, ['status' => 'ok', 'results' => $results]);
}

if ($action === 'feed') {
    $limit = max(1, min(50, (int) ($_GET['limit'] ?? 20)));
    $sourceLabel = strtolower(trim((string) ($_GET['source'] ?? '')));
    $sourceUrl = '';
    foreach (clickfix_blog_feed_sources() as $src) {
        if ($sourceLabel !== '' && strtolower((string) ($src['label'] ?? '')) === $sourceLabel) {
            $sourceUrl = (string) ($src['url'] ?? '');
            break;
        }
    }
    $items = clickfix_blog_feed_cache_get($pdo, $sourceUrl, $limit);
    clickfix_api_json(200, ['status' => 'ok', 'items' => $items, 'count' => count($items)]);
}

if ($action === 'crosslinks') {
    $crosslinks = clickfix_blog_feed_crosslink_investigations($pdo);
    clickfix_api_json(200, ['status' => 'ok', 'crosslinks' => $crosslinks, 'count' => count($crosslinks)]);
}

if ($action === 'sources') {
    $sources = clickfix_blog_feed_sources();
    clickfix_api_json(200, ['status' => 'ok', 'sources' => $sources]);
}

clickfix_api_json(400, ['status' => 'error', 'message' => 'unknown_action', 'supported' => ['feed', 'refresh', 'crosslinks', 'sources']]);
