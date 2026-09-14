import json, imaplib, email
from email.header import decode_header, make_header

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
USER = creds["mail_user"]
PASS = creds["mail_password"]

CHECKPOINT_UID = 8784

def dh(v):
    if not v:
        return ""
    try:
        return str(make_header(decode_header(v)))
    except Exception:
        return v

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(USER, PASS)
M.select("INBOX", readonly=True)

typ, data = M.uid("search", None, f"UID {CHECKPOINT_UID+1}:*")
uids = data[0].split()

results = []
max_uid = CHECKPOINT_UID
for uid in uids:
    uid_int = int(uid)
    if uid_int <= CHECKPOINT_UID:
        continue
    typ, msgdata = M.uid("fetch", uid, "(RFC822)")
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)

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
        "uid": uid_int,
        "from": dh(msg.get("From")),
        "to": dh(msg.get("To")),
        "subject": dh(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body_excerpt": body_text[:2000],
    })
    max_uid = max(max_uid, uid_int)

M.logout()

out = {"max_uid": max_uid, "messages": results}
with open("run_20260909_2112utc2_inbox.json", "w", encoding="utf-8") as f:
    json.dump(out, f, indent=2, ensure_ascii=False)

print(f"Fetched {len(results)} messages, max_uid={max_uid}")
