# Ticket Mailbox Sync — real-time rebuild

## What this is, honestly

This is **not** the original "Azwebcorp ticket mailbox sync" plugin. That
plugin's code lives only on the production WordPress install and was not
reachable while writing this — no server login, no network path to the
site. This is a from-scratch rebuild aimed at the described job: catch a
client email in real time, assign it a ticket number, thread replies to
the right ticket, and nudge the client if a ticket goes quiet — not a
line-for-line port of unseen code. Compare it against the real plugin's
actual behavior before retiring that plugin.

## The one thing this genuinely cannot do

**It cannot see when your team replies from their own inbox.** Everything
here only knows what arrives through the webhook. So a "nudge" is only ever
"the client emailed and nothing further came in through this system since"
— never "the team hasn't answered yet." If your team replies directly from
Titan webmail or Outlook, this plugin has no idea that happened, and it
will nudge the client again on schedule unless a human marks the ticket
**Resolved** in wp-admin → Ticket Mailbox.

If the real plugin tracked outbound replies too (e.g. it was itself the
thing your team sends replies through), this rebuild does not match that
behavior, because it was never visible to build against.

## How real-time capture works

Same mechanism as the Client Watch rebuild, and for the same reason: Titan
(GoDaddy) has no push API, and this WordPress install has no persistent
process for a true IMAP listener. A Titan forwarding rule sends a copy of
each incoming email to Mailgun or SendGrid's inbound parse, which calls
this plugin's webhook within seconds.

## Ticket numbering and threading

- Every new sender+subject combination opens a ticket, numbered `AZW-00001`,
  `AZW-00002`, etc. (the row ID, zero-padded).
- The automatic acknowledgment sent to the client includes `[Ticket
  #AZW-00042]` in the subject. When they reply, most mail clients keep that
  tag in the subject line, so the reply threads onto the same ticket.
- If the tag is missing (forwarded, subject edited), it falls back to
  matching the same sender + a normalized subject (strips Re:/Fwd:/the tag)
  within the last 45 days.
- No match on either → a new ticket opens. Filing a reply as new is a minor
  annoyance; misfiling it onto a stranger's ticket is worse, so ties go to
  "open a new one."
- A reply on a `resolved`/`closed` ticket reopens it automatically.

## Setup

### 1. Webhook secret

In `wp-config.php`:

```php
define( 'AZWC_TMS_WEBHOOK_SECRET', 'a-different-long-random-hex-string' );
```

Deliberately a **separate** secret from Client Watch's — the two plugins
are independent; one leaking shouldn't hand over the other's endpoint.

### 2. Inbound route (Mailgun or SendGrid)

Point it at:

```
https://azwebcorp.com/wp-json/azwc/v1/webhook/ticket-mailbox/YOUR_SECRET
```

Same field names as documented in `client-watch/README.md` (Mailgun:
`sender`/`from`/`subject`/`body-plain`; SendGrid: `from`/`subject`/`text`).

### 3. Titan forwarding rule

Add a filter in Titan webmail on info@azwebcorp.com forwarding a copy of
every incoming message to whichever inbound-parse address Mailgun/SendGrid
assigned. **This has to happen inside Titan's own settings** — nothing here
can do it remotely.

If you also run Client Watch, you can forward the same copy to both
webhooks (two forwarding rules, or one rule to an address that fans out to
both — depends what your forwarding tool supports), or decide this plugin
supersedes it. That's a product decision, not something this rebuild
assumes for you.

### 4. Verify

```bash
wp azwc-ticket-mailbox
```

Shows the table status, open-ticket count, whether the secret is set, and
the next scheduled nudge check.

## Nudges

- Runs once a day (`azwc_tms_nudge_tick`, WP-Cron `daily`).
- A ticket nudges when: `status = 'open'`, and it's been at least
  `AZWC_TMS_NUDGE_DAYS` (default 3) since the client's last message, and
  either it's never been nudged or the last nudge was that long ago too.
- Stops after `AZWC_TMS_NUDGE_MAX` (default 3) nudges, so an abandoned
  ticket doesn't email the client forever.
- Both constants are defined in `azwc-ticket-mailbox-sync.php` — change
  them there if 3 days / 3 nudges isn't the right cadence.

## No IMAP fallback, same as Client Watch

If the forwarding rule breaks, tickets simply stop arriving — there's no
slow-poll fallback, by design. No mailbox credentials were available to
build one, and a silent fallback would hide a broken forward instead of
someone noticing quickly.
