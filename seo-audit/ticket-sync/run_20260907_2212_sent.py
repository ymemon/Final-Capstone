import imaplib, email, json
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))

def dec(s):
    if not s:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

def get_body(msg):
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in disp:
                try:
                    return part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception:
                    return ""
        return ""
    else:
        try:
            return msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception:
            return ""

CHECKPOINT_DT = parsedate_to_datetime("Mon, 07 Sep 2026 18:30:29 -0700")

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("Sent", readonly=True)

typ, data = M.search(None, "SINCE", "07-Sep-2026")
ids = data[0].split() if data[0] else []
print("Sent candidates (SINCE 07-Sep-2026):", ids)

results = []
for i in ids:
    typ, msgdata = M.fetch(i, "(RFC822)")
    if typ != "OK" or not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    try:
        dt = parsedate_to_datetime(msg.get("Date"))
    except Exception:
        continue
    if dt <= CHECKPOINT_DT:
        continue
    body = get_body(msg)
    results.append({
        "id": i.decode(),
        "from": dec(msg.get("From")),
        "to": dec(msg.get("To")),
        "subject": dec(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": body[:2000],
    })

M.logout()

with open("run_20260907_2212_sent.json", "w", encoding="utf-8") as f:
    json.dump(results, f, indent=2, ensure_ascii=False)

print("Saved", len(results), "messages newer than checkpoint")
