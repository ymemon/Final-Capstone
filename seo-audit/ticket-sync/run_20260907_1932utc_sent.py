import json, imaplib, email, datetime
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
pw = creds["mail_password"]

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", pw)

CHECKPOINT_DATE = parsedate_to_datetime("Mon, 7 Sep 2026 12:29:48 +0000")

def dh(s):
    if not s:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

M.select("Sent", readonly=True)
# search since checkpoint date (IMAP SINCE is date-granularity only, so we'll filter precisely after)
typ, data = M.search(None, 'SINCE', "07-Sep-2026")
uids = data[0].split() if data[0] else []
print("Sent candidate seq nums (since 07-Sep-2026):", uids)

results = []
for num in uids:
    typ, msg_data = M.fetch(num, "(RFC822)")
    if not msg_data or not msg_data[0]:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    try:
        msg_date = parsedate_to_datetime(msg.get("Date"))
    except Exception:
        continue
    if msg_date <= CHECKPOINT_DATE:
        continue
    to = dh(msg.get("To"))
    if "azwebcorp.com" in to.lower() or "shopazwebcorp.com" in to.lower():
        continue

    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            if part.get_content_type() == "text/plain" and not part.get("Content-Disposition"):
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
        "seq": num.decode(),
        "from": dh(msg.get("From")),
        "to": to,
        "subject": dh(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body_snippet": body[:1500],
    })

print(json.dumps({"sent_new": results}, indent=2, ensure_ascii=False))
M.logout()
