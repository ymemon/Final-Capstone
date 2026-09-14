import imaplib, json, email, sys
from email.header import decode_header

with open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json") as f:
    creds = json.load(f)

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])

typ, data = M.list()
print("=== FOLDERS ===")
for line in data:
    print(line.decode(errors="replace"))

M.logout()
