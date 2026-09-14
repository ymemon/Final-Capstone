# Deployment Guide

## Local Development

```bash
cd booking
php bootstrap.php
php -S localhost:8000
# Visit http://localhost:8000/public/index.html
```

The bootstrap script creates a SQLite database and seeds all initial data. You can then test the booking flow locally.

## Production Deployment

### Prerequisites

- GoDaddy shared hosting account (or any MySQL 5.7+ host)
- PHP 7.4+
- SSH/SFTP access
- Ability to set up cron jobs

### Step 1: Database Setup

Create a new MySQL database and user:

```sql
CREATE DATABASE rebound_booking;
CREATE USER 'booking'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON rebound_booking.* TO 'booking'@'localhost';
FLUSH PRIVILEGES;
```

Run the schema migration:

```sql
SOURCE migrations/001_schema.sql;
```

Or use the PHP bootstrap (modified for MySQL):

```php
$db = new Db(
    'mysql:host=localhost;dbname=rebound_booking;charset=utf8mb4',
    'booking',
    'strong_password_here'
);
// Then run the rest of bootstrap.php
```

### Step 2: File Deployment

Upload the entire `booking/` directory to your hosting account. Common paths:
- `/public_html/booking/` (standard shared hosting)
- `/home/username/public_html/booking/` (full path)
- Or a subdomain: `booking.reboundbodystudio.com`

Ensure these directories are writable:
- `migrations/` — for backup files (optional)
- `logs/` — for mail and error logs (optional)

### Step 3: Configuration

Update database credentials in your endpoints. Create a `config.php` file:

```php
<?php
define('DB_DSN', 'mysql:host=localhost;dbname=rebound_booking;charset=utf8mb4');
define('DB_USER', 'booking');
define('DB_PASS', 'strong_password_here');
define('SITE_URL', 'https://reboundbodystudio.com');
define('SITE_EMAIL', 'hello@reboundbodystudio.com');
```

Replace the database path in all PHP files that instantiate `Db::sqlite()`:

Before:
```php
$db = Db::sqlite(dirname(__DIR__) . '/booking.db');
```

After:
```php
require_once __DIR__ . '/config.php';
$db = new Db(DB_DSN, DB_USER, DB_PASS);
```

### Step 4: Cron Setup

#### GoDaddy Managed WordPress (current preview)

GoDaddy's local PHP mail transport accepts messages without reliably delivering
them, and this Managed WordPress environment blocks outbound SMTP sockets. Do
not run the queue drain on the server.

The protected `cron/drain-http.php` endpoint exposes only lease and
acknowledgement operations. On the AZWebCorp Windows host,
`rebound_mail_daemon.py` polls it every 30 seconds and sends through the
authenticated `info@azwebcorp.com` mailbox. `run_idle_watch.bat` starts the
daemon under the existing `AZWebCorp Mail IDLE Watch` S4U task, so it continues
while the user is logged off.

Check the endpoint without claiming or sending any mail:

```
python booking/rebound_mail_worker.py --probe
```

The separate Windows `Rebound Mail Drain` task must remain disabled; the S4U
daemon is the only production worker. Its rotating log is
`booking/rebound_mail_worker.log`.

#### Hosts with real system cron

Set up the mail draining cron job via your hosting control panel (cPanel, Plesk, etc.):

```
*/5 * * * * /usr/bin/php /home/username/public_html/booking/cron/drain-mail.php >> /home/username/public_html/booking/logs/mail.log 2>&1
```

This runs every 5 minutes and sends queued mail.

### Step 5: SMTP Configuration

On the production preview, the Windows worker connects directly to
`smtpout.secureserver.net` with the existing AZWebCorp mailbox credential. The
server-side `Mailer::sendViaMail()` deliberately refuses production delivery so
an operator cannot accidentally turn GoDaddy's false-positive PHP mail result
back into a lost message.

The current preview must use `info@azwebcorp.com` as `mail_from`, because that
domain authorizes GoDaddy's mail servers with SPF and enforces aligned DMARC.
Keep `hello@reboundbodystudio.com` as `studio_email`/Reply-To so replies still
reach Randi. Do not change the From address to reboundbodystudio.com until that
domain has a verified outbound provider plus SPF, DKIM, and DMARC records.

### Step 6: HTTPS & Security

- Ensure HTTPS is enabled (Let's Encrypt is free)
- Set secure cookies: `session.cookie_secure = On` in `php.ini`
- Use environment variables for database credentials (don't commit them)

### Step 7: Testing

1. **Test booking form**: Visit `https://reboundbodystudio.com/booking/public/index.html`
2. **Request an appointment** with your test email
3. **Check mail queue**: `SELECT * FROM mail_queue WHERE sent_at IS NULL;`
4. **Check worker log**: confirm the queue id says `delivered and acknowledged`
5. **Verify email arrived** in your test inbox
6. **Log in to admin**: Randi can request a login link via the admin login endpoint

### Step 8: Integrate with Website

Embed the booking form in the website:

```html
<iframe 
    src="https://reboundbodystudio.com/booking/public/index.html"
    width="100%"
    height="900"
    frameborder="0"
    style="border: none; border-radius: 8px;">
</iframe>
```

Or link directly:
```html
<a href="https://reboundbodystudio.com/booking/public/index.html">
    Book an Appointment
</a>
```

## Monitoring

### Check Mail Queue

```php
$db = new Db(...);
$pending = $db->all('SELECT * FROM mail_queue WHERE sent_at IS NULL');
$failed = $db->all('SELECT * FROM mail_queue WHERE sent_at IS NULL AND attempts >= 5');
```

### Check Appointment Status

```php
$db->all('SELECT * FROM appointments WHERE status = ? ORDER BY created_at DESC', ['requested']);
$db->all('SELECT * FROM appointments WHERE created_at > ? ORDER BY created_at DESC', [date('Y-m-d 00:00:00')]);
```

## Troubleshooting

### Mail Not Sending

1. Run `python booking/rebound_mail_worker.py --probe`
2. Check `booking/rebound_mail_worker.log`
3. Confirm the `AZWebCorp Mail IDLE Watch` S4U task is running
4. Check the queue row's `attempts`, `last_error`, and lease fields
5. Check the recipient's spam/quarantine folder

### Double Bookings

- Ensure database supports `SELECT ... FOR UPDATE` (MySQL does; SQLite relies on transactions)
- Check that `Availability::isStillFree()` is called inside `Booking::request()`

### Timezone Issues

- Verify `America/Phoenix` is a valid timezone on the server
- Check PHP's `date_default_timezone_set()`

## Rollback

Keep a backup of the original `booking.db` or database dump:

```bash
mysqldump rebound_booking > backup_2026-09-09.sql
```

To restore:

```bash
mysql rebound_booking < backup_2026-09-09.sql
```

## Performance

For 100+ appointments/month, consider:
- Database indexing (already in schema)
- Caching slots in Redis
- Async mail sending via a queue service (AWS SQS, etc.)

The current design handles a solo therapist's volume easily.
