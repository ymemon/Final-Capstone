import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
from email.header import decode_header
from email.utils import parsedate_to_datetime
import json

CHECKPOINT_DATE_STR = "Tue, 1 Sep 2026 10:02:39 +0000"
cutoff_dt = parsedate_to_datetime(CHECKPOINT_DATE_STR)

def decode_str(s):
    if not s:
        return ""
    parts = decode_header(s)
    out = ""
    for text, enc in parts:
        if isinstance(text, bytes):
            try:
                out += text.decode(enc or "utf-8", errors="replace")
            except LookupError:
                out += text.decode("utf-8", errors="replace")
        else:
            out += text
    return out

def get_body(msg):
    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            cdispo = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in cdispo:
                try:
                    payload = part.get_payload(decode=True)
                    charset = part.get_content_charset() or "utf-8"
                    body += payload.decode(charset, errors="replace")
                except Exception as e:
                    body += f"[error decoding part: {e}]"
    else:
        try:
            payload = msg.get_payload(decode=True)
            charset = msg.get_content_charset() or "utf-8"
            body = payload.decode(charset, errors="replace")
        except Exception as e:
            body = f"[error decoding: {e}]"
    return body[:2000]

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", _AZWC["mail_password"])
M.select("Sent", readonly=True)

since_str = cutoff_dt.strftime("%d-%b-%Y")
typ, data = M.uid('search', None, f'SINCE {since_str}')
uids = data[0].split() if data and data[0] else []

results = []
for uid in uids:
    typ, msgdata = M.uid('fetch', uid, '(RFC822)')
    if typ != "OK" or not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    date_hdr = msg.get("Date")
    try:
        msg_dt = parsedate_to_datetime(date_hdr)
        if msg_dt.tzinfo is None:
            continue
    except Exception:
        continue
    if msg_dt <= cutoff_dt:
        continue
    entry = {
        "uid": int(uid),
        "folder": "Sent",
        "from": decode_str(msg.get("From")),
        "to": decode_str(msg.get("To")),
        "subject": decode_str(msg.get("Subject")),
        "date": date_hdr,
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": get_body(msg),
    }
    results.append(entry)

M.logout()

with open("new_sent2.json", "w", encoding="utf-8") as f:
    json.dump(results, f, indent=2, ensure_ascii=False)

print(f"Sent: {len(results)} new messages (date-filtered, after {cutoff_dt.isoformat()})")
