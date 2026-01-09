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
    country TEXT
);

CREATE TABLE IF NOT EXISTS stats (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    received_at TEXT NOT NULL,
    enabled INTEGER,
    alert_count INTEGER,
    block_count INTEGER,
    manual_sites_json TEXT,
    country TEXT
);
