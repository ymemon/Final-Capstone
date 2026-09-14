import json, imaplib, email
from email.header import decode_header, make_header

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
USER = creds["mail_user"]
PASS = creds["mail_password"]

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(USER, PASS)
typ, data = M.list()
folders = []
for line in data:
    folders.append(line.decode("utf-8", errors="replace"))
M.logout()

with open("run_20260909_2112utc2_folders.json", "w", encoding="utf-8") as f:
    json.dump(folders, f, indent=2)

print("\n".join(folders))
