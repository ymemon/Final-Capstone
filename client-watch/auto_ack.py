"""
Instant client auto-acknowledgment - the ONE exception to "the watcher never
sends email" (see README.md / prompt.txt for the rest of the boundary).

Yasir, 2026-09-05: the moment a client sends a genuine new request, they
should get an immediate "we got it, working on it" reply - not silence until
the next investigation cycle.

WHAT THIS DOES send a short, templated, non-committal acknowledgment. Nothing
else. It never answers the actual request, never promises a timeline, never
states a fix - that judgment still belongs to Yasir or a live session, staged
in the normal briefing.

WHAT DECIDES WHETHER TO SEND lives in idle_watch.py's is_new_request() - only
fires for mail from a known client (never audit senders, never internal mail)
that looks like a FRESH ask rather than a reply-in-thread or a courtesy note.
That check is a heuristic, not judgment - it will occasionally ack something
that didn't need it (a fresh "just FYI" email with no reply headers, say).
That is an acceptable false positive: the ack is generic and true regardless
("we got your message"), so acking a non-request costs nothing but a slightly
unnecessary email. Never treat this heuristic as a substitute for the real
investigation - it does not read for genuineness, only for shape.

State: ack_state.json - {"acked_message_ids": [...], "day": "YYYY-MM-DD", "count": N}
Log:   ack-log.md - append-only, one line per ack sent, for Yasir to audit.

DAILY CAP: 15 acks/day, mirroring the Lane B auto-fix cap. A flood past that
more likely means a mail loop or spoofed traffic than 15 genuine new clients
in a day - stop and let Yasir look rather than sending 40 auto-replies.
"""

import json, os, sys, time
from datetime import datetime, timezone
from email.message import EmailMessage
from email.utils import formatdate, make_msgid, parseaddr

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(os.path.dirname(HERE), "azwebcorp-email"))
import azwc_mail  # send_and_file() - SMTP send + Sent-folder copy, shared helper

STATE = os.path.join(HERE, "ack_state.json")
LOG = os.path.join(HERE, "ack-log.md")

DAILY_CAP = 15

FROM_HEADER = "Phil - AZ Web Corp <requests@azwebcorp.com>"

BODY_TEMPLATE = """Hi{greeting},

Got your message - thanks for reaching out. I'm on it now and will follow up
shortly with next steps.

Phil
Account & Delivery, AZ Web Corp
requests@azwebcorp.com
"""


def _load_state():
    try:
        return json.load(open(STATE, encoding="utf-8"))
    except Exception:
        return {"acked_message_ids": [], "day": "", "count": 0}


def _save_state(state):
    json.dump(state, open(STATE, "w", encoding="utf-8"), indent=2)


def _log(line):
    with open(LOG, "a", encoding="utf-8") as fh:
        fh.write(line + "\n")


def already_acked(message_id, state):
    return bool(message_id) and message_id in state.get("acked_message_ids", [])


def under_daily_cap(state):
    today = datetime.now(timezone.utc).strftime("%Y-%m-%d")
    if state.get("day") != today:
        return True  # new day, counter will reset on record()
    return state.get("count", 0) < DAILY_CAP


def record(message_id, state):
    today = datetime.now(timezone.utc).strftime("%Y-%m-%d")
    if state.get("day") != today:
        state["day"] = today
        state["count"] = 0
    state["count"] = state.get("count", 0) + 1
    ids = state.setdefault("acked_message_ids", [])
    if message_id:
        ids.append(message_id)
    # Cap stored id list so the file doesn't grow forever.
    state["acked_message_ids"] = ids[-2000:]
    _save_state(state)


def send_ack(to_addr, subject, message_id, client_name="", reason=""):
    """
    Sends the auto-ack and files a copy in Sent. Returns (sent_ok, filed_ok, why)
    where why is "sent" or a skip/failure reason - never raises for an
    ordinary skip (cap hit, dup, no valid address).
    """
    state = _load_state()

    if not to_addr or "@" not in to_addr:
        return False, False, "no valid reply-to address"

    if already_acked(message_id, state):
        return False, False, "already acked this message-id"

    if not under_daily_cap(state):
        return False, False, f"daily cap ({DAILY_CAP}) reached - skipping, flagging for review"

    name = parseaddr(to_addr)[0].split()[0] if parseaddr(to_addr)[0] else ""
    greeting = f" {name}" if name else ""

    msg = EmailMessage()
    msg["From"] = FROM_HEADER
    msg["To"] = to_addr
    msg["Subject"] = subject if subject.lower().startswith("re:") else f"Re: {subject}" if subject else "Got your message"
    msg["Date"] = formatdate(localtime=True)
    msg["Message-ID"] = make_msgid(domain="azwebcorp.com")
    msg["Reply-To"] = "requests@azwebcorp.com"
    if message_id:
        msg["In-Reply-To"] = message_id
        msg["References"] = message_id
    msg.set_content(BODY_TEMPLATE.format(greeting=greeting))

    try:
        sent_ok, filed_ok = azwc_mail.send_and_file(msg)
    except Exception as e:
        _log(f"- {datetime.now(timezone.utc):%Y-%m-%d %H:%M:%S} UTC  FAILED  "
             f"to={to_addr}  client={client_name}  error={e}")
        return False, False, f"send failed: {e}"

    record(message_id, state)
    _log(f"- {datetime.now(timezone.utc):%Y-%m-%d %H:%M:%S} UTC  ACKED  "
         f"to={to_addr}  client={client_name}  subject={subject!r}  "
         f"reason={reason}  filed_in_sent={filed_ok}")
    return sent_ok, filed_ok, "sent"


if __name__ == "__main__":
    # Manual smoke test - does NOT send. Use --send-test-to <addr> to actually send.
    if "--send-test-to" in sys.argv:
        addr = sys.argv[sys.argv.index("--send-test-to") + 1]
        ok, filed, why = send_ack(addr, "test subject", None, client_name="TEST", reason="manual test")
        print(ok, filed, why)
    else:
        print("Import this module from idle_watch.py. For a live send test:")
        print("  python auto_ack.py --send-test-to you@example.com")
