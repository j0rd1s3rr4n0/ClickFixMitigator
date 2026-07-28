<?php
declare(strict_types=1);

require_once __DIR__ . '/src/clickfix_core.php';

ini_set('display_errors', '0');
clickfix_apply_api_headers('POST, OPTIONS', 'Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

$dbPath = clickfix_resolve_db_path();
$schemaPath = null;
$preferredSchema = __DIR__ . '/data/clickfix.sql';
if (is_readable($preferredSchema)) {
    $schemaPath = $preferredSchema;
} else {
    $schemaCandidates = glob(__DIR__ . '/data/*.sql') ?: [];
    foreach ($schemaCandidates as $candidate) {
        if (is_readable($candidate)) {
            $schemaPath = $candidate;
            break;
        }
    }
}

$defaultSchemaSql = <<<SQL
CREATE TABLE IF NOT EXISTS reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    url TEXT,
    previous_url TEXT,
    hostname TEXT,
    message TEXT,
    detected_content TEXT,
    full_context TEXT,
    signals_json TEXT,
    blocked INTEGER DEFAULT 0,
    accepted INTEGER DEFAULT 0,
    accepted_by INTEGER,
    accepted_at TEXT,
    review_status TEXT DEFAULT 'pending',
    reviewed_by INTEGER,
    reviewed_at TEXT,
    client_id TEXT,
    score_total INTEGER,
    score_details_json TEXT,
    reason_entries_json TEXT,
    matched_snippets_json TEXT,
    duplicate_count INTEGER DEFAULT 1,
    last_seen TEXT,
    user_agent TEXT,
    ip TEXT,
    country TEXT,
    event_type TEXT DEFAULT 'clickfix_alert',
    runtime_verdict_json TEXT,
    server_score_total INTEGER,
    server_verdict TEXT,
    trusted_signal_source INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    enabled INTEGER,
    alert_count INTEGER,
    block_count INTEGER,
    manual_sites_json TEXT,
    user_agent TEXT,
    client_id TEXT,
    install_type TEXT,
    install_source TEXT,
    install_channel TEXT,
    country TEXT
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS appeals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    domain TEXT NOT NULL,
    reason TEXT NOT NULL,
    contact TEXT,
    status TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS list_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    list_type TEXT NOT NULL,
    domain TEXT NOT NULL,
    reason TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS list_suggestions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER,
    list_type TEXT NOT NULL,
    domain TEXT NOT NULL,
    reason TEXT NOT NULL,
    status TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS api_clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    label TEXT,
    license_key_hash TEXT NOT NULL UNIQUE,
    tier TEXT NOT NULL DEFAULT 'basic',
    max_rpm INTEGER NOT NULL DEFAULT 120,
    active INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS api_refresh_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    client_id INTEGER NOT NULL,
    device_id TEXT NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    last_used_at TEXT,
    revoked_at TEXT,
    last_ip TEXT
);

CREATE TABLE IF NOT EXISTS api_rate_limits (
    bucket_key TEXT PRIMARY KEY,
    window_start INTEGER NOT NULL,
    request_count INTEGER NOT NULL
);
SQL;

function clickfix_report_log_dir(): string
{
    $dir = __DIR__ . '/data/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

$logDir = clickfix_report_log_dir();
$logFile = $logDir . '/clickfix-report.log';

function writeDebugLog(string $debugFile, array $entry): void
{
    $entry['logged_at'] = gmdate('c');
    @file_put_contents(
        $debugFile,
        json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function clickfix_decode_data_url_image(string $raw, int $maxBytes): ?array
{
    $value = trim($raw);
    if ($value === '') {
        return null;
    }
    if (!preg_match('#^data:image/(png|jpeg|jpg|webp|gif|bmp|avif);base64,([A-Za-z0-9+/=]+)$#i', $value, $matches)) {
        return null;
    }
    $mime = strtolower((string) $matches[1]);
    $base64 = (string) $matches[2];
    if ($base64 === '') {
        return null;
    }
    $decoded = base64_decode($base64, true);
    if (!is_string($decoded) || $decoded === '') {
        return null;
    }
    if (strlen($decoded) > $maxBytes) {
        return null;
    }
    $extension = $mime === 'jpeg' || $mime === 'jpg'
        ? 'jpg'
        : ($mime === 'webp'
            ? 'webp'
            : ($mime === 'gif'
                ? 'gif'
                : ($mime === 'bmp' ? 'bmp' : ($mime === 'avif' ? 'avif' : 'png'))));
    return [
        'mime' => $mime,
        'extension' => $extension,
        'binary' => $decoded,
    ];
}

function clickfix_store_scan_image(int $reportId, string $kind, array $decodedImage): ?string
{
    if ($reportId <= 0) {
        return null;
    }
    if (!in_array($kind, ['before', 'after'], true)) {
        return null;
    }
    $extension = (string) ($decodedImage['extension'] ?? '');
    $binary = $decodedImage['binary'] ?? null;
    if (!in_array($extension, ['png', 'jpg', 'webp', 'gif', 'bmp', 'avif'], true) || !is_string($binary) || $binary === '') {
        return null;
    }
    $detectedInfo = clickfix_scan_detect_image_info($binary);
    if ($detectedInfo === null) {
        return null;
    }
    $extension = (string) ($detectedInfo['ext'] ?? $extension);

    $scanDir = __DIR__ . '/data/scans';
    if (!is_dir($scanDir)) {
        @mkdir($scanDir, 0775, true);
    }
    if (!is_dir($scanDir) || !is_writable($scanDir)) {
        return null;
    }

    foreach (['png', 'jpg', 'webp', 'gif', 'bmp', 'avif'] as $existingExt) {
        $existingPath = $scanDir . '/' . $reportId . '-' . $kind . '.' . $existingExt;
        if (is_file($existingPath)) {
            @unlink($existingPath);
        }
    }

    $targetPath = $scanDir . '/' . $reportId . '-' . $kind . '.' . $extension;
    $written = @file_put_contents($targetPath, $binary, LOCK_EX);
    if ($written === false) {
        return null;
    }
    return $targetPath;
}

function clickfix_siteshot_api_key(): string
{
    $key = trim((string) clickfix_env('CLICKFIX_SITESHOT_API_KEY', ''));
    return substr($key, 0, 200);
}

function clickfix_detect_binary_image_extension(string $binary): string
{
    if ($binary === '') {
        return '';
    }
    if (strncmp($binary, "\x89PNG\x0D\x0A\x1A\x0A", 8) === 0) {
        return 'png';
    }
    if (strncmp($binary, "\xFF\xD8\xFF", 3) === 0) {
        return 'jpg';
    }
    if (strncmp($binary, 'RIFF', 4) === 0 && substr($binary, 8, 4) === 'WEBP') {
        return 'webp';
    }
    if (strncmp($binary, "GIF87a", 6) === 0 || strncmp($binary, "GIF89a", 6) === 0) {
        return 'gif';
    }
    if (strncmp($binary, 'BM', 2) === 0) {
        return 'bmp';
    }
    if (substr($binary, 4, 8) === 'ftypavif' || substr($binary, 4, 8) === 'ftypavis') {
        return 'avif';
    }
    return '';
}

function clickfix_capture_before_image_with_siteshot(string $targetUrl, int $maxBytes, string $debugFile): ?array
{
    $targetUrl = trim($targetUrl);
    if ($targetUrl === '' || filter_var($targetUrl, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    $parts = parse_url($targetUrl);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = (string) ($parts['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return null;
    }
    if (function_exists('clickfix_ml_url_allowed') && !clickfix_ml_url_allowed($targetUrl)) {
        writeDebugLog($debugFile, [
            'status' => 'siteshot_skip_non_public_url',
            'url' => $targetUrl,
        ]);
        return null;
    }

    $apiKey = clickfix_siteshot_api_key();
    if ($apiKey === '') {
        writeDebugLog($debugFile, ['status' => 'siteshot_missing_api_key']);
        return null;
    }

    $query = http_build_query([
        'url' => $targetUrl,
        'userkey' => $apiKey,
        'response_type' => 'json',
        'format' => 'png',
        'full_size' => 1,
        'max_height' => 16000,
        'width' => 1366,
        'height' => 768,
        'delay_time' => 1200,
        'timeout' => 35000,
    ], '', '&', PHP_QUERY_RFC3986);
    $endpoint = 'https://api.site-shot.com/?' . $query;

    $response = clickfix_http_request(
        $endpoint,
        'GET',
        ['Accept' => 'application/json,image/png,image/jpeg,image/webp,*/*;q=0.1'],
        null,
        20
    );
    $status = (int) ($response['status'] ?? 0);
    $body = (string) ($response['body'] ?? '');
    if (empty($response['ok']) || $status < 200 || $status >= 300 || $body === '') {
        writeDebugLog($debugFile, [
            'status' => 'siteshot_request_failed',
            'http_status' => $status,
            'url' => $targetUrl,
        ]);
        return null;
    }

    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        if (!empty($decoded['error'])) {
            writeDebugLog($debugFile, [
                'status' => 'siteshot_api_error',
                'error' => substr((string) $decoded['error'], 0, 300),
                'url' => $targetUrl,
            ]);
            return null;
        }
        $imageRaw = trim((string) ($decoded['image'] ?? ''));
        if ($imageRaw !== '') {
            $commaPos = strpos($imageRaw, ',');
            if ($commaPos !== false && stripos(substr($imageRaw, 0, $commaPos), 'base64') !== false) {
                $imageRaw = substr($imageRaw, $commaPos + 1);
            }
            $binary = base64_decode($imageRaw, true);
            if (is_string($binary) && $binary !== '' && strlen($binary) <= $maxBytes) {
                return [
                    'mime' => 'png',
                    'extension' => 'png',
                    'binary' => $binary,
                ];
            }
        }
    }

    $fallbackExt = clickfix_detect_binary_image_extension($body);
    if ($fallbackExt === '' || strlen($body) > $maxBytes) {
        writeDebugLog($debugFile, [
            'status' => 'siteshot_invalid_image',
            'http_status' => $status,
            'url' => $targetUrl,
        ]);
        return null;
    }

    return [
        'mime' => $fallbackExt === 'jpg' ? 'jpeg' : $fallbackExt,
        'extension' => $fallbackExt,
        'binary' => $body,
    ];
}

function startsWithHashComment(string $line): bool
{
    return substr($line, 0, 1) === '#';
}

function respondWithError(int $statusCode, string $message, string $debugFile, array $context = []): void
{
    http_response_code($statusCode);
    echo json_encode(['status' => 'error', 'message' => $message]);
    writeDebugLog($debugFile, ['status' => 'error', 'code' => $statusCode, 'message' => $message] + $context);
    exit;
}

function ensureReportsSchema(PDO $pdo, string $debugFile): void
{
    try {
        $columns = $pdo->query('PRAGMA table_info(reports)')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage()]);
        return;
    }

    $existing = [];
    foreach ($columns as $column) {
        $existing[(string) ($column['name'] ?? '')] = true;
    }

    $updates = [];
    if (!isset($existing['full_context'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN full_context TEXT';
    }
    if (!isset($existing['blocked'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN blocked INTEGER DEFAULT 0';
    }
    if (!isset($existing['accepted'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN accepted INTEGER DEFAULT 0';
    }
    if (!isset($existing['accepted_by'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN accepted_by INTEGER';
    }
    if (!isset($existing['accepted_at'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN accepted_at TEXT';
    }
    if (!isset($existing['review_status'])) {
        $updates[] = "ALTER TABLE reports ADD COLUMN review_status TEXT DEFAULT 'pending'";
    }
    if (!isset($existing['reviewed_by'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN reviewed_by INTEGER';
    }
    if (!isset($existing['reviewed_at'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN reviewed_at TEXT';
    }
    if (!isset($existing['previous_url'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN previous_url TEXT';
    }
    if (!isset($existing['client_id'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN client_id TEXT';
    }
    if (!isset($existing['score_total'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN score_total INTEGER';
    }
    if (!isset($existing['score_details_json'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN score_details_json TEXT';
    }
    if (!isset($existing['reason_entries_json'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN reason_entries_json TEXT';
    }
    if (!isset($existing['matched_snippets_json'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN matched_snippets_json TEXT';
    }
    if (!isset($existing['duplicate_count'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN duplicate_count INTEGER DEFAULT 1';
    }
    if (!isset($existing['last_seen'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN last_seen TEXT';
    }
    if (!isset($existing['event_type'])) {
        $updates[] = "ALTER TABLE reports ADD COLUMN event_type TEXT DEFAULT 'clickfix_alert'";
    }
    if (!isset($existing['runtime_verdict_json'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN runtime_verdict_json TEXT';
    }
    if (!isset($existing['server_score_total'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN server_score_total INTEGER';
    }
    if (!isset($existing['server_verdict'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN server_verdict TEXT';
    }
    if (!isset($existing['trusted_signal_source'])) {
        $updates[] = 'ALTER TABLE reports ADD COLUMN trusted_signal_source INTEGER DEFAULT 0';
    }

    foreach ($updates as $statement) {
        try {
            $pdo->exec($statement);
        } catch (Throwable $exception) {
            writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => $statement]);
        }
    }
}

function ensureAdminSchema(PDO $pdo, string $debugFile): void
{
    $statements = [
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS appeals (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            domain TEXT NOT NULL,
            reason TEXT NOT NULL,
            contact TEXT,
            status TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS list_actions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            user_id INTEGER,
            action TEXT NOT NULL,
            list_type TEXT NOT NULL,
            domain TEXT NOT NULL,
            reason TEXT NOT NULL
        )',
        'CREATE TABLE IF NOT EXISTS list_suggestions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            user_id INTEGER,
            list_type TEXT NOT NULL,
            domain TEXT NOT NULL,
            reason TEXT NOT NULL,
            status TEXT NOT NULL
        )'
    ];
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
        } catch (Throwable $exception) {
            writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => $statement]);
        }
    }

    try {
        $columns = $pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
        $existing = [];
        foreach ($columns as $column) {
            $existing[(string) ($column['name'] ?? '')] = true;
        }
        if (!isset($existing['created_at'])) {
            $pdo->exec('ALTER TABLE users ADD COLUMN created_at TEXT');
        }
        if (!isset($existing['role'])) {
            $pdo->exec('ALTER TABLE users ADD COLUMN role TEXT');
        }
    } catch (Throwable $exception) {
        writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => 'ALTER users']);
    }

    try {
        $columns = $pdo->query('PRAGMA table_info(appeals)')->fetchAll(PDO::FETCH_ASSOC);
        $existing = [];
        foreach ($columns as $column) {
            $existing[(string) ($column['name'] ?? '')] = true;
        }
        if (!isset($existing['contact'])) {
            $pdo->exec('ALTER TABLE appeals ADD COLUMN contact TEXT');
        }
        if (!isset($existing['status'])) {
            $pdo->exec('ALTER TABLE appeals ADD COLUMN status TEXT');
        }
    } catch (Throwable $exception) {
        writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => 'ALTER appeals']);
    }
}

function ensureStatsSchema(PDO $pdo, string $debugFile): void
{
    try {
        $columns = $pdo->query('PRAGMA table_info(stats)')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $exception) {
        writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => 'PRAGMA stats']);
        return;
    }

    $existing = [];
    foreach ($columns as $column) {
        $existing[(string) ($column['name'] ?? '')] = true;
    }
    if (!isset($existing['user_agent'])) {
        try {
            $pdo->exec('ALTER TABLE stats ADD COLUMN user_agent TEXT');
        } catch (Throwable $exception) {
            writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => 'ALTER stats']);
        }
    }
    if (!isset($existing['install_type'])) {
        try {
            $pdo->exec('ALTER TABLE stats ADD COLUMN install_type TEXT');
        } catch (Throwable $exception) {
            writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => 'ALTER stats']);
        }
    }
    if (!isset($existing['install_source'])) {
        try {
            $pdo->exec('ALTER TABLE stats ADD COLUMN install_source TEXT');
        } catch (Throwable $exception) {
            writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => 'ALTER stats']);
        }
    }
    if (!isset($existing['install_channel'])) {
        try {
            $pdo->exec('ALTER TABLE stats ADD COLUMN install_channel TEXT');
        } catch (Throwable $exception) {
            writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => 'ALTER stats']);
        }
    }
    if (!isset($existing['client_id'])) {
        try {
            $pdo->exec('ALTER TABLE stats ADD COLUMN client_id TEXT');
        } catch (Throwable $exception) {
            writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'statement' => 'ALTER stats']);
        }
    }
}

function computeServerVerdict(array $signals, string $message, array $reasons, array $snippets, string $eventType): array
{
    $score = 0;
    $boolSignals = [
        'commandMatch' => 20,
        'shellHint' => 14,
        'evasionHint' => 5,
        'mismatch' => 8,
        'clipboardWarning' => 4,
        'winRHint' => 8,
        'winXHint' => 6,
        'consoleHint' => 8,
        'pasteSequenceHint' => 8,
        'copyTriggerHint' => 5,
        'fileExplorerHint' => 5,
        'browserErrorHint' => 5,
        'fixActionHint' => 5,
        'captchaHint' => 4
    ];
    $hasExecutionCorroboration = !empty($signals['commandMatch'])
        || !empty($signals['shellHint'])
        || !empty($signals['winRHint'])
        || !empty($signals['winXHint'])
        || !empty($signals['consoleHint'])
        || !empty($signals['pasteSequenceHint'])
        || !empty($signals['fileExplorerHint']);
    foreach ($boolSignals as $key => $points) {
        if (empty($signals[$key])) {
            continue;
        }
        if ($key === 'evasionHint' && !$hasExecutionCorroboration) {
            continue;
        }
        if ($key === 'clipboardWarning' && empty($signals['mismatch']) && !$hasExecutionCorroboration) {
            continue;
        }
        $score += $points;
    }

    $messageLower = strtolower($message);
    if (preg_match('/powershell|cmd\s+\/c|bash\s+-c|curl\s+|wget\s+|invoke-webrequest|encodedcommand/', $messageLower)) {
        $score += 10;
    }
    if (preg_match('/captcha|verification|fix|error|console|terminal/', $messageLower)) {
        $score += 4;
    }

    $reasonCount = count($reasons);
    if ($reasonCount >= 6) {
        $score += 6;
    } elseif ($reasonCount >= 3) {
        $score += 3;
    }

    $snippetCount = count($snippets);
    if ($snippetCount >= 4) {
        $score += 4;
    } elseif ($snippetCount >= 2) {
        $score += 2;
    }

    if ($eventType === 'unsafe_download') {
        $score += 18;
    } elseif ($eventType === 'shadow_ai') {
        $score += 6;
    }

    $score = max(0, min(100, $score));
    $verdict = 'low';
    if ($score >= 72) {
        $verdict = 'unsafe';
    } elseif ($score >= 48) {
        $verdict = 'suspicious';
    }

    return ['score' => $score, 'verdict' => $verdict];
}

function openDatabase(string $dbPath, ?string $schemaPath, string $schemaSqlFallback, string $debugFile): ?PDO
{
    $dataDir = dirname($dbPath);
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0775, true);
    }

    if (!file_exists($dbPath)) {
        try {
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $schemaSql = null;
            if ($schemaPath !== null) {
                $schemaSql = file_get_contents($schemaPath);
            }
            if (!is_string($schemaSql) || $schemaSql === '') {
                $schemaSql = $schemaSqlFallback;
            }
            $pdo->exec($schemaSql);
        } catch (Throwable $exception) {
            writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'db_path' => $dbPath]);
            return null;
        }
    }

    if (!is_readable($dbPath)) {
        return null;
    }

    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        clickfix_run_migrations($pdo);
    } catch (Throwable $exception) {
        writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'db_path' => $dbPath]);
        return null;
    }

    ensureReportsSchema($pdo, $debugFile);
    ensureAdminSchema($pdo, $debugFile);
    ensureStatsSchema($pdo, $debugFile);
    return $pdo;
}

$debugFile = $logDir . '/clickfix-debug.log';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondWithError(405, 'Method not allowed', $debugFile, ['method' => $_SERVER['REQUEST_METHOD'] ?? '']);
}
if (!clickfix_is_request_origin_allowed(true)) {
    respondWithError(403, 'Origin not allowed', $debugFile, ['origin' => clickfix_request_origin()]);
}

$maxBytes = (int) clickfix_env('CLICKFIX_REPORT_MAX_BYTES', '786432');
$maxBytes = max(32768, min(2097152, $maxBytes));
$rawBody = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
if ($rawBody === false || strlen($rawBody) > $maxBytes) {
    respondWithError(413, 'Payload too large', $debugFile, ['bytes' => $rawBody === false ? null : strlen($rawBody)]);
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') === false) {
    respondWithError(415, 'Unsupported content type', $debugFile, ['content_type' => $contentType]);
}

try {
    $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    respondWithError(400, 'Invalid JSON', $debugFile, ['error' => $exception->getMessage()]);
}

if (!is_array($payload)) {
    respondWithError(400, 'Invalid payload', $debugFile);
}

$type = isset($payload['type']) ? strtolower(trim((string) $payload['type'])) : 'alert';
if (!in_array($type, ['alert', 'stats'], true)) {
    respondWithError(400, 'Invalid type', $debugFile, ['type' => $type]);
}

$url = isset($payload['url']) ? trim((string) $payload['url']) : '';
$previousUrl = isset($payload['previous_url']) ? trim((string) $payload['previous_url']) : '';
$hostname = isset($payload['hostname']) ? trim((string) $payload['hostname']) : '';
$message = isset($payload['message']) ? trim((string) $payload['message']) : '';
$reason = isset($payload['reason']) ? trim((string) $payload['reason']) : '';
$timestamp = isset($payload['timestamp']) ? (string) $payload['timestamp'] : '';
$signals = isset($payload['signals']) && is_array($payload['signals']) ? $payload['signals'] : [];
$reasonEntriesRaw = isset($payload['reason_entries']) && is_array($payload['reason_entries'])
    ? $payload['reason_entries']
    : (isset($payload['reasonEntries']) && is_array($payload['reasonEntries']) ? $payload['reasonEntries'] : []);
$matchedSnippetsRaw = isset($payload['snippets']) && is_array($payload['snippets']) ? $payload['snippets'] : [];
$detectedContent = isset($payload['detectedContent'])
    ? trim((string) $payload['detectedContent'])
    : '';
$fullContext = isset($payload['full_context'])
    ? trim((string) $payload['full_context'])
    : '';
$statsData = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
$clientIdRaw = isset($payload['client_id']) ? (string) $payload['client_id'] : (string) ($statsData['clientId'] ?? '');
$clientId = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', trim($clientIdRaw)), 0, 64);
$eventType = strtolower(trim((string) ($payload['event_type'] ?? 'clickfix_alert')));
$runtimeVerdictRaw = $payload['runtime_verdict'] ?? null;
$runtimeVerdictJson = null;
if (is_array($runtimeVerdictRaw)) {
    $runtimeVerdictJson = json_encode($runtimeVerdictRaw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif (is_string($runtimeVerdictRaw) && $runtimeVerdictRaw !== '') {
    $runtimeVerdictJson = $runtimeVerdictRaw;
}
if (is_string($runtimeVerdictJson)) {
    $runtimeVerdictJson = substr($runtimeVerdictJson, 0, 4000);
}
$manualReport = filter_var($payload['manualReport'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
$blocked = filter_var($payload['blocked'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
$scoreTotalRaw = $payload['score_total'] ?? $payload['scoreTotal'] ?? ($signals['confidenceScore'] ?? null);
$scoreTotal = null;
if (is_numeric($scoreTotalRaw)) {
    $scoreTotal = (int) $scoreTotalRaw;
    $scoreTotal = max(0, min(100, $scoreTotal));
}
$scoreDetailsRaw = $payload['score_details'] ?? $payload['scoreDetails'] ?? null;
$scoreDetailsJson = null;
if (is_array($scoreDetailsRaw)) {
    $scoreDetailsJson = json_encode($scoreDetailsRaw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} elseif (is_string($scoreDetailsRaw) && $scoreDetailsRaw !== '') {
    $scoreDetailsJson = $scoreDetailsRaw;
}
if (is_string($scoreDetailsJson)) {
    $scoreDetailsJson = substr($scoreDetailsJson, 0, 20000);
}
$scanAfterImageRaw = isset($payload['scan_after_image']) ? (string) $payload['scan_after_image'] : '';
$scanMaxImageBytes = 260 * 1024;
$scanServerMaxImageBytes = 1200 * 1024;
$decodedAfterImage = clickfix_decode_data_url_image($scanAfterImageRaw, $scanMaxImageBytes);

$url = substr($url, 0, 2048);
$previousUrl = substr($previousUrl, 0, 2048);
$hostname = substr($hostname, 0, 255);
$message = substr($message !== '' ? $message : $reason, 0, 2000);
$timestamp = substr($timestamp, 0, 100);
$detectedMax = (int) clickfix_env('CLICKFIX_REPORT_DETECTED_MAX', '4000');
$detectedMax = max(1000, min(60000, $detectedMax));
$fullContextMax = (int) clickfix_env('CLICKFIX_REPORT_FULL_CONTEXT_MAX', '50000');
$fullContextMax = max(5000, min(250000, $fullContextMax));
$detectedContent = substr($detectedContent, 0, $detectedMax);
$fullContext = substr($fullContext, 0, $fullContextMax);
$eventType = substr($eventType, 0, 60);

$message = preg_replace('/[\x00-\x1F\x7F]/', ' ', (string) $message);
$message = trim(strip_tags((string) $message));
$message = substr($message, 0, 2000);
$reason = preg_replace('/[\x00-\x1F\x7F]/', ' ', (string) $reason);
$reason = trim(strip_tags((string) $reason));
$reason = substr($reason, 0, 500);

if ($url !== '' && preg_match('/\s/', $url)) {
    respondWithError(400, 'Invalid url', $debugFile, ['url' => $url]);
}

if ($previousUrl !== '' && preg_match('/\s/', $previousUrl)) {
    $previousUrl = '';
}

if ($url !== '') {
    $parsedUrl = parse_url($url);
    $scheme = strtolower((string) ($parsedUrl['scheme'] ?? ''));
    $host = (string) ($parsedUrl['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        respondWithError(400, 'Invalid url', $debugFile, ['url' => $url]);
    }
    if ($hostname === '') {
        $hostname = $host;
    }
}

if ($previousUrl !== '') {
    $parsedUrl = parse_url($previousUrl);
    $scheme = strtolower((string) ($parsedUrl['scheme'] ?? ''));
    $host = (string) ($parsedUrl['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        $previousUrl = '';
    }
}

if ($url !== '' && preg_match('/\s/', $url)) {
    respondWithError(400, 'Invalid url', $debugFile, ['url' => $url]);
}

if ($url !== '') {
    $parsedUrl = parse_url($url);
    $scheme = strtolower((string) ($parsedUrl['scheme'] ?? ''));
    $host = (string) ($parsedUrl['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        respondWithError(400, 'Invalid url', $debugFile, ['url' => $url]);
    }
    if ($hostname === '') {
        $hostname = $host;
    }
}

if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
    respondWithError(400, 'Invalid url', $debugFile, ['url' => $url]);
}

if ($previousUrl !== '' && filter_var($previousUrl, FILTER_VALIDATE_URL) === false) {
    $previousUrl = '';
}

if ($hostname !== '' && !preg_match('/^[a-z0-9.-]+$/i', $hostname)) {
    respondWithError(400, 'Invalid hostname', $debugFile, ['hostname' => $hostname]);
}
if ($eventType === '' || !preg_match('/^[a-z0-9_-]+$/', $eventType)) {
    $eventType = 'clickfix_alert';
}
if ($type === 'alert' && $manualReport) {
    $eventType = 'manual_report';
}

if ($type === 'alert' && $url === '' && $hostname === '' && $message === '') {
    respondWithError(400, 'Missing required fields', $debugFile);
}

$normalizedStats = [];
if ($type === 'stats') {
$normalizedStats = [
    'enabled' => filter_var($statsData['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
    'alert_count' => (int) ($statsData['alertCount'] ?? 0),
    'block_count' => (int) ($statsData['blockCount'] ?? 0),
    'manual_sites' => [],
    'alert_sites' => [],
    'baseline_hosts' => [],
    'country' => '',
    'install_type' => '',
    'install_source' => '',
    'install_channel' => '',
    'client_id' => $clientId
];
    $manualSites = $statsData['manualSites'] ?? [];
    if (is_array($manualSites)) {
        foreach (array_slice($manualSites, 0, 200) as $site) {
            $site = substr(trim((string) $site), 0, 255);
            if ($site !== '' && preg_match('/^[a-z0-9.-]+$/i', $site)) {
                $normalizedStats['manual_sites'][] = $site;
            }
        }
    }
    $alertSites = $statsData['alertSites'] ?? [];
    if (is_array($alertSites)) {
        foreach (array_slice($alertSites, 0, 400) as $site) {
            $site = substr(trim((string) $site), 0, 255);
            if ($site !== '' && preg_match('/^[a-z0-9.-]+$/i', $site)) {
                $normalizedStats['alert_sites'][] = $site;
            }
        }
    }
    $baselineHosts = $statsData['baselineHosts'] ?? [];
    if (is_array($baselineHosts)) {
        foreach (array_slice($baselineHosts, 0, 60) as $hostRow) {
            if (!is_array($hostRow)) {
                continue;
            }
            $host = clickfix_normalize_domain((string) ($hostRow['hostname'] ?? ''));
            if ($host === '') {
                continue;
            }
            $lastSeenDay = substr(trim((string) ($hostRow['lastSeenDay'] ?? '')), 0, 10);
            if ($lastSeenDay !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lastSeenDay)) {
                $lastSeenDay = '';
            }
            $normalizedStats['baseline_hosts'][] = [
                'hostname' => $host,
                'visits_count' => max(0, min(255, (int) ($hostRow['visitsCount'] ?? 0))),
                'days_seen' => max(0, min(255, (int) ($hostRow['daysSeen'] ?? 0))),
                'alert_count' => max(0, min(255, (int) ($hostRow['alertCount'] ?? 0))),
                'blocked_count' => max(0, min(255, (int) ($hostRow['blockedCount'] ?? 0))),
                'trust_score' => max(0, min(100, (int) ($hostRow['trustScore'] ?? 0))),
                'local_allowlisted' => filter_var($hostRow['localAllowlisted'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                'last_seen_day' => $lastSeenDay,
            ];
        }
    }
    $countryInput = strtoupper(substr(trim((string) ($statsData['country'] ?? '')), 0, 2));
    if ($countryInput !== '' && preg_match('/^[A-Z]{2}$/', $countryInput)) {
        $normalizedStats['country'] = $countryInput;
    }

    $installType = strtolower(trim((string) ($statsData['installType'] ?? '')));
    $installSource = strtolower(trim((string) ($statsData['installSource'] ?? '')));
    $installChannel = strtolower(trim((string) ($statsData['installChannel'] ?? '')));
    if ($installType !== '' && preg_match('/^[a-z0-9._-]+$/', $installType)) {
        $normalizedStats['install_type'] = substr($installType, 0, 40);
    }
    if ($installSource !== '' && preg_match('/^[a-z0-9._-]+$/', $installSource)) {
        $normalizedStats['install_source'] = substr($installSource, 0, 40);
    }
    if ($installChannel !== '' && preg_match('/^[a-z0-9._-]+$/', $installChannel)) {
        $normalizedStats['install_channel'] = substr($installChannel, 0, 40);
    }
}

$normalizedSignals = [];
foreach ($signals as $key => $value) {
    if (!is_string($key)) {
        continue;
    }
    $normalizedSignals[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
}

$normalizedReasonEntries = [];
foreach (array_slice($reasonEntriesRaw, 0, 60) as $entry) {
    if (!is_array($entry)) {
        continue;
    }
    $key = substr(trim((string) ($entry['key'] ?? '')), 0, 80);
    if ($key === '' || !preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
        continue;
    }
    $value = $entry['value'] ?? null;
    if ($value === null || $value === '') {
        $normalizedReasonEntries[] = ['key' => $key];
        continue;
    }
    $normalizedReasonEntries[] = [
        'key' => $key,
        'value' => substr(trim((string) $value), 0, 260),
    ];
}

$snippetLimit = (int) clickfix_env('CLICKFIX_REPORT_MAX_SNIPPETS', '80');
$snippetLimit = max(5, min(200, $snippetLimit));
$snippetMaxLen = (int) clickfix_env('CLICKFIX_REPORT_SNIPPET_MAX_CHARS', '4000');
$snippetMaxLen = max(120, min(20000, $snippetMaxLen));
$normalizedSnippets = [];
foreach (array_slice($matchedSnippetsRaw, 0, $snippetLimit) as $snippet) {
    $snippet = substr(trim((string) $snippet), 0, $snippetMaxLen);
    if ($snippet !== '') {
        $normalizedSnippets[] = $snippet;
    }
}

$country = $_SERVER['HTTP_CF_IPCOUNTRY']
    ?? $_SERVER['HTTP_X_COUNTRY']
    ?? $_SERVER['HTTP_GEOIP_COUNTRY_CODE']
    ?? '';
$country = substr(preg_replace('/[^A-Z]/', '', (string) $country), 0, 2);
$ipAddress = clickfix_client_ip();

$pdo = openDatabase($dbPath, $schemaPath, $defaultSchemaSql, $debugFile);
if (!($pdo instanceof PDO)) {
    respondWithError(500, 'Database unavailable', $debugFile, ['db_path' => $dbPath]);
}

if (!clickfix_api_rate_limit($pdo, 'report:ip:' . $ipAddress, 240, 60)) {
    respondWithError(429, 'Rate limited', $debugFile, ['ip' => $ipAddress]);
}

$requireAuth = in_array(
    strtolower(trim((string) clickfix_env('CLICKFIX_REPORT_REQUIRE_AUTH', '1'))),
    ['1', 'true', 'yes', 'on'],
    true
);
$apiClaims = clickfix_authenticate_api_request($pdo, ['report:write']);
$trustedSignalSource = is_array($apiClaims) ? 1 : 0;
if ($requireAuth && !is_array($apiClaims)) {
    respondWithError(401, 'Unauthorized', $debugFile, ['ip' => $ipAddress]);
}
if (is_array($apiClaims)) {
    $tokenClient = (string) ($apiClaims['sub'] ?? '');
    $tokenRpm = (int) ($apiClaims['rpm'] ?? 120);
    if (!clickfix_api_rate_limit($pdo, 'report:token:' . $tokenClient, max(30, min(2000, $tokenRpm)), 60)) {
        respondWithError(429, 'Rate limited', $debugFile, ['token_client' => $tokenClient]);
    }
}

$serverVerdict = computeServerVerdict($normalizedSignals, $message, $normalizedReasonEntries, $normalizedSnippets, $eventType);

$entry = [
    'type' => $type,
    'received_at' => gmdate('c'),
    'url' => $url,
    'previous_url' => $previousUrl,
    'hostname' => $hostname,
    'message' => $message,
    'timestamp' => $timestamp,
    'signals' => $normalizedSignals,
    'reason_entries' => $normalizedReasonEntries,
    'matched_snippets' => $normalizedSnippets,
    'detected_content' => $detectedContent,
    'full_context' => $fullContext,
    'blocked' => $blocked ? 1 : 0,
    'stats' => $normalizedStats,
    'client_id' => $clientId,
    'score_total' => $scoreTotal,
    'score_details' => $scoreDetailsJson,
    'event_type' => $eventType,
    'runtime_verdict_json' => $runtimeVerdictJson,
    'server_score_total' => (int) ($serverVerdict['score'] ?? 0),
    'server_verdict' => (string) ($serverVerdict['verdict'] ?? 'low'),
    'trusted_signal_source' => $trustedSignalSource,
    'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
    'ip' => $ipAddress,
    'country' => $type === 'stats' && $normalizedStats['country'] !== '' ? $normalizedStats['country'] : $country
];
$logLine = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

$inserted = false;
$skipLogWrite = false;
$targetReportId = 0;
if ($pdo instanceof PDO) {
    try {
        if ($type === 'stats') {
            $statement = $pdo->prepare(
                'INSERT INTO stats (received_at, enabled, alert_count, block_count, manual_sites_json, user_agent, client_id, install_type, install_source, install_channel, country)
                 VALUES (:received_at, :enabled, :alert_count, :block_count, :manual_sites_json, :user_agent, :client_id, :install_type, :install_source, :install_channel, :country)'
            );
            $statement->execute([
                ':received_at' => $entry['received_at'],
                ':enabled' => $normalizedStats['enabled'] ? 1 : 0,
                ':alert_count' => $normalizedStats['alert_count'],
                ':block_count' => $normalizedStats['block_count'],
                ':manual_sites_json' => json_encode($normalizedStats['manual_sites'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                ':user_agent' => $entry['user_agent'],
                ':client_id' => $clientId,
                ':install_type' => $normalizedStats['install_type'],
                ':install_source' => $normalizedStats['install_source'],
                ':install_channel' => $normalizedStats['install_channel'],
                ':country' => $entry['country']
            ]);
            clickfix_baseline_merge_host_summaries($pdo, $clientId, $normalizedStats['baseline_hosts'], $entry['received_at']);
        } else {
            $dedupeRow = null;
            if ($clientId !== '' || $entry['ip'] !== '') {
                $dedupeStatement = $pdo->prepare(
                    'SELECT id, duplicate_count
                     FROM reports
                     WHERE client_id = :client_id
                       AND hostname = :hostname
                       AND url = :url
                       AND message = :message
                       AND detected_content = :detected
                       AND blocked = :blocked
                       AND ip = :ip
                     ORDER BY received_at DESC
                     LIMIT 1'
                );
                $dedupeStatement->execute([
                    ':client_id' => $clientId,
                    ':hostname' => $entry['hostname'],
                    ':url' => $entry['url'],
                    ':message' => $entry['message'],
                    ':detected' => $entry['detected_content'],
                    ':blocked' => $entry['blocked'],
                    ':ip' => $entry['ip']
                ]);
                $dedupeRow = $dedupeStatement->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            if ($dedupeRow) {
                $newCount = ((int) ($dedupeRow['duplicate_count'] ?? 1)) + 1;
                $updateStatement = $pdo->prepare(
                    'UPDATE reports
                     SET duplicate_count = :count,
                          last_seen = :last_seen,
                          user_agent = :user_agent,
                          country = :country,
                          event_type = :event_type,
                          runtime_verdict_json = COALESCE(:runtime_verdict_json, runtime_verdict_json),
                          score_total = COALESCE(:score_total, score_total),
                          server_score_total = COALESCE(:server_score_total, server_score_total),
                          server_verdict = COALESCE(:server_verdict, server_verdict),
                          trusted_signal_source = CASE WHEN :trusted_signal_source = 1 THEN 1 ELSE trusted_signal_source END,
                          score_details_json = COALESCE(:score_details, score_details_json),
                          reason_entries_json = COALESCE(:reason_entries, reason_entries_json),
                          matched_snippets_json = COALESCE(:matched_snippets, matched_snippets_json)
                     WHERE id = :id'
                );
                $updateStatement->execute([
                    ':count' => $newCount,
                    ':last_seen' => $entry['received_at'],
                    ':user_agent' => $entry['user_agent'],
                    ':country' => $entry['country'],
                    ':event_type' => $entry['event_type'],
                    ':runtime_verdict_json' => $entry['runtime_verdict_json'],
                    ':score_total' => $entry['score_total'],
                    ':server_score_total' => $entry['server_score_total'],
                    ':server_verdict' => $entry['server_verdict'],
                    ':trusted_signal_source' => $entry['trusted_signal_source'],
                    ':score_details' => $entry['score_details'],
                    ':reason_entries' => json_encode($entry['reason_entries'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ':matched_snippets' => json_encode($entry['matched_snippets'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ':id' => (int) ($dedupeRow['id'] ?? 0)
                ]);
                clickfix_baseline_record_alert($pdo, $clientId, $entry['hostname'], !empty($entry['blocked']), $entry['received_at'], (string) $entry['server_verdict']);
                $targetReportId = (int) ($dedupeRow['id'] ?? 0);
                $inserted = true;
                $skipLogWrite = true;
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO reports (received_at, url, previous_url, hostname, message, detected_content, full_context, signals_json, blocked, client_id, score_total, score_details_json, reason_entries_json, matched_snippets_json, duplicate_count, last_seen, user_agent, ip, country, event_type, runtime_verdict_json, server_score_total, server_verdict, trusted_signal_source)
                     VALUES (:received_at, :url, :previous_url, :hostname, :message, :detected_content, :full_context, :signals_json, :blocked, :client_id, :score_total, :score_details, :reason_entries, :matched_snippets, :duplicate_count, :last_seen, :user_agent, :ip, :country, :event_type, :runtime_verdict_json, :server_score_total, :server_verdict, :trusted_signal_source)'
                );
                $statement->execute([
                    ':received_at' => $entry['received_at'],
                    ':url' => $entry['url'],
                    ':previous_url' => $entry['previous_url'],
                    ':hostname' => $entry['hostname'],
                    ':message' => $entry['message'],
                    ':detected_content' => $entry['detected_content'],
                    ':full_context' => $entry['full_context'],
                    ':signals_json' => json_encode($entry['signals'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ':blocked' => $entry['blocked'],
                    ':client_id' => $clientId,
                    ':score_total' => $entry['score_total'],
                    ':score_details' => $entry['score_details'],
                    ':reason_entries' => json_encode($entry['reason_entries'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ':matched_snippets' => json_encode($entry['matched_snippets'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    ':duplicate_count' => 1,
                    ':last_seen' => $entry['received_at'],
                    ':user_agent' => $entry['user_agent'],
                    ':ip' => $entry['ip'],
                    ':country' => $entry['country'],
                    ':event_type' => $entry['event_type'],
                    ':runtime_verdict_json' => $entry['runtime_verdict_json'],
                    ':server_score_total' => $entry['server_score_total'],
                    ':server_verdict' => $entry['server_verdict'],
                    ':trusted_signal_source' => $entry['trusted_signal_source']
                ]);
                clickfix_baseline_record_alert($pdo, $clientId, $entry['hostname'], !empty($entry['blocked']), $entry['received_at'], (string) $entry['server_verdict']);
                $targetReportId = (int) $pdo->lastInsertId();
            }
        }
        $inserted = true;
    } catch (Throwable $exception) {
        writeDebugLog($debugFile, ['status' => 'db_error', 'error' => $exception->getMessage(), 'db_path' => $dbPath]);
    }
}

if (!$skipLogWrite && file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX) === false) {
    respondWithError(500, 'Failed to write report', $debugFile, ['log_file' => $logFile]);
}

if ($type === 'alert' && $targetReportId > 0) {
    $afterStored = false;
    if (is_array($decodedAfterImage)) {
        $storedAfter = clickfix_store_scan_image($targetReportId, 'after', $decodedAfterImage);
        if ($storedAfter === null) {
            writeDebugLog($debugFile, [
                'status' => 'scan_store_warning',
                'report_id' => $targetReportId,
                'kind' => 'after'
            ]);
        } elseif (!clickfix_scan_image_mark_pending($pdo, $targetReportId, 'after')) {
            writeDebugLog($debugFile, [
                'status' => 'scan_review_queue_warning',
                'report_id' => $targetReportId,
                'kind' => 'after'
            ]);
            $afterStored = true;
        } else {
            $afterStored = true;
        }
    }
    if ($afterStored && $entry['url'] !== '') {
        $serverBefore = clickfix_capture_before_image_with_siteshot($entry['url'], $scanServerMaxImageBytes, $debugFile);
        if (is_array($serverBefore)) {
            $storedBefore = clickfix_store_scan_image($targetReportId, 'before', $serverBefore);
            if ($storedBefore === null) {
                writeDebugLog($debugFile, [
                    'status' => 'scan_store_warning',
                    'report_id' => $targetReportId,
                    'kind' => 'before'
                ]);
            } elseif (!clickfix_scan_image_mark_pending($pdo, $targetReportId, 'before')) {
                writeDebugLog($debugFile, [
                    'status' => 'scan_review_queue_warning',
                    'report_id' => $targetReportId,
                    'kind' => 'before'
                ]);
            }
        } else {
            writeDebugLog($debugFile, [
                'status' => 'siteshot_before_not_available',
                'report_id' => $targetReportId,
                'url' => $entry['url'],
            ]);
        }
    }
}

function extractMsiexecDownloadUrl(string $text): string
{
    $text = clickfix_pipeline_refang_text($text);
    if ($text === '') {
        return '';
    }
    if (preg_match('/\\bmsiexec(?:\\.exe)?\\b[^\\n]*?\\/(?:i|I)\\s+([\'"]?)(https?:\\/\\/[^\\s\'"]+)\\1/i', $text, $match)) {
        return (string) ($match[2] ?? '');
    }
    return '';
}

function pickAutoPipelineUserId(PDO $pdo): int
{
    $envId = (int) clickfix_env('CLICKFIX_AUTO_PIPELINE_USER_ID', '0');
    if ($envId > 0) {
        return $envId;
    }
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    $adminId = (int) ($stmt ? $stmt->fetchColumn() : 0);
    if ($adminId > 0) {
        return $adminId;
    }
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'analyst_sr' ORDER BY id ASC LIMIT 1");
    return (int) ($stmt ? $stmt->fetchColumn() : 0);
}

if ($targetReportId > 0) {
    $autoEnabled = (string) clickfix_env('CLICKFIX_AUTO_MSIEXEC_PIPELINE', '1') === '1';
    if ($autoEnabled && clickfix_has_table($pdo, 'investigation_graphs')) {
        $rootTextParts = [];
        foreach (['url', 'message', 'detected_content', 'full_context'] as $field) {
            $value = trim((string) ($entry[$field] ?? ''));
            if ($value !== '') {
                $rootTextParts[] = strtoupper($field) . ': ' . $value;
            }
        }
        $rootText = implode(PHP_EOL . PHP_EOL, $rootTextParts);
        $msiUrl = extractMsiexecDownloadUrl($rootText);
        if ($msiUrl !== '') {
            $autoUserId = pickAutoPipelineUserId($pdo);
            if ($autoUserId > 0) {
                $existingGraphId = 0;
                if (clickfix_has_column($pdo, 'investigation_graphs', 'source_report_id')) {
                    $stmt = $pdo->prepare('SELECT id FROM investigation_graphs WHERE source_report_id = :rid AND deleted = 0 ORDER BY id DESC LIMIT 1');
                    $stmt->execute([':rid' => $targetReportId]);
                    $existingGraphId = (int) ($stmt->fetchColumn() ?: 0);
                }
                $graphId = $existingGraphId;
                if ($graphId <= 0) {
                    $domainForInvestigation = clickfix_normalize_domain((string) ($entry['hostname'] ?? ''));
                    if ($domainForInvestigation === '') {
                        $domainForInvestigation = clickfix_normalize_domain($msiUrl);
                    }
                    $summaryLines = [
                        'Auto pipeline: msiexec /i detectado.',
                        'URL payload: ' . $msiUrl,
                    ];
                    if (!empty($entry['message'])) {
                        $summaryLines[] = 'Mensaje: ' . substr((string) $entry['message'], 0, 500);
                    }
                    $summary = implode(PHP_EOL, $summaryLines);
                    $graphNodes = [
                        [
                            'id' => 'n_alert_' . $targetReportId,
                            'label' => 'Alerta #' . $targetReportId,
                            'color' => '#e66a6a',
                            'x' => 200,
                            'y' => 140,
                            'tags' => ['alert', 'clickfix', 'auto'],
                            'notes' => $summary,
                        ],
                        [
                            'id' => 'n_payload_' . preg_replace('/[^a-z0-9]/', '_', strtolower($msiUrl)),
                            'label' => $msiUrl,
                            'color' => '#a78bfa',
                            'x' => 520,
                            'y' => 170,
                            'tags' => ['payload', 'url'],
                            'notes' => 'Descarga msiexec /i',
                        ],
                    ];
                    $graphEdges = [
                        [
                            'id' => 'e_alert_payload_' . $targetReportId,
                            'from' => 'n_alert_' . $targetReportId,
                            'to' => 'n_payload_' . preg_replace('/[^a-z0-9]/', '_', strtolower($msiUrl)),
                            'label' => 'descarga',
                            'color' => '#a78bfa',
                        ],
                    ];
                    $graphId = (int) (clickfix_investigation_save(
                        $pdo,
                        $autoUserId,
                        null,
                        'Investigacion msiexec #' . $targetReportId . ' - ' . ($domainForInvestigation !== '' ? $domainForInvestigation : 'payload'),
                        $domainForInvestigation,
                        'investigating',
                        $summary,
                        'auto, msiexec, payload',
                        ['nodes' => $graphNodes, 'edges' => $graphEdges],
                        true,
                        $targetReportId
                    ) ?? 0);
                }
                if ($graphId > 0) {
                    clickfix_investigation_enqueue_alert_correlation($pdo, $graphId, $targetReportId, $autoUserId, 4);
                }
            }
        }
    }
}

if ($manualReport && $hostname !== '' && $trustedSignalSource === 1) {
    $listFile = __DIR__ . '/clickfixlist';
    $existing = [];
    if (is_readable($listFile)) {
        $lines = file($listFile, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || startsWithHashComment($line)) {
                continue;
            }
            $existing[strtolower($line)] = true;
        }
    }
    $normalized = strtolower($hostname);
    if (!isset($existing[$normalized])) {
        if (!is_writable($listFile) && !is_writable(__DIR__)) {
            respondWithError(500, 'Blocklist is not writable', $debugFile, ['blocklist' => $listFile]);
        }
        $lineToAdd = $hostname . PHP_EOL;
        if (file_put_contents($listFile, $lineToAdd, FILE_APPEND | LOCK_EX) === false) {
            respondWithError(500, 'Failed to update blocklist', $debugFile, ['blocklist' => $listFile]);
        }
    }
}

if ($type === 'stats' && !empty($normalizedStats['alert_sites']) && $trustedSignalSource === 1) {
    $listFile = __DIR__ . '/alertsites';
    $existing = [];
    if (is_readable($listFile)) {
        $lines = file($listFile, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || startsWithHashComment($line)) {
                continue;
            }
            $existing[strtolower($line)] = true;
        }
    }
    foreach ($normalizedStats['alert_sites'] as $site) {
        $normalized = strtolower($site);
        if (isset($existing[$normalized])) {
            continue;
        }
        if (!is_writable($listFile) && !is_writable(__DIR__)) {
            respondWithError(500, 'Alertsites list is not writable', $debugFile, ['alertsites' => $listFile]);
        }
        $lineToAdd = $site . PHP_EOL;
        if (file_put_contents($listFile, $lineToAdd, FILE_APPEND | LOCK_EX) === false) {
            respondWithError(500, 'Failed to update alertsites list', $debugFile, ['alertsites' => $listFile]);
        }
        $existing[$normalized] = true;
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);
writeDebugLog($debugFile, [
    'status' => 'ok',
    'type' => $type,
    'url' => $url,
    'hostname' => $hostname,
    'manualReport' => $manualReport
]);
