"""Deliver Rebound's server queue through AZWebCorp's authenticated mailbox."""

from __future__ import annotations

import email.utils
import argparse
import imaplib
import json
import logging
from logging.handlers import RotatingFileHandler
from email.message import EmailMessage
from pathlib import Path
import re
import smtplib
import ssl
import sys
import time
import urllib.parse
import urllib.request
import uuid


ROOT = Path(__file__).resolve().parent
ENDPOINT = "https://azwebcorp.com/preview/rebound/booking/cron/drain-http.php"
DRAIN_BATCH = ROOT / "drain_mail_http.bat"
CREDENTIALS = Path.home() / ".claude-tools" / "azwebcorp-creds.json"
SMTP_HOST, SMTP_PORT = "smtpout.secureserver.net", 465
IMAP_HOST, IMAP_PORT = "imap.secureserver.net", 993
MAIL_USER = "info@azwebcorp.com"
LOG_PATH = ROOT / "rebound_mail_worker.log"

logger = logging.getLogger("rebound_mail_worker")
logger.setLevel(logging.INFO)
handler = RotatingFileHandler(LOG_PATH, maxBytes=1_000_000, backupCount=3, encoding="utf-8")
handler.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(message)s"))
logger.addHandler(handler)


def load_secrets() -> tuple[str, str]:
    batch = DRAIN_BATCH.read_text(encoding="utf-8")
    match = re.search(r"[?&]token=([^&\"\s]+)", batch, flags=re.IGNORECASE)
    if not match:
        raise RuntimeError("drain token not found")
    password = str(json.loads(CREDENTIALS.read_text(encoding="utf-8"))["mail_password"])
    if not password:
        raise RuntimeError("mail password not found")
    return match.group(1), password


def post(fields: dict[str, object]) -> dict[str, object]:
    data = urllib.parse.urlencode(fields).encode("utf-8")
    request = urllib.request.Request(
        ENDPOINT,
        data=data,
        headers={
            "Cache-Control": "no-cache",
            "Content-Type": "application/x-www-form-urlencoded",
            "User-Agent": "AZWebCorp-Rebound-Mail-Worker/1.0",
        },
        method="POST",
    )
    with urllib.request.urlopen(request, timeout=30) as response:
        payload = json.loads(response.read().decode("utf-8"))
    if not payload.get("ok"):
        raise RuntimeError("worker endpoint rejected request")
    return payload


def build_message(item: dict[str, object]) -> EmailMessage:
    message = EmailMessage()
    message["From"] = str((item.get("headers") or {}).get("From") or f"Rebound Body Studio <{MAIL_USER}>")
    message["To"] = email.utils.formataddr((str(item.get("to_name") or ""), str(item["to"])))
    message["Subject"] = str(item["subject"])
    reply_to = str((item.get("headers") or {}).get("Reply-To") or "")
    if reply_to:
        message["Reply-To"] = reply_to
    for header in ("List-Unsubscribe", "List-Unsubscribe-Post"):
        value = str((item.get("headers") or {}).get(header) or "")
        if value:
            message[header] = value
    message.set_content(str(item["text"]))
    return message


def file_in_sent(message: EmailMessage, password: str) -> None:
    try:
        with imaplib.IMAP4_SSL(IMAP_HOST, IMAP_PORT) as mailbox:
            mailbox.login(MAIL_USER, password)
            mailbox.append(
                '"Sent"',
                "\\Seen",
                imaplib.Time2Internaldate(time.time()),
                message.as_bytes(),
            )
    except Exception as error:  # Delivery succeeded; filing is secondary.
        logger.warning("sent-copy filing failed: %s", error)


def run(probe: bool = False) -> int:
    token, password = load_secrets()
    if probe:
        payload = post({"token": token, "action": "status"})
        print(json.dumps({"ok": True, "pending": payload.get("pending")}))
        return 0

    claim_token = uuid.uuid4().hex
    payload = post({"token": token, "action": "claim", "claim_token": claim_token})
    result = payload.get("result") or {}
    messages = result.get("messages") or []
    if result.get("skipped"):
        logger.info("skipped %s stale or ineligible message(s)", result["skipped"])

    for item in messages:
        queue_id = int(item["queue_id"])
        try:
            message = build_message(item)
            with smtplib.SMTP_SSL(
                SMTP_HOST,
                SMTP_PORT,
                context=ssl.create_default_context(),
                timeout=60,
            ) as smtp:
                smtp.login(MAIL_USER, password)
                eligibility = post({"token": token, "action": "verify", "queue_id": queue_id, "claim_token": claim_token})
                if not eligibility.get("eligible"):
                    logger.info("queue %s skipped before delivery", queue_id)
                    continue
                smtp.send_message(message, from_addr=MAIL_USER, to_addrs=[str(item["to"])])
            file_in_sent(message, password)
            post({
                "token": token,
                "action": "ack",
                "queue_id": queue_id,
                "claim_token": claim_token,
                "result": "sent",
            })
            logger.info("queue %s delivered and acknowledged", queue_id)
        except Exception as error:
            try:
                post({
                    "token": token,
                    "action": "ack",
                    "queue_id": queue_id,
                    "claim_token": claim_token,
                    "result": "failed",
                    "error": str(error)[:300],
                })
            except Exception as ack_error:
                logger.error("queue %s failed; failure acknowledgement also failed: %s", queue_id, ack_error)
            logger.exception("queue %s delivery failed", queue_id)
            return 1
    return 0


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--probe", action="store_true", help="check the endpoint without claiming or sending mail")
    args = parser.parse_args()
    try:
        raise SystemExit(run(probe=args.probe))
    except Exception:
        logger.exception("worker run failed")
        raise
