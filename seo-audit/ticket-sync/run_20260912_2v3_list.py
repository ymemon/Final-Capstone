import imaplib, json

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
USER = creds["mail_user"]
PW = creds["mail_password"]

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(USER, PW)
typ, data = M.list()
for line in data:
    print(line.decode(errors="replace"))
M.logout()
