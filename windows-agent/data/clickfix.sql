CREATE TABLE IF NOT EXISTS reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    url TEXT,
    hostname TEXT,
    message TEXT,
    detected_content TEXT,
    full_context TEXT,
    signals_json TEXT,
    blocked INTEGER DEFAULT 0,
    user_agent TEXT,
    ip TEXT,
    country TEXT,
    active_process TEXT,
    active_window TEXT,
    action_taken TEXT,
    host_snapshot_json TEXT
);

CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    enabled INTEGER,
    alert_count INTEGER,
    block_count INTEGER,
    manual_sites_json TEXT,
    country TEXT,
    host_health TEXT
);

CREATE TABLE IF NOT EXISTS host_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    recorded_at TEXT NOT NULL,
    hostname TEXT,
    cpu_percent REAL,
    memory_percent REAL,
    dns_json TEXT,
    antivirus_json TEXT,
    processes_json TEXT
);
