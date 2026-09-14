import imaplib, json
creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("INBOX", readonly=True)
typ, data = M.uid("search", None, "ALL")
uids = data[0].split()
print("Max INBOX UID:", max(int(u) for u in uids) if uids else None)
M.logout()
