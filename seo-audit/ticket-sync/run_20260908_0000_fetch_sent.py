import json, imaplib, email, datetime
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
CHECKPOINT_DATE = parsedate_to_datetime("Mon, 7 Sep 2026 21:33:53 +0000")

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("Sent", readonly=True)

# SENTSINCE not universally supported reliably across servers with UID mapping;
# fetch by date near checkpoint then filter precisely by actual Date header.
typ, data = M.uid("search", None, "SINCE", "07-Sep-2026")
uids = data[0].split()

results = []
for uid in uids:
    typ, msgdata = M.uid("fetch", uid, "(RFC822)")
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)

    def dec(h):
        if h is None:
            return ""
        try:
            return str(make_header(decode_header(h)))
        except Exception:
            return h

    date_hdr = msg.get("Date")
    try:
        msg_dt = parsedate_to_datetime(date_hdr)
    except Exception:
        continue

    if msg_dt <= CHECKPOINT_DATE:
        continue

    body_text = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in disp:
                try:
                    body_text = part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception:
                    pass
                break
    else:
        try:
            body_text = msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception:
            body_text = str(msg.get_payload())

    results.append({
        "uid": uid.decode(),
        "from": dec(msg.get("From")),
        "to": dec(msg.get("To")),
        "subject": dec(msg.get("Subject")),
        "date": date_hdr,
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body_excerpt": body_text[:1500],
    })

M.logout()
print(json.dumps({"count": len(results), "messages": results}, indent=2, ensure_ascii=False))
