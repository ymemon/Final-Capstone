import imaplib, json
from email.header import decode_header, make_header

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))

def dec(s):
    if not s:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
typ, data = M.list()
folders = []
for line in data:
    folders.append(dec(line.decode() if isinstance(line, bytes) else line))
M.logout()

with open("run_20260907_2151utc_folders.json", "w", encoding="utf-8") as f:
    json.dump(folders, f, indent=2, ensure_ascii=False)

for x in folders:
    print(x)
