import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib, email, re
from email.header import decode_header
from email.utils import parsedate_to_datetime
from datetime import datetime, timezone

HOST = "imap.secureserver.net"
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]

CUTOFF = datetime(2026, 9, 14, 10, 38, 46, tzinfo=timezone.utc)
SINCE_DATE = "12-Sep-2026"  # a bit before cutoff, IMAP SINCE is date-granularity only

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

def get_body(msg, limit=1500):
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

typ, folders = m.list()

results = {}
max_uid_seen = 0
max_date_seen = None

def process_folder(name):
    global max_uid_seen, max_date_seen
    try:
        typ, data = m.select(f'"{name}"', readonly=True)
    except Exception as e:
        results[name] = {"error": str(e)}
        return
    if typ != "OK":
        results[name] = {"error": str(data)}
        return
    typ, data = m.uid('search', None, f'(SINCE {SINCE_DATE})')
    if typ != "OK" or not data or not data[0]:
        results[name] = {"messages": []}
        return
    uids = data[0].split()
    msgs = []
    for uid in uids:
        typ, msgdata = m.uid('fetch', uid, '(RFC822)')
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
        is_new = (dt is not None and dt > CUTOFF)
        uid_int = int(uid.decode())
        if name.upper() == "INBOX" and dt is not None:
            if uid_int > max_uid_seen:
                max_uid_seen = uid_int
                max_date_seen = date_hdr
        if not is_new:
            continue
        msgs.append({
            "uid": uid_int,
            "from": decode(msg.get("From")),
            "to": decode(msg.get("To")),
            "subject": decode(msg.get("Subject")),
            "date": date_hdr,
            "message_id": msg.get("Message-ID"),
            "in_reply_to": msg.get("In-Reply-To"),
            "references": msg.get("References"),
            "body": get_body(msg),
        })
    results[name] = {"messages": msgs}

folder_names = []
for folder_line in folders:
    line = folder_line.decode(errors="replace")
    m2 = re.match(r'^\(.*?\)\s+(?:"(.*?)"|(\S+))\s+(?:"(.*)"|(\S+))$', line)
    if not m2:
        continue
    fname = m2.group(3) if m2.group(3) is not None else m2.group(4)
    folder_names.append(fname)

for fname in folder_names:
    if "drafts" in fname.lower():
        continue
    process_folder(fname)

m.logout()

out = {
    "folder_names": folder_names,
    "results": results,
    "max_uid_seen_inbox": max_uid_seen,
    "max_date_seen_inbox": max_date_seen,
}
print(json.dumps(out, ensure_ascii=False)) if False else None
with open(r"C:\Users\yasir\Documents\Final-Capstone\seo-audit\ticket-sync\run_20260914_out.json", "w", encoding="utf-8") as f:
    _json.dump(out, f, ensure_ascii=False, indent=2)
print("DONE")
print("folders:", folder_names)
print("max_uid_seen_inbox:", max_uid_seen, max_date_seen)
for k, v in results.items():
    print(k, len(v.get("messages", [])), v.get("error", ""))
