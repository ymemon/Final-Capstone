import imaplib, json
creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("INBOX", readonly=True)
typ, data = M.uid("search", None, "ALL")
uids = [int(u) for u in data[0].split()]
print("count:", len(uids))
print("max uid:", max(uids) if uids else None)
print("last 10:", sorted(uids)[-10:])
M.logout()
