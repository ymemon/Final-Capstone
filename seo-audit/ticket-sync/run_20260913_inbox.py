import json, imaplib, email
from email.header import decode_header, make_header

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
CHECKPOINT_UID = 4087

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("INBOX", readonly=True)

typ, data = M.uid("search", None, f"UID {CHECKPOINT_UID+1}:*")
uids = [u for u in data[0].split() if int(u) > CHECKPOINT_UID]
print("UIDs found:", uids)

def dec(v):
    if v is None:
        return ""
    try:
        return str(make_header(decode_header(v)))
    except Exception:
        return v

results = []
for uid in uids:
    typ, msgdata = M.uid("fetch", uid, "(RFC822)")
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)

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
        "uid": uid.decode(),
        "from": dec(msg.get("From")),
        "to": dec(msg.get("To")),
        "subject": dec(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": body[:1500],
    })

M.logout()

with open("run_20260913_inbox_out.json", "w", encoding="utf-8") as f:
    json.dump(results, f, indent=2, ensure_ascii=False)

print("Total messages:", len(results))
