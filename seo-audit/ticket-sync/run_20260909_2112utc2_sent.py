import json, imaplib, email
import email.utils
from email.header import decode_header, make_header
from datetime import datetime, timedelta

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
USER = creds["mail_user"]
PASS = creds["mail_password"]

def dh(v):
    if not v:
        return ""
    try:
        return str(make_header(decode_header(v)))
    except Exception:
        return v

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(USER, PASS)
M.select("Sent", readonly=True)

# checkpoint date: Wed, 09 Sep 2026 06:21:33 -0700 -> use IMAP SINCE with a couple days back to be safe, then filter precisely by parsed date
typ, data = M.search(None, "SINCE", "07-Sep-2026")
uids = data[0].split()

CHECKPOINT_DT = email.utils.parsedate_to_datetime("Wed, 09 Sep 2026 06:21:33 -0700")

results = []
max_seen_dt = CHECKPOINT_DT
for uid in uids:
    typ, msgdata = M.fetch(uid, "(RFC822)")
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    date_hdr = msg.get("Date")
    try:
        msg_dt = email.utils.parsedate_to_datetime(date_hdr)
        if msg_dt.tzinfo is None:
            msg_dt = msg_dt.replace(tzinfo=CHECKPOINT_DT.tzinfo)
    except Exception:
        continue

    if msg_dt <= CHECKPOINT_DT:
        continue

    body_text = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in disp:
                try:
                    payload = part.get_payload(decode=True)
                    charset = part.get_content_charset() or "utf-8"
                    body_text += payload.decode(charset, errors="replace")
                except Exception:
                    pass
    else:
        try:
            payload = msg.get_payload(decode=True)
            charset = msg.get_content_charset() or "utf-8"
            body_text = payload.decode(charset, errors="replace")
        except Exception:
            body_text = str(msg.get_payload())

    results.append({
        "uid": uid.decode(),
        "from": dh(msg.get("From")),
        "to": dh(msg.get("To")),
        "subject": dh(msg.get("Subject")),
        "date": date_hdr,
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body_excerpt": body_text[:2000],
    })
    if msg_dt > max_seen_dt:
        max_seen_dt = msg_dt

M.logout()

out = {"messages": results}
with open("run_20260909_2112utc2_sent.json", "w", encoding="utf-8") as f:
    json.dump(out, f, indent=2, ensure_ascii=False)

print(f"Fetched {len(results)} sent messages after checkpoint")
