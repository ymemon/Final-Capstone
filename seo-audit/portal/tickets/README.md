# Client portal ticket system

Scaffolding for the two-tier request model (2026-09-12): **Tier 1** actions
run instantly with no ticket; **Tier 2** categories collect a fixed
discovery-question set and create a ticket that a human (yasir) answers once,
with every response also texted to the client and a client SMS reply routed
back into the same ticket.

**2026-09-14: a client can now originate a brand-new ticket over SMS, not
just reply to one that already exists.** Previously, a text that didn't
match `#<id>` and had no open ticket to fall back to just dead-ended with
"we don't see an open ticket to attach this to." Now it starts the same
Tier 2 category + discovery-question wizard the portal's chat UI drives, one
question per text (see `sms_webhook.php`'s `handle_intake_reply()` and the
new `sms_intake_state` table in `schema.sql`, one in-progress row per
contact). Reply `CANCEL` anytime to abandon it; reply `NEW` to start a fresh
request even while a ticket is already open (otherwise a plain reply still
routes to the most-recently-updated open ticket, unchanged). Ticket creation
itself was pulled out of `ticket_api.php` into `ticket_lib.php` so the portal
form and the SMS wizard create tickets through the exact same function -
there is only one place that logic lives now.

Built alongside the existing gated reporting portal (`../client-gate.php`,
`../owner-gate.php`), reusing both of its auth mechanisms rather than adding
a third. All logic is lint-tested (`php -l`), functionally tested locally
(schema, session verification, ticket lifecycle, SMS fallback routing, phone
normalization, and the shared-storage contract below), AND, as of
2026-09-13, deployed and verified live: real login, real ticket API auth
checks, and real Twilio-credential loading all confirmed against
azwebcorp.com in production.

## Storage: inside the webroot, not `$HOME` - this was a real, fixed bug

The first version of this system (and `client-gate.php` itself, which
predates it) stored the roster, signing secret, SQLite DB and Twilio
credentials under `$HOME/.portal-*-state/`, on the theory that this was
outside the webroot but still readable by web PHP. **Live testing on
2026-09-13 proved that theory wrong**: web PHP runs in a different chroot
than SSH on this host, so `getenv('HOME')` can resolve to a path that looks
completely correct as a string while being invisible to an actual web
request. In practice this meant `client-gate.php`'s roster lookup silently
failed on every request - **no client, including AZ Web Corp's own account,
could ever actually sign in** - until this was found and fixed the same day.

The fix, now in `../state_lib.php` (shared by `client-gate.php` and this
system): store everything inside the webroot, in files PHP *executes*
rather than serves. A file containing only `<?php return <value>;`,
requested directly over HTTP, produces zero bytes - proven live (see
`state_read()`/`state_write()`). The one exception is the SQLite database,
which can't be wrapped in PHP syntax (a raw binary file has no `<?php` tag,
so PHP would pass its bytes straight through as literal output to a direct
request) - that file instead gets an unguessable random filename, generated
once and remembered the normal safe way, so there's no path to guess.

## Files

| File | Deploy target | Purpose |
|---|---|---|
| `../state_lib.php` | `html/reports/state_lib.php` AND `html/reports/_owner/state_lib.php` | Shared in-webroot storage (`state_read`/`state_write`/`state_raw_path`) |
| `schema.sql` | alongside every `db.php` copy | SQLite table definitions |
| `db.php` | `html/reports/db.php` AND `.../_owner/db.php` | Opens/creates the SQLite DB at an unguessable path under `_state/` |
| `categories.php` | `html/reports/categories.php` | The Tier 2 taxonomy + question sets (single source of truth) |
| `ticket_lib.php` | `html/reports/ticket_lib.php` | Shared ticket-creation logic (`create_ticket()`), used by both `ticket_api.php` and `sms_webhook.php` |
| `session_lib.php` | `html/reports/session_lib.php` AND `.../_owner/session_lib.php` | Reads client-gate.php's roster/secret via state_lib.php, and owner-gate.php's PHP session |
| `ticket_api.php` | `html/reports/tickets_api.php` | Client-facing JSON API |
| `agent_api.php` | `html/reports/_owner/tickets_api.php` | Agent-facing JSON API (same dir as `owner-gate.php`'s `index.php`, so its PHP session is shared) |
| `sms_notify.php` | `html/reports/sms_notify.php` AND `.../_owner/sms_notify.php` | Outbound Twilio send + credential loader (reads the `twilio_creds` state entry) |
| `sms_webhook.php` | `html/reports/sms_webhook.php` | Twilio inbound-SMS webhook (set as the number's "a message comes in" URL) |
| `tier1_actions.php` | `html/reports/tier1_actions.php` | Tier 1 action registry |

`state_lib.php`, `db.php`, `session_lib.php` and `sms_notify.php` are each
deployed TWICE (client-facing and agent-facing directories) because
`__DIR__`-relative requires resolve against wherever the script actually
runs - but all copies agree on one shared `_state/` directory via
`reports_root()`'s `basename(__DIR__) === '_owner'` check, so both sides
read and write the exact same database, roster and credentials rather than
silently diverging. This is covered by an automated test (`db.php`, invoked
from a `_owner/`-rooted subprocess, must resolve to the identical file path
a client-rooted process already created).

All the `.php` support files execute rather than serve as text when
requested directly - a bare `GET` returns 0 bytes - but they carry no
secrets in source, so that is defense in depth, not the real protection.
The real protection is that `ticket_api.php`/`agent_api.php` are the only
entry points, and both require a valid session before touching anything.
`deploy_tickets.py` (in `portal/`) pushes the whole manifest above in one
command.

## Before this can go live

1. **Deploy the files** via `python deploy_tickets.py --apply` (from
   `portal/`) - done as of 2026-09-13.
2. **Create the `twilio_creds` state entry** (`_state/twilio_creds.php` under
   `html/reports/`, in the `<?php return [...];` format `state_write()`
   produces) - done as of 2026-09-13, using yasir-provided credentials.
   Expected shape:
   ```php
   <?php
   return [
     'account_sid' => 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
     'auth_token'  => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
     'from_number' => '+1XXXXXXXXXX',
     'agent_phone' => '+1XXXXXXXXXX', // optional, for new-ticket alerts
   ];
   ```
   Complete A2P 10DLC brand + campaign registration on the Twilio number
   before relying on outbound delivery - Twilio will reject
   application-to-person SMS on an unregistered number regardless of
   whether the credentials file is correct.
3. **Backfill phone numbers.** `contacts.phone_e164` is null until a client
   provides one (Tier 1 action `contact_phone_update`, or set directly).
   SMS notifications silently no-op for a contact with no phone on file.
4. **Set the Twilio number's inbound webhook** to wherever `sms_webhook.php`
   is deployed, as an HTTP POST.
5. **Build the client-facing UI** that calls `ticket_api.php` (intake form
   driven by the `categories` action's response, a ticket list/detail view,
   a reply box) and the equivalent agent view calling `agent_api.php`. A
   first version of this now exists in `../template.html` (Messages and
   Reminders tabs, added 2026-09-12) - deployed as backend-only so far; the
   template itself needs a fresh `build_portal.py` + `deploy_clients_gated.py`
   pass before clients see the new tabs.
6. **Multi-agent readiness (not needed yet, single agent today):** the
   inbound-SMS routing in `sms_webhook.php` falls back to "the contact's
   most recent open ticket" when no `#<id>` is found in the reply. That is
   fine for one agent handling everything; if staff are ever added, a
   contact with two simultaneously open tickets can get misrouted. Twilio's
   Conversations API (a distinct address per conversation) is the real fix
   at that point, not a bigger regex.

## Design decisions worth knowing before extending this

- **SQLite, not MySQL.** No DB server/credentials needed on shared hosting,
  and ticket volume here (a handful of clients) does not need one. Swapping
  to MySQL later only requires rewriting `db.php`'s connection + `schema.sql`
  syntax; the calling code uses portable PDO/SQL throughout.
- **EAV-shaped `ticket_answers`.** Every Tier 2 category has different
  questions (`categories.php`), so answers are `(ticket_id, question_key,
  answer)` rows rather than a column per possible question across every
  category.
- **`ticket_messages` is channel-agnostic.** A portal reply and an SMS reply
  are the same row shape with a different `channel` value, so the ticket
  thread view never needs to special-case where a message came from.
- **Tier 1 actions never create a ticket row.** They log to
  `tier1_action_log` only. Do not be tempted to model "instant" work as "a
  ticket that immediately auto-resolves" - that muddies the ticket list with
  entries nobody needs to look at.
