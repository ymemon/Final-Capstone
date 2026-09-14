import imaplib, json, email
from email.header import decode_header

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
pw = creds["mail_password"]
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", pw)
M.select("INBOX", readonly=True)

def dec(s):
    if not s:
        return ""
    parts = decode_header(s)
    out = ""
    for text, enc in parts:
        if isinstance(text, bytes):
            out += text.decode(enc or "utf-8", errors="replace")
        else:
            out += text
    return out

typ, data = M.uid('search', None, 'UID', '4092:*')
uids = [u for u in data[0].split()]
print("UIDS FOUND (raw range query):", uids)

# confirm against UIDNEXT / ALL to avoid the edge-case artifact seen in prior runs
typ2, alldata = M.uid('search', None, 'ALL')
all_uids = [int(u) for u in alldata[0].split()]
max_uid = max(all_uids) if all_uids else None
print("Max UID in mailbox:", max_uid)

real_new = [u for u in uids if int(u) > 4091]
print("Confirmed new UIDs:", real_new)

results = []
for uid in real_new:
    typ, msgdata = M.uid('fetch', uid, '(RFC822)')
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

json.dump(results, open("_run339_inbox_out.json", "w", encoding="utf-8"), indent=2)
print("Wrote", len(results), "messages")
M.logout()
