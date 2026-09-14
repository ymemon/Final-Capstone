-- AZ Web Corp client-portal ticket system.
--
-- SQLite by design, not MySQL: the file lives outside the webroot (same
-- ~/.portal-*-state pattern client-gate.php already uses for roster.json and
-- the HMAC secret), needs no DB server or credentials on the shared host, and
-- ticket volume here is a handful of clients, not a high-traffic app.
--
-- Applied by db.php via PDO::exec() on every request if a table is missing
-- (CREATE ... IF NOT EXISTS everywhere), so there is no separate migration
-- step to remember to run after a deploy.

CREATE TABLE IF NOT EXISTS schema_meta (
  key   TEXT PRIMARY KEY,
  value TEXT NOT NULL
);

-- One row per person at a client, not per client. clients.json/roster.json
-- (client-gate.php) still own who is ALLOWED to sign in; this table is the
-- ticket system's own contact list (adds phone numbers, which auth has no
-- use for) and is seeded from the roster, then edited directly to add phones.
CREATE TABLE IF NOT EXISTS contacts (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  client_slug  TEXT NOT NULL,             -- matches clients.json key
  name         TEXT NOT NULL DEFAULT '',
  email        TEXT NOT NULL,
  phone_e164   TEXT,                      -- e.g. +14805551234; null until collected
  sms_opt_in   INTEGER NOT NULL DEFAULT 1,-- all contacts are opted in by default per policy
  created_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_contacts_client_email
  ON contacts(client_slug, email COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_contacts_phone ON contacts(phone_e164);

-- One row per ticket. Tier 1 (self-service) actions never create a row here;
-- see tier1_action_log for those. A ticket only exists once a Tier 2 category
-- has been chosen and its questionnaire submitted.
CREATE TABLE IF NOT EXISTS tickets (
  id                  INTEGER PRIMARY KEY AUTOINCREMENT,
  client_slug         TEXT NOT NULL,
  contact_id          INTEGER NOT NULL REFERENCES contacts(id),
  category            TEXT NOT NULL,               -- key into categories.php
  status              TEXT NOT NULL DEFAULT 'open', -- open|in_progress|waiting_on_client|resolved|closed
  assigned_agent      TEXT NOT NULL DEFAULT 'yasir',
  deadline            TEXT,                         -- free text from the closing question, not parsed
  publish_preference  TEXT NOT NULL DEFAULT 'review', -- 'auto' | 'review' (closing question answer)
  summary             TEXT NOT NULL DEFAULT '',      -- auto-built, e.g. "Content Update - Homepage"
  created_at          TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
  updated_at          TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);
CREATE INDEX IF NOT EXISTS idx_tickets_client ON tickets(client_slug);
CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status);
CREATE INDEX IF NOT EXISTS idx_tickets_contact ON tickets(contact_id);

-- The structured discovery-question answers a ticket was created with.
-- EAV-shaped on purpose: every Tier 2 category has a different question set
-- (see categories.php), so this stays one uniform table instead of a column
-- per possible question. question_label is a snapshot of the question text
-- at submit time so an old ticket still reads correctly if categories.php
-- wording changes later.
CREATE TABLE IF NOT EXISTS ticket_answers (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_id      INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
  question_key   TEXT NOT NULL,
  question_label TEXT NOT NULL,
  answer         TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_ticket_answers_ticket ON ticket_answers(ticket_id);

-- The ongoing thread on a ticket. A client's SMS reply and an agent's portal
-- reply both land here as the same shape, just a different channel, so the
-- ticket view never has to special-case where a message came from.
CREATE TABLE IF NOT EXISTS ticket_messages (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  ticket_id    INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
  sender_type  TEXT NOT NULL,             -- 'client' | 'agent' | 'system'
  sender_label TEXT NOT NULL DEFAULT '',
  channel      TEXT NOT NULL DEFAULT 'portal', -- 'portal' | 'sms' | 'email'
  body         TEXT NOT NULL,
  created_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);
CREATE INDEX IF NOT EXISTS idx_ticket_messages_ticket ON ticket_messages(ticket_id);

-- Audit trail of every SMS actually sent/received, independent of
-- ticket_messages, so a delivery problem can be debugged without touching
-- the client-visible thread. A row here with ticket_id NULL means an inbound
-- text could not be matched to any ticket (see sms_webhook.php).
CREATE TABLE IF NOT EXISTS sms_log (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  direction   TEXT NOT NULL,              -- 'out' | 'in'
  contact_id  INTEGER REFERENCES contacts(id),
  ticket_id   INTEGER REFERENCES tickets(id),
  twilio_sid  TEXT,
  from_number TEXT,
  to_number   TEXT,
  body        TEXT NOT NULL DEFAULT '',
  status      TEXT NOT NULL DEFAULT '',   -- queued|sent|delivered|failed|received|unmatched
  created_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);
CREATE INDEX IF NOT EXISTS idx_sms_log_contact ON sms_log(contact_id);
CREATE INDEX IF NOT EXISTS idx_sms_log_ticket ON sms_log(ticket_id);

-- Every Tier 1 (instant, no ticket) action still gets logged for
-- accountability, even though nothing here waits on a human.
CREATE TABLE IF NOT EXISTS tier1_action_log (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  client_slug TEXT NOT NULL,
  contact_id  INTEGER REFERENCES contacts(id),
  action_key  TEXT NOT NULL,              -- e.g. 'gmail_connect', 'report_frequency_set'
  detail_json TEXT NOT NULL DEFAULT '{}',
  result      TEXT NOT NULL DEFAULT 'ok', -- 'ok' | 'error'
  created_at  TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);
CREATE INDEX IF NOT EXISTS idx_tier1_log_client ON tier1_action_log(client_slug);

-- Client-facing action items ("Reminders / To-Do"), per yasir 2026-09-12: a
-- separate concept from tickets. A ticket is a request the client made; a
-- todo is something the CLIENT owes (e.g. "provide DNS access", "approve the
-- homepage mockup") so work already in progress isn't stuck silently. The
-- login popup that surfaces "anything due today" reads this table, filtered
-- to due_date = today.
CREATE TABLE IF NOT EXISTS todos (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  client_slug  TEXT NOT NULL,
  contact_id   INTEGER REFERENCES contacts(id), -- null = applies to the whole account, not one person
  ticket_id    INTEGER REFERENCES tickets(id),  -- null = not tied to a specific ticket
  title        TEXT NOT NULL,
  detail       TEXT NOT NULL DEFAULT '',
  due_date     TEXT,                            -- 'YYYY-MM-DD'; null = no specific date
  status       TEXT NOT NULL DEFAULT 'open',     -- 'open' | 'done'
  created_by   TEXT NOT NULL DEFAULT 'agent',    -- 'agent' | 'system'
  created_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
  completed_at TEXT
);
CREATE INDEX IF NOT EXISTS idx_todos_client ON todos(client_slug);
CREATE INDEX IF NOT EXISTS idx_todos_due ON todos(due_date);
CREATE INDEX IF NOT EXISTS idx_todos_status ON todos(status);

-- In-progress "start a new ticket over SMS" wizard state, one row per
-- contact (a contact can only be mid-intake once at a time). category is
-- null while the client is still picking one from the numbered menu.
-- answers_json accumulates ticket_category_questions() answers one reply at
-- a time; trigger_body is the message that started the intake (the client's
-- first text before any menu was shown), folded into the ticket's opening
-- message once it's created so that context isn't lost.
CREATE TABLE IF NOT EXISTS sms_intake_state (
  contact_id   INTEGER PRIMARY KEY REFERENCES contacts(id),
  category     TEXT,
  step_index   INTEGER NOT NULL DEFAULT 0,
  answers_json TEXT NOT NULL DEFAULT '{}',
  trigger_body TEXT NOT NULL DEFAULT '',
  updated_at   TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
);

INSERT OR IGNORE INTO schema_meta(key, value) VALUES ('version', '3');
