import json, imaplib, email, sys
from email.header import decode_header
from datetime import datetime, timedelta
import urllib.request

CREDS_PATH = r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"
API_BASE = "https://azwebcorp.com/wp-json/azwc-tickets/v1"

with open(CREDS_PATH) as f:
    creds = json.load(f)

MAIL_PASSWORD = creds["mail_password"]
API_KEY = creds["ticket_api_key"]

def api_get(path):
    req = urllib.request.Request(API_BASE + path, headers={"X-AZWC-Key": API_KEY, "User-Agent": "Mozilla/5.0 (AZWC-TicketSync)"})
    with urllib.request.urlopen(req) as r:
        return json.loads(r.read().decode())

def decode_str(s):
    if not s:
        return ""
    parts = decode_header(s)
    out = ""
    for text, enc in parts:
        if isinstance(text, bytes):
            out += text.decode(enc or "utf-8", errors="replace")
        else:
            out += text
    return out

checkpoint = api_get("/checkpoint")
print("CHECKPOINT:", checkpoint)

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", MAIL_PASSWORD)

typ, folders = M.list()
print("\n=== FOLDERS ===")
folder_names = []
for f in folders:
    decoded = f.decode(errors="replace")
    print(decoded)
    # parse folder name (last quoted token)
    parts = decoded.split(' "/" ')
    if len(parts) == 2:
        name = parts[1].strip().strip('"')
        folder_names.append(name)

print("\nPARSED FOLDER NAMES:", folder_names)

def fetch_new(folder, checkpoint, use_uid_filter=True):
    # NOTE: UID numbering is per-folder in IMAP, so a checkpoint UID recorded
    # against one folder (INBOX) is meaningless for another folder (Sent).
    # For folders other than the one the checkpoint UID belongs to, filter by
    # date instead.
    try:
        typ, _ = M.select(folder, readonly=True)
    except Exception as e:
        print(f"Could not select {folder}: {e}")
        return []
    if typ != "OK":
        print(f"Could not select {folder}")
        return []
    uid_gt = checkpoint.get("uid") if checkpoint else None
    cp_date = checkpoint.get("date") if checkpoint else None
    if use_uid_filter and uid_gt:
        typ, data = M.uid("search", None, f"UID {int(uid_gt)+1}:*")
    elif cp_date:
        try:
            since_dt = email.utils.parsedate_to_datetime(cp_date)
            since = since_dt.strftime("%d-%b-%Y")
        except Exception:
            since = (datetime.utcnow() - timedelta(days=2)).strftime("%d-%b-%Y")
        typ, data = M.uid("search", None, f"SINCE {since}")
    else:
        since = (datetime.utcnow() - timedelta(days=2)).strftime("%d-%b-%Y")
        typ, data = M.uid("search", None, f"SINCE {since}")
    if typ != "OK" or not data or not data[0]:
        print(f"No messages in {folder}")
        return []
    uids = data[0].split()
    if use_uid_filter and uid_gt:
        uids = [u for u in uids if int(u) > int(uid_gt)]
    if not uids:
        print(f"No messages in {folder} strictly newer than checkpoint")
        return []
    results = []
    for uid in uids:
        typ, msgdata = M.uid("fetch", uid, "(RFC822)")
        if typ != "OK" or not msgdata or msgdata[0] is None:
            continue
        raw = msgdata[0][1]
        msg = email.message_from_bytes(raw)
        body = ""
        if msg.is_multipart():
            for part in msg.walk():
                ctype = part.get_content_type()
                cdispo = str(part.get("Content-Disposition"))
                if ctype == "text/plain" and "attachment" not in cdispo:
                    try:
                        body = part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                    except Exception:
                        pass
                    break
        else:
            try:
                body = msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
            except Exception:
                body = str(msg.get_payload())
        results.append({
            "uid": uid.decode(),
            "from": decode_str(msg.get("From")),
            "to": decode_str(msg.get("To")),
            "subject": decode_str(msg.get("Subject")),
            "date": msg.get("Date"),
            "message_id": msg.get("Message-ID"),
            "in_reply_to": msg.get("In-Reply-To"),
            "references": msg.get("References"),
            "body_snippet": body[:1500],
        })
    return results

print("\n=== INBOX NEW MESSAGES ===")
inbox_msgs = fetch_new("INBOX", checkpoint, use_uid_filter=True)
for m in inbox_msgs:
    print("---")
    for k, v in m.items():
        if k == "body_snippet":
            print(f"{k}: {v[:400]!r}")
        else:
            print(f"{k}: {v}")

# try to find sent folder
sent_candidates = [n for n in folder_names if n.lower() in ("sent", "sent items", "sent messages")]
print("\nSENT CANDIDATES:", sent_candidates)

sent_msgs = []
if sent_candidates:
    print(f"\n=== {sent_candidates[0]} NEW MESSAGES ===")
    sent_msgs = fetch_new(sent_candidates[0], checkpoint, use_uid_filter=False)
    if checkpoint and checkpoint.get("date"):
        try:
            cp_dt = email.utils.parsedate_to_datetime(checkpoint["date"])
            filtered = []
            for m in sent_msgs:
                try:
                    md = email.utils.parsedate_to_datetime(m["date"])
                    if md > cp_dt:
                        filtered.append(m)
                except Exception:
                    filtered.append(m)
            sent_msgs = filtered
        except Exception:
            pass
    for m in sent_msgs:
        print("---")
        for k, v in m.items():
            if k == "body_snippet":
                print(f"{k}: {v[:400]!r}")
            else:
                print(f"{k}: {v}")
else:
    print("No sent folder found among:", folder_names)

M.logout()

# dump full data to json for later use
with open("step1_output.json", "w", encoding="utf-8") as f:
    json.dump({"checkpoint": checkpoint, "folder_names": folder_names, "inbox": inbox_msgs, "sent": sent_msgs, "sent_folder_used": sent_candidates[0] if sent_candidates else None}, f, indent=2)

print("\nSaved full details to step1_output.json")
