CREATE TABLE IF NOT EXISTS square_checkouts (
    purchase_id INTEGER PRIMARY KEY REFERENCES package_purchases(id),
    environment TEXT NOT NULL,
    merchant_id TEXT NOT NULL,
    location_id TEXT NOT NULL,
    idempotency_key TEXT NOT NULL UNIQUE,
    request_json TEXT NOT NULL,
    order_id TEXT UNIQUE,
    link_id TEXT UNIQUE,
    checkout_url TEXT,
    payment_id TEXT UNIQUE,
    status TEXT NOT NULL DEFAULT 'creating',
    review_reason TEXT,
    refunded_cents INTEGER NOT NULL DEFAULT 0,
    checked_at TEXT,
    created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS square_events (
    event_id TEXT PRIMARY KEY,
    event_type TEXT NOT NULL,
    processed_at TEXT NOT NULL
);
