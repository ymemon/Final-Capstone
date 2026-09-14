import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib, email, sys, re
from email.header import decode_header
from email.utils import parsedate_to_datetime
from datetime import datetime, timezone, timedelta

HOST = "imap.secureserver.net"
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]

# checkpoint cutoff (exclusive) - messages strictly after this datetime are "new"
CUTOFF = datetime(2026, 9, 1, 12, 6, 28, tzinfo=timezone.utc)
SINCE_DATE = "01-Sep-2026"  # IMAP SINCE is date-granularity; we filter precisely after fetch

def decode(s):
    if s is None:
        return ""
    parts = decode_header(s)
    out = []
    for text, enc in parts:
        if isinstance(text, bytes):
            try:
                out.append(text.decode(enc or "utf-8", errors="replace"))
            except Exception:
                out.append(text.decode("utf-8", errors="replace"))
        else:
            out.append(text)
    return "".join(out)

def get_body(msg, limit=1500):
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in disp:
                try:
                    payload = part.get_payload(decode=True)
                    charset = part.get_content_charset() or "utf-8"
                    return payload.decode(charset, errors="replace")[:limit]
                except Exception:
                    continue
        return "(no plain text part)"
    else:
        try:
            payload = msg.get_payload(decode=True)
            charset = msg.get_content_charset() or "utf-8"
            return payload.decode(charset, errors="replace")[:limit]
        except Exception:
            return "(decode error)"

m = imaplib.IMAP4_SSL(HOST, 993)
m.login(USER, PASS)

typ, folders = m.list()

def process_folder(name):
    try:
        typ, data = m.select(f'"{name}"', readonly=True)
    except Exception as e:
        print(f"COULD NOT SELECT {name}: {e}")
        return
    if typ != "OK":
        print(f"COULD NOT SELECT {name}: {data}")
        return
    typ, data = m.uid('search', None, f'(SINCE {SINCE_DATE})')
    if typ != "OK" or not data or not data[0]:
        print(f"--- {name}: no messages since {SINCE_DATE} ---")
        return
    uids = data[0].split()
    print(f"--- {name}: {len(uids)} candidate UIDs since {SINCE_DATE} ---")
    max_uid_seen = None
    max_date_seen = None
    for uid in uids:
        typ, msgdata = m.uid('fetch', uid, '(RFC822)')
        if typ != "OK" or not msgdata or msgdata[0] is None:
            continue
        raw = msgdata[0][1]
        msg = email.message_from_bytes(raw)
        date_hdr = msg.get("Date")
        try:
            dt = parsedate_to_datetime(date_hdr)
            if dt.tzinfo is None:
                dt = dt.replace(tzinfo=timezone.utc)
        except Exception:
            dt = None
        is_new = (dt is not None and dt > CUTOFF)
        marker = "NEW" if is_new else "old(skip)"
        print(f"\n>>> UID {uid.decode()} in {name} [{marker}] dt={dt}")
        if not is_new:
            continue
        print("From:", decode(msg.get("From")))
        print("To:", decode(msg.get("To")))
        print("Subject:", decode(msg.get("Subject")))
        print("Date:", date_hdr)
        print("Message-ID:", msg.get("Message-ID"))
        print("In-Reply-To:", msg.get("In-Reply-To"))
        print("References:", msg.get("References"))
        body = get_body(msg)
        print("Body snippet:", body[:800].replace("\n", " | "))

for folder_line in folders:
    line = folder_line.decode(errors="replace")
    m2 = re.match(r'^\(.*?\)\s+(?:"(.*?)"|(\S+))\s+(?:"(.*)"|(\S+))$', line)
    if not m2:
        print(f"COULD NOT PARSE FOLDER LINE: {line}")
        continue
    fname = m2.group(3) if m2.group(3) is not None else m2.group(4)
    if "drafts" in fname.lower():
        print(f"SKIPPING (out of scope): {fname}")
        continue
    if fname.upper() in ("INBOX",) or "sent" in fname.lower():
        process_folder(fname)

m.logout()
