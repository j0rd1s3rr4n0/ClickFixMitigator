<?php
declare(strict_types=1);


const CLICKFIX_DB_ENV = 'CLICKFIX_DB_PATH';
const CLICKFIX_SESSION_DIR = __DIR__ . '/../data/sessions';

function clickfix_external_tracking_disabled(): bool
{
    return clickfix_env_truthy('CLICKFIX_DISABLE_EXTERNAL_TRACKING', true);
}

function clickfix_str_starts_with(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }
    return substr($haystack, 0, strlen($needle)) === $needle;
}

function clickfix_cache_namespace(): string
{
    static $namespace = null;
    if (is_string($namespace) && $namespace !== '') {
        return $namespace;
    }
    $base = 'clickfix-default';
    try {
        $base = clickfix_resolve_db_path();
    } catch (Throwable $exception) {
        $base = 'clickfix-default';
    }
    $namespace = 'clickfix:' . substr(sha1($base), 0, 16);
    return $namespace;
}

function clickfix_cache_key(string $name, array $parts = []): string
{
    $suffix = $parts ? ':' . sha1(json_encode($parts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '') : '';
    return clickfix_cache_namespace() . ':' . $name . $suffix;
}

function &clickfix_cache_runtime_store(): array
{
    static $store = [];
    return $store;
}

function clickfix_cache_get(string $key)
{
    $store = &clickfix_cache_runtime_store();
    $now = time();
    if (isset($store[$key])) {
        $entry = $store[$key];
        if (($entry['exp'] ?? 0) >= $now) {
            return $entry['val'] ?? null;
        }
        unset($store[$key]);
    }

    if (!function_exists('apcu_fetch')) {
        return null;
    }
    $success = false;
    $value = apcu_fetch($key, $success);
    if ($success) {
        $store[$key] = ['exp' => $now + 1, 'val' => $value];
    }
    return $success ? $value : null;
}

function clickfix_cache_set(string $key, $value, int $ttlSeconds): void
{
    if ($ttlSeconds <= 0) {
        return;
    }
    $store = &clickfix_cache_runtime_store();
    $store[$key] = ['exp' => time() + $ttlSeconds, 'val' => $value];
    if (function_exists('apcu_store')) {
        @apcu_store($key, $value, $ttlSeconds);
    }
}

function clickfix_bootstrap(): void
{
    if (!headers_sent()) {
        header('CF-RocketLoader: off');
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header("Content-Security-Policy: default-src 'self' https: data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; connect-src 'self' http: https: ws: wss:; img-src 'self' data: blob: http: https:; style-src 'self' 'unsafe-inline' https:; font-src 'self' data: https:; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
        $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $sessionDir = clickfix_pick_session_dir();
        if ($sessionDir !== null) {
            @session_save_path($sessionDir);
        }
        if (!@session_start()) {
            // Keep runtime usable even if session persistence is unavailable.
            if (!isset($_SESSION) || !is_array($_SESSION)) {
                $_SESSION = [];
            }
        }
    }
}

function clickfix_pick_session_dir(): ?string
{
    $tmpBase = rtrim((string) sys_get_temp_dir(), "/\\");
    $tmpDir = $tmpBase !== '' ? $tmpBase . DIRECTORY_SEPARATOR . 'clickfix_sessions' : '';
    $candidates = [CLICKFIX_SESSION_DIR];
    if ($tmpDir !== '') {
        $candidates[] = $tmpDir;
    }

    foreach ($candidates as $candidate) {
        if (!is_dir($candidate)) {
            @mkdir($candidate, 0775, true);
        }
        if (is_dir($candidate) && is_writable($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function clickfix_db_candidates(): array
{
    $configured = trim((string) clickfix_env(CLICKFIX_DB_ENV, ''));
    $candidates = [
        __DIR__ . '/../data/clickfix.sqlite',
        __DIR__ . '/../clickfix.sqlite',
        __DIR__ . '/../Databases/Actual/clickfix.sqlite',
        __DIR__ . '/../Databases/old/clickfix.sqlite',
    ];
    if ($configured !== '') {
        array_unshift($candidates, clickfix_resolve_env_path($configured));
    }

    $normalized = [];
    foreach ($candidates as $candidate) {
        $normalized[] = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
    }
    return array_values(array_unique($normalized));
}

function clickfix_non_empty_file(string $path): bool
{
    return is_file($path) && filesize($path) > 0;
}

function clickfix_can_write_path(string $path): bool
{
    if (is_file($path)) {
        return is_writable($path);
    }
    $parent = dirname($path);
    return is_dir($parent) && is_writable($parent);
}

function clickfix_resolve_db_path(): string
{
    $candidates = clickfix_db_candidates();
    $fallback = $candidates[0];
    $primary = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, __DIR__ . '/../data/clickfix.sqlite');

    if (clickfix_non_empty_file($primary)) {
        return $primary;
    }

    $seedSource = null;
    foreach ($candidates as $candidate) {
        if (clickfix_non_empty_file($candidate)) {
            $seedSource = $candidate;
            break;
        }
        if (is_file($candidate)) {
            $fallback = $candidate;
        }
    }

    if ($seedSource !== null && $seedSource !== $primary && !clickfix_non_empty_file($primary)) {
        $parent = dirname($primary);
        if (!is_dir($parent)) {
            @mkdir($parent, 0775, true);
        }
        if (clickfix_can_write_path($primary)) {
            @copy($seedSource, $primary);
            if (clickfix_non_empty_file($primary)) {
                return $primary;
            }
        }
    }

    foreach ($candidates as $candidate) {
        if (clickfix_non_empty_file($candidate) && clickfix_can_write_path($candidate)) {
            return $candidate;
        }
    }

    if ($seedSource !== null) {
        return $seedSource;
    }

    foreach ($candidates as $candidate) {
        if (clickfix_can_write_path($candidate)) {
            return $candidate;
        }
    }

    return $fallback;
}

function clickfix_open_db(bool $runMigrations = true): PDO
{
    $path = clickfix_resolve_db_path();
    $parent = dirname($path);
    if (!is_dir($parent)) {
        @mkdir($parent, 0775, true);
    }

    try {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->exec('PRAGMA journal_mode = WAL');

        if ($runMigrations) {
            clickfix_run_migrations($pdo);
        }
        return $pdo;
    } catch (Throwable $exception) {
        if (
            $runMigrations &&
            (stripos($exception->getMessage(), 'readonly') !== false || stripos($exception->getMessage(), 'read-only') !== false)
        ) {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA busy_timeout = 5000');
            try {
                $pdo->exec('PRAGMA journal_mode = OFF');
                $pdo->exec('PRAGMA synchronous = OFF');
                $pdo->exec('PRAGMA temp_store = MEMORY');
            } catch (Throwable $pragmaException) {
                // Ignore if the filesystem is read-only.
            }
            $pdo->exec('PRAGMA query_only = ON');
            return $pdo;
        }
        throw $exception;
    }
}

function clickfix_is_readonly_error(Throwable $exception): bool
{
    $message = strtolower($exception->getMessage());
    return str_contains($message, 'readonly') || str_contains($message, 'read-only') || str_contains($message, 'attempt to write a readonly database');
}

function clickfix_has_column(PDO $pdo, string $table, string $column): bool
{
    try {
        $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
        foreach ($rows as $row) {
            if ((string) ($row['name'] ?? '') === $column) {
                return true;
            }
        }
        return false;
    } catch (Throwable $exception) {
        if (clickfix_is_readonly_error($exception)) {
            return false;
        }
        throw $exception;
    }
}

function clickfix_run_migrations(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            migration_id TEXT PRIMARY KEY,
            applied_at TEXT NOT NULL
        )'
    );

    $appliedRows = $pdo->query('SELECT migration_id FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    $applied = [];
    foreach ($appliedRows as $row) {
        $applied[(string) $row] = true;
    }

    $migrations = [
        '20260301_001_tables',
        '20260301_002_reports_columns',
        '20260301_003_stats_columns',
        '20260301_004_users_access_columns',
        '20260301_005_indexes',
        '20260301_006_backfill',
        '20260301_007_reason_columns',
        '20260301_008_investigation_graphs',
        '20260301_009_api_security',
        '20260301_010_ops_suite',
        '20260301_011_users_email',
        '20260301_012_user_extension_links',
        '20260301_013_investigation_events',
        '20260301_014_geo_intel_cache',
        '20260301_015_whatweb_cache',
        '20260301_016_community_workflow',
        '20260301_017_user_profiles',
        '20260301_018_ml_keyword_enrichment_cache',
        '20260305_019_scan_image_reviews',
        '20260309_020_performance_indexes',
        '20260309_021_user_api_keys',
        '20260312_022_investigation_api_lookup_history',
        '20260312_023_user_platform_api_keys',
        '20260313_024_user_theme_avatar',
        '20260314_025_user_session_audit',
        '20260315_026_investigation_home_featured',
        '20260315_027_public_page_hits',
        '20260315_028_internal_ads',
        '20260320_029_investigation_correlation_pipeline',
        '20260322_030_access_request_profiles',
        '20260325_031_user_single_session',
        '20260424_032_client_host_baseline',
    ];

    foreach ($migrations as $id) {
        if (isset($applied[$id])) {
            continue;
        }
        $pdo->beginTransaction();
        try {
            clickfix_apply_migration($pdo, $id);
            $ins = $pdo->prepare('INSERT INTO schema_migrations (migration_id, applied_at) VALUES (:id, :at)');
            $ins->execute([':id' => $id, ':at' => gmdate('c')]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }
}

function clickfix_apply_migration(PDO $pdo, string $id): void
{
    if ($id === '20260301_001_tables') {
        $pdo->exec(<<<'SQL'
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
    user_agent TEXT,
    ip TEXT,
    country TEXT
);
SQL
        );
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    enabled INTEGER,
    alert_count INTEGER,
    block_count INTEGER,
    manual_sites_json TEXT,
    country TEXT
);
SQL
        );
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    username TEXT NOT NULL UNIQUE,
    email TEXT,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL
);
SQL
        );
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS appeals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    domain TEXT NOT NULL,
    reason TEXT NOT NULL,
    contact TEXT,
    status TEXT NOT NULL
);
SQL
        );
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS list_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    list_type TEXT NOT NULL,
    domain TEXT NOT NULL,
    reason TEXT NOT NULL
);
SQL
        );
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS list_suggestions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER,
    list_type TEXT NOT NULL,
    domain TEXT NOT NULL,
    reason TEXT NOT NULL,
    status TEXT NOT NULL
);
SQL
        );
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS access_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    email_hash TEXT,
    request_count INTEGER DEFAULT 1,
    first_seen_at TEXT,
    last_seen_at TEXT,
    request_ip TEXT,
    user_agent TEXT,
    request_lang TEXT,
    linkedin_url TEXT,
    company_website TEXT,
    status TEXT NOT NULL DEFAULT 'pending'
);
SQL
        );
        return;
    }

    if ($id === '20260301_002_reports_columns') {
        $columns = [
            'previous_url' => 'ALTER TABLE reports ADD COLUMN previous_url TEXT',
            'full_context' => 'ALTER TABLE reports ADD COLUMN full_context TEXT',
            'blocked' => 'ALTER TABLE reports ADD COLUMN blocked INTEGER DEFAULT 0',
            'accepted' => 'ALTER TABLE reports ADD COLUMN accepted INTEGER DEFAULT 0',
            'accepted_by' => 'ALTER TABLE reports ADD COLUMN accepted_by INTEGER',
            'accepted_at' => 'ALTER TABLE reports ADD COLUMN accepted_at TEXT',
            'review_status' => "ALTER TABLE reports ADD COLUMN review_status TEXT DEFAULT 'pending'",
            'reviewed_by' => 'ALTER TABLE reports ADD COLUMN reviewed_by INTEGER',
            'reviewed_at' => 'ALTER TABLE reports ADD COLUMN reviewed_at TEXT',
            'client_id' => 'ALTER TABLE reports ADD COLUMN client_id TEXT',
            'duplicate_count' => 'ALTER TABLE reports ADD COLUMN duplicate_count INTEGER DEFAULT 1',
            'last_seen' => 'ALTER TABLE reports ADD COLUMN last_seen TEXT',
            'score_total' => 'ALTER TABLE reports ADD COLUMN score_total INTEGER',
            'score_details_json' => 'ALTER TABLE reports ADD COLUMN score_details_json TEXT',
            'reason_entries_json' => 'ALTER TABLE reports ADD COLUMN reason_entries_json TEXT',
            'matched_snippets_json' => 'ALTER TABLE reports ADD COLUMN matched_snippets_json TEXT',
            'user_agent' => 'ALTER TABLE reports ADD COLUMN user_agent TEXT',
            'ip' => 'ALTER TABLE reports ADD COLUMN ip TEXT',
            'country' => 'ALTER TABLE reports ADD COLUMN country TEXT',
        ];
        foreach ($columns as $name => $sql) {
            if (!clickfix_has_column($pdo, 'reports', $name)) {
                $pdo->exec($sql);
            }
        }
        return;
    }

    if ($id === '20260301_003_stats_columns') {
        $columns = [
            'user_agent' => 'ALTER TABLE stats ADD COLUMN user_agent TEXT',
            'install_type' => 'ALTER TABLE stats ADD COLUMN install_type TEXT',
            'install_source' => 'ALTER TABLE stats ADD COLUMN install_source TEXT',
            'install_channel' => 'ALTER TABLE stats ADD COLUMN install_channel TEXT',
            'client_id' => 'ALTER TABLE stats ADD COLUMN client_id TEXT',
            'country' => 'ALTER TABLE stats ADD COLUMN country TEXT',
        ];
        foreach ($columns as $name => $sql) {
            if (!clickfix_has_column($pdo, 'stats', $name)) {
                $pdo->exec($sql);
            }
        }
        return;
    }

    if ($id === '20260301_004_users_access_columns') {
        if (!clickfix_has_column($pdo, 'users', 'verified')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN verified INTEGER DEFAULT 0');
        }
        $columns = [
            'email_hash' => 'ALTER TABLE access_requests ADD COLUMN email_hash TEXT',
            'request_count' => 'ALTER TABLE access_requests ADD COLUMN request_count INTEGER DEFAULT 1',
            'first_seen_at' => 'ALTER TABLE access_requests ADD COLUMN first_seen_at TEXT',
            'last_seen_at' => 'ALTER TABLE access_requests ADD COLUMN last_seen_at TEXT',
            'request_ip' => 'ALTER TABLE access_requests ADD COLUMN request_ip TEXT',
            'user_agent' => 'ALTER TABLE access_requests ADD COLUMN user_agent TEXT',
            'request_lang' => 'ALTER TABLE access_requests ADD COLUMN request_lang TEXT',
            'linkedin_url' => 'ALTER TABLE access_requests ADD COLUMN linkedin_url TEXT',
            'company_website' => 'ALTER TABLE access_requests ADD COLUMN company_website TEXT',
            'status' => "ALTER TABLE access_requests ADD COLUMN status TEXT DEFAULT 'pending'",
        ];
        foreach ($columns as $name => $sql) {
            if (!clickfix_has_column($pdo, 'access_requests', $name)) {
                $pdo->exec($sql);
            }
        }
        return;
    }

    if ($id === '20260301_005_indexes') {
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_received_at ON reports(received_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_hostname ON reports(hostname)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_review_status ON reports(review_status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_client_host ON reports(client_id, hostname, url, blocked, ip)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_stats_received_at ON stats(received_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_stats_client_id ON stats(client_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_appeals_status ON appeals(status)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_access_requests_email_hash ON access_requests(email_hash)');
        return;
    }

    if ($id === '20260301_006_backfill') {
        if (clickfix_has_column($pdo, 'reports', 'last_seen')) {
            $pdo->exec("UPDATE reports SET last_seen = received_at WHERE last_seen IS NULL OR last_seen = ''");
        }
        if (clickfix_has_column($pdo, 'access_requests', 'first_seen_at') && clickfix_has_column($pdo, 'access_requests', 'last_seen_at')) {
            $pdo->exec('UPDATE access_requests SET first_seen_at = COALESCE(first_seen_at, last_seen_at)');
            $pdo->exec('UPDATE access_requests SET last_seen_at = COALESCE(last_seen_at, first_seen_at)');
        }
        return;
    }

    if ($id === '20260301_007_reason_columns') {
        if (!clickfix_has_column($pdo, 'reports', 'reason_entries_json')) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN reason_entries_json TEXT');
        }
        if (!clickfix_has_column($pdo, 'reports', 'matched_snippets_json')) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN matched_snippets_json TEXT');
        }
        return;
    }

    if ($id === '20260301_008_investigation_graphs') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS investigation_graphs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    site_domain TEXT,
    verdict TEXT NOT NULL DEFAULT 'suspicious',
    summary TEXT,
    tags_json TEXT,
    graph_json TEXT NOT NULL,
    is_public INTEGER DEFAULT 0,
    share_token TEXT,
    deleted INTEGER DEFAULT 0
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_graphs_user ON investigation_graphs(user_id, updated_at DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_graphs_site ON investigation_graphs(site_domain)');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_investigation_graphs_share ON investigation_graphs(share_token)');
        return;
    }

    if ($id === '20260301_009_api_security') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS api_clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    label TEXT,
    license_key_hash TEXT NOT NULL UNIQUE,
    tier TEXT NOT NULL DEFAULT 'basic',
    max_rpm INTEGER NOT NULL DEFAULT 120,
    active INTEGER NOT NULL DEFAULT 1
);
SQL
        );
        $pdo->exec(<<<'SQL'
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
SQL
        );
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS api_rate_limits (
    bucket_key TEXT PRIMARY KEY,
    window_start INTEGER NOT NULL,
    request_count INTEGER NOT NULL
);
SQL
        );
        if (!clickfix_has_column($pdo, 'reports', 'event_type')) {
            $pdo->exec("ALTER TABLE reports ADD COLUMN event_type TEXT DEFAULT 'clickfix_alert'");
        }
        if (!clickfix_has_column($pdo, 'reports', 'runtime_verdict_json')) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN runtime_verdict_json TEXT');
        }
        if (!clickfix_has_column($pdo, 'reports', 'server_score_total')) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN server_score_total INTEGER');
        }
        if (!clickfix_has_column($pdo, 'reports', 'server_verdict')) {
            $pdo->exec('ALTER TABLE reports ADD COLUMN server_verdict TEXT');
        }
        if (!clickfix_has_column($pdo, 'reports', 'trusted_signal_source')) {
            $pdo->exec("ALTER TABLE reports ADD COLUMN trusted_signal_source INTEGER DEFAULT 0");
        }
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_event_type ON reports(event_type)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_server_verdict ON reports(server_verdict)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_api_refresh_client ON api_refresh_tokens(client_id, device_id)');
        return;
    }

    if ($id === '20260301_010_ops_suite') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS extension_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    created_by INTEGER,
    target_scope TEXT NOT NULL,
    target_client_id TEXT,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    severity TEXT NOT NULL DEFAULT 'info',
    starts_at TEXT,
    expires_at TEXT,
    active INTEGER NOT NULL DEFAULT 1
);
SQL
        );
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS report_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    period TEXT NOT NULL,
    recipient TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    last_run_at TEXT,
    next_run_at TEXT
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_extension_messages_scope ON extension_messages(target_scope, active)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_extension_messages_client ON extension_messages(target_client_id, active)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_report_schedules_period ON report_schedules(period, enabled)');
        return;
    }

    if ($id === '20260301_011_users_email') {
        if (!clickfix_has_column($pdo, 'users', 'email')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN email TEXT');
        }
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_email_unique ON users(email COLLATE NOCASE) WHERE email IS NOT NULL AND email != ""');
        return;
    }

    if ($id === '20260301_012_user_extension_links') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_extension_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    created_by INTEGER,
    user_id INTEGER NOT NULL,
    client_id TEXT NOT NULL,
    note TEXT,
    active INTEGER NOT NULL DEFAULT 1
);
SQL
        );
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_user_extension_links_unique ON user_extension_links(user_id, client_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_extension_links_client ON user_extension_links(client_id, active)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_extension_links_user ON user_extension_links(user_id, active)');
        return;
    }

    if ($id === '20260301_013_investigation_events') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS investigation_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    graph_id INTEGER NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    details_json TEXT
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_events_graph ON investigation_events(graph_id, created_at DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_events_user ON investigation_events(user_id, created_at DESC)');
        $pdo->exec(<<<'SQL'
INSERT INTO investigation_events (created_at, graph_id, user_id, action, details_json)
SELECT COALESCE(ig.created_at, ig.updated_at, CURRENT_TIMESTAMP),
       ig.id,
       ig.user_id,
       'snapshot',
       '{"source":"migration_seed"}'
FROM investigation_graphs ig
WHERE ig.deleted = 0
  AND NOT EXISTS (
    SELECT 1
    FROM investigation_events ie
    WHERE ie.graph_id = ig.id
  )
SQL
        );
        return;
    }

    if ($id === '20260301_014_geo_intel_cache') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS geo_country_cache (
    country_code TEXT PRIMARY KEY,
    country_name TEXT,
    latitude REAL,
    longitude REAL,
    languages_json TEXT,
    updated_at TEXT NOT NULL
);
SQL
        );
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS domain_intel_cache (
    hostname TEXT PRIMARY KEY,
    ip TEXT,
    isp TEXT,
    country_code TEXT,
    country_name TEXT,
    language TEXT,
    latitude REAL,
    longitude REAL,
    source TEXT,
    checked_at TEXT NOT NULL
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_domain_intel_country ON domain_intel_cache(country_code)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_domain_intel_checked ON domain_intel_cache(checked_at)');
        return;
    }

    if ($id === '20260301_015_whatweb_cache') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS whatweb_cache (
    hostname TEXT PRIMARY KEY,
    checked_at TEXT NOT NULL,
    raw_line TEXT,
    status TEXT,
    ip TEXT,
    country_code TEXT,
    country_name TEXT,
    http_server TEXT,
    title TEXT,
    plugins_json TEXT,
    services_json TEXT,
    error_text TEXT
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_whatweb_cache_checked ON whatweb_cache(checked_at)');
        return;
    }

    if ($id === '20260301_016_community_workflow') {
        if (!clickfix_has_column($pdo, 'users', 'preferred_lang')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN preferred_lang TEXT DEFAULT 'en'");
        }
        if (!clickfix_has_column($pdo, 'users', 'reputation')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN reputation INTEGER DEFAULT 0');
        }
        $pdo->exec("UPDATE users SET preferred_lang = 'en' WHERE preferred_lang IS NULL OR preferred_lang = ''");
        $pdo->exec('UPDATE users SET reputation = 0 WHERE reputation IS NULL');

        if (!clickfix_has_column($pdo, 'investigation_graphs', 'submitted_to_community')) {
            $pdo->exec('ALTER TABLE investigation_graphs ADD COLUMN submitted_to_community INTEGER DEFAULT 0');
        }
        if (!clickfix_has_column($pdo, 'investigation_graphs', 'workflow_status')) {
            $pdo->exec("ALTER TABLE investigation_graphs ADD COLUMN workflow_status TEXT DEFAULT 'draft'");
        }
        if (!clickfix_has_column($pdo, 'investigation_graphs', 'community_origin_role')) {
            $pdo->exec('ALTER TABLE investigation_graphs ADD COLUMN community_origin_role TEXT');
        }
        if (!clickfix_has_column($pdo, 'investigation_graphs', 'verified_by')) {
            $pdo->exec('ALTER TABLE investigation_graphs ADD COLUMN verified_by INTEGER');
        }
        if (!clickfix_has_column($pdo, 'investigation_graphs', 'verified_at')) {
            $pdo->exec('ALTER TABLE investigation_graphs ADD COLUMN verified_at TEXT');
        }
        if (!clickfix_has_column($pdo, 'investigation_graphs', 'verification_note')) {
            $pdo->exec('ALTER TABLE investigation_graphs ADD COLUMN verification_note TEXT');
        }
        $pdo->exec(
            "UPDATE investigation_graphs
             SET submitted_to_community = CASE WHEN is_public = 1 THEN 1 ELSE COALESCE(submitted_to_community, 0) END
             WHERE submitted_to_community IS NULL"
        );
        $pdo->exec(
            "UPDATE investigation_graphs
             SET workflow_status = CASE
                 WHEN is_public = 1 THEN 'verified_public'
                 WHEN COALESCE(workflow_status, '') = '' THEN 'draft'
                 ELSE workflow_status
             END
             WHERE workflow_status IS NULL OR workflow_status = ''"
        );

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS investigation_votes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    graph_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    vote INTEGER NOT NULL
);
SQL
        );
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_investigation_votes_graph_user ON investigation_votes(graph_id, user_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_votes_graph ON investigation_votes(graph_id, vote)');

        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_reputation_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    delta INTEGER NOT NULL,
    reason TEXT NOT NULL,
    context_graph_id INTEGER,
    created_by INTEGER
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_rep_events_user ON user_reputation_events(user_id, created_at DESC)');
        return;
    }

    if ($id === '20260301_017_user_profiles') {
        $columns = [
            'full_name' => 'ALTER TABLE users ADD COLUMN full_name TEXT',
            'profile_email_public' => 'ALTER TABLE users ADD COLUMN profile_email_public INTEGER DEFAULT 0',
            'profile_vt_public' => 'ALTER TABLE users ADD COLUMN profile_vt_public INTEGER DEFAULT 0',
            'profile_vt_handle' => 'ALTER TABLE users ADD COLUMN profile_vt_handle TEXT',
            'profile_threatrip_public' => 'ALTER TABLE users ADD COLUMN profile_threatrip_public INTEGER DEFAULT 0',
            'profile_threatrip_id' => 'ALTER TABLE users ADD COLUMN profile_threatrip_id TEXT',
            'profile_abuseipdb_public' => 'ALTER TABLE users ADD COLUMN profile_abuseipdb_public INTEGER DEFAULT 0',
            'profile_abuseipdb_id' => 'ALTER TABLE users ADD COLUMN profile_abuseipdb_id TEXT',
            'profile_github_public' => 'ALTER TABLE users ADD COLUMN profile_github_public INTEGER DEFAULT 0',
            'profile_github_handle' => 'ALTER TABLE users ADD COLUMN profile_github_handle TEXT',
        ];
        foreach ($columns as $name => $sql) {
            if (!clickfix_has_column($pdo, 'users', $name)) {
                $pdo->exec($sql);
            }
        }
        $pdo->exec('UPDATE users SET profile_email_public = COALESCE(profile_email_public, 0)');
        $pdo->exec('UPDATE users SET profile_vt_public = COALESCE(profile_vt_public, 0)');
        $pdo->exec('UPDATE users SET profile_threatrip_public = COALESCE(profile_threatrip_public, 0)');
        $pdo->exec('UPDATE users SET profile_abuseipdb_public = COALESCE(profile_abuseipdb_public, 0)');
        $pdo->exec('UPDATE users SET profile_github_public = COALESCE(profile_github_public, 0)');
        return;
    }

    if ($id === '20260301_018_ml_keyword_enrichment_cache') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS ml_keyword_enrichment_cache (
    url TEXT PRIMARY KEY,
    checked_at TEXT NOT NULL,
    keyword_hits_json TEXT,
    resource_count INTEGER DEFAULT 0,
    status TEXT
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ml_keyword_enrichment_checked ON ml_keyword_enrichment_cache(checked_at)');
        return;
    }

    if ($id === '20260305_019_scan_image_reviews') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS scan_image_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL,
    kind TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    reviewed_at TEXT,
    reviewed_by INTEGER,
    review_note TEXT
);
SQL
        );
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_scan_image_reviews_unique ON scan_image_reviews(report_id, kind)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_scan_image_reviews_status ON scan_image_reviews(status, updated_at)');
        return;
    }

    if ($id === '20260309_020_performance_indexes') {
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_last_seen_id ON reports(last_seen DESC, id DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_received_id ON reports(received_at DESC, id DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports_client_received ON reports(client_id, received_at DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_stats_client_received ON stats(client_id, received_at DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_access_requests_last_seen ON access_requests(last_seen_at DESC, id DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_ext_links_active_updated ON user_extension_links(active, updated_at DESC, id DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_extension_messages_created ON extension_messages(id DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_graphs_deleted_updated ON investigation_graphs(deleted, updated_at DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_graphs_deleted_user_updated ON investigation_graphs(deleted, user_id, updated_at DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_graphs_community ON investigation_graphs(deleted, submitted_to_community, updated_at DESC)');
        return;
    }

    if ($id === '20260309_021_user_api_keys') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    provider TEXT NOT NULL,
    api_key_enc TEXT NOT NULL,
    note TEXT,
    active INTEGER NOT NULL DEFAULT 1
);
SQL
        );
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_user_api_keys_user_provider ON user_api_keys(user_id, provider)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_api_keys_user_active_updated ON user_api_keys(user_id, active, updated_at DESC)');
        return;
    }

    if ($id === '20260312_022_investigation_api_lookup_history') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS investigation_api_lookup_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    graph_id INTEGER NOT NULL DEFAULT 0,
    provider TEXT NOT NULL,
    target TEXT NOT NULL,
    target_type TEXT NOT NULL DEFAULT 'unknown',
    status INTEGER NOT NULL DEFAULT 0,
    ok INTEGER NOT NULL DEFAULT 0,
    error TEXT,
    summary_json TEXT,
    response_json TEXT
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_lookup_history_user_created ON investigation_api_lookup_history(user_id, created_at DESC, id DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_lookup_history_user_graph_created ON investigation_api_lookup_history(user_id, graph_id, created_at DESC, id DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_lookup_history_provider ON investigation_api_lookup_history(provider, created_at DESC, id DESC)');
        return;
    }

    if ($id === '20260312_023_user_platform_api_keys') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS api_user_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    label TEXT NOT NULL,
    key_prefix TEXT NOT NULL,
    key_hash TEXT NOT NULL UNIQUE,
    scopes TEXT NOT NULL DEFAULT 'intel:read',
    max_rpm INTEGER NOT NULL DEFAULT 120,
    last_used_at TEXT,
    last_ip TEXT,
    expires_at TEXT,
    revoked_at TEXT
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_api_user_keys_user_active ON api_user_keys(user_id, revoked_at, expires_at, updated_at DESC, id DESC)');
        return;
    }

    if ($id === '20260313_024_user_theme_avatar') {
        if (!clickfix_has_column($pdo, 'users', 'profile_theme')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN profile_theme TEXT DEFAULT 'default'");
        }
        if (!clickfix_has_column($pdo, 'users', 'profile_avatar_url')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN profile_avatar_url TEXT DEFAULT ''");
        }
        $pdo->exec("UPDATE users SET profile_theme = 'default' WHERE profile_theme IS NULL OR TRIM(profile_theme) = ''");
        $pdo->exec("UPDATE users SET profile_avatar_url = '' WHERE profile_avatar_url IS NULL");
        return;
    }

    if ($id === '20260314_025_user_session_audit') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_session_audit (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    username TEXT NOT NULL,
    event_type TEXT NOT NULL,
    ip TEXT,
    user_agent TEXT,
    session_id TEXT
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_session_audit_user_created ON user_session_audit(user_id, created_at DESC, id DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_session_audit_event_created ON user_session_audit(event_type, created_at DESC, id DESC)');
        return;
    }

    if ($id === '20260315_026_investigation_home_featured') {
        if (!clickfix_has_column($pdo, 'investigation_graphs', 'source_report_id')) {
            $pdo->exec('ALTER TABLE investigation_graphs ADD COLUMN source_report_id INTEGER DEFAULT 0');
        }
        if (!clickfix_has_column($pdo, 'investigation_graphs', 'show_on_home')) {
            $pdo->exec('ALTER TABLE investigation_graphs ADD COLUMN show_on_home INTEGER DEFAULT 0');
        }
        if (!clickfix_has_column($pdo, 'investigation_graphs', 'home_position')) {
            $pdo->exec('ALTER TABLE investigation_graphs ADD COLUMN home_position INTEGER DEFAULT 0');
        }
        $pdo->exec('UPDATE investigation_graphs SET source_report_id = COALESCE(source_report_id, 0)');
        $pdo->exec('UPDATE investigation_graphs SET show_on_home = COALESCE(show_on_home, 0)');
        $pdo->exec('UPDATE investigation_graphs SET home_position = COALESCE(home_position, 0)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_graphs_home_featured ON investigation_graphs(show_on_home, is_public, home_position, updated_at DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_graphs_source_report ON investigation_graphs(source_report_id)');
        return;
    }

    if ($id === '20260315_027_public_page_hits') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS public_page_hits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    path TEXT,
    lang TEXT,
    ip TEXT,
    country_code TEXT,
    country_name TEXT,
    region TEXT,
    region_name TEXT,
    city TEXT,
    timezone TEXT,
    isp TEXT,
    org TEXT,
    asn TEXT,
    asname TEXT,
    referrer_url TEXT,
    referrer_host TEXT,
    user_agent TEXT,
    mobile INTEGER DEFAULT 0,
    proxy INTEGER DEFAULT 0,
    hosting INTEGER DEFAULT 0,
    lat REAL,
    lon REAL
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_public_page_hits_created ON public_page_hits(created_at)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_public_page_hits_ip ON public_page_hits(ip)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_public_page_hits_referrer ON public_page_hits(referrer_host)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_public_page_hits_network ON public_page_hits(asname, isp)');
        return;
    }

    if ($id === '20260322_030_access_request_profiles') {
        $columns = [
            'linkedin_url' => 'ALTER TABLE access_requests ADD COLUMN linkedin_url TEXT',
            'company_website' => 'ALTER TABLE access_requests ADD COLUMN company_website TEXT',
        ];
        foreach ($columns as $name => $sql) {
            if (!clickfix_has_column($pdo, 'access_requests', $name)) {
                $pdo->exec($sql);
            }
        }
        return;
    }

    if ($id === '20260325_031_user_single_session') {
        if (!clickfix_has_column($pdo, 'users', 'active_session_id')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN active_session_id TEXT');
        }
        if (!clickfix_has_column($pdo, 'users', 'active_session_updated_at')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN active_session_updated_at TEXT');
        }
        return;
    }


    if ($id === '20260424_032_client_host_baseline') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS client_host_baseline (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id TEXT NOT NULL,
    hostname TEXT NOT NULL,
    first_seen_at TEXT NOT NULL,
    last_seen_at TEXT NOT NULL,
    last_visit_day TEXT,
    days_seen INTEGER DEFAULT 0,
    visits_count INTEGER DEFAULT 0,
    alert_count INTEGER DEFAULT 0,
    blocked_count INTEGER DEFAULT 0,
    accepted_count INTEGER DEFAULT 0,
    rejected_count INTEGER DEFAULT 0,
    allowlisted_count INTEGER DEFAULT 0,
    local_allowlisted INTEGER DEFAULT 0,
    trust_score INTEGER DEFAULT 0,
    last_verdict TEXT,
    updated_at TEXT NOT NULL,
    source TEXT DEFAULT 'extension'
);
SQL
        );
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_client_host_baseline_unique ON client_host_baseline(client_id, hostname)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_client_host_baseline_client ON client_host_baseline(client_id, trust_score DESC, last_seen_at DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_client_host_baseline_host ON client_host_baseline(hostname, trust_score DESC)');
        return;
    }

    if ($id === '20260428_033_public_preview_settings') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS public_preview_settings (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    updated_at TEXT NOT NULL,
    updated_by INTEGER,
    limit_points_per_country INTEGER DEFAULT 1,
    max_points_per_country INTEGER DEFAULT 2
);
SQL
        );
        $pdo->exec(
            "INSERT OR IGNORE INTO public_preview_settings (
                id, updated_at, updated_by, limit_points_per_country, max_points_per_country
             ) VALUES (
                1, '" . gmdate('c') . "', 0, 1, 2
             )"
        );
        return;
    }

    if ($id === '20260315_028_internal_ads') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS internal_ad_settings (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    updated_at TEXT NOT NULL,
    updated_by INTEGER,
    enabled_global INTEGER DEFAULT 1,
    show_guest INTEGER DEFAULT 1,
    show_analyst_jr INTEGER DEFAULT 1,
    show_analyst_mid INTEGER DEFAULT 1,
    show_analyst_sr INTEGER DEFAULT 0,
    show_admin INTEGER DEFAULT 0
);
SQL
        );
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS internal_ads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    created_by INTEGER,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    cta_label TEXT,
    cta_url TEXT,
    placement TEXT NOT NULL DEFAULT 'both',
    theme TEXT NOT NULL DEFAULT 'cyan',
    priority INTEGER DEFAULT 100,
    starts_at TEXT,
    expires_at TEXT,
    active INTEGER DEFAULT 1,
    target_guest INTEGER DEFAULT 1,
    target_analyst_jr INTEGER DEFAULT 1,
    target_analyst_mid INTEGER DEFAULT 1,
    target_analyst_sr INTEGER DEFAULT 0,
    target_admin INTEGER DEFAULT 0
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_internal_ads_active_placement ON internal_ads(active, placement, priority DESC, updated_at DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_internal_ads_window ON internal_ads(starts_at, expires_at)');
        $pdo->exec(
            "INSERT OR IGNORE INTO internal_ad_settings (
                id, updated_at, updated_by, enabled_global, show_guest, show_analyst_jr, show_analyst_mid, show_analyst_sr, show_admin
             ) VALUES (
                1, '" . gmdate('c') . "', 0, 1, 1, 1, 1, 0, 0
             )"
        );
        $existingAds = (int) ($pdo->query('SELECT COUNT(*) FROM internal_ads')->fetchColumn() ?: 0);
        if ($existingAds === 0) {
            $seedNow = gmdate('c');
            $seedRows = [
                [
                    'title' => 'Demo sponsor | Threat Intel Feed',
                    'body' => 'Espacio de prueba para promocionar un feed CTI, una demo o un partner. Visible solo para perfiles con anuncios habilitados.',
                    'cta_label' => 'Ver demo',
                    'cta_url' => 'https://jordiserrano.me',
                    'placement' => 'both',
                    'theme' => 'cyan',
                    'priority' => 200,
                    'target_guest' => 1,
                    'target_analyst_jr' => 1,
                    'target_analyst_mid' => 1,
                    'target_analyst_sr' => 0,
                    'target_admin' => 0,
                ],
                [
                    'title' => 'Test ad | Analyst onboarding',
                    'body' => 'Anuncio interno de prueba para captar analistas, dar visibilidad a nuevas demos o resaltar workflows guiados.',
                    'cta_label' => 'Solicitar acceso',
                    'cta_url' => 'https://clickfix.jordiserrano.me/index.php#dashboard-preview-access',
                    'placement' => 'index',
                    'theme' => 'lime',
                    'priority' => 180,
                    'target_guest' => 1,
                    'target_analyst_jr' => 1,
                    'target_analyst_mid' => 0,
                    'target_analyst_sr' => 0,
                    'target_admin' => 0,
                ],
                [
                    'title' => 'Test ad | Upgrade workspace',
                    'body' => 'Promociona integraciones, servicios gestionados o nuevas capacidades del panel sin depender de terceros.',
                    'cta_label' => 'Abrir dashboard',
                    'cta_url' => 'https://clickfix.jordiserrano.me/dashboard.php?page=ops',
                    'placement' => 'dashboard',
                    'theme' => 'amber',
                    'priority' => 160,
                    'target_guest' => 0,
                    'target_analyst_jr' => 1,
                    'target_analyst_mid' => 1,
                    'target_analyst_sr' => 0,
                    'target_admin' => 0,
                ],
            ];
            $ins = $pdo->prepare(
                'INSERT INTO internal_ads (
                    created_at, updated_at, created_by, title, body, cta_label, cta_url, placement, theme, priority, starts_at, expires_at,
                    active, target_guest, target_analyst_jr, target_analyst_mid, target_analyst_sr, target_admin
                 ) VALUES (
                    :created_at, :updated_at, 0, :title, :body, :cta_label, :cta_url, :placement, :theme, :priority, NULL, NULL,
                    1, :target_guest, :target_analyst_jr, :target_analyst_mid, :target_analyst_sr, :target_admin
                 )'
            );
            foreach ($seedRows as $seedRow) {
                $ins->execute([
                    ':created_at' => $seedNow,
                    ':updated_at' => $seedNow,
                    ':title' => $seedRow['title'],
                    ':body' => $seedRow['body'],
                    ':cta_label' => $seedRow['cta_label'],
                    ':cta_url' => $seedRow['cta_url'],
                    ':placement' => $seedRow['placement'],
                    ':theme' => $seedRow['theme'],
                    ':priority' => (int) $seedRow['priority'],
                    ':target_guest' => (int) $seedRow['target_guest'],
                    ':target_analyst_jr' => (int) $seedRow['target_analyst_jr'],
                    ':target_analyst_mid' => (int) $seedRow['target_analyst_mid'],
                    ':target_analyst_sr' => (int) $seedRow['target_analyst_sr'],
                    ':target_admin' => (int) $seedRow['target_admin'],
                ]);
            }
        }
        return;
    }

    if ($id === '20260320_029_investigation_correlation_pipeline') {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS investigation_analysis_jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    graph_id INTEGER NOT NULL,
    report_id INTEGER DEFAULT 0,
    user_id INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'queued',
    mode TEXT NOT NULL DEFAULT 'alert_correlation',
    root_text TEXT,
    requested_depth INTEGER DEFAULT 3,
    started_at TEXT,
    finished_at TEXT,
    processed_artifacts INTEGER DEFAULT 0,
    last_error TEXT
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_analysis_jobs_status ON investigation_analysis_jobs(status, updated_at DESC)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_analysis_jobs_graph ON investigation_analysis_jobs(graph_id, created_at DESC)');
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS investigation_artifacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    graph_id INTEGER NOT NULL,
    job_id INTEGER DEFAULT 0,
    parent_artifact_id INTEGER DEFAULT 0,
    user_id INTEGER NOT NULL,
    artifact_kind TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'derived',
    label TEXT,
    artifact_value TEXT NOT NULL,
    normalized_value TEXT,
    source_url TEXT,
    file_name TEXT,
    depth INTEGER DEFAULT 0,
    fetch_status TEXT NOT NULL DEFAULT 'not_fetched',
    analysis_status TEXT NOT NULL DEFAULT 'pending',
    vt_summary_json TEXT,
    threatrip_summary_json TEXT,
    tags_json TEXT,
    metadata_json TEXT
);
SQL
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_artifacts_graph ON investigation_artifacts(graph_id, depth, id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_artifacts_job ON investigation_artifacts(job_id, id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_investigation_artifacts_parent ON investigation_artifacts(parent_artifact_id, id)');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_investigation_artifacts_unique ON investigation_artifacts(graph_id, parent_artifact_id, artifact_kind, normalized_value, depth)');
        return;
    }
}

function clickfix_client_ip(): string
{
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        $value = trim((string) ($_SERVER[$header] ?? ''));
        if ($value === '') {
            continue;
        }
        $candidate = trim(explode(',', $value)[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }
    return '0.0.0.0';
}

function clickfix_csrf_token(): string
{
    if (!isset($_SESSION['clickfix_csrf']) || !is_string($_SESSION['clickfix_csrf'])) {
        $_SESSION['clickfix_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['clickfix_csrf'];
}

function clickfix_verify_csrf(string $token): bool
{
    $known = (string) ($_SESSION['clickfix_csrf'] ?? '');
    return $known !== '' && hash_equals($known, $token);
}

function clickfix_normalize_role(string $role): string
{
    $normalized = strtolower(trim($role));
    if (in_array($normalized, ['admin', 'administrator', 'administrador', 'administrateur', 'superadmin', 'super_admin', 'root'], true)) {
        return 'admin';
    }
    if (in_array($normalized, ['analyst_sr', 'sr', 'senior', 'analista_sr', 'midsenior', 'mid_senior', 'analyst_midsenior', 'analista_midsenior'], true)) {
        return 'analyst_sr';
    }
    if (in_array($normalized, ['analyst_mid', 'mid', 'analyst', 'analista_mid'], true)) {
        return 'analyst_mid';
    }
    if (in_array($normalized, ['analyst_jr', 'jr', 'junior', 'analista_jr'], true)) {
        return 'analyst_jr';
    }
    return 'analyst_jr';
}

function clickfix_role_rank(string $role): int
{
    $normalized = clickfix_normalize_role($role);
    $ranks = [
        'analyst_jr' => 10,
        'analyst_mid' => 20,
        'analyst_sr' => 30,
        'admin' => 40,
    ];
    return (int) ($ranks[$normalized] ?? 0);
}

function clickfix_role_label(string $role): string
{
    $normalized = clickfix_normalize_role($role);
    $labels = [
        'analyst_jr' => 'Analista Jr',
        'analyst_mid' => 'Analista Mid',
        'analyst_sr' => 'Analista Sr',
        'admin' => 'Administrador',
    ];
    return (string) ($labels[$normalized] ?? 'Analista Jr');
}

function clickfix_normalize_user_language(string $language): string
{
    $normalized = strtolower(trim($language));
    $supported = ['en', 'es', 'ca', 'de', 'fr', 'it', 'nl', 'he', 'ru', 'zh', 'ko', 'ja', 'pt', 'ar', 'hi'];
    if (in_array($normalized, $supported, true)) {
        return $normalized;
    }
    $base = substr($normalized, 0, 2);
    if (in_array($base, $supported, true)) {
        return $base;
    }
    return 'en';
}

function clickfix_profile_normalize_name(string $value): string
{
    return substr(trim($value), 0, 120);
}

function clickfix_profile_normalize_flag($value): int
{
    return ((string) $value === '1' || (string) $value === 'true' || (string) $value === 'on') ? 1 : 0;
}

function clickfix_profile_normalize_threatrip_id(string $value): string
{
    $normalized = preg_replace('/[^0-9]/', '', trim($value));
    return substr((string) $normalized, 0, 16);
}

function clickfix_profile_normalize_abuseipdb_id(string $value): string
{
    $normalized = preg_replace('/[^0-9]/', '', trim($value));
    return substr((string) $normalized, 0, 16);
}

function clickfix_profile_normalize_vt_handle(string $value): string
{
    $normalized = preg_replace('/[^a-zA-Z0-9._-]/', '', trim($value));
    return substr((string) $normalized, 0, 80);
}

function clickfix_profile_normalize_github_handle(string $value): string
{
    $normalized = preg_replace('/[^a-zA-Z0-9-]/', '', trim($value));
    $normalized = trim((string) $normalized, '-');
    return substr((string) $normalized, 0, 80);
}

function clickfix_profile_normalize_theme(string $value): string
{
    $normalized = strtolower(trim($value));
    $allowed = ['default', 'teal', 'sunset', 'mono'];
    return in_array($normalized, $allowed, true) ? $normalized : 'default';
}

function clickfix_profile_normalize_avatar_url(string $value): string
{
    $trimmed = substr(trim($value), 0, 420);
    if ($trimmed === '') {
        return '';
    }
    if (clickfix_str_starts_with($trimmed, '/')) {
        return $trimmed;
    }
    $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }
    return filter_var($trimmed, FILTER_VALIDATE_URL) ? $trimmed : '';
}

function clickfix_user_has_min_role(?array $user, string $minimumRole): bool
{
    if ($user === null) {
        return false;
    }
    return clickfix_role_rank((string) ($user['role'] ?? '')) >= clickfix_role_rank($minimumRole);
}

function clickfix_current_user(): ?array
{
    if (!isset($_SESSION['clickfix_user']) || !is_array($_SESSION['clickfix_user'])) {
        return null;
    }
    $row = $_SESSION['clickfix_user'];
    $role = clickfix_normalize_role((string) ($row['role'] ?? 'analyst_mid'));
    return [
        'id' => (int) ($row['id'] ?? 0),
        'username' => (string) ($row['username'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'role' => $role,
        'role_label' => clickfix_role_label($role),
        'role_rank' => clickfix_role_rank($role),
        'verified' => (int) ($row['verified'] ?? 0),
        'preferred_lang' => clickfix_normalize_user_language((string) ($row['preferred_lang'] ?? 'en')),
        'reputation' => (int) ($row['reputation'] ?? 0),
        'profile_theme' => clickfix_profile_normalize_theme((string) ($row['profile_theme'] ?? 'default')),
        'profile_avatar_url' => clickfix_profile_normalize_avatar_url((string) ($row['profile_avatar_url'] ?? '')),
    ];
}

function clickfix_is_admin(): bool
{
    $user = clickfix_current_user();
    return clickfix_user_has_min_role($user, 'admin');
}

function clickfix_authenticate(PDO $pdo, string $username, string $password): ?array
{
    $hasEmail = clickfix_has_column($pdo, 'users', 'email');
    $hasPreferredLang = clickfix_has_column($pdo, 'users', 'preferred_lang');
    $hasReputation = clickfix_has_column($pdo, 'users', 'reputation');
    $hasProfileTheme = clickfix_has_column($pdo, 'users', 'profile_theme');
    $hasProfileAvatarUrl = clickfix_has_column($pdo, 'users', 'profile_avatar_url');
    $sql = 'SELECT id, username, ';
    $sql .= $hasEmail ? 'email' : "'' AS email";
    $sql .= ', role, verified, password_hash, ';
    $sql .= $hasPreferredLang ? 'preferred_lang' : "'en' AS preferred_lang";
    $sql .= ', ';
    $sql .= $hasReputation ? 'reputation' : '0 AS reputation';
    $sql .= ', ';
    $sql .= $hasProfileTheme ? 'profile_theme' : "'default' AS profile_theme";
    $sql .= ', ';
    $sql .= $hasProfileAvatarUrl ? 'profile_avatar_url' : "'' AS profile_avatar_url";
    $sql .= ' FROM users WHERE LOWER(username) = LOWER(:u) LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':u' => trim($username)]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $hash = (string) ($row['password_hash'] ?? '');
    if ($hash === '' || !password_verify($password, $hash)) {
        return null;
    }
    $role = clickfix_normalize_role((string) ($row['role'] ?? 'analyst_mid'));
    return [
        'id' => (int) ($row['id'] ?? 0),
        'username' => (string) ($row['username'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'role' => $role,
        'role_label' => clickfix_role_label($role),
        'role_rank' => clickfix_role_rank($role),
        'verified' => (int) ($row['verified'] ?? 0),
        'preferred_lang' => clickfix_normalize_user_language((string) ($row['preferred_lang'] ?? 'en')),
        'reputation' => (int) ($row['reputation'] ?? 0),
        'profile_theme' => clickfix_profile_normalize_theme((string) ($row['profile_theme'] ?? 'default')),
        'profile_avatar_url' => clickfix_profile_normalize_avatar_url((string) ($row['profile_avatar_url'] ?? '')),
    ];
}

function clickfix_live_metrics(PDO $pdo): array
{
    $cacheKey = clickfix_cache_key('live_metrics', ['v2' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $stats = [
        'last_update' => gmdate('c'),
        'total_alerts' => 0,
        'total_blocks' => 0,
        'alerts_24h' => 0,
        'blocks_24h' => 0,
        'unique_hosts' => 0,
        'countries_count' => 0,
        'unique_users' => 0,
        'countries' => [],
        'manual_sites_count' => 0,
        'pending_review' => 0,
        'pending_domains_outside_lists' => 0,
        'accepted_reviews' => 0,
        'rejected_reviews' => 0,
        'reviewed_total' => 0,
        'review_coverage_pct' => 0.0,
        'block_rate_24h' => 0.0,
        'high_risk_24h' => 0,
        'new_domains_24h' => 0,
        'active_extension_clients_24h' => 0,
    ];

    $row = $pdo->query("SELECT COUNT(*) total_alerts, SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) total_blocks, COUNT(DISTINCT hostname) unique_hosts, SUM(CASE WHEN review_status IS NULL OR review_status = 'pending' THEN 1 ELSE 0 END) pending_review_total, SUM(CASE WHEN review_status = 'accepted' THEN 1 ELSE 0 END) accepted_reviews, SUM(CASE WHEN review_status = 'rejected' THEN 1 ELSE 0 END) rejected_reviews, SUM(CASE WHEN review_status = 'allowlisted' THEN 1 ELSE 0 END) allowlisted_reviews, MAX(received_at) last_update FROM reports")->fetch() ?: [];
    $stats['total_alerts'] = (int) ($row['total_alerts'] ?? 0);
    $stats['total_blocks'] = (int) ($row['total_blocks'] ?? 0);
    $stats['unique_hosts'] = (int) ($row['unique_hosts'] ?? 0);
    $stats['pending_review_total'] = (int) ($row['pending_review_total'] ?? 0);
    $stats['accepted_reviews'] = (int) ($row['accepted_reviews'] ?? 0);
    $stats['rejected_reviews'] = (int) ($row['rejected_reviews'] ?? 0);
    $stats['allowlisted_reviews'] = (int) ($row['allowlisted_reviews'] ?? 0);
    if (!empty($row['last_update'])) {
        $stats['last_update'] = (string) $row['last_update'];
    }
    $allowlist = clickfix_load_list_file('allowlist');
    $blocklist = clickfix_load_list_file('blocklist');
    $pendingOutside = clickfix_pending_alerts_outside_lists($pdo, $allowlist, $blocklist);
    $stats['pending_review'] = (int) ($pendingOutside['alerts'] ?? 0);
    $stats['pending_domains_outside_lists'] = (int) ($pendingOutside['domains'] ?? 0);

    $cutoff = gmdate('c', time() - 86400);
    $day = $pdo->prepare('SELECT COUNT(*) alerts_24h, SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) blocks_24h, SUM(CASE WHEN score_total >= 80 THEN 1 ELSE 0 END) high_risk_24h FROM reports WHERE received_at >= :cutoff');
    $day->execute([':cutoff' => $cutoff]);
    $dayRow = $day->fetch() ?: [];
    $stats['alerts_24h'] = (int) ($dayRow['alerts_24h'] ?? 0);
    $stats['blocks_24h'] = (int) ($dayRow['blocks_24h'] ?? 0);
    $stats['high_risk_24h'] = (int) ($dayRow['high_risk_24h'] ?? 0);
    if ($stats['alerts_24h'] > 0) {
        $stats['block_rate_24h'] = round(($stats['blocks_24h'] / $stats['alerts_24h']) * 100, 2);
    }

    $uq = $pdo->prepare("SELECT COUNT(DISTINCT COALESCE(NULLIF(client_id, ''), user_agent, 'unknown')) users FROM stats WHERE received_at >= :cutoff");
    $uq->execute([':cutoff' => $cutoff]);
    $stats['unique_users'] = (int) ($uq->fetchColumn() ?: 0);

    $clientQ = $pdo->prepare("SELECT COUNT(DISTINCT client_id) FROM stats WHERE received_at >= :cutoff AND client_id IS NOT NULL AND client_id != ''");
    $clientQ->execute([':cutoff' => $cutoff]);
    $stats['active_extension_clients_24h'] = (int) ($clientQ->fetchColumn() ?: 0);

    $newDomainsQ = $pdo->prepare(
        "SELECT COUNT(*) FROM (
            SELECT hostname
            FROM reports
            WHERE hostname IS NOT NULL AND hostname != ''
            GROUP BY hostname
            HAVING MIN(received_at) >= :cutoff
        )"
    );
    $newDomainsQ->execute([':cutoff' => $cutoff]);
    $stats['new_domains_24h'] = (int) ($newDomainsQ->fetchColumn() ?: 0);

    $countries = $pdo->query("SELECT country, COUNT(*) hits FROM reports WHERE country IS NOT NULL AND country != '' GROUP BY country ORDER BY hits DESC LIMIT 8")->fetchAll();
    foreach ($countries as $country) {
        $stats['countries'][] = ['country' => (string) ($country['country'] ?? ''), 'hits' => (int) ($country['hits'] ?? 0)];
    }
    $stats['countries_count'] = (int) ($pdo->query("SELECT COUNT(DISTINCT country) FROM reports WHERE country IS NOT NULL AND country != ''")->fetchColumn() ?: 0);

    $manualRows = $pdo->query("SELECT manual_sites_json FROM stats WHERE manual_sites_json IS NOT NULL AND manual_sites_json != '' ORDER BY received_at DESC LIMIT 120")->fetchAll();
    $manualSet = [];
    foreach ($manualRows as $manualRow) {
        $decoded = json_decode((string) ($manualRow['manual_sites_json'] ?? '[]'), true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach ($decoded as $site) {
            $site = strtolower(trim((string) $site));
            if ($site !== '') {
                $manualSet[$site] = true;
            }
        }
    }
    $stats['manual_sites_count'] = count($manualSet);
    $stats['reviewed_total'] = $stats['accepted_reviews'] + $stats['rejected_reviews'] + $stats['allowlisted_reviews'];
    if ($stats['total_alerts'] > 0) {
        $stats['review_coverage_pct'] = round(($stats['reviewed_total'] / $stats['total_alerts']) * 100, 2);
    }
    clickfix_cache_set($cacheKey, $stats, 5);
    return $stats;
}

function clickfix_public_preview_payload(PDO $pdo, int $days = 14, int $recentLimit = 8): array
{
    $days = max(7, min(60, $days));
    $recentLimit = max(1, min(30, $recentLimit));
    $previewSettings = clickfix_public_preview_settings($pdo);
    $cacheKey = clickfix_cache_key('public_preview_payload', [
        'days' => $days,
        'recent' => $recentLimit,
        'limit_enabled' => !empty($previewSettings['limit_points_per_country']) ? 1 : 0,
        'limit_max' => (int) ($previewSettings['max_points_per_country'] ?? 2),
        'v3' => true,
    ]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $overview = clickfix_analytics_overview($pdo, $days);
    $labels = array_values(array_map('strval', (array) ($overview['labels'] ?? [])));
    $alerts = array_values(array_map('intval', (array) ($overview['alerts'] ?? [])));
    $blocks = array_values(array_map('intval', (array) ($overview['blocks'] ?? [])));

    $recentDomains = [];
    try {
        $allowlist = clickfix_load_list_file('allowlist');
        $sampleLimit = max(80, $recentLimit * 25);
        $stmt = $pdo->prepare(
            "SELECT hostname,
                    COALESCE(NULLIF(last_seen, ''), received_at) AS activity_at
             FROM reports
             WHERE hostname IS NOT NULL
               AND hostname != ''
             ORDER BY COALESCE(NULLIF(last_seen, ''), received_at) DESC, id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit', $sampleLimit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $seen = [];
        foreach ($rows as $row) {
            $hostname = clickfix_normalize_domain((string) ($row['hostname'] ?? ''));
            if ($hostname === '' || isset($seen[$hostname])) {
                continue;
            }
            if (clickfix_domain_in_list($hostname, $allowlist)) {
                continue;
            }
            $seen[$hostname] = true;
            $recentDomains[] = [
                'domain' => $hostname,
                'date' => (string) ($row['activity_at'] ?? ''),
            ];
            if (count($recentDomains) >= $recentLimit) {
                break;
            }
        }
    } catch (Throwable $exception) {
        $recentDomains = [];
    }

    $geoPoints = [];
    try {
        $remoteGeoBudget = 5;
        $geoStmt = $pdo->prepare(
            "SELECT UPPER(TRIM(country)) AS country_code,
                    COUNT(*) AS hits
             FROM reports
             WHERE country IS NOT NULL
               AND TRIM(country) != ''
             GROUP BY UPPER(TRIM(country))
             ORDER BY hits DESC
             LIMIT 24"
        );
        $geoStmt->execute();
        foreach ($geoStmt->fetchAll() as $geoRow) {
            $countryCode = strtoupper(substr((string) ($geoRow['country_code'] ?? ''), 0, 2));
            if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
                continue;
            }
            $hits = (int) ($geoRow['hits'] ?? 0);
            if ($hits <= 0) {
                continue;
            }
            $countryInfo = clickfix_country_geo_lookup($pdo, $countryCode, $remoteGeoBudget > 0);
            if ($remoteGeoBudget > 0) {
                $remoteGeoBudget--;
            }
            if (!is_array($countryInfo)) {
                continue;
            }
            $lat = isset($countryInfo['lat']) ? (float) $countryInfo['lat'] : 0.0;
            $lon = isset($countryInfo['lon']) ? (float) $countryInfo['lon'] : 0.0;
            if (!is_finite($lat) || !is_finite($lon) || (abs($lat) < 0.01 && abs($lon) < 0.01)) {
                continue;
            }
            $geoPoints[] = [
                'country_code' => $countryCode,
                'country_name' => (string) ($countryInfo['country_name'] ?? $countryCode),
                'hits' => $hits,
                'lat' => $lat,
                'lon' => $lon,
            ];
        }
    } catch (Throwable $exception) {
        $geoPoints = [];
    }

    $domainGeoPoints = [];
    try {
        if (clickfix_has_table($pdo, 'domain_intel_cache')) {
            $domainStmt = $pdo->prepare(
                "SELECT d.hostname,
                        h.hits,
                        d.country_code,
                        d.country_name,
                        d.latitude,
                        d.longitude
                 FROM domain_intel_cache d
                 JOIN (
                    SELECT LOWER(TRIM(hostname)) AS host_key, COUNT(*) AS hits
                    FROM reports
                    WHERE hostname IS NOT NULL
                      AND TRIM(hostname) != ''
                    GROUP BY LOWER(TRIM(hostname))
                 ) h ON h.host_key = LOWER(TRIM(d.hostname))
                 WHERE d.latitude IS NOT NULL
                   AND d.longitude IS NOT NULL
                   AND (ABS(d.latitude) > 0.01 OR ABS(d.longitude) > 0.01)
                 ORDER BY h.hits DESC, d.checked_at DESC
                 LIMIT 28"
            );
            $domainStmt->execute();
            foreach ($domainStmt->fetchAll() as $domainRow) {
                $domainHostname = clickfix_normalize_domain((string) ($domainRow['hostname'] ?? ''));
                if ($domainHostname !== '' && clickfix_domain_in_list($domainHostname, $allowlist)) {
                    continue;
                }
                $lat = isset($domainRow['latitude']) ? (float) $domainRow['latitude'] : 0.0;
                $lon = isset($domainRow['longitude']) ? (float) $domainRow['longitude'] : 0.0;
                if (!is_finite($lat) || !is_finite($lon) || (abs($lat) < 0.01 && abs($lon) < 0.01)) {
                    continue;
                }
                $domainGeoPoints[] = [
                    'hostname' => clickfix_normalize_domain((string) ($domainRow['hostname'] ?? '')),
                    'country_code' => strtoupper(substr((string) ($domainRow['country_code'] ?? ''), 0, 2)),
                    'country_name' => (string) ($domainRow['country_name'] ?? ''),
                    'hits' => (int) ($domainRow['hits'] ?? 0),
                    'lat' => $lat,
                    'lon' => $lon,
                ];
            }
        }
    } catch (Throwable $exception) {
        $domainGeoPoints = [];
    }

    $domainGeoPoints = clickfix_limit_geo_points_per_country(
        $domainGeoPoints,
        !empty($previewSettings['limit_points_per_country']),
        (int) ($previewSettings['max_points_per_country'] ?? 2)
    );

    $payload = [
        'charts' => [
            'daily' => [
                'labels' => $labels,
                'values' => $alerts,
            ],
            'dailyBlocks' => [
                'labels' => $labels,
                'values' => $blocks,
            ],
        ],
        'recent_domains' => $recentDomains,
        'geo_points' => $geoPoints,
        'geo_points_alerts' => $geoPoints,
        'geo_points_domains' => $domainGeoPoints,
        'map_settings' => [
            'limit_points_per_country' => !empty($previewSettings['limit_points_per_country']),
            'max_points_per_country' => (int) ($previewSettings['max_points_per_country'] ?? 2),
        ],
    ];

    clickfix_cache_set($cacheKey, $payload, 20);
    return $payload;
}

function clickfix_sigmoid(float $value): float
{
    if ($value < -20.0) {
        return 0.0;
    }
    if ($value > 20.0) {
        return 1.0;
    }
    return 1.0 / (1.0 + exp(-$value));
}

function clickfix_recent_reports(PDO $pdo, int $limit = 25, ?string $search = null): array
{
    $limit = max(1, min($limit, 200));
    $searchTerm = $search !== null ? trim($search) : '';
    $pendingOnlyClause = "(review_status IS NULL OR TRIM(review_status) = '' OR LOWER(TRIM(review_status)) = 'pending')";
    if ($searchTerm === '') {
        $cacheKey = clickfix_cache_key('recent_reports', ['limit' => $limit, 'v3' => true]);
        $cached = clickfix_cache_get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }
    }
    if ($searchTerm !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, received_at, last_seen, hostname, url, previous_url, message, blocked, review_status, duplicate_count, score_total, score_details_json, country, detected_content, full_context, signals_json, reason_entries_json, matched_snippets_json, event_type, user_agent, ip, client_id
             FROM reports
             WHERE (hostname LIKE :term OR url LIKE :term OR message LIKE :term)
               AND $pendingOnlyClause
             ORDER BY COALESCE(NULLIF(last_seen, ''), received_at) DESC, id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':term', '%' . $searchTerm . '%', PDO::PARAM_STR);
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, received_at, last_seen, hostname, url, previous_url, message, blocked, review_status, duplicate_count, score_total, score_details_json, country, detected_content, full_context, signals_json, reason_entries_json, matched_snippets_json, event_type, user_agent, ip, client_id
             FROM reports
             WHERE (review_status IS NULL OR TRIM(review_status) = \'\' OR LOWER(TRIM(review_status)) = \'pending\')
             ORDER BY COALESCE(NULLIF(last_seen, \'\'), received_at) DESC, id DESC
             LIMIT :limit'
        );
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $enriched = clickfix_enrich_report_rows($rows);
    if ($searchTerm === '') {
        clickfix_cache_set($cacheKey, $enriched, 4);
    }
    return $enriched;
}

function clickfix_enrich_report_rows(array $rows): array
{
    foreach ($rows as &$row) {
        $reasonEntries = json_decode((string) ($row['reason_entries_json'] ?? '[]'), true);
        $row['reason_entries'] = is_array($reasonEntries) ? $reasonEntries : [];
        $signals = json_decode((string) ($row['signals_json'] ?? '[]'), true);
        $row['signals'] = is_array($signals) ? $signals : [];
        $snippets = json_decode((string) ($row['matched_snippets_json'] ?? '[]'), true);
        $row['matched_snippets'] = is_array($snippets) ? $snippets : [];
        $scoreDetails = json_decode((string) ($row['score_details_json'] ?? 'null'), true);
        $row['score_details'] = is_array($scoreDetails) ? $scoreDetails : null;
        $ua = (string) ($row['user_agent'] ?? '');
        $row['user_agent'] = $ua;
        $row['extension_version'] = clickfix_extract_extension_version($ua);
    }
    unset($row);
    return $rows;
}

function clickfix_filtered_reports(PDO $pdo, array $filters = [], int $limit = 120): array
{
    $limit = max(1, min(300, $limit));
    $domain = trim((string) ($filters['domain'] ?? ''));
    $command = trim((string) ($filters['command'] ?? ''));
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    $dateTo = trim((string) ($filters['date_to'] ?? ''));

    $sql = "SELECT id, received_at, last_seen, hostname, url, previous_url, message, blocked, review_status, duplicate_count, score_total, score_details_json, country, detected_content, full_context, signals_json, reason_entries_json, matched_snippets_json, event_type, user_agent, ip, client_id FROM reports WHERE 1=1";
    $params = [];

    if ($domain !== '') {
        $sql .= " AND (hostname LIKE :domain OR url LIKE :domain)";
        $params[':domain'] = '%' . $domain . '%';
    }
    if ($command !== '') {
        $sql .= " AND (message LIKE :command OR detected_content LIKE :command OR full_context LIKE :command)";
        $params[':command'] = '%' . $command . '%';
    }
    if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $sql .= " AND received_at >= :date_from";
        $params[':date_from'] = $dateFrom . 'T00:00:00Z';
    }
    if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $sql .= " AND received_at <= :date_to";
        $params[':date_to'] = $dateTo . 'T23:59:59Z';
    }
    $sql .= " ORDER BY COALESCE(NULLIF(last_seen, ''), received_at) DESC, id DESC LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    return clickfix_enrich_report_rows($rows);
}

function clickfix_report_by_id(PDO $pdo, int $reportId): ?array
{
    if ($reportId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT id, received_at, hostname, url, previous_url, message, blocked, review_status, duplicate_count, score_total, score_details_json, country, detected_content, full_context, signals_json, reason_entries_json, matched_snippets_json, event_type, user_agent, ip, client_id
         FROM reports
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $reportId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $enriched = clickfix_enrich_report_rows([$row]);
    return $enriched[0] ?? null;
}

function clickfix_hostname_web_ip(PDO $pdo, string $hostname, bool $allowDnsFallback = true): string
{
    static $memo = [];

    $host = clickfix_normalize_domain($hostname);
    if ($host === '') {
        return '';
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return $host;
    }

    $memoKey = $host . '|' . ($allowDnsFallback ? 'dns1' : 'dns0');
    if (isset($memo[$memoKey])) {
        return $memo[$memoKey];
    }

    $candidate = '';

    if (clickfix_has_table($pdo, 'domain_intel_cache')) {
        $stmt = $pdo->prepare('SELECT ip FROM domain_intel_cache WHERE hostname = :hostname LIMIT 1');
        $stmt->execute([':hostname' => $host]);
        $candidate = trim((string) ($stmt->fetchColumn() ?: ''));
    }
    if (($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_IP)) && clickfix_has_table($pdo, 'whatweb_cache')) {
        $stmt = $pdo->prepare('SELECT ip FROM whatweb_cache WHERE hostname = :hostname LIMIT 1');
        $stmt->execute([':hostname' => $host]);
        $candidate = trim((string) ($stmt->fetchColumn() ?: ''));
    }
    if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_IP)) {
        $candidate = $allowDnsFallback ? clickfix_resolve_host_to_ip($host) : '';
    }
    if ($candidate !== '' && !filter_var($candidate, FILTER_VALIDATE_IP)) {
        $candidate = '';
    }

    $memo[$memoKey] = $candidate;
    return $candidate;
}

function clickfix_related_reports(PDO $pdo, int $reportId, string $hostname = '', string $ip = '', int $limit = 30): array
{
    if ($reportId <= 0) {
        return [];
    }
    $limit = max(1, min(120, $limit));
    $sourceReport = clickfix_report_by_id($pdo, $reportId);
    $sourceReasons = [];
    $sourceSignals = [];
    $sourceSnippets = [];
    if (is_array($sourceReport)) {
        $sourceReasons = array_map('strtolower', array_filter(array_map('trim', (array) ($sourceReport['reason_list'] ?? []))));
        $sourceSignals = array_map('strtolower', array_filter(array_map('trim', (array) ($sourceReport['signals'] ?? []))));
        $sourceSnippets = array_map('strtolower', array_filter(array_map('trim', (array) ($sourceReport['snippets'] ?? []))));
    }
    $normalizedHost = clickfix_normalize_domain($hostname);
    $normalizedIp = trim($ip);
    if ($normalizedIp !== '' && !filter_var($normalizedIp, FILTER_VALIDATE_IP)) {
        $normalizedIp = '';
    }
    $hasDomainIntelCache = clickfix_has_table($pdo, 'domain_intel_cache');
    $hasWhatwebCache = clickfix_has_table($pdo, 'whatweb_cache');

    $clauses = [];
    $params = [
        ':report_id' => $reportId,
    ];
    if ($normalizedHost !== '') {
        $clauses[] = 'LOWER(TRIM(COALESCE(r.hostname, \'\'))) = :hostname';
        $params[':hostname'] = $normalizedHost;
    }
    if ($normalizedIp !== '') {
        $ipClauses = [];
        $usesWebIpParam = false;
        if ($hasDomainIntelCache) {
            $ipClauses[] = 'TRIM(COALESCE(dic.ip, \'\')) = :web_ip';
            $usesWebIpParam = true;
        }
        if ($hasWhatwebCache) {
            $ipClauses[] = 'TRIM(COALESCE(wwc.ip, \'\')) = :web_ip';
            $usesWebIpParam = true;
        }
        $ipClauses[] = 'LOWER(TRIM(COALESCE(r.hostname, \'\'))) = :web_ip_host';
        if (!empty($ipClauses)) {
            $clauses[] = '(' . implode(' OR ', $ipClauses) . ')';
            if ($usesWebIpParam) {
                $params[':web_ip'] = $normalizedIp;
            }
            $params[':web_ip_host'] = strtolower($normalizedIp);
        }
    }
    $hasSourceSignals = !empty($sourceSignals) || !empty($sourceReasons) || !empty($sourceSnippets);
    if (empty($clauses) && !$hasSourceSignals) {
        return [];
    }

    $selectFields = 'r.id, r.received_at, r.last_seen, r.hostname, r.url, r.previous_url, r.message, r.blocked, r.review_status, r.duplicate_count, r.score_total, r.score_details_json, r.country, r.detected_content, r.full_context, r.signals_json, r.reason_entries_json, r.matched_snippets_json, r.event_type, r.user_agent, r.ip, r.client_id';
    $joins = '';
    if ($hasDomainIntelCache) {
        $selectFields .= ', TRIM(COALESCE(dic.ip, \'\')) AS cache_domain_intel_ip';
        $joins .= ' LEFT JOIN domain_intel_cache dic ON LOWER(TRIM(COALESCE(dic.hostname, \'\'))) = LOWER(TRIM(COALESCE(r.hostname, \'\')))';
    }
    if ($hasWhatwebCache) {
        $selectFields .= ', TRIM(COALESCE(wwc.ip, \'\')) AS cache_whatweb_ip';
        $joins .= ' LEFT JOIN whatweb_cache wwc ON LOWER(TRIM(COALESCE(wwc.hostname, \'\'))) = LOWER(TRIM(COALESCE(r.hostname, \'\')))';
    }
    $queryLimit = max($limit * 6, 120);
    $queryLimit = min($queryLimit, 500);

    $whereSql = '';
    if (!empty($clauses)) {
        $whereSql = ' AND (' . implode(' OR ', $clauses) . ')';
    }
    $sql = 'SELECT ' . $selectFields . '
            FROM reports r' . $joins . '
            WHERE r.id != :report_id' . $whereSql . '
            ORDER BY COALESCE(NULLIF(r.last_seen, \'\'), r.received_at) DESC, r.id DESC
            LIMIT :query_limit';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $stmt->bindValue(':report_id', $reportId, PDO::PARAM_INT);
    $stmt->bindValue(':query_limit', $queryLimit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = clickfix_enrich_report_rows($stmt->fetchAll());

    $filteredRows = [];
    foreach ($rows as &$row) {
        $rowReasons = array_map('strtolower', array_filter(array_map('trim', (array) ($row['reason_list'] ?? []))));
        $rowSignals = array_map('strtolower', array_filter(array_map('trim', (array) ($row['signals'] ?? []))));
        $rowSnippets = array_map('strtolower', array_filter(array_map('trim', (array) ($row['snippets'] ?? []))));
        $sharedReasons = array_values(array_intersect($sourceReasons, $rowReasons));
        $sharedSignals = array_values(array_intersect($sourceSignals, $rowSignals));
        $sharedSnippets = array_values(array_intersect($sourceSnippets, $rowSnippets));

        $rowHost = clickfix_normalize_domain((string) ($row['hostname'] ?? ''));
        $rowWebIp = trim((string) (($row['cache_domain_intel_ip'] ?? '') ?: ($row['cache_whatweb_ip'] ?? '')));
        if (!filter_var($rowWebIp, FILTER_VALIDATE_IP)) {
            $rowWebIp = '';
        }
        if ($rowWebIp === '' && filter_var($rowHost, FILTER_VALIDATE_IP)) {
            $rowWebIp = $rowHost;
        }
        if ($rowWebIp === '' && $rowHost !== '' && $rowHost === $normalizedHost) {
            $rowWebIp = clickfix_hostname_web_ip($pdo, $rowHost, true);
        }
        $row['web_ip'] = $rowWebIp;
        $row['related_by_domain'] = $normalizedHost !== '' && $rowHost === $normalizedHost;
        $row['related_by_ip'] = $normalizedIp !== '' && $rowWebIp !== '' && $rowWebIp === $normalizedIp;
        $row['related_by_ttp'] = !empty($sharedReasons) || !empty($sharedSignals);
        $row['related_by_snippet'] = !empty($sharedSnippets);
        $row['shared_reasons'] = $sharedReasons;
        $row['shared_signals'] = $sharedSignals;
        $row['shared_snippets'] = $sharedSnippets;
        if (
            !$row['related_by_domain']
            && !$row['related_by_ip']
            && !$row['related_by_ttp']
            && !$row['related_by_snippet']
        ) {
            continue;
        }
        $filteredRows[] = $row;
        if (count($filteredRows) >= $limit) {
            break;
        }
    }
    unset($row);

    return $filteredRows;
}

function clickfix_report_blocked_history(PDO $pdo, array $hostnames = [], array $ips = []): array
{
    $result = [
        'hostnames' => [],
        'ips' => [],
    ];

    $normalizedHosts = [];
    foreach ($hostnames as $hostname) {
        $normalized = clickfix_normalize_domain((string) $hostname);
        if ($normalized !== '') {
            $normalizedHosts[$normalized] = true;
        }
    }
    $normalizedHosts = array_keys($normalizedHosts);

    $normalizedIps = [];
    foreach ($ips as $ip) {
        $candidate = trim((string) $ip);
        if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_IP)) {
            continue;
        }
        $normalizedIps[$candidate] = true;
    }
    $normalizedIps = array_keys($normalizedIps);

    if (!empty($normalizedHosts)) {
        foreach (array_chunk($normalizedHosts, 120) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '?'));
            $sql = "SELECT LOWER(TRIM(hostname)) AS lookup_key,
                           COUNT(*) AS total_count,
                           SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) AS blocked_count,
                           MAX(CASE WHEN blocked = 1 THEN COALESCE(NULLIF(last_seen, ''), received_at) END) AS last_blocked_at
                    FROM reports
                    WHERE hostname IS NOT NULL
                      AND hostname != ''
                      AND LOWER(TRIM(hostname)) IN ($placeholders)
                    GROUP BY LOWER(TRIM(hostname))";
            $stmt = $pdo->prepare($sql);
            foreach (array_values($chunk) as $idx => $value) {
                $stmt->bindValue($idx + 1, strtolower($value), PDO::PARAM_STR);
            }
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $key = strtolower(trim((string) ($row['lookup_key'] ?? '')));
                if ($key === '') {
                    continue;
                }
                $result['hostnames'][$key] = [
                    'total_count' => (int) ($row['total_count'] ?? 0),
                    'blocked_count' => (int) ($row['blocked_count'] ?? 0),
                    'last_blocked_at' => (string) ($row['last_blocked_at'] ?? ''),
                ];
            }
        }
    }

    if (!empty($normalizedIps)) {
        foreach (array_chunk($normalizedIps, 120) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '?'));
            $sql = "SELECT TRIM(ip) AS lookup_key,
                           COUNT(*) AS total_count,
                           SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) AS blocked_count,
                           MAX(CASE WHEN blocked = 1 THEN COALESCE(NULLIF(last_seen, ''), received_at) END) AS last_blocked_at
                    FROM reports
                    WHERE ip IS NOT NULL
                      AND ip != ''
                      AND TRIM(ip) IN ($placeholders)
                    GROUP BY TRIM(ip)";
            $stmt = $pdo->prepare($sql);
            foreach (array_values($chunk) as $idx => $value) {
                $stmt->bindValue($idx + 1, $value, PDO::PARAM_STR);
            }
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $key = trim((string) ($row['lookup_key'] ?? ''));
                if ($key === '') {
                    continue;
                }
                $result['ips'][$key] = [
                    'total_count' => (int) ($row['total_count'] ?? 0),
                    'blocked_count' => (int) ($row['blocked_count'] ?? 0),
                    'last_blocked_at' => (string) ($row['last_blocked_at'] ?? ''),
                ];
            }
        }
    }

    return $result;
}

function clickfix_update_report_review(PDO $pdo, int $reportId, string $status, int $reviewerId): bool
{
    $allowed = ['pending', 'accepted', 'rejected', 'allowlisted'];
    if ($reportId <= 0 || !in_array($status, $allowed, true)) {
        return false;
    }
    $stmt = $pdo->prepare(
        "UPDATE reports
         SET review_status = :status,
             accepted = CASE WHEN :status = 'accepted' THEN 1 ELSE 0 END,
             reviewed_by = :uid,
             reviewed_at = :at,
             accepted_by = CASE WHEN :status = 'accepted' THEN :uid ELSE accepted_by END,
             accepted_at = CASE WHEN :status = 'accepted' THEN :at ELSE accepted_at END
         WHERE id = :id"
    );
    if (!$stmt->execute([':status' => $status, ':uid' => $reviewerId, ':at' => gmdate('c'), ':id' => $reportId])) {
        return false;
    }
    $updated = $stmt->rowCount() > 0;
    if ($updated && $status === 'accepted') {
        try {
            $report = clickfix_report_by_id($pdo, $reportId);
            if (is_array($report)) {
                $domain = clickfix_normalize_domain((string) ($report['hostname'] ?? ''));
                if ($domain === '') {
                    $domain = clickfix_normalize_domain((string) ($report['url'] ?? ''));
                }
                if ($domain !== '') {
                    clickfix_baseline_record_review($pdo, (string) ($report['client_id'] ?? ''), $domain, $status);
                    clickfix_apply_list_action(
                        $pdo,
                        $reviewerId,
                        'blocklist',
                        'add',
                        $domain,
                        'auto block after accepted malicious review #' . $reportId
                    );
                }
                clickfix_set_report_blocked($pdo, $reportId, true);
            }
        } catch (Throwable $exception) {
            // Keep the review result even if the automatic block action fails.
        }
    } elseif ($updated && $status === 'allowlisted') {
        try {
            $report = clickfix_report_by_id($pdo, $reportId);
            if (is_array($report)) {
                $domain = clickfix_normalize_domain((string) ($report['hostname'] ?? ''));
                if ($domain === '') {
                    $domain = clickfix_normalize_domain((string) ($report['url'] ?? ''));
                }
                if ($domain !== '') {
                    clickfix_baseline_record_review($pdo, (string) ($report['client_id'] ?? ''), $domain, $status);
                    clickfix_apply_list_action(
                        $pdo,
                        $reviewerId,
                        'allowlist',
                        'add',
                        $domain,
                        'auto allowlist after review #' . $reportId
                    );
                }
                clickfix_set_report_blocked($pdo, $reportId, false);
            }
        } catch (Throwable $exception) {
            // Keep the review result even if the automatic allowlist action fails.
        }
    } elseif ($updated && $status === 'rejected') {
        try {
            $report = clickfix_report_by_id($pdo, $reportId);
            if (is_array($report)) {
                $domain = clickfix_normalize_domain((string) ($report['hostname'] ?? ''));
                if ($domain === '') {
                    $domain = clickfix_normalize_domain((string) ($report['url'] ?? ''));
                }
                if ($domain !== '') {
                    clickfix_baseline_record_review($pdo, (string) ($report['client_id'] ?? ''), $domain, $status);
                }
            }
        } catch (Throwable $exception) {
            // Keep the review result even if the baseline update fails.
        }
    }
    return $updated;
}

function clickfix_set_report_blocked(PDO $pdo, int $reportId, bool $blocked = true): bool
{
    if ($reportId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE reports SET blocked = :blocked WHERE id = :id');
    if (!$stmt->execute([
        ':blocked' => $blocked ? 1 : 0,
        ':id' => $reportId,
    ])) {
        return false;
    }
    return $stmt->rowCount() > 0;
}

function clickfix_delete_report(PDO $pdo, int $reportId): bool
{
    $reportId = (int) $reportId;
    if ($reportId <= 0) {
        return false;
    }

    $startedTransaction = false;
    $deleted = false;
    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $startedTransaction = true;
        }

        $cleanupStmt = $pdo->prepare('DELETE FROM scan_image_reviews WHERE report_id = :id');
        $cleanupStmt->execute([':id' => $reportId]);

        $deleteStmt = $pdo->prepare('DELETE FROM reports WHERE id = :id');
        $deleteStmt->execute([':id' => $reportId]);
        $deleted = $deleteStmt->rowCount() > 0;

        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }

    if (!$deleted) {
        return false;
    }

    foreach (['before', 'after'] as $kind) {
        $path = clickfix_scan_asset_absolute_path($reportId, $kind);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    return true;
}

function clickfix_delete_domain_cache_rows(PDO $pdo, string $hostname, bool $includeSubdomains = true): int
{
    $hostname = clickfix_normalize_domain($hostname);
    if ($hostname === '') {
        return 0;
    }

    $tables = ['domain_intel_cache', 'whatweb_cache'];
    $deleted = 0;
    foreach ($tables as $table) {
        if (!clickfix_has_table($pdo, $table)) {
            continue;
        }
        $clauses = ['LOWER(TRIM(hostname)) = :host'];
        $params = [':host' => $hostname];
        if ($includeSubdomains) {
            $clauses[] = 'LOWER(TRIM(hostname)) LIKE :sub';
            $params[':sub'] = '%.' . $hostname;
        }
        $sql = 'DELETE FROM ' . $table . ' WHERE ' . implode(' OR ', $clauses);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $deleted += $stmt->rowCount();
    }
    return $deleted;
}

function clickfix_delete_investigations_by_domain(PDO $pdo, string $hostname, bool $includeSubdomains = true): int
{
    if (!clickfix_has_table($pdo, 'investigation_graphs')) {
        return 0;
    }
    $hostname = clickfix_normalize_domain($hostname);
    if ($hostname === '') {
        return 0;
    }
    $clauses = ['LOWER(TRIM(COALESCE(site_domain, \'\'))) = :host'];
    $params = [':host' => $hostname];
    if ($includeSubdomains) {
        $clauses[] = 'LOWER(TRIM(COALESCE(site_domain, \'\'))) LIKE :sub';
        $params[':sub'] = '%.' . $hostname;
    }
    $stmt = $pdo->prepare('DELETE FROM investigation_graphs WHERE ' . implode(' OR ', $clauses));
    $stmt->execute($params);
    return $stmt->rowCount();
}

function clickfix_delete_reports_by_domain(PDO $pdo, string $domain, array $options = []): array
{
    $host = clickfix_normalize_domain($domain);
    if ($host === '') {
        return [
            'host' => '',
            'matched' => 0,
            'deleted' => 0,
            'failed' => 0,
            'cache_deleted' => 0,
            'investigations_deleted' => 0,
        ];
    }

    $includeSubdomains = array_key_exists('include_subdomains', $options) ? (bool) $options['include_subdomains'] : true;
    $includeUrl = array_key_exists('include_url', $options) ? (bool) $options['include_url'] : true;
    $includePreviousUrl = array_key_exists('include_previous_url', $options) ? (bool) $options['include_previous_url'] : true;
    $deleteCaches = array_key_exists('delete_caches', $options) ? (bool) $options['delete_caches'] : true;
    $deleteInvestigations = array_key_exists('delete_investigations', $options) ? (bool) $options['delete_investigations'] : false;

    $clauses = ['LOWER(TRIM(COALESCE(hostname, \'\'))) = :host'];
    $params = [':host' => $host];
    if ($includeSubdomains) {
        $clauses[] = 'LOWER(TRIM(COALESCE(hostname, \'\'))) LIKE :sub';
        $params[':sub'] = '%.' . $host;
    }
    if ($includeUrl) {
        $clauses[] = 'LOWER(COALESCE(url, \'\')) LIKE :url';
        $params[':url'] = '%' . $host . '%';
    }
    if ($includePreviousUrl) {
        $clauses[] = 'LOWER(COALESCE(previous_url, \'\')) LIKE :purl';
        $params[':purl'] = '%' . $host . '%';
    }

    $stmt = $pdo->prepare('SELECT id FROM reports WHERE ' . implode(' OR ', $clauses));
    $stmt->execute($params);
    $reportIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $matched = count($reportIds);
    $deleted = 0;
    $failed = 0;

    foreach ($reportIds as $reportId) {
        if (clickfix_delete_report($pdo, (int) $reportId)) {
            $deleted++;
        } else {
            $failed++;
        }
    }

    $cacheDeleted = 0;
    if ($deleteCaches) {
        $cacheDeleted = clickfix_delete_domain_cache_rows($pdo, $host, $includeSubdomains);
    }

    $investigationsDeleted = 0;
    if ($deleteInvestigations) {
        $investigationsDeleted = clickfix_delete_investigations_by_domain($pdo, $host, $includeSubdomains);
    }

    return [
        'host' => $host,
        'matched' => $matched,
        'deleted' => $deleted,
        'failed' => $failed,
        'cache_deleted' => $cacheDeleted,
        'investigations_deleted' => $investigationsDeleted,
    ];
}

function clickfix_normalize_domain(string $domain): string
{
    $domain = strtolower(trim($domain));
    $domain = preg_replace('/^https?:\/\//', '', $domain);
    $domain = preg_replace('/\/.*$/', '', (string) $domain);
    if (!is_string($domain) || $domain === '' || !preg_match('/^[a-z0-9.-]+$/', $domain)) {
        return '';
    }
    return substr($domain, 0, 255);
}

function clickfix_list_file_path(string $listType): string
{
    $base = dirname(__DIR__);
    if ($listType === 'allowlist') {
        return $base . '/clickfixallowlist';
    }
    if ($listType === 'alertlist') {
        return $base . '/alertsites';
    }
    if ($listType === 'investigatelist') {
        return $base . '/investigatesites';
    }
    return $base . '/clickfixlist';
}

function clickfix_list_file_paths(string $listType): array
{
    $primary = clickfix_list_file_path($listType);
    $paths = [$primary];

    $dataDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data');
    if ($dataDir !== '') {
        $paths[] = $dataDir . DIRECTORY_SEPARATOR . basename($primary);
    }

    $tmpBase = rtrim((string) sys_get_temp_dir(), "/\\");
    if ($tmpBase !== '') {
        $paths[] = $tmpBase . DIRECTORY_SEPARATOR . 'clickfix_' . basename($primary);
    }

    $normalized = [];
    foreach ($paths as $path) {
        $normalized[] = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
    return array_values(array_unique($normalized));
}

function clickfix_load_list_file(string $listType): array
{
    $items = [];
    foreach (clickfix_list_file_paths($listType) as $path) {
        if (!is_file($path)) {
            continue;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            $item = clickfix_normalize_domain((string) $line);
            if ($item !== '') {
                $items[$item] = true;
            }
        }
    }
    ksort($items);
    return array_keys($items);
}

function clickfix_hostname_matches_domain_entry(string $hostname, string $entry): bool
{
    $host = clickfix_normalize_domain($hostname);
    $item = clickfix_normalize_domain($entry);
    if ($host === '' || $item === '') {
        return false;
    }
    if ($host === $item) {
        return true;
    }
    $suffix = '.' . $item;
    if (strlen($host) <= strlen($suffix)) {
        return false;
    }
    return substr($host, -strlen($suffix)) === $suffix;
}

function clickfix_is_hostname_covered_by_lists(string $hostname, array $allowlist, array $blocklist): bool
{
    foreach ($allowlist as $entry) {
        if (clickfix_hostname_matches_domain_entry($hostname, (string) $entry)) {
            return true;
        }
    }
    foreach ($blocklist as $entry) {
        if (clickfix_hostname_matches_domain_entry($hostname, (string) $entry)) {
            return true;
        }
    }
    return false;
}

function clickfix_alert_domains_outside_lists(
    PDO $pdo,
    array $allowlist,
    array $blocklist,
    int $limit = 120
): array {
    $limit = max(1, min($limit, 500));
    $stmt = $pdo->query(
        'SELECT hostname, COUNT(*) AS alerts, MAX(received_at) AS last_seen
         FROM reports
         WHERE hostname IS NOT NULL AND hostname != \'\'
           AND (review_status IS NULL OR TRIM(review_status) = \'\' OR LOWER(TRIM(review_status)) = \'pending\')
         GROUP BY hostname
         ORDER BY alerts DESC, last_seen DESC'
    );

    $result = [];
    $seen = [];
    while (is_object($stmt) && ($row = $stmt->fetch())) {
        $hostname = clickfix_normalize_domain((string) ($row['hostname'] ?? ''));
        if ($hostname === '' || isset($seen[$hostname])) {
            continue;
        }
        $seen[$hostname] = true;
        if (clickfix_is_hostname_covered_by_lists($hostname, $allowlist, $blocklist)) {
            continue;
        }
        $result[] = [
            'hostname' => $hostname,
            'alerts' => (int) ($row['alerts'] ?? 0),
            'last_seen' => (string) ($row['last_seen'] ?? ''),
        ];
        if (count($result) >= $limit) {
            break;
        }
    }
    return $result;
}

function clickfix_pending_alerts_outside_lists(PDO $pdo, array $allowlist, array $blocklist): array
{
    $stmt = $pdo->query(
        'SELECT hostname, COUNT(*) AS alerts
         FROM reports
         WHERE hostname IS NOT NULL AND hostname != \'\'
           AND (review_status IS NULL OR TRIM(review_status) = \'\' OR LOWER(TRIM(review_status)) = \'pending\')
         GROUP BY hostname'
    );
    if (!is_object($stmt)) {
        return ['alerts' => 0, 'domains' => 0];
    }

    $alerts = 0;
    $domains = 0;
    $seen = [];
    while ($row = $stmt->fetch()) {
        $hostname = clickfix_normalize_domain((string) ($row['hostname'] ?? ''));
        if ($hostname === '' || isset($seen[$hostname])) {
            continue;
        }
        $seen[$hostname] = true;
        if (clickfix_is_hostname_covered_by_lists($hostname, $allowlist, $blocklist)) {
            continue;
        }
        $domains++;
        $alerts += (int) ($row['alerts'] ?? 0);
    }

    return ['alerts' => $alerts, 'domains' => $domains];
}

function clickfix_pending_reports_outside_lists(
    PDO $pdo,
    array $allowlist,
    array $blocklist,
    int $limit = 120
): array {
    $limit = max(1, min($limit, 500));
    $sampleLimit = max(300, min(8000, $limit * 35));
    $stmt = $pdo->prepare(
        "SELECT id, received_at, hostname, score_total, event_type, blocked, review_status, message
         FROM reports
         WHERE hostname IS NOT NULL
           AND hostname != ''
           AND (review_status IS NULL OR TRIM(review_status) = '' OR LOWER(TRIM(review_status)) = 'pending')
         ORDER BY received_at DESC
         LIMIT :sample_limit"
    );
    $stmt->bindValue(':sample_limit', $sampleLimit, PDO::PARAM_INT);
    $stmt->execute();

    $result = [];
    $seen = [];
    while ($row = $stmt->fetch()) {
        $hostname = clickfix_normalize_domain((string) ($row['hostname'] ?? ''));
        if ($hostname === '') {
            continue;
        }
        if (clickfix_is_hostname_covered_by_lists($hostname, $allowlist, $blocklist)) {
            continue;
        }
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0 || isset($seen[$id])) {
            continue;
        }
        $seen[$id] = true;
        $result[] = [
            'id' => $id,
            'received_at' => (string) ($row['received_at'] ?? ''),
            'hostname' => $hostname,
            'score_total' => (int) ($row['score_total'] ?? 0),
            'event_type' => (string) ($row['event_type'] ?? 'clickfix_alert'),
            'blocked' => !empty($row['blocked']),
            'review_status' => (string) ($row['review_status'] ?? 'pending'),
            'message' => substr(trim((string) ($row['message'] ?? '')), 0, 240),
        ];
        if (count($result) >= $limit) {
            break;
        }
    }

    return $result;
}

function clickfix_write_list_file(string $listType, array $items): bool
{
    $unique = [];
    foreach ($items as $item) {
        $domain = clickfix_normalize_domain((string) $item);
        if ($domain !== '') {
            $unique[$domain] = true;
        }
    }
    $payload = implode(PHP_EOL, array_keys($unique));
    if ($payload !== '') {
        $payload .= PHP_EOL;
    }
    foreach (clickfix_list_file_paths($listType) as $path) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!clickfix_can_write_path($path)) {
            continue;
        }
        $written = @file_put_contents($path, $payload, LOCK_EX);
        if ($written !== false) {
            return true;
        }
    }
    return false;
}

function clickfix_apply_list_action(PDO $pdo, int $userId, string $listType, string $operation, string $domain, string $reason): bool
{
    $listType = in_array($listType, ['blocklist', 'allowlist', 'alertlist', 'investigatelist'], true) ? $listType : 'blocklist';
    $operation = in_array($operation, ['add', 'remove'], true) ? $operation : 'add';
    $domain = clickfix_normalize_domain($domain);
    if ($domain === '') {
        return false;
    }

    $items = clickfix_load_list_file($listType);
    $set = [];
    foreach ($items as $item) {
        $set[$item] = true;
    }
    if ($operation === 'add') {
        $set[$domain] = true;
    } else {
        unset($set[$domain]);
    }
    if (!clickfix_write_list_file($listType, array_keys($set))) {
        return false;
    }

    $stmt = $pdo->prepare('INSERT INTO list_actions (created_at, user_id, action, list_type, domain, reason) VALUES (:at, :uid, :action, :list_type, :domain, :reason)');
    return $stmt->execute([
        ':at' => gmdate('c'),
        ':uid' => $userId,
        ':action' => $operation,
        ':list_type' => $listType,
        ':domain' => $domain,
        ':reason' => substr(trim($reason), 0, 300),
    ]);
}

function clickfix_parse_domain_batch(string $raw): array
{
    $domains = [];
    $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
    foreach ($parts as $part) {
        $domain = clickfix_normalize_domain((string) $part);
        if ($domain !== '' && !isset($domains[$domain])) {
            $domains[$domain] = true;
        }
    }
    return array_keys($domains);
}

function clickfix_apply_list_bulk_action(
    PDO $pdo,
    int $userId,
    string $listType,
    string $operation,
    string $domainsRaw,
    string $reason
): array {
    $domains = clickfix_parse_domain_batch($domainsRaw);
    $applied = 0;
    $errors = 0;
    foreach ($domains as $domain) {
        if (clickfix_apply_list_action($pdo, $userId, $listType, $operation, $domain, $reason)) {
            $applied++;
        } else {
            $errors++;
        }
    }
    return [
        'total' => count($domains),
        'applied' => $applied,
        'errors' => $errors,
    ];
}

function clickfix_recent_list_actions(PDO $pdo, int $limit = 20): array
{
    $limit = max(1, min($limit, 100));
    $cacheKey = clickfix_cache_key('recent_list_actions', ['limit' => $limit, 'v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }
    $stmt = $pdo->prepare('SELECT la.created_at, la.action, la.list_type, la.domain, la.reason, u.username FROM list_actions la LEFT JOIN users u ON la.user_id = u.id ORDER BY la.id DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    clickfix_cache_set($cacheKey, $rows, 8);
    return $rows;
}

function clickfix_submit_appeal(PDO $pdo, string $domain, string $reason, string $contact): bool
{
    $domain = clickfix_normalize_domain($domain);
    $reason = trim($reason);
    if ($domain === '' || $reason === '') {
        return false;
    }
    $stmt = $pdo->prepare('INSERT INTO appeals (created_at, domain, reason, contact, status) VALUES (:at, :domain, :reason, :contact, :status)');
    return $stmt->execute([
        ':at' => gmdate('c'),
        ':domain' => $domain,
        ':reason' => substr($reason, 0, 1200),
        ':contact' => substr(trim($contact), 0, 255),
        ':status' => 'pending',
    ]);
}

function clickfix_recent_appeals(PDO $pdo, int $limit = 20): array
{
    $limit = max(1, min($limit, 100));
    $cacheKey = clickfix_cache_key('recent_appeals', ['limit' => $limit, 'v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }
    $stmt = $pdo->prepare('SELECT id, created_at, domain, reason, contact, status FROM appeals ORDER BY id DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    clickfix_cache_set($cacheKey, $rows, 8);
    return $rows;
}

function clickfix_recent_delete_requests(PDO $pdo, int $limit = 30): array
{
    $limit = max(1, min($limit, 200));
    $stmt = $pdo->prepare(
        'SELECT id, received_at, hostname, url, message, client_id, user_agent
         FROM reports
         WHERE LOWER(TRIM(event_type)) = \'delete_request\'
         ORDER BY id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function clickfix_update_appeal_status(PDO $pdo, int $appealId, string $status): bool
{
    $status = in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : 'pending';
    $stmt = $pdo->prepare('UPDATE appeals SET status = :status WHERE id = :id');
    return $stmt->execute([':status' => $status, ':id' => $appealId]);
}

function clickfix_store_access_request(PDO $pdo, string $email, string $language, string $linkedinUrl = '', string $companyWebsite = ''): bool
{
    $email = strtolower(trim($email));
    if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }
    $language = strtolower(trim($language));
    if (!preg_match('/^[a-z]{2}$/', $language)) {
        $language = 'en';
    }
    $linkedinUrl = clickfix_sanitize_http_url($linkedinUrl);
    $companyWebsite = clickfix_sanitize_http_url($companyWebsite);
    if ($linkedinUrl !== '') {
        $linkedinHost = strtolower((string) parse_url($linkedinUrl, PHP_URL_HOST));
        if ($linkedinHost !== '' && !str_contains($linkedinHost, 'linkedin.com')) {
            return false;
        }
    }

    if (!clickfix_has_column($pdo, 'access_requests', 'linkedin_url')) {
        $pdo->exec('ALTER TABLE access_requests ADD COLUMN linkedin_url TEXT');
    }
    if (!clickfix_has_column($pdo, 'access_requests', 'company_website')) {
        $pdo->exec('ALTER TABLE access_requests ADD COLUMN company_website TEXT');
    }
    if (!clickfix_has_column($pdo, 'access_requests', 'status')) {
        $pdo->exec("ALTER TABLE access_requests ADD COLUMN status TEXT DEFAULT 'pending'");
    }

    $hash = hash('sha256', $email);
    $now = gmdate('c');
    $ip = clickfix_client_ip();
    $agent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $hasLinkedin = clickfix_has_column($pdo, 'access_requests', 'linkedin_url');
    $hasCompany = clickfix_has_column($pdo, 'access_requests', 'company_website');
    $hasStatus = clickfix_has_column($pdo, 'access_requests', 'status');
    $stmt = $pdo->prepare('SELECT id' . ($hasStatus ? ', status' : '') . ' FROM access_requests WHERE email_hash = :hash LIMIT 1');
    $stmt->execute([':hash' => $hash]);
    $row = $stmt->fetch();

    if ($row) {
        $setParts = [
            'request_count = request_count + 1',
            'last_seen_at = :last',
            'request_ip = :ip',
            'user_agent = :agent',
            'request_lang = :lang',
        ];
        $params = [
            ':last' => $now,
            ':ip' => $ip,
            ':agent' => $agent,
            ':lang' => $language,
            ':id' => (int) ($row['id'] ?? 0),
        ];
        if ($hasStatus && trim((string) ($row['status'] ?? '')) === '') {
            $setParts[] = "status = 'pending'";
        }
        if ($hasLinkedin) {
            $setParts[] = 'linkedin_url = :linkedin';
            $params[':linkedin'] = $linkedinUrl;
        }
        if ($hasCompany) {
            $setParts[] = 'company_website = :company';
            $params[':company'] = $companyWebsite;
        }
        $up = $pdo->prepare('UPDATE access_requests SET ' . implode(', ', $setParts) . ' WHERE id = :id');
        return $up->execute($params);
    }

    $columns = [
        'email',
        'email_hash',
        'request_count',
        'first_seen_at',
        'last_seen_at',
        'request_ip',
        'user_agent',
        'request_lang',
    ];
    $placeholders = [
        ':email',
        ':hash',
        '1',
        ':first',
        ':last',
        ':ip',
        ':agent',
        ':lang',
    ];
    $params = [
        ':email' => $email,
        ':hash' => $hash,
        ':first' => $now,
        ':last' => $now,
        ':ip' => $ip,
        ':agent' => $agent,
        ':lang' => $language,
    ];
    if ($hasStatus) {
        $columns[] = 'status';
        $placeholders[] = "'pending'";
    }
    if ($hasLinkedin) {
        $columns[] = 'linkedin_url';
        $placeholders[] = ':linkedin';
        $params[':linkedin'] = $linkedinUrl;
    }
    if ($hasCompany) {
        $columns[] = 'company_website';
        $placeholders[] = ':company';
        $params[':company'] = $companyWebsite;
    }
    $ins = $pdo->prepare('INSERT INTO access_requests (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')');
    return $ins->execute($params);
}

function clickfix_recent_access_requests(PDO $pdo, int $limit = 30, ?string $status = 'pending'): array
{
    $limit = max(1, min($limit, 200));
    $statusValue = $status !== null ? strtolower(trim($status)) : '';
    $allowedStatus = ['pending', 'approved', 'denied'];
    $filterStatus = in_array($statusValue, $allowedStatus, true) ? $statusValue : '';
    $cacheKey = clickfix_cache_key('recent_access_requests', ['limit' => $limit, 'status' => $filterStatus, 'v2' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }
    $hasLinkedin = clickfix_has_column($pdo, 'access_requests', 'linkedin_url');
    $hasCompany = clickfix_has_column($pdo, 'access_requests', 'company_website');
    $hasStatus = clickfix_has_column($pdo, 'access_requests', 'status');
    $select = [
        'id',
        'email',
        ($hasLinkedin ? 'linkedin_url' : "'' AS linkedin_url"),
        ($hasCompany ? 'company_website' : "'' AS company_website"),
        'request_count',
        'first_seen_at',
        'last_seen_at',
        'request_ip',
        'request_lang',
        ($hasStatus ? 'status' : "'' AS status"),
    ];
    if ($filterStatus !== '' && $hasStatus) {
        $stmt = $pdo->prepare(
            'SELECT ' . implode(', ', $select) . ' FROM access_requests WHERE status = :status ORDER BY last_seen_at DESC, id DESC LIMIT :limit'
        );
        $stmt->bindValue(':status', $filterStatus, PDO::PARAM_STR);
    } else {
        $stmt = $pdo->prepare('SELECT ' . implode(', ', $select) . ' FROM access_requests ORDER BY last_seen_at DESC, id DESC LIMIT :limit');
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    if ($filterStatus === '' || $filterStatus === 'pending') {
        $fallbackRows = clickfix_access_request_fallback_load($limit);
        if (!empty($fallbackRows)) {
            $rows = clickfix_merge_access_request_rows($rows, $fallbackRows, $limit);
        }
    }

    clickfix_cache_set($cacheKey, $rows, 8);
    return $rows;
}

function clickfix_update_access_request_status(PDO $pdo, int $requestId, string $status): bool
{
    $status = in_array($status, ['pending', 'approved', 'denied'], true) ? $status : 'pending';
    if (!clickfix_has_column($pdo, 'access_requests', 'status')) {
        $pdo->exec("ALTER TABLE access_requests ADD COLUMN status TEXT DEFAULT 'pending'");
    }
    $stmt = $pdo->prepare('UPDATE access_requests SET status = :status WHERE id = :id');
    return $stmt->execute([':status' => $status, ':id' => $requestId]);
}

function clickfix_access_request_fallback_paths(): array
{
    $paths = [
        __DIR__ . '/../data/access_requests.ndjson',
    ];
    $sessionDir = clickfix_pick_session_dir();
    if (is_string($sessionDir) && $sessionDir !== '') {
        $paths[] = rtrim($sessionDir, "/\\") . DIRECTORY_SEPARATOR . 'access_requests.ndjson';
    }
    $tmpBase = rtrim((string) sys_get_temp_dir(), "/\\");
    if ($tmpBase !== '') {
        $paths[] = $tmpBase . DIRECTORY_SEPARATOR . 'clickfix_access_requests.ndjson';
    }

    $normalized = [];
    foreach ($paths as $path) {
        $normalized[] = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
    return array_values(array_unique($normalized));
}

function clickfix_access_request_fallback_write(array $record): bool
{
    $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($line) || $line === '') {
        return false;
    }

    foreach (clickfix_access_request_fallback_paths() as $path) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            continue;
        }
        $result = @file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($result !== false) {
            return true;
        }
    }

    return false;
}

function clickfix_access_request_fallback_load(int $limit = 30): array
{
    $limit = max(1, min($limit, 200));
    $entries = [];

    foreach (clickfix_access_request_fallback_paths() as $path) {
        if (!is_file($path)) {
            continue;
        }
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines) || empty($lines)) {
            continue;
        }
        foreach ($lines as $line) {
            $payload = json_decode((string) $line, true);
            if (!is_array($payload)) {
                continue;
            }
            $email = strtolower(trim((string) ($payload['email'] ?? '')));
            if ($email === '') {
                continue;
            }
            $created = (string) ($payload['created_at'] ?? '');
            $key = $email;
            if (!isset($entries[$key])) {
                $entries[$key] = [
                    'email' => $email,
                    'linkedin_url' => (string) ($payload['linkedin_url'] ?? ''),
                    'company_website' => (string) ($payload['company_website'] ?? ''),
                    'request_count' => 1,
                    'first_seen_at' => $created,
                    'last_seen_at' => $created,
                    'request_ip' => (string) ($payload['request_ip'] ?? ''),
                    'request_lang' => (string) ($payload['request_lang'] ?? 'en'),
                ];
            } else {
                $entries[$key]['request_count'] = (int) ($entries[$key]['request_count'] ?? 1) + 1;
                if ($created !== '' && ($entries[$key]['last_seen_at'] ?? '') < $created) {
                    $entries[$key]['last_seen_at'] = $created;
                }
                if (($entries[$key]['first_seen_at'] ?? '') === '' || ($created !== '' && $created < (string) ($entries[$key]['first_seen_at'] ?? ''))) {
                    $entries[$key]['first_seen_at'] = $created;
                }
                if (($entries[$key]['linkedin_url'] ?? '') === '' && !empty($payload['linkedin_url'])) {
                    $entries[$key]['linkedin_url'] = (string) $payload['linkedin_url'];
                }
                if (($entries[$key]['company_website'] ?? '') === '' && !empty($payload['company_website'])) {
                    $entries[$key]['company_website'] = (string) $payload['company_website'];
                }
                if (($entries[$key]['request_ip'] ?? '') === '' && !empty($payload['request_ip'])) {
                    $entries[$key]['request_ip'] = (string) $payload['request_ip'];
                }
                if (($entries[$key]['request_lang'] ?? '') === '' && !empty($payload['request_lang'])) {
                    $entries[$key]['request_lang'] = (string) $payload['request_lang'];
                }
            }
        }
    }

    $rows = array_values($entries);
    usort($rows, static function (array $a, array $b): int {
        return strcmp((string) ($b['last_seen_at'] ?? ''), (string) ($a['last_seen_at'] ?? ''));
    });

    return array_slice($rows, 0, $limit);
}

function clickfix_merge_access_request_rows(array $primary, array $fallback, int $limit): array
{
    $limit = max(1, min($limit, 200));
    $index = [];
    foreach ($primary as $row) {
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($email === '') {
            continue;
        }
        $index[$email] = $row;
    }

    foreach ($fallback as $row) {
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($email === '' || isset($index[$email])) {
            continue;
        }
        $index[$email] = $row;
    }

    $rows = array_values($index);
    usort($rows, static function (array $a, array $b): int {
        return strcmp((string) ($b['last_seen_at'] ?? ''), (string) ($a['last_seen_at'] ?? ''));
    });

    return array_slice($rows, 0, $limit);
}

function clickfix_public_referrer_host(?string $url): string
{
    $safeUrl = clickfix_sanitize_http_url($url);
    if ($safeUrl === '') {
        return '';
    }
    $host = (string) parse_url($safeUrl, PHP_URL_HOST);
    return clickfix_normalize_domain($host);
}

function clickfix_public_network_key(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    return $normalized;
}

function clickfix_log_public_page_hit(PDO $pdo, array $payload, int $throttleSeconds = 1800): bool
{
    if (!clickfix_has_table($pdo, 'public_page_hits')) {
        return false;
    }

    $ip = trim((string) ($payload['ip'] ?? clickfix_client_ip()));
    if (!clickfix_is_public_ip($ip)) {
        return false;
    }

    $path = trim((string) ($payload['path'] ?? ''));
    if ($path === '') {
        $path = '/';
    }
    $path = substr($path, 0, 180);
    $lang = strtolower(trim((string) ($payload['lang'] ?? 'en')));
    if (!preg_match('/^[a-z]{2}$/', $lang)) {
        $lang = 'en';
    }

    $referrerUrl = clickfix_sanitize_http_url((string) ($payload['referrer_url'] ?? ''));
    $referrerHost = clickfix_public_referrer_host($referrerUrl);
    $userAgent = substr(trim((string) ($payload['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''))), 0, 255);
    $bucket = (int) floor(time() / max(300, $throttleSeconds));
    $fingerprint = sha1(implode('|', [$ip, $path, $lang, $referrerHost, $userAgent, (string) $bucket]));
    if (!isset($_SESSION['clickfix_public_hit_log']) || !is_array($_SESSION['clickfix_public_hit_log'])) {
        $_SESSION['clickfix_public_hit_log'] = [];
    }
    if (isset($_SESSION['clickfix_public_hit_log'][$fingerprint])) {
        return false;
    }
    $_SESSION['clickfix_public_hit_log'][$fingerprint] = time();
    if (count($_SESSION['clickfix_public_hit_log']) > 80) {
        $_SESSION['clickfix_public_hit_log'] = array_slice($_SESSION['clickfix_public_hit_log'], -40, null, true);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO public_page_hits (
            created_at, path, lang, ip, country_code, country_name, region, region_name, city, timezone,
            isp, org, asn, asname, referrer_url, referrer_host, user_agent, mobile, proxy, hosting, lat, lon
         ) VALUES (
            :created_at, :path, :lang, :ip, :country_code, :country_name, :region, :region_name, :city, :timezone,
            :isp, :org, :asn, :asname, :referrer_url, :referrer_host, :user_agent, :mobile, :proxy, :hosting, :lat, :lon
         )'
    );

    return $stmt->execute([
        ':created_at' => gmdate('c'),
        ':path' => $path,
        ':lang' => $lang,
        ':ip' => substr($ip, 0, 80),
        ':country_code' => substr(trim((string) ($payload['country_code'] ?? '')), 0, 12),
        ':country_name' => substr(trim((string) ($payload['country_name'] ?? '')), 0, 120),
        ':region' => substr(trim((string) ($payload['region'] ?? '')), 0, 24),
        ':region_name' => substr(trim((string) ($payload['region_name'] ?? '')), 0, 120),
        ':city' => substr(trim((string) ($payload['city'] ?? '')), 0, 120),
        ':timezone' => substr(trim((string) ($payload['timezone'] ?? '')), 0, 80),
        ':isp' => substr(trim((string) ($payload['isp'] ?? '')), 0, 160),
        ':org' => substr(trim((string) ($payload['org'] ?? '')), 0, 160),
        ':asn' => substr(trim((string) ($payload['asn'] ?? '')), 0, 80),
        ':asname' => substr(trim((string) ($payload['asname'] ?? '')), 0, 160),
        ':referrer_url' => substr($referrerUrl, 0, 500),
        ':referrer_host' => substr($referrerHost, 0, 190),
        ':user_agent' => $userAgent,
        ':mobile' => !empty($payload['mobile']) ? 1 : 0,
        ':proxy' => !empty($payload['proxy']) ? 1 : 0,
        ':hosting' => !empty($payload['hosting']) ? 1 : 0,
        ':lat' => isset($payload['lat']) && is_numeric($payload['lat']) ? (float) $payload['lat'] : null,
        ':lon' => isset($payload['lon']) && is_numeric($payload['lon']) ? (float) $payload['lon'] : null,
    ]);
}

function clickfix_project_exposure_overview(PDO $pdo, int $days = 30): array
{
    $days = max(7, min(90, $days));
    $result = [
        'window_days' => $days,
        'summary' => [
            'hits_total' => 0,
            'unique_ips' => 0,
            'suspicious_hits' => 0,
            'referrer_overlap_hits' => 0,
            'infra_overlap_hits' => 0,
            'external_referrer_hits' => 0,
        ],
        'top_referrers' => [],
        'top_networks' => [],
        'events' => [],
    ];
    if (!clickfix_has_table($pdo, 'public_page_hits')) {
        return $result;
    }

    $cacheKey = clickfix_cache_key('project_exposure_overview', ['days' => $days, 'v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $cutoff = gmdate('c', time() - ($days * 86400));
    $hostCutoff = gmdate('c', time() - (max($days, 60) * 86400));
    $summaryStmt = $pdo->prepare(
        'SELECT COUNT(*) AS hits_total,
                COUNT(DISTINCT ip) AS unique_ips,
                SUM(CASE WHEN proxy = 1 OR hosting = 1 THEN 1 ELSE 0 END) AS suspicious_hits,
                SUM(CASE WHEN referrer_host IS NOT NULL AND referrer_host != \'\' THEN 1 ELSE 0 END) AS external_referrer_hits
         FROM public_page_hits
         WHERE created_at >= :cutoff'
    );
    $summaryStmt->execute([':cutoff' => $cutoff]);
    $summaryRow = $summaryStmt->fetch() ?: [];
    $result['summary']['hits_total'] = (int) ($summaryRow['hits_total'] ?? 0);
    $result['summary']['unique_ips'] = (int) ($summaryRow['unique_ips'] ?? 0);
    $result['summary']['suspicious_hits'] = (int) ($summaryRow['suspicious_hits'] ?? 0);
    $result['summary']['external_referrer_hits'] = (int) ($summaryRow['external_referrer_hits'] ?? 0);

    $reportedSet = [];
    $reportedHitsByHost = [];
    if (clickfix_has_table($pdo, 'reports')) {
        $hostStmt = $pdo->prepare(
            'SELECT hostname, COUNT(*) AS hits
             FROM reports
             WHERE hostname IS NOT NULL AND hostname != \'\' AND received_at >= :cutoff
             GROUP BY hostname'
        );
        $hostStmt->execute([':cutoff' => $hostCutoff]);
        foreach ($hostStmt->fetchAll() as $row) {
            $host = clickfix_normalize_domain((string) ($row['hostname'] ?? ''));
            if ($host === '') {
                continue;
            }
            $reportedSet[$host] = true;
            $reportedHitsByHost[$host] = (int) ($row['hits'] ?? 0);
        }
    }

    $ipToDomains = [];
    $ispToDomains = [];
    if (clickfix_has_table($pdo, 'domain_intel_cache') && !empty($reportedSet)) {
        $intelStmt = $pdo->prepare(
            'SELECT dic.hostname, dic.ip, dic.isp
             FROM domain_intel_cache dic
             INNER JOIN (
                SELECT DISTINCT LOWER(TRIM(hostname)) AS hostname
                FROM reports
                WHERE hostname IS NOT NULL AND hostname != \'\' AND received_at >= :cutoff
             ) rh ON LOWER(TRIM(dic.hostname)) = rh.hostname'
        );
        $intelStmt->execute([':cutoff' => $hostCutoff]);
        foreach ($intelStmt->fetchAll() as $row) {
            $host = clickfix_normalize_domain((string) ($row['hostname'] ?? ''));
            $ip = trim((string) ($row['ip'] ?? ''));
            $ispKey = clickfix_public_network_key((string) ($row['isp'] ?? ''));
            if ($host === '') {
                continue;
            }
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $ipToDomains[$ip][$host] = true;
            }
            if ($ispKey !== '') {
                $ispToDomains[$ispKey][$host] = true;
            }
        }
    }
    if (clickfix_has_table($pdo, 'whatweb_cache') && !empty($reportedSet)) {
        $whatwebStmt = $pdo->prepare(
            'SELECT wwc.hostname, wwc.ip
             FROM whatweb_cache wwc
             INNER JOIN (
                SELECT DISTINCT LOWER(TRIM(hostname)) AS hostname
                FROM reports
                WHERE hostname IS NOT NULL AND hostname != \'\' AND received_at >= :cutoff
             ) rh ON LOWER(TRIM(wwc.hostname)) = rh.hostname'
        );
        $whatwebStmt->execute([':cutoff' => $hostCutoff]);
        foreach ($whatwebStmt->fetchAll() as $row) {
            $host = clickfix_normalize_domain((string) ($row['hostname'] ?? ''));
            $ip = trim((string) ($row['ip'] ?? ''));
            if ($host !== '' && $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $ipToDomains[$ip][$host] = true;
            }
        }
    }

    $recentStmt = $pdo->prepare(
        'SELECT created_at, path, lang, ip, country_code, country_name, region_name, city, timezone, isp, org, asn, asname,
                referrer_url, referrer_host, mobile, proxy, hosting
         FROM public_page_hits
         WHERE created_at >= :cutoff
         ORDER BY created_at DESC, id DESC
         LIMIT 400'
    );
    $recentStmt->execute([':cutoff' => $cutoff]);
    $rows = $recentStmt->fetchAll();

    $topReferrers = [];
    $topNetworks = [];
    $events = [];
    $referrerOverlapHits = 0;
    $infraOverlapHits = 0;

    foreach ($rows as $row) {
        $referrerHost = clickfix_normalize_domain((string) ($row['referrer_host'] ?? ''));
        $ip = trim((string) ($row['ip'] ?? ''));
        $isp = trim((string) ($row['isp'] ?? ''));
        $asname = trim((string) ($row['asname'] ?? ''));
        $networkLabel = $asname !== '' ? $asname : ($isp !== '' ? $isp : trim((string) ($row['org'] ?? '')));
        $networkKey = clickfix_public_network_key($networkLabel !== '' ? $networkLabel : $isp);
        $matchedDomains = [];
        $flags = [];

        if ($referrerHost !== '') {
            if (!isset($topReferrers[$referrerHost])) {
                $topReferrers[$referrerHost] = [
                    'host' => $referrerHost,
                    'hits' => 0,
                    'last_seen' => (string) ($row['created_at'] ?? ''),
                    'reported_hits' => (int) ($reportedHitsByHost[$referrerHost] ?? 0),
                    'overlap' => isset($reportedSet[$referrerHost]),
                ];
            }
            $topReferrers[$referrerHost]['hits']++;
            if (isset($reportedSet[$referrerHost])) {
                $flags[] = 'referrer_match';
                $matchedDomains[$referrerHost] = true;
                $referrerOverlapHits++;
                $topReferrers[$referrerHost]['overlap'] = true;
            }
        }

        if ($ip !== '' && isset($ipToDomains[$ip])) {
            $flags[] = 'direct_ip_overlap';
            $infraOverlapHits++;
            foreach (array_keys($ipToDomains[$ip]) as $domain) {
                $matchedDomains[$domain] = true;
            }
        }

        $ispKey = clickfix_public_network_key($isp);
        if (($ispKey !== '' && isset($ispToDomains[$ispKey])) || ($networkKey !== '' && isset($ispToDomains[$networkKey]))) {
            $flags[] = 'network_overlap';
            foreach (array_keys($ispToDomains[$ispKey] ?? $ispToDomains[$networkKey] ?? []) as $domain) {
                $matchedDomains[$domain] = true;
            }
        }

        if (!empty($row['proxy']) || !empty($row['hosting'])) {
            $flags[] = !empty($row['proxy']) ? 'proxy' : 'hosting';
        }

        if ($networkKey !== '') {
            if (!isset($topNetworks[$networkKey])) {
                $topNetworks[$networkKey] = [
                    'network' => $networkLabel !== '' ? $networkLabel : $networkKey,
                    'hits' => 0,
                    'unique_ips' => [],
                    'suspicious_hits' => 0,
                    'overlap_hits' => 0,
                    'last_seen' => (string) ($row['created_at'] ?? ''),
                ];
            }
            $topNetworks[$networkKey]['hits']++;
            $topNetworks[$networkKey]['unique_ips'][$ip] = true;
            if (!empty($row['proxy']) || !empty($row['hosting'])) {
                $topNetworks[$networkKey]['suspicious_hits']++;
            }
            if (in_array('direct_ip_overlap', $flags, true) || in_array('network_overlap', $flags, true)) {
                $topNetworks[$networkKey]['overlap_hits']++;
            }
        }

        if (!empty($flags)) {
            $events[] = [
                'created_at' => (string) ($row['created_at'] ?? ''),
                'path' => (string) ($row['path'] ?? '/'),
                'lang' => (string) ($row['lang'] ?? 'en'),
                'ip' => $ip,
                'country_code' => (string) ($row['country_code'] ?? ''),
                'country_name' => (string) ($row['country_name'] ?? ''),
                'region_name' => (string) ($row['region_name'] ?? ''),
                'city' => (string) ($row['city'] ?? ''),
                'timezone' => (string) ($row['timezone'] ?? ''),
                'network' => $networkLabel,
                'referrer_host' => $referrerHost,
                'referrer_url' => (string) ($row['referrer_url'] ?? ''),
                'mobile' => !empty($row['mobile']),
                'proxy' => !empty($row['proxy']),
                'hosting' => !empty($row['hosting']),
                'flags' => array_values(array_unique($flags)),
                'matched_domains' => array_slice(array_values(array_keys($matchedDomains)), 0, 6),
            ];
        }
    }

    foreach ($topNetworks as &$networkRow) {
        $networkRow['unique_ips'] = count(array_filter(array_keys((array) $networkRow['unique_ips'])));
    }
    unset($networkRow);

    usort($events, static function (array $a, array $b): int {
        return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
    });
    uasort($topReferrers, static function (array $a, array $b): int {
        $left = (($b['overlap'] ?? false) <=> ($a['overlap'] ?? false));
        if ($left !== 0) {
            return $left;
        }
        return ((int) ($b['hits'] ?? 0)) <=> ((int) ($a['hits'] ?? 0));
    });
    uasort($topNetworks, static function (array $a, array $b): int {
        $left = ((int) ($b['overlap_hits'] ?? 0)) <=> ((int) ($a['overlap_hits'] ?? 0));
        if ($left !== 0) {
            return $left;
        }
        return ((int) ($b['hits'] ?? 0)) <=> ((int) ($a['hits'] ?? 0));
    });

    $result['summary']['referrer_overlap_hits'] = $referrerOverlapHits;
    $result['summary']['infra_overlap_hits'] = $infraOverlapHits;
    $result['top_referrers'] = array_slice(array_values($topReferrers), 0, 16);
    $result['top_networks'] = array_slice(array_values($topNetworks), 0, 16);
    $result['events'] = array_slice($events, 0, 40);

    clickfix_cache_set($cacheKey, $result, 20);
    return $result;
}

function clickfix_analytics_overview(PDO $pdo, int $days = 30): array
{
    $days = max(7, min(90, $days));
    $cacheKey = clickfix_cache_key('analytics_overview', ['days' => $days, 'v3' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }
    $labels = [];
    $alerts = [];
    $blocks = [];
    $reviewPending = [];
    $reviewAccepted = [];
    $reviewRejected = [];
    $reviewed = [];
    $manualReports = [];
    $highRisk = [];
    $avgScore = [];
    $uniqueHosts = [];
    $byDayMap = [];
    $cutoff = gmdate('c', time() - (($days - 1) * 86400));

    $stmt = $pdo->prepare(
        "SELECT substr(received_at, 1, 10) as d,
                COUNT(*) as c,
                SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as b,
                SUM(CASE WHEN review_status = 'accepted' THEN 1 ELSE 0 END) as review_accepted,
                SUM(CASE WHEN review_status = 'rejected' THEN 1 ELSE 0 END) as review_rejected,
                SUM(CASE WHEN review_status = 'allowlisted' THEN 1 ELSE 0 END) as review_allowlisted,
                SUM(CASE WHEN review_status IS NULL OR review_status = '' OR review_status = 'pending' THEN 1 ELSE 0 END) as review_pending,
                SUM(CASE WHEN event_type = 'manual_report' THEN 1 ELSE 0 END) as manual_reports,
                SUM(CASE WHEN COALESCE(score_total, 0) >= 80 THEN 1 ELSE 0 END) as high_risk,
                AVG(COALESCE(score_total, 0)) as avg_score,
                COUNT(DISTINCT CASE WHEN hostname IS NOT NULL AND TRIM(hostname) != '' THEN LOWER(TRIM(hostname)) END) as unique_hosts
         FROM reports
         WHERE received_at >= :cutoff
         GROUP BY substr(received_at, 1, 10)
         ORDER BY d ASC"
    );
    $stmt->execute([':cutoff' => $cutoff]);
    foreach ($stmt->fetchAll() as $row) {
        $key = (string) ($row['d'] ?? '');
        $byDayMap[$key] = [
            'alerts' => (int) ($row['c'] ?? 0),
            'blocks' => (int) ($row['b'] ?? 0),
            'review_pending' => (int) ($row['review_pending'] ?? 0),
            'review_accepted' => (int) ($row['review_accepted'] ?? 0),
            'review_rejected' => (int) ($row['review_rejected'] ?? 0),
            'review_allowlisted' => (int) ($row['review_allowlisted'] ?? 0),
            'manual_reports' => (int) ($row['manual_reports'] ?? 0),
            'high_risk' => (int) ($row['high_risk'] ?? 0),
            'avg_score' => round((float) ($row['avg_score'] ?? 0.0), 2),
            'unique_hosts' => (int) ($row['unique_hosts'] ?? 0),
        ];
    }

    for ($i = $days - 1; $i >= 0; $i--) {
        $day = gmdate('Y-m-d', time() - ($i * 86400));
        $labels[] = $day;
        $alerts[] = (int) ($byDayMap[$day]['alerts'] ?? 0);
        $blocks[] = (int) ($byDayMap[$day]['blocks'] ?? 0);
        $reviewPending[] = (int) ($byDayMap[$day]['review_pending'] ?? 0);
        $reviewAccepted[] = (int) ($byDayMap[$day]['review_accepted'] ?? 0);
        $reviewRejected[] = (int) ($byDayMap[$day]['review_rejected'] ?? 0);
        $reviewed[] = (int) (($byDayMap[$day]['review_accepted'] ?? 0) + ($byDayMap[$day]['review_rejected'] ?? 0) + ($byDayMap[$day]['review_allowlisted'] ?? 0));
        $manualReports[] = (int) ($byDayMap[$day]['manual_reports'] ?? 0);
        $highRisk[] = (int) ($byDayMap[$day]['high_risk'] ?? 0);
        $avgScore[] = (float) ($byDayMap[$day]['avg_score'] ?? 0.0);
        $uniqueHosts[] = (int) ($byDayMap[$day]['unique_hosts'] ?? 0);
    }

    $eventTypeLabels = [];
    $eventTypeCounts = [];
    $eventTypeStmt = $pdo->prepare(
        "SELECT COALESCE(NULLIF(TRIM(event_type), ''), 'clickfix_alert') AS event_type,
                COUNT(*) AS total
         FROM reports
         WHERE received_at >= :cutoff
         GROUP BY COALESCE(NULLIF(TRIM(event_type), ''), 'clickfix_alert')
         ORDER BY total DESC, event_type ASC
         LIMIT 8"
    );
    $eventTypeStmt->execute([':cutoff' => $cutoff]);
    foreach ($eventTypeStmt->fetchAll() as $row) {
        $eventTypeLabels[] = (string) ($row['event_type'] ?? 'clickfix_alert');
        $eventTypeCounts[] = (int) ($row['total'] ?? 0);
    }

    $newDomains = [];
    $newStmt = $pdo->prepare(
        "SELECT hostname, MIN(received_at) as first_seen, COUNT(*) as hits
         FROM reports
         WHERE hostname IS NOT NULL AND hostname != ''
         GROUP BY hostname
         HAVING MIN(received_at) >= :cutoff
         ORDER BY first_seen DESC
         LIMIT 60"
    );
    $newStmt->execute([':cutoff' => $cutoff]);
    foreach ($newStmt->fetchAll() as $row) {
        $newDomains[] = [
            'hostname' => (string) ($row['hostname'] ?? ''),
            'first_seen' => (string) ($row['first_seen'] ?? ''),
            'hits' => (int) ($row['hits'] ?? 0),
        ];
    }

    $latestEvidence = clickfix_latest_scan_evidence($pdo, true, 500);
    $latestScan = is_array($latestEvidence['report'] ?? null) ? $latestEvidence['report'] : null;
    $scanAssets = is_array($latestEvidence['assets'] ?? null) ? $latestEvidence['assets'] : null;

    $result = [
        'labels' => $labels,
        'alerts' => $alerts,
        'blocks' => $blocks,
        'review_pending' => $reviewPending,
        'review_accepted' => $reviewAccepted,
        'review_rejected' => $reviewRejected,
        'reviewed' => $reviewed,
        'manual_reports' => $manualReports,
        'high_risk' => $highRisk,
        'avg_score' => $avgScore,
        'unique_hosts' => $uniqueHosts,
        'event_type_labels' => $eventTypeLabels,
        'event_type_counts' => $eventTypeCounts,
        'new_domains' => $newDomains,
        'latest_scan' => $latestScan,
        'latest_scan_assets' => $scanAssets,
    ];
    clickfix_cache_set($cacheKey, $result, 15);
    return $result;
}

function clickfix_latest_scan_evidence(PDO $pdo, bool $publicOnly = true, int $limit = 500): array
{
    $limit = max(1, min(1000, $limit));
    $emptyAssets = [
        'before' => null,
        'after' => null,
        'before_exists' => false,
        'after_exists' => false,
        'before_status' => 'missing',
        'after_status' => 'missing',
    ];
    $result = [
        'report' => null,
        'assets' => $emptyAssets,
        'has_pair' => false,
    ];

    if (clickfix_has_table($pdo, 'scan_image_reviews')) {
        $statusList = $publicOnly ? ['approved'] : ['approved', 'pending'];
        $placeholders = [];
        $params = [];
        foreach ($statusList as $idx => $status) {
            $key = ':status_' . $idx;
            $placeholders[] = $key;
            $params[$key] = $status;
        }
        $sql = 'SELECT r.id,
                       r.received_at,
                       r.hostname,
                       r.url,
                       r.previous_url,
                       MAX(CASE WHEN sir.kind = \'before\' THEN 1 ELSE 0 END) AS before_flag,
                       MAX(CASE WHEN sir.kind = \'after\' THEN 1 ELSE 0 END) AS after_flag
                FROM scan_image_reviews sir
                INNER JOIN reports r ON r.id = sir.report_id
                WHERE sir.status IN (' . implode(', ', $placeholders) . ')
                GROUP BY r.id, r.received_at, r.hostname, r.url, r.previous_url
                ORDER BY
                    CASE
                        WHEN MAX(CASE WHEN sir.kind = \'before\' THEN 1 ELSE 0 END) = 1
                         AND MAX(CASE WHEN sir.kind = \'after\' THEN 1 ELSE 0 END) = 1 THEN 0
                        WHEN MAX(CASE WHEN sir.kind = \'after\' THEN 1 ELSE 0 END) = 1 THEN 1
                        WHEN MAX(CASE WHEN sir.kind = \'before\' THEN 1 ELSE 0 END) = 1 THEN 2
                        ELSE 3
                    END,
                    COALESCE(r.received_at, \'\') DESC,
                    r.id DESC
                LIMIT :limit';
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll() as $candidate) {
            $candidateId = (int) ($candidate['id'] ?? 0);
            if ($candidateId <= 0) {
                continue;
            }
            $candidateAssets = clickfix_scan_preview_assets($pdo, $candidateId, $publicOnly);
            if (empty($candidateAssets['before']) && empty($candidateAssets['after'])) {
                continue;
            }
            $result['report'] = $candidate;
            $result['assets'] = $candidateAssets;
            $result['has_pair'] = !empty($candidateAssets['before']) && !empty($candidateAssets['after']);
            return $result;
        }
    }

    if ($publicOnly) {
        return $result;
    }

    $fallbackStmt = $pdo->prepare(
        'SELECT id, received_at, hostname, url, previous_url
         FROM reports
         ORDER BY COALESCE(received_at, \'\') DESC, id DESC
         LIMIT :limit'
    );
    $fallbackStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $fallbackStmt->execute();
    foreach ($fallbackStmt->fetchAll() as $candidate) {
        $candidateId = (int) ($candidate['id'] ?? 0);
        if ($candidateId <= 0) {
            continue;
        }
        $candidateAssets = clickfix_scan_preview_assets($pdo, $candidateId, false);
        if (
            empty($candidateAssets['before'])
            && empty($candidateAssets['after'])
            && empty($candidateAssets['before_exists'])
            && empty($candidateAssets['after_exists'])
        ) {
            continue;
        }
        $result['report'] = $candidate;
        $result['assets'] = $candidateAssets;
        $result['has_pair'] = !empty($candidateAssets['before']) && !empty($candidateAssets['after']);
        return $result;
    }

    return $result;
}

function clickfix_ml_insights(PDO $pdo, int $sampleSize = 260): array
{
    $sampleSize = max(300, min(300, $sampleSize));
    $cacheKey = clickfix_cache_key('ml_insights', ['sample' => $sampleSize, 'v2' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $keywords = ['powershell', 'invoke-webrequest', 'frombase64string', 'mshta', 'rundll32', 'regsvr32', 'certutil', 'wscript', 'cscript', 'cmd /c', 'curl ', 'wget ', 'clipboard', 'captcha', '.zip', '.exe'];

    $sampleStmt = $pdo->prepare(
        'SELECT id, received_at, hostname, url, message, blocked, review_status, duplicate_count, score_total, detected_content, full_context, reason_entries_json, signals_json, matched_snippets_json
         FROM reports
         ORDER BY received_at DESC
         LIMIT :limit'
    );
    $sampleStmt->bindValue(':limit', $sampleSize, PDO::PARAM_INT);
    $sampleStmt->execute();
    $sampleRows = clickfix_enrich_report_rows($sampleStmt->fetchAll());

    $sampleKeywordHits = clickfix_ml_keyword_hits_empty($keywords);
    $predictions = [];
    $sampleMalicious = 0;
    $sampleSuspicious = 0;
    $sampleLowRisk = 0;
    $sampleHighConfidence = 0;
    $sampleAvgScore = 0.0;
    $enrichmentRunBudget = 12;

    foreach ($sampleRows as $row) {
        $scoreTotal = isset($row['score_total']) ? (int) $row['score_total'] : 0;
        $duplicateCount = isset($row['duplicate_count']) ? max(1, (int) $row['duplicate_count']) : 1;
        $host = strtolower((string) ($row['hostname'] ?? ''));
        $url = strtolower((string) ($row['url'] ?? ''));
        $blob = strtolower(trim((string) ($row['message'] ?? '') . ' ' . (string) ($row['detected_content'] ?? '') . ' ' . (string) ($row['full_context'] ?? '')));
        $remoteHits = clickfix_ml_keyword_hits_empty($keywords);
        if ($scoreTotal > 20 && $url !== '' && $enrichmentRunBudget > 0) {
            $enriched = clickfix_ml_keyword_enrichment_cached($pdo, $url, $keywords, 6 * 3600);
            if (is_array($enriched['keyword_hits'] ?? null)) {
                foreach ($remoteHits as $k => $v) {
                    $remoteHits[$k] = (int) ($enriched['keyword_hits'][$k] ?? 0);
                }
            }
            if (!empty($enriched['used_fetch'])) {
                $enrichmentRunBudget--;
            }
        }
        $keywordScore = 0.0;
        foreach ($keywords as $keyword) {
            $localFound = $blob !== '' && strpos($blob, $keyword) !== false;
            $remoteCount = (int) ($remoteHits[$keyword] ?? 0);
            if ($localFound || $remoteCount > 0) {
                $increment = ($localFound ? 1 : 0) + min(4, $remoteCount);
                $keywordScore += 0.22 * min(4, $increment);
                $sampleKeywordHits[$keyword] += $increment;
            }
        }
        $raw = -1.2;
        $raw += min(2.0, $scoreTotal / 55.0);
        $raw += !empty($row['blocked']) ? 0.55 : 0.0;
        $raw += min(0.75, log((float) $duplicateCount, 2) * 0.18);
        $raw += min(1.6, $keywordScore);
        $raw += strpos($host, 'xn--') !== false ? 0.42 : 0.0;
        $raw += preg_match('/^(?:\d{1,3}\.){3}\d{1,3}$/', $host) ? 0.42 : 0.0;
        $raw += preg_match('/https?:\/\/(?:\d{1,3}\.){3}\d{1,3}/', $url) ? 0.35 : 0.0;
        $raw += preg_match('/\.(top|xyz|click|shop|live|fit|buzz|rest|monster|cam)$/', $host) ? 0.25 : 0.0;
        $raw += strtolower((string) ($row['review_status'] ?? 'pending')) === 'accepted' ? 0.4 : 0.0;
        $raw += strtolower((string) ($row['review_status'] ?? 'pending')) === 'rejected' ? -0.6 : 0.0;

        $predictedScore = round(clickfix_sigmoid($raw) * 100.0, 2);
        $label = 'low_risk';
        if ($predictedScore > 38.0) {
            $label = 'malicious';
            $sampleMalicious++;
        } elseif ($predictedScore >= 15.0) {
            $label = 'suspicious';
            $sampleSuspicious++;
        } else {
            $sampleLowRisk++;
        }
        if ($predictedScore >= 85.0) {
            $sampleHighConfidence++;
        }
        $sampleAvgScore += $predictedScore;
        $predictions[] = [
            'id' => (int) ($row['id'] ?? 0),
            'received_at' => (string) ($row['received_at'] ?? ''),
            'hostname' => (string) ($row['hostname'] ?? ''),
            'score_total' => $scoreTotal,
            'predicted_score' => $predictedScore,
            'predicted_label' => $label,
            'blocked' => !empty($row['blocked']),
        ];
    }
    usort($predictions, static function (array $a, array $b): int {
        return ((float) ($b['predicted_score'] ?? 0.0) <=> (float) ($a['predicted_score'] ?? 0.0));
    });
    arsort($sampleKeywordHits);

    $burstStmt = $pdo->prepare("SELECT hostname, SUM(CASE WHEN received_at >= :cut24 THEN 1 ELSE 0 END) hits_24h, SUM(CASE WHEN received_at >= :cut7 AND received_at < :cut24 THEN 1 ELSE 0 END) hits_prev FROM reports WHERE received_at >= :cut7 AND hostname IS NOT NULL AND hostname != '' GROUP BY hostname HAVING hits_24h > 0 ORDER BY hits_24h DESC LIMIT 120");
    $burstStmt->execute([':cut24' => gmdate('c', time() - 86400), ':cut7' => gmdate('c', time() - (7 * 86400))]);
    $burstDomains = [];
    foreach ($burstStmt->fetchAll() as $row) {
        $h24 = (int) ($row['hits_24h'] ?? 0);
        $prev = (int) ($row['hits_prev'] ?? 0);
        $ratio = $h24 / max(0.2, $prev / 6.0);
        if ($h24 >= 3 && $ratio >= 2.2) {
            $burstDomains[] = ['hostname' => (string) ($row['hostname'] ?? ''), 'hits_24h' => $h24, 'burst_ratio' => round($ratio, 2)];
        }
    }
    usort($burstDomains, static function (array $a, array $b): int {
        return ((float) ($b['burst_ratio'] ?? 0.0) <=> (float) ($a['burst_ratio'] ?? 0.0));
    });

    $histStmt = $pdo->query(
        'SELECT received_at, hostname, url, message, blocked, review_status, duplicate_count, score_total, detected_content, full_context
         FROM reports'
    );
    $histKeywordHits = clickfix_ml_keyword_hits_empty($keywords);
    $histMalicious = 0;
    $histSuspicious = 0;
    $histLowRisk = 0;
    $histHighConfidence = 0;
    $histAvgScore = 0.0;
    $histTotal = 0;

    $windowHits = [
        'total_historico' => clickfix_ml_keyword_hits_empty($keywords),
        'ultima_semana' => clickfix_ml_keyword_hits_empty($keywords),
        'ultimo_mes' => clickfix_ml_keyword_hits_empty($keywords),
        'ultimos_3_meses' => clickfix_ml_keyword_hits_empty($keywords),
        'ultimos_6_meses' => clickfix_ml_keyword_hits_empty($keywords),
    ];
    $windowCutoffs = [
        'ultima_semana' => time() - (7 * 86400),
        'ultimo_mes' => time() - (30 * 86400),
        'ultimos_3_meses' => time() - (90 * 86400),
        'ultimos_6_meses' => time() - (180 * 86400),
    ];

    while ($row = $histStmt->fetch()) {
        $scoreTotal = isset($row['score_total']) ? (int) $row['score_total'] : 0;
        $duplicateCount = isset($row['duplicate_count']) ? max(1, (int) $row['duplicate_count']) : 1;
        $host = strtolower((string) ($row['hostname'] ?? ''));
        $url = strtolower((string) ($row['url'] ?? ''));
        $blob = strtolower(trim((string) ($row['message'] ?? '') . ' ' . (string) ($row['detected_content'] ?? '') . ' ' . (string) ($row['full_context'] ?? '')));
        $ts = strtotime((string) ($row['received_at'] ?? ''));
        if ($ts === false) {
            $ts = 0;
        }

        $keywordScore = 0.0;
        foreach ($keywords as $keyword) {
            if ($blob !== '' && strpos($blob, $keyword) !== false) {
                $keywordScore += 0.22;
                $histKeywordHits[$keyword] += 1;
                $windowHits['total_historico'][$keyword] += 1;
                foreach ($windowCutoffs as $windowKey => $cutoffTs) {
                    if ($ts >= $cutoffTs) {
                        $windowHits[$windowKey][$keyword] += 1;
                    }
                }
            }
        }

        $raw = -1.2;
        $raw += min(2.0, $scoreTotal / 55.0);
        $raw += !empty($row['blocked']) ? 0.55 : 0.0;
        $raw += min(0.75, log((float) $duplicateCount, 2) * 0.18);
        $raw += min(1.6, $keywordScore);
        $raw += strpos($host, 'xn--') !== false ? 0.42 : 0.0;
        $raw += preg_match('/^(?:\d{1,3}\.){3}\d{1,3}$/', $host) ? 0.42 : 0.0;
        $raw += preg_match('/https?:\/\/(?:\d{1,3}\.){3}\d{1,3}/', $url) ? 0.35 : 0.0;
        $raw += preg_match('/\.(top|xyz|click|shop|live|fit|buzz|rest|monster|cam)$/', $host) ? 0.25 : 0.0;
        $raw += strtolower((string) ($row['review_status'] ?? 'pending')) === 'accepted' ? 0.4 : 0.0;
        $raw += strtolower((string) ($row['review_status'] ?? 'pending')) === 'rejected' ? -0.6 : 0.0;

        $predictedScore = round(clickfix_sigmoid($raw) * 100.0, 2);
        if ($predictedScore > 38.0) {
            $histMalicious++;
        } elseif ($predictedScore >= 15.0) {
            $histSuspicious++;
        } else {
            $histLowRisk++;
        }
        if ($predictedScore >= 85.0) {
            $histHighConfidence++;
        }
        $histAvgScore += $predictedScore;
        $histTotal++;
    }
    arsort($histKeywordHits);
    foreach ($windowHits as &$hits) {
        arsort($hits);
    }
    unset($hits);

    $toKeywordRows = static function (array $hits, int $limit = 12): array {
        $rows = [];
        foreach (array_slice($hits, 0, $limit, true) as $keyword => $count) {
            $rows[] = ['keyword' => (string) $keyword, 'hits' => (int) $count];
        }
        return $rows;
    };

    $sampleSummary = [
        'sample_size' => count($sampleRows),
        'avg_risk_score' => !empty($sampleRows) ? round($sampleAvgScore / count($sampleRows), 2) : 0.0,
        'malicious_predicted' => $sampleMalicious,
        'suspicious_predicted' => $sampleSuspicious,
        'low_risk_predicted' => $sampleLowRisk,
        'high_confidence_count' => $sampleHighConfidence,
        'top_keywords' => $toKeywordRows($sampleKeywordHits, 12),
        'burst_domains' => array_slice($burstDomains, 0, 12),
        'top_predictions' => array_slice($predictions, 0, 18),
    ];

    $historicalSummary = [
        'sample_size' => $histTotal,
        'avg_risk_score' => $histTotal > 0 ? round($histAvgScore / $histTotal, 2) : 0.0,
        'malicious_predicted' => $histMalicious,
        'suspicious_predicted' => $histSuspicious,
        'low_risk_predicted' => $histLowRisk,
        'high_confidence_count' => $histHighConfidence,
        'top_keywords' => $toKeywordRows($histKeywordHits, 12),
    ];

    $result = [
        'generated_at' => gmdate('c'),
        'sample_size' => $sampleSummary['sample_size'],
        'avg_risk_score' => $sampleSummary['avg_risk_score'],
        'malicious_predicted' => $sampleSummary['malicious_predicted'],
        'suspicious_predicted' => $sampleSummary['suspicious_predicted'],
        'low_risk_predicted' => $sampleSummary['low_risk_predicted'],
        'high_confidence_count' => $sampleSummary['high_confidence_count'],
        'top_keywords' => $sampleSummary['top_keywords'],
        'burst_domains' => $sampleSummary['burst_domains'],
        'top_predictions' => $sampleSummary['top_predictions'],
        'sample_300' => $sampleSummary,
        'historical_all' => $historicalSummary,
        'keywords_windows' => [
            'total_historico' => $toKeywordRows($windowHits['total_historico'], 12),
            'ultima_semana' => $toKeywordRows($windowHits['ultima_semana'], 12),
            'ultimo_mes' => $toKeywordRows($windowHits['ultimo_mes'], 12),
            'ultimos_3_meses' => $toKeywordRows($windowHits['ultimos_3_meses'], 12),
            'ultimos_6_meses' => $toKeywordRows($windowHits['ultimos_6_meses'], 12),
        ],
    ];

    clickfix_cache_set($cacheKey, $result, 45);
    return $result;
}

function clickfix_anomaly_detector(PDO $pdo, int $days = 35, int $topDomains = 20): array
{
    $days = max(14, min(120, $days));
    $topDomains = max(5, min(100, $topDomains));
    $cacheKey = clickfix_cache_key('anomaly_detector', ['days' => $days, 'top' => $topDomains, 'v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $cutoff = gmdate('c', time() - (($days - 1) * 86400));
    $dailyStmt = $pdo->prepare(
        "SELECT substr(received_at, 1, 10) AS d,
                COUNT(*) AS alerts,
                SUM(CASE WHEN COALESCE(score_total, 0) >= 80 THEN 1 ELSE 0 END) AS high_risk,
                SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) AS blocked
         FROM reports
         WHERE received_at >= :cutoff
         GROUP BY substr(received_at, 1, 10)
         ORDER BY d ASC"
    );
    $dailyStmt->execute([':cutoff' => $cutoff]);
    $dailyMap = [];
    foreach ($dailyStmt->fetchAll() as $row) {
        $dayKey = (string) ($row['d'] ?? '');
        if ($dayKey === '') {
            continue;
        }
        $dailyMap[$dayKey] = [
            'alerts' => (int) ($row['alerts'] ?? 0),
            'high_risk' => (int) ($row['high_risk'] ?? 0),
            'blocked' => (int) ($row['blocked'] ?? 0),
        ];
    }

    $labels = [];
    $alertsSeries = [];
    $highRiskSeries = [];
    $blockRateSeries = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $day = gmdate('Y-m-d', time() - ($i * 86400));
        $dayAlerts = (int) ($dailyMap[$day]['alerts'] ?? 0);
        $dayHighRisk = (int) ($dailyMap[$day]['high_risk'] ?? 0);
        $dayBlocked = (int) ($dailyMap[$day]['blocked'] ?? 0);
        $labels[] = $day;
        $alertsSeries[] = $dayAlerts;
        $highRiskSeries[] = $dayHighRisk;
        $blockRateSeries[] = $dayAlerts > 0 ? round(($dayBlocked / $dayAlerts) * 100.0, 2) : 0.0;
    }

    $seriesStats = static function (array $series): array {
        if (empty($series)) {
            return ['mean' => 0.0, 'std' => 0.0];
        }
        $count = count($series);
        $sum = 0.0;
        foreach ($series as $value) {
            $sum += (float) $value;
        }
        $mean = $count > 0 ? $sum / $count : 0.0;
        if ($count <= 1) {
            return ['mean' => $mean, 'std' => 0.0];
        }
        $variance = 0.0;
        foreach ($series as $value) {
            $delta = (float) $value - $mean;
            $variance += $delta * $delta;
        }
        $std = sqrt($variance / max(1, $count - 1));
        return ['mean' => $mean, 'std' => $std];
    };

    $buildSignal = static function (string $metric, float $current, array $baselineStats, float $minAbsolute): array {
        $mean = (float) ($baselineStats['mean'] ?? 0.0);
        $std = (float) ($baselineStats['std'] ?? 0.0);
        $z = $std > 0.0001 ? (($current - $mean) / $std) : 0.0;
        $deltaPct = $mean > 0.0001 ? (($current - $mean) / $mean) * 100.0 : ($current > 0 ? 100.0 : 0.0);
        $isAnomaly = $current >= $minAbsolute && ($z >= 2.2 || ($mean > 0.0 && $deltaPct >= 150.0));
        $severity = 'low';
        if ($isAnomaly && $z >= 3.5) {
            $severity = 'high';
        } elseif ($isAnomaly && $z >= 2.6) {
            $severity = 'medium';
        }
        return [
            'metric' => $metric,
            'current' => round($current, 2),
            'baseline_mean' => round($mean, 2),
            'baseline_std' => round($std, 2),
            'z_score' => round($z, 2),
            'delta_pct' => round($deltaPct, 2),
            'is_anomaly' => $isAnomaly,
            'severity' => $severity,
        ];
    };

    $summary = [];
    if (!empty($alertsSeries)) {
        $lastIndex = count($alertsSeries) - 1;
        $baselineAlerts = array_slice($alertsSeries, 0, max(0, $lastIndex));
        $baselineHighRisk = array_slice($highRiskSeries, 0, max(0, $lastIndex));
        $baselineBlockRate = array_slice($blockRateSeries, 0, max(0, $lastIndex));
        if (count($baselineAlerts) < 3) {
            $baselineAlerts = $alertsSeries;
        }
        if (count($baselineHighRisk) < 3) {
            $baselineHighRisk = $highRiskSeries;
        }
        if (count($baselineBlockRate) < 3) {
            $baselineBlockRate = $blockRateSeries;
        }
        $summary[] = $buildSignal(
            'alerts_24h',
            (float) $alertsSeries[$lastIndex],
            $seriesStats($baselineAlerts),
            8.0
        );
        $summary[] = $buildSignal(
            'high_risk_24h',
            (float) $highRiskSeries[$lastIndex],
            $seriesStats($baselineHighRisk),
            3.0
        );
        $summary[] = $buildSignal(
            'block_rate_24h_pct',
            (float) $blockRateSeries[$lastIndex],
            $seriesStats($baselineBlockRate),
            15.0
        );
    }

    $cut24 = gmdate('c', time() - 86400);
    $cut7 = gmdate('c', time() - (7 * 86400));
    $domainStmt = $pdo->prepare(
        "SELECT hostname,
                SUM(CASE WHEN received_at >= :cut24 THEN 1 ELSE 0 END) AS hits_24h,
                SUM(CASE WHEN received_at >= :cut24 AND blocked = 1 THEN 1 ELSE 0 END) AS blocked_24h,
                SUM(CASE WHEN received_at >= :cut24 AND COALESCE(score_total, 0) >= 80 THEN 1 ELSE 0 END) AS high_risk_24h,
                SUM(CASE WHEN received_at >= :cut7 AND received_at < :cut24 THEN 1 ELSE 0 END) AS hits_prev_6d
         FROM reports
         WHERE received_at >= :cut7
           AND hostname IS NOT NULL
           AND hostname != ''
         GROUP BY hostname
         HAVING hits_24h > 0
         ORDER BY hits_24h DESC
         LIMIT :limit"
    );
    $domainStmt->bindValue(':cut24', $cut24, PDO::PARAM_STR);
    $domainStmt->bindValue(':cut7', $cut7, PDO::PARAM_STR);
    $domainStmt->bindValue(':limit', 450, PDO::PARAM_INT);
    $domainStmt->execute();

    $domainSpikes = [];
    foreach ($domainStmt->fetchAll() as $row) {
        $hostname = clickfix_normalize_domain((string) ($row['hostname'] ?? ''));
        if ($hostname === '') {
            continue;
        }
        $hits24 = (int) ($row['hits_24h'] ?? 0);
        $hitsPrev6d = (int) ($row['hits_prev_6d'] ?? 0);
        $baselinePer24h = $hitsPrev6d / 6.0;
        $ratio = $hits24 / max(0.25, $baselinePer24h);
        $z = ($hits24 - $baselinePer24h) / sqrt(max(1.0, $baselinePer24h));
        if ($hits24 < 2) {
            continue;
        }
        if ($ratio < 2.5 && $z < 3.0) {
            continue;
        }
        $domainSpikes[] = [
            'hostname' => $hostname,
            'hits_24h' => $hits24,
            'baseline_per_24h' => round($baselinePer24h, 2),
            'ratio' => round($ratio, 2),
            'z_score' => round($z, 2),
            'blocked_24h' => (int) ($row['blocked_24h'] ?? 0),
            'high_risk_24h' => (int) ($row['high_risk_24h'] ?? 0),
        ];
    }
    usort($domainSpikes, static function (array $left, array $right): int {
        $leftScore = ((float) ($left['z_score'] ?? 0.0) * 100.0) + ((float) ($left['ratio'] ?? 0.0) * 10.0);
        $rightScore = ((float) ($right['z_score'] ?? 0.0) * 100.0) + ((float) ($right['ratio'] ?? 0.0) * 10.0);
        return $rightScore <=> $leftScore;
    });

    $result = [
        'generated_at' => gmdate('c'),
        'window_days' => $days,
        'summary' => $summary,
        'domain_spikes' => array_slice($domainSpikes, 0, $topDomains),
        'timeline' => [
            'labels' => $labels,
            'alerts' => $alertsSeries,
            'high_risk' => $highRiskSeries,
            'block_rate_pct' => $blockRateSeries,
        ],
    ];

    clickfix_cache_set($cacheKey, $result, 45);
    return $result;
}

function clickfix_investigation_correlation_stats(PDO $pdo, int $topLimit = 12): array
{
    $topLimit = max(5, min(40, $topLimit));
    $result = [
        'generated_at' => gmdate('c'),
        'jobs' => [
            'total' => 0,
            'queued' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'processed_artifacts' => 0,
        ],
        'artifacts' => [
            'total' => 0,
            'commands' => 0,
            'urls' => 0,
            'domains' => 0,
            'ips' => 0,
            'files' => 0,
            'hashes' => 0,
            'fetched_payloads' => 0,
            'analysis_done' => 0,
        ],
        'alerts' => [
            'pending' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'allowlisted' => 0,
            'blocked' => 0,
        ],
        'investigations' => [
            'with_pipeline' => 0,
            'malicious' => 0,
            'suspicious' => 0,
            'investigating' => 0,
            'clean' => 0,
            'unknown' => 0,
        ],
        'stages' => [
            'avg' => 0.0,
            'max' => 0,
            'distribution' => [],
            'top_chains' => [],
        ],
        'malware_types' => [],
        'artifact_types' => [],
        'top_commands' => [],
        'recent_jobs' => [],
    ];

    if (!clickfix_has_table($pdo, 'investigation_analysis_jobs') || !clickfix_has_table($pdo, 'investigation_artifacts')) {
        return $result;
    }

    $jobRow = $pdo->query(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) AS queued,
                SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) AS running,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                SUM(COALESCE(processed_artifacts, 0)) AS processed_artifacts
         FROM investigation_analysis_jobs"
    )->fetch() ?: [];
    $result['jobs'] = [
        'total' => (int) ($jobRow['total'] ?? 0),
        'queued' => (int) ($jobRow['queued'] ?? 0),
        'running' => (int) ($jobRow['running'] ?? 0),
        'completed' => (int) ($jobRow['completed'] ?? 0),
        'failed' => (int) ($jobRow['failed'] ?? 0),
        'processed_artifacts' => (int) ($jobRow['processed_artifacts'] ?? 0),
    ];

    $artifactRow = $pdo->query(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN artifact_kind = 'command' THEN 1 ELSE 0 END) AS commands,
                SUM(CASE WHEN artifact_kind = 'url' THEN 1 ELSE 0 END) AS urls,
                SUM(CASE WHEN artifact_kind = 'domain' THEN 1 ELSE 0 END) AS domains,
                SUM(CASE WHEN artifact_kind = 'ip' THEN 1 ELSE 0 END) AS ips,
                SUM(CASE WHEN artifact_kind = 'file' THEN 1 ELSE 0 END) AS files,
                SUM(CASE WHEN artifact_kind IN ('md5','sha1','sha256') THEN 1 ELSE 0 END) AS hashes,
                SUM(CASE WHEN LOWER(COALESCE(fetch_status, '')) = 'fetched' THEN 1 ELSE 0 END) AS fetched_payloads,
                SUM(CASE WHEN LOWER(COALESCE(analysis_status, '')) = 'done' THEN 1 ELSE 0 END) AS analysis_done
         FROM investigation_artifacts"
    )->fetch() ?: [];
    $result['artifacts'] = [
        'total' => (int) ($artifactRow['total'] ?? 0),
        'commands' => (int) ($artifactRow['commands'] ?? 0),
        'urls' => (int) ($artifactRow['urls'] ?? 0),
        'domains' => (int) ($artifactRow['domains'] ?? 0),
        'ips' => (int) ($artifactRow['ips'] ?? 0),
        'files' => (int) ($artifactRow['files'] ?? 0),
        'hashes' => (int) ($artifactRow['hashes'] ?? 0),
        'fetched_payloads' => (int) ($artifactRow['fetched_payloads'] ?? 0),
        'analysis_done' => (int) ($artifactRow['analysis_done'] ?? 0),
    ];

    if (clickfix_has_table($pdo, 'reports')) {
        $alertRow = $pdo->query(
            "SELECT SUM(CASE WHEN review_status = 'accepted' THEN 1 ELSE 0 END) AS accepted,
                    SUM(CASE WHEN review_status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                    SUM(CASE WHEN review_status = 'allowlisted' THEN 1 ELSE 0 END) AS allowlisted,
                    SUM(CASE WHEN review_status IS NULL OR TRIM(review_status) = '' OR LOWER(TRIM(review_status)) = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) AS blocked
             FROM reports"
        )->fetch() ?: [];
        $result['alerts'] = [
            'pending' => (int) ($alertRow['pending'] ?? 0),
            'accepted' => (int) ($alertRow['accepted'] ?? 0),
            'rejected' => (int) ($alertRow['rejected'] ?? 0),
            'allowlisted' => (int) ($alertRow['allowlisted'] ?? 0),
            'blocked' => (int) ($alertRow['blocked'] ?? 0),
        ];
    }

    if (clickfix_has_table($pdo, 'investigation_graphs')) {
        $invRow = $pdo->query(
            "SELECT COUNT(DISTINCT ia.graph_id) AS with_pipeline,
                    SUM(CASE WHEN LOWER(COALESCE(ig.verdict, '')) = 'malicious' THEN 1 ELSE 0 END) AS malicious,
                    SUM(CASE WHEN LOWER(COALESCE(ig.verdict, '')) = 'suspicious' THEN 1 ELSE 0 END) AS suspicious,
                    SUM(CASE WHEN LOWER(COALESCE(ig.verdict, '')) = 'investigating' THEN 1 ELSE 0 END) AS investigating,
                    SUM(CASE WHEN LOWER(COALESCE(ig.verdict, '')) = 'clean' THEN 1 ELSE 0 END) AS clean,
                    SUM(CASE WHEN LOWER(COALESCE(ig.verdict, '')) = 'unknown' THEN 1 ELSE 0 END) AS unknown
             FROM investigation_artifacts ia
             LEFT JOIN investigation_graphs ig ON ig.id = ia.graph_id"
        )->fetch() ?: [];
        $result['investigations'] = [
            'with_pipeline' => (int) ($invRow['with_pipeline'] ?? 0),
            'malicious' => (int) ($invRow['malicious'] ?? 0),
            'suspicious' => (int) ($invRow['suspicious'] ?? 0),
            'investigating' => (int) ($invRow['investigating'] ?? 0),
            'clean' => (int) ($invRow['clean'] ?? 0),
            'unknown' => (int) ($invRow['unknown'] ?? 0),
        ];
    }

    $stageRows = $pdo->query(
        "SELECT ia.graph_id,
                MAX(COALESCE(ia.depth, 0)) + 1 AS stages,
                COUNT(*) AS artifact_total,
                COALESCE(ig.title, 'Investigation') AS title,
                COALESCE(ig.site_domain, '') AS site_domain
         FROM investigation_artifacts ia
         LEFT JOIN investigation_graphs ig ON ig.id = ia.graph_id
         GROUP BY ia.graph_id
         ORDER BY stages DESC, artifact_total DESC, ia.graph_id DESC"
    )->fetchAll() ?: [];
    $stageDistribution = [];
    $stageSum = 0;
    foreach ($stageRows as $row) {
        $stages = max(1, (int) ($row['stages'] ?? 1));
        $stageSum += $stages;
        $stageDistribution[$stages] = ($stageDistribution[$stages] ?? 0) + 1;
    }
    ksort($stageDistribution);
    $stageDistRows = [];
    foreach ($stageDistribution as $stage => $count) {
        $stageDistRows[] = ['stage_count' => (int) $stage, 'investigations' => (int) $count];
    }
    $result['stages']['avg'] = !empty($stageRows) ? round($stageSum / count($stageRows), 2) : 0.0;
    $result['stages']['max'] = !empty($stageRows) ? max(array_map(static fn(array $r): int => max(1, (int) ($r['stages'] ?? 1)), $stageRows)) : 0;
    $result['stages']['distribution'] = $stageDistRows;
    $result['stages']['top_chains'] = array_slice(array_map(static function (array $row): array {
        return [
            'graph_id' => (int) ($row['graph_id'] ?? 0),
            'title' => (string) ($row['title'] ?? 'Investigation'),
            'site_domain' => (string) ($row['site_domain'] ?? ''),
            'stages' => max(1, (int) ($row['stages'] ?? 1)),
            'artifact_total' => (int) ($row['artifact_total'] ?? 0),
        ];
    }, $stageRows), 0, $topLimit);

    $artifactTypeRows = $pdo->query(
        "SELECT artifact_kind, COUNT(*) AS total
         FROM investigation_artifacts
         GROUP BY artifact_kind
         ORDER BY total DESC, artifact_kind ASC
         LIMIT 20"
    )->fetchAll() ?: [];
    $result['artifact_types'] = array_map(static function (array $row): array {
        return [
            'kind' => (string) ($row['artifact_kind'] ?? 'unknown'),
            'total' => (int) ($row['total'] ?? 0),
        ];
    }, $artifactTypeRows);

    $commandRows = $pdo->query(
        "SELECT label, COUNT(*) AS total
         FROM investigation_artifacts
         WHERE artifact_kind = 'command'
         GROUP BY label
         ORDER BY total DESC, label ASC
         LIMIT 15"
    )->fetchAll() ?: [];
    $result['top_commands'] = array_map(static function (array $row): array {
        return [
            'label' => (string) ($row['label'] ?? ''),
            'total' => (int) ($row['total'] ?? 0),
        ];
    }, $commandRows);

    $tagRows = $pdo->query("SELECT tags_json FROM investigation_artifacts WHERE tags_json IS NOT NULL AND TRIM(tags_json) != ''")->fetchAll() ?: [];
    $tagCounts = [];
    foreach ($tagRows as $row) {
        $decoded = json_decode((string) ($row['tags_json'] ?? '[]'), true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach ($decoded as $tag) {
            $clean = strtolower(trim((string) $tag));
            if ($clean === '') {
                continue;
            }
            $tagCounts[$clean] = ($tagCounts[$clean] ?? 0) + 1;
        }
    }
    arsort($tagCounts);
    $interestingTags = ['clickfix', 'downloader', 'loader', 'infostealer', 'ransomware', 'trojan', 'malicious', 'suspicious'];
    $malwareTypes = [];
    foreach ($interestingTags as $tag) {
        if (!empty($tagCounts[$tag])) {
            $malwareTypes[] = ['tag' => $tag, 'total' => (int) $tagCounts[$tag]];
        }
    }
    foreach ($tagCounts as $tag => $count) {
        if (count($malwareTypes) >= $topLimit) {
            break;
        }
        if (in_array($tag, $interestingTags, true)) {
            continue;
        }
        $malwareTypes[] = ['tag' => $tag, 'total' => (int) $count];
    }
    $result['malware_types'] = $malwareTypes;

    $recentJobRows = $pdo->query(
        "SELECT j.id, j.graph_id, j.status, j.mode, j.created_at, j.updated_at, j.processed_artifacts, j.last_error,
                COALESCE(ig.title, 'Investigation') AS title,
                COALESCE(ig.site_domain, '') AS site_domain
         FROM investigation_analysis_jobs j
         LEFT JOIN investigation_graphs ig ON ig.id = j.graph_id
         ORDER BY j.id DESC
         LIMIT 20"
    )->fetchAll() ?: [];
    $result['recent_jobs'] = array_map(static function (array $row): array {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'graph_id' => (int) ($row['graph_id'] ?? 0),
            'status' => (string) ($row['status'] ?? 'queued'),
            'mode' => (string) ($row['mode'] ?? 'alert_correlation'),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'processed_artifacts' => (int) ($row['processed_artifacts'] ?? 0),
            'last_error' => (string) ($row['last_error'] ?? ''),
            'title' => (string) ($row['title'] ?? 'Investigation'),
            'site_domain' => (string) ($row['site_domain'] ?? ''),
        ];
    }, $recentJobRows);

    return $result;
}

function clickfix_has_table(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :name");
    $stmt->execute([':name' => $table]);
    return (int) $stmt->fetchColumn() === 1;
}

function clickfix_is_public_ip(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

function clickfix_fetch_json(string $url, int $timeoutSeconds = 3): ?array
{
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => max(1, min(8, $timeoutSeconds)),
            'ignore_errors' => true,
            'header' => "User-Agent: ClickFixMitigator/1.0\r\nAccept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $ctx);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function clickfix_http_request(
    string $url,
    string $method = 'GET',
    array $headers = [],
    ?string $body = null,
    int $timeoutSeconds = 8
): array {
    $method = strtoupper(trim($method));
    if (!in_array($method, ['GET', 'POST'], true)) {
        $method = 'GET';
    }

    $headerLines = [];
    foreach ($headers as $name => $value) {
        $key = trim((string) $name);
        if ($key === '') {
            continue;
        }
        $headerLines[] = $key . ': ' . trim((string) $value);
    }

    $options = [
        'http' => [
            'method' => $method,
            'timeout' => max(2, min(20, $timeoutSeconds)),
            'ignore_errors' => true,
            'header' => implode("\r\n", $headerLines) . ($headerLines ? "\r\n" : ''),
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ];
    if ($method === 'POST') {
        $options['http']['content'] = (string) ($body ?? '');
    }

    $context = stream_context_create($options);
    $raw = @file_get_contents($url, false, $context);
    $responseHeaders = is_array($http_response_header ?? null) ? $http_response_header : [];
    $statusCode = 0;
    if (!empty($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', (string) $responseHeaders[0], $m) === 1) {
        $statusCode = (int) $m[1];
    }
    return [
        'ok' => $raw !== false,
        'status' => $statusCode,
        'body' => is_string($raw) ? $raw : '',
        'headers' => $responseHeaders,
    ];
}

function clickfix_http_multipart_request(
    string $url,
    array $headers = [],
    array $fields = [],
    array $files = [],
    int $timeoutSeconds = 20
): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $postFields = [];
        foreach ($fields as $name => $value) {
            $postFields[(string) $name] = (string) $value;
        }
        foreach ($files as $name => $file) {
            $path = (string) ($file['path'] ?? '');
            if ($path === '' || !is_file($path)) {
                continue;
            }
            $mime = (string) ($file['mime'] ?? 'application/octet-stream');
            $filename = (string) ($file['filename'] ?? basename($path));
            $postFields[(string) $name] = new CURLFile($path, $mime, $filename);
        }
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = trim((string) $name) . ': ' . trim((string) $value);
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => max(5, min(60, $timeoutSeconds)),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'ok' => $raw !== false && $status >= 200 && $status < 300,
            'status' => $status,
            'body' => is_string($raw) ? $raw : '',
            'headers' => [],
            'error' => $error,
        ];
    }

    $boundary = '----ClickFixBoundary' . bin2hex(random_bytes(8));
    $eol = "\r\n";
    $body = '';
    foreach ($fields as $name => $value) {
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="' . str_replace('"', '', (string) $name) . '"' . $eol . $eol;
        $body .= (string) $value . $eol;
    }
    foreach ($files as $name => $file) {
        $path = (string) ($file['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            continue;
        }
        $bytes = @file_get_contents($path);
        if (!is_string($bytes)) {
            continue;
        }
        $mime = (string) ($file['mime'] ?? 'application/octet-stream');
        $filename = str_replace('"', '', (string) ($file['filename'] ?? basename($path)));
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="' . str_replace('"', '', (string) $name) . '"; filename="' . $filename . '"' . $eol;
        $body .= 'Content-Type: ' . $mime . $eol . $eol;
        $body .= $bytes . $eol;
    }
    $body .= '--' . $boundary . '--' . $eol;

    $headers['Content-Type'] = 'multipart/form-data; boundary=' . $boundary;
    $headers['Content-Length'] = (string) strlen($body);
    return clickfix_http_request($url, 'POST', $headers, $body, $timeoutSeconds);
}

function clickfix_fetch_html_lang(string $hostname, int $timeoutSeconds = 3): string
{
    $host = clickfix_normalize_domain($hostname);
    if ($host === '') {
        return '';
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => max(1, min(8, $timeoutSeconds)),
            'ignore_errors' => true,
            'header' => "User-Agent: ClickFixMitigator/1.0\r\nAccept: text/html,*/*;q=0.8\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    foreach (['https://' . $host . '/', 'http://' . $host . '/'] as $url) {
        $raw = @file_get_contents($url, false, $ctx);
        if (!is_string($raw) || $raw === '') {
            continue;
        }
        $sample = substr($raw, 0, 150000);
        if (preg_match('/<html[^>]*\blang\s*=\s*["\']?([a-zA-Z-]{2,12})/i', $sample, $m)) {
            return strtolower(substr((string) $m[1], 0, 12));
        }
    }
    return '';
}

function clickfix_ml_keyword_hits_empty(array $keywords): array
{
    $hits = [];
    foreach ($keywords as $keyword) {
        $hits[(string) $keyword] = 0;
    }
    return $hits;
}

function clickfix_ml_host_is_public(string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === '' || $host === 'localhost' || clickfix_str_starts_with($host, 'localhost.')) {
        return false;
    }
    if (preg_match('/(?:^|\.)local$/', $host) || preg_match('/(?:^|\.)internal$/', $host)) {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return clickfix_is_public_ip($host);
    }

    $public = false;
    $records = @dns_get_record($host, DNS_A + DNS_AAAA);
    if (is_array($records) && !empty($records)) {
        foreach ($records as $record) {
            $ip = (string) ($record['ip'] ?? $record['ipv6'] ?? '');
            if ($ip !== '' && clickfix_is_public_ip($ip)) {
                $public = true;
                break;
            }
        }
    } else {
        $fallback = @gethostbynamel($host);
        if (is_array($fallback)) {
            foreach ($fallback as $ip) {
                if (clickfix_is_public_ip((string) $ip)) {
                    $public = true;
                    break;
                }
            }
        }
    }
    return $public;
}

function clickfix_ml_url_allowed(string $url): bool
{
    $safe = clickfix_sanitize_http_url($url);
    if ($safe === '') {
        return false;
    }
    $parts = parse_url($safe);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }
    $host = strtolower((string) ($parts['host'] ?? ''));
    return clickfix_ml_host_is_public($host);
}

function clickfix_ml_join_url(string $baseUrl, string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    $raw = preg_replace('/#.*$/', '', $raw) ?? $raw;
    $lower = strtolower($raw);
    if (
        clickfix_str_starts_with($lower, 'javascript:')
        || clickfix_str_starts_with($lower, 'data:')
        || clickfix_str_starts_with($lower, 'mailto:')
        || clickfix_str_starts_with($lower, 'tel:')
    ) {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $raw)) {
        return clickfix_sanitize_http_url($raw);
    }
    $base = parse_url(clickfix_sanitize_http_url($baseUrl));
    if (!is_array($base)) {
        return '';
    }
    $scheme = (string) ($base['scheme'] ?? 'https');
    $host = (string) ($base['host'] ?? '');
    if ($host === '') {
        return '';
    }
    $port = isset($base['port']) ? (':' . (int) $base['port']) : '';

    if (clickfix_str_starts_with($raw, '//')) {
        return clickfix_sanitize_http_url($scheme . ':' . $raw);
    }

    if (clickfix_str_starts_with($raw, '/')) {
        return clickfix_sanitize_http_url($scheme . '://' . $host . $port . $raw);
    }

    $basePath = (string) ($base['path'] ?? '/');
    $dir = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
    if ($dir === '' || $dir === '.') {
        $dir = '';
    }
    $joined = $scheme . '://' . $host . $port . '/' . ltrim($dir . '/' . $raw, '/');
    return clickfix_sanitize_http_url($joined);
}

function clickfix_ml_extract_resource_links(string $html): array
{
    $links = [];
    if ($html === '') {
        return $links;
    }
    $patterns = [
        '/<(?:script|img|iframe|source)[^>]+\bsrc\s*=\s*["\']([^"\']+)["\']/i',
        '/<(?:link|a)[^>]+\bhref\s*=\s*["\']([^"\']+)["\']/i',
    ];
    foreach ($patterns as $pattern) {
        if (!preg_match_all($pattern, $html, $matches)) {
            continue;
        }
        foreach ($matches[1] ?? [] as $candidate) {
            $candidate = substr(trim((string) $candidate), 0, 1000);
            if ($candidate !== '' && !in_array($candidate, $links, true)) {
                $links[] = $candidate;
            }
            if (count($links) >= 150) {
                break 2;
            }
        }
    }
    return $links;
}

function clickfix_ml_fetch_text(string $url, int $timeoutSeconds = 3, int $maxBytes = 220000): array
{
    $result = [
        'ok' => false,
        'status' => 0,
        'content_type' => '',
        'text' => '',
        'links' => [],
    ];
    $safeUrl = clickfix_sanitize_http_url($url);
    if ($safeUrl === '') {
        return $result;
    }
    if (!clickfix_ml_url_allowed($safeUrl)) {
        $result['status'] = 0;
        return $result;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => max(1, min(8, $timeoutSeconds)),
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 2,
            'header' => "User-Agent: ClickFixMitigator-ML/1.0\r\nAccept: text/html,text/plain,application/javascript,text/css,application/json,*/*;q=0.1\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $raw = @file_get_contents($safeUrl, false, $ctx, 0, max(20000, min(400000, $maxBytes)));
    $responseHeaders = is_array($http_response_header ?? null) ? $http_response_header : [];
    if (!is_string($raw) || $raw === '') {
        return $result;
    }

    $status = 0;
    $contentType = '';
    foreach ($responseHeaders as $headerLine) {
        $headerLine = trim((string) $headerLine);
        if ($headerLine === '') {
            continue;
        }
        if (preg_match('/^HTTP\/\d(?:\.\d)?\s+(\d{3})/i', $headerLine, $m)) {
            $status = (int) ($m[1] ?? 0);
            continue;
        }
        if (stripos($headerLine, 'content-type:') === 0) {
            $contentType = strtolower(trim(substr($headerLine, strlen('content-type:'))));
        }
    }
    if ($status < 200 || $status >= 400) {
        $result['status'] = $status;
        return $result;
    }

    $textLike = (
        $contentType === ''
        || strpos($contentType, 'text/') !== false
        || strpos($contentType, 'json') !== false
        || strpos($contentType, 'javascript') !== false
        || strpos($contentType, 'xml') !== false
        || strpos($contentType, 'svg') !== false
    );
    if (!$textLike) {
        $result['status'] = $status;
        $result['content_type'] = $contentType;
        return $result;
    }

    $sample = substr($raw, 0, max(20000, min(400000, $maxBytes)));
    $isHtml = strpos($contentType, 'text/html') !== false || stripos($sample, '<html') !== false || stripos($sample, '<!doctype html') !== false;
    $text = $sample;
    $links = [];
    if ($isHtml) {
        $links = clickfix_ml_extract_resource_links($sample);
        $clean = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $sample);
        $clean = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', (string) $clean);
        $text = strip_tags((string) $clean);
    }
    $text = strtolower(trim((string) $text));
    if ($text === '') {
        return $result;
    }

    $result['ok'] = true;
    $result['status'] = $status;
    $result['content_type'] = $contentType;
    $result['text'] = $text;
    $result['links'] = $links;
    return $result;
}

function clickfix_ml_keyword_hits_from_text(string $text, array $keywords): array
{
    $hits = clickfix_ml_keyword_hits_empty($keywords);
    if ($text === '') {
        return $hits;
    }
    $lower = strtolower($text);
    foreach ($keywords as $keyword) {
        $key = (string) $keyword;
        $needle = strtolower($key);
        if ($needle === '') {
            continue;
        }
        $count = substr_count($lower, $needle);
        if ($count > 0) {
            $hits[$key] = min(50, $count);
        }
    }
    return $hits;
}

function clickfix_ml_collect_keyword_enrichment(string $url, array $keywords): array
{
    $hits = clickfix_ml_keyword_hits_empty($keywords);
    $status = 'skip';
    $resourceCount = 0;
    $maxResources = 8;
    $visited = [];

    $primary = clickfix_ml_fetch_text($url, 4, 260000);
    if (empty($primary['ok'])) {
        return [
            'keyword_hits' => $hits,
            'resource_count' => 0,
            'status' => 'fetch_failed',
        ];
    }
    $status = 'ok';
    $resourceCount = 1;
    $visited[$url] = true;
    $firstHits = clickfix_ml_keyword_hits_from_text((string) ($primary['text'] ?? ''), $keywords);
    foreach ($firstHits as $k => $v) {
        $hits[$k] = (int) $v;
    }

    $links = is_array($primary['links'] ?? null) ? $primary['links'] : [];
    foreach ($links as $rawLink) {
        if ($resourceCount >= $maxResources) {
            break;
        }
        $resolved = clickfix_ml_join_url($url, (string) $rawLink);
        if ($resolved === '' || isset($visited[$resolved])) {
            continue;
        }
        $visited[$resolved] = true;
        if (!clickfix_ml_url_allowed($resolved)) {
            continue;
        }
        $resource = clickfix_ml_fetch_text($resolved, 3, 180000);
        if (empty($resource['ok'])) {
            continue;
        }
        $resourceCount++;
        $resourceHits = clickfix_ml_keyword_hits_from_text((string) ($resource['text'] ?? ''), $keywords);
        foreach ($resourceHits as $k => $v) {
            $hits[$k] = min(100, ((int) ($hits[$k] ?? 0)) + (int) $v);
        }
    }

    return [
        'keyword_hits' => $hits,
        'resource_count' => $resourceCount,
        'status' => $status,
    ];
}

function clickfix_ml_keyword_enrichment_cached(PDO $pdo, string $url, array $keywords, int $ttlSeconds = 21600): array
{
    $result = [
        'keyword_hits' => clickfix_ml_keyword_hits_empty($keywords),
        'resource_count' => 0,
        'status' => 'skip',
        'from_cache' => false,
        'used_fetch' => false,
    ];
    $safeUrl = clickfix_sanitize_http_url($url);
    if ($safeUrl === '' || !clickfix_has_table($pdo, 'ml_keyword_enrichment_cache')) {
        return $result;
    }

    $stmt = $pdo->prepare('SELECT checked_at, keyword_hits_json, resource_count, status FROM ml_keyword_enrichment_cache WHERE url = :url LIMIT 1');
    $stmt->execute([':url' => $safeUrl]);
    $row = $stmt->fetch();
    if ($row) {
        $checkedAt = strtotime((string) ($row['checked_at'] ?? ''));
        if ($checkedAt !== false && $checkedAt >= (time() - max(600, $ttlSeconds))) {
            $decoded = json_decode((string) ($row['keyword_hits_json'] ?? '{}'), true);
            if (is_array($decoded)) {
                foreach ($result['keyword_hits'] as $k => $v) {
                    $result['keyword_hits'][$k] = (int) ($decoded[$k] ?? 0);
                }
            }
            $result['resource_count'] = (int) ($row['resource_count'] ?? 0);
            $result['status'] = (string) ($row['status'] ?? 'ok');
            $result['from_cache'] = true;
            return $result;
        }
    }

    $enriched = clickfix_ml_collect_keyword_enrichment($safeUrl, $keywords);
    $result['keyword_hits'] = is_array($enriched['keyword_hits'] ?? null) ? $enriched['keyword_hits'] : $result['keyword_hits'];
    $result['resource_count'] = (int) ($enriched['resource_count'] ?? 0);
    $result['status'] = (string) ($enriched['status'] ?? 'ok');
    $result['used_fetch'] = true;

    try {
        $upsert = $pdo->prepare(
            'INSERT INTO ml_keyword_enrichment_cache (url, checked_at, keyword_hits_json, resource_count, status)
             VALUES (:url, :checked_at, :keyword_hits_json, :resource_count, :status)
             ON CONFLICT(url) DO UPDATE SET
               checked_at = excluded.checked_at,
               keyword_hits_json = excluded.keyword_hits_json,
               resource_count = excluded.resource_count,
               status = excluded.status'
        );
        $upsert->execute([
            ':url' => $safeUrl,
            ':checked_at' => gmdate('c'),
            ':keyword_hits_json' => json_encode($result['keyword_hits'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':resource_count' => $result['resource_count'],
            ':status' => substr($result['status'], 0, 60),
        ]);
    } catch (Throwable $exception) {
        // Ignore cache write errors; enrichment still usable for current request.
    }

    return $result;
}

function clickfix_safe_shell_binary(string $raw): string
{
    $candidate = trim($raw);
    if ($candidate === '') {
        return 'whatweb';
    }
    if (!preg_match('/^[a-zA-Z0-9._:\\\\\/-]+$/', $candidate)) {
        return 'whatweb';
    }
    return $candidate;
}

function clickfix_whatweb_command(): string
{
    return clickfix_safe_shell_binary((string) clickfix_env('CLICKFIX_WHATWEB_BIN', 'whatweb'));
}

function clickfix_strip_ansi(string $text): string
{
    return preg_replace('/\x1B\[[0-9;]*[A-Za-z]/', '', $text) ?? $text;
}

function clickfix_parse_whatweb_line(string $line): array
{
    $line = trim($line);
    $result = [
        'ok' => false,
        'raw_line' => $line,
        'url' => '',
        'status' => '',
        'ip' => '',
        'country_code' => '',
        'country_name' => '',
        'http_server' => '',
        'title' => '',
        'plugins' => [],
        'services' => [],
        'error' => '',
    ];
    if ($line === '') {
        $result['error'] = 'empty_output';
        return $result;
    }
    if (stripos($line, 'ERROR') !== false || stripos($line, 'not found') !== false || stripos($line, 'no se reconoce') !== false) {
        $result['error'] = $line;
        return $result;
    }

    $payload = $line;
    if (preg_match('/^(https?:\/\/[^\s]+)\s+\[([^\]]+)\]\s*(.*)$/i', $line, $m)) {
        $result['url'] = (string) $m[1];
        $result['status'] = trim((string) $m[2]);
        $payload = trim((string) $m[3]);
    }

    $plugins = [];
    $services = [];
    foreach (preg_split('/\s*,\s*/', $payload) as $token) {
        $token = trim((string) $token);
        if ($token === '') {
            continue;
        }
        if (!preg_match('/^([A-Za-z0-9._-]+)(?:\[(.+)\])?$/', $token, $m)) {
            $services[] = $token;
            continue;
        }
        $name = (string) $m[1];
        $values = [];
        if (isset($m[2]) && $m[2] !== '') {
            preg_match_all('/\[?([^\[\]]+)\]?/', '[' . $m[2] . ']', $mm);
            if (!empty($mm[1])) {
                foreach ($mm[1] as $vv) {
                    $clean = trim((string) $vv);
                    if ($clean !== '') {
                        $values[] = $clean;
                    }
                }
            }
        }
        $plugins[$name] = $values;
        $services[] = $name;
    }

    $result['plugins'] = $plugins;
    $result['services'] = array_values(array_unique($services));
    if (!empty($plugins['IP'][0]) && filter_var((string) $plugins['IP'][0], FILTER_VALIDATE_IP)) {
        $result['ip'] = (string) $plugins['IP'][0];
    }
    if (!empty($plugins['Country'][0])) {
        $result['country_name'] = (string) $plugins['Country'][0];
    }
    if (!empty($plugins['Country'][1])) {
        $code = strtoupper(substr((string) $plugins['Country'][1], 0, 2));
        if (preg_match('/^[A-Z]{2}$/', $code)) {
            $result['country_code'] = $code;
        }
    }
    if (!empty($plugins['HTTPServer'][0])) {
        $result['http_server'] = (string) $plugins['HTTPServer'][0];
    } elseif (in_array('LiteSpeed', $result['services'], true)) {
        $result['http_server'] = 'LiteSpeed';
    }
    if (!empty($plugins['Title'][0])) {
        $result['title'] = (string) $plugins['Title'][0];
    }
    $result['ok'] = true;
    return $result;
}

function clickfix_parse_whatweb_verbose_output(string $raw): array
{
    $clean = clickfix_strip_ansi($raw);
    $result = [
        'ok' => false,
        'raw_line' => '',
        'url' => '',
        'status' => '',
        'ip' => '',
        'country_code' => '',
        'country_name' => '',
        'http_server' => '',
        'title' => '',
        'plugins' => [],
        'services' => [],
        'error' => '',
    ];
    if (!is_string($clean) || trim($clean) === '') {
        $result['error'] = 'empty_output';
        return $result;
    }
    if (!preg_match('/WhatWeb report for\s+(\S+)/i', $clean, $mUrl)) {
        $result['error'] = 'verbose_format_not_found';
        return $result;
    }
    $result['url'] = (string) $mUrl[1];
    if (preg_match('/^\s*Status\s*:\s*(.+)$/mi', $clean, $m)) {
        $result['status'] = trim((string) $m[1]);
    }
    if (preg_match('/^\s*Title\s*:\s*(.+)$/mi', $clean, $m)) {
        $result['title'] = trim((string) $m[1]);
    }
    if (preg_match('/^\s*IP\s*:\s*([0-9a-fA-F:.]+)\s*$/mi', $clean, $m) && filter_var((string) $m[1], FILTER_VALIDATE_IP)) {
        $result['ip'] = trim((string) $m[1]);
    }
    if (preg_match('/^\s*Country\s*:\s*([^,\r\n]+)(?:,\s*([A-Za-z]{2}))?/mi', $clean, $m)) {
        $result['country_name'] = trim((string) $m[1]);
        if (!empty($m[2])) {
            $cc = strtoupper(trim((string) $m[2]));
            if (preg_match('/^[A-Z]{2}$/', $cc)) {
                $result['country_code'] = $cc;
            }
        }
    }
    if (preg_match('/^\s*Summary\s*:\s*(.+)$/mi', $clean, $m)) {
        $summary = trim((string) $m[1]);
        $result['raw_line'] = $summary;
        $summaryParsed = clickfix_parse_whatweb_line(($result['url'] !== '' ? ($result['url'] . ' [' . ($result['status'] !== '' ? $result['status'] : 'OK') . '] ') : '') . $summary);
        if (!empty($summaryParsed['plugins']) && is_array($summaryParsed['plugins'])) {
            $result['plugins'] = $summaryParsed['plugins'];
        }
        if (!empty($summaryParsed['services']) && is_array($summaryParsed['services'])) {
            $result['services'] = $summaryParsed['services'];
        }
        if ($result['http_server'] === '' && !empty($summaryParsed['http_server'])) {
            $result['http_server'] = (string) $summaryParsed['http_server'];
        }
    }
    if ($result['http_server'] === '' && preg_match('/^\s*Server\s*:\s*(.+)$/mi', $clean, $m)) {
        $result['http_server'] = trim((string) $m[1]);
    }
    $result['ok'] = true;
    return $result;
}

function clickfix_parse_whatweb_output(string $raw): array
{
    $clean = clickfix_strip_ansi($raw);
    if (stripos($clean, 'WhatWeb report for') !== false) {
        $verbose = clickfix_parse_whatweb_verbose_output($clean);
        if (!empty($verbose['ok'])) {
            return $verbose;
        }
    }
    $line = '';
    foreach (preg_split('/\R+/', $clean) as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            continue;
        }
        if (preg_match('/^(https?:\/\/|[A-Za-z0-9.-]+\s+\[[^\]]+\])/', $candidate)) {
            $line = $candidate;
            break;
        }
        if ($line === '') {
            $line = $candidate;
        }
    }
    return clickfix_parse_whatweb_line($line);
}

function clickfix_whatweb_scan_safe(string $input): array
{
    $hostname = clickfix_normalize_domain($input);
    if ($hostname === '') {
        return [
            'ok' => false,
            'error' => 'invalid_hostname',
            'hostname' => '',
            'raw_line' => '',
            'plugins' => [],
            'services' => [],
            'status' => '',
            'ip' => '',
            'country_code' => '',
            'country_name' => '',
            'http_server' => '',
            'title' => '',
        ];
    }
    $binary = clickfix_whatweb_command();
    $target = 'https://' . $hostname . '/';
    $timeoutSeconds = (int) clickfix_env('CLICKFIX_WHATWEB_TIMEOUT_SECONDS', '12');
    $timeoutSeconds = max(3, min(30, $timeoutSeconds));
    $aggression = (int) clickfix_env('CLICKFIX_WHATWEB_AGGRESSION', '1');
    if (!in_array($aggression, [1, 3, 4], true)) {
        $aggression = 1;
    }

    $base = escapeshellcmd($binary);
    $targetArg = escapeshellarg($target);
    $attempts = [
        $base . ' -a ' . $aggression . ' --no-errors --color=never --max-redirect=2 ' . $targetArg . ' 2>&1',
        $base . ' -a ' . $aggression . ' ' . $targetArg . ' 2>&1',
        $base . ' -a ' . $aggression . ' -v ' . $targetArg . ' 2>&1',
    ];
    $raw = '';
    foreach ($attempts as $attempt) {
        $command = $attempt;
        if (stripos(PHP_OS, 'WIN') !== 0) {
            $command = 'timeout ' . $timeoutSeconds . 's ' . $attempt;
        }
        $try = @shell_exec($command);
        if (!is_string($try) || trim($try) === '') {
            continue;
        }
        $raw = $try;
        if (stripos($raw, 'Agression level must be') !== false || stripos($raw, 'invalid option') !== false) {
            continue;
        }
        break;
    }
    if (!is_string($raw) || trim($raw) === '') {
        return [
            'ok' => false,
            'error' => 'whatweb_no_output',
            'hostname' => $hostname,
            'raw_line' => '',
            'plugins' => [],
            'services' => [],
            'status' => '',
            'ip' => '',
            'country_code' => '',
            'country_name' => '',
            'http_server' => '',
            'title' => '',
        ];
    }
    $parsed = clickfix_parse_whatweb_output($raw);
    $parsed['hostname'] = $hostname;
    return $parsed;
}

function clickfix_whatweb_cached_lookup(PDO $pdo, string $hostname, int &$scanBudget = 0): array
{
    $host = clickfix_normalize_domain($hostname);
    $empty = [
        'ok' => false,
        'hostname' => $host,
        'raw_line' => '',
        'status' => '',
        'ip' => '',
        'country_code' => '',
        'country_name' => '',
        'http_server' => '',
        'title' => '',
        'plugins' => [],
        'services' => [],
        'error' => '',
    ];
    if ($host === '' || !clickfix_has_table($pdo, 'whatweb_cache')) {
        return $empty;
    }

    $stmt = $pdo->prepare('SELECT * FROM whatweb_cache WHERE hostname = :hostname LIMIT 1');
    $stmt->execute([':hostname' => $host]);
    $row = $stmt->fetch();
    if ($row) {
        $age = time() - (int) strtotime((string) ($row['checked_at'] ?? ''));
        if ($age < 3 * 86400) {
            $plugins = json_decode((string) ($row['plugins_json'] ?? '{}'), true);
            $services = json_decode((string) ($row['services_json'] ?? '[]'), true);
            return [
                'ok' => empty($row['error_text']),
                'hostname' => $host,
                'raw_line' => (string) ($row['raw_line'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'ip' => (string) ($row['ip'] ?? ''),
                'country_code' => strtoupper(substr((string) ($row['country_code'] ?? ''), 0, 2)),
                'country_name' => (string) ($row['country_name'] ?? ''),
                'http_server' => (string) ($row['http_server'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'plugins' => is_array($plugins) ? $plugins : [],
                'services' => is_array($services) ? array_values(array_map('strval', $services)) : [],
                'error' => (string) ($row['error_text'] ?? ''),
            ];
        }
    }

    if ($scanBudget <= 0) {
        if ($row) {
            $plugins = json_decode((string) ($row['plugins_json'] ?? '{}'), true);
            $services = json_decode((string) ($row['services_json'] ?? '[]'), true);
            return [
                'ok' => empty($row['error_text']),
                'hostname' => $host,
                'raw_line' => (string) ($row['raw_line'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'ip' => (string) ($row['ip'] ?? ''),
                'country_code' => strtoupper(substr((string) ($row['country_code'] ?? ''), 0, 2)),
                'country_name' => (string) ($row['country_name'] ?? ''),
                'http_server' => (string) ($row['http_server'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'plugins' => is_array($plugins) ? $plugins : [],
                'services' => is_array($services) ? array_values(array_map('strval', $services)) : [],
                'error' => (string) ($row['error_text'] ?? ''),
            ];
        }
        return $empty;
    }
    $scanBudget--;
    $scan = clickfix_whatweb_scan_safe($host);
    $upsert = $pdo->prepare(
        'INSERT INTO whatweb_cache (hostname, checked_at, raw_line, status, ip, country_code, country_name, http_server, title, plugins_json, services_json, error_text)
         VALUES (:hostname, :checked_at, :raw_line, :status, :ip, :country_code, :country_name, :http_server, :title, :plugins_json, :services_json, :error_text)
         ON CONFLICT(hostname) DO UPDATE SET
           checked_at = excluded.checked_at,
           raw_line = excluded.raw_line,
           status = excluded.status,
           ip = excluded.ip,
           country_code = excluded.country_code,
           country_name = excluded.country_name,
           http_server = excluded.http_server,
           title = excluded.title,
           plugins_json = excluded.plugins_json,
           services_json = excluded.services_json,
           error_text = excluded.error_text'
    );
    $upsert->execute([
        ':hostname' => $host,
        ':checked_at' => gmdate('c'),
        ':raw_line' => (string) ($scan['raw_line'] ?? ''),
        ':status' => (string) ($scan['status'] ?? ''),
        ':ip' => (string) ($scan['ip'] ?? ''),
        ':country_code' => strtoupper(substr((string) ($scan['country_code'] ?? ''), 0, 2)),
        ':country_name' => (string) ($scan['country_name'] ?? ''),
        ':http_server' => (string) ($scan['http_server'] ?? ''),
        ':title' => (string) ($scan['title'] ?? ''),
        ':plugins_json' => json_encode($scan['plugins'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':services_json' => json_encode($scan['services'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':error_text' => (string) ($scan['error'] ?? ''),
    ]);
    return $scan;
}

function clickfix_country_geo_lookup(PDO $pdo, string $countryCode, bool $allowRemote = true): ?array
{
    $countryCode = strtoupper(substr(trim($countryCode), 0, 2));
    if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
        return null;
    }
    if (!clickfix_has_table($pdo, 'geo_country_cache')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT country_name, latitude, longitude, languages_json, updated_at FROM geo_country_cache WHERE country_code = :code LIMIT 1');
    $stmt->execute([':code' => $countryCode]);
    $row = $stmt->fetch();
    if ($row) {
        $age = time() - (int) strtotime((string) ($row['updated_at'] ?? ''));
        if ($age < 90 * 86400) {
            $languages = json_decode((string) ($row['languages_json'] ?? '[]'), true);
            return [
                'country_code' => $countryCode,
                'country_name' => (string) ($row['country_name'] ?? $countryCode),
                'lat' => isset($row['latitude']) ? (float) $row['latitude'] : 0.0,
                'lon' => isset($row['longitude']) ? (float) $row['longitude'] : 0.0,
                'languages' => is_array($languages) ? array_values(array_map('strval', $languages)) : [],
            ];
        }
    }
    if (!$allowRemote) {
        return $row ? [
            'country_code' => $countryCode,
            'country_name' => (string) ($row['country_name'] ?? $countryCode),
            'lat' => isset($row['latitude']) ? (float) $row['latitude'] : 0.0,
            'lon' => isset($row['longitude']) ? (float) $row['longitude'] : 0.0,
            'languages' => [],
        ] : null;
    }

    $remote = clickfix_fetch_json('https://restcountries.com/v3.1/alpha/' . rawurlencode($countryCode) . '?fields=cca2,name,latlng,languages', 4);
    if (!is_array($remote) || empty($remote[0]) || !is_array($remote[0])) {
        return $row ? [
            'country_code' => $countryCode,
            'country_name' => (string) ($row['country_name'] ?? $countryCode),
            'lat' => isset($row['latitude']) ? (float) $row['latitude'] : 0.0,
            'lon' => isset($row['longitude']) ? (float) $row['longitude'] : 0.0,
            'languages' => [],
        ] : null;
    }
    $entry = $remote[0];
    $latlng = is_array($entry['latlng'] ?? null) ? $entry['latlng'] : [];
    $lat = isset($latlng[0]) ? (float) $latlng[0] : 0.0;
    $lon = isset($latlng[1]) ? (float) $latlng[1] : 0.0;
    $countryName = (string) ($entry['name']['common'] ?? $countryCode);
    $languagesRaw = is_array($entry['languages'] ?? null) ? $entry['languages'] : [];
    $languages = [];
    foreach ($languagesRaw as $langName) {
        $langName = trim((string) $langName);
        if ($langName !== '' && !in_array($langName, $languages, true)) {
            $languages[] = $langName;
        }
    }
    $upsert = $pdo->prepare(
        'INSERT INTO geo_country_cache (country_code, country_name, latitude, longitude, languages_json, updated_at)
         VALUES (:code, :name, :lat, :lon, :langs, :updated_at)
         ON CONFLICT(country_code) DO UPDATE SET
           country_name = excluded.country_name,
           latitude = excluded.latitude,
           longitude = excluded.longitude,
           languages_json = excluded.languages_json,
           updated_at = excluded.updated_at'
    );
    $upsert->execute([
        ':code' => $countryCode,
        ':name' => $countryName,
        ':lat' => $lat,
        ':lon' => $lon,
        ':langs' => json_encode($languages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':updated_at' => gmdate('c'),
    ]);
    return [
        'country_code' => $countryCode,
        'country_name' => $countryName,
        'lat' => $lat,
        'lon' => $lon,
        'languages' => $languages,
    ];
}

function clickfix_domain_intel_lookup(PDO $pdo, string $hostname, string $reportedIp = '', string $reportedCountry = '', int &$remoteBudget = 0, int &$whatwebBudget = 0): array
{
    $host = clickfix_normalize_domain($hostname);
    $reportedCountry = strtoupper(substr(trim($reportedCountry), 0, 2));
    $fallback = [
        'hostname' => $host,
        'ip' => '',
        'isp' => '',
        'country_code' => $reportedCountry,
        'country_name' => $reportedCountry,
        'language' => '',
        'http_server' => '',
        'services' => [],
        'lat' => 0.0,
        'lon' => 0.0,
    ];
    if ($host === '' || !clickfix_has_table($pdo, 'domain_intel_cache')) {
        return $fallback;
    }

    $reportedIp = trim($reportedIp);
    if (!filter_var($reportedIp, FILTER_VALIDATE_IP)) {
        $reportedIp = '';
    }

    $stmt = $pdo->prepare('SELECT * FROM domain_intel_cache WHERE hostname = :host LIMIT 1');
    $stmt->execute([':host' => $host]);
    $cached = $stmt->fetch();
    if ($cached) {
        $age = time() - (int) strtotime((string) ($cached['checked_at'] ?? ''));
        $cacheIp = (string) ($cached['ip'] ?? '');
        if ($age < 3 * 86400 && ($reportedIp === '' || $cacheIp === '' || $cacheIp === $reportedIp)) {
            return [
                'hostname' => $host,
                'ip' => $cacheIp,
                'isp' => (string) ($cached['isp'] ?? ''),
                'country_code' => strtoupper(substr((string) ($cached['country_code'] ?? $reportedCountry), 0, 2)),
                'country_name' => (string) ($cached['country_name'] ?? $reportedCountry),
                'language' => (string) ($cached['language'] ?? ''),
                'http_server' => '',
                'services' => [],
                'lat' => isset($cached['latitude']) ? (float) $cached['latitude'] : 0.0,
                'lon' => isset($cached['longitude']) ? (float) $cached['longitude'] : 0.0,
            ];
        }
    }

    $ip = $reportedIp;
    if ($ip === '') {
        $resolved = gethostbyname($host);
        if (is_string($resolved) && filter_var($resolved, FILTER_VALIDATE_IP)) {
            $ip = $resolved;
        }
    }

    $isp = (string) ($cached['isp'] ?? '');
    $countryCode = strtoupper(substr((string) ($cached['country_code'] ?? $reportedCountry), 0, 2));
    $countryName = (string) ($cached['country_name'] ?? $countryCode);
    $lat = isset($cached['latitude']) ? (float) $cached['latitude'] : 0.0;
    $lon = isset($cached['longitude']) ? (float) $cached['longitude'] : 0.0;
    $language = (string) ($cached['language'] ?? '');
    $source = 'cache';

    if ($ip !== '' && clickfix_is_public_ip($ip) && $remoteBudget > 0) {
        $geo = clickfix_fetch_json('https://ipwho.is/' . rawurlencode($ip), 4);
        if (is_array($geo) && !empty($geo['success'])) {
            $countryCode = strtoupper(substr((string) ($geo['country_code'] ?? $countryCode), 0, 2));
            $countryName = (string) ($geo['country'] ?? $countryName);
            $isp = trim((string) (($geo['connection']['isp'] ?? '') ?: ($geo['isp'] ?? $isp)));
            $lat = isset($geo['latitude']) ? (float) $geo['latitude'] : $lat;
            $lon = isset($geo['longitude']) ? (float) $geo['longitude'] : $lon;
            $source = 'ipwhois';
        }
        $remoteBudget--;
    }

    if (($lat === 0.0 && $lon === 0.0) || $countryName === '' || $countryName === $countryCode) {
        $countryInfo = clickfix_country_geo_lookup($pdo, $countryCode, $remoteBudget > 0);
        if ($countryInfo !== null) {
            if ($countryName === '' || $countryName === $countryCode) {
                $countryName = (string) ($countryInfo['country_name'] ?? $countryCode);
            }
            if ($lat === 0.0 && $lon === 0.0) {
                $lat = (float) ($countryInfo['lat'] ?? 0.0);
                $lon = (float) ($countryInfo['lon'] ?? 0.0);
            }
            if ($language === '' && !empty($countryInfo['languages'][0])) {
                $language = (string) $countryInfo['languages'][0];
            }
        }
    }

    if ($language === '' && $remoteBudget > 0 && $host !== '') {
        $langDetected = clickfix_fetch_html_lang($host, 3);
        if ($langDetected !== '') {
            $language = $langDetected;
        }
        $remoteBudget--;
    }

    $whatweb = clickfix_whatweb_cached_lookup($pdo, $host, $whatwebBudget);
    $services = is_array($whatweb['services'] ?? null) ? array_values(array_map('strval', $whatweb['services'])) : [];
    $httpServer = (string) ($whatweb['http_server'] ?? '');
    if ($ip === '' && !empty($whatweb['ip']) && filter_var((string) $whatweb['ip'], FILTER_VALIDATE_IP)) {
        $ip = (string) $whatweb['ip'];
    }
    if ($countryCode === '' && !empty($whatweb['country_code'])) {
        $countryCode = strtoupper(substr((string) $whatweb['country_code'], 0, 2));
    }
    if (($countryName === '' || $countryName === $countryCode) && !empty($whatweb['country_name'])) {
        $countryName = (string) $whatweb['country_name'];
    }

    $upsert = $pdo->prepare(
        'INSERT INTO domain_intel_cache (hostname, ip, isp, country_code, country_name, language, latitude, longitude, source, checked_at)
         VALUES (:hostname, :ip, :isp, :country_code, :country_name, :language, :latitude, :longitude, :source, :checked_at)
         ON CONFLICT(hostname) DO UPDATE SET
           ip = excluded.ip,
           isp = excluded.isp,
           country_code = excluded.country_code,
           country_name = excluded.country_name,
           language = excluded.language,
           latitude = excluded.latitude,
           longitude = excluded.longitude,
           source = excluded.source,
           checked_at = excluded.checked_at'
    );
    $upsert->execute([
        ':hostname' => $host,
        ':ip' => substr($ip, 0, 64),
        ':isp' => substr($isp, 0, 255),
        ':country_code' => substr($countryCode, 0, 2),
        ':country_name' => substr($countryName, 0, 120),
        ':language' => substr($language, 0, 40),
        ':latitude' => $lat,
        ':longitude' => $lon,
        ':source' => substr($source, 0, 40),
        ':checked_at' => gmdate('c'),
    ]);

    return [
        'hostname' => $host,
        'ip' => $ip,
        'isp' => $isp,
        'country_code' => $countryCode,
        'country_name' => $countryName,
        'language' => $language,
        'http_server' => $httpServer,
        'services' => $services,
        'lat' => $lat,
        'lon' => $lon,
    ];
}

function clickfix_home_maps_dataset(PDO $pdo, int $domainLimit = 40): array
{
    $domainLimit = max(10, min(120, $domainLimit));
    $cacheKey = clickfix_cache_key('home_maps_dataset', ['limit' => $domainLimit, 'v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $extensionCountryRows = [];
    $extensionPoints = [];
    $usersTotal = 0;
    $cutoff = gmdate('c', time() - 30 * 86400);
    $extStmt = $pdo->prepare(
        "SELECT country,
                COUNT(DISTINCT COALESCE(NULLIF(client_id, ''), ip, user_agent, 'unknown')) AS users
         FROM reports
         WHERE country IS NOT NULL
           AND country != ''
           AND received_at >= :cutoff
         GROUP BY country
         ORDER BY users DESC
         LIMIT 80"
    );
    $extStmt->execute([':cutoff' => $cutoff]);
    foreach ($extStmt->fetchAll() as $row) {
        $countryCode = strtoupper(substr(trim((string) ($row['country'] ?? '')), 0, 2));
        if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
            continue;
        }
        $users = (int) ($row['users'] ?? 0);
        if ($users <= 0) {
            continue;
        }
        $countryInfo = clickfix_country_geo_lookup($pdo, $countryCode, true);
        if ($countryInfo === null) {
            continue;
        }
        $usersTotal += $users;
        $languages = is_array($countryInfo['languages'] ?? null) ? $countryInfo['languages'] : [];
        $extensionCountryRows[] = [
            'country_code' => $countryCode,
            'country_name' => (string) ($countryInfo['country_name'] ?? $countryCode),
            'users' => $users,
            'languages' => $languages,
        ];
        $extensionPoints[] = [
            'country_code' => $countryCode,
            'country_name' => (string) ($countryInfo['country_name'] ?? $countryCode),
            'users' => $users,
            'lat' => (float) ($countryInfo['lat'] ?? 0.0),
            'lon' => (float) ($countryInfo['lon'] ?? 0.0),
        ];
    }

    $domainsStmt = $pdo->prepare(
        "SELECT hostname,
                COUNT(*) AS hits,
                MAX(received_at) AS last_seen,
                MAX(CASE WHEN ip IS NOT NULL AND ip != '' THEN ip END) AS ip,
                MAX(CASE WHEN country IS NOT NULL AND country != '' THEN country END) AS country
         FROM reports
         WHERE hostname IS NOT NULL
           AND hostname != ''
         GROUP BY hostname
         ORDER BY hits DESC
         LIMIT :limit"
    );
    $domainsStmt->bindValue(':limit', $domainLimit, PDO::PARAM_INT);
    $domainsStmt->execute();
    $domainRowsRaw = $domainsStmt->fetchAll();

    $domainRows = [];
    $websitePoints = [];
    $lookupBudget = 10;
    $whatwebBudget = 8;
    foreach ($domainRowsRaw as $row) {
        $host = clickfix_normalize_domain((string) ($row['hostname'] ?? ''));
        if ($host === '') {
            continue;
        }
        $intel = clickfix_domain_intel_lookup(
            $pdo,
            $host,
            (string) ($row['ip'] ?? ''),
            (string) ($row['country'] ?? ''),
            $lookupBudget,
            $whatwebBudget
        );
        $domainRow = [
            'hostname' => $host,
            'hits' => (int) ($row['hits'] ?? 0),
            'last_seen' => (string) ($row['last_seen'] ?? ''),
            'ip' => (string) ($intel['ip'] ?? ''),
            'isp' => (string) ($intel['isp'] ?? ''),
            'country_code' => (string) ($intel['country_code'] ?? ''),
            'country_name' => (string) ($intel['country_name'] ?? ''),
            'language' => (string) ($intel['language'] ?? ''),
            'http_server' => (string) ($intel['http_server'] ?? ''),
            'services' => is_array($intel['services'] ?? null) ? array_values(array_map('strval', $intel['services'])) : [],
            'lat' => (float) ($intel['lat'] ?? 0.0),
            'lon' => (float) ($intel['lon'] ?? 0.0),
        ];
        $domainRows[] = $domainRow;
        if ($domainRow['lat'] !== 0.0 || $domainRow['lon'] !== 0.0) {
            $websitePoints[] = $domainRow;
        }
    }

    $result = [
        'generated_at' => gmdate('c'),
        'extension_users_total' => $usersTotal,
        'extension_points' => $extensionPoints,
        'extension_country_counts' => $extensionCountryRows,
        'website_points' => $websitePoints,
        'website_rows' => $domainRows,
        'trend' => clickfix_analytics_overview($pdo, 14),
    ];
    clickfix_cache_set($cacheKey, $result, 20);
    return $result;
}

function clickfix_scan_kind_normalize(string $kind): string
{
    $kind = strtolower(trim($kind));
    return in_array($kind, ['before', 'after'], true) ? $kind : '';
}

function clickfix_scan_asset_relative_path(int $reportId, string $kind): ?string
{
    $reportId = (int) $reportId;
    if ($reportId <= 0) {
        return null;
    }
    $kind = clickfix_scan_kind_normalize($kind);
    if ($kind === '') {
        return null;
    }
    $base = dirname(__DIR__) . '/data/scans';
    foreach (['png', 'jpg', 'jpeg', 'webp'] as $ext) {
        $path = $base . '/' . $reportId . '-' . $kind . '.' . $ext;
        if (is_file($path)) {
            return 'data/scans/' . $reportId . '-' . $kind . '.' . $ext;
        }
    }
    return null;
}

function clickfix_scan_asset_absolute_path(int $reportId, string $kind): ?string
{
    $relative = clickfix_scan_asset_relative_path($reportId, $kind);
    if ($relative === null) {
        return null;
    }
    return dirname(__DIR__) . '/' . $relative;
}

function clickfix_scan_asset_storage_dir(): string
{
    return dirname(__DIR__) . '/data/scans';
}

function clickfix_scan_asset_clear_kind_files(int $reportId, string $kind): void
{
    $reportId = (int) $reportId;
    $kind = clickfix_scan_kind_normalize($kind);
    if ($reportId <= 0 || $kind === '') {
        return;
    }
    $dir = clickfix_scan_asset_storage_dir();
    foreach (['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'avif'] as $ext) {
        $path = $dir . '/' . $reportId . '-' . $kind . '.' . $ext;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function clickfix_scan_detect_image_info(string $bytes): ?array
{
    if ($bytes === '') {
        return null;
    }
    if (strlen($bytes) > 8 * 1024 * 1024) {
        return null;
    }

    $prefix = strtolower(substr(ltrim($bytes), 0, 256));
    if (
        str_starts_with($prefix, '<svg')
        || str_starts_with($prefix, '<?xml')
        || str_contains($prefix, '<script')
        || str_contains($prefix, '<?php')
        || str_contains($prefix, '<html')
    ) {
        return null;
    }

    $size = @getimagesizefromstring($bytes);
    if (!is_array($size)) {
        return null;
    }

    $width = (int) ($size[0] ?? 0);
    $height = (int) ($size[1] ?? 0);
    $mime = strtolower((string) ($size['mime'] ?? ''));
    if ($width <= 0 || $height <= 0 || $width > 12000 || $height > 12000) {
        return null;
    }

    $extByMime = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
        'image/avif' => 'avif',
    ];
    $ext = (string) ($extByMime[$mime] ?? '');
    if ($ext === '') {
        return null;
    }

    return [
        'mime' => $mime,
        'ext' => $ext,
        'width' => $width,
        'height' => $height,
    ];
}

function clickfix_scan_write_asset_bytes(int $reportId, string $kind, string $bytes, string $ext): bool
{
    $reportId = (int) $reportId;
    $kind = clickfix_scan_kind_normalize($kind);
    $ext = strtolower(trim($ext));
    if ($reportId <= 0 || $kind === '' || !in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'avif'], true)) {
        return false;
    }
    if ($bytes === '') {
        return false;
    }
    $imageInfo = clickfix_scan_detect_image_info($bytes);
    if ($imageInfo === null) {
        return false;
    }
    $dir = clickfix_scan_asset_storage_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }
    clickfix_scan_asset_clear_kind_files($reportId, $kind);
    $safeExt = (string) ($imageInfo['ext'] ?? $ext);
    $path = $dir . '/' . $reportId . '-' . $kind . '.' . $safeExt;
    return file_put_contents($path, $bytes, LOCK_EX) !== false;
}

function clickfix_scan_asset_info(int $reportId, string $kind): ?array
{
    $path = clickfix_scan_asset_absolute_path($reportId, $kind);
    if ($path === null || !is_file($path)) {
        return null;
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $bytes = @file_get_contents($path);
    if (!is_string($bytes) || $bytes === '') {
        return null;
    }
    return [
        'path' => $path,
        'ext' => $ext,
        'bytes' => $bytes,
    ];
}

function clickfix_scan_swap_before_after(PDO $pdo, int $reportId, int $actorId = 0): bool
{
    $reportId = (int) $reportId;
    if ($reportId <= 0) {
        return false;
    }
    $beforeInfo = clickfix_scan_asset_info($reportId, 'before');
    $afterInfo = clickfix_scan_asset_info($reportId, 'after');
    if ($beforeInfo === null && $afterInfo === null) {
        return false;
    }

    $beforeStatus = clickfix_scan_image_review_status($pdo, $reportId, 'before');
    $afterStatus = clickfix_scan_image_review_status($pdo, $reportId, 'after');
    $changed = false;

    if ($afterInfo !== null) {
        if (clickfix_scan_write_asset_bytes($reportId, 'before', (string) $afterInfo['bytes'], (string) $afterInfo['ext'])) {
            $changed = true;
        }
    } else {
        if (clickfix_delete_scan_image($pdo, $reportId, 'before')) {
            $changed = true;
        }
    }

    if ($beforeInfo !== null) {
        if (clickfix_scan_write_asset_bytes($reportId, 'after', (string) $beforeInfo['bytes'], (string) $beforeInfo['ext'])) {
            $changed = true;
        }
    } else {
        if (clickfix_delete_scan_image($pdo, $reportId, 'after')) {
            $changed = true;
        }
    }

    if ($afterInfo !== null) {
        clickfix_scan_image_set_review($pdo, $reportId, 'before', $afterStatus, $actorId, 'swapped from after');
    }
    if ($beforeInfo !== null) {
        clickfix_scan_image_set_review($pdo, $reportId, 'after', $beforeStatus, $actorId, 'swapped from before');
    }

    return $changed;
}

function clickfix_scan_assign_kind(
    PDO $pdo,
    int $reportId,
    string $sourceKind,
    string $targetKind,
    int $actorId = 0,
    bool $keepSource = true
): bool {
    $reportId = (int) $reportId;
    $sourceKind = clickfix_scan_kind_normalize($sourceKind);
    $targetKind = clickfix_scan_kind_normalize($targetKind);
    if ($reportId <= 0 || $sourceKind === '' || $targetKind === '' || $sourceKind === $targetKind) {
        return false;
    }

    $sourceInfo = clickfix_scan_asset_info($reportId, $sourceKind);
    if ($sourceInfo === null) {
        return false;
    }

    $sourceStatus = clickfix_scan_image_review_status($pdo, $reportId, $sourceKind);
    $stored = clickfix_scan_write_asset_bytes($reportId, $targetKind, (string) $sourceInfo['bytes'], (string) $sourceInfo['ext']);
    if (!$stored) {
        return false;
    }

    clickfix_scan_image_set_review(
        $pdo,
        $reportId,
        $targetKind,
        $sourceStatus,
        $actorId,
        'assigned from ' . $sourceKind
    );

    if (!$keepSource) {
        clickfix_delete_scan_image($pdo, $reportId, $sourceKind);
    }

    return true;
}

function clickfix_scan_image_url(int $reportId, string $kind, bool $adminPreview = false): string
{
    $query = [
        'report_id' => (string) max(0, (int) $reportId),
        'kind' => clickfix_scan_kind_normalize($kind),
    ];
    if ($adminPreview) {
        $query['preview'] = '1';
    }
    return 'scan-image.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

function clickfix_scan_image_review_status(PDO $pdo, int $reportId, string $kind): string
{
    $reportId = (int) $reportId;
    $kind = clickfix_scan_kind_normalize($kind);
    if ($reportId <= 0 || $kind === '') {
        return 'pending';
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT status
             FROM scan_image_reviews
             WHERE report_id = :report_id
               AND kind = :kind
             LIMIT 1'
        );
        $stmt->execute([
            ':report_id' => $reportId,
            ':kind' => $kind,
        ]);
        $status = strtolower(trim((string) $stmt->fetchColumn()));
        if (in_array($status, ['approved', 'rejected', 'pending'], true)) {
            return $status;
        }
    } catch (Throwable $exception) {
        return 'pending';
    }
    return 'pending';
}

function clickfix_scan_image_mark_pending(PDO $pdo, int $reportId, string $kind): bool
{
    $reportId = (int) $reportId;
    $kind = clickfix_scan_kind_normalize($kind);
    if ($reportId <= 0 || $kind === '') {
        return false;
    }
    $now = gmdate('c');
    $stmt = $pdo->prepare(
        'INSERT INTO scan_image_reviews (report_id, kind, status, created_at, updated_at, reviewed_at, reviewed_by, review_note)
         VALUES (:report_id, :kind, \'pending\', :created_at, :updated_at, NULL, NULL, NULL)
         ON CONFLICT(report_id, kind) DO UPDATE SET
            status = \'pending\',
            updated_at = excluded.updated_at,
            reviewed_at = NULL,
            reviewed_by = NULL,
            review_note = NULL'
    );
    return $stmt->execute([
        ':report_id' => $reportId,
        ':kind' => $kind,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);
}

function clickfix_scan_image_set_review(
    PDO $pdo,
    int $reportId,
    string $kind,
    string $status,
    int $reviewedBy,
    string $note = ''
): bool {
    $reportId = (int) $reportId;
    $kind = clickfix_scan_kind_normalize($kind);
    $status = strtolower(trim($status));
    if ($reportId <= 0 || $kind === '' || !in_array($status, ['pending', 'approved', 'rejected'], true)) {
        return false;
    }
    $now = gmdate('c');
    $stmt = $pdo->prepare(
        'INSERT INTO scan_image_reviews (report_id, kind, status, created_at, updated_at, reviewed_at, reviewed_by, review_note)
         VALUES (:report_id, :kind, :status, :created_at, :updated_at, :reviewed_at, :reviewed_by, :review_note)
         ON CONFLICT(report_id, kind) DO UPDATE SET
            status = excluded.status,
            updated_at = excluded.updated_at,
            reviewed_at = excluded.reviewed_at,
            reviewed_by = excluded.reviewed_by,
            review_note = excluded.review_note'
    );
    return $stmt->execute([
        ':report_id' => $reportId,
        ':kind' => $kind,
        ':status' => $status,
        ':created_at' => $now,
        ':updated_at' => $now,
        ':reviewed_at' => $status === 'pending' ? null : $now,
        ':reviewed_by' => $status === 'pending' ? null : ($reviewedBy > 0 ? $reviewedBy : null),
        ':review_note' => $status === 'pending' ? null : substr(trim($note), 0, 500),
    ]);
}

function clickfix_delete_scan_image(PDO $pdo, int $reportId, string $kind = ''): bool
{
    $reportId = (int) $reportId;
    $normalizedKind = clickfix_scan_kind_normalize($kind);
    $kinds = $normalizedKind === '' ? ['before', 'after'] : [$normalizedKind];
    if ($reportId <= 0 || empty($kinds)) {
        return false;
    }

    $deletedAny = false;
    foreach ($kinds as $singleKind) {
        try {
            $stmt = $pdo->prepare(
                'DELETE FROM scan_image_reviews
                 WHERE report_id = :report_id
                   AND kind = :kind'
            );
            $stmt->execute([
                ':report_id' => $reportId,
                ':kind' => $singleKind,
            ]);
            if ($stmt->rowCount() > 0) {
                $deletedAny = true;
            }
        } catch (Throwable $exception) {
            // Keep going and attempt filesystem cleanup.
        }

        $path = clickfix_scan_asset_absolute_path($reportId, $singleKind);
        if ($path !== null && is_file($path) && @unlink($path)) {
            $deletedAny = true;
        }
    }

    return $deletedAny;
}

function clickfix_scan_image_review_queue(PDO $pdo, int $limit = 80): array
{
    $stmt = $pdo->prepare(
        'SELECT sir.report_id,
                sir.kind,
                sir.status,
                sir.updated_at,
                sir.reviewed_at,
                sir.reviewed_by,
                sir.review_note,
                r.received_at,
                r.hostname,
                u.username AS reviewed_by_username
         FROM scan_image_reviews sir
         LEFT JOIN reports r ON r.id = sir.report_id
         LEFT JOIN users u ON u.id = sir.reviewed_by
         WHERE sir.status = \'pending\'
         ORDER BY COALESCE(r.received_at, sir.updated_at) DESC, sir.id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', max(1, min(300, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $reportId = (int) ($row['report_id'] ?? 0);
        $kind = clickfix_scan_kind_normalize((string) ($row['kind'] ?? ''));
        $row['asset_exists'] = $reportId > 0 && $kind !== '' && clickfix_scan_asset_absolute_path($reportId, $kind) !== null;
        $row['preview_url'] = $row['asset_exists'] ? clickfix_scan_image_url($reportId, $kind, true) : '';
        $row['public_url'] = $row['asset_exists'] ? clickfix_scan_image_url($reportId, $kind, false) : '';
    }
    unset($row);
    return $rows;
}

function clickfix_scan_preview_assets(PDO $pdo, int $reportId, bool $approvedOnly = true): array
{
    $reportId = (int) $reportId;
    $result = [
        'before' => null,
        'after' => null,
        'before_exists' => false,
        'after_exists' => false,
        'before_status' => 'missing',
        'after_status' => 'missing',
    ];
    if ($reportId <= 0) {
        return $result;
    }
    foreach (['before', 'after'] as $kind) {
        $path = clickfix_scan_asset_absolute_path($reportId, $kind);
        if ($path === null) {
            continue;
        }
        $existsKey = $kind . '_exists';
        $statusKey = $kind . '_status';
        $result[$existsKey] = true;
        $status = clickfix_scan_image_review_status($pdo, $reportId, $kind);
        $result[$statusKey] = $status;
        if ($approvedOnly && $status !== 'approved') {
            continue;
        }
        $result[$kind] = clickfix_scan_image_url($reportId, $kind, false);
    }
    return $result;
}

function clickfix_extract_extension_version(string $userAgent): string
{
    if ($userAgent === '') {
        return '';
    }
    if (preg_match('/(?:clickfix|extension|mitigator)[\/ ]([0-9]+\.[0-9]+\.[0-9]+)/i', $userAgent, $m)) {
        return (string) $m[1];
    }
    if (preg_match('/([0-9]+\.[0-9]+\.[0-9]+)/', $userAgent, $m)) {
        return (string) $m[1];
    }
    return '';
}

function clickfix_recent_extension_clients(PDO $pdo, int $limit = 120): array
{
    $limit = max(1, min(500, $limit));
    $cacheKey = clickfix_cache_key('recent_extension_clients', ['limit' => $limit, 'v2' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $stmt = $pdo->prepare(
        "SELECT rc.client_id,
                rc.first_seen,
                rc.last_seen,
                rc.total_events,
                rc.total_blocks,
                rc.ip_count,
                rc.ip_history,
                COALESCE(meta.user_agent, '') AS user_agent,
                COALESCE(meta.install_channel, '') AS install_channel,
                COALESCE(meta.install_source, '') AS install_source
         FROM (
             SELECT COALESCE(NULLIF(client_id, ''), 'unknown') AS client_id,
                    MIN(received_at) AS first_seen,
                    MAX(received_at) AS last_seen,
                    COUNT(*) AS total_events,
                    SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) AS total_blocks,
                    COUNT(DISTINCT CASE WHEN ip IS NOT NULL AND ip != '' THEN ip END) AS ip_count,
                    GROUP_CONCAT(DISTINCT CASE WHEN ip IS NOT NULL AND ip != '' THEN ip END) AS ip_history
             FROM reports
             GROUP BY COALESCE(NULLIF(client_id, ''), 'unknown')
         ) rc
         LEFT JOIN stats meta
           ON meta.id = (
             SELECT s2.id
             FROM stats s2
             WHERE COALESCE(NULLIF(s2.client_id, ''), 'unknown') = rc.client_id
             ORDER BY s2.received_at DESC, s2.id DESC
             LIMIT 1
           )
         ORDER BY rc.last_seen DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $ua = (string) ($row['user_agent'] ?? '');
        $row['extension_version'] = clickfix_extract_extension_version($ua);
        $row['user_agent'] = $ua;
        $row['days_active'] = 0;
        $first = strtotime((string) ($row['first_seen'] ?? ''));
        $last = strtotime((string) ($row['last_seen'] ?? ''));
        if ($first !== false && $last !== false && $last >= $first) {
            $row['days_active'] = (int) floor(($last - $first) / 86400) + 1;
        }
    }
    unset($row);
    clickfix_cache_set($cacheKey, $rows, 10);
    return $rows;
}

function clickfix_extension_client_events(PDO $pdo, string $clientId, int $limit = 120): array
{
    $clientId = trim($clientId);
    if ($clientId === '') {
        return [];
    }
    $stmt = $pdo->prepare(
        "SELECT id, received_at, hostname, url, ip, blocked, review_status, score_total
         FROM reports
         WHERE COALESCE(NULLIF(client_id, ''), 'unknown') = :client_id
         ORDER BY received_at DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
    $stmt->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function clickfix_baseline_trust_score(array $row): int
{
    $daysSeen = max(0, (int) ($row['days_seen'] ?? 0));
    $visitsCount = max(0, (int) ($row['visits_count'] ?? 0));
    $alertCount = max(0, (int) ($row['alert_count'] ?? 0));
    $blockedCount = max(0, (int) ($row['blocked_count'] ?? 0));
    $localAllowlisted = !empty($row['local_allowlisted']);

    $score = 0;
    if ($daysSeen >= 2) {
        $score += 18;
    }
    if ($daysSeen >= 4) {
        $score += 14;
    }
    if ($daysSeen >= 7) {
        $score += 12;
    }
    if ($visitsCount >= 4) {
        $score += 16;
    }
    if ($visitsCount >= 8) {
        $score += 12;
    }
    if ($visitsCount >= 20) {
        $score += 10;
    }
    if ($alertCount === 0) {
        $score += 10;
    } elseif ($alertCount <= 1) {
        $score += 4;
    }
    if ($blockedCount === 0) {
        $score += 4;
    }
    if ($localAllowlisted) {
        $score += 14;
    }

    return max(0, min(100, (int) round($score)));
}

function clickfix_upsert_client_host_baseline(PDO $pdo, string $clientId, string $hostname, array $fields = []): bool
{
    $clientId = clickfix_normalize_client_id($clientId);
    $hostname = clickfix_normalize_domain($hostname);
    if ($clientId === '' || $hostname === '' || !clickfix_has_table($pdo, 'client_host_baseline')) {
        return false;
    }

    $now = (string) ($fields['updated_at'] ?? gmdate('c'));
    $select = $pdo->prepare('SELECT * FROM client_host_baseline WHERE client_id = :client_id AND hostname = :hostname LIMIT 1');
    $select->execute([':client_id' => $clientId, ':hostname' => $hostname]);
    $current = $select->fetch(PDO::FETCH_ASSOC) ?: null;

    $row = [
        'client_id' => $clientId,
        'hostname' => $hostname,
        'first_seen_at' => (string) ($fields['first_seen_at'] ?? ($current['first_seen_at'] ?? $now)),
        'last_seen_at' => (string) ($fields['last_seen_at'] ?? ($current['last_seen_at'] ?? $now)),
        'last_visit_day' => (string) ($fields['last_visit_day'] ?? ($current['last_visit_day'] ?? substr($now, 0, 10))),
        'days_seen' => max(0, (int) ($fields['days_seen'] ?? ($current['days_seen'] ?? 0))),
        'visits_count' => max(0, (int) ($fields['visits_count'] ?? ($current['visits_count'] ?? 0))),
        'alert_count' => max(0, (int) ($fields['alert_count'] ?? ($current['alert_count'] ?? 0))),
        'blocked_count' => max(0, (int) ($fields['blocked_count'] ?? ($current['blocked_count'] ?? 0))),
        'accepted_count' => max(0, (int) ($fields['accepted_count'] ?? ($current['accepted_count'] ?? 0))),
        'rejected_count' => max(0, (int) ($fields['rejected_count'] ?? ($current['rejected_count'] ?? 0))),
        'allowlisted_count' => max(0, (int) ($fields['allowlisted_count'] ?? ($current['allowlisted_count'] ?? 0))),
        'local_allowlisted' => !empty($fields['local_allowlisted']) || !empty($current['local_allowlisted']) ? 1 : 0,
        'trust_score' => 0,
        'last_verdict' => substr((string) ($fields['last_verdict'] ?? ($current['last_verdict'] ?? '')), 0, 40),
        'updated_at' => $now,
        'source' => substr((string) ($fields['source'] ?? ($current['source'] ?? 'extension')), 0, 40),
    ];
    $row['trust_score'] = clickfix_baseline_trust_score($row);

    if ($current) {
        $stmt = $pdo->prepare(
            'UPDATE client_host_baseline
             SET first_seen_at = :first_seen_at,
                 last_seen_at = :last_seen_at,
                 last_visit_day = :last_visit_day,
                 days_seen = :days_seen,
                 visits_count = :visits_count,
                 alert_count = :alert_count,
                 blocked_count = :blocked_count,
                 accepted_count = :accepted_count,
                 rejected_count = :rejected_count,
                 allowlisted_count = :allowlisted_count,
                 local_allowlisted = :local_allowlisted,
                 trust_score = :trust_score,
                 last_verdict = :last_verdict,
                 updated_at = :updated_at,
                 source = :source
             WHERE client_id = :client_id AND hostname = :hostname'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO client_host_baseline (
                client_id, hostname, first_seen_at, last_seen_at, last_visit_day,
                days_seen, visits_count, alert_count, blocked_count,
                accepted_count, rejected_count, allowlisted_count,
                local_allowlisted, trust_score, last_verdict, updated_at, source
             ) VALUES (
                :client_id, :hostname, :first_seen_at, :last_seen_at, :last_visit_day,
                :days_seen, :visits_count, :alert_count, :blocked_count,
                :accepted_count, :rejected_count, :allowlisted_count,
                :local_allowlisted, :trust_score, :last_verdict, :updated_at, :source
             )'
        );
    }

    return $stmt->execute([
        ':client_id' => $row['client_id'],
        ':hostname' => $row['hostname'],
        ':first_seen_at' => $row['first_seen_at'],
        ':last_seen_at' => $row['last_seen_at'],
        ':last_visit_day' => $row['last_visit_day'],
        ':days_seen' => $row['days_seen'],
        ':visits_count' => $row['visits_count'],
        ':alert_count' => $row['alert_count'],
        ':blocked_count' => $row['blocked_count'],
        ':accepted_count' => $row['accepted_count'],
        ':rejected_count' => $row['rejected_count'],
        ':allowlisted_count' => $row['allowlisted_count'],
        ':local_allowlisted' => $row['local_allowlisted'],
        ':trust_score' => $row['trust_score'],
        ':last_verdict' => $row['last_verdict'],
        ':updated_at' => $row['updated_at'],
        ':source' => $row['source'],
    ]);
}

function clickfix_baseline_merge_host_summaries(PDO $pdo, string $clientId, array $rows, string $receivedAt): void
{
    if (!clickfix_has_table($pdo, 'client_host_baseline')) {
        return;
    }
    $clientId = clickfix_normalize_client_id($clientId);
    if ($clientId === '') {
        return;
    }
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $hostname = clickfix_normalize_domain((string) ($row['hostname'] ?? ''));
        if ($hostname === '') {
            continue;
        }
        clickfix_upsert_client_host_baseline($pdo, $clientId, $hostname, [
            'first_seen_at' => (string) ($row['first_seen_at'] ?? $receivedAt),
            'last_seen_at' => $receivedAt,
            'last_visit_day' => substr((string) ($row['last_seen_day'] ?? substr($receivedAt, 0, 10)), 0, 10),
            'days_seen' => max(0, (int) ($row['days_seen'] ?? 0)),
            'visits_count' => max(0, (int) ($row['visits_count'] ?? 0)),
            'alert_count' => max(0, (int) ($row['alert_count'] ?? 0)),
            'blocked_count' => max(0, (int) ($row['blocked_count'] ?? 0)),
            'local_allowlisted' => !empty($row['local_allowlisted']) ? 1 : 0,
            'last_verdict' => '',
            'updated_at' => $receivedAt,
            'source' => 'summary',
        ]);
    }
}

function clickfix_baseline_record_alert(PDO $pdo, string $clientId, string $hostname, bool $blocked = false, string $receivedAt = '', string $verdict = ''): void
{
    if (!clickfix_has_table($pdo, 'client_host_baseline')) {
        return;
    }
    $clientId = clickfix_normalize_client_id($clientId);
    $hostname = clickfix_normalize_domain($hostname);
    if ($clientId === '' || $hostname === '') {
        return;
    }
    $at = $receivedAt !== '' ? $receivedAt : gmdate('c');
    $select = $pdo->prepare('SELECT * FROM client_host_baseline WHERE client_id = :client_id AND hostname = :hostname LIMIT 1');
    $select->execute([':client_id' => $clientId, ':hostname' => $hostname]);
    $current = $select->fetch(PDO::FETCH_ASSOC) ?: [];
    $day = substr($at, 0, 10);
    $daysSeen = max(1, (int) ($current['days_seen'] ?? 0));
    if ((string) ($current['last_visit_day'] ?? '') !== $day) {
        $daysSeen++;
    }
    clickfix_upsert_client_host_baseline($pdo, $clientId, $hostname, [
        'first_seen_at' => (string) ($current['first_seen_at'] ?? $at),
        'last_seen_at' => $at,
        'last_visit_day' => $day,
        'days_seen' => $daysSeen,
        'visits_count' => max(1, (int) ($current['visits_count'] ?? 0)) + 1,
        'alert_count' => max(0, (int) ($current['alert_count'] ?? 0)) + 1,
        'blocked_count' => max(0, (int) ($current['blocked_count'] ?? 0)) + ($blocked ? 1 : 0),
        'accepted_count' => max(0, (int) ($current['accepted_count'] ?? 0)),
        'rejected_count' => max(0, (int) ($current['rejected_count'] ?? 0)),
        'allowlisted_count' => max(0, (int) ($current['allowlisted_count'] ?? 0)),
        'local_allowlisted' => !empty($current['local_allowlisted']) ? 1 : 0,
        'last_verdict' => $verdict !== '' ? $verdict : (string) ($current['last_verdict'] ?? ''),
        'updated_at' => $at,
        'source' => 'alert',
    ]);
}

function clickfix_baseline_record_review(PDO $pdo, string $clientId, string $hostname, string $status): void
{
    if (!clickfix_has_table($pdo, 'client_host_baseline')) {
        return;
    }
    $status = strtolower(trim($status));
    if (!in_array($status, ['accepted', 'rejected', 'allowlisted'], true)) {
        return;
    }
    $clientId = clickfix_normalize_client_id($clientId);
    $hostname = clickfix_normalize_domain($hostname);
    if ($clientId === '' || $hostname === '') {
        return;
    }
    $select = $pdo->prepare('SELECT * FROM client_host_baseline WHERE client_id = :client_id AND hostname = :hostname LIMIT 1');
    $select->execute([':client_id' => $clientId, ':hostname' => $hostname]);
    $current = $select->fetch(PDO::FETCH_ASSOC) ?: [];
    clickfix_upsert_client_host_baseline($pdo, $clientId, $hostname, [
        'first_seen_at' => (string) ($current['first_seen_at'] ?? gmdate('c')),
        'last_seen_at' => (string) ($current['last_seen_at'] ?? gmdate('c')),
        'last_visit_day' => (string) ($current['last_visit_day'] ?? gmdate('Y-m-d')),
        'days_seen' => max(0, (int) ($current['days_seen'] ?? 0)),
        'visits_count' => max(0, (int) ($current['visits_count'] ?? 0)),
        'alert_count' => max(0, (int) ($current['alert_count'] ?? 0)),
        'blocked_count' => max(0, (int) ($current['blocked_count'] ?? 0)),
        'accepted_count' => max(0, (int) ($current['accepted_count'] ?? 0)) + ($status === 'accepted' ? 1 : 0),
        'rejected_count' => max(0, (int) ($current['rejected_count'] ?? 0)) + ($status === 'rejected' ? 1 : 0),
        'allowlisted_count' => max(0, (int) ($current['allowlisted_count'] ?? 0)) + ($status === 'allowlisted' ? 1 : 0),
        'local_allowlisted' => !empty($current['local_allowlisted']) || $status === 'allowlisted' ? 1 : 0,
        'last_verdict' => $status,
        'updated_at' => gmdate('c'),
        'source' => 'review',
    ]);
}

function clickfix_extension_client_baseline_hosts(PDO $pdo, string $clientId, int $limit = 60): array
{
    if (!clickfix_has_table($pdo, 'client_host_baseline')) {
        return [];
    }
    $clientId = clickfix_normalize_client_id($clientId);
    if ($clientId === '') {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT hostname, first_seen_at, last_seen_at, last_visit_day, days_seen, visits_count,
                alert_count, blocked_count, accepted_count, rejected_count, allowlisted_count,
                local_allowlisted, trust_score, last_verdict, updated_at
         FROM client_host_baseline
         WHERE client_id = :client_id
         ORDER BY trust_score DESC, visits_count DESC, last_seen_at DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
    $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function clickfix_baseline_global_candidates(PDO $pdo, int $limit = 50): array
{
    if (!clickfix_has_table($pdo, 'client_host_baseline')) {
        return [];
    }
    $stmt = $pdo->prepare(
        "SELECT hostname,
                COUNT(DISTINCT client_id) AS clients,
                SUM(visits_count) AS visits,
                SUM(alert_count) AS alerts,
                SUM(blocked_count) AS blocks,
                SUM(accepted_count) AS accepted,
                SUM(rejected_count) AS rejected,
                SUM(allowlisted_count) AS allowlisted,
                AVG(trust_score) AS avg_trust,
                MAX(last_seen_at) AS last_seen_at
         FROM client_host_baseline
         GROUP BY hostname
         HAVING COUNT(DISTINCT client_id) >= 1
         ORDER BY avg_trust DESC, clients DESC, visits DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function clickfix_normalize_client_id(string $clientId): string
{
    $clientId = substr(trim($clientId), 0, 120);
    if ($clientId === '') {
        return '';
    }
    if (!preg_match('/^[A-Za-z0-9._:-]+$/', $clientId)) {
        return '';
    }
    return $clientId;
}

function clickfix_link_user_extension_client(PDO $pdo, int $createdBy, int $userId, string $clientId, string $note = ''): bool
{
    if ($userId <= 0) {
        return false;
    }
    $clientId = clickfix_normalize_client_id($clientId);
    if ($clientId === '' || strtolower($clientId) === 'unknown') {
        return false;
    }
    $existsUser = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id = :id');
    $existsUser->execute([':id' => $userId]);
    if ((int) $existsUser->fetchColumn() < 1) {
        return false;
    }

    $note = substr(trim($note), 0, 280);
    $now = gmdate('c');
    $check = $pdo->prepare('SELECT id FROM user_extension_links WHERE user_id = :user_id AND client_id = :client_id LIMIT 1');
    $check->execute([':user_id' => $userId, ':client_id' => $clientId]);
    $row = $check->fetch();
    if ($row) {
        $upd = $pdo->prepare(
            'UPDATE user_extension_links
             SET updated_at = :updated_at, created_by = :created_by, note = :note, active = 1
             WHERE id = :id'
        );
        return $upd->execute([
            ':updated_at' => $now,
            ':created_by' => $createdBy > 0 ? $createdBy : null,
            ':note' => $note,
            ':id' => (int) ($row['id'] ?? 0),
        ]);
    }

    $ins = $pdo->prepare(
        'INSERT INTO user_extension_links (created_at, updated_at, created_by, user_id, client_id, note, active)
         VALUES (:created_at, :updated_at, :created_by, :user_id, :client_id, :note, 1)'
    );
    return $ins->execute([
        ':created_at' => $now,
        ':updated_at' => $now,
        ':created_by' => $createdBy > 0 ? $createdBy : null,
        ':user_id' => $userId,
        ':client_id' => $clientId,
        ':note' => $note,
    ]);
}

function clickfix_unlink_user_extension_client(PDO $pdo, int $linkId): bool
{
    if ($linkId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE user_extension_links SET active = 0, updated_at = :updated_at WHERE id = :id');
    return $stmt->execute([
        ':updated_at' => gmdate('c'),
        ':id' => $linkId,
    ]);
}

function clickfix_extension_user_links(PDO $pdo, int $limit = 500): array
{
    $limit = max(1, min(2000, $limit));
    $cacheKey = clickfix_cache_key('extension_user_links', ['limit' => $limit, 'v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }
    $stmt = $pdo->prepare(
        "SELECT l.id,
                l.created_at,
                l.updated_at,
                l.created_by,
                l.user_id,
                l.client_id,
                l.note,
                l.active,
                u.username,
                u.email,
                u.role,
                u.verified,
                rc.last_seen,
                rc.total_events,
                rc.total_blocks
         FROM user_extension_links l
         JOIN users u ON u.id = l.user_id
         LEFT JOIN (
            SELECT COALESCE(NULLIF(client_id, ''), 'unknown') AS client_id,
                   MAX(received_at) AS last_seen,
                   COUNT(*) AS total_events,
                   SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) AS total_blocks
            FROM reports
            GROUP BY COALESCE(NULLIF(client_id, ''), 'unknown')
         ) rc ON rc.client_id = l.client_id
         WHERE l.active = 1
         ORDER BY l.updated_at DESC, l.id DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $role = clickfix_normalize_role((string) ($row['role'] ?? 'analyst_jr'));
        $row['role'] = $role;
        $row['role_label'] = clickfix_role_label($role);
    }
    unset($row);
    clickfix_cache_set($cacheKey, $rows, 10);
    return $rows;
}

function clickfix_extension_client_ids_for_user(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT client_id
         FROM user_extension_links
         WHERE user_id = :user_id
           AND active = 1
         ORDER BY updated_at DESC'
    );
    $stmt->execute([':user_id' => $userId]);
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $clientId) {
        $clean = clickfix_normalize_client_id((string) $clientId);
        if ($clean === '' || isset($result[$clean])) {
            continue;
        }
        $result[$clean] = true;
    }
    return array_keys($result);
}

function clickfix_extension_client_ids_for_users(PDO $pdo, array $userIds): array
{
    $cleanUserIds = [];
    foreach ($userIds as $rawUserId) {
        $userId = (int) $rawUserId;
        if ($userId > 0) {
            $cleanUserIds[$userId] = true;
        }
    }
    if (empty($cleanUserIds)) {
        return [];
    }
    $ids = array_keys($cleanUserIds);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT client_id
         FROM user_extension_links
         WHERE active = 1
           AND user_id IN ({$placeholders})"
    );
    foreach ($ids as $idx => $id) {
        $stmt->bindValue($idx + 1, $id, PDO::PARAM_INT);
    }
    $stmt->execute();
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $clientId) {
        $clean = clickfix_normalize_client_id((string) $clientId);
        if ($clean === '' || strtolower($clean) === 'unknown' || isset($result[$clean])) {
            continue;
        }
        $result[$clean] = true;
    }
    return array_keys($result);
}

function clickfix_extension_client_ids_linked(PDO $pdo): array
{
    $cacheKey = clickfix_cache_key('extension_client_ids_linked', ['v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }
    $stmt = $pdo->query(
        'SELECT DISTINCT client_id
         FROM user_extension_links
         WHERE active = 1'
    );
    if (!$stmt) {
        return [];
    }
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $clientId) {
        $clean = clickfix_normalize_client_id((string) $clientId);
        if ($clean === '' || strtolower($clean) === 'unknown' || isset($result[$clean])) {
            continue;
        }
        $result[$clean] = true;
    }
    $out = array_keys($result);
    clickfix_cache_set($cacheKey, $out, 8);
    return $out;
}

function clickfix_extension_client_ids_unlinked(PDO $pdo): array
{
    $cacheKey = clickfix_cache_key('extension_client_ids_unlinked', ['v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }
    $stmt = $pdo->query(
        "SELECT x.client_id
         FROM (
             SELECT DISTINCT COALESCE(NULLIF(client_id, ''), '') AS client_id FROM reports
             UNION
             SELECT DISTINCT COALESCE(NULLIF(client_id, ''), '') AS client_id FROM stats
         ) x
         LEFT JOIN user_extension_links l
           ON l.client_id = x.client_id
          AND l.active = 1
         WHERE x.client_id != ''
           AND lower(x.client_id) != 'unknown'
           AND l.id IS NULL"
    );
    if (!$stmt) {
        return [];
    }
    $result = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $clientId) {
        $clean = clickfix_normalize_client_id((string) $clientId);
        if ($clean === '' || isset($result[$clean])) {
            continue;
        }
        $result[$clean] = true;
    }
    $out = array_keys($result);
    clickfix_cache_set($cacheKey, $out, 8);
    return $out;
}

function clickfix_parse_client_id_batch(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }
    $parts = preg_split('/[\s,;]+/', $raw) ?: [];
    $result = [];
    foreach ($parts as $part) {
        $clean = clickfix_normalize_client_id((string) $part);
        if ($clean === '' || strtolower($clean) === 'unknown' || isset($result[$clean])) {
            continue;
        }
        $result[$clean] = true;
    }
    return array_keys($result);
}

function clickfix_data_center_snapshot(PDO $pdo): array
{
    $cacheKey = clickfix_cache_key('data_center_snapshot', ['v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $tables = [
        'reports',
        'stats',
        'users',
        'appeals',
        'list_actions',
        'list_suggestions',
        'access_requests',
        'investigation_graphs',
        'api_clients',
        'api_user_keys',
        'api_refresh_tokens',
        'api_rate_limits',
        'extension_messages',
        'report_schedules',
        'user_extension_links',
        'investigation_events',
        'investigation_votes',
        'investigation_api_lookup_history',
        'user_reputation_events',
        'geo_country_cache',
        'domain_intel_cache',
        'whatweb_cache',
    ];
    $result = [];
    foreach ($tables as $table) {
        try {
            $count = (int) ($pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() ?: 0);
            $latest = $pdo->query("SELECT MAX(created_at) FROM {$table}")->fetchColumn();
            if (($latest === false || $latest === null || $latest === '') && $table === 'reports') {
                $latest = $pdo->query("SELECT MAX(received_at) FROM reports")->fetchColumn();
            }
            $result[] = [
                'table' => $table,
                'rows' => $count,
                'latest' => is_string($latest) ? $latest : '',
            ];
        } catch (Throwable $exception) {
            $result[] = [
                'table' => $table,
                'rows' => 0,
                'latest' => '',
            ];
        }
    }
    clickfix_cache_set($cacheKey, $result, 10);
    return $result;
}

function clickfix_table_recent(PDO $pdo, string $table, int $limit = 50): array
{
    $limit = max(1, min(200, $limit));
    $allowed = [
        'reports',
        'stats',
        'users',
        'appeals',
        'list_actions',
        'list_suggestions',
        'access_requests',
        'investigation_graphs',
        'api_user_keys',
        'extension_messages',
        'report_schedules',
        'user_extension_links',
        'investigation_events',
        'investigation_votes',
        'investigation_api_lookup_history',
        'user_reputation_events',
        'geo_country_cache',
        'domain_intel_cache',
        'whatweb_cache',
    ];
    if (!in_array($table, $allowed, true)) {
        return [];
    }
    $cacheKey = clickfix_cache_key('table_recent', ['table' => $table, 'limit' => $limit, 'v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }
    $stmt = $pdo->prepare("SELECT * FROM {$table} ORDER BY rowid DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    clickfix_cache_set($cacheKey, $rows, 8);
    return $rows;
}

function clickfix_score_config_path(bool $premium = false): string
{
    $base = dirname(__DIR__);
    return $premium ? ($base . '/clickfix-score-config-premium.json') : ($base . '/clickfix-score-config.json');
}

function clickfix_load_score_config(bool $premium = false): array
{
    $path = clickfix_score_config_path($premium);
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function clickfix_save_score_config(bool $premium, string $rawJson, ?string &$error = null): bool
{
    $decoded = json_decode($rawJson, true);
    if (!is_array($decoded)) {
        $error = 'JSON invalido.';
        return false;
    }
    $payload = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($payload) || $payload === '') {
        $error = 'No se pudo serializar el JSON.';
        return false;
    }
    $payload .= PHP_EOL;
    $path = clickfix_score_config_path($premium);
    $ok = file_put_contents($path, $payload, LOCK_EX);
    if ($ok === false) {
        $error = 'No se pudo escribir el archivo de configuracion.';
        return false;
    }
    return true;
}

function clickfix_send_extension_message(
    PDO $pdo,
    int $createdBy,
    string $scope,
    ?string $clientId,
    string $title,
    string $body,
    string $severity = 'info',
    int $expiresDays = 7,
    string $expiresAtRaw = ''
): bool {
    $scope = strtolower(trim($scope));
    if (!in_array($scope, ['all', 'client'], true)) {
        return false;
    }
    $targetClientId = null;
    if ($scope === 'client') {
        $targetClientId = substr(trim((string) $clientId), 0, 120);
        if ($targetClientId === '') {
            return false;
        }
    }
    $title = substr(trim($title), 0, 180);
    $body = substr(trim($body), 0, 5000);
    if ($title === '' || $body === '') {
        return false;
    }
    $severity = strtolower(trim($severity));
    if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
        $severity = 'info';
    }
    $now = gmdate('c');
    $expiresAt = clickfix_normalize_extension_message_expires_at($expiresAtRaw, $expiresDays);
    $stmt = $pdo->prepare(
        'INSERT INTO extension_messages (created_at, created_by, target_scope, target_client_id, title, body, severity, starts_at, expires_at, active)
         VALUES (:created_at, :created_by, :target_scope, :target_client_id, :title, :body, :severity, :starts_at, :expires_at, 1)'
    );
    return $stmt->execute([
        ':created_at' => $now,
        ':created_by' => $createdBy > 0 ? $createdBy : null,
        ':target_scope' => $scope,
        ':target_client_id' => $targetClientId,
        ':title' => $title,
        ':body' => $body,
        ':severity' => $severity,
        ':starts_at' => $now,
        ':expires_at' => $expiresAt,
    ]);
}

function clickfix_send_extension_message_to_clients(
    PDO $pdo,
    int $createdBy,
    array $clientIds,
    string $title,
    string $body,
    string $severity = 'info',
    int $expiresDays = 7,
    string $expiresAtRaw = ''
): int {
    $title = substr(trim($title), 0, 180);
    $body = substr(trim($body), 0, 5000);
    if ($title === '' || $body === '') {
        return 0;
    }
    $severity = strtolower(trim($severity));
    if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
        $severity = 'info';
    }
    $targets = [];
    foreach ($clientIds as $rawClientId) {
        $clean = clickfix_normalize_client_id((string) $rawClientId);
        if ($clean === '' || strtolower($clean) === 'unknown') {
            continue;
        }
        $targets[$clean] = true;
    }
    if (empty($targets)) {
        return 0;
    }

    $now = gmdate('c');
    $expiresAt = clickfix_normalize_extension_message_expires_at($expiresAtRaw, $expiresDays);
    $stmt = $pdo->prepare(
        'INSERT INTO extension_messages (created_at, created_by, target_scope, target_client_id, title, body, severity, starts_at, expires_at, active)
         VALUES (:created_at, :created_by, :target_scope, :target_client_id, :title, :body, :severity, :starts_at, :expires_at, 1)'
    );

    $sent = 0;
    foreach (array_keys($targets) as $clientId) {
        $ok = $stmt->execute([
            ':created_at' => $now,
            ':created_by' => $createdBy > 0 ? $createdBy : null,
            ':target_scope' => 'client',
            ':target_client_id' => $clientId,
            ':title' => $title,
            ':body' => $body,
            ':severity' => $severity,
            ':starts_at' => $now,
            ':expires_at' => $expiresAt,
        ]);
        if ($ok) {
            $sent++;
        }
    }

    return $sent;
}

function clickfix_dispatch_extension_message(
    PDO $pdo,
    int $createdBy,
    string $scope,
    ?string $clientId,
    int $userId,
    string $title,
    string $body,
    string $severity = 'info',
    int $expiresDays = 7,
    string $expiresAtRaw = '',
    string $clientIdsRaw = '',
    array $userIds = []
): array {
    $scope = strtolower(trim($scope));
    if (!in_array($scope, ['all', 'client', 'user', 'linked', 'unlinked'], true)) {
        return ['ok' => false, 'scope' => $scope, 'sent' => 0, 'resolved_clients' => 0];
    }

    if ($scope === 'all') {
        $ok = clickfix_send_extension_message($pdo, $createdBy, 'all', null, $title, $body, $severity, $expiresDays, $expiresAtRaw);
        return ['ok' => $ok, 'scope' => 'all', 'sent' => $ok ? 1 : 0, 'resolved_clients' => 0];
    }

    if ($scope === 'client') {
        $targets = [];
        $singleClientId = clickfix_normalize_client_id((string) $clientId);
        if ($singleClientId !== '' && strtolower($singleClientId) !== 'unknown') {
            $targets[$singleClientId] = true;
        }
        foreach (clickfix_parse_client_id_batch($clientIdsRaw) as $batchClientId) {
            $targets[$batchClientId] = true;
        }
        $resolvedClientIds = array_keys($targets);
        if (empty($resolvedClientIds)) {
            return ['ok' => false, 'scope' => 'client', 'sent' => 0, 'resolved_clients' => 0];
        }
        $sent = clickfix_send_extension_message_to_clients($pdo, $createdBy, $resolvedClientIds, $title, $body, $severity, $expiresDays, $expiresAtRaw);
        return ['ok' => $sent > 0, 'scope' => 'client', 'sent' => $sent, 'resolved_clients' => count($resolvedClientIds)];
    }

    if ($scope === 'linked') {
        $resolvedClientIds = clickfix_extension_client_ids_linked($pdo);
        if (empty($resolvedClientIds)) {
            return ['ok' => false, 'scope' => 'linked', 'sent' => 0, 'resolved_clients' => 0];
        }
        $sent = clickfix_send_extension_message_to_clients($pdo, $createdBy, $resolvedClientIds, $title, $body, $severity, $expiresDays, $expiresAtRaw);
        return [
            'ok' => $sent > 0,
            'scope' => 'linked',
            'sent' => $sent,
            'resolved_clients' => count($resolvedClientIds),
        ];
    }

    if ($scope === 'unlinked') {
        $resolvedClientIds = clickfix_extension_client_ids_unlinked($pdo);
        if (empty($resolvedClientIds)) {
            return ['ok' => false, 'scope' => 'unlinked', 'sent' => 0, 'resolved_clients' => 0];
        }
        $sent = clickfix_send_extension_message_to_clients($pdo, $createdBy, $resolvedClientIds, $title, $body, $severity, $expiresDays, $expiresAtRaw);
        return [
            'ok' => $sent > 0,
            'scope' => 'unlinked',
            'sent' => $sent,
            'resolved_clients' => count($resolvedClientIds),
        ];
    }

    $targetUsers = [];
    if ($userId > 0) {
        $targetUsers[$userId] = true;
    }
    foreach ($userIds as $rawUserId) {
        $candidate = (int) $rawUserId;
        if ($candidate > 0) {
            $targetUsers[$candidate] = true;
        }
    }
    $resolvedClientIds = clickfix_extension_client_ids_for_users($pdo, array_keys($targetUsers));
    if (empty($resolvedClientIds)) {
        return ['ok' => false, 'scope' => 'user', 'sent' => 0, 'resolved_clients' => 0];
    }
    $sent = clickfix_send_extension_message_to_clients($pdo, $createdBy, $resolvedClientIds, $title, $body, $severity, $expiresDays, $expiresAtRaw);
    return [
        'ok' => $sent > 0,
        'scope' => 'user',
        'sent' => $sent,
        'resolved_clients' => count($resolvedClientIds),
    ];
}

function clickfix_normalize_extension_message_expires_at(string $expiresAtRaw, int $expiresDays = 7): string
{
    $expiresAtRaw = trim($expiresAtRaw);
    $expiresDays = max(1, min(90, $expiresDays));
    if ($expiresAtRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAtRaw) === 1) {
        $timestamp = strtotime($expiresAtRaw . ' 23:59:59 UTC');
        if ($timestamp !== false) {
            return gmdate('c', $timestamp);
        }
    }
    return gmdate('c', time() + $expiresDays * 86400);
}

function clickfix_recent_extension_messages(PDO $pdo, int $limit = 120): array
{
    $limit = max(1, min(300, $limit));
    $stmt = $pdo->prepare(
        'SELECT em.*, u.username AS created_by_username
         FROM extension_messages em
         LEFT JOIN users u ON u.id = em.created_by
         ORDER BY em.id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function clickfix_deactivate_extension_message(PDO $pdo, int $messageId): bool
{
    if ($messageId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare(
        'UPDATE extension_messages
         SET active = 0
         WHERE id = :id'
    );
    return $stmt->execute([':id' => $messageId]);
}

function clickfix_delete_extension_message(PDO $pdo, int $messageId): bool
{
    if ($messageId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare(
        'DELETE FROM extension_messages
         WHERE id = :id'
    );
    return $stmt->execute([':id' => $messageId]);
}

function clickfix_update_extension_message(
    PDO $pdo,
    int $messageId,
    string $title,
    string $body,
    string $severity = 'info',
    int $expiresDays = 7,
    string $expiresAtRaw = '',
    ?bool $active = null
): bool {
    if ($messageId <= 0) {
        return false;
    }
    $title = substr(trim($title), 0, 180);
    $body = substr(trim($body), 0, 5000);
    if ($title === '' || $body === '') {
        return false;
    }
    $severity = strtolower(trim($severity));
    if (!in_array($severity, ['info', 'warning', 'critical'], true)) {
        $severity = 'info';
    }
    $expiresAt = clickfix_normalize_extension_message_expires_at($expiresAtRaw, $expiresDays);
    $setParts = [
        'title = :title',
        'body = :body',
        'severity = :severity',
        'expires_at = :expires_at',
    ];
    $params = [
        ':id' => $messageId,
        ':title' => $title,
        ':body' => $body,
        ':severity' => $severity,
        ':expires_at' => $expiresAt,
    ];
    if ($active !== null) {
        $setParts[] = 'active = :active';
        $params[':active'] = $active ? 1 : 0;
    }
    $stmt = $pdo->prepare(
        'UPDATE extension_messages
         SET ' . implode(', ', $setParts) . '
         WHERE id = :id'
    );
    return $stmt->execute($params);
}

function clickfix_purge_extension_messages(PDO $pdo, string $mode = 'inactive'): int
{
    $mode = strtolower(trim($mode));
    if ($mode === 'all') {
        $stmt = $pdo->prepare('DELETE FROM extension_messages');
        $stmt->execute();
        return max(0, (int) $stmt->rowCount());
    }
    if ($mode !== 'inactive') {
        return 0;
    }
    $stmt = $pdo->prepare(
        'DELETE FROM extension_messages
         WHERE active = 0
            OR (expires_at IS NOT NULL AND expires_at != "" AND expires_at < :now)'
    );
    $stmt->execute([':now' => gmdate('c')]);
    return max(0, (int) $stmt->rowCount());
}

function clickfix_extension_messages_for_client(PDO $pdo, string $clientId, int $limit = 30): array
{
    $clientId = substr(trim($clientId), 0, 120);
    $now = gmdate('c');
    $stmt = $pdo->prepare(
        'SELECT id, created_at, target_scope, target_client_id, title, body, severity, starts_at, expires_at
         FROM extension_messages
         WHERE active = 1
           AND (starts_at IS NULL OR starts_at = \'\' OR starts_at <= :now)
           AND (expires_at IS NULL OR expires_at = \'\' OR expires_at >= :now)
           AND (
             target_scope = \'all\'
             OR (target_scope = \'client\' AND target_client_id = :client_id)
           )
         ORDER BY id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':now', $now, PDO::PARAM_STR);
    $stmt->bindValue(':client_id', $clientId, PDO::PARAM_STR);
    $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function clickfix_next_schedule_at(string $period, ?int $fromTs = null): string
{
    $period = strtolower(trim($period));
    $ts = $fromTs ?? time();
    if ($period === 'weekly') {
        return gmdate('c', $ts + 7 * 86400);
    }
    if ($period === 'monthly') {
        return gmdate('c', strtotime('+1 month', $ts));
    }
    return gmdate('c', $ts + 86400);
}

function clickfix_upsert_report_schedule(PDO $pdo, string $period, string $recipient, bool $enabled): bool
{
    $period = strtolower(trim($period));
    if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) {
        return false;
    }
    $recipient = substr(trim($recipient), 0, 255);
    if ($recipient === '') {
        return false;
    }
    $now = gmdate('c');
    $check = $pdo->prepare('SELECT id FROM report_schedules WHERE period = :period LIMIT 1');
    $check->execute([':period' => $period]);
    $row = $check->fetch();
    if ($row) {
        $stmt = $pdo->prepare(
            'UPDATE report_schedules
             SET updated_at = :updated_at, recipient = :recipient, enabled = :enabled, next_run_at = :next_run_at
             WHERE id = :id'
        );
        return $stmt->execute([
            ':updated_at' => $now,
            ':recipient' => $recipient,
            ':enabled' => $enabled ? 1 : 0,
            ':next_run_at' => clickfix_next_schedule_at($period),
            ':id' => (int) ($row['id'] ?? 0),
        ]);
    }
    $stmt = $pdo->prepare(
        'INSERT INTO report_schedules (created_at, updated_at, period, recipient, enabled, last_run_at, next_run_at)
         VALUES (:created_at, :updated_at, :period, :recipient, :enabled, NULL, :next_run_at)'
    );
    return $stmt->execute([
        ':created_at' => $now,
        ':updated_at' => $now,
        ':period' => $period,
        ':recipient' => $recipient,
        ':enabled' => $enabled ? 1 : 0,
        ':next_run_at' => clickfix_next_schedule_at($period),
    ]);
}

function clickfix_list_report_schedules(PDO $pdo): array
{
    $cacheKey = clickfix_cache_key('report_schedules', ['v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }
    $rows = $pdo->query('SELECT * FROM report_schedules ORDER BY period ASC')->fetchAll();
    $result = is_array($rows) ? $rows : [];
    clickfix_cache_set($cacheKey, $result, 12);
    return $result;
}

function clickfix_generate_period_report(PDO $pdo, string $period): array
{
    $period = strtolower(trim($period));
    if ($period === 'weekly') {
        $from = gmdate('c', time() - 7 * 86400);
    } elseif ($period === 'monthly') {
        $from = gmdate('c', strtotime('-30 days'));
    } else {
        $period = 'daily';
        $from = gmdate('c', time() - 86400);
    }
    $cacheKey = clickfix_cache_key('period_report', ['period' => $period, 'v2' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }
    $fromSql = gmdate('Y-m-d H:i:s', strtotime($from) ?: time() - 86400);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as total_alerts,
                SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as total_blocks,
                COUNT(DISTINCT hostname) as unique_hosts,
                COUNT(DISTINCT COALESCE(NULLIF(client_id, ''), ip, 'unknown')) as active_clients
         FROM reports
         WHERE COALESCE(
                   datetime(replace(substr(received_at, 1, 19), 'T', ' ')),
                   datetime(received_at)
               ) >= datetime(:from_sql)"
    );
    $stmt->execute([':from_sql' => $fromSql]);
    $row = $stmt->fetch() ?: [];

    $totalAlerts = (int) ($row['total_alerts'] ?? 0);
    $totalBlocks = (int) ($row['total_blocks'] ?? 0);

    $topDomains = [];
    $domainsStmt = $pdo->prepare(
        "SELECT hostname,
                COUNT(*) as hits,
                SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_hits,
                MAX(received_at) as last_seen
         FROM reports
         WHERE COALESCE(
                   datetime(replace(substr(received_at, 1, 19), 'T', ' ')),
                   datetime(received_at)
               ) >= datetime(:from_sql)
           AND hostname IS NOT NULL AND hostname != ''
         GROUP BY hostname
         ORDER BY hits DESC
         LIMIT 15"
    );
    $domainsStmt->execute([':from_sql' => $fromSql]);
    foreach ($domainsStmt->fetchAll() as $domainRow) {
        $topDomains[] = [
            'hostname' => (string) ($domainRow['hostname'] ?? ''),
            'hits' => (int) ($domainRow['hits'] ?? 0),
            'blocked_hits' => (int) ($domainRow['blocked_hits'] ?? 0),
            'percent_total' => $totalAlerts > 0 ? round(((int) ($domainRow['hits'] ?? 0) * 100) / $totalAlerts, 2) : 0.0,
            'last_seen' => (string) ($domainRow['last_seen'] ?? ''),
        ];
    }

    $eventTypeDistribution = [];
    $eventStmt = $pdo->prepare(
        "SELECT COALESCE(NULLIF(TRIM(event_type), ''), 'clickfix_alert') as event_type,
                COUNT(*) as hits,
                SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_hits
         FROM reports
         WHERE COALESCE(
                   datetime(replace(substr(received_at, 1, 19), 'T', ' ')),
                   datetime(received_at)
               ) >= datetime(:from_sql)
         GROUP BY COALESCE(NULLIF(TRIM(event_type), ''), 'clickfix_alert')
         ORDER BY hits DESC
         LIMIT 12"
    );
    $eventStmt->execute([':from_sql' => $fromSql]);
    foreach ($eventStmt->fetchAll() as $eventRow) {
        $hits = (int) ($eventRow['hits'] ?? 0);
        $eventTypeDistribution[] = [
            'event_type' => (string) ($eventRow['event_type'] ?? 'clickfix_alert'),
            'hits' => $hits,
            'blocked_hits' => (int) ($eventRow['blocked_hits'] ?? 0),
            'percent_total' => $totalAlerts > 0 ? round(($hits * 100) / $totalAlerts, 2) : 0.0,
        ];
    }

    $severityDistribution = [];
    $severityStmt = $pdo->prepare(
        "SELECT CASE
                    WHEN COALESCE(server_score_total, score_total, 0) >= 75 THEN 'critical'
                    WHEN COALESCE(server_score_total, score_total, 0) >= 50 THEN 'high'
                    WHEN COALESCE(server_score_total, score_total, 0) >= 25 THEN 'medium'
                    ELSE 'low'
                END as severity,
                COUNT(*) as hits,
                SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) as blocked_hits
         FROM reports
         WHERE COALESCE(
                   datetime(replace(substr(received_at, 1, 19), 'T', ' ')),
                   datetime(received_at)
               ) >= datetime(:from_sql)
         GROUP BY severity
         ORDER BY CASE severity
                    WHEN 'critical' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    ELSE 4
                  END"
    );
    $severityStmt->execute([':from_sql' => $fromSql]);
    foreach ($severityStmt->fetchAll() as $severityRow) {
        $hits = (int) ($severityRow['hits'] ?? 0);
        $severityDistribution[] = [
            'severity' => (string) ($severityRow['severity'] ?? 'low'),
            'hits' => $hits,
            'blocked_hits' => (int) ($severityRow['blocked_hits'] ?? 0),
            'percent_total' => $totalAlerts > 0 ? round(($hits * 100) / $totalAlerts, 2) : 0.0,
        ];
    }

    $result = [
        'title' => 'ClickFix Mitigator - Top Sources of Attack',
        'period' => $period,
        'generated_at' => gmdate('c'),
        'from' => $from,
        'to' => gmdate('c'),
        'time_window' => [
            'earliest_event_time' => $from,
            'latest_event_time' => gmdate('c'),
            'label' => 'Earliest Event Time: ' . $from . ' to Latest Event Time: ' . gmdate('c'),
        ],
        'summary' => [
            'total_alerts' => $totalAlerts,
            'total_blocks' => $totalBlocks,
            'unique_hosts' => (int) ($row['unique_hosts'] ?? 0),
            'active_clients' => (int) ($row['active_clients'] ?? 0),
            'block_rate_percent' => $totalAlerts > 0 ? round(($totalBlocks * 100) / $totalAlerts, 2) : 0.0,
        ],
        'top_domains' => $topDomains,
        'top_sources_of_attack' => array_map(static function (array $domainRow): array {
            return [
                'attacking_host' => (string) ($domainRow['hostname'] ?? ''),
                'number_of_attacks' => (int) ($domainRow['hits'] ?? 0),
                'percent_total' => (float) ($domainRow['percent_total'] ?? 0.0),
                'blocked_attacks' => (int) ($domainRow['blocked_hits'] ?? 0),
                'last_seen' => (string) ($domainRow['last_seen'] ?? ''),
            ];
        }, $topDomains),
        'event_type_distribution' => $eventTypeDistribution,
        'severity_distribution' => $severityDistribution,
    ];
    clickfix_cache_set($cacheKey, $result, 15);
    return $result;
}

function clickfix_run_due_report_schedules(PDO $pdo, bool $forceRunAllEnabled = false): array
{
    $now = gmdate('c');
    if ($forceRunAllEnabled) {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM report_schedules
             WHERE enabled = 1
             ORDER BY id ASC"
        );
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare(
            "SELECT *
             FROM report_schedules
             WHERE enabled = 1
               AND (next_run_at IS NULL OR next_run_at = '' OR next_run_at <= :now)
             ORDER BY id ASC"
        );
        $stmt->execute([':now' => $now]);
    }
    $rows = $stmt->fetchAll();
    $results = [];
    $dir = dirname(__DIR__) . '/data/reports';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    foreach ($rows as $row) {
        $period = (string) ($row['period'] ?? 'daily');
        $payload = clickfix_generate_period_report($pdo, $period);
        $filename = $dir . '/report-' . $period . '-' . gmdate('Ymd-His') . '.json';
        $written = file_put_contents($filename, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL, LOCK_EX) !== false;
        $nextRun = clickfix_next_schedule_at($period);
        $update = $pdo->prepare('UPDATE report_schedules SET last_run_at = :last_run_at, next_run_at = :next_run_at, updated_at = :updated_at WHERE id = :id');
        $update->execute([
            ':last_run_at' => $now,
            ':next_run_at' => $nextRun,
            ':updated_at' => $now,
            ':id' => (int) ($row['id'] ?? 0),
        ]);
        $results[] = [
            'schedule_id' => (int) ($row['id'] ?? 0),
            'period' => $period,
            'recipient' => (string) ($row['recipient'] ?? ''),
            'file' => $written ? $filename : '',
            'ok' => $written,
        ];
    }
    return $results;
}

function clickfix_recent_users(PDO $pdo, int $limit = 120): array
{
    $limit = max(1, min($limit, 500));
    $cacheKey = clickfix_cache_key('recent_users', ['limit' => $limit, 'v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }
    $hasEmail = clickfix_has_column($pdo, 'users', 'email');
    $hasPreferredLang = clickfix_has_column($pdo, 'users', 'preferred_lang');
    $hasReputation = clickfix_has_column($pdo, 'users', 'reputation');
    $sql = 'SELECT id, created_at, username, ';
    $sql .= $hasEmail ? 'email' : "'' AS email";
    $sql .= ', role, verified, ';
    $sql .= $hasPreferredLang ? 'preferred_lang' : "'en' AS preferred_lang";
    $sql .= ', ';
    $sql .= $hasReputation ? 'reputation' : '0 AS reputation';
    $sql .= ' FROM users ORDER BY id DESC LIMIT :limit';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $normalizedRole = clickfix_normalize_role((string) ($row['role'] ?? 'analyst_mid'));
        $row['role'] = $normalizedRole;
        $row['role_label'] = clickfix_role_label($normalizedRole);
        $row['role_rank'] = clickfix_role_rank($normalizedRole);
        $row['preferred_lang'] = clickfix_normalize_user_language((string) ($row['preferred_lang'] ?? 'en'));
        $row['reputation'] = (int) ($row['reputation'] ?? 0);
    }
    unset($row);
    clickfix_cache_set($cacheKey, $rows, 10);
    return $rows;
}

function clickfix_create_user(
    PDO $pdo,
    string $username,
    string $password,
    string $role,
    bool $verified = true,
    string $email = '',
    string $preferredLang = 'en'
): bool
{
    $username = trim($username);
    if ($username === '' || strlen($username) > 80) {
        return false;
    }
    $email = strtolower(trim($email));
    if ($email === '' || strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }
    if (strlen($password) < 10) {
        return false;
    }
    $role = clickfix_normalize_role($role);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '') {
        return false;
    }
    $hasEmail = clickfix_has_column($pdo, 'users', 'email');
    $hasPreferredLang = clickfix_has_column($pdo, 'users', 'preferred_lang');
    $hasReputation = clickfix_has_column($pdo, 'users', 'reputation');
    if (!$hasEmail) {
        return false;
    }
    $normalizedLang = clickfix_normalize_user_language($preferredLang);

    if ($hasPreferredLang && $hasReputation) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (created_at, username, email, password_hash, role, verified, preferred_lang, reputation)
             VALUES (:created_at, :username, :email, :password_hash, :role, :verified, :preferred_lang, :reputation)'
        );
        return $stmt->execute([
            ':created_at' => gmdate('c'),
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $hash,
            ':role' => $role,
            ':verified' => $verified ? 1 : 0,
            ':preferred_lang' => $normalizedLang,
            ':reputation' => 0,
        ]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (created_at, username, email, password_hash, role, verified)
         VALUES (:created_at, :username, :email, :password_hash, :role, :verified)'
    );
    return $stmt->execute([
        ':created_at' => gmdate('c'),
        ':username' => $username,
        ':email' => $email,
        ':password_hash' => $hash,
        ':role' => $role,
        ':verified' => $verified ? 1 : 0,
    ]);
}

function clickfix_update_user_profile(
    PDO $pdo,
    int $userId,
    string $role,
    bool $verified,
    ?string $newPassword = null,
    ?string $email = null,
    ?string $preferredLang = null,
    ?int $reputation = null
): bool
{
    if ($userId <= 0) {
        return false;
    }
    $role = clickfix_normalize_role($role);
    $hasEmail = clickfix_has_column($pdo, 'users', 'email');
    $hasPreferredLang = clickfix_has_column($pdo, 'users', 'preferred_lang');
    $hasReputation = clickfix_has_column($pdo, 'users', 'reputation');
    $normalizedEmail = strtolower(trim((string) $email));
    $shouldUpdateEmail = $hasEmail && $normalizedEmail !== '';
    $normalizedLang = clickfix_normalize_user_language((string) ($preferredLang ?? 'en'));
    $shouldUpdateLang = $hasPreferredLang && $preferredLang !== null && trim((string) $preferredLang) !== '';
    $normalizedRep = $hasReputation ? max(-1000, min(100000, (int) ($reputation ?? 0))) : 0;
    $shouldUpdateRep = $hasReputation && $reputation !== null;
    if ($shouldUpdateEmail && (strlen($normalizedEmail) > 190 || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false)) {
        return false;
    }
    if ($newPassword !== null && trim($newPassword) !== '') {
        if (strlen($newPassword) < 10) {
            return false;
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            return false;
        }
        $parts = ['role = :role', 'verified = :verified', 'password_hash = :hash'];
        $params = [
            ':role' => $role,
            ':verified' => $verified ? 1 : 0,
            ':hash' => $hash,
            ':id' => $userId,
        ];
        if ($shouldUpdateEmail) {
            $parts[] = 'email = :email';
            $params[':email'] = $normalizedEmail;
        }
        if ($shouldUpdateLang) {
            $parts[] = 'preferred_lang = :preferred_lang';
            $params[':preferred_lang'] = $normalizedLang;
        }
        if ($shouldUpdateRep) {
            $parts[] = 'reputation = :reputation';
            $params[':reputation'] = $normalizedRep;
        }
        $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $parts) . ' WHERE id = :id');
        return $stmt->execute($params);
    }

    $parts = ['role = :role', 'verified = :verified'];
    $params = [
        ':role' => $role,
        ':verified' => $verified ? 1 : 0,
        ':id' => $userId,
    ];
    if ($shouldUpdateEmail) {
        $parts[] = 'email = :email';
        $params[':email'] = $normalizedEmail;
    }
    if ($shouldUpdateLang) {
        $parts[] = 'preferred_lang = :preferred_lang';
        $params[':preferred_lang'] = $normalizedLang;
    }
    if ($shouldUpdateRep) {
        $parts[] = 'reputation = :reputation';
        $params[':reputation'] = $normalizedRep;
    }
    $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $parts) . ' WHERE id = :id');
    return $stmt->execute($params);
}

function clickfix_user_update_preferences(PDO $pdo, int $userId, string $preferredLang): bool
{
    if ($userId <= 0) {
        return false;
    }
    if (!clickfix_has_column($pdo, 'users', 'preferred_lang')) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE users SET preferred_lang = :preferred_lang WHERE id = :id');
    return $stmt->execute([
        ':preferred_lang' => clickfix_normalize_user_language($preferredLang),
        ':id' => $userId,
    ]);
}

function clickfix_user_change_password(PDO $pdo, int $userId, string $currentPassword, string $newPassword): bool
{
    if ($userId <= 0) {
        return false;
    }
    $newPassword = trim($newPassword);
    if (strlen($newPassword) < 10) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $userId]);
    $hash = (string) ($stmt->fetchColumn() ?: '');
    if ($hash === '' || !password_verify($currentPassword, $hash)) {
        return false;
    }
    $nextHash = password_hash($newPassword, PASSWORD_DEFAULT);
    if (!is_string($nextHash) || $nextHash === '') {
        return false;
    }
    $update = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
    return $update->execute([
        ':password_hash' => $nextHash,
        ':id' => $userId,
    ]);
}

function clickfix_user_reload_session(PDO $pdo, int $userId): void
{
    if ($userId <= 0 || !isset($_SESSION['clickfix_user']) || !is_array($_SESSION['clickfix_user'])) {
        return;
    }
    $hasEmail = clickfix_has_column($pdo, 'users', 'email');
    $hasPreferredLang = clickfix_has_column($pdo, 'users', 'preferred_lang');
    $hasReputation = clickfix_has_column($pdo, 'users', 'reputation');
    $hasProfileTheme = clickfix_has_column($pdo, 'users', 'profile_theme');
    $hasProfileAvatarUrl = clickfix_has_column($pdo, 'users', 'profile_avatar_url');
    $sql = 'SELECT id, username, ';
    $sql .= $hasEmail ? 'email' : "'' AS email";
    $sql .= ', role, verified, ';
    $sql .= $hasPreferredLang ? 'preferred_lang' : "'en' AS preferred_lang";
    $sql .= ', ';
    $sql .= $hasReputation ? 'reputation' : '0 AS reputation';
    $sql .= ', ';
    $sql .= $hasProfileTheme ? 'profile_theme' : "'default' AS profile_theme";
    $sql .= ', ';
    $sql .= $hasProfileAvatarUrl ? 'profile_avatar_url' : "'' AS profile_avatar_url";
    $sql .= ' FROM users WHERE id = :id LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }
    $role = clickfix_normalize_role((string) ($row['role'] ?? 'analyst_jr'));
    $_SESSION['clickfix_user'] = [
        'id' => (int) ($row['id'] ?? 0),
        'username' => (string) ($row['username'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'role' => $role,
        'role_label' => clickfix_role_label($role),
        'role_rank' => clickfix_role_rank($role),
        'verified' => (int) ($row['verified'] ?? 0),
        'preferred_lang' => clickfix_normalize_user_language((string) ($row['preferred_lang'] ?? 'en')),
        'reputation' => (int) ($row['reputation'] ?? 0),
        'profile_theme' => clickfix_profile_normalize_theme((string) ($row['profile_theme'] ?? 'default')),
        'profile_avatar_url' => clickfix_profile_normalize_avatar_url((string) ($row['profile_avatar_url'] ?? '')),
    ];
}

function clickfix_user_api_key_providers(): array
{
    return [
        'virustotal' => [
            'label' => 'VirusTotal',
            'help' => 'Domain/IP/URL lookup',
        ],
        'abuseipdb' => [
            'label' => 'AbuseIPDB',
            'help' => 'IP reputation lookup',
        ],
        'urlscan' => [
            'label' => 'URLScan',
            'help' => 'Search scans by URL/domain/IP',
        ],
        'threatrip' => [
            'label' => 'Threat.rip',
            'help' => 'File intelligence and malware report lookup by SHA256',
        ],
    ];
}

function clickfix_normalize_user_api_provider(string $provider): string
{
    $provider = strtolower(trim($provider));
    $allowed = clickfix_user_api_key_providers();
    return isset($allowed[$provider]) ? $provider : '';
}

function clickfix_provider_service_api_key(string $provider): string
{
    $provider = clickfix_normalize_user_api_provider($provider);
    if ($provider === '') {
        return '';
    }

    $envMap = [
        'virustotal' => 'CLICKFIX_PROVIDER_VIRUSTOTAL_API_KEY',
        'abuseipdb' => 'CLICKFIX_PROVIDER_ABUSEIPDB_API_KEY',
        'urlscan' => 'CLICKFIX_PROVIDER_URLSCAN_API_KEY',
        'threatrip' => 'CLICKFIX_PROVIDER_THREATRIP_API_KEY',
    ];
    $envKey = (string) ($envMap[$provider] ?? '');
    if ($envKey === '') {
        return '';
    }

    $value = trim((string) clickfix_env($envKey, ''));
    if ($value === '') {
        return '';
    }
    return substr($value, 0, 600);
}

function clickfix_user_api_keys_secret(): string
{
    $secret = trim((string) clickfix_env('CLICKFIX_USER_API_KEYS_SECRET', ''));
    if ($secret !== '') {
        return $secret;
    }
    return clickfix_jwt_secret();
}

function clickfix_encrypt_user_api_key(string $plain): string
{
    $plain = trim($plain);
    if ($plain === '') {
        return '';
    }
    if (!function_exists('openssl_encrypt')) {
        return '';
    }
    $key = hash('sha256', clickfix_user_api_keys_secret(), true);
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if (!is_string($cipher) || $cipher === '' || !is_string($tag) || strlen($tag) < 12) {
        return '';
    }
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function clickfix_decrypt_user_api_key(string $encoded): string
{
    $encoded = trim($encoded);
    if ($encoded === '') {
        return '';
    }
    if (!function_exists('openssl_decrypt')) {
        return '';
    }
    if (!clickfix_str_starts_with($encoded, 'v1:')) {
        return '';
    }
    $blob = base64_decode(substr($encoded, 3), true);
    if (!is_string($blob) || strlen($blob) <= 28) {
        return '';
    }
    $iv = substr($blob, 0, 12);
    $tag = substr($blob, 12, 16);
    $cipher = substr($blob, 28);
    $key = hash('sha256', clickfix_user_api_keys_secret(), true);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return is_string($plain) ? trim($plain) : '';
}

function clickfix_mask_secret(string $value): string
{
    $value = trim($value);
    $len = strlen($value);
    if ($len <= 0) {
        return '';
    }
    if ($len <= 4) {
        return str_repeat('*', $len);
    }
    return substr($value, 0, 2) . str_repeat('*', max(4, $len - 4)) . substr($value, -2);
}

function clickfix_user_api_keys(PDO $pdo, int $userId, bool $includeSecrets = false): array
{
    $providers = clickfix_user_api_key_providers();
    $result = [];
    foreach ($providers as $provider => $meta) {
        $result[$provider] = [
            'provider' => $provider,
            'label' => (string) ($meta['label'] ?? $provider),
            'help' => (string) ($meta['help'] ?? ''),
            'has_key' => false,
            'masked' => '',
            'updated_at' => '',
            'api_key' => '',
        ];
    }
    if ($userId <= 0 || !clickfix_has_table($pdo, 'user_api_keys')) {
        return array_values($result);
    }
    $stmt = $pdo->prepare(
        'SELECT provider, api_key_enc, updated_at
         FROM user_api_keys
         WHERE user_id = :user_id AND active = 1'
    );
    $stmt->execute([':user_id' => $userId]);
    foreach ($stmt->fetchAll() as $row) {
        $provider = clickfix_normalize_user_api_provider((string) ($row['provider'] ?? ''));
        if ($provider === '' || !isset($result[$provider])) {
            continue;
        }
        $raw = clickfix_decrypt_user_api_key((string) ($row['api_key_enc'] ?? ''));
        if ($raw === '') {
            continue;
        }
        $result[$provider]['has_key'] = true;
        $result[$provider]['masked'] = clickfix_mask_secret($raw);
        $result[$provider]['updated_at'] = (string) ($row['updated_at'] ?? '');
        if ($includeSecrets) {
            $result[$provider]['api_key'] = $raw;
        }
    }
    return array_values($result);
}

function clickfix_user_api_key_upsert(PDO $pdo, int $userId, string $provider, string $apiKey, string $note = ''): bool
{
    if ($userId <= 0 || !clickfix_has_table($pdo, 'user_api_keys')) {
        return false;
    }
    $provider = clickfix_normalize_user_api_provider($provider);
    if ($provider === '') {
        return false;
    }
    $apiKey = trim($apiKey);
    if ($apiKey === '' || strlen($apiKey) > 600) {
        return false;
    }
    $encrypted = clickfix_encrypt_user_api_key($apiKey);
    if ($encrypted === '') {
        return false;
    }
    $now = gmdate('c');
    $stmt = $pdo->prepare(
        'INSERT INTO user_api_keys (created_at, updated_at, user_id, provider, api_key_enc, note, active)
         VALUES (:created_at, :updated_at, :user_id, :provider, :api_key_enc, :note, 1)
         ON CONFLICT(user_id, provider) DO UPDATE SET
           updated_at = excluded.updated_at,
           api_key_enc = excluded.api_key_enc,
           note = excluded.note,
           active = 1'
    );
    return $stmt->execute([
        ':created_at' => $now,
        ':updated_at' => $now,
        ':user_id' => $userId,
        ':provider' => $provider,
        ':api_key_enc' => $encrypted,
        ':note' => substr(trim($note), 0, 180),
    ]);
}

function clickfix_user_api_key_delete(PDO $pdo, int $userId, string $provider): bool
{
    if ($userId <= 0 || !clickfix_has_table($pdo, 'user_api_keys')) {
        return false;
    }
    $provider = clickfix_normalize_user_api_provider($provider);
    if ($provider === '') {
        return false;
    }
    $stmt = $pdo->prepare(
        'UPDATE user_api_keys
         SET active = 0, updated_at = :updated_at
         WHERE user_id = :user_id AND provider = :provider'
    );
    return $stmt->execute([
        ':updated_at' => gmdate('c'),
        ':user_id' => $userId,
        ':provider' => $provider,
    ]);
}

function clickfix_user_api_key_value(PDO $pdo, int $userId, string $provider): string
{
    if ($userId <= 0 || !clickfix_has_table($pdo, 'user_api_keys')) {
        return '';
    }
    $provider = clickfix_normalize_user_api_provider($provider);
    if ($provider === '') {
        return '';
    }
    $stmt = $pdo->prepare(
        'SELECT api_key_enc
         FROM user_api_keys
         WHERE user_id = :user_id AND provider = :provider AND active = 1
         LIMIT 1'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':provider' => $provider,
    ]);
    $enc = (string) ($stmt->fetchColumn() ?: '');
    return clickfix_decrypt_user_api_key($enc);
}

function clickfix_platform_api_allowed_scopes(): array
{
    return [
        'config:read',
        'intel:read',
        'alerts:read',
        'stats:read',
        'reviews:write',
        'lists:write',
        'messages:write',
        'investigations:read',
        'investigations:write',
    ];
}

function clickfix_platform_api_scope_text(string $scopeText): string
{
    $allowed = clickfix_platform_api_allowed_scopes();
    $set = [];
    foreach (preg_split('/\s+/', strtolower(trim($scopeText))) ?: [] as $scope) {
        $scope = trim((string) $scope);
        if ($scope === '' || !in_array($scope, $allowed, true)) {
            continue;
        }
        $set[$scope] = true;
    }
    if (empty($set)) {
        return 'intel:read';
    }
    return implode(' ', array_keys($set));
}

function clickfix_platform_api_key_prefix(string $apiKey): string
{
    $apiKey = trim($apiKey);
    if ($apiKey === '') {
        return '';
    }
    return substr($apiKey, 0, min(18, strlen($apiKey)));
}

function clickfix_platform_api_generate_key(): string
{
    return 'cfm_uak_' . clickfix_base64url_encode(random_bytes(36));
}

function clickfix_platform_api_key_hash(string $apiKey): string
{
    return clickfix_hash_secret('user_api_key|' . trim($apiKey));
}

function clickfix_user_platform_api_keys(PDO $pdo, int $userId): array
{
    if ($userId <= 0 || !clickfix_has_table($pdo, 'api_user_keys')) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT id, created_at, updated_at, label, key_prefix, scopes, max_rpm, last_used_at, last_ip, expires_at, revoked_at
         FROM api_user_keys
         WHERE user_id = :user_id
         ORDER BY CASE WHEN revoked_at IS NULL THEN 0 ELSE 1 END, id DESC
         LIMIT 120'
    );
    $stmt->execute([':user_id' => $userId]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $expiresAt = trim((string) ($row['expires_at'] ?? ''));
        $expiresTs = $expiresAt !== '' ? strtotime($expiresAt) : false;
        $isExpired = is_int($expiresTs) ? ($expiresTs < time()) : false;
        $isRevoked = trim((string) ($row['revoked_at'] ?? '')) !== '';
        $rows[] = [
            'id' => (int) ($row['id'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'key_prefix' => (string) ($row['key_prefix'] ?? ''),
            'scopes' => clickfix_platform_api_scope_text((string) ($row['scopes'] ?? 'intel:read')),
            'max_rpm' => max(30, min(2000, (int) ($row['max_rpm'] ?? 120))),
            'last_used_at' => (string) ($row['last_used_at'] ?? ''),
            'last_ip' => (string) ($row['last_ip'] ?? ''),
            'expires_at' => $expiresAt,
            'revoked_at' => (string) ($row['revoked_at'] ?? ''),
            'is_active' => !$isRevoked && !$isExpired,
            'is_expired' => $isExpired,
            'is_revoked' => $isRevoked,
        ];
    }
    return $rows;
}

function clickfix_user_platform_api_key_create(
    PDO $pdo,
    int $userId,
    string $label,
    int $expiresDays = 90,
    string $scopeText = 'intel:read',
    int $maxRpm = 120
): ?array {
    if ($userId <= 0 || !clickfix_has_table($pdo, 'api_user_keys')) {
        return null;
    }

    $userStmt = $pdo->prepare('SELECT role, verified FROM users WHERE id = :id LIMIT 1');
    $userStmt->execute([':id' => $userId]);
    $userRow = $userStmt->fetch();
    if (!$userRow || (int) ($userRow['verified'] ?? 0) !== 1) {
        return null;
    }
    $role = clickfix_normalize_role((string) ($userRow['role'] ?? 'analyst_jr'));
    if (clickfix_role_rank($role) < clickfix_role_rank('analyst_jr')) {
        return null;
    }

    $label = preg_replace('/\s+/', ' ', trim($label)) ?? '';
    if ($label === '') {
        $label = 'integration-' . gmdate('Ymd');
    }
    $label = substr($label, 0, 80);
    $scopeText = clickfix_platform_api_scope_text($scopeText);
    $expiresDays = max(1, min(365, $expiresDays));
    $maxRpm = max(30, min(2000, $maxRpm));
    $apiKey = clickfix_platform_api_generate_key();
    $keyHash = clickfix_platform_api_key_hash($apiKey);
    $keyPrefix = clickfix_platform_api_key_prefix($apiKey);
    if ($apiKey === '' || $keyHash === '' || $keyPrefix === '') {
        return null;
    }

    $nowIso = gmdate('c');
    $expiresAt = gmdate('c', time() + ($expiresDays * 86400));
    $insert = $pdo->prepare(
        'INSERT INTO api_user_keys (created_at, updated_at, user_id, label, key_prefix, key_hash, scopes, max_rpm, last_used_at, last_ip, expires_at, revoked_at)
         VALUES (:created_at, :updated_at, :user_id, :label, :key_prefix, :key_hash, :scopes, :max_rpm, NULL, NULL, :expires_at, NULL)'
    );
    $ok = $insert->execute([
        ':created_at' => $nowIso,
        ':updated_at' => $nowIso,
        ':user_id' => $userId,
        ':label' => $label,
        ':key_prefix' => $keyPrefix,
        ':key_hash' => $keyHash,
        ':scopes' => $scopeText,
        ':max_rpm' => $maxRpm,
        ':expires_at' => $expiresAt,
    ]);
    if (!$ok) {
        return null;
    }

    return [
        'id' => (int) $pdo->lastInsertId(),
        'api_key' => $apiKey,
        'key_prefix' => $keyPrefix,
        'label' => $label,
        'scopes' => $scopeText,
        'max_rpm' => $maxRpm,
        'created_at' => $nowIso,
        'expires_at' => $expiresAt,
    ];
}

function clickfix_user_platform_api_key_revoke(PDO $pdo, int $userId, int $keyId): bool
{
    if ($userId <= 0 || $keyId <= 0 || !clickfix_has_table($pdo, 'api_user_keys')) {
        return false;
    }
    $stmt = $pdo->prepare(
        'UPDATE api_user_keys
         SET revoked_at = :revoked_at, updated_at = :updated_at
         WHERE id = :id AND user_id = :user_id AND revoked_at IS NULL'
    );
    return $stmt->execute([
        ':revoked_at' => gmdate('c'),
        ':updated_at' => gmdate('c'),
        ':id' => $keyId,
        ':user_id' => $userId,
    ]);
}

function clickfix_resolve_host_to_ip(string $host): string
{
    $host = clickfix_normalize_domain($host);
    if ($host === '') {
        return '';
    }

    $candidates = [];
    if (function_exists('dns_get_record')) {
        $dnsTypes = defined('DNS_AAAA') ? (DNS_A + DNS_AAAA) : DNS_A;
        $records = @dns_get_record($host, $dnsTypes);
        if (is_array($records)) {
            foreach ($records as $record) {
                $ip = trim((string) ($record['ip'] ?? $record['ipv6'] ?? ''));
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                    $candidates[$ip] = true;
                }
            }
        }
    }
    if (function_exists('gethostbynamel')) {
        $fallback = @gethostbynamel($host);
        if (is_array($fallback)) {
            foreach ($fallback as $ip) {
                $ip = trim((string) $ip);
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                    $candidates[$ip] = true;
                }
            }
        }
    }
    $single = @gethostbyname($host);
    if (is_string($single) && filter_var($single, FILTER_VALIDATE_IP)) {
        $candidates[$single] = true;
    }

    if (empty($candidates)) {
        return '';
    }

    foreach (array_keys($candidates) as $ip) {
        if (clickfix_is_public_ip($ip)) {
            return $ip;
        }
    }

    // Fallback to first resolved IP even if private/reserved.
    return (string) array_key_first($candidates);
}

function clickfix_user_api_lookup(string $provider, string $apiKey, string $indicator): array
{
    $provider = clickfix_normalize_user_api_provider($provider);
    $apiKey = trim($apiKey);
    $indicatorRaw = trim($indicator);
    if ($provider === '' || $apiKey === '' || $indicatorRaw === '') {
        return [
            'ok' => false,
            'provider' => $provider,
            'target' => $indicatorRaw,
            'status' => 0,
            'summary' => [],
            'response' => null,
            'error' => 'Parametros invalidos.',
        ];
    }

    $target = substr($indicatorRaw, 0, 500);
    $isIp = filter_var($target, FILTER_VALIDATE_IP) !== false;
    $isUrl = (bool) preg_match('/^https?:\/\//i', $target);
    $domain = clickfix_normalize_domain($target);
    $hashKind = clickfix_pipeline_artifact_hash_kind($target);
    $queryIp = '';
    $resolvedFromHost = '';

    $url = '';
    $headers = [
        'User-Agent' => 'ClickFixMitigator/1.0',
        'Accept' => 'application/json',
    ];

    if ($provider === 'virustotal') {
        $headers['x-apikey'] = $apiKey;
        if ($hashKind !== '') {
            $url = 'https://www.virustotal.com/api/v3/files/' . rawurlencode($target);
        } elseif ($isUrl) {
            $urlId = rtrim(strtr(base64_encode($target), '+/', '-_'), '=');
            $url = 'https://www.virustotal.com/api/v3/urls/' . rawurlencode($urlId);
        } elseif ($isIp) {
            $url = 'https://www.virustotal.com/api/v3/ip_addresses/' . rawurlencode($target);
        } else {
            $lookup = $domain !== '' ? $domain : $target;
            $url = 'https://www.virustotal.com/api/v3/domains/' . rawurlencode($lookup);
        }
    } elseif ($provider === 'abuseipdb') {
        $queryIp = $target;
        if (!$isIp) {
            $hostForResolution = '';
            if ($isUrl) {
                $hostForResolution = clickfix_normalize_domain((string) parse_url($target, PHP_URL_HOST));
            } else {
                $hostForResolution = $domain !== '' ? $domain : clickfix_normalize_domain($target);
            }
            if ($hostForResolution === '') {
                return [
                    'ok' => false,
                    'provider' => $provider,
                    'target' => $target,
                    'status' => 0,
                    'summary' => [],
                    'response' => null,
                    'error' => 'AbuseIPDB requiere IP o dominio resolvible.',
                ];
            }
            $resolvedIp = clickfix_resolve_host_to_ip($hostForResolution);
            if ($resolvedIp === '') {
                return [
                    'ok' => false,
                    'provider' => $provider,
                    'target' => $target,
                    'status' => 0,
                    'summary' => [],
                    'response' => null,
                    'error' => 'No se pudo resolver el dominio a IP.',
                ];
            }
            $queryIp = $resolvedIp;
            $resolvedFromHost = $hostForResolution;
        }
        if (!filter_var($queryIp, FILTER_VALIDATE_IP)) {
            return [
                'ok' => false,
                'provider' => $provider,
                'target' => $target,
                'status' => 0,
                'summary' => [],
                'response' => null,
                'error' => 'IP invalida para AbuseIPDB.',
            ];
        }
        $headers['Key'] = $apiKey;
        $headers['Accept'] = 'application/json';
        $url = 'https://api.abuseipdb.com/api/v2/check?ipAddress=' . rawurlencode($queryIp) . '&maxAgeInDays=90&verbose';
    } elseif ($provider === 'urlscan') {
        $headers['API-Key'] = $apiKey;
        $query = '';
        if ($isUrl) {
            $query = 'page.url:"' . $target . '"';
        } elseif ($isIp) {
            $query = 'page.ip:' . $target;
        } else {
            $lookup = $domain !== '' ? $domain : $target;
            $query = 'domain:' . $lookup;
        }
        $url = 'https://urlscan.io/api/v1/search/?q=' . rawurlencode($query) . '&size=10';
    } elseif ($provider === 'threatrip') {
        if ($hashKind !== 'sha256') {
            return [
                'ok' => false,
                'provider' => $provider,
                'target' => $target,
                'status' => 0,
                'summary' => [],
                'response' => null,
                'error' => 'Threat.rip requiere SHA256.',
            ];
        }
        return clickfix_pipeline_threatrip_lookup_by_sha256($target, $apiKey, '');
    }

    if ($url === '') {
        return [
            'ok' => false,
            'provider' => $provider,
            'target' => $target,
            'status' => 0,
            'summary' => [],
            'response' => null,
            'error' => 'Proveedor no soportado.',
        ];
    }

    $http = clickfix_http_request($url, 'GET', $headers, null, 10);
    $status = (int) ($http['status'] ?? 0);
    $body = (string) ($http['body'] ?? '');
    $decoded = null;
    if ($body !== '') {
        $tmp = json_decode($body, true);
        if (is_array($tmp)) {
            $decoded = $tmp;
        }
    }

    $summary = [];
    if ($provider === 'virustotal' && is_array($decoded)) {
        $stats = is_array($decoded['data']['attributes']['last_analysis_stats'] ?? null)
            ? $decoded['data']['attributes']['last_analysis_stats']
            : [];
        if (!empty($stats)) {
            $summary = [
                'malicious' => (int) ($stats['malicious'] ?? 0),
                'suspicious' => (int) ($stats['suspicious'] ?? 0),
                'harmless' => (int) ($stats['harmless'] ?? 0),
                'undetected' => (int) ($stats['undetected'] ?? 0),
            ];
        }
    } elseif ($provider === 'abuseipdb' && is_array($decoded)) {
        $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : [];
        if (!empty($data)) {
            $summary = [
                'abuseConfidenceScore' => (int) ($data['abuseConfidenceScore'] ?? 0),
                'totalReports' => (int) ($data['totalReports'] ?? 0),
                'countryCode' => (string) ($data['countryCode'] ?? ''),
                'isp' => (string) ($data['isp'] ?? ''),
                'queryIp' => (string) ($data['ipAddress'] ?? $queryIp),
                'resolvedFrom' => $resolvedFromHost,
            ];
        }
    } elseif ($provider === 'urlscan' && is_array($decoded)) {
        $results = is_array($decoded['results'] ?? null) ? $decoded['results'] : [];
        $summary = [
            'total' => (int) ($decoded['total'] ?? count($results)),
            'returned' => count($results),
        ];
    }

    return [
        'ok' => $status >= 200 && $status < 300,
        'provider' => $provider,
        'target' => $target,
        'status' => $status,
        'summary' => $summary,
        'response' => $decoded,
        'error' => ($status >= 200 && $status < 300) ? '' : ('HTTP ' . $status),
    ];
}

function clickfix_intel_lookup_target_type(string $target): string
{
    $value = trim($target);
    if ($value === '') {
        return 'unknown';
    }
    if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
        return 'ip';
    }
    if ((bool) preg_match('/^https?:\/\//i', $value)) {
        return 'url';
    }
    $hashKind = clickfix_pipeline_artifact_hash_kind($value);
    if ($hashKind !== '') {
        return $hashKind;
    }
    $normalized = clickfix_normalize_domain($value);
    return $normalized !== '' ? 'domain' : 'unknown';
}

function clickfix_intel_pretty_json($value, int $maxLength = 120000): string
{
    $maxLength = max(4000, min(300000, $maxLength));
    if (is_array($value) || is_object($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($encoded)) {
            return '';
        }
        if (strlen($encoded) > $maxLength) {
            return substr($encoded, 0, $maxLength) . PHP_EOL . '... [truncated]';
        }
        return $encoded;
    }
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (is_string($encoded) && $encoded !== '') {
            if (strlen($encoded) > $maxLength) {
                return substr($encoded, 0, $maxLength) . PHP_EOL . '... [truncated]';
            }
            return $encoded;
        }
    }
    if (strlen($raw) > $maxLength) {
        return substr($raw, 0, $maxLength) . PHP_EOL . '... [truncated]';
    }
    return $raw;
}

function clickfix_investigation_api_lookup_store(
    PDO $pdo,
    int $userId,
    int $graphId,
    array $lookup,
    string $responseJson
): bool {
    if ($userId <= 0 || !clickfix_has_table($pdo, 'investigation_api_lookup_history')) {
        return false;
    }
    $provider = clickfix_normalize_user_api_provider((string) ($lookup['provider'] ?? ''));
    if ($provider === '') {
        $provider = 'unknown';
    }
    $target = substr(trim((string) ($lookup['target'] ?? '')), 0, 500);
    if ($target === '') {
        $target = '(empty)';
    }
    $status = (int) ($lookup['status'] ?? 0);
    $ok = !empty($lookup['ok']) ? 1 : 0;
    $error = substr(trim((string) ($lookup['error'] ?? '')), 0, 1000);
    $summary = is_array($lookup['summary'] ?? null) ? $lookup['summary'] : [];
    $summaryJson = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($summaryJson)) {
        $summaryJson = '{}';
    }
    $prettyResponse = clickfix_intel_pretty_json($responseJson, 120000);
    if ($prettyResponse === '' && is_array($lookup['response'] ?? null)) {
        $prettyResponse = clickfix_intel_pretty_json($lookup['response'], 120000);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO investigation_api_lookup_history (
            created_at, user_id, graph_id, provider, target, target_type, status, ok, error, summary_json, response_json
         ) VALUES (
            :created_at, :user_id, :graph_id, :provider, :target, :target_type, :status, :ok, :error, :summary_json, :response_json
         )'
    );
    $okInsert = $stmt->execute([
        ':created_at' => gmdate('c'),
        ':user_id' => $userId,
        ':graph_id' => max(0, $graphId),
        ':provider' => $provider,
        ':target' => $target,
        ':target_type' => clickfix_intel_lookup_target_type($target),
        ':status' => $status,
        ':ok' => $ok,
        ':error' => $error,
        ':summary_json' => $summaryJson,
        ':response_json' => $prettyResponse,
    ]);
    if (!$okInsert) {
        return false;
    }

    // Keep recent lookup history bounded per user.
    try {
        $trimStmt = $pdo->prepare(
            'DELETE FROM investigation_api_lookup_history
             WHERE user_id = :user_id
               AND id NOT IN (
                 SELECT id
                 FROM investigation_api_lookup_history
                 WHERE user_id = :user_id_inner
                 ORDER BY id DESC
                 LIMIT 300
               )'
        );
        $trimStmt->execute([
            ':user_id' => $userId,
            ':user_id_inner' => $userId,
        ]);
    } catch (Throwable $exception) {
        // Ignore retention trimming failures.
    }
    return true;
}

function clickfix_investigation_api_lookup_recent(PDO $pdo, int $userId, int $limit = 20, int $graphId = 0): array
{
    if ($userId <= 0 || !clickfix_has_table($pdo, 'investigation_api_lookup_history')) {
        return [];
    }
    $limit = max(1, min(200, $limit));
    $where = 'WHERE user_id = :user_id';
    $params = [':user_id' => $userId];
    if ($graphId > 0) {
        $where .= ' AND graph_id = :graph_id';
        $params[':graph_id'] = $graphId;
    }
    $stmt = $pdo->prepare(
        "SELECT id, created_at, graph_id, provider, target, target_type, status, ok, error, summary_json, response_json
         FROM investigation_api_lookup_history
         {$where}
         ORDER BY id DESC
         LIMIT :limit"
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $result = [];
    foreach ($rows as $row) {
        $summaryDecoded = json_decode((string) ($row['summary_json'] ?? '{}'), true);
        $summary = is_array($summaryDecoded) ? $summaryDecoded : [];
        $result[] = [
            'id' => (int) ($row['id'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'graph_id' => (int) ($row['graph_id'] ?? 0),
            'provider' => (string) ($row['provider'] ?? ''),
            'target' => (string) ($row['target'] ?? ''),
            'target_type' => (string) ($row['target_type'] ?? 'unknown'),
            'status' => (int) ($row['status'] ?? 0),
            'ok' => !empty($row['ok']),
            'error' => (string) ($row['error'] ?? ''),
            'summary' => $summary,
            'response_json' => clickfix_intel_pretty_json((string) ($row['response_json'] ?? ''), 120000),
        ];
    }
    return $result;
}

function clickfix_pipeline_refang_text(string $text): string
{
    if ($text === '') {
        return '';
    }
    $refanged = preg_replace_callback('/hxxps?/i', static function (array $match): string {
        $value = strtolower((string) ($match[0] ?? 'hxxp'));
        return $value === 'hxxps' ? 'https' : 'http';
    }, $text);
    if (!is_string($refanged)) {
        $refanged = $text;
    }
    return str_ireplace(
        ['[.]', '(.)', '[dot]', '[@]', '[:]', '[://]', '[slash]'],
        ['.', '.', '.', '@', ':', '://', '/'],
        $refanged
    );
}

function clickfix_pipeline_artifact_hash_kind(string $value): string
{
    $trimmed = strtolower(trim($value));
    if ($trimmed !== '' && preg_match('/^[a-f0-9]{64}$/', $trimmed)) {
        return 'sha256';
    }
    if ($trimmed !== '' && preg_match('/^[a-f0-9]{40}$/', $trimmed)) {
        return 'sha1';
    }
    if ($trimmed !== '' && preg_match('/^[a-f0-9]{32}$/', $trimmed)) {
        return 'md5';
    }
    return '';
}

function clickfix_pipeline_normalize_artifact_value(string $kind, string $value): string
{
    $kind = strtolower(trim($kind));
    $value = trim(clickfix_pipeline_refang_text($value));
    if ($value === '') {
        return '';
    }
    if ($kind === 'url') {
        if (!preg_match('/^https?:\/\//i', $value)) {
            return '';
        }
        return substr($value, 0, 2000);
    }
    if ($kind === 'domain') {
        return clickfix_normalize_domain($value);
    }
    if ($kind === 'ip') {
        return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : '';
    }
    if (in_array($kind, ['md5', 'sha1', 'sha256'], true)) {
        return clickfix_pipeline_artifact_hash_kind($value) === $kind ? strtolower($value) : '';
    }
    if ($kind === 'command' || $kind === 'text') {
        $normalized = preg_replace('/\s+/', ' ', $value);
        return is_string($normalized) ? substr(trim($normalized), 0, 4000) : substr($value, 0, 4000);
    }
    if ($kind === 'file') {
        return substr($value, 0, 500);
    }
    if ($kind === 'email') {
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? strtolower($value) : '';
    }
    if ($kind === 'cve') {
        return preg_match('/^CVE-\d{4}-\d{4,}$/i', $value) ? strtoupper($value) : '';
    }
    return substr($value, 0, 1000);
}

function clickfix_pipeline_extract_artifacts_from_text(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }
    $clean = clickfix_pipeline_refang_text($text);
    $artifacts = [];
    $seen = [];
    $push = static function (string $type, string $value) use (&$artifacts, &$seen): void {
        $normalized = clickfix_pipeline_normalize_artifact_value($type, $value);
        if ($normalized === '') {
            return;
        }
        $key = strtolower($type) . '|' . strtolower($normalized);
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $artifacts[] = ['type' => strtolower($type), 'value' => $normalized];
    };

    if (preg_match_all('#https?://[^\s<>"\'`]+#i', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push('url', rtrim((string) $match, ".,;:!?)]]}>'\""));
        }
    }
    if (preg_match_all('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push('ip', (string) $match);
        }
    }
    if (preg_match_all('/(?<!@)\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\b/i', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push('domain', (string) $match);
        }
    }
    if (preg_match_all('/\b[a-f0-9]{64}\b/i', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push('sha256', (string) $match);
        }
    }
    if (preg_match_all('/\b[a-f0-9]{40}\b/i', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push('sha1', (string) $match);
        }
    }
    if (preg_match_all('/\b[a-f0-9]{32}\b/i', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push('md5', (string) $match);
        }
    }
    if (preg_match_all('/\b[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,63}\b/i', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push('email', (string) $match);
        }
    }
    if (preg_match_all('/\bCVE-\d{4}-\d{4,}\b/i', $clean, $matches)) {
        foreach ((array) ($matches[0] ?? []) as $match) {
            $push('cve', (string) $match);
        }
    }

    return $artifacts;
}

function clickfix_pipeline_extract_command_candidates(string $text, int $limit = 16): array
{
    $text = trim(clickfix_pipeline_refang_text($text));
    if ($text === '') {
        return [];
    }
    $limit = max(1, min(64, $limit));
    $patterns = [
        '/\b(?:powershell|pwsh|cmd(?:\.exe)?|mshta(?:\.exe)?|rundll32|regsvr32|cscript|wscript|curl|wget|certutil|bitsadmin|msiexec|wmic|conhost(?:\.exe)?|net\s+use)\b/i',
        '/\b(?:invoke-webrequest|invoke-restmethod|downloadstring|downloadfile|frombase64string|start-bitstransfer|iex|iwr|irm)\b/i',
    ];
    $lines = preg_split('/[\r\n]+/', $text) ?: [$text];
    $seen = [];
    $commands = [];
    foreach ($lines as $line) {
        $candidate = trim((string) $line);
        if ($candidate === '' || strlen($candidate) < 4) {
            continue;
        }
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $candidate)) {
                $normalized = clickfix_pipeline_normalize_artifact_value('command', $candidate);
                if ($normalized !== '' && !isset($seen[strtolower($normalized)])) {
                    $seen[strtolower($normalized)] = true;
                    $commands[] = $normalized;
                }
                break;
            }
        }
        if (count($commands) >= $limit) {
            break;
        }
    }
    return $commands;
}

function clickfix_pipeline_artifact_label(string $kind, string $value, array $metadata = []): string
{
    $kind = strtolower(trim($kind));
    $value = trim($value);
    if ($kind === 'command') {
        return mb_substr($value, 0, 96);
    }
    if ($kind === 'url') {
        return mb_substr($value, 0, 120);
    }
    if ($kind === 'file') {
        $name = trim((string) ($metadata['file_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
    }
    if (in_array($kind, ['md5', 'sha1', 'sha256'], true)) {
        return strtoupper($kind) . ': ' . substr($value, 0, 18) . (strlen($value) > 18 ? '...' : '');
    }
    return mb_substr($value, 0, 120);
}

function clickfix_investigation_analysis_job_create(
    PDO $pdo,
    int $graphId,
    int $reportId,
    int $userId,
    string $rootText,
    int $requestedDepth = 4,
    string $mode = 'alert_correlation'
): ?int {
    if ($graphId <= 0 || $userId <= 0 || !clickfix_has_table($pdo, 'investigation_analysis_jobs')) {
        return null;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO investigation_analysis_jobs (
            created_at, updated_at, graph_id, report_id, user_id, status, mode, root_text, requested_depth, started_at, finished_at, processed_artifacts, last_error
         ) VALUES (
            :created_at, :updated_at, :graph_id, :report_id, :user_id, :status, :mode, :root_text, :requested_depth, NULL, NULL, 0, ""
         )'
    );
    $now = gmdate('c');
    $ok = $stmt->execute([
        ':created_at' => $now,
        ':updated_at' => $now,
        ':graph_id' => $graphId,
        ':report_id' => max(0, $reportId),
        ':user_id' => $userId,
        ':status' => 'queued',
        ':mode' => substr(trim($mode), 0, 40),
        ':root_text' => substr($rootText, 0, 50000),
        ':requested_depth' => max(1, min(8, $requestedDepth)),
    ]);
    return $ok ? (int) $pdo->lastInsertId() : null;
}

function clickfix_investigation_analysis_job_set_state(
    PDO $pdo,
    int $jobId,
    string $status,
    int $processedArtifacts = 0,
    string $lastError = ''
): bool {
    if ($jobId <= 0 || !clickfix_has_table($pdo, 'investigation_analysis_jobs')) {
        return false;
    }
    $status = strtolower(trim($status));
    if (!in_array($status, ['queued', 'running', 'completed', 'failed'], true)) {
        $status = 'queued';
    }
    $params = [
        ':id' => $jobId,
        ':updated_at' => gmdate('c'),
        ':status' => $status,
        ':processed_artifacts' => max(0, $processedArtifacts),
        ':last_error' => substr(trim($lastError), 0, 2000),
    ];
    $sql = 'UPDATE investigation_analysis_jobs
            SET updated_at = :updated_at,
                status = :status,
                processed_artifacts = :processed_artifacts,
                last_error = :last_error';
    if ($status === 'running') {
        $sql .= ', started_at = COALESCE(started_at, :started_at)';
        $params[':started_at'] = gmdate('c');
    }
    if (in_array($status, ['completed', 'failed'], true)) {
        $sql .= ', finished_at = :finished_at';
        $params[':finished_at'] = gmdate('c');
    }
    $sql .= ' WHERE id = :id';
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

function clickfix_investigation_analysis_job_by_id(PDO $pdo, int $jobId): ?array
{
    if ($jobId <= 0 || !clickfix_has_table($pdo, 'investigation_analysis_jobs')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM investigation_analysis_jobs WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $jobId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function clickfix_investigation_analysis_jobs_by_graph(PDO $pdo, int $graphId, int $limit = 20): array
{
    if ($graphId <= 0 || !clickfix_has_table($pdo, 'investigation_analysis_jobs')) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM investigation_analysis_jobs WHERE graph_id = :graph_id ORDER BY id DESC LIMIT :limit');
    $stmt->bindValue(':graph_id', $graphId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function clickfix_investigation_artifact_by_id(PDO $pdo, int $artifactId): ?array
{
    if ($artifactId <= 0 || !clickfix_has_table($pdo, 'investigation_artifacts')) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM investigation_artifacts WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $artifactId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function clickfix_investigation_artifact_upsert(PDO $pdo, array $payload): ?array
{
    if (!clickfix_has_table($pdo, 'investigation_artifacts')) {
        return null;
    }
    $graphId = max(0, (int) ($payload['graph_id'] ?? 0));
    $jobId = max(0, (int) ($payload['job_id'] ?? 0));
    $parentArtifactId = max(0, (int) ($payload['parent_artifact_id'] ?? 0));
    $userId = max(0, (int) ($payload['user_id'] ?? 0));
    $artifactKind = strtolower(trim((string) ($payload['artifact_kind'] ?? '')));
    $role = substr(trim((string) ($payload['role'] ?? 'derived')), 0, 60);
    $rawValue = (string) ($payload['artifact_value'] ?? '');
    $normalizedValue = clickfix_pipeline_normalize_artifact_value($artifactKind, (string) ($payload['normalized_value'] ?? $rawValue));
    if ($graphId <= 0 || $jobId <= 0 || $userId <= 0 || $artifactKind === '' || $normalizedValue === '') {
        return null;
    }
    $depth = max(0, min(12, (int) ($payload['depth'] ?? 0)));
    $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
    $tags = is_array($payload['tags'] ?? null) ? array_values(array_unique(array_map('strval', $payload['tags']))) : [];
    if (!empty($tags)) {
        $metadata['tags'] = $tags;
    }
    $label = substr(trim((string) ($payload['label'] ?? clickfix_pipeline_artifact_label($artifactKind, $normalizedValue, $metadata))), 0, 180);
    if ($label === '') {
        $label = clickfix_pipeline_artifact_label($artifactKind, $normalizedValue, $metadata);
    }

    $existingStmt = $pdo->prepare(
        'SELECT * FROM investigation_artifacts
         WHERE graph_id = :graph_id
           AND parent_artifact_id = :parent_artifact_id
           AND artifact_kind = :artifact_kind
           AND normalized_value = :normalized_value
           AND depth = :depth
         LIMIT 1'
    );
    $existingStmt->execute([
        ':graph_id' => $graphId,
        ':parent_artifact_id' => $parentArtifactId,
        ':artifact_kind' => $artifactKind,
        ':normalized_value' => $normalizedValue,
        ':depth' => $depth,
    ]);
    $existing = $existingStmt->fetch() ?: null;
    $metadataJson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($metadataJson)) {
        $metadataJson = '{}';
    }

    if ($existing) {
        $update = $pdo->prepare(
            'UPDATE investigation_artifacts
             SET updated_at = :updated_at,
                 label = :label,
                 role = :role,
                 artifact_value = :artifact_value,
                 source_url = COALESCE(NULLIF(:source_url, ""), source_url),
                 file_name = COALESCE(NULLIF(:file_name, ""), file_name),
                 metadata_json = CASE WHEN COALESCE(metadata_json, "") = "" THEN :metadata_json ELSE metadata_json END
             WHERE id = :id'
        );
        $update->execute([
            ':updated_at' => gmdate('c'),
            ':label' => $label,
            ':role' => $role !== '' ? $role : (string) ($existing['role'] ?? 'derived'),
            ':artifact_value' => substr($rawValue !== '' ? $rawValue : $normalizedValue, 0, 4000),
            ':source_url' => substr(trim((string) ($payload['source_url'] ?? '')), 0, 2000),
            ':file_name' => substr(trim((string) ($payload['file_name'] ?? '')), 0, 255),
            ':metadata_json' => $metadataJson,
            ':id' => (int) ($existing['id'] ?? 0),
        ]);
        return clickfix_investigation_artifact_by_id($pdo, (int) ($existing['id'] ?? 0));
    }

    $stmt = $pdo->prepare(
        'INSERT INTO investigation_artifacts (
            created_at, updated_at, graph_id, job_id, parent_artifact_id, user_id, artifact_kind, role, label, artifact_value, normalized_value, source_url, file_name, depth, fetch_status, analysis_status, vt_summary_json, threatrip_summary_json, tags_json, metadata_json
         ) VALUES (
            :created_at, :updated_at, :graph_id, :job_id, :parent_artifact_id, :user_id, :artifact_kind, :role, :label, :artifact_value, :normalized_value, :source_url, :file_name, :depth, :fetch_status, :analysis_status, :vt_summary_json, :threatrip_summary_json, :tags_json, :metadata_json
         )'
    );
    $now = gmdate('c');
    $ok = $stmt->execute([
        ':created_at' => $now,
        ':updated_at' => $now,
        ':graph_id' => $graphId,
        ':job_id' => $jobId,
        ':parent_artifact_id' => $parentArtifactId,
        ':user_id' => $userId,
        ':artifact_kind' => $artifactKind,
        ':role' => $role !== '' ? $role : 'derived',
        ':label' => $label,
        ':artifact_value' => substr($rawValue !== '' ? $rawValue : $normalizedValue, 0, 4000),
        ':normalized_value' => substr($normalizedValue, 0, 4000),
        ':source_url' => substr(trim((string) ($payload['source_url'] ?? '')), 0, 2000),
        ':file_name' => substr(trim((string) ($payload['file_name'] ?? '')), 0, 255),
        ':depth' => $depth,
        ':fetch_status' => substr(trim((string) ($payload['fetch_status'] ?? 'pending')), 0, 40),
        ':analysis_status' => substr(trim((string) ($payload['analysis_status'] ?? 'queued')), 0, 40),
        ':vt_summary_json' => '{}',
        ':threatrip_summary_json' => '{}',
        ':tags_json' => json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
        ':metadata_json' => $metadataJson,
    ]);
    if (!$ok) {
        return null;
    }
    return clickfix_investigation_artifact_by_id($pdo, (int) $pdo->lastInsertId());
}

function clickfix_investigation_artifacts_by_job(PDO $pdo, int $jobId): array
{
    if ($jobId <= 0 || !clickfix_has_table($pdo, 'investigation_artifacts')) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM investigation_artifacts WHERE job_id = :job_id ORDER BY depth ASC, id ASC');
    $stmt->execute([':job_id' => $jobId]);
    return $stmt->fetchAll() ?: [];
}

function clickfix_investigation_artifacts_by_graph(PDO $pdo, int $graphId): array
{
    if ($graphId <= 0 || !clickfix_has_table($pdo, 'investigation_artifacts')) {
        return [];
    }
    $stmt = $pdo->prepare('SELECT * FROM investigation_artifacts WHERE graph_id = :graph_id ORDER BY depth ASC, id ASC');
    $stmt->execute([':graph_id' => $graphId]);
    return $stmt->fetchAll() ?: [];
}

function clickfix_investigation_artifact_update(PDO $pdo, int $artifactId, array $fields): bool
{
    if ($artifactId <= 0 || empty($fields) || !clickfix_has_table($pdo, 'investigation_artifacts')) {
        return false;
    }
    $allowed = ['label', 'role', 'artifact_value', 'source_url', 'file_name', 'fetch_status', 'analysis_status', 'vt_summary_json', 'threatrip_summary_json', 'tags_json', 'metadata_json'];
    $set = ['updated_at = :updated_at'];
    $params = [':updated_at' => gmdate('c'), ':id' => $artifactId];
    foreach ($allowed as $field) {
        if (!array_key_exists($field, $fields)) {
            continue;
        }
        $set[] = $field . ' = :' . $field;
        $value = $fields[$field];
        if (in_array($field, ['vt_summary_json', 'threatrip_summary_json', 'tags_json', 'metadata_json'], true) && is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $value = is_string($encoded) ? $encoded : '{}';
        }
        $params[':' . $field] = is_string($value) ? $value : (string) $value;
    }
    if (count($set) <= 1) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE investigation_artifacts SET ' . implode(', ', $set) . ' WHERE id = :id');
    return $stmt->execute($params);
}

function clickfix_investigation_pipeline_data_dir(): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, __DIR__ . '/../data/investigation_artifacts');
}

function clickfix_investigation_pipeline_store_bytes(int $graphId, string $fileName, string $bytes): array
{
    $baseDir = clickfix_investigation_pipeline_data_dir();
    $graphDir = $baseDir . DIRECTORY_SEPARATOR . 'graph_' . max(0, $graphId);
    if (!is_dir($graphDir)) {
        @mkdir($graphDir, 0775, true);
    }
    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', trim($fileName)) ?: 'payload.bin';
    $ext = pathinfo($safeName, PATHINFO_EXTENSION);
    $prefix = gmdate('Ymd_His') . '_' . substr(sha1($bytes), 0, 10);
    $finalName = $prefix . ($ext !== '' ? ('.' . strtolower($ext)) : '');
    $absolute = $graphDir . DIRECTORY_SEPARATOR . $finalName;
    $ok = @file_put_contents($absolute, $bytes) !== false;
    if (!$ok) {
        return ['ok' => false];
    }
    return [
        'ok' => true,
        'absolute_path' => $absolute,
        'relative_path' => 'data/investigation_artifacts/graph_' . max(0, $graphId) . '/' . $finalName,
        'file_name' => $safeName,
        'size' => strlen($bytes),
        'md5' => md5($bytes),
        'sha1' => sha1($bytes),
        'sha256' => hash('sha256', $bytes),
    ];
}

function clickfix_pipeline_bytes_are_text(string $bytes): bool
{
    if ($bytes === '') {
        return false;
    }
    $sample = substr($bytes, 0, 60000);
    if ($sample === '' || strpos($sample, "\0") !== false) {
        return false;
    }
    $printable = preg_match_all('/[\x09\x0A\x0D\x20-\x7E]/', $sample, $matches);
    if (!is_int($printable) || $printable <= 0) {
        return false;
    }
    return ($printable / max(1, strlen($sample))) >= 0.78;
}

function clickfix_pipeline_suggest_file_name(string $url, array $headers = []): string
{
    foreach ($headers as $headerLine) {
        $line = (string) $headerLine;
        if (preg_match('/content-disposition:\s*attachment;\s*filename=\"?([^\";]+)\"?/i', $line, $m)) {
            return substr(trim((string) ($m[1] ?? 'payload.bin')), 0, 255);
        }
    }
    $path = (string) parse_url($url, PHP_URL_PATH);
    $base = trim((string) basename($path));
    if ($base !== '' && $base !== '/' && $base !== '.') {
        return substr($base, 0, 255);
    }
    return 'payload.bin';
}

function clickfix_pipeline_select_api_key(PDO $pdo, int $userId, string $provider): string
{
    $provider = clickfix_normalize_user_api_provider($provider);
    if ($provider === '') {
        return '';
    }
    $userKey = clickfix_user_api_key_value($pdo, $userId, $provider);
    if ($userKey !== '') {
        return $userKey;
    }
    return clickfix_provider_service_api_key($provider);
}

function clickfix_pipeline_threatrip_headers(string $apiKey): array
{
    $headerName = trim((string) clickfix_env('CLICKFIX_PROVIDER_THREATRIP_AUTH_HEADER', 'Authorization'));
    $headerPrefix = (string) clickfix_env('CLICKFIX_PROVIDER_THREATRIP_AUTH_PREFIX', '');
    return [
        $headerName => $headerPrefix . trim($apiKey),
        'Accept' => 'application/json',
        'User-Agent' => 'ClickFixMitigator/1.0',
    ];
}

function clickfix_pipeline_threatrip_base_url(): string
{
    $configured = trim((string) clickfix_env('CLICKFIX_PROVIDER_THREATRIP_BASE_URL', ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    return 'https://www.threat.rip';
}

function clickfix_pipeline_threatrip_get(string $path, string $apiKey): array
{
    $url = clickfix_pipeline_threatrip_base_url() . $path;
    $http = clickfix_http_request($url, 'GET', clickfix_pipeline_threatrip_headers($apiKey), null, 20);
    $decoded = null;
    if (($http['body'] ?? '') !== '') {
        $tmp = json_decode((string) $http['body'], true);
        if (is_array($tmp)) {
            $decoded = $tmp;
        }
    }
    return [
        'ok' => (int) ($http['status'] ?? 0) >= 200 && (int) ($http['status'] ?? 0) < 300,
        'status' => (int) ($http['status'] ?? 0),
        'response' => $decoded,
        'error' => ((int) ($http['status'] ?? 0) >= 200 && (int) ($http['status'] ?? 0) < 300) ? '' : ('HTTP ' . (int) ($http['status'] ?? 0)),
    ];
}

function clickfix_pipeline_threatrip_upload_file(string $filePath, string $apiKey, string $password = ''): array
{
    if ($filePath === '' || !is_file($filePath)) {
        return ['ok' => false, 'status' => 0, 'response' => null, 'error' => 'File not found.'];
    }
    $fields = [];
    if ($password !== '') {
        $fields['password'] = $password;
    }
    $http = clickfix_http_multipart_request(
        clickfix_pipeline_threatrip_base_url() . '/api/upload/file',
        clickfix_pipeline_threatrip_headers($apiKey),
        $fields,
        [
            'file' => [
                'path' => $filePath,
                'filename' => basename($filePath),
                'mime' => 'application/octet-stream',
            ],
        ],
        60
    );
    $decoded = null;
    if (($http['body'] ?? '') !== '') {
        $tmp = json_decode((string) $http['body'], true);
        if (is_array($tmp)) {
            $decoded = $tmp;
        }
    }
    return [
        'ok' => !empty($http['ok']),
        'status' => (int) ($http['status'] ?? 0),
        'response' => $decoded,
        'error' => !empty($http['ok']) ? '' : (string) ($http['error'] ?? ('HTTP ' . (int) ($http['status'] ?? 0))),
    ];
}

function clickfix_pipeline_threatrip_lookup_by_sha256(string $sha256, string $apiKey, string $filePath = ''): array
{
    $sha256 = strtolower(trim($sha256));
    if (!preg_match('/^[a-f0-9]{64}$/', $sha256)) {
        return [
            'ok' => false,
            'provider' => 'threatrip',
            'target' => $sha256,
            'status' => 0,
            'summary' => [],
            'response' => null,
            'error' => 'Invalid SHA256.',
        ];
    }
    if (trim($apiKey) === '') {
        return [
            'ok' => false,
            'provider' => 'threatrip',
            'target' => $sha256,
            'status' => 0,
            'summary' => ['state' => 'not_configured'],
            'response' => null,
            'error' => 'Threatrip API not configured.',
        ];
    }

    $existsResult = clickfix_pipeline_threatrip_get('/api/reports/file/' . rawurlencode($sha256) . '/exists', $apiKey);
    $exists = !empty($existsResult['ok']);
    $uploaded = false;
    $uploadResult = null;
    if (!$exists && $filePath !== '' && is_file($filePath)) {
        $uploadResult = clickfix_pipeline_threatrip_upload_file($filePath, $apiKey);
        $uploaded = !empty($uploadResult['ok']);
        $existsResult = clickfix_pipeline_threatrip_get('/api/reports/file/' . rawurlencode($sha256) . '/exists', $apiKey);
        $exists = !empty($existsResult['ok']);
    }

    $reportResult = $exists ? clickfix_pipeline_threatrip_get('/api/reports/file/' . rawurlencode($sha256), $apiKey) : ['ok' => false, 'status' => 0, 'response' => null, 'error' => 'not_found'];
    $metadataResult = $exists ? clickfix_pipeline_threatrip_get('/api/reports/file/' . rawurlencode($sha256) . '/metadata', $apiKey) : ['ok' => false, 'status' => 0, 'response' => null, 'error' => 'not_found'];
    $classificationResult = $exists ? clickfix_pipeline_threatrip_get('/api/reports/file/' . rawurlencode($sha256) . '/classification', $apiKey) : ['ok' => false, 'status' => 0, 'response' => null, 'error' => 'not_found'];

    $summary = [
        'sha256' => $sha256,
        'exists' => $exists,
        'uploaded' => $uploaded,
        'classification' => is_array($classificationResult['response'] ?? null) ? $classificationResult['response'] : [],
        'metadata' => is_array($metadataResult['response'] ?? null) ? $metadataResult['response'] : [],
    ];
    $response = [
        'exists' => $existsResult,
        'upload' => $uploadResult,
        'report' => $reportResult,
        'metadata' => $metadataResult,
        'classification' => $classificationResult,
    ];
    return [
        'ok' => $exists,
        'provider' => 'threatrip',
        'target' => $sha256,
        'status' => $exists ? (int) ($reportResult['status'] ?? 200) : (int) ($existsResult['status'] ?? 0),
        'summary' => $summary,
        'response' => $response,
        'error' => $exists ? '' : (string) ($uploadResult['error'] ?? $existsResult['error'] ?? 'not_found'),
    ];
}

function clickfix_pipeline_merge_tags(array ...$tagSets): array
{
    $merged = [];
    foreach ($tagSets as $tagSet) {
        foreach ($tagSet as $tag) {
            $clean = substr(trim((string) $tag), 0, 40);
            if ($clean !== '' && !in_array($clean, $merged, true)) {
                $merged[] = $clean;
            }
        }
    }
    sort($merged);
    return $merged;
}

function clickfix_pipeline_infer_tags(string $text, string $fileName = '', array $lookupSummaries = []): array
{
    $sample = strtolower(clickfix_pipeline_refang_text($text . "\n" . $fileName));
    $tags = [];
    $contains = static function (array $needles) use ($sample): bool {
        foreach ($needles as $needle) {
            if ($needle !== '' && strpos($sample, strtolower($needle)) !== false) {
                return true;
            }
        }
        return false;
    };

    if ($contains(['mshta', 'win+r', 'win + r', 'win+x', 'win + x', 'captcha', 'clipboard'])) {
        $tags[] = 'clickfix';
    }
    if ($contains(['invoke-webrequest', 'downloadstring', 'downloadfile', 'curl ', 'wget ', 'bitsadmin', 'certutil', 'http://', 'https://'])) {
        $tags[] = 'downloader';
    }
    if ($contains(['iex', 'frombase64string', '-enc', 'encodedcommand', 'powershell'])) {
        $tags[] = 'loader';
    }
    if ($contains(['lumma', 'stealer', 'cookie', 'wallet', 'credential', 'browser data'])) {
        $tags[] = 'infostealer';
    }
    if ($contains(['ransom', 'encrypt', 'locker', '.onion', 'note.txt'])) {
        $tags[] = 'ransomware';
    }
    if ($contains(['rat', 'backdoor', 'remote access', 'trojan'])) {
        $tags[] = 'trojan';
    }

    foreach ($lookupSummaries as $provider => $summary) {
        if (!is_array($summary)) {
            continue;
        }
        if (strtolower((string) $provider) === 'virustotal') {
            $malicious = (int) ($summary['malicious'] ?? 0);
            $suspicious = (int) ($summary['suspicious'] ?? 0);
            if ($malicious > 0) {
                $tags[] = 'malicious';
            } elseif ($suspicious > 0) {
                $tags[] = 'suspicious';
            }
        }
    }

    return clickfix_pipeline_merge_tags($tags);
}

function clickfix_investigation_enqueue_alert_correlation(
    PDO $pdo,
    int $graphId,
    int $reportId,
    int $userId,
    int $requestedDepth = 4
): ?int {
    if ($graphId <= 0 || $reportId <= 0 || $userId <= 0) {
        return null;
    }
    $report = clickfix_report_by_id($pdo, $reportId);
    if (!$report) {
        return null;
    }
    $rootTextParts = [];
    foreach (['url', 'message', 'detected_content', 'full_context'] as $field) {
        $value = trim((string) ($report[$field] ?? ''));
        if ($value !== '') {
            $rootTextParts[] = strtoupper($field) . ': ' . $value;
        }
    }
    $rootText = implode(PHP_EOL . PHP_EOL, $rootTextParts);
    $jobId = clickfix_investigation_analysis_job_create($pdo, $graphId, $reportId, $userId, $rootText, $requestedDepth, 'alert_correlation');
    if ($jobId === null) {
        return null;
    }

    $rootUrl = trim((string) ($report['url'] ?? ''));
    $rootDomain = clickfix_normalize_domain((string) ($report['hostname'] ?? ''));
    if ($rootUrl !== '') {
        clickfix_investigation_artifact_upsert($pdo, [
            'graph_id' => $graphId,
            'job_id' => $jobId,
            'parent_artifact_id' => 0,
            'user_id' => $userId,
            'artifact_kind' => 'url',
            'role' => 'alert_url',
            'artifact_value' => $rootUrl,
            'label' => $rootUrl,
            'source_url' => $rootUrl,
            'depth' => 0,
            'fetch_status' => 'pending',
            'analysis_status' => 'queued',
        ]);
    }
    if ($rootDomain !== '') {
        clickfix_investigation_artifact_upsert($pdo, [
            'graph_id' => $graphId,
            'job_id' => $jobId,
            'parent_artifact_id' => 0,
            'user_id' => $userId,
            'artifact_kind' => 'domain',
            'role' => 'alert_domain',
            'artifact_value' => $rootDomain,
            'label' => $rootDomain,
            'depth' => 0,
            'fetch_status' => 'not_applicable',
            'analysis_status' => 'queued',
        ]);
    }
    foreach (clickfix_pipeline_extract_command_candidates($rootText, 12) as $command) {
        clickfix_investigation_artifact_upsert($pdo, [
            'graph_id' => $graphId,
            'job_id' => $jobId,
            'parent_artifact_id' => 0,
            'user_id' => $userId,
            'artifact_kind' => 'command',
            'role' => 'execution_chain',
            'artifact_value' => $command,
            'label' => clickfix_pipeline_artifact_label('command', $command),
            'depth' => 0,
            'fetch_status' => 'not_applicable',
            'analysis_status' => 'queued',
        ]);
    }
    foreach (clickfix_pipeline_extract_artifacts_from_text($rootText) as $artifact) {
        $kind = (string) ($artifact['type'] ?? '');
        clickfix_investigation_artifact_upsert($pdo, [
            'graph_id' => $graphId,
            'job_id' => $jobId,
            'parent_artifact_id' => 0,
            'user_id' => $userId,
            'artifact_kind' => $kind,
            'role' => 'extracted',
            'artifact_value' => (string) ($artifact['value'] ?? ''),
            'label' => clickfix_pipeline_artifact_label($kind, (string) ($artifact['value'] ?? '')),
            'depth' => 0,
            'fetch_status' => $kind === 'url' ? 'pending' : 'not_applicable',
            'analysis_status' => 'queued',
        ]);
    }
    clickfix_investigation_log_event($pdo, $graphId, $userId, 'correlation_queued', [
        'job_id' => $jobId,
        'report_id' => $reportId,
        'requested_depth' => max(1, min(8, $requestedDepth)),
    ]);
    return $jobId;
}

function clickfix_investigation_rebuild_pipeline_graph(PDO $pdo, int $graphId, int $actorId = 0): bool
{
    $investigation = clickfix_get_investigation($pdo, $graphId, $actorId, true);
    if (!$investigation) {
        return false;
    }
    $graph = clickfix_normalize_graph_payload(is_array($investigation['graph'] ?? null) ? $investigation['graph'] : ['nodes' => [], 'edges' => []]);
    $artifacts = clickfix_investigation_artifacts_by_graph($pdo, $graphId);
    $nodes = [];
    foreach (($graph['nodes'] ?? []) as $node) {
        if (!is_array($node) || clickfix_str_starts_with((string) ($node['id'] ?? ''), 'pipe_artifact_')) {
            continue;
        }
        $nodes[] = $node;
    }
    $edges = [];
    foreach (($graph['edges'] ?? []) as $edge) {
        if (!is_array($edge) || clickfix_str_starts_with((string) ($edge['id'] ?? ''), 'pipe_edge_')) {
            continue;
        }
        $edges[] = $edge;
    }

    $alertNodeId = '';
    foreach ($nodes as $node) {
        $nodeId = (string) ($node['id'] ?? '');
        $tags = is_array($node['tags'] ?? null) ? $node['tags'] : [];
        if ($nodeId !== '' && (clickfix_str_starts_with($nodeId, 'n_alert_') || in_array('alert', $tags, true))) {
            $alertNodeId = $nodeId;
            break;
        }
    }

    $depthSlots = [];
    $allTags = is_array($investigation['tags'] ?? null) ? $investigation['tags'] : [];
    foreach ($artifacts as $artifact) {
        $artifactId = (int) ($artifact['id'] ?? 0);
        if ($artifactId <= 0) {
            continue;
        }
        $depth = max(0, (int) ($artifact['depth'] ?? 0));
        $depthSlots[$depth] = ($depthSlots[$depth] ?? 0) + 1;
        $slot = $depthSlots[$depth];
        $kind = strtolower((string) ($artifact['artifact_kind'] ?? 'text'));
        $value = (string) ($artifact['normalized_value'] ?? $artifact['artifact_value'] ?? '');
        $tagDecoded = json_decode((string) ($artifact['tags_json'] ?? '[]'), true);
        $tags = is_array($tagDecoded) ? array_values(array_map('strval', $tagDecoded)) : [];
        $allTags = clickfix_pipeline_merge_tags($allTags, $tags, [$kind]);
        $notes = [];
        if ($value !== '') {
            $notes[] = 'Value: ' . $value;
        }
        $sourceUrl = trim((string) ($artifact['source_url'] ?? ''));
        if ($sourceUrl !== '') {
            $notes[] = 'Source URL: ' . $sourceUrl;
        }
        $fileName = trim((string) ($artifact['file_name'] ?? ''));
        if ($fileName !== '') {
            $notes[] = 'File: ' . $fileName;
        }
        $fetchStatus = trim((string) ($artifact['fetch_status'] ?? ''));
        $analysisStatus = trim((string) ($artifact['analysis_status'] ?? ''));
        if ($fetchStatus !== '') {
            $notes[] = 'Fetch: ' . $fetchStatus;
        }
        if ($analysisStatus !== '') {
            $notes[] = 'Analysis: ' . $analysisStatus;
        }
        $vtSummary = json_decode((string) ($artifact['vt_summary_json'] ?? '{}'), true);
        if (is_array($vtSummary) && !empty($vtSummary)) {
            $notes[] = 'VT: ' . clickfix_intel_pretty_json($vtSummary, 4000);
        }
        $threatripSummary = json_decode((string) ($artifact['threatrip_summary_json'] ?? '{}'), true);
        if (is_array($threatripSummary) && !empty($threatripSummary)) {
            $notes[] = 'Threatrip: ' . clickfix_intel_pretty_json($threatripSummary, 4000);
        }
        $nodeColor = '#5dc8ff';
        if ($kind === 'command') {
            $nodeColor = '#ffd166';
        } elseif ($kind === 'file') {
            $nodeColor = '#8ecae6';
        } elseif (in_array($kind, ['md5', 'sha1', 'sha256'], true)) {
            $nodeColor = '#f4a261';
        } elseif ($kind === 'domain') {
            $nodeColor = '#58d68d';
        } elseif ($kind === 'ip') {
            $nodeColor = '#ff8fab';
        }
        $nodes[] = [
            'id' => 'pipe_artifact_' . $artifactId,
            'label' => clickfix_pipeline_artifact_label($kind, (string) ($artifact['label'] ?? $value), ['file_name' => $fileName]),
            'color' => $nodeColor,
            'x' => 220 + ($depth * 240),
            'y' => 160 + (($slot - 1) * 120),
            'tags' => clickfix_pipeline_merge_tags([$kind], $tags),
            'notes' => implode(PHP_EOL, $notes),
        ];

        $parentArtifactId = (int) ($artifact['parent_artifact_id'] ?? 0);
        $fromId = $parentArtifactId > 0 ? 'pipe_artifact_' . $parentArtifactId : $alertNodeId;
        if ($fromId !== '') {
            $edges[] = [
                'id' => 'pipe_edge_' . $artifactId,
                'from' => $fromId,
                'to' => 'pipe_artifact_' . $artifactId,
                'label' => (string) ($artifact['role'] ?? 'derived'),
                'color' => '#4ad7d1',
            ];
        }
    }

    $mergedGraph = ['nodes' => $nodes, 'edges' => $edges];
    $newSummary = trim((string) ($investigation['summary'] ?? ''));
    $pipelineStats = 'Correlation pipeline: ' . count($artifacts) . ' artifacts';
    if ($newSummary === '' || strpos($newSummary, 'Correlation pipeline:') === false) {
        $newSummary = trim($newSummary . PHP_EOL . PHP_EOL . $pipelineStats);
    } else {
        $newSummary = preg_replace('/Correlation pipeline:.*$/m', $pipelineStats, $newSummary) ?: $newSummary;
    }
    return clickfix_investigation_save(
        $pdo,
        max(0, $actorId),
        $graphId,
        (string) ($investigation['title'] ?? 'Investigacion'),
        (string) ($investigation['site_domain'] ?? ''),
        (string) ($investigation['verdict'] ?? 'investigating'),
        $newSummary,
        implode(', ', clickfix_pipeline_merge_tags($allTags)),
        $mergedGraph,
        true,
        (int) ($investigation['source_report_id'] ?? 0)
    ) !== null;
}

function clickfix_investigation_run_correlation_job(PDO $pdo, int $jobId, array $options = []): array
{
    $job = clickfix_investigation_analysis_job_by_id($pdo, $jobId);
    if (!$job) {
        return ['ok' => false, 'error' => 'Job not found.'];
    }
    $graphId = (int) ($job['graph_id'] ?? 0);
    $userId = (int) ($job['user_id'] ?? 0);
    $requestedDepth = max(1, min(8, (int) ($job['requested_depth'] ?? 4)));
    $fetchEnabled = !empty($options['enable_fetch']) || clickfix_env_truthy('CLICKFIX_ENABLE_CORRELATION_FETCH', false);
    clickfix_investigation_analysis_job_set_state($pdo, $jobId, 'running', (int) ($job['processed_artifacts'] ?? 0), '');

    $processed = 0;
    $seenArtifactIds = [];
    while (true) {
        $artifacts = clickfix_investigation_artifacts_by_job($pdo, $jobId);
        $processedThisRound = 0;
        foreach ($artifacts as $artifact) {
            $artifactId = (int) ($artifact['id'] ?? 0);
            if ($artifactId <= 0 || isset($seenArtifactIds[$artifactId])) {
                continue;
            }
            $seenArtifactIds[$artifactId] = true;
        $depth = max(0, (int) ($artifact['depth'] ?? 0));
        $kind = strtolower((string) ($artifact['artifact_kind'] ?? ''));
        $normalizedValue = trim((string) ($artifact['normalized_value'] ?? $artifact['artifact_value'] ?? ''));
        $currentMetadata = json_decode((string) ($artifact['metadata_json'] ?? '{}'), true);
        $metadata = is_array($currentMetadata) ? $currentMetadata : [];
        $vtSummary = [];
        $threatripSummary = [];
        $tags = json_decode((string) ($artifact['tags_json'] ?? '[]'), true);
        $tags = is_array($tags) ? array_values(array_map('strval', $tags)) : [];

        if (in_array($kind, ['url', 'domain', 'ip', 'md5', 'sha1', 'sha256'], true) && $normalizedValue !== '') {
            $vtKey = clickfix_pipeline_select_api_key($pdo, $userId, 'virustotal');
            if ($vtKey !== '') {
                $lookup = clickfix_user_api_lookup('virustotal', $vtKey, $normalizedValue);
                $vtSummary = is_array($lookup['summary'] ?? null) ? $lookup['summary'] : [];
                clickfix_investigation_api_lookup_store($pdo, $userId, $graphId, $lookup, clickfix_intel_pretty_json($lookup['response'] ?? null, 120000));
            }

            if (in_array($kind, ['ip', 'domain'], true)) {
                $abuseKey = clickfix_pipeline_select_api_key($pdo, $userId, 'abuseipdb');
                if ($abuseKey !== '') {
                    $lookup = clickfix_user_api_lookup('abuseipdb', $abuseKey, $normalizedValue);
                    $metadata['abuseipdb_summary'] = is_array($lookup['summary'] ?? null) ? $lookup['summary'] : [];
                    clickfix_investigation_api_lookup_store($pdo, $userId, $graphId, $lookup, clickfix_intel_pretty_json($lookup['response'] ?? null, 120000));
                }
            }

            if (in_array($kind, ['url', 'domain', 'ip'], true)) {
                $urlscanKey = clickfix_pipeline_select_api_key($pdo, $userId, 'urlscan');
                if ($urlscanKey !== '') {
                    $lookup = clickfix_user_api_lookup('urlscan', $urlscanKey, $normalizedValue);
                    $metadata['urlscan_summary'] = is_array($lookup['summary'] ?? null) ? $lookup['summary'] : [];
                    clickfix_investigation_api_lookup_store($pdo, $userId, $graphId, $lookup, clickfix_intel_pretty_json($lookup['response'] ?? null, 120000));
                }
            }

        }

        $threatripKey = clickfix_pipeline_select_api_key($pdo, $userId, 'threatrip');
        $threatripSha256 = '';
        $threatripFilePath = '';
        if ($kind === 'sha256') {
            $threatripSha256 = $normalizedValue;
        } elseif ($kind === 'file') {
            if (preg_match('/^[a-f0-9]{64}$/', $normalizedValue)) {
                $threatripSha256 = strtolower($normalizedValue);
            } elseif (preg_match('/^[a-f0-9]{64}$/', (string) ($metadata['sha256'] ?? ''))) {
                $threatripSha256 = strtolower((string) $metadata['sha256']);
            } elseif (preg_match('/^[a-f0-9]{64}$/', (string) ($metadata['download']['sha256'] ?? ''))) {
                $threatripSha256 = strtolower((string) $metadata['download']['sha256']);
            }
            $candidatePath = (string) ($metadata['download']['absolute_path'] ?? '');
            if ($candidatePath === '') {
                $candidatePath = (string) ($artifact['artifact_value'] ?? '');
                if ($candidatePath !== '' && !preg_match('/^[A-Za-z]:\\\\|^\//', $candidatePath)) {
                    $candidatePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($candidatePath, '/\\'));
                }
            }
            if ($candidatePath !== '' && is_file($candidatePath)) {
                $threatripFilePath = $candidatePath;
            }
        }
        if ($threatripKey !== '' && $threatripSha256 !== '') {
            $threatripLookup = clickfix_pipeline_threatrip_lookup_by_sha256($threatripSha256, $threatripKey, $threatripFilePath);
            if (!empty($threatripLookup['ok']) || !empty($threatripLookup['response'])) {
                $threatripSummary = is_array($threatripLookup['summary'] ?? null) ? $threatripLookup['summary'] : [];
                clickfix_investigation_api_lookup_store($pdo, $userId, $graphId, $threatripLookup, clickfix_intel_pretty_json($threatripLookup['response'] ?? null, 120000));
            } else {
                $metadata['threatrip_state'] = (string) ($threatripLookup['error'] ?? 'not_configured');
            }
        }

        $artifactText = $normalizedValue;
        if ($kind === 'command') {
            $tags = clickfix_pipeline_merge_tags($tags, clickfix_pipeline_infer_tags($artifactText, (string) ($artifact['file_name'] ?? ''), [
                'virustotal' => $vtSummary,
                'threatrip' => $threatripSummary,
            ]));
        }

        $fetchStatus = (string) ($artifact['fetch_status'] ?? 'not_applicable');
        if ($fetchEnabled && $kind === 'url' && $depth < $requestedDepth) {
            $http = clickfix_http_request($normalizedValue, 'GET', [
                'User-Agent' => 'ClickFixMitigator/1.0',
                'Accept' => '*/*',
            ], null, 15);
            if ((int) ($http['status'] ?? 0) >= 200 && (int) ($http['status'] ?? 0) < 300 && (string) ($http['body'] ?? '') !== '') {
                $fileName = clickfix_pipeline_suggest_file_name($normalizedValue, is_array($http['headers'] ?? null) ? $http['headers'] : []);
                $stored = clickfix_investigation_pipeline_store_bytes($graphId, $fileName, (string) $http['body']);
                if (!empty($stored['ok'])) {
                    $fetchStatus = 'fetched';
                    $metadata['download'] = $stored;
                    $childFile = clickfix_investigation_artifact_upsert($pdo, [
                        'graph_id' => $graphId,
                        'job_id' => $jobId,
                        'parent_artifact_id' => $artifactId,
                        'user_id' => $userId,
                        'artifact_kind' => 'file',
                        'role' => 'downloaded_payload',
                        'artifact_value' => (string) ($stored['relative_path'] ?? ''),
                        'normalized_value' => (string) ($stored['sha256'] ?? $stored['relative_path'] ?? ''),
                        'file_name' => (string) ($stored['file_name'] ?? ''),
                        'label' => (string) ($stored['file_name'] ?? 'payload.bin'),
                        'source_url' => $normalizedValue,
                        'depth' => $depth + 1,
                        'fetch_status' => 'stored',
                        'analysis_status' => 'queued',
                        'metadata' => $stored,
                    ]);
                    foreach (['md5', 'sha1', 'sha256'] as $hashKind) {
                        if (!empty($stored[$hashKind])) {
                            clickfix_investigation_artifact_upsert($pdo, [
                                'graph_id' => $graphId,
                                'job_id' => $jobId,
                                'parent_artifact_id' => (int) ($childFile['id'] ?? $artifactId),
                                'user_id' => $userId,
                                'artifact_kind' => $hashKind,
                                'role' => 'file_hash',
                                'artifact_value' => (string) $stored[$hashKind],
                                'label' => clickfix_pipeline_artifact_label($hashKind, (string) $stored[$hashKind]),
                                'depth' => $depth + 1,
                                'fetch_status' => 'not_applicable',
                                'analysis_status' => 'queued',
                            ]);
                        }
                    }
                    $body = (string) ($http['body'] ?? '');
                    if (clickfix_pipeline_bytes_are_text($body)) {
                        $tags = clickfix_pipeline_merge_tags($tags, clickfix_pipeline_infer_tags($body, (string) ($stored['file_name'] ?? ''), [
                            'virustotal' => $vtSummary,
                            'threatrip' => $threatripSummary,
                        ]));
                        foreach (clickfix_pipeline_extract_command_candidates($body, 10) as $command) {
                            clickfix_investigation_artifact_upsert($pdo, [
                                'graph_id' => $graphId,
                                'job_id' => $jobId,
                                'parent_artifact_id' => (int) ($childFile['id'] ?? $artifactId),
                                'user_id' => $userId,
                                'artifact_kind' => 'command',
                                'role' => 'downloaded_command',
                                'artifact_value' => $command,
                                'label' => clickfix_pipeline_artifact_label('command', $command),
                                'depth' => $depth + 1,
                                'fetch_status' => 'not_applicable',
                                'analysis_status' => 'queued',
                            ]);
                        }
                        foreach (clickfix_pipeline_extract_artifacts_from_text($body) as $childArtifact) {
                            $childKind = (string) ($childArtifact['type'] ?? '');
                            clickfix_investigation_artifact_upsert($pdo, [
                                'graph_id' => $graphId,
                                'job_id' => $jobId,
                                'parent_artifact_id' => (int) ($childFile['id'] ?? $artifactId),
                                'user_id' => $userId,
                                'artifact_kind' => $childKind,
                                'role' => 'downloaded_reference',
                                'artifact_value' => (string) ($childArtifact['value'] ?? ''),
                                'label' => clickfix_pipeline_artifact_label($childKind, (string) ($childArtifact['value'] ?? '')),
                                'source_url' => $normalizedValue,
                                'depth' => $depth + 1,
                                'fetch_status' => $childKind === 'url' ? 'pending' : 'not_applicable',
                                'analysis_status' => 'queued',
                            ]);
                        }
                    }
                } else {
                    $fetchStatus = 'failed';
                }
            } else {
                $fetchStatus = 'failed';
                $metadata['fetch_error'] = 'HTTP ' . (int) ($http['status'] ?? 0);
            }
        } elseif ($kind === 'url' && !$fetchEnabled) {
            $fetchStatus = 'disabled';
        }

        if (!empty($vtSummary)) {
            $tags = clickfix_pipeline_merge_tags($tags, clickfix_pipeline_infer_tags($artifactText, (string) ($artifact['file_name'] ?? ''), ['virustotal' => $vtSummary]));
        }
        clickfix_investigation_artifact_update($pdo, $artifactId, [
            'fetch_status' => $fetchStatus,
            'analysis_status' => 'done',
            'vt_summary_json' => $vtSummary,
            'threatrip_summary_json' => $threatripSummary,
            'tags_json' => $tags,
            'metadata_json' => $metadata,
        ]);
        $processed++;
            $processedThisRound++;
        }
        if ($processedThisRound <= 0) {
            break;
        }
    }

    clickfix_investigation_rebuild_pipeline_graph($pdo, $graphId, $userId);
    clickfix_investigation_analysis_job_set_state($pdo, $jobId, 'completed', $processed, '');
    clickfix_investigation_log_event($pdo, $graphId, $userId, 'correlation_completed', [
        'job_id' => $jobId,
        'processed_artifacts' => $processed,
        'fetch_enabled' => $fetchEnabled,
    ]);
    return ['ok' => true, 'processed_artifacts' => $processed, 'job_id' => $jobId];
}

function clickfix_vt_reported_webs_stats(PDO $pdo, int $topDomains = 25): array
{
    $topDomains = max(5, min(80, $topDomains));
    $cacheKey = clickfix_cache_key('vt_reported_webs_stats', ['top' => $topDomains, 'v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $result = [
        'generated_at' => gmdate('c'),
        'reported_domains_total' => 0,
        'domains_with_vt' => 0,
        'domains_without_vt' => 0,
        'detected_any' => 0,
        'detected_malicious' => 0,
        'detected_suspicious_only' => 0,
        'harmless_or_undetected' => 0,
        'engine_totals' => [
            'malicious' => 0,
            'suspicious' => 0,
            'harmless' => 0,
            'undetected' => 0,
        ],
        'class_labels' => ['malicious', 'suspicious', 'harmless_or_undetected', 'no_vt'],
        'class_values' => [0, 0, 0, 0],
        'engine_labels' => ['malicious', 'suspicious', 'harmless', 'undetected'],
        'engine_values' => [0, 0, 0, 0],
        'top_domains' => [],
    ];

    if (!clickfix_has_table($pdo, 'reports') || !clickfix_has_table($pdo, 'investigation_api_lookup_history')) {
        return $result;
    }

    $reportedRows = $pdo->query(
        "SELECT LOWER(TRIM(hostname)) AS hostname
         FROM reports
         WHERE hostname IS NOT NULL AND TRIM(hostname) != ''
         GROUP BY LOWER(TRIM(hostname))"
    )->fetchAll();
    $reportedSet = [];
    foreach ($reportedRows as $row) {
        $host = clickfix_normalize_domain((string) ($row['hostname'] ?? ''));
        if ($host !== '') {
            $reportedSet[$host] = true;
        }
    }
    $result['reported_domains_total'] = count($reportedSet);
    if (empty($reportedSet)) {
        clickfix_cache_set($cacheKey, $result, 45);
        return $result;
    }

    $vtRows = $pdo->query(
        "SELECT id, target, target_type, summary_json, status, ok, created_at
         FROM investigation_api_lookup_history
         WHERE provider = 'virustotal'
           AND summary_json IS NOT NULL
           AND TRIM(summary_json) != ''
         ORDER BY id DESC
         LIMIT 60000"
    )->fetchAll();

    $latestByDomain = [];
    foreach ($vtRows as $row) {
        $targetType = strtolower(trim((string) ($row['target_type'] ?? 'unknown')));
        if (!in_array($targetType, ['domain', 'url'], true)) {
            continue;
        }
        $target = trim((string) ($row['target'] ?? ''));
        if ($target === '') {
            continue;
        }
        $domain = '';
        if ($targetType === 'domain') {
            $domain = clickfix_normalize_domain($target);
        } else {
            $domain = clickfix_normalize_domain((string) parse_url($target, PHP_URL_HOST));
        }
        if ($domain === '' || !isset($reportedSet[$domain]) || isset($latestByDomain[$domain])) {
            continue;
        }
        $summaryRaw = json_decode((string) ($row['summary_json'] ?? '{}'), true);
        $summary = is_array($summaryRaw) ? $summaryRaw : [];
        $latestByDomain[$domain] = [
            'domain' => $domain,
            'status' => (int) ($row['status'] ?? 0),
            'ok' => !empty($row['ok']),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'malicious' => (int) ($summary['malicious'] ?? 0),
            'suspicious' => (int) ($summary['suspicious'] ?? 0),
            'harmless' => (int) ($summary['harmless'] ?? 0),
            'undetected' => (int) ($summary['undetected'] ?? 0),
        ];
    }

    $result['domains_with_vt'] = count($latestByDomain);
    $result['domains_without_vt'] = max(0, $result['reported_domains_total'] - $result['domains_with_vt']);

    $topRows = [];
    foreach ($latestByDomain as $domain => $row) {
        $mal = (int) ($row['malicious'] ?? 0);
        $sus = (int) ($row['suspicious'] ?? 0);
        $har = (int) ($row['harmless'] ?? 0);
        $und = (int) ($row['undetected'] ?? 0);
        $total = $mal + $sus + $har + $und;

        $verdict = 'harmless_or_undetected';
        if ($mal > 0) {
            $verdict = 'malicious';
            $result['detected_malicious']++;
            $result['detected_any']++;
        } elseif ($sus > 0) {
            $verdict = 'suspicious';
            $result['detected_suspicious_only']++;
            $result['detected_any']++;
        } else {
            $result['harmless_or_undetected']++;
        }

        $result['engine_totals']['malicious'] += $mal;
        $result['engine_totals']['suspicious'] += $sus;
        $result['engine_totals']['harmless'] += $har;
        $result['engine_totals']['undetected'] += $und;

        $topRows[] = [
            'domain' => $domain,
            'verdict' => $verdict,
            'malicious' => $mal,
            'suspicious' => $sus,
            'harmless' => $har,
            'undetected' => $und,
            'total' => $total,
            'last_lookup_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    usort($topRows, static function (array $a, array $b): int {
        $scoreA = ((int) ($a['malicious'] ?? 0) * 1000)
            + ((int) ($a['suspicious'] ?? 0) * 100)
            + ((int) ($a['total'] ?? 0));
        $scoreB = ((int) ($b['malicious'] ?? 0) * 1000)
            + ((int) ($b['suspicious'] ?? 0) * 100)
            + ((int) ($b['total'] ?? 0));
        return $scoreB <=> $scoreA;
    });

    $result['class_values'] = [
        (int) $result['detected_malicious'],
        (int) $result['detected_suspicious_only'],
        (int) $result['harmless_or_undetected'],
        (int) $result['domains_without_vt'],
    ];
    $result['engine_values'] = [
        (int) ($result['engine_totals']['malicious'] ?? 0),
        (int) ($result['engine_totals']['suspicious'] ?? 0),
        (int) ($result['engine_totals']['harmless'] ?? 0),
        (int) ($result['engine_totals']['undetected'] ?? 0),
    ];
    $result['top_domains'] = array_slice($topRows, 0, $topDomains);

    clickfix_cache_set($cacheKey, $result, 45);
    return $result;
}

function clickfix_user_profile(PDO $pdo, int $targetUserId, ?int $viewerUserId = null, bool $viewerIsAdmin = false): ?array
{
    if ($targetUserId <= 0) {
        return null;
    }
    $hasEmail = clickfix_has_column($pdo, 'users', 'email');
    $hasFullName = clickfix_has_column($pdo, 'users', 'full_name');
    $hasPreferredLang = clickfix_has_column($pdo, 'users', 'preferred_lang');
    $hasReputation = clickfix_has_column($pdo, 'users', 'reputation');
    $hasProfileEmailPublic = clickfix_has_column($pdo, 'users', 'profile_email_public');
    $hasProfileVTPublic = clickfix_has_column($pdo, 'users', 'profile_vt_public');
    $hasProfileVTHandle = clickfix_has_column($pdo, 'users', 'profile_vt_handle');
    $hasProfileThreatripPublic = clickfix_has_column($pdo, 'users', 'profile_threatrip_public');
    $hasProfileThreatripId = clickfix_has_column($pdo, 'users', 'profile_threatrip_id');
    $hasProfileAbusePublic = clickfix_has_column($pdo, 'users', 'profile_abuseipdb_public');
    $hasProfileAbuseId = clickfix_has_column($pdo, 'users', 'profile_abuseipdb_id');
    $hasProfileGitHubPublic = clickfix_has_column($pdo, 'users', 'profile_github_public');
    $hasProfileGitHubHandle = clickfix_has_column($pdo, 'users', 'profile_github_handle');
    $hasProfileTheme = clickfix_has_column($pdo, 'users', 'profile_theme');
    $hasProfileAvatarUrl = clickfix_has_column($pdo, 'users', 'profile_avatar_url');

    $sql = 'SELECT id, created_at, username, role, verified, ';
    $sql .= $hasEmail ? 'email' : "'' AS email";
    $sql .= ', ';
    $sql .= $hasFullName ? 'full_name' : "'' AS full_name";
    $sql .= ', ';
    $sql .= $hasPreferredLang ? 'preferred_lang' : "'en' AS preferred_lang";
    $sql .= ', ';
    $sql .= $hasReputation ? 'reputation' : '0 AS reputation';
    $sql .= ', ';
    $sql .= $hasProfileEmailPublic ? 'profile_email_public' : '0 AS profile_email_public';
    $sql .= ', ';
    $sql .= $hasProfileVTPublic ? 'profile_vt_public' : '0 AS profile_vt_public';
    $sql .= ', ';
    $sql .= $hasProfileVTHandle ? 'profile_vt_handle' : "'' AS profile_vt_handle";
    $sql .= ', ';
    $sql .= $hasProfileThreatripPublic ? 'profile_threatrip_public' : '0 AS profile_threatrip_public';
    $sql .= ', ';
    $sql .= $hasProfileThreatripId ? 'profile_threatrip_id' : "'' AS profile_threatrip_id";
    $sql .= ', ';
    $sql .= $hasProfileAbusePublic ? 'profile_abuseipdb_public' : '0 AS profile_abuseipdb_public';
    $sql .= ', ';
    $sql .= $hasProfileAbuseId ? 'profile_abuseipdb_id' : "'' AS profile_abuseipdb_id";
    $sql .= ', ';
    $sql .= $hasProfileGitHubPublic ? 'profile_github_public' : '0 AS profile_github_public';
    $sql .= ', ';
    $sql .= $hasProfileGitHubHandle ? 'profile_github_handle' : "'' AS profile_github_handle";
    $sql .= ', ';
    $sql .= $hasProfileTheme ? 'profile_theme' : "'default' AS profile_theme";
    $sql .= ', ';
    $sql .= $hasProfileAvatarUrl ? 'profile_avatar_url' : "'' AS profile_avatar_url";
    $sql .= ' FROM users WHERE id = :id LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $targetUserId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $role = clickfix_normalize_role((string) ($row['role'] ?? 'analyst_jr'));
    $isOwner = $viewerUserId !== null && $viewerUserId > 0 && $viewerUserId === (int) ($row['id'] ?? 0);
    $canViewPrivate = $viewerIsAdmin || $isOwner;

    $email = strtolower(trim((string) ($row['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = '';
    }
    $emailPublic = !empty($row['profile_email_public']) || $canViewPrivate;
    $visibleEmail = $emailPublic ? $email : '';

    $vtHandle = clickfix_profile_normalize_vt_handle((string) ($row['profile_vt_handle'] ?? ''));
    $threatripId = clickfix_profile_normalize_threatrip_id((string) ($row['profile_threatrip_id'] ?? ''));
    $abuseId = clickfix_profile_normalize_abuseipdb_id((string) ($row['profile_abuseipdb_id'] ?? ''));
    $githubHandle = clickfix_profile_normalize_github_handle((string) ($row['profile_github_handle'] ?? ''));

    $vtVisible = $vtHandle !== '' && ($canViewPrivate || !empty($row['profile_vt_public']));
    $threatripVisible = $threatripId !== '' && ($canViewPrivate || !empty($row['profile_threatrip_public']));
    $abuseVisible = $abuseId !== '' && ($canViewPrivate || !empty($row['profile_abuseipdb_public']));
    $githubVisible = $githubHandle !== '' && ($canViewPrivate || !empty($row['profile_github_public']));

    $fullName = clickfix_profile_normalize_name((string) ($row['full_name'] ?? ''));
    $displayName = $fullName !== '' ? $fullName : (string) ($row['username'] ?? '');

    return [
        'id' => (int) ($row['id'] ?? 0),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'username' => (string) ($row['username'] ?? ''),
        'full_name' => $fullName,
        'display_name' => $displayName,
        'email' => $email,
        'email_visible' => $visibleEmail,
        'email_is_public' => !empty($row['profile_email_public']),
        'can_view_private' => $canViewPrivate,
        'is_owner' => $isOwner,
        'role' => $role,
        'role_label' => clickfix_role_label($role),
        'verified' => (int) ($row['verified'] ?? 0),
        'preferred_lang' => clickfix_normalize_user_language((string) ($row['preferred_lang'] ?? 'en')),
        'reputation' => (int) ($row['reputation'] ?? 0),
        'profile_theme' => clickfix_profile_normalize_theme((string) ($row['profile_theme'] ?? 'default')),
        'profile_avatar_url' => clickfix_profile_normalize_avatar_url((string) ($row['profile_avatar_url'] ?? '')),
        'account_threatrip' => [
            'id' => $threatripId,
            'is_public' => !empty($row['profile_threatrip_public']),
            'visible' => $threatripVisible,
            'url' => $threatripVisible ? ('https://www.threat.rip/user/' . rawurlencode($threatripId)) : '',
        ],
        'account_virustotal' => [
            'handle' => $vtHandle,
            'is_public' => !empty($row['profile_vt_public']),
            'visible' => $vtVisible,
            'url' => $vtVisible ? ('https://www.virustotal.com/gui/user/' . rawurlencode($vtHandle)) : '',
        ],
        'account_abuseipdb' => [
            'id' => $abuseId,
            'is_public' => !empty($row['profile_abuseipdb_public']),
            'visible' => $abuseVisible,
            'url' => $abuseVisible ? ('https://www.abuseipdb.com/user/' . rawurlencode($abuseId)) : '',
        ],
        'account_github' => [
            'handle' => $githubHandle,
            'is_public' => !empty($row['profile_github_public']),
            'visible' => $githubVisible,
            'url' => $githubVisible ? ('https://github.com/' . rawurlencode($githubHandle)) : '',
        ],
    ];
}

function clickfix_user_update_public_profile(PDO $pdo, int $userId, array $payload): bool
{
    if ($userId <= 0) {
        return false;
    }
    $columns = [
        'full_name' => clickfix_has_column($pdo, 'users', 'full_name'),
        'profile_email_public' => clickfix_has_column($pdo, 'users', 'profile_email_public'),
        'profile_vt_public' => clickfix_has_column($pdo, 'users', 'profile_vt_public'),
        'profile_vt_handle' => clickfix_has_column($pdo, 'users', 'profile_vt_handle'),
        'profile_threatrip_public' => clickfix_has_column($pdo, 'users', 'profile_threatrip_public'),
        'profile_threatrip_id' => clickfix_has_column($pdo, 'users', 'profile_threatrip_id'),
        'profile_abuseipdb_public' => clickfix_has_column($pdo, 'users', 'profile_abuseipdb_public'),
        'profile_abuseipdb_id' => clickfix_has_column($pdo, 'users', 'profile_abuseipdb_id'),
        'profile_github_public' => clickfix_has_column($pdo, 'users', 'profile_github_public'),
        'profile_github_handle' => clickfix_has_column($pdo, 'users', 'profile_github_handle'),
        'profile_theme' => clickfix_has_column($pdo, 'users', 'profile_theme'),
        'profile_avatar_url' => clickfix_has_column($pdo, 'users', 'profile_avatar_url'),
    ];
    $set = [];
    $params = [':id' => $userId];

    if ($columns['full_name']) {
        $set[] = 'full_name = :full_name';
        $params[':full_name'] = clickfix_profile_normalize_name((string) ($payload['full_name'] ?? ''));
    }
    if ($columns['profile_email_public']) {
        $set[] = 'profile_email_public = :profile_email_public';
        $params[':profile_email_public'] = clickfix_profile_normalize_flag($payload['profile_email_public'] ?? '0');
    }
    if ($columns['profile_vt_public']) {
        $set[] = 'profile_vt_public = :profile_vt_public';
        $params[':profile_vt_public'] = clickfix_profile_normalize_flag($payload['profile_vt_public'] ?? '0');
    }
    if ($columns['profile_vt_handle']) {
        $set[] = 'profile_vt_handle = :profile_vt_handle';
        $params[':profile_vt_handle'] = clickfix_profile_normalize_vt_handle((string) ($payload['profile_vt_handle'] ?? ''));
    }
    if ($columns['profile_threatrip_public']) {
        $set[] = 'profile_threatrip_public = :profile_threatrip_public';
        $params[':profile_threatrip_public'] = clickfix_profile_normalize_flag($payload['profile_threatrip_public'] ?? '0');
    }
    if ($columns['profile_threatrip_id']) {
        $set[] = 'profile_threatrip_id = :profile_threatrip_id';
        $params[':profile_threatrip_id'] = clickfix_profile_normalize_threatrip_id((string) ($payload['profile_threatrip_id'] ?? ''));
    }
    if ($columns['profile_abuseipdb_public']) {
        $set[] = 'profile_abuseipdb_public = :profile_abuseipdb_public';
        $params[':profile_abuseipdb_public'] = clickfix_profile_normalize_flag($payload['profile_abuseipdb_public'] ?? '0');
    }
    if ($columns['profile_abuseipdb_id']) {
        $set[] = 'profile_abuseipdb_id = :profile_abuseipdb_id';
        $params[':profile_abuseipdb_id'] = clickfix_profile_normalize_abuseipdb_id((string) ($payload['profile_abuseipdb_id'] ?? ''));
    }
    if ($columns['profile_github_public']) {
        $set[] = 'profile_github_public = :profile_github_public';
        $params[':profile_github_public'] = clickfix_profile_normalize_flag($payload['profile_github_public'] ?? '0');
    }
    if ($columns['profile_github_handle']) {
        $set[] = 'profile_github_handle = :profile_github_handle';
        $params[':profile_github_handle'] = clickfix_profile_normalize_github_handle((string) ($payload['profile_github_handle'] ?? ''));
    }
    if ($columns['profile_theme']) {
        $set[] = 'profile_theme = :profile_theme';
        $params[':profile_theme'] = clickfix_profile_normalize_theme((string) ($payload['profile_theme'] ?? 'default'));
    }
    if ($columns['profile_avatar_url']) {
        $set[] = 'profile_avatar_url = :profile_avatar_url';
        $params[':profile_avatar_url'] = clickfix_profile_normalize_avatar_url((string) ($payload['profile_avatar_url'] ?? ''));
    }

    if (empty($set)) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id');
    return $stmt->execute($params);
}

function clickfix_user_update_theme_avatar(PDO $pdo, int $userId, string $theme, string $avatarUrl): bool
{
    if ($userId <= 0) {
        return false;
    }
    $set = [];
    $params = [':id' => $userId];
    if (clickfix_has_column($pdo, 'users', 'profile_theme')) {
        $set[] = 'profile_theme = :profile_theme';
        $params[':profile_theme'] = clickfix_profile_normalize_theme($theme);
    }
    if (clickfix_has_column($pdo, 'users', 'profile_avatar_url')) {
        $set[] = 'profile_avatar_url = :profile_avatar_url';
        $params[':profile_avatar_url'] = clickfix_profile_normalize_avatar_url($avatarUrl);
    }
    if (empty($set)) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $set) . ' WHERE id = :id');
    return $stmt->execute($params);
}

function clickfix_user_profile_investigations(PDO $pdo, int $targetUserId, bool $includePrivate, int $limit = 120): array
{
    if ($targetUserId <= 0) {
        return [];
    }
    $sql = 'SELECT ig.*, u.username
            FROM investigation_graphs ig
            LEFT JOIN users u ON u.id = ig.user_id
            WHERE ig.deleted = 0 AND ig.user_id = :user_id';
    if (!$includePrivate) {
        $sql .= ' AND ig.is_public = 1';
    }
    $sql .= ' ORDER BY ig.updated_at DESC LIMIT :limit';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $targetUserId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', max(1, min($limit, 300)), PDO::PARAM_INT);
    $stmt->execute();
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = clickfix_investigation_decode_row($row);
    }
    return $rows;
}

function clickfix_user_profile_reports(PDO $pdo, int $targetUserId, int $limit = 120): array
{
    if ($targetUserId <= 0) {
        return [];
    }
    $stmt = $pdo->prepare(
        "SELECT id, received_at, hostname, url, message, review_status, reviewed_by, reviewed_at, accepted_by, accepted_at
         FROM reports
         WHERE reviewed_by = :user_id OR accepted_by = :user_id
         ORDER BY COALESCE(reviewed_at, accepted_at, received_at) DESC
         LIMIT :limit"
    );
    $stmt->bindValue(':user_id', $targetUserId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', max(1, min($limit, 300)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function clickfix_normalize_session_event_type(string $eventType): string
{
    $event = strtolower(trim($eventType));
    if ($event === 'login') {
        return 'login';
    }
    if ($event === 'logout') {
        return 'logout';
    }
    return '';
}

function clickfix_log_user_session_event(
    PDO $pdo,
    int $userId,
    string $username,
    string $eventType,
    string $ip = '',
    string $userAgent = '',
    string $sessionId = ''
): bool {
    if ($userId <= 0 || !clickfix_has_table($pdo, 'user_session_audit')) {
        return false;
    }
    $username = substr(trim($username), 0, 120);
    if ($username === '') {
        return false;
    }
    $event = clickfix_normalize_session_event_type($eventType);
    if ($event === '') {
        return false;
    }
    $ip = trim($ip);
    if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = '';
    }
    $userAgent = substr(trim($userAgent), 0, 450);
    $sessionId = substr(trim($sessionId), 0, 180);

    $stmt = $pdo->prepare(
        'INSERT INTO user_session_audit (created_at, user_id, username, event_type, ip, user_agent, session_id)
         VALUES (:created_at, :user_id, :username, :event_type, :ip, :user_agent, :session_id)'
    );
    $ok = $stmt->execute([
        ':created_at' => gmdate('c'),
        ':user_id' => $userId,
        ':username' => $username,
        ':event_type' => $event,
        ':ip' => $ip,
        ':user_agent' => $userAgent,
        ':session_id' => $sessionId,
    ]);
    if (!$ok) {
        return false;
    }

    // Keep recent audit entries bounded per user.
    try {
        $trimStmt = $pdo->prepare(
            'DELETE FROM user_session_audit
             WHERE user_id = :user_id
               AND id NOT IN (
                 SELECT id
                 FROM user_session_audit
                 WHERE user_id = :inner_user_id
                 ORDER BY id DESC
                 LIMIT 500
               )'
        );
        $trimStmt->execute([
            ':user_id' => $userId,
            ':inner_user_id' => $userId,
        ]);
    } catch (Throwable $exception) {
        // Keep the insert even if trimming fails.
    }
    return true;
}

function clickfix_user_session_history(PDO $pdo, int $userId, int $limit = 120): array
{
    if ($userId <= 0 || !clickfix_has_table($pdo, 'user_session_audit')) {
        return [];
    }
    $limit = max(1, min($limit, 400));
    $stmt = $pdo->prepare(
        'SELECT id, created_at, user_id, username, event_type, ip, user_agent, session_id
         FROM user_session_audit
         WHERE user_id = :user_id
         ORDER BY id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'id' => (int) ($row['id'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'username' => (string) ($row['username'] ?? ''),
            'event_type' => clickfix_normalize_session_event_type((string) ($row['event_type'] ?? '')) ?: 'unknown',
            'ip' => (string) ($row['ip'] ?? ''),
            'user_agent' => (string) ($row['user_agent'] ?? ''),
            'session_id' => (string) ($row['session_id'] ?? ''),
        ];
    }
    return $rows;
}

function clickfix_apply_user_reputation(
    PDO $pdo,
    int $userId,
    int $delta,
    string $reason,
    ?int $contextGraphId = null,
    ?int $createdBy = null
): bool {
    if ($userId <= 0 || $delta === 0 || !clickfix_has_column($pdo, 'users', 'reputation')) {
        return false;
    }
    $reason = substr(trim($reason), 0, 200);
    if ($reason === '') {
        $reason = 'update';
    }
    $now = gmdate('c');
    try {
        $update = $pdo->prepare('UPDATE users SET reputation = COALESCE(reputation, 0) + :delta WHERE id = :id');
        $ok = $update->execute([':delta' => $delta, ':id' => $userId]);
    } catch (Throwable $exception) {
        return false;
    }
    if (!$ok) {
        return false;
    }
    try {
        $insert = $pdo->prepare(
            'INSERT INTO user_reputation_events (created_at, user_id, delta, reason, context_graph_id, created_by)
             VALUES (:created_at, :user_id, :delta, :reason, :context_graph_id, :created_by)'
        );
        $insert->execute([
            ':created_at' => $now,
            ':user_id' => $userId,
            ':delta' => $delta,
            ':reason' => $reason,
            ':context_graph_id' => $contextGraphId,
            ':created_by' => $createdBy,
        ]);
    } catch (Throwable $exception) {
        // Keep reputation update even if event log table is unavailable.
    }
    return true;
}

function clickfix_flash(?string $set = null): ?string
{
    if ($set !== null) {
        $_SESSION['clickfix_flash'] = $set;
        return $set;
    }
    $value = isset($_SESSION['clickfix_flash']) ? (string) $_SESSION['clickfix_flash'] : null;
    unset($_SESSION['clickfix_flash']);
    return $value;
}

function clickfix_redirect(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

function clickfix_fix_locale_text(?string $value): string
{
    $text = (string) $value;
    if ($text === '') {
        return '';
    }

    $replace = [
        'Ã¡' => 'á',
        'Ã©' => 'é',
        'Ã­' => 'í',
        'Ã³' => 'ó',
        'Ãº' => 'ú',
        'Ã' => 'Á',
        'Ã‰' => 'É',
        'Ã' => 'Í',
        'Ã“' => 'Ó',
        'Ãš' => 'Ú',
        'Ã±' => 'ñ',
        'Ã‘' => 'Ñ',
        'Ã¼' => 'ü',
        'Ãœ' => 'Ü',
        'Â¿' => '¿',
        'Â¡' => '¡',
        'â' => "'",
        'â' => '"',
        'â' => '"',
        'â' => '-',
        'â' => '-',
        'aci?ón' => 'ación',
        'aci?nes' => 'aciones',
        'ici?ón' => 'ición',
        'ici?nes' => 'iciones',
        'uci?ón' => 'ución',
        'uci?nes' => 'uciones',
        'sesi?ón' => 'sesión',
        'revisi?ón' => 'revisión',
        'detecci?ón' => 'detección',
        'Investigaci?ón' => 'Investigación',
        'Investigaci?ones' => 'Investigaciones',
        'investigaci?ón' => 'investigación',
        'investigaci?ones' => 'investigaciones',
        '?ltima' => 'Última',
        '?ltimo' => 'Último',
        '?ltimos' => 'Últimos',
        '?ltim' => 'Últim',
        '?n' => 'ón',
        '?squeda' => 'úsqueda',
        'b?squeda' => 'búsqueda',
        'Pa?s' => 'País',
        'pa?s' => 'país',
        'pases' => 'países',
        'revisin' => 'revisión',
        'revisi?n' => 'revisión',
        'sesion' => 'sesión',
        'deteccion' => 'detección',
        'detecciones' => 'detecciones',
        'detecci?n' => 'detección',
        'investigacin' => 'investigación',
        'investigacines' => 'investigaciones',
        'investigaci?n' => 'investigación',
        'investigaci?nes' => 'investigaciones',
        'investigacin pública' => 'investigación pública',
        'programacion' => 'programación',
        'programaci?n' => 'programación',
        'Acci?n' => 'Acción',
        'acci?n' => 'acción',
        'operaci?nal' => 'operacional',
        'operaci?nes' => 'operaciones',
        'Contrase?a' => 'Contraseña',
        'contrase?a' => 'contraseña',
        'Autenticaci?n' => 'Autenticación',
        'autenticaci?n' => 'autenticación',
        'Configuraci?n' => 'Configuración',
        'configuraci?n' => 'configuración',
        'Informaci?n' => 'Información',
        'informaci?n' => 'información',
        'Clasificaci?n' => 'Clasificación',
        'clasificaci?n' => 'clasificación',
        'Se?ales' => 'Señales',
        'se?ales' => 'señales',
        'Gr?ficos' => 'Gráficos',
        'gr?ficos' => 'gráficos',
        'Gr?fico' => 'Gráfico',
        'gr?fico' => 'gráfico',
        'B?squeda' => 'Búsqueda',
        'b?squeda' => 'búsqueda',
        'Pa?ses' => 'Países',
        'pa?ses' => 'países',
        'm?s' => 'más',
        'M?s' => 'Más',
        'd?a' => 'día',
        'D?a' => 'Día',
        'd?as' => 'días',
        'D?as' => 'Días',
        'telemetr?a' => 'telemetría',
        'analitica' => 'analítica',
        'Analitica' => 'Analítica',
        'hist?rico' => 'histórico',
        'Hist?rico' => 'Histórico',
        'm?dulo' => 'módulo',
        'M?dulo' => 'Módulo',
        'versi?n' => 'versión',
        'Versi?n' => 'Versión',
        'p?blica' => 'pública',
        'P?blica' => 'Pública',
        'p?blico' => 'público',
        'P?blico' => 'Público',
        'r?pido' => 'rápido',
        'R?pido' => 'Rápido',
        'r?pida' => 'rápida',
        'R?pida' => 'Rápida',
        'analitica' => 'analítica',
        'hist?rico' => 'histórico',
        'an?lisis' => 'análisis',
        'Pblico' => 'Público',
        'publica' => 'pública',
        'publico' => 'público',
        'rapida' => 'rápida',
        'rapido' => 'rápido',
        'metricas' => 'métricas',
        'politica' => 'política',
        'correlacion' => 'correlación',
        'automatica' => 'automática',
        'valida' => 'válida',
        'solo' => 'solo',
    ];
    $text = strtr($text, $replace);

    return $text;
}

function clickfix_h(?string $value): string
{
    return htmlspecialchars(clickfix_fix_locale_text((string) $value), ENT_QUOTES, 'UTF-8');
}

function clickfix_normalize_hex_color(string $value, string $fallback = '#5dc8ff'): string
{
    $value = trim($value);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
        return strtolower($value);
    }
    return $fallback;
}

function clickfix_normalize_graph_payload(array $graph): array
{
    $nodes = [];
    $edges = [];
    $knownNodeIds = [];

    $rawNodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
    foreach ($rawNodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        $id = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) ($node['id'] ?? ''));
        if ($id === '') {
            $id = 'n_' . bin2hex(random_bytes(4));
        }
        if (isset($knownNodeIds[$id])) {
            continue;
        }
        $knownNodeIds[$id] = true;
        $label = trim((string) ($node['label'] ?? 'node'));
        if ($label === '') {
            $label = 'node';
        }
        $tags = [];
        $rawTags = $node['tags'] ?? [];
        if (is_string($rawTags)) {
            $rawTags = preg_split('/\s*,\s*/', $rawTags) ?: [];
        }
        if (is_array($rawTags)) {
            foreach ($rawTags as $tag) {
                $cleanTag = trim((string) $tag);
                if ($cleanTag !== '' && !in_array($cleanTag, $tags, true)) {
                    $tags[] = substr($cleanTag, 0, 40);
                }
            }
        }
        $nodes[] = [
            'id' => substr($id, 0, 64),
            'label' => substr($label, 0, 120),
            'color' => clickfix_normalize_hex_color((string) ($node['color'] ?? '#5dc8ff')),
            'x' => round((float) ($node['x'] ?? 120), 2),
            'y' => round((float) ($node['y'] ?? 120), 2),
            'tags' => $tags,
            'notes' => substr(trim((string) ($node['notes'] ?? '')), 0, 400),
        ];
    }

    $rawEdges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
    $knownEdgeIds = [];
    foreach ($rawEdges as $edge) {
        if (!is_array($edge)) {
            continue;
        }
        $from = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) ($edge['from'] ?? ''));
        $to = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) ($edge['to'] ?? ''));
        if ($from === '' || $to === '' || !isset($knownNodeIds[$from]) || !isset($knownNodeIds[$to])) {
            continue;
        }
        $id = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) ($edge['id'] ?? ''));
        if ($id === '') {
            $id = 'e_' . bin2hex(random_bytes(4));
        }
        if (isset($knownEdgeIds[$id])) {
            continue;
        }
        $knownEdgeIds[$id] = true;
        $edges[] = [
            'id' => substr($id, 0, 64),
            'from' => substr($from, 0, 64),
            'to' => substr($to, 0, 64),
            'label' => substr(trim((string) ($edge['label'] ?? '')), 0, 120),
            'color' => clickfix_normalize_hex_color((string) ($edge['color'] ?? '#94a3b8'), '#94a3b8'),
        ];
    }

    return ['nodes' => $nodes, 'edges' => $edges];
}

function clickfix_parse_tags(string $raw): array
{
    $parts = preg_split('/\s*,\s*/', trim($raw)) ?: [];
    $tags = [];
    foreach ($parts as $part) {
        $tag = trim((string) $part);
        if ($tag !== '' && !in_array($tag, $tags, true)) {
            $tags[] = substr($tag, 0, 40);
        }
    }
    return $tags;
}

function clickfix_generate_share_token(): string
{
    return bin2hex(random_bytes(16));
}

function clickfix_investigation_graph_metrics(array $graph): array
{
    $nodes = is_array($graph['nodes'] ?? null) ? $graph['nodes'] : [];
    $edges = is_array($graph['edges'] ?? null) ? $graph['edges'] : [];
    $withNotes = 0;
    $withTags = 0;
    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        $notes = trim((string) ($node['notes'] ?? ''));
        $tags = $node['tags'] ?? [];
        if ($notes !== '') {
            $withNotes++;
        }
        if (is_array($tags) && !empty($tags)) {
            $withTags++;
        }
    }
    return [
        'nodes' => count($nodes),
        'edges' => count($edges),
        'nodes_with_notes' => $withNotes,
        'nodes_with_tags' => $withTags,
    ];
}

function clickfix_investigation_node_signature(array $node): string
{
    $payload = [
        'label' => substr(trim((string) ($node['label'] ?? '')), 0, 120),
        'color' => clickfix_normalize_hex_color((string) ($node['color'] ?? '#5dc8ff')),
        'tags' => array_values(array_map('strval', is_array($node['tags'] ?? null) ? $node['tags'] : [])),
        'notes' => substr(trim((string) ($node['notes'] ?? '')), 0, 400),
    ];
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
}

function clickfix_investigation_edge_signature(array $edge): string
{
    $payload = [
        'from' => substr((string) ($edge['from'] ?? ''), 0, 64),
        'to' => substr((string) ($edge['to'] ?? ''), 0, 64),
        'label' => substr(trim((string) ($edge['label'] ?? '')), 0, 120),
        'color' => clickfix_normalize_hex_color((string) ($edge['color'] ?? '#94a3b8'), '#94a3b8'),
    ];
    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
}

function clickfix_investigation_graph_delta(?array $previousGraph, array $nextGraph): array
{
    $before = clickfix_normalize_graph_payload(is_array($previousGraph) ? $previousGraph : ['nodes' => [], 'edges' => []]);
    $after = clickfix_normalize_graph_payload($nextGraph);

    $beforeNodes = [];
    foreach (($before['nodes'] ?? []) as $node) {
        if (!is_array($node) || empty($node['id'])) {
            continue;
        }
        $beforeNodes[(string) $node['id']] = clickfix_investigation_node_signature($node);
    }
    $afterNodes = [];
    foreach (($after['nodes'] ?? []) as $node) {
        if (!is_array($node) || empty($node['id'])) {
            continue;
        }
        $afterNodes[(string) $node['id']] = clickfix_investigation_node_signature($node);
    }

    $beforeEdges = [];
    foreach (($before['edges'] ?? []) as $edge) {
        if (!is_array($edge) || empty($edge['id'])) {
            continue;
        }
        $beforeEdges[(string) $edge['id']] = clickfix_investigation_edge_signature($edge);
    }
    $afterEdges = [];
    foreach (($after['edges'] ?? []) as $edge) {
        if (!is_array($edge) || empty($edge['id'])) {
            continue;
        }
        $afterEdges[(string) $edge['id']] = clickfix_investigation_edge_signature($edge);
    }

    $addedNodes = array_values(array_diff(array_keys($afterNodes), array_keys($beforeNodes)));
    $removedNodes = array_values(array_diff(array_keys($beforeNodes), array_keys($afterNodes)));
    $addedEdges = array_values(array_diff(array_keys($afterEdges), array_keys($beforeEdges)));
    $removedEdges = array_values(array_diff(array_keys($beforeEdges), array_keys($afterEdges)));

    $updatedNodes = 0;
    foreach ($afterNodes as $nodeId => $signature) {
        if (isset($beforeNodes[$nodeId]) && $beforeNodes[$nodeId] !== $signature) {
            $updatedNodes++;
        }
    }
    $updatedEdges = 0;
    foreach ($afterEdges as $edgeId => $signature) {
        if (isset($beforeEdges[$edgeId]) && $beforeEdges[$edgeId] !== $signature) {
            $updatedEdges++;
        }
    }

    return [
        'node_added' => count($addedNodes),
        'node_removed' => count($removedNodes),
        'node_updated' => $updatedNodes,
        'edge_added' => count($addedEdges),
        'edge_removed' => count($removedEdges),
        'edge_updated' => $updatedEdges,
        'after_metrics' => clickfix_investigation_graph_metrics($after),
    ];
}

function clickfix_investigation_log_event(PDO $pdo, int $graphId, ?int $userId, string $action, array $details = []): bool
{
    if ($graphId <= 0) {
        return false;
    }
    $action = strtolower(trim($action));
    if ($action === '') {
        $action = 'update';
    }
    $payload = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($payload)) {
        $payload = '{}';
    }
    $stmt = $pdo->prepare(
        'INSERT INTO investigation_events (created_at, graph_id, user_id, action, details_json)
         VALUES (:created_at, :graph_id, :user_id, :action, :details_json)'
    );
    return $stmt->execute([
        ':created_at' => gmdate('c'),
        ':graph_id' => $graphId,
        ':user_id' => ($userId !== null && $userId > 0) ? $userId : null,
        ':action' => substr($action, 0, 50),
        ':details_json' => $payload,
    ]);
}

function clickfix_investigation_events(PDO $pdo, int $graphId, int $limit = 200): array
{
    if ($graphId <= 0) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT ie.*, u.username
         FROM investigation_events ie
         LEFT JOIN users u ON u.id = ie.user_id
         WHERE ie.graph_id = :graph_id
         ORDER BY ie.id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':graph_id', $graphId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', max(1, min(1000, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $decoded = json_decode((string) ($row['details_json'] ?? '{}'), true);
        $row['details'] = is_array($decoded) ? $decoded : [];
    }
    unset($row);
    return $rows;
}

function clickfix_investigation_save(
    PDO $pdo,
    int $userId,
    ?int $graphId,
    string $title,
    string $siteDomain,
    string $verdict,
    string $summary,
    string $tagsText,
    array $graph,
    bool $isAdmin = false,
    ?int $sourceReportId = null
): ?int {
    $title = substr(trim($title), 0, 180);
    if ($title === '') {
        return null;
    }
    $domain = clickfix_normalize_domain($siteDomain);
    $verdict = strtolower(trim($verdict));
    if (!in_array($verdict, ['malicious', 'suspicious', 'clean', 'unknown', 'investigating'], true)) {
        $verdict = 'suspicious';
    }
    $summary = substr(trim($summary), 0, 5000);
    $tags = clickfix_parse_tags($tagsText);
    $normalizedGraph = clickfix_normalize_graph_payload($graph);
    $sourceReportId = $sourceReportId !== null ? max(0, $sourceReportId) : null;
    $now = gmdate('c');

    if ($graphId !== null && $graphId > 0) {
        $previousStmt = $pdo->prepare('SELECT title, site_domain, verdict, graph_json FROM investigation_graphs WHERE id = :id LIMIT 1');
        $previousStmt->execute([':id' => $graphId]);
        $previousRow = $previousStmt->fetch() ?: null;
        $previousGraph = null;
        if (is_array($previousRow)) {
            $decodedPrev = json_decode((string) ($previousRow['graph_json'] ?? '{}'), true);
            $previousGraph = is_array($decodedPrev) ? $decodedPrev : ['nodes' => [], 'edges' => []];
        }
        if ($isAdmin) {
            $params = [
                ':updated_at' => $now,
                ':title' => $title,
                ':site_domain' => $domain,
                ':verdict' => $verdict,
                ':summary' => $summary,
                ':tags_json' => json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':graph_json' => json_encode($normalizedGraph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':id' => $graphId,
            ];
            $sql = 'UPDATE investigation_graphs SET updated_at = :updated_at, title = :title, site_domain = :site_domain, verdict = :verdict, summary = :summary, tags_json = :tags_json, graph_json = :graph_json';
            if ($sourceReportId !== null) {
                $sql .= ', source_report_id = :source_report_id';
                $params[':source_report_id'] = $sourceReportId;
            }
            $sql .= ' WHERE id = :id AND deleted = 0';
            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute($params);
            if ($ok) {
                $details = clickfix_investigation_graph_delta($previousGraph, $normalizedGraph);
                $details['verdict'] = $verdict;
                $details['title'] = $title;
                if ($sourceReportId !== null) {
                    $details['source_report_id'] = $sourceReportId;
                }
                if (is_array($previousRow)) {
                    $details['field_changes'] = [
                        'title_changed' => ((string) ($previousRow['title'] ?? '')) !== $title,
                        'domain_changed' => ((string) ($previousRow['site_domain'] ?? '')) !== $domain,
                        'verdict_changed' => ((string) ($previousRow['verdict'] ?? '')) !== $verdict,
                    ];
                }
                clickfix_investigation_log_event($pdo, $graphId, $userId, 'update', $details);
                return $graphId;
            }
            return null;
        }
        $params = [
            ':updated_at' => $now,
            ':title' => $title,
            ':site_domain' => $domain,
            ':verdict' => $verdict,
            ':summary' => $summary,
            ':tags_json' => json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':graph_json' => json_encode($normalizedGraph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':id' => $graphId,
            ':user_id' => $userId,
        ];
        $sql = 'UPDATE investigation_graphs SET updated_at = :updated_at, title = :title, site_domain = :site_domain, verdict = :verdict, summary = :summary, tags_json = :tags_json, graph_json = :graph_json';
        if ($sourceReportId !== null) {
            $sql .= ', source_report_id = :source_report_id';
            $params[':source_report_id'] = $sourceReportId;
        }
        $sql .= ' WHERE id = :id AND user_id = :user_id AND deleted = 0';
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute($params);
        if ($ok) {
            $details = clickfix_investigation_graph_delta($previousGraph, $normalizedGraph);
            $details['verdict'] = $verdict;
            $details['title'] = $title;
            if ($sourceReportId !== null) {
                $details['source_report_id'] = $sourceReportId;
            }
            if (is_array($previousRow)) {
                $details['field_changes'] = [
                    'title_changed' => ((string) ($previousRow['title'] ?? '')) !== $title,
                    'domain_changed' => ((string) ($previousRow['site_domain'] ?? '')) !== $domain,
                    'verdict_changed' => ((string) ($previousRow['verdict'] ?? '')) !== $verdict,
                ];
            }
            clickfix_investigation_log_event($pdo, $graphId, $userId, 'update', $details);
            return $graphId;
        }
        return null;
    }

    $stmt = $pdo->prepare('INSERT INTO investigation_graphs (created_at, updated_at, user_id, title, site_domain, verdict, summary, tags_json, graph_json, is_public, share_token, deleted, source_report_id, show_on_home, home_position) VALUES (:created_at, :updated_at, :user_id, :title, :site_domain, :verdict, :summary, :tags_json, :graph_json, 0, NULL, 0, :source_report_id, 0, 0)');
    $ok = $stmt->execute([
        ':created_at' => $now,
        ':updated_at' => $now,
        ':user_id' => $userId,
        ':title' => $title,
        ':site_domain' => $domain,
        ':verdict' => $verdict,
        ':summary' => $summary,
        ':tags_json' => json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':graph_json' => json_encode($normalizedGraph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':source_report_id' => $sourceReportId ?? 0,
    ]);
    if (!$ok) {
        return null;
    }
    $newId = (int) $pdo->lastInsertId();
    clickfix_investigation_log_event($pdo, $newId, $userId, 'create', [
        'title' => $title,
        'site_domain' => $domain,
        'verdict' => $verdict,
        'source_report_id' => $sourceReportId ?? 0,
        'graph_metrics' => clickfix_investigation_graph_metrics($normalizedGraph),
    ]);
    return $newId;
}

function clickfix_investigation_set_share(PDO $pdo, int $graphId, int $userId, bool $share, bool $isAdmin = false): ?string
{
    if ($graphId <= 0) {
        return null;
    }
    $params = [':id' => $graphId];
    $where = 'id = :id AND deleted = 0';
    if (!$isAdmin) {
        $where .= ' AND user_id = :user_id';
        $params[':user_id'] = $userId;
    }

    if (!$share) {
        $stmt = $pdo->prepare('UPDATE investigation_graphs SET is_public = 0 WHERE ' . $where);
        $stmt->execute($params);
        clickfix_investigation_log_event($pdo, $graphId, $userId, 'share_disabled', []);
        return null;
    }

    $read = $pdo->prepare('SELECT share_token FROM investigation_graphs WHERE ' . $where . ' LIMIT 1');
    $read->execute($params);
    $row = $read->fetch();
    if (!$row) {
        return null;
    }
    $token = trim((string) ($row['share_token'] ?? ''));
    if ($token === '') {
        $token = clickfix_generate_share_token();
    }
    $update = $pdo->prepare('UPDATE investigation_graphs SET is_public = 1, share_token = :token, updated_at = :updated_at WHERE ' . $where);
    $params[':token'] = $token;
    $params[':updated_at'] = gmdate('c');
    $update->execute($params);
    clickfix_investigation_log_event($pdo, $graphId, $userId, 'share_enabled', ['share_token' => $token]);
    return $token;
}

function clickfix_investigation_delete(PDO $pdo, int $graphId, int $userId, bool $isAdmin = false): bool
{
    if ($graphId <= 0) {
        return false;
    }
    if ($isAdmin) {
        $stmt = $pdo->prepare('UPDATE investigation_graphs SET deleted = 1, is_public = 0, updated_at = :updated_at WHERE id = :id');
        $ok = $stmt->execute([':id' => $graphId, ':updated_at' => gmdate('c')]);
        if ($ok) {
            clickfix_investigation_log_event($pdo, $graphId, $userId, 'delete', ['by_admin' => true]);
        }
        return $ok;
    }
    $stmt = $pdo->prepare('UPDATE investigation_graphs SET deleted = 1, is_public = 0, updated_at = :updated_at WHERE id = :id AND user_id = :user_id');
    $ok = $stmt->execute([':id' => $graphId, ':user_id' => $userId, ':updated_at' => gmdate('c')]);
    if ($ok) {
        clickfix_investigation_log_event($pdo, $graphId, $userId, 'delete', ['by_admin' => false]);
    }
    return $ok;
}

function clickfix_investigation_decode_row(array $row): array
{
    $graph = json_decode((string) ($row['graph_json'] ?? '{}'), true);
    $tags = json_decode((string) ($row['tags_json'] ?? '[]'), true);
    $row['graph'] = is_array($graph) ? clickfix_normalize_graph_payload($graph) : ['nodes' => [], 'edges' => []];
    $row['tags'] = is_array($tags) ? array_values(array_map('strval', $tags)) : [];
    $row['is_public'] = !empty($row['is_public']);
    $row['source_report_id'] = isset($row['source_report_id']) ? (int) $row['source_report_id'] : 0;
    $row['show_on_home'] = !empty($row['show_on_home']);
    $row['home_position'] = isset($row['home_position']) ? (int) $row['home_position'] : 0;
    $workflow = clickfix_investigation_workflow_status((string) ($row['workflow_status'] ?? ''));
    if ($workflow === 'draft' && $row['is_public']) {
        $workflow = 'verified_public';
    }
    $row['workflow_status'] = $workflow;
    $row['submitted_to_community'] = !empty($row['submitted_to_community']) || $workflow !== 'draft';
    $row['community_origin_role'] = clickfix_normalize_role((string) ($row['community_origin_role'] ?? 'analyst_jr'));
    $row['verified_by'] = isset($row['verified_by']) ? (int) $row['verified_by'] : null;
    $row['verification_note'] = (string) ($row['verification_note'] ?? '');
    return $row;
}

function clickfix_investigation_set_home_feature(
    PDO $pdo,
    int $graphId,
    int $userId,
    bool $showOnHome,
    int $homePosition = 0,
    ?int $sourceReportId = null,
    bool $isAdmin = false
): bool {
    if ($graphId <= 0) {
        return false;
    }
    $homePosition = max(0, min(9999, $homePosition));
    $sourceReportId = $sourceReportId !== null ? max(0, $sourceReportId) : null;
    $params = [
        ':id' => $graphId,
        ':updated_at' => gmdate('c'),
        ':show_on_home' => $showOnHome ? 1 : 0,
        ':home_position' => $homePosition,
    ];
    $where = 'id = :id AND deleted = 0';
    if (!$isAdmin) {
        $where .= ' AND user_id = :user_id';
        $params[':user_id'] = $userId;
    }
    $setParts = [
        'updated_at = :updated_at',
        'show_on_home = :show_on_home',
        'home_position = :home_position',
    ];
    if ($sourceReportId !== null) {
        $setParts[] = 'source_report_id = :source_report_id';
        $params[':source_report_id'] = $sourceReportId;
    }
    $stmt = $pdo->prepare('UPDATE investigation_graphs SET ' . implode(', ', $setParts) . ' WHERE ' . $where);
    $ok = $stmt->execute($params);
    if ($ok && $stmt->rowCount() > 0) {
        clickfix_investigation_log_event($pdo, $graphId, $userId, 'home_feature', [
            'show_on_home' => $showOnHome,
            'home_position' => $homePosition,
            'source_report_id' => $sourceReportId,
            'by_admin' => $isAdmin,
        ]);
        return true;
    }
    if (!$ok) {
        return false;
    }
    $checkSql = 'SELECT id FROM investigation_graphs WHERE ' . $where . ' LIMIT 1';
    $checkParams = [':id' => $graphId];
    if (!$isAdmin) {
        $checkParams[':user_id'] = $userId;
    }
    $check = $pdo->prepare($checkSql);
    $check->execute($checkParams);
    return (bool) $check->fetchColumn();
}

function clickfix_featured_home_investigations(PDO $pdo, int $limit = 3, bool $publicOnly = true): array
{
    $limit = max(1, min(12, $limit));
    $sql = 'SELECT ig.*, u.username
            FROM investigation_graphs ig
            LEFT JOIN users u ON u.id = ig.user_id
            WHERE ig.deleted = 0 AND ig.show_on_home = 1';
    if ($publicOnly) {
        $sql .= ' AND ig.is_public = 1';
    }
    $sql .= '
            ORDER BY
                CASE WHEN COALESCE(ig.home_position, 0) > 0 THEN 0 ELSE 1 END,
                COALESCE(ig.home_position, 0) ASC,
                ig.updated_at DESC
            LIMIT :limit';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row = clickfix_investigation_decode_row($row);
        $sourceReportId = (int) ($row['source_report_id'] ?? 0);
        $row['scan_assets'] = $sourceReportId > 0
            ? clickfix_scan_preview_assets($pdo, $sourceReportId, true)
            : ['before' => null, 'after' => null, 'before_exists' => false, 'after_exists' => false, 'before_status' => 'missing', 'after_status' => 'missing'];
    }
    unset($row);
    return $rows;
}

function clickfix_recent_investigations(PDO $pdo, int $userId, bool $isAdmin = false, int $limit = 80): array
{
    $limit = max(1, min(200, $limit));
    $cacheKey = clickfix_cache_key('recent_investigations', [
        'user_id' => $isAdmin ? 0 : $userId,
        'admin' => $isAdmin ? 1 : 0,
        'limit' => $limit,
        'v1' => true,
    ]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }
    if ($isAdmin) {
        $stmt = $pdo->prepare('SELECT ig.*, u.username FROM investigation_graphs ig LEFT JOIN users u ON u.id = ig.user_id WHERE ig.deleted = 0 ORDER BY ig.updated_at DESC LIMIT :limit');
    } else {
        $stmt = $pdo->prepare('SELECT ig.*, u.username FROM investigation_graphs ig LEFT JOIN users u ON u.id = ig.user_id WHERE ig.deleted = 0 AND ig.user_id = :user_id ORDER BY ig.updated_at DESC LIMIT :limit');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row = clickfix_investigation_decode_row($row);
    }
    unset($row);
    clickfix_cache_set($cacheKey, $rows, 8);
    return $rows;
}

function clickfix_get_investigation(PDO $pdo, int $graphId, int $userId, bool $isAdmin = false): ?array
{
    if ($graphId <= 0) {
        return null;
    }
    if ($isAdmin) {
        $stmt = $pdo->prepare('SELECT ig.*, u.username FROM investigation_graphs ig LEFT JOIN users u ON u.id = ig.user_id WHERE ig.deleted = 0 AND ig.id = :id LIMIT 1');
        $stmt->execute([':id' => $graphId]);
    } else {
        $stmt = $pdo->prepare('SELECT ig.*, u.username FROM investigation_graphs ig LEFT JOIN users u ON u.id = ig.user_id WHERE ig.deleted = 0 AND ig.id = :id AND ig.user_id = :user_id LIMIT 1');
        $stmt->execute([':id' => $graphId, ':user_id' => $userId]);
    }
    $row = $stmt->fetch();
    return $row ? clickfix_investigation_decode_row($row) : null;
}

function clickfix_get_investigation_by_share(PDO $pdo, string $token): ?array
{
    $token = trim($token);
    if ($token === '' || !preg_match('/^[a-f0-9]{24,64}$/', $token)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT ig.*, u.username FROM investigation_graphs ig LEFT JOIN users u ON u.id = ig.user_id WHERE ig.deleted = 0 AND ig.is_public = 1 AND ig.share_token = :token LIMIT 1');
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch();
    return $row ? clickfix_investigation_decode_row($row) : null;
}

function clickfix_investigation_workflow_status(string $status): string
{
    $normalized = strtolower(trim($status));
    $allowed = [
        'draft',
        'jr_submitted',
        'mid_verified',
        'sr_review',
        'verified_public',
        'verified_internal',
        'rejected',
    ];
    if (in_array($normalized, $allowed, true)) {
        return $normalized;
    }
    return 'draft';
}

function clickfix_get_investigation_any(PDO $pdo, int $graphId): ?array
{
    if ($graphId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT ig.*, u.username
         FROM investigation_graphs ig
         LEFT JOIN users u ON u.id = ig.user_id
         WHERE ig.deleted = 0 AND ig.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $graphId]);
    $row = $stmt->fetch();
    return $row ? clickfix_investigation_decode_row($row) : null;
}

function clickfix_investigation_submit_community(
    PDO $pdo,
    int $graphId,
    int $actorId,
    string $actorRole,
    bool $isAdmin = false
): bool {
    if ($graphId <= 0 || $actorId <= 0) {
        return false;
    }
    $row = clickfix_get_investigation_any($pdo, $graphId);
    if ($row === null) {
        return false;
    }
    $ownerId = (int) ($row['user_id'] ?? 0);
    if (!$isAdmin && $ownerId !== $actorId) {
        return false;
    }
    $currentStatus = clickfix_investigation_workflow_status((string) ($row['workflow_status'] ?? 'draft'));
    $alreadySubmitted = !empty($row['submitted_to_community']) || $currentStatus !== 'draft';

    $stmt = $pdo->prepare(
        'UPDATE investigation_graphs
         SET submitted_to_community = 1,
             workflow_status = :workflow_status,
             community_origin_role = :community_origin_role,
             updated_at = :updated_at
         WHERE id = :id AND deleted = 0'
    );
    $ok = $stmt->execute([
        ':workflow_status' => 'jr_submitted',
        ':community_origin_role' => clickfix_normalize_role($actorRole),
        ':updated_at' => gmdate('c'),
        ':id' => $graphId,
    ]);
    if (!$ok) {
        return false;
    }
    clickfix_investigation_log_event($pdo, $graphId, $actorId, 'community_submit', [
        'from_status' => $currentStatus,
        'to_status' => 'jr_submitted',
        'origin_role' => clickfix_normalize_role($actorRole),
    ]);
    if (!$alreadySubmitted && $ownerId > 0) {
        clickfix_apply_user_reputation($pdo, $ownerId, 1, 'community_submit', $graphId, $actorId);
    }
    return true;
}

function clickfix_investigation_set_workflow(
    PDO $pdo,
    int $graphId,
    int $actorId,
    string $nextStatus,
    string $note = ''
): bool {
    if ($graphId <= 0 || $actorId <= 0) {
        return false;
    }
    $row = clickfix_get_investigation_any($pdo, $graphId);
    if ($row === null) {
        return false;
    }
    $currentStatus = clickfix_investigation_workflow_status((string) ($row['workflow_status'] ?? 'draft'));
    $next = clickfix_investigation_workflow_status($nextStatus);
    if ($currentStatus === $next) {
        return true;
    }
    $now = gmdate('c');
    $params = [
        ':workflow_status' => $next,
        ':updated_at' => $now,
        ':submitted_to_community' => ($next === 'draft') ? 0 : 1,
        ':id' => $graphId,
        ':verification_note' => substr(trim($note), 0, 4000),
    ];

    $setParts = [
        'workflow_status = :workflow_status',
        'updated_at = :updated_at',
        'submitted_to_community = :submitted_to_community',
        'verification_note = :verification_note',
    ];
    if ($next === 'verified_public') {
        $token = trim((string) ($row['share_token'] ?? ''));
        if ($token === '') {
            $token = clickfix_generate_share_token();
        }
        $setParts[] = 'verified_by = :verified_by';
        $setParts[] = 'verified_at = :verified_at';
        $setParts[] = 'is_public = 1';
        $setParts[] = 'share_token = :share_token';
        $params[':verified_by'] = $actorId;
        $params[':verified_at'] = $now;
        $params[':share_token'] = $token;
    } elseif ($next === 'verified_internal') {
        $setParts[] = 'verified_by = :verified_by';
        $setParts[] = 'verified_at = :verified_at';
        $setParts[] = 'is_public = 0';
        $params[':verified_by'] = $actorId;
        $params[':verified_at'] = $now;
    } elseif ($next === 'rejected') {
        $setParts[] = 'is_public = 0';
        $setParts[] = 'verified_by = :verified_by';
        $setParts[] = 'verified_at = :verified_at';
        $params[':verified_by'] = $actorId;
        $params[':verified_at'] = $now;
    }

    $stmt = $pdo->prepare(
        'UPDATE investigation_graphs SET ' . implode(', ', $setParts) . ' WHERE id = :id AND deleted = 0'
    );
    $ok = $stmt->execute($params);
    if (!$ok) {
        return false;
    }

    $ownerId = (int) ($row['user_id'] ?? 0);
    if ($ownerId > 0) {
        if ($next === 'mid_verified') {
            clickfix_apply_user_reputation($pdo, $ownerId, 2, 'community_mid_verify', $graphId, $actorId);
        } elseif ($next === 'verified_public' || $next === 'verified_internal') {
            clickfix_apply_user_reputation($pdo, $ownerId, 3, 'community_senior_verify', $graphId, $actorId);
        } elseif ($next === 'rejected') {
            clickfix_apply_user_reputation($pdo, $ownerId, -1, 'community_rejected', $graphId, $actorId);
        }
    }

    clickfix_investigation_log_event($pdo, $graphId, $actorId, 'workflow_update', [
        'from_status' => $currentStatus,
        'to_status' => $next,
        'note' => substr(trim($note), 0, 280),
    ]);
    return true;
}

function clickfix_investigation_vote(PDO $pdo, int $graphId, int $userId, int $vote): bool
{
    if ($graphId <= 0 || $userId <= 0) {
        return false;
    }
    $vote = $vote > 0 ? 1 : -1;
    $row = clickfix_get_investigation_any($pdo, $graphId);
    if ($row === null) {
        return false;
    }
    $now = gmdate('c');
    $check = $pdo->prepare('SELECT id FROM investigation_votes WHERE graph_id = :graph_id AND user_id = :user_id LIMIT 1');
    $check->execute([':graph_id' => $graphId, ':user_id' => $userId]);
    $existingId = (int) ($check->fetchColumn() ?: 0);
    if ($existingId > 0) {
        $upd = $pdo->prepare('UPDATE investigation_votes SET vote = :vote, updated_at = :updated_at WHERE id = :id');
        $ok = $upd->execute([
            ':vote' => $vote,
            ':updated_at' => $now,
            ':id' => $existingId,
        ]);
    } else {
        $ins = $pdo->prepare(
            'INSERT INTO investigation_votes (created_at, updated_at, graph_id, user_id, vote)
             VALUES (:created_at, :updated_at, :graph_id, :user_id, :vote)'
        );
        $ok = $ins->execute([
            ':created_at' => $now,
            ':updated_at' => $now,
            ':graph_id' => $graphId,
            ':user_id' => $userId,
            ':vote' => $vote,
        ]);
    }
    if ($ok) {
        clickfix_investigation_log_event($pdo, $graphId, $userId, 'community_vote', ['vote' => $vote]);
    }
    return $ok;
}

function clickfix_investigation_vote_totals(PDO $pdo, int $graphId): array
{
    if ($graphId <= 0) {
        return ['score' => 0, 'upvotes' => 0, 'downvotes' => 0, 'classification' => 'neutral'];
    }
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(vote), 0) AS score,
                COALESCE(SUM(CASE WHEN vote > 0 THEN 1 ELSE 0 END), 0) AS upvotes,
                COALESCE(SUM(CASE WHEN vote < 0 THEN 1 ELSE 0 END), 0) AS downvotes
         FROM investigation_votes
         WHERE graph_id = :graph_id'
    );
    $stmt->execute([':graph_id' => $graphId]);
    $row = $stmt->fetch() ?: [];
    $score = (int) ($row['score'] ?? 0);
    $classification = 'neutral';
    if ($score > 1) {
        $classification = 'malware';
    } elseif ($score < 1) {
        $classification = 'legit';
    }
    return [
        'score' => $score,
        'upvotes' => (int) ($row['upvotes'] ?? 0),
        'downvotes' => (int) ($row['downvotes'] ?? 0),
        'classification' => $classification,
    ];
}

function clickfix_community_investigations(PDO $pdo, int $limit = 160): array
{
    $limit = max(1, min(500, $limit));
    $cacheKey = clickfix_cache_key('community_investigations', ['limit' => $limit, 'v1' => true]);
    $cached = clickfix_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }
    $stmt = $pdo->prepare(
        'SELECT ig.*, u.username, u.reputation
         FROM investigation_graphs ig
         LEFT JOIN users u ON u.id = ig.user_id
         WHERE ig.deleted = 0 AND COALESCE(ig.submitted_to_community, 0) = 1
         ORDER BY ig.updated_at DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (int) ($row['id'] ?? 0);
    }
    $scoreByGraph = [];
    if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $voteStmt = $pdo->prepare(
            "SELECT graph_id,
                    COALESCE(SUM(vote), 0) AS score,
                    COALESCE(SUM(CASE WHEN vote > 0 THEN 1 ELSE 0 END), 0) AS upvotes,
                    COALESCE(SUM(CASE WHEN vote < 0 THEN 1 ELSE 0 END), 0) AS downvotes
             FROM investigation_votes
             WHERE graph_id IN ({$in})
             GROUP BY graph_id"
        );
        foreach ($ids as $idx => $graphId) {
            $voteStmt->bindValue($idx + 1, $graphId, PDO::PARAM_INT);
        }
        $voteStmt->execute();
        foreach ($voteStmt->fetchAll() as $voteRow) {
            $graphId = (int) ($voteRow['graph_id'] ?? 0);
            $score = (int) ($voteRow['score'] ?? 0);
            $classification = 'neutral';
            if ($score > 1) {
                $classification = 'malware';
            } elseif ($score < 1) {
                $classification = 'legit';
            }
            $scoreByGraph[$graphId] = [
                'score' => $score,
                'upvotes' => (int) ($voteRow['upvotes'] ?? 0),
                'downvotes' => (int) ($voteRow['downvotes'] ?? 0),
                'classification' => $classification,
            ];
        }
    }

    $out = [];
    foreach ($rows as $row) {
        $decoded = clickfix_investigation_decode_row($row);
        $graphId = (int) ($decoded['id'] ?? 0);
        $voteData = $scoreByGraph[$graphId] ?? ['score' => 0, 'upvotes' => 0, 'downvotes' => 0, 'classification' => 'neutral'];
        $decoded['vote_score'] = (int) $voteData['score'];
        $decoded['upvotes'] = (int) $voteData['upvotes'];
        $decoded['downvotes'] = (int) $voteData['downvotes'];
        $decoded['malware_classification'] = (string) $voteData['classification'];
        $decoded['workflow_status'] = clickfix_investigation_workflow_status((string) ($decoded['workflow_status'] ?? 'draft'));
        $decoded['community_origin_role'] = clickfix_normalize_role((string) ($decoded['community_origin_role'] ?? 'analyst_jr'));
        $decoded['author_reputation'] = (int) ($decoded['reputation'] ?? 0);
        $out[] = $decoded;
    }
    clickfix_cache_set($cacheKey, $out, 8);
    return $out;
}

function clickfix_load_env_file(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;
    $envPath = dirname(__DIR__) . '/.env.security';
    if (!is_readable($envPath)) {
        return;
    }
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || clickfix_str_starts_with($line, '#')) {
            continue;
        }
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));
        if ($key === '') {
            continue;
        }
        if (strlen($value) >= 2 && (
            ($value[0] === '"' && $value[strlen($value) - 1] === '"') ||
            ($value[0] === "'" && $value[strlen($value) - 1] === "'")
        )) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function clickfix_env(string $key, ?string $fallback = null): ?string
{
    clickfix_load_env_file();
    $value = getenv($key);
    if (!is_string($value) || $value === '') {
        return $fallback;
    }
    return $value;
}

function clickfix_env_truthy(string $key, bool $fallback = false): bool
{
    $raw = clickfix_env($key, $fallback ? '1' : '0');
    $value = strtolower(trim((string) $raw));
    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function clickfix_sanitize_http_url(?string $value): string
{
    $url = trim((string) $value);
    if ($url === '') {
        return '';
    }
    if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return '';
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if ($scheme !== 'http' && $scheme !== 'https') {
        return '';
    }
    if (isset($parts['user']) || isset($parts['pass'])) {
        return '';
    }
    $host = strtolower(trim((string) ($parts['host'] ?? '')));
    if ($host === '') {
        return '';
    }
    return $url;
}

function clickfix_monetization_config(): array
{
    $externalTrackingDisabled = clickfix_external_tracking_disabled();
    $paypalUrl = clickfix_sanitize_http_url(clickfix_env('CLICKFIX_DONATION_PAYPAL_URL', ''));
    $kofiUrl = clickfix_sanitize_http_url(clickfix_env('CLICKFIX_DONATION_KOFI_URL', ''));
    $stripeUrl = clickfix_sanitize_http_url(clickfix_env('CLICKFIX_DONATION_STRIPE_URL', ''));
    $adsenseClient = trim((string) clickfix_env('CLICKFIX_ADSENSE_CLIENT', ''));
    $adsenseSlot = trim((string) clickfix_env('CLICKFIX_ADSENSE_SLOT', ''));
    $adsenseEnabled = clickfix_env_truthy('CLICKFIX_ADSENSE_ENABLED', false);

    if ($externalTrackingDisabled) {
        $paypalUrl = '';
        $kofiUrl = '';
        $stripeUrl = '';
        $adsenseClient = '';
        $adsenseSlot = '';
        $adsenseEnabled = false;
    }

    $hasDonations = $paypalUrl !== '' || $kofiUrl !== '' || $stripeUrl !== '';
    $enabledByFlag = clickfix_env_truthy('CLICKFIX_MONETIZATION_ENABLED', false);
    $enabled = $enabledByFlag || $hasDonations || $adsenseEnabled;

    if (!preg_match('/^ca-pub-[0-9]{10,20}$/', $adsenseClient)) {
        $adsenseClient = '';
    }
    if (!preg_match('/^[0-9]{6,20}$/', $adsenseSlot)) {
        $adsenseSlot = '';
    }

    $showAds = !$externalTrackingDisabled && $enabled && $adsenseEnabled && $adsenseClient !== '' && $adsenseSlot !== '';

    return [
        'enabled' => $enabled,
        'has_donations' => $enabled && $hasDonations,
        'donation_paypal_url' => $paypalUrl,
        'donation_kofi_url' => $kofiUrl,
        'donation_stripe_url' => $stripeUrl,
        'show_ads' => $showAds,
        'adsense_client' => $adsenseClient,
        'adsense_slot' => $adsenseSlot,
    ];
}

function clickfix_internal_ad_theme(string $theme): string
{
    $value = strtolower(trim($theme));
    return in_array($value, ['cyan', 'lime', 'amber', 'fuchsia'], true) ? $value : 'cyan';
}

function clickfix_internal_ad_placement(string $placement): string
{
    $value = strtolower(trim($placement));
    return in_array($value, ['index', 'dashboard', 'both'], true) ? $value : 'both';
}

function clickfix_internal_ad_settings_default(): array
{
    return [
        'enabled_global' => 1,
        'show_guest' => 1,
        'show_analyst_jr' => 1,
        'show_analyst_mid' => 1,
        'show_analyst_sr' => 0,
        'show_admin' => 0,
    ];
}

function clickfix_public_preview_settings_default(): array
{
    return [
        'limit_points_per_country' => 1,
        'max_points_per_country' => 2,
    ];
}

function clickfix_public_preview_settings(PDO $pdo): array
{
    $defaults = clickfix_public_preview_settings_default();
    if (!clickfix_has_table($pdo, 'public_preview_settings')) {
        return $defaults;
    }
    $row = $pdo->query('SELECT * FROM public_preview_settings WHERE id = 1 LIMIT 1')->fetch() ?: [];
    if (!$row) {
        return $defaults;
    }
    return [
        'limit_points_per_country' => !empty($row['limit_points_per_country']) ? 1 : 0,
        'max_points_per_country' => max(1, min(12, (int) ($row['max_points_per_country'] ?? $defaults['max_points_per_country']))),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
        'updated_by' => (int) ($row['updated_by'] ?? 0),
    ];
}

function clickfix_public_preview_settings_save(PDO $pdo, array $payload, int $actorId = 0): bool
{
    if (!clickfix_has_table($pdo, 'public_preview_settings')) {
        return false;
    }
    $enabled = !empty($payload['limit_points_per_country']) ? 1 : 0;
    $max = max(1, min(12, (int) ($payload['max_points_per_country'] ?? 2)));
    $stmt = $pdo->prepare(
        'INSERT INTO public_preview_settings (
            id, updated_at, updated_by, limit_points_per_country, max_points_per_country
         ) VALUES (
            1, :updated_at, :updated_by, :enabled, :max_points
         )
         ON CONFLICT(id) DO UPDATE SET
            updated_at = excluded.updated_at,
            updated_by = excluded.updated_by,
            limit_points_per_country = excluded.limit_points_per_country,
            max_points_per_country = excluded.max_points_per_country'
    );
    return $stmt->execute([
        ':updated_at' => gmdate('c'),
        ':updated_by' => max(0, $actorId),
        ':enabled' => $enabled,
        ':max_points' => $max,
    ]);
}

function clickfix_limit_geo_points_per_country(array $points, bool $enabled, int $maxPointsPerCountry): array
{
    if (!$enabled) {
        return array_values($points);
    }
    $maxPointsPerCountry = max(1, min(12, $maxPointsPerCountry));
    $limited = [];
    $seen = [];
    foreach ($points as $point) {
        if (!is_array($point)) {
            continue;
        }
        $countryCode = strtoupper(substr((string) ($point['country_code'] ?? ''), 0, 2));
        if ($countryCode === '' || !preg_match('/^[A-Z]{2}$/', $countryCode)) {
            $countryCode = '__UNK__';
        }
        $seen[$countryCode] = ($seen[$countryCode] ?? 0) + 1;
        if ($seen[$countryCode] > $maxPointsPerCountry) {
            continue;
        }
        $limited[] = $point;
    }
    return $limited;
}

function clickfix_internal_ad_settings(PDO $pdo): array
{
    $defaults = clickfix_internal_ad_settings_default();
    if (!clickfix_has_table($pdo, 'internal_ad_settings')) {
        return $defaults;
    }
    $row = $pdo->query('SELECT * FROM internal_ad_settings WHERE id = 1 LIMIT 1')->fetch() ?: [];
    if (!$row) {
        return $defaults;
    }
    return [
        'enabled_global' => !empty($row['enabled_global']) ? 1 : 0,
        'show_guest' => !empty($row['show_guest']) ? 1 : 0,
        'show_analyst_jr' => !empty($row['show_analyst_jr']) ? 1 : 0,
        'show_analyst_mid' => !empty($row['show_analyst_mid']) ? 1 : 0,
        'show_analyst_sr' => !empty($row['show_analyst_sr']) ? 1 : 0,
        'show_admin' => !empty($row['show_admin']) ? 1 : 0,
        'updated_at' => (string) ($row['updated_at'] ?? ''),
        'updated_by' => (int) ($row['updated_by'] ?? 0),
    ];
}

function clickfix_internal_ad_settings_save(PDO $pdo, array $payload, int $actorId = 0): bool
{
    if (!clickfix_has_table($pdo, 'internal_ad_settings')) {
        return false;
    }
    $defaults = clickfix_internal_ad_settings_default();
    $row = [];
    foreach ($defaults as $key => $defaultValue) {
        $row[$key] = !empty($payload[$key]) ? 1 : 0;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO internal_ad_settings (
            id, updated_at, updated_by, enabled_global, show_guest, show_analyst_jr, show_analyst_mid, show_analyst_sr, show_admin
         ) VALUES (
            1, :updated_at, :updated_by, :enabled_global, :show_guest, :show_analyst_jr, :show_analyst_mid, :show_analyst_sr, :show_admin
         )
         ON CONFLICT(id) DO UPDATE SET
            updated_at = excluded.updated_at,
            updated_by = excluded.updated_by,
            enabled_global = excluded.enabled_global,
            show_guest = excluded.show_guest,
            show_analyst_jr = excluded.show_analyst_jr,
            show_analyst_mid = excluded.show_analyst_mid,
            show_analyst_sr = excluded.show_analyst_sr,
            show_admin = excluded.show_admin'
    );
    return $stmt->execute([
        ':updated_at' => gmdate('c'),
        ':updated_by' => max(0, $actorId),
        ':enabled_global' => $row['enabled_global'],
        ':show_guest' => $row['show_guest'],
        ':show_analyst_jr' => $row['show_analyst_jr'],
        ':show_analyst_mid' => $row['show_analyst_mid'],
        ':show_analyst_sr' => $row['show_analyst_sr'],
        ':show_admin' => $row['show_admin'],
    ]);
}

function clickfix_internal_ad_role_enabled(array $settings, string $role): bool
{
    if (empty($settings['enabled_global'])) {
        return false;
    }
    if (strtolower(trim($role)) === 'guest') {
        return !empty($settings['show_guest']);
    }
    $role = clickfix_normalize_role($role);
    if ($role === 'analyst_jr') {
        return !empty($settings['show_analyst_jr']);
    }
    if ($role === 'analyst_mid') {
        return !empty($settings['show_analyst_mid']);
    }
    if ($role === 'analyst_sr') {
        return !empty($settings['show_analyst_sr']);
    }
    if ($role === 'admin') {
        return !empty($settings['show_admin']);
    }
    return false;
}

function clickfix_internal_ads_seed_test(PDO $pdo, int $actorId = 0, bool $force = false): int
{
    if (!clickfix_has_table($pdo, 'internal_ads')) {
        return 0;
    }
    $existingAds = (int) ($pdo->query('SELECT COUNT(*) FROM internal_ads')->fetchColumn() ?: 0);
    if ($existingAds > 0 && !$force) {
        return 0;
    }
    $seedRows = [
        [
            'title' => 'Demo sponsor | Threat Intel Feed',
            'body' => 'Espacio de prueba para promocionar un feed CTI, una demo o un partner. Visible solo para perfiles con anuncios habilitados.',
            'cta_label' => 'Ver demo',
            'cta_url' => 'https://jordiserrano.me',
            'placement' => 'both',
            'theme' => 'cyan',
            'priority' => 200,
            'target_guest' => 1,
            'target_analyst_jr' => 1,
            'target_analyst_mid' => 1,
            'target_analyst_sr' => 0,
            'target_admin' => 0,
        ],
        [
            'title' => 'Test ad | Analyst onboarding',
            'body' => 'Anuncio interno de prueba para captar analistas, dar visibilidad a nuevas demos o resaltar workflows guiados.',
            'cta_label' => 'Solicitar acceso',
            'cta_url' => 'https://clickfix.jordiserrano.me/index.php#dashboard-preview-access',
            'placement' => 'index',
            'theme' => 'lime',
            'priority' => 180,
            'target_guest' => 1,
            'target_analyst_jr' => 1,
            'target_analyst_mid' => 0,
            'target_analyst_sr' => 0,
            'target_admin' => 0,
        ],
        [
            'title' => 'Test ad | Upgrade workspace',
            'body' => 'Promociona integraciones, servicios gestionados o nuevas capacidades del panel sin depender de terceros.',
            'cta_label' => 'Abrir dashboard',
            'cta_url' => 'https://clickfix.jordiserrano.me/dashboard.php?page=ops',
            'placement' => 'dashboard',
            'theme' => 'amber',
            'priority' => 160,
            'target_guest' => 0,
            'target_analyst_jr' => 1,
            'target_analyst_mid' => 1,
            'target_analyst_sr' => 0,
            'target_admin' => 0,
        ],
    ];
    $count = 0;
    foreach ($seedRows as $row) {
        if (clickfix_internal_ad_save($pdo, $row, $actorId)) {
            $count++;
        }
    }
    return $count;
}

function clickfix_internal_ad_save(PDO $pdo, array $payload, int $actorId = 0): bool
{
    if (!clickfix_has_table($pdo, 'internal_ads')) {
        return false;
    }
    $title = trim((string) ($payload['title'] ?? ''));
    $body = trim((string) ($payload['body'] ?? ''));
    if ($title === '' || $body === '') {
        return false;
    }
    $ctaLabel = trim((string) ($payload['cta_label'] ?? ''));
    $ctaUrl = clickfix_sanitize_http_url((string) ($payload['cta_url'] ?? ''));
    $placement = clickfix_internal_ad_placement((string) ($payload['placement'] ?? 'both'));
    $theme = clickfix_internal_ad_theme((string) ($payload['theme'] ?? 'cyan'));
    $priority = max(0, min(10000, (int) ($payload['priority'] ?? 100)));
    $startsAt = trim((string) ($payload['starts_at'] ?? ''));
    $expiresAt = trim((string) ($payload['expires_at'] ?? ''));
    $active = !array_key_exists('active', $payload) || !empty($payload['active']) ? 1 : 0;
    $targetGuest = !empty($payload['target_guest']) ? 1 : 0;
    $targetJr = !empty($payload['target_analyst_jr']) ? 1 : 0;
    $targetMid = !empty($payload['target_analyst_mid']) ? 1 : 0;
    $targetSr = !empty($payload['target_analyst_sr']) ? 1 : 0;
    $targetAdmin = !empty($payload['target_admin']) ? 1 : 0;
    if (($targetGuest + $targetJr + $targetMid + $targetSr + $targetAdmin) <= 0) {
        return false;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO internal_ads (
            created_at, updated_at, created_by, title, body, cta_label, cta_url, placement, theme, priority, starts_at, expires_at,
            active, target_guest, target_analyst_jr, target_analyst_mid, target_analyst_sr, target_admin
         ) VALUES (
            :created_at, :updated_at, :created_by, :title, :body, :cta_label, :cta_url, :placement, :theme, :priority, :starts_at, :expires_at,
            :active, :target_guest, :target_analyst_jr, :target_analyst_mid, :target_analyst_sr, :target_admin
         )'
    );
    return $stmt->execute([
        ':created_at' => gmdate('c'),
        ':updated_at' => gmdate('c'),
        ':created_by' => max(0, $actorId),
        ':title' => substr($title, 0, 180),
        ':body' => substr($body, 0, 2000),
        ':cta_label' => substr($ctaLabel, 0, 80),
        ':cta_url' => $ctaUrl,
        ':placement' => $placement,
        ':theme' => $theme,
        ':priority' => $priority,
        ':starts_at' => $startsAt !== '' ? $startsAt : null,
        ':expires_at' => $expiresAt !== '' ? $expiresAt : null,
        ':active' => $active,
        ':target_guest' => $targetGuest,
        ':target_analyst_jr' => $targetJr,
        ':target_analyst_mid' => $targetMid,
        ':target_analyst_sr' => $targetSr,
        ':target_admin' => $targetAdmin,
    ]);
}

function clickfix_internal_ads_recent(PDO $pdo, int $limit = 80): array
{
    if (!clickfix_has_table($pdo, 'internal_ads')) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT ia.*, u.username AS created_by_username
         FROM internal_ads ia
         LEFT JOIN users u ON u.id = ia.created_by
         ORDER BY ia.priority DESC, ia.updated_at DESC, ia.id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', max(1, min(300, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function clickfix_internal_ad_toggle(PDO $pdo, int $adId, bool $active): bool
{
    if ($adId <= 0 || !clickfix_has_table($pdo, 'internal_ads')) {
        return false;
    }
    $stmt = $pdo->prepare('UPDATE internal_ads SET active = :active, updated_at = :updated_at WHERE id = :id');
    return $stmt->execute([
        ':active' => $active ? 1 : 0,
        ':updated_at' => gmdate('c'),
        ':id' => $adId,
    ]);
}

function clickfix_internal_ad_delete(PDO $pdo, int $adId): bool
{
    if ($adId <= 0 || !clickfix_has_table($pdo, 'internal_ads')) {
        return false;
    }
    $stmt = $pdo->prepare('DELETE FROM internal_ads WHERE id = :id');
    return $stmt->execute([':id' => $adId]);
}

function clickfix_internal_ads_for_context(PDO $pdo, string $placement, string $role = 'guest', int $limit = 4): array
{
    $settings = clickfix_internal_ad_settings($pdo);
    if (!clickfix_internal_ad_role_enabled($settings, $role) || !clickfix_has_table($pdo, 'internal_ads')) {
        return [];
    }
    $placement = clickfix_internal_ad_placement($placement);
    $role = $role === 'guest' ? 'guest' : clickfix_normalize_role($role);
    $targetColumn = 'target_guest';
    if ($role === 'analyst_jr') {
        $targetColumn = 'target_analyst_jr';
    } elseif ($role === 'analyst_mid') {
        $targetColumn = 'target_analyst_mid';
    } elseif ($role === 'analyst_sr') {
        $targetColumn = 'target_analyst_sr';
    } elseif ($role === 'admin') {
        $targetColumn = 'target_admin';
    }
    $now = gmdate('c');
    $sql =
        'SELECT *
         FROM internal_ads
         WHERE active = 1
           AND ' . $targetColumn . ' = 1
           AND placement IN (:placement, :both)
           AND (starts_at IS NULL OR starts_at = \'\' OR starts_at <= :now)
           AND (expires_at IS NULL OR expires_at = \'\' OR expires_at >= :now)
         ORDER BY priority DESC, updated_at DESC, id DESC
         LIMIT :limit';
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':placement', $placement, PDO::PARAM_STR);
    $stmt->bindValue(':both', 'both', PDO::PARAM_STR);
    $stmt->bindValue(':now', $now, PDO::PARAM_STR);
    $stmt->bindValue(':limit', max(1, min(12, $limit)), PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function clickfix_is_absolute_path(string $path): bool
{
    if ($path === '') {
        return false;
    }
    if ($path[0] === '/' || $path[0] === '\\') {
        return true;
    }
    return (bool) preg_match('/^[A-Za-z]:[\/\\\\]/', $path);
}

function clickfix_resolve_env_path(string $path): string
{
    $trimmed = trim($path);
    if ($trimmed === '') {
        return '';
    }
    if (clickfix_is_absolute_path($trimmed)) {
        return $trimmed;
    }
    $base = dirname(__DIR__);
    return $base . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $trimmed), DIRECTORY_SEPARATOR);
}

function clickfix_allowed_origins(): array
{
    $raw = trim((string) clickfix_env('CLICKFIX_ALLOWED_ORIGINS', ''));
    if ($raw === '') {
        return [];
    }
    $origins = [];
    foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $part) {
        $origin = trim((string) $part);
        if ($origin !== '') {
            $origins[$origin] = true;
        }
    }
    return array_keys($origins);
}

function clickfix_apply_api_headers(
    string $allowedMethods = 'POST, OPTIONS',
    string $allowedHeaders = 'Content-Type, Authorization, X-API-Key'
): void {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cache-Control: no-store');
        header('Access-Control-Allow-Methods: ' . $allowedMethods);
        header('Access-Control-Allow-Headers: ' . $allowedHeaders);
    }
    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $allowed = clickfix_allowed_origins();
    if (!headers_sent()) {
        if ($origin !== '' && clickfix_is_extension_origin($origin)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            return;
        }
        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
    }
}

function clickfix_request_origin(): string
{
    return trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
}

function clickfix_is_extension_origin(string $origin): bool
{
    $origin = trim($origin);
    if ($origin === '') {
        return false;
    }
    return (bool) preg_match('/^(chrome-extension|moz-extension):\/\/[a-z0-9_-]+$/i', $origin);
}

function clickfix_is_request_origin_allowed(bool $allowNoOrigin = true): bool
{
    $origin = clickfix_request_origin();
    if ($origin === '') {
        return $allowNoOrigin;
    }
    if (clickfix_is_extension_origin($origin)) {
        return true;
    }
    $allowed = clickfix_allowed_origins();
    if (empty($allowed)) {
        return false;
    }
    return in_array($origin, $allowed, true);
}

function clickfix_api_json(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clickfix_base64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function clickfix_base64url_decode(string $encoded): ?string
{
    if ($encoded === '' || preg_match('/[^A-Za-z0-9\-_]/', $encoded)) {
        return null;
    }
    $raw = strtr($encoded, '-_', '+/');
    $pad = strlen($raw) % 4;
    if ($pad > 0) {
        $raw .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode($raw, true);
    return $decoded === false ? null : $decoded;
}

function clickfix_jwt_secret(): string
{
    $secret = trim((string) clickfix_env('CLICKFIX_API_JWT_SECRET', ''));
    if ($secret !== '') {
        return $secret;
    }
    return 'CHANGE_ME_CLICKFIX_API_JWT_SECRET';
}

function clickfix_jwt_sign(array $claims, int $ttlSeconds = 600): string
{
    $now = time();
    $payload = $claims;
    $payload['iat'] = (int) ($payload['iat'] ?? $now);
    $payload['nbf'] = (int) ($payload['nbf'] ?? $now);
    $payload['exp'] = (int) ($payload['exp'] ?? ($now + max(30, $ttlSeconds)));
    $payload['iss'] = (string) ($payload['iss'] ?? 'clickfix-api');
    $payload['jti'] = (string) ($payload['jti'] ?? bin2hex(random_bytes(12)));

    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $header64 = clickfix_base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES));
    $payload64 = clickfix_base64url_encode(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $message = $header64 . '.' . $payload64;
    $signature = hash_hmac('sha256', $message, clickfix_jwt_secret(), true);
    return $message . '.' . clickfix_base64url_encode($signature);
}

function clickfix_jwt_verify(string $token): ?array
{
    $parts = explode('.', trim($token));
    if (count($parts) !== 3) {
        return null;
    }
    [$header64, $payload64, $sig64] = $parts;
    $headerJson = clickfix_base64url_decode($header64);
    $payloadJson = clickfix_base64url_decode($payload64);
    $signature = clickfix_base64url_decode($sig64);
    if ($headerJson === null || $payloadJson === null || $signature === null) {
        return null;
    }
    $header = json_decode($headerJson, true);
    $payload = json_decode($payloadJson, true);
    if (!is_array($header) || !is_array($payload)) {
        return null;
    }
    if ((string) ($header['alg'] ?? '') !== 'HS256') {
        return null;
    }
    $expected = hash_hmac('sha256', $header64 . '.' . $payload64, clickfix_jwt_secret(), true);
    if (!hash_equals($expected, $signature)) {
        return null;
    }
    $now = time();
    $nbf = (int) ($payload['nbf'] ?? 0);
    $exp = (int) ($payload['exp'] ?? 0);
    if ($nbf > 0 && $nbf > ($now + 30)) {
        return null;
    }
    if ($exp > 0 && $exp < ($now - 30)) {
        return null;
    }
    return $payload;
}

function clickfix_hash_secret(string $value): string
{
    $pepper = (string) clickfix_env('CLICKFIX_SECRET_PEPPER', '');
    return hash('sha256', trim($value) . '|' . $pepper);
}

function clickfix_api_rate_limit(PDO $pdo, string $bucketKey, int $limit, int $windowSeconds): bool
{
    try {
        $limit = max(1, $limit);
        $windowSeconds = max(10, $windowSeconds);
        $now = time();
        $windowStart = $now - ($now % $windowSeconds);

        if (random_int(1, 50) === 1) {
            try {
                $cleanup = $pdo->prepare('DELETE FROM api_rate_limits WHERE window_start < :min_window');
                $cleanup->execute([':min_window' => $windowStart - ($windowSeconds * 2)]);
            } catch (Throwable $exception) {
                // Cleanup is best-effort; ignore lock contention.
            }
        }

        $attempts = 0;
        while ($attempts < 3) {
            try {
                $upsert = $pdo->prepare(
                    'INSERT INTO api_rate_limits (bucket_key, window_start, request_count)
                     VALUES (:k, :w, 1)
                     ON CONFLICT(bucket_key) DO UPDATE SET
                       window_start = CASE
                         WHEN api_rate_limits.window_start = :w THEN api_rate_limits.window_start
                         ELSE :w
                       END,
                       request_count = CASE
                         WHEN api_rate_limits.window_start = :w THEN api_rate_limits.request_count + 1
                         ELSE 1
                       END'
                );
                $upsert->execute([':k' => $bucketKey, ':w' => $windowStart]);

                $select = $pdo->prepare('SELECT window_start, request_count FROM api_rate_limits WHERE bucket_key = :k LIMIT 1');
                $select->execute([':k' => $bucketKey]);
                $row = $select->fetch();
                if (!$row) {
                    return true;
                }
                $storedWindow = (int) ($row['window_start'] ?? 0);
                $storedCount = (int) ($row['request_count'] ?? 0);
                if ($storedWindow !== $windowStart) {
                    return true;
                }
                return $storedCount <= $limit;
            } catch (PDOException $exception) {
                $message = strtolower($exception->getMessage());
                if (str_contains($message, 'database is locked') || str_contains($message, 'busy')) {
                    $attempts++;
                    usleep(50000);
                    continue;
                }
                throw $exception;
            }
        }

        return false;
    } catch (Throwable $exception) {
        if (clickfix_is_readonly_error($exception)) {
            // If the DB is read-only, allow the request instead of 500.
            return true;
        }
        throw $exception;
    }
}

function clickfix_static_license_map(): array
{
    $raw = trim((string) clickfix_env('CLICKFIX_API_LICENSE_KEYS', ''));
    if ($raw === '') {
        return [];
    }
    $map = [];
    foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $chunk) {
        $chunk = trim((string) $chunk);
        if ($chunk === '') {
            continue;
        }
        $parts = explode(':', $chunk);
        $license = trim((string) ($parts[0] ?? ''));
        if ($license === '') {
            continue;
        }
        $tier = strtolower(trim((string) ($parts[1] ?? 'basic')));
        if (!in_array($tier, ['basic', 'premium', 'enterprise'], true)) {
            $tier = 'basic';
        }
        $rpm = (int) ($parts[2] ?? 120);
        $map[$license] = ['tier' => $tier, 'max_rpm' => max(30, min(2000, $rpm))];
    }
    return $map;
}

function clickfix_api_client_from_license(PDO $pdo, string $licenseKey): ?array
{
    $licenseKey = trim($licenseKey);
    if ($licenseKey === '' || strlen($licenseKey) > 200) {
        return null;
    }
    $hash = clickfix_hash_secret($licenseKey);
    $stmt = $pdo->prepare('SELECT id, tier, max_rpm, active FROM api_clients WHERE license_key_hash = :h LIMIT 1');
    $stmt->execute([':h' => $hash]);
    $row = $stmt->fetch();
    if ($row) {
        if ((int) ($row['active'] ?? 0) !== 1) {
            return null;
        }
        return [
            'id' => (int) ($row['id'] ?? 0),
            'tier' => (string) ($row['tier'] ?? 'basic'),
            'max_rpm' => (int) ($row['max_rpm'] ?? 120),
        ];
    }

    $static = clickfix_static_license_map();
    if (!isset($static[$licenseKey])) {
        return null;
    }
    $tier = (string) ($static[$licenseKey]['tier'] ?? 'basic');
    $maxRpm = (int) ($static[$licenseKey]['max_rpm'] ?? 120);
    $insert = $pdo->prepare('INSERT INTO api_clients (created_at, label, license_key_hash, tier, max_rpm, active) VALUES (:at, :label, :hash, :tier, :rpm, 1)');
    $insert->execute([
        ':at' => gmdate('c'),
        ':label' => 'env-seeded',
        ':hash' => $hash,
        ':tier' => $tier,
        ':rpm' => max(30, min(2000, $maxRpm)),
    ]);
    return [
        'id' => (int) $pdo->lastInsertId(),
        'tier' => $tier,
        'max_rpm' => max(30, min(2000, $maxRpm)),
    ];
}

function clickfix_issue_api_tokens(
    PDO $pdo,
    int $clientId,
    string $tier,
    int $maxRpm,
    string $deviceId,
    string $ip
): array {
    $deviceId = substr(preg_replace('/[^a-zA-Z0-9._:-]/', '', $deviceId), 0, 96);
    if ($deviceId === '') {
        $deviceId = 'unknown-device';
    }
    $now = time();
    $accessExp = $now + 10 * 60;
    $refreshExpIso = gmdate('c', $now + 30 * 86400);
    $scopes = ['report:write', 'config:read', 'intel:read'];
    if ($tier === 'enterprise') {
        $scopes[] = 'intel:write';
    }

    $accessToken = clickfix_jwt_sign([
        'sub' => (string) $clientId,
        'tier' => $tier,
        'device' => $deviceId,
        'rpm' => max(30, min(2000, $maxRpm)),
        'scope' => implode(' ', $scopes),
    ], 10 * 60);

    $refreshToken = clickfix_base64url_encode(random_bytes(48));
    $refreshHash = clickfix_hash_secret($refreshToken);
    $insert = $pdo->prepare('INSERT INTO api_refresh_tokens (created_at, client_id, device_id, token_hash, expires_at, last_used_at, revoked_at, last_ip) VALUES (:at, :cid, :device, :hash, :exp, :used, NULL, :ip)');
    $insert->execute([
        ':at' => gmdate('c'),
        ':cid' => $clientId,
        ':device' => $deviceId,
        ':hash' => $refreshHash,
        ':exp' => $refreshExpIso,
        ':used' => gmdate('c'),
        ':ip' => substr($ip, 0, 80),
    ]);

    return [
        'access_token' => $accessToken,
        'token_type' => 'Bearer',
        'expires_in' => max(30, $accessExp - $now),
        'expires_at' => gmdate('c', $accessExp),
        'refresh_token' => $refreshToken,
        'scope' => implode(' ', $scopes),
        'tier' => $tier,
        'max_rpm' => max(30, min(2000, $maxRpm)),
    ];
}

function clickfix_refresh_api_tokens(PDO $pdo, string $refreshToken, string $deviceId, string $ip): ?array
{
    $hash = clickfix_hash_secret(trim($refreshToken));
    if ($hash === clickfix_hash_secret('')) {
        return null;
    }
    $deviceId = substr(preg_replace('/[^a-zA-Z0-9._:-]/', '', $deviceId), 0, 96);
    $query = $pdo->prepare(
        'SELECT rt.id, rt.client_id, rt.device_id, rt.expires_at, rt.revoked_at, c.tier, c.max_rpm, c.active
         FROM api_refresh_tokens rt
         JOIN api_clients c ON c.id = rt.client_id
         WHERE rt.token_hash = :hash
         LIMIT 1'
    );
    $query->execute([':hash' => $hash]);
    $row = $query->fetch();
    if (!$row) {
        return null;
    }
    if ((int) ($row['active'] ?? 0) !== 1) {
        return null;
    }
    if (!empty($row['revoked_at'])) {
        return null;
    }
    $expiresAt = strtotime((string) ($row['expires_at'] ?? ''));
    if (!$expiresAt || $expiresAt < time()) {
        return null;
    }
    if ($deviceId !== '' && $deviceId !== (string) ($row['device_id'] ?? '')) {
        return null;
    }
    $revoke = $pdo->prepare('UPDATE api_refresh_tokens SET revoked_at = :revoked WHERE id = :id');
    $revoke->execute([':revoked' => gmdate('c'), ':id' => (int) ($row['id'] ?? 0)]);
    return clickfix_issue_api_tokens(
        $pdo,
        (int) ($row['client_id'] ?? 0),
        (string) ($row['tier'] ?? 'basic'),
        (int) ($row['max_rpm'] ?? 120),
        (string) ($row['device_id'] ?? $deviceId),
        $ip
    );
}

function clickfix_authorization_header(): string
{
    $raw = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if ($raw === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            $raw = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
        }
    }
    return trim($raw);
}

function clickfix_bearer_token(): string
{
    $raw = clickfix_authorization_header();
    if (!preg_match('/^\s*Bearer\s+(.+)\s*$/i', $raw, $matches)) {
        return '';
    }
    return trim((string) ($matches[1] ?? ''));
}

function clickfix_api_key_token(): string
{
    $key = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($key === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (is_array($headers)) {
            $key = trim((string) ($headers['X-API-Key'] ?? $headers['x-api-key'] ?? ''));
        }
    }
    if ($key === '') {
        $raw = clickfix_authorization_header();
        if (preg_match('/^\s*ApiKey\s+(.+)\s*$/i', $raw, $matches)) {
            $key = trim((string) ($matches[1] ?? ''));
        }
    }
    if ($key === '' || strlen($key) > 220) {
        return '';
    }
    return $key;
}

function clickfix_token_has_scopes(array $claims, array $requiredScopes): bool
{
    if (empty($requiredScopes)) {
        return true;
    }
    $scopeText = trim((string) ($claims['scope'] ?? ''));
    if ($scopeText === '') {
        return false;
    }
    $available = preg_split('/\s+/', $scopeText) ?: [];
    $set = [];
    foreach ($available as $scope) {
        $scope = trim((string) $scope);
        if ($scope !== '') {
            $set[$scope] = true;
        }
    }
    foreach ($requiredScopes as $required) {
        if (!isset($set[$required])) {
            return false;
        }
    }
    return true;
}

function clickfix_authenticate_api_key(PDO $pdo, string $apiKey, array $requiredScopes = []): ?array
{
    $apiKey = trim($apiKey);
    if ($apiKey === '' || !clickfix_has_table($pdo, 'api_user_keys')) {
        return null;
    }
    $hash = clickfix_platform_api_key_hash($apiKey);
    if ($hash === '') {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT k.id, k.user_id, k.scopes, k.max_rpm, k.last_used_at, k.expires_at, k.revoked_at, u.role, u.verified
         FROM api_user_keys k
         JOIN users u ON u.id = k.user_id
         WHERE k.key_hash = :key_hash
         LIMIT 1'
    );
    $stmt->execute([':key_hash' => $hash]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    if ((int) ($row['verified'] ?? 0) !== 1) {
        return null;
    }
    if (trim((string) ($row['revoked_at'] ?? '')) !== '') {
        return null;
    }
    $expiresAt = trim((string) ($row['expires_at'] ?? ''));
    if ($expiresAt !== '') {
        $expiresTs = strtotime($expiresAt);
        if ($expiresTs !== false && $expiresTs < time()) {
            return null;
        }
    }

    $scopeText = clickfix_platform_api_scope_text((string) ($row['scopes'] ?? 'intel:read'));
    $claims = [
        'sub' => (int) ($row['id'] ?? 0),
        'user_id' => (int) ($row['user_id'] ?? 0),
        'user_role' => clickfix_normalize_role((string) ($row['role'] ?? 'analyst_jr')),
        'tier' => 'user',
        'rpm' => max(30, min(2000, (int) ($row['max_rpm'] ?? 120))),
        'scope' => $scopeText,
        'auth' => 'api_key',
        'key_id' => (int) ($row['id'] ?? 0),
        'rate_bucket' => 'key:' . (int) ($row['id'] ?? 0),
    ];
    if (!clickfix_token_has_scopes($claims, $requiredScopes)) {
        return null;
    }

    $lastUsedAt = trim((string) ($row['last_used_at'] ?? ''));
    $lastUsedTs = $lastUsedAt !== '' ? strtotime($lastUsedAt) : false;
    if ($lastUsedTs === false || (time() - $lastUsedTs) >= 120) {
        $touch = $pdo->prepare(
            'UPDATE api_user_keys
             SET last_used_at = :last_used_at, last_ip = :last_ip, updated_at = :updated_at
             WHERE id = :id'
        );
        $touch->execute([
            ':last_used_at' => gmdate('c'),
            ':last_ip' => substr(clickfix_client_ip(), 0, 80),
            ':updated_at' => gmdate('c'),
            ':id' => (int) ($row['id'] ?? 0),
        ]);
    }

    return $claims;
}

function clickfix_authenticate_api_request(PDO $pdo, array $requiredScopes = []): ?array
{
    $token = clickfix_bearer_token();
    if ($token !== '') {
        $claims = clickfix_jwt_verify($token);
        if (!is_array($claims)) {
            return null;
        }
        if (!clickfix_token_has_scopes($claims, $requiredScopes)) {
            return null;
        }
        $clientId = (int) ($claims['sub'] ?? 0);
        if ($clientId <= 0) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id, active, tier, max_rpm FROM api_clients WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $clientId]);
        $client = $stmt->fetch();
        if (!$client || (int) ($client['active'] ?? 0) !== 1) {
            return null;
        }
        $claims['sub'] = $clientId;
        $claims['tier'] = (string) ($client['tier'] ?? ($claims['tier'] ?? 'basic'));
        $claims['rpm'] = (int) ($client['max_rpm'] ?? ($claims['rpm'] ?? 120));
        $claims['auth'] = 'bearer';
        return $claims;
    }

    $apiKey = clickfix_api_key_token();
    if ($apiKey !== '') {
        return clickfix_authenticate_api_key($pdo, $apiKey, $requiredScopes);
    }

    return null;
}

function clickfix_redact_sensitive_text(string $input): string
{
    if ($input === '') {
        return '';
    }
    $value = $input;
    $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[REDACTED_EMAIL]', $value) ?? $value;
    $value = preg_replace(
        '/((?:["\']?\b(?:password|passwd|pwd|pass|contrasena|clave)\b["\']?)\s*[:=]\s*)(?:"[^"]*"|\'[^\']*\'|[^\s,;|]+)/iu',
        '$1[REDACTED_PASSWORD]',
        $value
    ) ?? $value;
    $value = preg_replace(
        '/((?:["\']?\b(?:username|usuario|user|login|nick|nickname)\b["\']?)\s*[:=]\s*)(?:"[^"]*"|\'[^\']*\'|[^\s,;|]+)/iu',
        '$1[REDACTED_USERNAME]',
        $value
    ) ?? $value;
    $value = preg_replace('/(?<![A-Za-z0-9])(?:\+?\d[\d().\s-]{7,}\d)(?![A-Za-z0-9])/u', '[REDACTED_PHONE]', $value) ?? $value;
    return $value;
}

function clickfix_redact_sensitive_recursive($value)
{
    if (is_string($value)) {
        return clickfix_redact_sensitive_text($value);
    }
    if (is_array($value)) {
        $next = [];
        foreach ($value as $key => $item) {
            $next[$key] = clickfix_redact_sensitive_recursive($item);
        }
        return $next;
    }
    return $value;
}

function clickfix_api_parse_since(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $ts = strtotime($value);
    if ($ts === false || $ts <= 0) {
        return '';
    }
    return gmdate('c', $ts);
}

function clickfix_api_normalize_review_filter(string $value): string
{
    $value = strtolower(trim($value));
    if (in_array($value, ['pending', 'accepted', 'rejected', 'allowlisted'], true)) {
        return $value;
    }
    return 'all';
}

function clickfix_api_normalize_report_status(string $value): string
{
    $value = strtolower(trim($value));
    if (in_array($value, ['accepted', 'rejected', 'allowlisted'], true)) {
        return $value;
    }
    return 'pending';
}

function clickfix_api_normalize_blocked_filter(string $value): string
{
    $value = strtolower(trim($value));
    if ($value === '1' || $value === 'true' || $value === 'yes') {
        return '1';
    }
    if ($value === '0' || $value === 'false' || $value === 'no') {
        return '0';
    }
    return 'all';
}

function clickfix_api_fetch_alert_rows(
    PDO $pdo,
    int $limit = 120,
    int $sinceId = 0,
    string $sinceIso = '',
    string $reviewFilter = 'all',
    string $blockedFilter = 'all'
): array {
    $limit = max(1, min(1000, $limit));
    $sinceId = max(0, $sinceId);
    $sinceIso = clickfix_api_parse_since($sinceIso);
    $reviewFilter = clickfix_api_normalize_review_filter($reviewFilter);
    $blockedFilter = clickfix_api_normalize_blocked_filter($blockedFilter);

    $sql = 'SELECT id, received_at, last_seen, hostname, url, previous_url, message, blocked, review_status, duplicate_count, score_total, score_details_json, country, detected_content, full_context, signals_json, reason_entries_json, matched_snippets_json, event_type, user_agent, ip, client_id
            FROM reports
            WHERE 1=1';
    $params = [];
    if ($sinceId > 0) {
        $sql .= ' AND id > :since_id';
        $params[':since_id'] = $sinceId;
    }
    if ($sinceIso !== '') {
        $sql .= " AND COALESCE(NULLIF(last_seen, ''), received_at) >= :since_iso";
        $params[':since_iso'] = $sinceIso;
    }
    if ($reviewFilter === 'pending') {
        $sql .= " AND (review_status IS NULL OR TRIM(review_status) = '' OR LOWER(TRIM(review_status)) = 'pending')";
    } elseif ($reviewFilter === 'accepted') {
        $sql .= " AND LOWER(TRIM(COALESCE(review_status, ''))) = 'accepted'";
    } elseif ($reviewFilter === 'rejected') {
        $sql .= " AND LOWER(TRIM(COALESCE(review_status, ''))) = 'rejected'";
    } elseif ($reviewFilter === 'allowlisted') {
        $sql .= " AND LOWER(TRIM(COALESCE(review_status, ''))) = 'allowlisted'";
    }
    if ($blockedFilter === '0' || $blockedFilter === '1') {
        $sql .= ' AND blocked = :blocked';
        $params[':blocked'] = (int) $blockedFilter;
    }
    $sql .= ' ORDER BY id DESC LIMIT :limit';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, (string) $value, PDO::PARAM_STR);
        }
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return clickfix_enrich_report_rows($stmt->fetchAll());
}

function clickfix_api_shape_alert_row(array $row, bool $includeContext = false): array
{
    $reviewStatus = clickfix_api_normalize_report_status((string) ($row['review_status'] ?? 'pending'));
    $activityAt = trim((string) ($row['last_seen'] ?? ''));
    if ($activityAt === '') {
        $activityAt = (string) ($row['received_at'] ?? '');
    }
    $payload = [
        'id' => (int) ($row['id'] ?? 0),
        'received_at' => (string) ($row['received_at'] ?? ''),
        'last_seen' => (string) ($row['last_seen'] ?? ''),
        'activity_at' => $activityAt,
        'hostname' => clickfix_normalize_domain((string) ($row['hostname'] ?? '')),
        'url' => (string) ($row['url'] ?? ''),
        'previous_url' => (string) ($row['previous_url'] ?? ''),
        'ip' => trim((string) ($row['ip'] ?? '')),
        'country' => (string) ($row['country'] ?? ''),
        'blocked' => !empty($row['blocked']),
        'review_status' => $reviewStatus,
        'event_type' => (string) ($row['event_type'] ?? ''),
        'duplicate_count' => (int) ($row['duplicate_count'] ?? 1),
        'score_total' => (float) ($row['score_total'] ?? 0.0),
        'extension_version' => (string) ($row['extension_version'] ?? ''),
        'message' => clickfix_redact_sensitive_text((string) ($row['message'] ?? '')),
        'reason_entries' => clickfix_redact_sensitive_recursive(is_array($row['reason_entries'] ?? null) ? $row['reason_entries'] : []),
        'signals' => clickfix_redact_sensitive_recursive(is_array($row['signals'] ?? null) ? $row['signals'] : []),
        'matched_snippets' => clickfix_redact_sensitive_recursive(is_array($row['matched_snippets'] ?? null) ? $row['matched_snippets'] : []),
    ];
    if ($includeContext) {
        $payload['detected_content'] = clickfix_redact_sensitive_text((string) ($row['detected_content'] ?? ''));
        $payload['full_context'] = clickfix_redact_sensitive_text((string) ($row['full_context'] ?? ''));
    }
    return $payload;
}

function clickfix_api_normalize_ioc_type_filter(string $typeFilter): string
{
    $typeFilter = strtolower(trim($typeFilter));
    if (in_array($typeFilter, ['domain', 'ip', 'url'], true)) {
        return $typeFilter;
    }
    return 'all';
}

function clickfix_api_build_ioc_feed(array $alertRows, string $typeFilter = 'all', int $limit = 300): array
{
    $typeFilter = clickfix_api_normalize_ioc_type_filter($typeFilter);
    $limit = max(1, min(2000, $limit));
    $map = [];

    foreach ($alertRows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $reportId = (int) ($row['id'] ?? 0);
        if ($reportId <= 0) {
            continue;
        }
        $reviewStatus = clickfix_api_normalize_report_status((string) ($row['review_status'] ?? 'pending'));
        $activityAt = trim((string) ($row['last_seen'] ?? ''));
        if ($activityAt === '') {
            $activityAt = (string) ($row['received_at'] ?? '');
        }
        $activityTs = strtotime($activityAt) ?: 0;
        $receivedAt = (string) ($row['received_at'] ?? '');
        $receivedTs = strtotime($receivedAt) ?: $activityTs;
        $blocked = !empty($row['blocked']);

        $candidates = [];
        if ($typeFilter === 'all' || $typeFilter === 'domain') {
            $domain = clickfix_normalize_domain((string) ($row['hostname'] ?? ''));
            if ($domain !== '') {
                $candidates[] = ['type' => 'domain', 'value' => $domain];
            }
        }
        if ($typeFilter === 'all' || $typeFilter === 'ip') {
            $ip = trim((string) ($row['ip'] ?? ''));
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                $candidates[] = ['type' => 'ip', 'value' => $ip];
            }
        }
        if ($typeFilter === 'all' || $typeFilter === 'url') {
            $url = trim((string) ($row['url'] ?? ''));
            if ($url !== '' && preg_match('/^https?:\/\//i', $url)) {
                $candidates[] = ['type' => 'url', 'value' => substr($url, 0, 1000)];
            }
        }

        foreach ($candidates as $candidate) {
            $key = strtolower((string) $candidate['type']) . '|' . trim((string) $candidate['value']);
            if (!isset($map[$key])) {
                $map[$key] = [
                    'type' => (string) $candidate['type'],
                    'value' => (string) $candidate['value'],
                    'first_seen' => $receivedAt !== '' ? $receivedAt : $activityAt,
                    'last_seen' => $activityAt,
                    'first_seen_ts' => $receivedTs,
                    'last_seen_ts' => $activityTs,
                    'reports' => 0,
                    'blocked_hits' => 0,
                    'status_counts' => ['pending' => 0, 'accepted' => 0, 'rejected' => 0, 'allowlisted' => 0],
                    'sample' => [
                        'report_id' => $reportId,
                        'hostname' => clickfix_normalize_domain((string) ($row['hostname'] ?? '')),
                        'url' => (string) ($row['url'] ?? ''),
                        'message' => clickfix_redact_sensitive_text((string) ($row['message'] ?? '')),
                    ],
                ];
            }
            $entry = &$map[$key];
            $entry['reports']++;
            if ($blocked) {
                $entry['blocked_hits']++;
            }
            $entry['status_counts'][$reviewStatus] = (int) ($entry['status_counts'][$reviewStatus] ?? 0) + 1;
            if ($activityTs > (int) ($entry['last_seen_ts'] ?? 0)) {
                $entry['last_seen_ts'] = $activityTs;
                $entry['last_seen'] = $activityAt;
            }
            if ($receivedTs > 0 && ((int) ($entry['first_seen_ts'] ?? 0) === 0 || $receivedTs < (int) $entry['first_seen_ts'])) {
                $entry['first_seen_ts'] = $receivedTs;
                $entry['first_seen'] = $receivedAt !== '' ? $receivedAt : $activityAt;
            }
            unset($entry);
        }
    }

    $items = [];
    foreach ($map as $entry) {
        $reports = max(1, (int) ($entry['reports'] ?? 1));
        $blockedHits = (int) ($entry['blocked_hits'] ?? 0);
        $acceptedHits = (int) (($entry['status_counts']['accepted'] ?? 0));
        $confidence = min(100, max(5, (int) round(($blockedHits * 100.0 / $reports) * 0.65 + ($acceptedHits * 100.0 / $reports) * 0.35)));
        $entry['confidence'] = $confidence;
        $entry['tags'] = ['clickfix', 'ioc'];
        if ($blockedHits > 0) {
            $entry['tags'][] = 'blocked';
        }
        if ($acceptedHits > 0) {
            $entry['tags'][] = 'accepted';
        }
        unset($entry['first_seen_ts'], $entry['last_seen_ts']);
        $items[] = $entry;
    }

    usort($items, static function (array $a, array $b): int {
        $aTs = strtotime((string) ($a['last_seen'] ?? '')) ?: 0;
        $bTs = strtotime((string) ($b['last_seen'] ?? '')) ?: 0;
        if ($aTs === $bTs) {
            return ((int) ($b['reports'] ?? 0)) <=> ((int) ($a['reports'] ?? 0));
        }
        return $bTs <=> $aTs;
    });

    return array_slice($items, 0, $limit);
}

function clickfix_uuid_from_hash(string $hash): string
{
    $hex = strtolower(preg_replace('/[^a-f0-9]/', '', $hash) ?? '');
    $hex = substr(str_pad($hex, 32, '0'), 0, 32);
    if ($hex === '') {
        $hex = str_repeat('0', 32);
    }
    $hex[12] = '4';
    $variant = hexdec($hex[16]);
    $variant = ($variant & 0x3) | 0x8;
    $hex[16] = dechex($variant);
    return substr($hex, 0, 8) . '-' .
        substr($hex, 8, 4) . '-' .
        substr($hex, 12, 4) . '-' .
        substr($hex, 16, 4) . '-' .
        substr($hex, 20, 12);
}

function clickfix_stix_escape_value(string $value): string
{
    return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
}

function clickfix_api_iocs_to_stix_bundle(array $iocs, string $sourceName = 'ClickFix Mitigator'): array
{
    $now = gmdate('c');
    $identityId = 'identity--' . clickfix_uuid_from_hash(hash('sha256', 'identity|' . $sourceName));
    $objects = [[
        'type' => 'identity',
        'spec_version' => '2.1',
        'id' => $identityId,
        'created' => $now,
        'modified' => $now,
        'name' => $sourceName,
        'identity_class' => 'organization',
    ]];

    foreach ($iocs as $ioc) {
        if (!is_array($ioc)) {
            continue;
        }
        $type = strtolower(trim((string) ($ioc['type'] ?? '')));
        $value = trim((string) ($ioc['value'] ?? ''));
        if ($value === '' || !in_array($type, ['domain', 'ip', 'url'], true)) {
            continue;
        }
        if ($type === 'domain') {
            $pattern = "[domain-name:value = '" . clickfix_stix_escape_value($value) . "']";
        } elseif ($type === 'ip') {
            $objectType = filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 'ipv6-addr' : 'ipv4-addr';
            $pattern = "[" . $objectType . ":value = '" . clickfix_stix_escape_value($value) . "']";
        } else {
            $pattern = "[url:value = '" . clickfix_stix_escape_value($value) . "']";
        }
        $created = trim((string) ($ioc['first_seen'] ?? $now));
        $modified = trim((string) ($ioc['last_seen'] ?? $created));
        if ($created === '') {
            $created = $now;
        }
        if ($modified === '') {
            $modified = $created;
        }
        $indicatorId = 'indicator--' . clickfix_uuid_from_hash(hash('sha256', $type . '|' . strtolower($value)));
        $labels = ['malicious-activity', 'clickfix'];
        foreach ((array) ($ioc['tags'] ?? []) as $tag) {
            $tagText = strtolower(trim((string) $tag));
            if ($tagText !== '' && !in_array($tagText, $labels, true)) {
                $labels[] = substr($tagText, 0, 40);
            }
        }
        $objects[] = [
            'type' => 'indicator',
            'spec_version' => '2.1',
            'id' => $indicatorId,
            'created_by_ref' => $identityId,
            'created' => $created,
            'modified' => $modified,
            'name' => 'ClickFix IOC: ' . $value,
            'description' => 'IOC exported from ClickFix Mitigator',
            'indicator_types' => ['malicious-activity'],
            'pattern' => $pattern,
            'pattern_type' => 'stix',
            'pattern_version' => '2.1',
            'valid_from' => $created,
            'confidence' => (int) ($ioc['confidence'] ?? 0),
            'labels' => $labels,
            'x_clickfix_reports' => (int) ($ioc['reports'] ?? 0),
        ];
    }

    $bundleSeed = $now . '|bundle|' . count($objects) . '|' . substr(hash('sha256', json_encode($objects, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''), 0, 24);
    return [
        'type' => 'bundle',
        'id' => 'bundle--' . clickfix_uuid_from_hash(hash('sha256', $bundleSeed)),
        'spec_version' => '2.1',
        'objects' => $objects,
    ];
}

function clickfix_api_fetch_investigation_events_feed(
    PDO $pdo,
    int $limit = 150,
    int $sinceId = 0,
    int $graphId = 0
): array {
    if (!clickfix_has_table($pdo, 'investigation_events')) {
        return [];
    }
    $limit = max(1, min(1000, $limit));
    $sinceId = max(0, $sinceId);
    $graphId = max(0, $graphId);

    $sql = 'SELECT ie.id, ie.created_at, ie.graph_id, ie.user_id, ie.action, ie.details_json, u.username, ig.title, ig.site_domain
            FROM investigation_events ie
            LEFT JOIN users u ON u.id = ie.user_id
            LEFT JOIN investigation_graphs ig ON ig.id = ie.graph_id
            WHERE 1=1';
    $params = [];
    if ($sinceId > 0) {
        $sql .= ' AND ie.id > :since_id';
        $params[':since_id'] = $sinceId;
    }
    if ($graphId > 0) {
        $sql .= ' AND ie.graph_id = :graph_id';
        $params[':graph_id'] = $graphId;
    }
    $sql .= ' ORDER BY ie.id DESC LIMIT :limit';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, (int) $value, PDO::PARAM_INT);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $row) {
        $details = json_decode((string) ($row['details_json'] ?? '{}'), true);
        $result[] = [
            'id' => (int) ($row['id'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'graph_id' => (int) ($row['graph_id'] ?? 0),
            'graph_title' => (string) ($row['title'] ?? ''),
            'site_domain' => clickfix_normalize_domain((string) ($row['site_domain'] ?? '')),
            'action' => (string) ($row['action'] ?? ''),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'username' => (string) ($row['username'] ?? ''),
            'details' => clickfix_redact_sensitive_recursive(is_array($details) ? $details : []),
        ];
    }
    return $result;
}

function clickfix_api_lookup_indicator(PDO $pdo, string $indicator, int $limit = 30): array
{
    $limit = max(1, min(120, $limit));
    $indicator = trim($indicator);
    if ($indicator === '') {
        return [];
    }

    $type = 'unknown';
    $normalized = $indicator;
    if (filter_var($indicator, FILTER_VALIDATE_IP)) {
        $type = 'ip';
    } elseif ((bool) preg_match('/^https?:\/\//i', $indicator)) {
        $type = 'url';
    } else {
        $domain = clickfix_normalize_domain($indicator);
        if ($domain !== '') {
            $type = 'domain';
            $normalized = $domain;
        }
    }
    if ($type === 'unknown') {
        return [];
    }

    $where = '';
    $params = [];
    if ($type === 'domain') {
        $where = "LOWER(TRIM(COALESCE(hostname, ''))) = :indicator";
        $params[':indicator'] = strtolower($normalized);
    } elseif ($type === 'ip') {
        $where = "TRIM(COALESCE(ip, '')) = :indicator";
        $params[':indicator'] = $normalized;
    } else {
        $where = "(TRIM(COALESCE(url, '')) = :indicator OR TRIM(COALESCE(previous_url, '')) = :indicator)";
        $params[':indicator'] = $normalized;
    }

    $statsStmt = $pdo->prepare(
        "SELECT COUNT(*) AS total_reports,
                MIN(received_at) AS first_seen,
                MAX(COALESCE(NULLIF(last_seen, ''), received_at)) AS last_seen,
                SUM(CASE WHEN blocked = 1 THEN 1 ELSE 0 END) AS blocked_hits,
                SUM(CASE WHEN review_status = 'accepted' THEN 1 ELSE 0 END) AS accepted_hits,
                SUM(CASE WHEN review_status = 'rejected' THEN 1 ELSE 0 END) AS rejected_hits,
                SUM(CASE WHEN review_status IS NULL OR TRIM(review_status) = '' OR LOWER(TRIM(review_status)) = 'pending' THEN 1 ELSE 0 END) AS pending_hits
         FROM reports
         WHERE {$where}"
    );
    foreach ($params as $key => $value) {
        $statsStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $statsStmt->execute();
    $stats = $statsStmt->fetch() ?: [];

    $rowStmt = $pdo->prepare(
        "SELECT id, received_at, last_seen, hostname, url, previous_url, message, blocked, review_status, duplicate_count, score_total, score_details_json, country, detected_content, full_context, signals_json, reason_entries_json, matched_snippets_json, event_type, user_agent, ip, client_id
         FROM reports
         WHERE {$where}
         ORDER BY id DESC
         LIMIT :limit"
    );
    foreach ($params as $key => $value) {
        $rowStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    $rowStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $rowStmt->execute();
    $rows = clickfix_enrich_report_rows($rowStmt->fetchAll());
    $recentAlerts = [];
    foreach ($rows as $row) {
        $recentAlerts[] = clickfix_api_shape_alert_row($row, false);
    }

    $listMembership = [
        'allowlist' => false,
        'blocklist' => false,
        'alertlist' => false,
        'investigatelist' => false,
    ];
    if ($type === 'domain') {
        foreach (array_keys($listMembership) as $listType) {
            $listMembership[$listType] = clickfix_domain_in_list($normalized, clickfix_load_list_file($listType));
        }
    }

    $relatedInvestigations = [];
    if ($type === 'domain' && clickfix_has_table($pdo, 'investigation_graphs')) {
        $invStmt = $pdo->prepare(
            "SELECT id, title, site_domain, verdict, workflow_status, updated_at, is_public, share_token
             FROM investigation_graphs
             WHERE deleted = 0 AND LOWER(TRIM(COALESCE(site_domain, ''))) = :domain
             ORDER BY id DESC
             LIMIT 20"
        );
        $invStmt->execute([':domain' => strtolower($normalized)]);
        foreach ($invStmt->fetchAll() as $invRow) {
            $relatedInvestigations[] = [
                'id' => (int) ($invRow['id'] ?? 0),
                'title' => (string) ($invRow['title'] ?? ''),
                'site_domain' => clickfix_normalize_domain((string) ($invRow['site_domain'] ?? '')),
                'verdict' => (string) ($invRow['verdict'] ?? 'unknown'),
                'workflow_status' => (string) ($invRow['workflow_status'] ?? 'draft'),
                'updated_at' => (string) ($invRow['updated_at'] ?? ''),
                'is_public' => !empty($invRow['is_public']),
                'share_token' => !empty($invRow['is_public']) ? (string) ($invRow['share_token'] ?? '') : '',
            ];
        }
    }

    return [
        'indicator' => $indicator,
        'normalized' => $normalized,
        'type' => $type,
        'already_reported' => ((int) ($stats['total_reports'] ?? 0)) > 0,
        'stats' => [
            'total_reports' => (int) ($stats['total_reports'] ?? 0),
            'first_seen' => (string) ($stats['first_seen'] ?? ''),
            'last_seen' => (string) ($stats['last_seen'] ?? ''),
            'blocked_hits' => (int) ($stats['blocked_hits'] ?? 0),
            'accepted_hits' => (int) ($stats['accepted_hits'] ?? 0),
            'rejected_hits' => (int) ($stats['rejected_hits'] ?? 0),
            'pending_hits' => (int) ($stats['pending_hits'] ?? 0),
        ],
        'list_membership' => $listMembership,
        'recent_alerts' => $recentAlerts,
        'related_investigations' => $relatedInvestigations,
    ];
}

function clickfix_sort_recursive($value)
{
    if (!is_array($value)) {
        return $value;
    }
    $isList = array_keys($value) === range(0, count($value) - 1);
    if ($isList) {
        return array_map('clickfix_sort_recursive', $value);
    }
    ksort($value);
    $sorted = [];
    foreach ($value as $key => $item) {
        $sorted[$key] = clickfix_sort_recursive($item);
    }
    return $sorted;
}

function clickfix_canonical_json(array $payload): string
{
    $sorted = clickfix_sort_recursive($payload);
    return (string) json_encode($sorted, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function clickfix_sign_payload(string $payload): ?array
{
    $configured = trim((string) clickfix_env('CLICKFIX_SIGN_PRIVATE_KEY', ''));
    if ($configured === '') {
        return null;
    }
    $privatePem = $configured;
    $privatePath = clickfix_resolve_env_path($configured);
    if (is_file($privatePath) && is_readable($privatePath)) {
        $privatePem = (string) file_get_contents($privatePath);
    }
    if ($privatePem === '') {
        return null;
    }
    $key = openssl_pkey_get_private($privatePem);
    if ($key === false) {
        return null;
    }
    $ok = openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256);
    openssl_pkey_free($key);
    if (!$ok || !is_string($signature) || $signature === '') {
        return null;
    }
    return [
        'algorithm' => 'RSASSA-PKCS1-v1_5-SHA256',
        'key_id' => trim((string) clickfix_env('CLICKFIX_SIGN_KEY_ID', 'default')),
        'signature' => base64_encode($signature),
    ];
}

function clickfix_http_fetch(string $url, array $options = []): ?string
{
    $method = strtoupper((string) ($options['method'] ?? 'GET'));
    $body = $options['body'] ?? null;
    $headers = is_array($options['headers'] ?? null) ? $options['headers'] : [];
    $timeout = max(3, min(30, (int) ($options['timeout'] ?? 15)));
    $headerLines = [];
    foreach ($headers as $k => $v) {
        if ($v !== '') { $headerLines[] = "{$k}: {$v}"; }
    }
    $opts = [
        'http' => [
            'method' => $method,
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headerLines) . "\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ];
    if ($body !== null) {
        $opts['http']['content'] = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);
    return is_string($raw) ? $raw : null;
}

function clickfix_http_fetch_json(string $url, array $options = []): ?array
{
    $response = clickfix_http_fetch($url, $options);
    if ($response === null) { return null; }
    return json_decode($response, true) ?: null;
}
