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

$basePath = rtrim((string) dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/api/docs.php')), '/\\');
if ($basePath === '') {
    $basePath = '/api';
}

$docs = [
    'status' => 'ok',
    'generated_at' => gmdate('c'),
    'service' => 'ClickFix Mitigator API',
    'authentication' => [
        [
            'type' => 'bearer',
            'description' => 'License/device flow token issued by /api/token.php and refreshed by /api/refresh.php.',
            'header' => 'Authorization: Bearer <access_token>',
        ],
        [
            'type' => 'api_key',
            'description' => 'User API key generated from the dashboard.',
            'headers' => [
                'X-API-Key: <api_key>',
                'Authorization: ApiKey <api_key>',
            ],
        ],
    ],
    'rate_limit' => [
        'model' => 'Per IP and per token/key bucket.',
        'notes' => [
            'Each endpoint enforces additional limits.',
            'Platform API keys support custom max_rpm.',
            'Bearer clients inherit max_rpm from the API client profile.',
        ],
    ],
    'endpoints' => [
        [
            'path' => $basePath . '/token.php',
            'method' => 'POST',
            'scope' => 'public',
            'description' => 'Issue bearer and refresh tokens from license_key + device_id.',
        ],
        [
            'path' => $basePath . '/refresh.php',
            'method' => 'POST',
            'scope' => 'bearer',
            'description' => 'Refresh bearer access token.',
        ],
        [
            'path' => $basePath . '/alerts.php',
            'method' => 'GET',
            'scope' => 'alerts:read | intel:read | config:read',
            'description' => 'Direct alert list endpoint with filters and incremental sync.',
        ],
        [
            'path' => $basePath . '/alert.php',
            'method' => 'GET',
            'scope' => 'alerts:read | intel:read | config:read',
            'description' => 'Fetch a single alert by report_id.',
        ],
        [
            'path' => $basePath . '/intel.php',
            'method' => 'GET',
            'scope' => 'intel:read | config:read',
            'description' => 'Alerts, IOCs, investigation events and STIX feed.',
        ],
        [
            'path' => $basePath . '/lookup.php',
            'method' => 'GET',
            'scope' => 'intel:read | config:read',
            'description' => 'Lookup a single indicator (domain, IP or URL).',
        ],
        [
            'path' => $basePath . '/stats.php',
            'method' => 'GET',
            'scope' => 'stats:read | intel:read | config:read',
            'description' => 'Operational metrics and top countries summary.',
        ],
        [
            'path' => $basePath . '/review.php',
            'method' => 'POST',
            'scope' => 'report:write | reviews:write',
            'description' => 'Apply review verdict to an alert (pending, accepted, rejected, allowlisted).',
            'body' => [
                'report_id' => 'integer',
                'review_status' => 'pending|accepted|rejected|allowlisted',
            ],
        ],
        [
            'path' => $basePath . '/lists.php',
            'method' => 'GET',
            'scope' => 'config:read',
            'description' => 'Read allowlist, blocklist, investigatelist and recent list actions.',
        ],
        [
            'path' => $basePath . '/lists.php',
            'method' => 'POST',
            'scope' => 'lists:write',
            'description' => 'Add/remove one or many domains from operational lists.',
        ],
        [
            'path' => $basePath . '/investigations.php',
            'method' => 'GET',
            'scope' => 'investigations:read',
            'description' => 'List recent investigations or fetch one by graph_id.',
        ],
        [
            'path' => $basePath . '/investigations.php',
            'method' => 'POST',
            'scope' => 'investigations:write',
            'description' => 'Create or update an investigation graph.',
        ],
        [
            'path' => $basePath . '/messages.php',
            'method' => 'GET',
            'scope' => 'report:write',
            'description' => 'Extension operational messages for a client/device.',
        ],
        [
            'path' => $basePath . '/message-dispatch.php',
            'method' => 'POST',
            'scope' => 'messages:write',
            'description' => 'Dispatch operator messages to all clients, one client, or a client set.',
        ],
        [
            'path' => $basePath . '/score-config.php',
            'method' => 'GET',
            'scope' => 'public',
            'description' => 'Signed public score configuration.',
        ],
    ],
    'documentation' => [
        'english' => $basePath . '/INTEGRATIONS.md',
        'spanish' => $basePath . '/INTEGRACIONES_ES.md',
        'openapi' => $basePath . '/openapi.yaml',
    ],
];

clickfix_api_json(200, $docs);
