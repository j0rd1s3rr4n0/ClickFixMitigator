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
    email TEXT,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL,
    preferred_lang TEXT DEFAULT 'en',
    reputation INTEGER DEFAULT 0,
    full_name TEXT,
    profile_email_public INTEGER DEFAULT 0,
    profile_vt_public INTEGER DEFAULT 0,
    profile_vt_handle TEXT,
    profile_threatrip_public INTEGER DEFAULT 0,
    profile_threatrip_id TEXT,
    profile_abuseipdb_public INTEGER DEFAULT 0,
    profile_abuseipdb_id TEXT,
    profile_github_public INTEGER DEFAULT 0,
    profile_github_handle TEXT
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
    deleted INTEGER DEFAULT 0,
    submitted_to_community INTEGER DEFAULT 0,
    workflow_status TEXT DEFAULT 'draft',
    community_origin_role TEXT,
    verified_by INTEGER,
    verified_at TEXT,
    verification_note TEXT
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

CREATE TABLE IF NOT EXISTS investigation_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    graph_id INTEGER NOT NULL,
    user_id INTEGER,
    action TEXT NOT NULL,
    details_json TEXT
);

CREATE INDEX IF NOT EXISTS idx_investigation_events_graph ON investigation_events(graph_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_investigation_events_user ON investigation_events(user_id, created_at DESC);

CREATE TABLE IF NOT EXISTS investigation_votes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    graph_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    vote INTEGER NOT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_investigation_votes_graph_user ON investigation_votes(graph_id, user_id);
CREATE INDEX IF NOT EXISTS idx_investigation_votes_graph ON investigation_votes(graph_id, vote);

CREATE TABLE IF NOT EXISTS user_reputation_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    created_at TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    delta INTEGER NOT NULL,
    reason TEXT NOT NULL,
    context_graph_id INTEGER,
    created_by INTEGER
);

CREATE INDEX IF NOT EXISTS idx_user_rep_events_user ON user_reputation_events(user_id, created_at DESC);

CREATE TABLE IF NOT EXISTS geo_country_cache (
    country_code TEXT PRIMARY KEY,
    country_name TEXT,
    latitude REAL,
    longitude REAL,
    languages_json TEXT,
    updated_at TEXT NOT NULL
);

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

CREATE INDEX IF NOT EXISTS idx_domain_intel_country ON domain_intel_cache(country_code);
CREATE INDEX IF NOT EXISTS idx_domain_intel_checked ON domain_intel_cache(checked_at);

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

CREATE INDEX IF NOT EXISTS idx_whatweb_cache_checked ON whatweb_cache(checked_at);

CREATE TABLE IF NOT EXISTS ml_keyword_enrichment_cache (
    url TEXT PRIMARY KEY,
    checked_at TEXT NOT NULL,
    keyword_hits_json TEXT,
    resource_count INTEGER DEFAULT 0,
    status TEXT
);

CREATE INDEX IF NOT EXISTS idx_ml_keyword_enrichment_checked ON ml_keyword_enrichment_cache(checked_at);
