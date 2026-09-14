# Rebound Body Studio - Booking Engine

A complete replacement for MassageBook: appointment scheduling, client management, package credits, email reminders, and admin approval workflow.

## What We're Building

A massively simplified booking system for one massage therapist (Randi) that handles:
- **Public booking flow**: clients pick a service → see available slots → request appointment → wait for approval
- **Approval workflow**: Randi gets notified of each request and approves/declines from an admin screen
- **Reminders**: automated 24-hour email reminders before confirmed appointments
- **Package deals**: session packages with credit tracking and expiry
- **Marketing email**: transactional and opt-in marketing mail, with one-click unsubscribe
- **Cancellations**: clients can cancel their own appointments; Randi can cancel or mark no-show from admin
- **Staff visibility**: Wendy is visible on the site but non-bookable (she rents the room, takes her own bookings)

## Project Structure

```
booking/
├── migrations/
│   └── 001_schema.sql          Database schema (15 tables)
├── src/
│   ├── Clock.php               Timezone handling (UTC ↔ America/Phoenix)
│   ├── Db.php                  PDO wrapper (SQLite/MySQL portable)
│   ├── Availability.php        Slot generation + collision detection
│   ├── Booking.php             Appointment lifecycle (request/approve/cancel)
│   ├── Mailer.php              Queued outbound mail + consent
│   ├── Templates.php           [TODO] Email template rendering
│   ├── Http.php                [TODO] Request routing
│   └── Admin.php               [TODO] Admin actions (approve, decline, mark complete)
├── api/
│   ├── slots.php               [TODO] GET /api/slots?serviceId=1&staffId=1
│   ├── request.php             [TODO] POST /api/booking/request
│   ├── manage.php              [TODO] GET /appointment/{manage_token}
│   └── admin/
│       ├── auth.php            [TODO] Passwordless login for Randi
│       └── approve.php         [TODO] POST /admin/approve/{apptId}
├── templates/
│   ├── request_received.txt    [TODO]
│   ├── confirmed.txt           [TODO]
│   ├── reminder_24h.txt        [TODO]
│   ├── declined.txt            [TODO]
│   └── cancelled_*.txt         [TODO]
├── public/
│   ├── book.html               [TODO] Booking form + slot picker
│   ├── manage.html             [TODO] Client's own appointment view
│   └── admin/                  [TODO] Randi's approval dashboard
└── README.md                   [This file]
```

## Key Design Decisions

1. **Timezone**: All times stored as UTC; displayed in America/Phoenix (Arizona has no DST, avoiding the classic booking bugs)
2. **Approval-first**: Nothing is "confirmed" until Randi approves it. Pending requests still block their slots.
3. **Queued mail**: Mail is committed to the queue first. A protected lease/ack endpoint hands due messages to the logoff-safe AZWebCorp worker, which delivers them through authenticated SMTP without exposing customer data to GoDaddy's unreliable PHP mail transport.
4. **Portable**: Works against SQLite (local dev) and MySQL 5.7+ (GoDaddy shared hosting).
5. **No ORM**: This is one therapist's system, not a platform. Raw SQL is clearer than an abstraction layer.

## Database Schema Highlights

- **services**: The massage types she offers (30/60/90/120-min durations with prices)
- **staff**: Who can be booked. Wendy has `bookable=0` (visible, non-bookable)
- **availability_rules**: Weekly recurring hours (e.g., "Monday 9am–1pm, 2pm–6pm")
- **blackouts**: Time off, courses, holidays
- **appointments**: The lifecycle (requested → confirmed → completed, or declined/cancelled)
- **clients**: Email, phone, marketing consent + one-click unsubscribe token
- **packages**: Session packages (e.g., "6 massage sessions for $450")
- **package_purchases**: One row per purchase, tracks credit balance
- **credit_ledger**: Audit trail of every session spent/refunded
- **mail_queue**: Outbound mail; drained by cron
- **login_tokens**: Passwordless sign-in for clients to view/cancel their appointments
- **settings**: Config (site_url, buffer_min, reminder_lead_hours, etc.)

## What's Done

✅ Schema (all 15 tables)
✅ Clock.php (timezone layer)
✅ Db.php (PDO wrapper)
✅ Availability.php (slot generation + collision detection)
✅ Booking.php (full lifecycle: request, approve, decline, cancel, mark completed/no-show, credits)
✅ Mailer.php (queue, lease/ack handoff, consent, unsubscribe)
✅ Templates.php (email body rendering)
✅ Email templates (8 files: request_received, confirmed, reminder_24h, declined, cancelled_by_client, cancelled_by_studio, studio_new_request, studio_cancellation)
✅ HTTP endpoints (slots, request, manage, unsubscribe, admin login/approve/requests/calendar)
✅ Booking form UI (public/index.html)
✅ Bootstrap script (database init + seeding)
✅ Mail cron worker (drain-mail.php)
✅ Logoff-safe authenticated SMTP daemon (30-second polling under the AZWebCorp S4U watcher)
✅ Deployment guide (DEPLOYMENT.md)

## What's Left for Launch

- [ ] Admin dashboard UI — Randi's interface to see requests, approve/decline, view calendar (API is ready)
- [ ] Client manage UI — Clients' page to view and cancel appointments (API is ready)
- [x] Preview email delivery — off-host authenticated AZWebCorp SMTP worker
- [ ] Production-domain email authentication — configure SPF, DKIM, and DMARC before sending from reboundbodystudio.com
- [ ] Live domain setup — reboundbodystudio.com hosting + SSL
- [x] Cron activation — Server-side WordPress event runs every 5 minutes
- [ ] Live testing — Test full end-to-end flow with real email and payments

## Running It

**Local development (SQLite):**
```php
$db = Db::sqlite('/path/to/booking.db');
$db->pdo()->exec(file_get_contents('migrations/001_schema.sql'));
```

**Production (MySQL on GoDaddy):**
```php
$db = new Db('mysql:host=db.example.com;dbname=booking', 'user', 'pass');
```

Then seed the data, wire up the endpoints, and deploy.

## Constraints & Notes

- **SOAP notes & intake forms stay in MassageBook** — medical records liability on shared hosting without a BAA is unacceptable. We're replacing booking only, not the clinical side.
- **Arizona timezone** — No daylight saving. The offset is hardcoded as -07:00 in Clock::STUDIO_TZ, so no "slot exists twice" bugs on spring forward.
- **No passwords** — Clients get one-use email tokens to view their appointments. Randi gets the same.
- **Row locking in MySQL** — Booking checks if a slot is free inside a transaction with `SELECT ... FOR UPDATE` to prevent double-books.
- **Idempotent mail** — Dedupe keys prevent sending the same message twice if a webhook retries or a button is double-clicked.

---

**Next developer**: Start with Templates.php → email endpoints → admin approval UI. The core engine is solid; the rest is plumbing.
