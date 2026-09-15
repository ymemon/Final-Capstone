# Client Watch — real-time rebuild

## What this is, honestly

This is **not** the original "Azwebcorp client watch" plugin. That plugin's
code lives only on the production WordPress install and was not reachable
while writing this — no server login, no filesystem access, no network path
to the site from the environment this was built in. Rather than guess at
unseen code, this is a clean, from-scratch replacement built to do the same
job (notice a client email the moment it lands at info@azwebcorp.com) without
the 15-minute wait.

**Before retiring the real plugin, compare behavior.** This rebuild logs
each email and notifies the team immediately. If the original plugin does
anything else on top of that — CRM sync, specific ticket fields, a particular
notification format others depend on — this does not replicate it, because
that behavior was never visible to build against.

## Why polling can't just be turned into "every 30 seconds"

The mailbox is Titan (GoDaddy) — plain IMAP/SMTP hosting, no push API. This
WordPress install has no persistent background process (WP-Cron only runs
when a visitor hits the site), so a true IMAP IDLE listener isn't possible
here without standing up a separate always-on server. The one mechanism that
*is* real-time without new infrastructure: forward a copy of every incoming
email to a service that already speaks webhooks (Mailgun or SendGrid), and
have that service call this plugin's endpoint the moment it receives the
forward — typically within a few seconds.

## Setup

### 1. Add the webhook secret

In `wp-config.php`:

```php
define( 'AZWC_CW_WEBHOOK_SECRET', 'a-long-random-hex-string' );
```

Generate one with `openssl rand -hex 32`. The secret travels in the URL path
(Mailgun/SendGrid's basic inbound setup doesn't let you attach a custom
header), so treat the full URL as the credential — don't post it anywhere
public.

### 2. Create an inbound route (Mailgun or SendGrid — pick one)

**Mailgun:**
- Add a route: match recipient `info@azwebcorp.com` (or a subaddress),
  action "Forward", target:
  `https://azwebcorp.com/wp-json/azwc/v1/webhook/client-watch/YOUR_SECRET`
- Mailgun POSTs `sender`, `from`, `subject`, `body-plain`, `stripped-text`.

**SendGrid Inbound Parse:**
- Settings → Inbound Parse → add hostname, destination URL:
  `https://azwebcorp.com/wp-json/azwc/v1/webhook/client-watch/YOUR_SECRET`
- SendGrid POSTs `from`, `subject`, `text`, `envelope`.

### 3. Point Titan at it

In Titan webmail (or GoDaddy's email admin), add a filter/forwarding rule on
info@azwebcorp.com that forwards a copy of every incoming message to the
address Mailgun/SendGrid gave you for the route above (a
`...@mydomain.mailgun.org`-style address, or whatever SendGrid assigned).
**This step has to happen inside Titan's own settings — nothing here can do
it remotely.**

### 4. Verify

```bash
wp azwc-client-watch
```

Confirms the table exists, the secret is set, and shows the notify address.
Send a real test email to trigger the whole chain, then check
**wp-admin → Client Watch**.

## What it does on receipt

1. Parses the forwarded message (Mailgun or SendGrid format, or a plain
   `{from, subject, body}` JSON POST for anything else).
2. Inserts a row into `wp_azwc_watch`.
3. Emails the team (`azwc_cw_notify_email()`, defaults to
   info@azwebcorp.com) immediately — no cron wait.
4. Fires `do_action( 'azwc_cw_email_received', $row )` for anything else
   that should react to it.

## Fallback if forwarding ever breaks

There is none built in — this plugin has no poller at all, by design, since
introducing one would need IMAP credentials that weren't available either.
If the forwarding rule breaks, mail simply stops appearing here until it's
fixed; it does **not** silently fall back to a slow check. Worth a periodic
glance at wp-admin → Client Watch to confirm messages are still arriving.
