import json, imaplib, email
from email.header import decode_header
from email.utils import parsedate_to_datetime, parseaddr

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))

def decode(s):
    if not s:
        return ""
    parts = decode_header(s)
    out = []
    for text, enc in parts:
        if isinstance(text, bytes):
            out.append(text.decode(enc or "utf-8", errors="replace"))
        else:
            out.append(text)
    return "".join(out)

def get_body(msg):
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in disp:
                charset = part.get_content_charset() or "utf-8"
                try:
                    return part.get_payload(decode=True).decode(charset, errors="replace")
                except Exception:
                    return part.get_payload(decode=True).decode("utf-8", errors="replace")
        return ""
    else:
        charset = msg.get_content_charset() or "utf-8"
        try:
            return msg.get_payload(decode=True).decode(charset, errors="replace")
        except Exception:
            return str(msg.get_payload())

CHECKPOINT_DT = parsedate_to_datetime("Mon, 07 Sep 2026 13:01:03 -0700")

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("Sent", readonly=True)

typ, data = M.uid("search", None, "SINCE", "07-Sep-2026")
uids = data[0].split()
print("candidate UIDs:", uids)

results = []
for uid in uids:
    typ, msgdata = M.uid("fetch", uid, "(RFC822)")
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    date_hdr = msg.get("Date")
    try:
        dt = parsedate_to_datetime(date_hdr)
    except Exception:
        continue
    if dt <= CHECKPOINT_DT:
        continue
    to_addr = parseaddr(msg.get("To") or "")[1]
    if to_addr.lower().endswith("@azwebcorp.com"):
        continue
    body = get_body(msg)
    results.append({
        "uid": uid.decode(),
        "from": decode(msg.get("From")),
        "to": decode(msg.get("To")),
        "subject": decode(msg.get("Subject")),
        "date": date_hdr,
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": body[:4000],
    })

M.logout()
with open("run_20260907_2200z_sent.json", "w", encoding="utf-8") as f:
    json.dump(results, f, indent=2, ensure_ascii=False)
print("count:", len(results))
