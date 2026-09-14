import json, imaplib, email
from email.utils import parsedate_to_datetime, parseaddr

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("Sent", readonly=True)

typ, data = M.uid("search", None, "ALL")
uids = data[0].split()
print("total UIDs in Sent:", len(uids), uids[-10:])

for uid in uids[-15:]:
    typ, msgdata = M.uid("fetch", uid, "(RFC822.HEADER)")
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    print(uid.decode(), "|", msg.get("Date"), "|", msg.get("To"), "|", msg.get("Subject"))

M.logout()
