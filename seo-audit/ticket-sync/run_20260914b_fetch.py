import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib, email
from email.header import decode_header
from email.utils import parsedate_to_datetime
from datetime import datetime, timezone

HOST = "imap.secureserver.net"
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]

CHECKPOINT_UID = 4093
CHECKPOINT_DT = datetime(2026, 9, 14, 10, 38, 45, tzinfo=timezone.utc)  # "Mon, 14 Sep 2026 05:38:45 -0500"
SINCE_DATE = "12-Sep-2026"  # a bit before cutoff; IMAP SINCE is date-granularity only

def decode(s):
    if s is None:
        return ""
    parts = decode_header(s)
    out = []
    for text, enc in parts:
        if isinstance(text, bytes):
            try:
                out.append(text.decode(enc or "utf-8", errors="replace"))
            except Exception:
                out.append(text.decode("utf-8", errors="replace"))
        else:
            out.append(text)
    return "".join(out)

def get_body(msg, limit=2000):
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in disp:
                try:
                    payload = part.get_payload(decode=True)
                    charset = part.get_content_charset() or "utf-8"
                    return payload.decode(charset, errors="replace")[:limit]
                except Exception:
                    continue
        return "(no plain text part)"
    else:
        try:
            payload = msg.get_payload(decode=True)
            charset = msg.get_content_charset() or "utf-8"
            return payload.decode(charset, errors="replace")[:limit]
        except Exception:
            return "(decode error)"

m = imaplib.IMAP4_SSL(HOST, 993)
m.login(USER, PASS)

def process_inbox(name, min_uid):
    typ, data = m.select(f'"{name}"', readonly=True)
    if typ != "OK":
        return {"error": str(data)}
    typ, data = m.uid('search', None, f'UID {min_uid+1}:*')
    if typ != "OK" or not data or not data[0]:
        return {"messages": []}
    uids = [int(u) for u in data[0].split()]
    uids = [u for u in uids if u > min_uid]
    msgs = []
    max_uid = min_uid
    max_date = None
    for uid in uids:
        typ, msgdata = m.uid('fetch', str(uid), '(RFC822)')
        if typ != "OK" or not msgdata or msgdata[0] is None:
            continue
        raw = msgdata[0][1]
        msg = email.message_from_bytes(raw)
        date_hdr = msg.get("Date")
        if uid > max_uid:
            max_uid = uid
            max_date = date_hdr
        msgs.append({
            "uid": uid,
            "from": decode(msg.get("From")),
            "to": decode(msg.get("To")),
            "subject": decode(msg.get("Subject")),
            "date": date_hdr,
            "message_id": msg.get("Message-ID"),
            "in_reply_to": msg.get("In-Reply-To"),
            "references": msg.get("References"),
            "body": get_body(msg),
        })
    return {"messages": msgs, "max_uid": max_uid, "max_date": max_date}

def process_sent(name, since_date, cutoff_dt):
    typ, data = m.select(f'"{name}"', readonly=True)
    if typ != "OK":
        return {"error": str(data)}
    typ, data = m.uid('search', None, f'(SINCE {since_date})')
    if typ != "OK" or not data or not data[0]:
        return {"messages": []}
    uids = [int(u) for u in data[0].split()]
    msgs = []
    for uid in uids:
        typ, msgdata = m.uid('fetch', str(uid), '(RFC822)')
        if typ != "OK" or not msgdata or msgdata[0] is None:
            continue
        raw = msgdata[0][1]
        msg = email.message_from_bytes(raw)
        date_hdr = msg.get("Date")
        try:
            dt = parsedate_to_datetime(date_hdr)
            if dt.tzinfo is None:
                dt = dt.replace(tzinfo=timezone.utc)
        except Exception:
            dt = None
        if dt is None or dt <= cutoff_dt:
            continue
        msgs.append({
            "uid": uid,
            "from": decode(msg.get("From")),
            "to": decode(msg.get("To")),
            "subject": decode(msg.get("Subject")),
            "date": date_hdr,
            "message_id": msg.get("Message-ID"),
            "in_reply_to": msg.get("In-Reply-To"),
            "references": msg.get("References"),
            "body": get_body(msg),
        })
    return {"messages": msgs}

inbox = process_inbox("INBOX", CHECKPOINT_UID)
sent = process_sent("Sent", SINCE_DATE, CHECKPOINT_DT)

m.logout()

out = {"inbox": inbox, "sent": sent}
with open(r"C:\Users\yasir\Documents\Final-Capstone\seo-audit\ticket-sync\run_20260914b_out.json", "w", encoding="utf-8") as f:
    _json.dump(out, f, ensure_ascii=False, indent=2)

print("INBOX new messages:", len(inbox.get("messages", [])))
print("INBOX max_uid:", inbox.get("max_uid"), inbox.get("max_date"))
print("SENT total messages fetched (unfiltered):", len(sent.get("messages", [])))
