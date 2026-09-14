"""
Date-based fallback for fetch_mail.py.

fetch_mail.py trusts state.json's last_uid_by_folder, which assumes IMAP
UIDs only ever increase for genuinely new mail. That assumption broke on
2026-08-29: a mailbox reorg (new folders appeared - AZPL, EverythingIT,
Candidates, WP candidate, SEO candidate, etc.) reassigned UIDs to old mail,
so a plain "UID > last_seen" search matched thousands of messages spanning
Oct 2025 to present instead of just what was actually new.

This script sidesteps that by filtering on the message's own Date header
against last_run_utc in state.json, ignoring UID entirely as a cutoff (UID
is still recorded per message for reference/logging). Use this instead of
fetch_mail.py if pending-new counts look implausibly large for the elapsed
time since last_run_utc - that is the signature of another reorg.

Output: mail_dump.json (UTF-8, written directly - do not print message
bodies to the console, some contain characters cp1252 can't encode).
"""
import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
import json
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime
from datetime import datetime, timezone, timedelta

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]

STATE_PATH = "state.json"

def load_state():
    with open(STATE_PATH, "r", encoding="utf-8") as f:
        return json.load(f)

def decode_val(v):
    if v is None:
        return ""
    try:
        return str(make_header(decode_header(v)))
    except Exception:
        return v

def get_body(msg):
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in disp:
                try:
                    charset = part.get_content_charset() or "utf-8"
                    return part.get_payload(decode=True).decode(charset, errors="replace")
                except Exception:
                    continue
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/html" and "attachment" not in disp:
                try:
                    charset = part.get_content_charset() or "utf-8"
                    return part.get_payload(decode=True).decode(charset, errors="replace")
                except Exception:
                    continue
        return ""
    else:
        try:
            charset = msg.get_content_charset() or "utf-8"
            return msg.get_payload(decode=True).decode(charset, errors="replace")
        except Exception:
            return str(msg.get_payload())

def main():
    state = load_state()
    last_run_utc_str = state.get("last_run_utc")
    if last_run_utc_str:
        cutoff = datetime.strptime(last_run_utc_str, "%Y-%m-%dT%H:%M:%SZ").replace(tzinfo=timezone.utc)
    else:
        cutoff = datetime.now(timezone.utc) - timedelta(days=2)

    # IMAP SINCE is date-granularity only; search a day early then filter precisely in Python.
    search_date = (cutoff - timedelta(days=1)).strftime("%d-%b-%Y")

    M = imaplib.IMAP4_SSL(HOST, PORT)
    M.login(USER, PASS)

    typ, data = M.list()
    folders = []
    for line in data:
        line = line.decode("utf-8", errors="replace")
        parts = line.split(' "/" ')
        if len(parts) == 2:
            name = parts[1].strip()
        else:
            parts = line.split(' "." ')
            name = parts[1].strip() if len(parts) == 2 else line.split()[-1]
        name = name.strip('"')
        folders.append(name)

    results = {}
    for folder in folders:
        if folder.lower() == "drafts":
            continue
        try:
            typ, _ = M.select(f'"{folder}"', readonly=True)
            if typ != "OK":
                continue
        except Exception as e:
            results[folder] = {"error": f"select failed: {e}"}
            continue

        typ, data = M.uid("search", None, "SINCE", search_date)
        if typ != "OK":
            results[folder] = {"error": "search failed"}
            continue
        uids = data[0].split()

        msgs = []
        max_uid_seen = state.get("last_uid_by_folder", {}).get(folder, 0)
        for u in uids:
            typ, msg_data = M.uid("fetch", u, "(RFC822)")
            if typ != "OK" or not msg_data or msg_data[0] is None:
                continue
            raw = msg_data[0][1]
            msg = email.message_from_bytes(raw)
            uid_int = int(u)

            date_hdr = msg.get("Date")
            try:
                msg_dt = parsedate_to_datetime(date_hdr)
                if msg_dt.tzinfo is None:
                    msg_dt = msg_dt.replace(tzinfo=timezone.utc)
                msg_dt = msg_dt.astimezone(timezone.utc)
            except Exception:
                msg_dt = None

            # strict cutoff: only messages actually dated after last run
            if msg_dt is not None and msg_dt <= cutoff:
                continue

            max_uid_seen = max(max_uid_seen, uid_int)
            body = get_body(msg)
            msgs.append({
                "uid": uid_int,
                "from": decode_val(msg.get("From")),
                "to": decode_val(msg.get("To")),
                "subject": decode_val(msg.get("Subject")),
                "date": decode_val(msg.get("Date")),
                "body": body[:8000],
            })
        results[folder] = {"messages": msgs, "max_uid_seen": max_uid_seen, "prior_uid": state.get("last_uid_by_folder", {}).get(folder, 0), "candidates_checked": len(uids)}

    M.logout()
    with open("mail_dump.json", "w", encoding="utf-8") as f:
        json.dump(results, f, indent=2, ensure_ascii=False)

if __name__ == "__main__":
    main()
