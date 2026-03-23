<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/clickfix_core.php';

clickfix_apply_api_headers('GET, OPTIONS', 'Content-Type, Authorization');

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

$pdo = clickfix_open_db(true);
$ip = clickfix_client_ip();
if (clickfix_jwt_secret() === 'CHANGE_ME_CLICKFIX_API_JWT_SECRET') {
    clickfix_api_json(503, ['status' => 'error', 'message' => 'jwt_secret_not_configured']);
}
$claims = clickfix_authenticate_api_request($pdo, ['config:read']);
if (!is_array($claims)) {
    clickfix_api_json(401, ['status' => 'error', 'message' => 'unauthorized']);
}

$clientId = (int) ($claims['sub'] ?? 0);
$tier = (string) ($claims['tier'] ?? 'basic');
$rpm = (int) ($claims['rpm'] ?? 120);

if (!clickfix_api_rate_limit($pdo, 'cfg:ip:' . $ip, 120, 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}
if (!clickfix_api_rate_limit($pdo, 'cfg:client:' . $clientId, max(30, min(2000, $rpm)), 60)) {
    clickfix_api_json(429, ['status' => 'error', 'message' => 'rate_limited']);
}

function clickfix_score_config_merge(array $base, array $overrides): array
{
    foreach ($overrides as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = clickfix_score_config_merge($base[$key], $value);
        } else {
            $base[$key] = $value;
        }
    }
    return $base;
}

$basePath = dirname(__DIR__, 2) . '/clickfix-score-config.json';
$premiumPathConfig = (string) clickfix_env('CLICKFIX_PREMIUM_SCORE_CONFIG_PATH', 'clickfix-score-config-premium.json');
$premiumPath = clickfix_resolve_env_path($premiumPathConfig);

$baseConfig = [];
if (is_readable($basePath)) {
    $decoded = json_decode((string) file_get_contents($basePath), true);
    if (is_array($decoded)) {
        $baseConfig = $decoded;
    }
}
if (!isset($baseConfig['scoreConfig']) && !isset($baseConfig['weights'])) {
    clickfix_api_json(500, ['status' => 'error', 'message' => 'base_score_config_missing']);
}

$config = isset($baseConfig['scoreConfig']) && is_array($baseConfig['scoreConfig'])
    ? $baseConfig['scoreConfig']
    : $baseConfig;

if (in_array($tier, ['premium', 'enterprise'], true) && is_string($premiumPath) && is_readable($premiumPath)) {
    $premiumRaw = json_decode((string) file_get_contents($premiumPath), true);
    if (is_array($premiumRaw)) {
        $premiumConfig = isset($premiumRaw['scoreConfig']) && is_array($premiumRaw['scoreConfig'])
            ? $premiumRaw['scoreConfig']
            : $premiumRaw;
        $config = clickfix_score_config_merge($config, $premiumConfig);
    }
}

$issuedAt = gmdate('c');
$expiresAt = gmdate('c', time() + 600);
$envelope = [
    'version' => 1,
    'issuer' => 'clickfix-premium-config',
    'issued_at' => $issuedAt,
    'expires_at' => $expiresAt,
    'client_id' => $clientId,
    'tier' => $tier,
    'scoreConfig' => $config,
];
$signedPayload = clickfix_canonical_json($envelope);
$signature = clickfix_sign_payload($signedPayload);
if ($signature === null) {
    clickfix_api_json(503, ['status' => 'error', 'message' => 'signature_unavailable']);
}

clickfix_api_json(200, [
    'status' => 'ok',
    'signed_payload' => $signedPayload,
    'signature' => (string) ($signature['signature'] ?? ''),
    'algorithm' => (string) ($signature['algorithm'] ?? 'RSASSA-PKCS1-v1_5-SHA256'),
    'key_id' => (string) ($signature['key_id'] ?? 'default'),
]);
