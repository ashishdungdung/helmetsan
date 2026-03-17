-- schema.sql
-- Run this with: wrangler d1 execute helmetsan-analytics --file=./schema.sql

DROP TABLE IF EXISTS analytics_events;

CREATE TABLE analytics_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_name TEXT NOT NULL,
    page_url TEXT,
    referrer TEXT,
    source TEXT DEFAULT 'frontend',
    meta_json TEXT,
    ip_hash TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_event_name ON analytics_events(event_name);
CREATE INDEX idx_created_at ON analytics_events(created_at);
