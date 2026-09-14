CREATE TABLE IF NOT EXISTS package_services (
    package_id INTEGER NOT NULL REFERENCES packages(id),
    service_id INTEGER NOT NULL REFERENCES services(id),
    PRIMARY KEY(package_id, service_id)
);
CREATE TABLE IF NOT EXISTS package_orders (
    purchase_id INTEGER PRIMARY KEY REFERENCES package_purchases(id),
    request_hash TEXT NOT NULL UNIQUE,
    quoted_price_cents INTEGER NOT NULL,
    package_name TEXT NOT NULL,
    duration_min INTEGER NOT NULL,
    paid_at TEXT,
    payment_method TEXT,
    payment_reference TEXT UNIQUE
);
CREATE TABLE IF NOT EXISTS package_access (
    token_hash TEXT PRIMARY KEY,
    purchase_id INTEGER NOT NULL REFERENCES package_purchases(id),
    expires_at TEXT
);
CREATE TABLE IF NOT EXISTS package_adjustments (
    request_hash TEXT PRIMARY KEY,
    purchase_id INTEGER NOT NULL REFERENCES package_purchases(id),
    delta INTEGER NOT NULL,
    note TEXT NOT NULL,
    created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS package_rate_limits (
    bucket TEXT PRIMARY KEY,
    attempts INTEGER NOT NULL,
    expires_at TEXT NOT NULL
);
