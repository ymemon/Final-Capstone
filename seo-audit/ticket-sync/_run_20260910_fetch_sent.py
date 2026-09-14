import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import json
import imaplib
import email
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]
CHECKPOINT_DATE = "10-Sep-2026"  # checkpoint date was 2026-09-10 09:40 UTC; search by date then filter precisely by parsed datetime

def dh(v):
    if v is None:
        return ""
    try:
        return str(make_header(decode_header(v)))
    except Exception:
        return v

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)
M.select("Sent", readonly=True)

typ, data = M.uid('search', None, f'SINCE {CHECKPOINT_DATE}')
uids = data[0].split()

results = []
from email.utils import parsedate_to_datetime
import datetime
cutoff = parsedate_to_datetime("Thu, 10 Sep 2026 09:40:02 +0000")

for uid in uids:
    typ, msg_data = M.uid('fetch', uid, '(RFC822)')
    if not msg_data or msg_data[0] is None:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    try:
        mdate = parsedate_to_datetime(msg.get("Date"))
        if mdate.tzinfo is None:
            mdate = mdate.replace(tzinfo=datetime.timezone.utc)
    except Exception:
        mdate = None
    if mdate is not None and mdate <= cutoff:
        continue

    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in disp:
                try:
                    body = part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception:
                    pass
                break
    else:
        try:
            body = msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception:
            body = str(msg.get_payload())

    results.append({
        "uid": int(uid),
        "from": dh(msg.get("From")),
        "to": dh(msg.get("To")),
        "subject": dh(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": body[:3000],
    })

M.logout()

with open("_run_20260910_sent.json", "w", encoding="utf-8") as f:
    json.dump({"count": len(results), "messages": results}, f, indent=2, ensure_ascii=False)

print(f"count={len(results)}")
