<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/clickfix_core.php';

clickfix_apply_api_headers('GET, OPTIONS', 'Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    clickfix_api_json(405, ['status' => 'error', 'message' => 'method_not_allowed']);
}

$pdo = clickfix_open_db(true);
$ip = clickfix_client_ip();
if (!clickfix_api_rate_limit($pdo, 'cfgpub:ip:' . $ip, 240, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}

$configRaw = clickfix_load_score_config(false);
$config = isset($configRaw['scoreConfig']) && is_array($configRaw['scoreConfig'])
    ? $configRaw['scoreConfig']
    : $configRaw;
if (!is_array($config) || empty($config)) {
    clickfix_api_json(404, ['status' => 'error', 'message' => 'score_config_not_found']);
}

$issuedAt = gmdate('c');
$expiresAt = gmdate('c', time() + 600);
$envelope = [
    'version' => 1,
    'issuer' => 'clickfix-public-config',
    'issued_at' => $issuedAt,
    'expires_at' => $expiresAt,
    'tier' => 'basic',
    'scoreConfig' => $config,
];
$signedPayload = clickfix_canonical_json($envelope);
$signature = clickfix_sign_payload($signedPayload);

$response = [
    'status' => 'ok',
    'scoreConfig' => $config,
];
if ($signature !== null) {
    $response['signed_payload'] = $signedPayload;
    $response['signature'] = (string) ($signature['signature'] ?? '');
    $response['algorithm'] = (string) ($signature['algorithm'] ?? 'RSASSA-PKCS1-v1_5-SHA256');
    $response['key_id'] = (string) ($signature['key_id'] ?? 'default');
}

clickfix_api_json(200, $response);

