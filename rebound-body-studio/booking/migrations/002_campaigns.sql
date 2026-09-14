CREATE TABLE IF NOT EXISTS campaigns (
    id INTEGER PRIMARY KEY,
    request_key VARCHAR(64) NOT NULL UNIQUE,
    subject VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    created_at VARCHAR(19) NOT NULL,
    recipient_count INTEGER NOT NULL
);
