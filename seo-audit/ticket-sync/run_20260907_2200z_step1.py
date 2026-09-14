import json, imaplib, email
from email.header import decode_header
from email.utils import parsedate_to_datetime

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

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])

typ, data = M.list()
folders = [d.decode(errors="replace") for d in data]
with open("run_20260907_2200z_folders.json", "w", encoding="utf-8") as f:
    json.dump(folders, f, indent=2, ensure_ascii=False)

M.logout()
print("done")
