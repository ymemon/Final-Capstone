import json, imaplib, email
from email.header import decode_header, make_header

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
pw = creds["mail_password"]

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", pw)

typ, data = M.list()
folders = []
for line in data:
    s = line.decode()
    folders.append(s)

CHECKPOINT_UID = 8776

def dec(s):
    if not s:
        return ""
    return str(make_header(decode_header(s)))

results = {"folders": folders, "inbox_new": [], "sent_new": []}

typ, _ = M.select("INBOX", readonly=True)
typ, msgnums = M.uid("search", None, f"UID {CHECKPOINT_UID+1}:*")
uids = msgnums[0].split() if msgnums[0] else []
# filter out checkpoint uid itself in case search includes boundary
for uid in uids:
    uid_i = int(uid)
    if uid_i <= CHECKPOINT_UID:
        continue
    typ, msgdata = M.uid("fetch", uid, "(RFC822)")
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    results["inbox_new"].append({
        "uid": uid_i,
        "from": dec(msg.get("From")),
        "to": dec(msg.get("To")),
        "subject": dec(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
    })

M.close()
M.logout()

with open("run_20260909_0409utc_out.json", "w", encoding="utf-8") as f:
    json.dump(results, f, indent=2, ensure_ascii=False)

print(json.dumps({"folders": folders, "inbox_new_count": len(results["inbox_new"])}, indent=2, ensure_ascii=False))
