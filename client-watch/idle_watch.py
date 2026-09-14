"""
Real-time INBOX watcher for info@azwebcorp.com using IMAP IDLE.

WHY THIS EXISTS
The ticket sync and the client watcher both run on a schedule (hourly at best),
so a client email could sit unseen for up to an hour. This connects once and
holds an IDLE session open, so the server pushes a notification within seconds
of a message arriving. On a message that matters it can fire the existing
sync/watch job immediately instead of waiting for the next tick.

WHAT IT DOES NOT DO
It never replies, never deletes, never marks anything read - the mailbox is
opened readonly and stays that way. It classifies and records, and optionally
launches an existing job. Deciding what to say to a client is still a human
(or a live session) decision.

DESIGN NOTES
- IDLE must be renewed; RFC 2177 says a client should re-issue at least every
  29 minutes, so the loop reconnects on that cycle rather than trusting the
  server to hold it open indefinitely.
- Connections drop. Every failure backs off (5s doubling to 5min) and retries
  forever rather than exiting, because a watcher that dies quietly is worse
  than no watcher.
- State is the highest UID seen, persisted, so a restart does not replay old
  mail or miss anything that arrived while it was down.

GoDaddy migrates this mailbox to Titan on 2026-09-10 - see the
titan-email-migration memory. connect() now tries both the old and new host
on every attempt, so the cutover itself should not need a code change. What
it cannot paper over is a changed password: Titan sometimes issues its own
credential rather than keeping the mailbox password, and if that happens
here MAIL_PASS (via azwebcorp-creds.json) will need updating by hand.

Usage:
    python idle_watch.py                 # run in foreground
    python idle_watch.py --once          # single check, no IDLE (for testing)
    python idle_watch.py --trigger "cmd" # run cmd when client mail arrives
"""

import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib, email, json, os, sys, time, subprocess, re, atexit
from email import policy
from email.utils import parseaddr
from datetime import datetime, timezone

HERE = os.path.dirname(os.path.abspath(__file__))
import auto_ack  # same directory - script's own dir is on sys.path automatically

IMAP_PORT = 993
MAIL_USER = "info@azwebcorp.com"
MAIL_PASS = _AZWC["mail_password"]

# GoDaddy migrates this mailbox to Titan on 2026-09-10. Tried in this order on
# every connection attempt, so whichever host is actually live today just
# works without a code change or restart. Verified 2026-09-10: secureserver
# still authenticates, titan.email rejects the same password (mailbox not
# actually cut over yet, or Titan wants its own regenerated credential per
# GoDaddy's docs) - if it turns out to need a new password, this fallback
# alone will not be enough and MAIL_PASS will need updating once that's known.
IMAP_HOSTS = ("imap.secureserver.net", "imap.titan.email")

STATE = os.path.join(HERE, "idle_state.json")
EVENTS = os.path.join(HERE, "idle_events.jsonl")
LOG = os.path.join(HERE, "idle_watch.log")
CLIENTS = os.path.join(HERE, "clients.json")

IDLE_SECONDS = 29 * 60


def log(msg):
    line = f"{datetime.now(timezone.utc):%Y-%m-%d %H:%M:%S} UTC  {msg}"
    print(line, flush=True)
    with open(LOG, "a", encoding="utf-8") as fh:
        fh.write(line + "\n")


def load_clients():
    """Domains/addresses that make a message worth waking someone for."""
    try:
        cfg = json.load(open(CLIENTS, encoding="utf-8"))
    except Exception as e:
        log(f"! could not read clients.json ({e}) - treating every sender as unknown")
        return set(), set(), set(), set()
    domains, addrs = set(), set()
    for c in cfg.get("clients", []):
        if c.get("name", "").startswith("AZ Web Corp"):
            continue                      # our own domain is not a client
        domains |= {d.lower() for d in c.get("domains", [])}
        addrs |= {a.lower() for a in c.get("emails", [])}
    audit = {a.lower() for a in cfg.get("audit_sources", [])}
    ignore = {s.lower() for s in cfg.get("ignore_senders", [])}
    return domains, addrs, audit, ignore


def build_client_name_map():
    """address (lowercased) -> client name, for logging only - not used for classification."""
    try:
        cfg = json.load(open(CLIENTS, encoding="utf-8"))
    except Exception:
        return {}
    out = {}
    for c in cfg.get("clients", []):
        if c.get("name", "").startswith("AZ Web Corp"):
            continue
        for a in c.get("emails", []):
            out[a.lower()] = c.get("name", "")
    return out


def classify(frm, domains, addrs, audit, ignore):
    f = (frm or "").lower()
    if not f:
        return "unknown"
    if f in audit:
        return "audit"                    # audit_sources beats ignore_senders
    if f.endswith("@azwebcorp.com"):
        return "ours"
    if f in addrs or any(f.endswith("@" + d) or f.endswith("." + d) for d in domains):
        return "client"
    if any(p in f for p in ignore):
        return "noise"
    return "unknown"


def read_state():
    try:
        return json.load(open(STATE, encoding="utf-8")).get("last_uid", 0)
    except Exception:
        return 0


def write_state(uid):
    json.dump({"last_uid": uid,
               "updated": datetime.now(timezone.utc).isoformat()},
              open(STATE, "w", encoding="utf-8"), indent=2)


def fetch_new(M, last_uid, cls):
    """Return (events, highest_uid). Readonly throughout."""
    typ, data = M.uid("search", None, f"(UID {last_uid + 1}:*)")
    uids = [u for u in (data[0].split() if data and data[0] else [])
            if int(u) > last_uid]
    out, high = [], last_uid
    for uid in uids:
        typ, d = M.uid("fetch", uid,
                       "(BODY.PEEK[HEADER.FIELDS "
                       "(FROM TO SUBJECT DATE MESSAGE-ID IN-REPLY-TO REFERENCES)])")
        if not d or not d[0]:
            continue
        m = email.message_from_bytes(d[0][1], policy=policy.default)
        frm = parseaddr(str(m["From"] or ""))[1]
        ev = {
            "at": datetime.now(timezone.utc).isoformat(),
            "uid": int(uid),
            "from": frm,
            "to": str(m["To"] or "")[:120],
            "subject": str(m["Subject"] or "")[:160],
            "date": str(m["Date"] or ""),
            "message_id": str(m["Message-ID"] or ""),
            "in_reply_to": str(m["In-Reply-To"] or ""),
            "references": str(m["References"] or ""),
            "kind": classify(frm, *cls),
        }
        out.append(ev)
        high = max(high, int(uid))
    return out, high


def fetch_body_text(M, uid, limit=4000):
    """Best-effort plain-text body for a message, readonly. Empty string on failure."""
    try:
        typ, d = M.uid("fetch", uid, "(BODY.PEEK[])")
        if not d or not d[0]:
            return ""
        m = email.message_from_bytes(d[0][1], policy=policy.default)
        body = m.get_body(preferencelist=("plain", "html"))
        if body is None:
            return ""
        text = body.get_content()
        if body.get_content_type() == "text/html":
            text = re.sub(r"<[^>]+>", " ", text)
        return text.strip()[:limit]
    except Exception:
        return ""


_COURTESY_MARKERS = (
    "thanks", "thank you", "thx", "appreciate it", "sounds good",
    "looks good", "perfect, ", "got it,", "noted,", "will do,",
)


def is_new_request(ev, body_text):
    """
    Heuristic only - not judgment. True means "looks like a fresh ask worth an
    instant ack", not "this is confirmed genuine work". See auto_ack.py's
    module docstring for what that distinction means in practice.
    """
    subj = (ev.get("subject") or "").strip().lower()
    if subj.startswith(("re:", "fwd:", "fw:")):
        return False, "reply/forward subject"
    if ev.get("in_reply_to") or ev.get("references"):
        return False, "threaded (In-Reply-To/References present)"
    text = (body_text or "").strip().lower()
    if text and len(text) < 400 and "?" not in text:
        if any(m in text for m in _COURTESY_MARKERS):
            return False, "short courtesy note, no question"
    return True, "fresh, unthreaded message"


def maybe_ack_client_events(M, events, client_names):
    """For each 'client' event, decide + send the instant auto-ack. Best-effort:
    never lets an ack failure break the watch loop."""
    for e in events:
        if e["kind"] != "client":
            continue
        try:
            body = fetch_body_text(M, e["uid"])
            ok, why = is_new_request(e, body)
            if not ok:
                log(f"   no ack (uid={e['uid']}): {why}")
                continue
            name = client_names.get(e["from"].lower(), "")
            sent_ok, filed_ok, result = auto_ack.send_ack(
                e["from"], e["subject"], e["message_id"] or None,
                client_name=name, reason=why,
            )
            if result == "sent":
                log(f"   -> ack sent to {e['from']} (filed_in_sent={filed_ok})")
            else:
                log(f"   no ack (uid={e['uid']}): {result}")
        except Exception as ex:
            log(f"   ! ack attempt failed for uid={e['uid']}: {type(ex).__name__}: {ex}")


def record(events):
    with open(EVENTS, "a", encoding="utf-8") as fh:
        for e in events:
            fh.write(json.dumps(e) + "\n")


def connect():
    """Try each known host in order; use whichever one actually accepts
    login today. See IMAP_HOSTS for why there is more than one."""
    last_err = None
    for host in IMAP_HOSTS:
        try:
            M = imaplib.IMAP4_SSL(host, IMAP_PORT)
            M.login(MAIL_USER, MAIL_PASS)
            M.select("INBOX", readonly=True)      # readonly, always
            return M
        except Exception as e:
            last_err = e
            log(f"   connect to {host} failed: {type(e).__name__}: {e}")
    raise last_err


class Trigger:
    """
    Runs the follow-on job, but never more than one at a time and never more
    often than the cooldown.

    Both guards matter. The sync job takes minutes; three client emails
    arriving together would otherwise start three overlapping runs against the
    same mailbox and the same ticket API, which is how you get duplicate
    tickets. A burst of mail should produce one run that sees all of it.
    """

    COOLDOWN = 120        # seconds

    def __init__(self, cmd):
        self.cmd = cmd
        self.proc = None
        self.last = 0.0

    def fire(self, why):
        if not self.cmd:
            return
        if self.proc and self.proc.poll() is None:
            log(f"-> trigger skipped ({why}): previous run still going")
            return
        wait = self.COOLDOWN - (time.time() - self.last)
        if wait > 0:
            log(f"-> trigger skipped ({why}): cooling down {wait:.0f}s")
            return
        log(f"-> firing trigger ({why}): {self.cmd}")
        self.proc = subprocess.Popen(self.cmd, shell=True)
        self.last = time.time()


LOCK_PATH = os.path.join(HERE, "idle_watch.lock")


def acquire_singleton_lock():
    """Refuse to start if another watcher is already live.

    WHY: on 2026-09-07 seventeen copies of this script were found running at
    once. Each held its own IDLE session and its own in-memory "last UID", so
    each independently saw the same new mail and independently called
    auto_ack.send_ack(). auto_ack's de-dup reads ack_state.json with no
    cross-process lock, so all of them passed the "not acked yet" check inside
    the same second. Faraz received fourteen copies of one acknowledgement and
    complained. Task Scheduler's own IgnoreNew setting did not prevent the
    pile-up, so the guard has to live here.

    Uses exclusive create (O_EXCL) - atomic, so two simultaneous starts cannot
    both win. A stale lock from a killed process is detected by checking
    whether the recorded PID is still alive, and reclaimed if not.
    """
    while True:
        try:
            fd = os.open(LOCK_PATH, os.O_CREAT | os.O_EXCL | os.O_WRONLY)
            with os.fdopen(fd, "w") as fh:
                fh.write(str(os.getpid()))
            atexit.register(release_singleton_lock)
            return True
        except FileExistsError:
            try:
                with open(LOCK_PATH) as fh:
                    other = int((fh.read() or "0").strip() or 0)
            except (ValueError, OSError):
                other = 0

            if other and pid_alive(other) and other != os.getpid():
                log(f"another watcher is already running (pid {other}); exiting")
                return False

            # Stale lock - the owner is gone. Reclaim it and retry.
            log(f"removing stale lock from dead pid {other}")
            try:
                os.unlink(LOCK_PATH)
            except OSError:
                return False


def pid_alive(pid):
    if os.name == "nt":
        out = subprocess.run(
            ["tasklist", "/FI", f"PID eq {pid}", "/NH"],
            capture_output=True, text=True,
        ).stdout
        return str(pid) in out
    try:
        os.kill(pid, 0)
    except OSError:
        return False
    return True


def release_singleton_lock():
    try:
        with open(LOCK_PATH) as fh:
            if int((fh.read() or "0").strip() or 0) != os.getpid():
                return   # not ours; leave the live owner's lock alone
        os.unlink(LOCK_PATH)
    except (OSError, ValueError):
        pass


def main():
    if not acquire_singleton_lock():
        return

    trigger = None
    if "--trigger" in sys.argv:
        trigger = sys.argv[sys.argv.index("--trigger") + 1]
    once = "--once" in sys.argv

    cls = load_clients()
    client_names = build_client_name_map()
    trig = Trigger(trigger)
    log(f"starting; watching {MAIL_USER} INBOX"
        + (f"; trigger={trigger!r}" if trigger else "; no trigger"))

    backoff = 5
    while True:
        try:
            M = connect()
            last = read_state()
            if last == 0:
                typ, data = M.uid("search", None, "ALL")
                allu = data[0].split() if data and data[0] else []
                last = int(allu[-1]) if allu else 0
                write_state(last)
                log(f"first run - baseline set at UID {last}, not replaying history")

            # Catch anything that arrived while we were disconnected.
            events, high = fetch_new(M, last, cls)
            if events:
                record(events)
                write_state(high)
                for e in events:
                    log(f"[{e['kind']:<7}] uid={e['uid']} {e['from']} | {e['subject'][:70]}")
                last = high
                maybe_ack_client_events(M, events, client_names)
                kinds = {e["kind"] for e in events}
                if kinds & {"client", "audit"}:
                    trig.fire(",".join(sorted(kinds & {"client", "audit"})))

            backoff = 5
            if once:
                log("--once given, exiting")
                M.logout()
                return

            log(f"IDLE open (renews every {IDLE_SECONDS // 60} min)")
            while True:
                got = False
                with M.idle(duration=IDLE_SECONDS) as idler:
                    for typ, data in idler:
                        # EXISTS means the mailbox gained a message.
                        if typ == "EXISTS":
                            got = True
                            break
                if got:
                    events, high = fetch_new(M, last, cls)
                    if events:
                        record(events)
                        write_state(high)
                        last = high
                        for e in events:
                            log(f"[{e['kind']:<7}] uid={e['uid']} {e['from']} | {e['subject'][:70]}")
                        maybe_ack_client_events(M, events, client_names)
                        kinds = {e["kind"] for e in events}
                        if kinds & {"client", "audit"}:
                            trig.fire(",".join(sorted(kinds & {"client", "audit"})))
                else:
                    # duration elapsed with nothing - renew the IDLE
                    M.noop()

        except KeyboardInterrupt:
            log("stopped by user")
            return
        except Exception as e:
            log(f"! {type(e).__name__}: {e} - reconnecting in {backoff}s")
            time.sleep(backoff)
            backoff = min(backoff * 2, 300)


if __name__ == "__main__":
    main()
