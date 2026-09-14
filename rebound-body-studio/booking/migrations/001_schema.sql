-- Rebound Body Studio - booking engine schema
--
-- Portable between SQLite (local development) and MySQL 5.7+ (shared hosting,
-- which is where this will actually run). That rules out a few conveniences:
-- no ENUM, no JSON columns, no partial indexes, and every timestamp is stored
-- as a plain string.
--
-- ALL TIMES ARE STORED AS UTC, 'YYYY-MM-DD HH:MM:SS'.
-- The studio is in Arizona, which does not observe daylight saving, so the
-- offset is a constant -07:00 all year. That removes the single largest source
-- of bugs in booking software: a slot that exists twice, or not at all, on a
-- clock-change morning. Display conversion happens in one place (Clock.php);
-- nothing else is allowed to do timezone arithmetic.

CREATE TABLE services (
    id            INTEGER PRIMARY KEY,
    slug          VARCHAR(64)  NOT NULL UNIQUE,
    name          VARCHAR(128) NOT NULL,
    duration_min  INTEGER      NOT NULL,
    price_cents   INTEGER      NOT NULL,
    sort_order    INTEGER      NOT NULL DEFAULT 0,
    active        INTEGER      NOT NULL DEFAULT 1
);

-- Wendy is a staff member with bookable = 0: she appears on the website and in
-- the studio, but she rents the room and takes her own bookings offline. This
-- is exactly what MassageBook wanted 75 dollars a month to express, and here it
-- is one column.
CREATE TABLE staff (
    id            INTEGER PRIMARY KEY,
    name          VARCHAR(128) NOT NULL,
    credentials   VARCHAR(255) NOT NULL DEFAULT '',
    bio           TEXT         NOT NULL DEFAULT '',
    photo_url     VARCHAR(255) NOT NULL DEFAULT '',
    bookable      INTEGER      NOT NULL DEFAULT 0,
    active        INTEGER      NOT NULL DEFAULT 1,
    sort_order    INTEGER      NOT NULL DEFAULT 0
);

-- Recurring weekly working hours, held in LOCAL time because that is how a
-- human thinks about "I work Tuesdays 9 to 5". Converted to UTC at slot
-- generation time, never stored converted.
CREATE TABLE availability_rules (
    id            INTEGER PRIMARY KEY,
    staff_id      INTEGER NOT NULL,
    weekday       INTEGER NOT NULL,          -- 0 = Sunday ... 6 = Saturday
    start_local   VARCHAR(5) NOT NULL,       -- 'HH:MM'
    end_local     VARCHAR(5) NOT NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(id)
);

-- Holidays, courses, gone-to-a-wedding. Stored UTC like every other instant.
CREATE TABLE blackouts (
    id            INTEGER PRIMARY KEY,
    staff_id      INTEGER NOT NULL,
    starts_at     VARCHAR(19) NOT NULL,
    ends_at       VARCHAR(19) NOT NULL,
    reason        VARCHAR(255) NOT NULL DEFAULT '',
    FOREIGN KEY (staff_id) REFERENCES staff(id)
);

CREATE TABLE clients (
    id                 INTEGER PRIMARY KEY,
    email              VARCHAR(255) NOT NULL UNIQUE,
    name               VARCHAR(128) NOT NULL DEFAULT '',
    phone              VARCHAR(32)  NOT NULL DEFAULT '',
    created_at         VARCHAR(19)  NOT NULL,
    -- Marketing consent is deliberately separate from having an account.
    -- New bookings use default enrollment with a visible marketing-only opt-out.
    -- Existing preferences are preserved. Transactional messages are independent.
    marketing_consent  INTEGER      NOT NULL DEFAULT 0,
    consent_at         VARCHAR(19)  NULL,
    consent_source     VARCHAR(64)  NOT NULL DEFAULT '',
    unsub_token        VARCHAR(64)  NOT NULL UNIQUE,
    unsubscribed_at    VARCHAR(19)  NULL,
    notes              TEXT         NOT NULL DEFAULT ''
);

-- status lifecycle:
--   requested -> confirmed -> completed
--   requested -> declined
--   requested | confirmed -> cancelled
--   confirmed -> no_show
-- She approves every booking, so nothing is ever created as 'confirmed'
-- directly by a client.
CREATE TABLE appointments (
    id                  INTEGER PRIMARY KEY,
    client_id           INTEGER NOT NULL,
    service_id          INTEGER NOT NULL,
    staff_id            INTEGER NOT NULL,
    starts_at           VARCHAR(19) NOT NULL,
    ends_at             VARCHAR(19) NOT NULL,
    status              VARCHAR(16) NOT NULL,
    client_note         TEXT        NOT NULL DEFAULT '',
    studio_note         TEXT        NOT NULL DEFAULT '',
    technique_pref      VARCHAR(128) NOT NULL DEFAULT '',
    created_at          VARCHAR(19) NOT NULL,
    decided_at          VARCHAR(19) NULL,
    cancelled_at        VARCHAR(19) NULL,
    cancel_reason       VARCHAR(255) NOT NULL DEFAULT '',
    package_purchase_id INTEGER NULL,
    manage_token        VARCHAR(64) NOT NULL UNIQUE,
    FOREIGN KEY (client_id)  REFERENCES clients(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (staff_id)   REFERENCES staff(id)
);

CREATE INDEX idx_appt_window ON appointments (staff_id, starts_at, ends_at);
CREATE INDEX idx_appt_status ON appointments (status, starts_at);
CREATE INDEX idx_appt_client ON appointments (client_id, starts_at);

CREATE TABLE packages (
    id            INTEGER PRIMARY KEY,
    slug          VARCHAR(64)  NOT NULL UNIQUE,
    name          VARCHAR(128) NOT NULL,
    service_id    INTEGER      NULL,   -- NULL = usable against any service
    sessions      INTEGER      NOT NULL,
    price_cents   INTEGER      NOT NULL,
    expires_days  INTEGER      NULL,   -- NULL = never expires
    active        INTEGER      NOT NULL DEFAULT 1,
    sort_order    INTEGER      NOT NULL DEFAULT 0,
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- One row per purchase, carrying its own credit balance. The balance lives
-- here rather than being recomputed from appointments so that a refund, a
-- goodwill credit or a manual adjustment is expressible without inventing a
-- fake appointment to hang it on.
CREATE TABLE package_purchases (
    id                INTEGER PRIMARY KEY,
    client_id         INTEGER NOT NULL,
    package_id        INTEGER NOT NULL,
    sessions_total    INTEGER NOT NULL,
    sessions_used     INTEGER NOT NULL DEFAULT 0,
    price_paid_cents  INTEGER NOT NULL,
    purchased_at      VARCHAR(19) NOT NULL,
    expires_at        VARCHAR(19) NULL,
    stripe_session_id VARCHAR(255) NULL UNIQUE,
    status            VARCHAR(16) NOT NULL DEFAULT 'active',  -- active|refunded|expired
    FOREIGN KEY (client_id)  REFERENCES clients(id),
    FOREIGN KEY (package_id) REFERENCES packages(id)
);

-- Every movement of a credit, so "what have I used?" is answerable and
-- auditable rather than inferred from a counter.
CREATE TABLE credit_ledger (
    id                  INTEGER PRIMARY KEY,
    package_purchase_id INTEGER NOT NULL,
    appointment_id      INTEGER NULL,
    delta               INTEGER NOT NULL,   -- -1 spend, +1 refund on cancel
    reason              VARCHAR(64) NOT NULL,
    created_at          VARCHAR(19) NOT NULL,
    FOREIGN KEY (package_purchase_id) REFERENCES package_purchases(id)
);

-- Outbound mail queue. Rows are written by the app and drained by a cron
-- worker, so a slow or failing SMTP server can never leave a client sitting on
-- a spinning form, and a transient failure is retried instead of lost.
CREATE TABLE mail_queue (
    id             INTEGER PRIMARY KEY,
    to_email       VARCHAR(255) NOT NULL,
    to_name        VARCHAR(128) NOT NULL DEFAULT '',
    subject        VARCHAR(255) NOT NULL,
    template       VARCHAR(64)  NOT NULL,
    payload_json   TEXT         NOT NULL,
    send_after     VARCHAR(19)  NOT NULL,
    sent_at        VARCHAR(19)  NULL,
    attempts       INTEGER      NOT NULL DEFAULT 0,
    last_error     VARCHAR(500) NOT NULL DEFAULT '',
    -- Lease fields used by the off-host authenticated SMTP worker. A lease
    -- prevents overlapping task runs from sending the same queue row.
    claim_token    VARCHAR(64)  NULL,
    claimed_at     VARCHAR(19)  NULL,
    -- Lets the app say "drop the reminder for appointment 12" without knowing
    -- which queue row that is.
    appointment_id INTEGER      NULL,
    kind           VARCHAR(32)  NOT NULL DEFAULT 'transactional',
    -- Guards against sending the same thing twice when a cron run overlaps
    -- itself, or a payment webhook is redelivered.
    dedupe_key     VARCHAR(128) NULL UNIQUE
);

CREATE INDEX idx_mail_pending ON mail_queue (sent_at, send_after);

CREATE TABLE settings (
    key_name   VARCHAR(64) PRIMARY KEY,
    value_text TEXT NOT NULL
);

-- Passwordless sign-in: a short-lived single-use token emailed to the client.
-- No password is ever stored, so none can be leaked here or reused elsewhere.
CREATE TABLE login_tokens (
    id         INTEGER PRIMARY KEY,
    client_id  INTEGER NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    expires_at VARCHAR(19) NOT NULL,
    used_at    VARCHAR(19) NULL,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);
