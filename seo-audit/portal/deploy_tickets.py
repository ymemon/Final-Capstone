"""Deploy the ticket-system backend (portal/tickets/) alongside the existing
gated portal.

    python deploy_tickets.py            # dry run, prints the plan
    python deploy_tickets.py --apply

WHERE THINGS LAND, AND WHY THERE ARE TWO COPIES OF SOME FILES
Client-facing files go beside client-gate.php's index.php
(html/reports/), and agent-facing files go beside owner-gate.php's index.php
(html/reports/_owner/) - see tickets/session_lib.php's doc comment for why
that placement is what lets each API read the matching gate's session.
agent_api.php needs its own copies of db.php/session_lib.php/sms_notify.php
in _owner/ because __DIR__-relative requires resolve against wherever the
script actually is. This is not wasteful duplication in practice: db.php
resolves its SQLite path from $HOME (not from __DIR__), so both copies read
and write the exact same database - see db.php's own doc comment.

This does NOT rebuild or redeploy template.html/dist/*/index.html - run
build_portal.py and deploy_clients_gated.py / deploy_owner.py separately for
that, the same as any other template change.
"""

import argparse
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
SRC = HERE / "tickets"
SSH = r"C:\Users\yasir\.claude-tools\ssh_run.bat"
SCP = r"C:\Users\yasir\.claude-tools\scp_run.bat"
CREDS = Path(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json")

REMOTE_CLIENT = "html/reports"
REMOTE_OWNER = "html/reports/_owner"

# state_lib.php lives one level up (portal/), a sibling of client-gate.php,
# not inside tickets/ - it's shared by both, not ticket-specific.
STATE_LIB = HERE / "state_lib.php"

# (source path, deployed filename) - ticket_api.php and agent_api.php are
# both deployed as "tickets_api.php" so the SAME relative
# fetch('tickets_api.php') in template.html hits the right one depending on
# which gate served the page. state_lib.php is deployed to BOTH directories
# so each resolves reports_root() from its own __DIR__ - see that file's
# doc comment for why both copies still agree on one shared _state/ dir.
CLIENT_FILES = [
    (STATE_LIB, "state_lib.php"),
    (SRC / "schema.sql", "schema.sql"),
    (SRC / "db.php", "db.php"),
    (SRC / "session_lib.php", "session_lib.php"),
    (SRC / "categories.php", "categories.php"),
    (SRC / "ticket_lib.php", "ticket_lib.php"),
    (SRC / "sms_notify.php", "sms_notify.php"),
    (SRC / "tier1_actions.php", "tier1_actions.php"),
    (SRC / "sms_webhook.php", "sms_webhook.php"),
    (SRC / "ticket_api.php", "tickets_api.php"),
]
OWNER_FILES = [
    (STATE_LIB, "state_lib.php"),
    (SRC / "schema.sql", "schema.sql"),
    (SRC / "db.php", "db.php"),
    (SRC / "session_lib.php", "session_lib.php"),
    (SRC / "sms_notify.php", "sms_notify.php"),
    (SRC / "agent_api.php", "tickets_api.php"),
]


def remote() -> str:
    import json
    if not CREDS.exists():
        sys.exit(f"missing {CREDS} - it holds portal_deploy_remote")
    target = json.loads(CREDS.read_text(encoding="utf-8")).get("portal_deploy_remote")
    if not target:
        sys.exit(f"{CREDS} has no 'portal_deploy_remote' entry")
    return target


def run(cmd, dry):
    print("   $", " ".join(str(c) for c in cmd))
    if dry:
        return
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.returncode != 0:
        sys.exit(f"FAILED: {' '.join(str(c) for c in cmd)}\n{r.stdout}\n{r.stderr}")


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--apply", action="store_true")
    a = ap.parse_args()

    for src, _ in CLIENT_FILES + OWNER_FILES:
        if not src.exists():
            sys.exit(f"missing {src}")

    target = remote()
    print(f"deploying to {target}\n")

    run([SSH, f"mkdir -p ~/{REMOTE_CLIENT} ~/{REMOTE_OWNER}"], not a.apply)

    print("\n-- client-facing (html/reports/) --")
    for src, dest in CLIENT_FILES:
        run([SCP, str(src), f"{target}:{REMOTE_CLIENT}/{dest}"], not a.apply)

    print("\n-- agent-facing (html/reports/_owner/) --")
    for src, dest in OWNER_FILES:
        run([SCP, str(src), f"{target}:{REMOTE_OWNER}/{dest}"], not a.apply)

    if not a.apply:
        print("\nDRY RUN. Re-run with --apply to actually push these files.")
        print("Nothing here creates the twilio_creds state entry - see tickets/README.md.")
        return 0

    print("\ndone. tickets_api.php is live at both "
          f"https://azwebcorp.com/reports/tickets_api.php and "
          f"https://azwebcorp.com/reports/_owner/tickets_api.php")
    print("SMS will no-op (logged as 'unconfigured') until the 'twilio_creds' "
          "state entry exists (see tickets/README.md).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
