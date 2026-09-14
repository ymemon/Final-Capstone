import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
import json
from email.header import decode_header, make_header
from datetime import datetime, timedelta, timezone

CHECKPOINT_DATE = "Fri, 28 Aug 2026 11:03:16 +0000"

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", _AZWC["mail_password"])

def dec(h):
    if h is None:
        return ""
    try:
        return str(make_header(decode_header(h)))
    except Exception:
        return h

def get_body(msg):
    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            cdispo = str(part.get("Content-Disposition"))
            if ctype == "text/plain" and "attachment" not in cdispo:
                try:
                    body = part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception as e:
                    body = f"<error decoding: {e}>"
                break
    else:
        try:
            body = msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception as e:
            body = f"<error decoding: {e}>"
    return body

M.select("Sent", readonly=True)

# search since checkpoint date - 1 day to be safe, IMAP SINCE is date-only granularity
since_dt = datetime(2026, 8, 27)
since_str = since_dt.strftime("%d-%b-%Y")
typ, data = M.search(None, f'(SINCE {since_str})')
uids = data[0].split()
print(f"=== SENT: {len(uids)} candidate messages since {since_str} ===")

results = []
for num in uids:
    typ, msg_data = M.fetch(num, '(RFC822)')
    if not msg_data or msg_data[0] is None:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    body = get_body(msg)
    entry = {
        "num": num.decode(),
        "from": dec(msg.get("From")),
        "to": dec(msg.get("To")),
        "subject": dec(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": body[:1500],
    }
    results.append(entry)

with open("sent_new.json", "w", encoding="utf-8") as f:
    json.dump(results, f, indent=2, ensure_ascii=False)

print(json.dumps(results, indent=2, ensure_ascii=False))

M.logout()
