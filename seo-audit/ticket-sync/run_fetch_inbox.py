import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
from email.header import decode_header, make_header

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]
CHECKPOINT_UID = 8660

def decode(s):
    if s is None:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)
M.select("INBOX", readonly=True)

typ, data = M.uid('search', None, f'UID {CHECKPOINT_UID+1}:*')
uids = data[0].split()
print(f"Found {len(uids)} candidate UIDs (may include boundary dup): {uids}")

max_uid = CHECKPOINT_UID
max_date = None

for uid in uids:
    uid_int = int(uid)
    if uid_int <= CHECKPOINT_UID:
        continue
    typ, msg_data = M.uid('fetch', uid, '(RFC822)')
    if not msg_data or msg_data[0] is None:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    from_ = decode(msg.get("From"))
    to_ = decode(msg.get("To"))
    subj = decode(msg.get("Subject"))
    date_ = msg.get("Date")
    msgid = msg.get("Message-ID")
    inreply = msg.get("In-Reply-To")
    refs = msg.get("References")

    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            cdispo = str(part.get('Content-Disposition'))
            if ctype == 'text/plain' and 'attachment' not in cdispo:
                try:
                    charset = part.get_content_charset() or 'utf-8'
                    body = part.get_payload(decode=True).decode(charset, errors='replace')
                except Exception as e:
                    body = f"[decode error: {e}]"
                break
    else:
        try:
            charset = msg.get_content_charset() or 'utf-8'
            body = msg.get_payload(decode=True).decode(charset, errors='replace')
        except Exception as e:
            body = f"[decode error: {e}]"

    body_snip = body.strip().replace('\r\n', '\n')[:1500]

    print("=" * 80)
    print(f"UID: {uid_int}")
    print(f"From: {from_}")
    print(f"To: {to_}")
    print(f"Subject: {subj}")
    print(f"Date: {date_}")
    print(f"Message-ID: {msgid}")
    print(f"In-Reply-To: {inreply}")
    print(f"References: {refs}")
    print(f"Body snippet:\n{body_snip}")
    print()

    if uid_int > max_uid:
        max_uid = uid_int
        max_date = date_

print("=" * 80)
print(f"MAX_UID: {max_uid}")
print(f"MAX_DATE: {max_date}")

M.logout()
