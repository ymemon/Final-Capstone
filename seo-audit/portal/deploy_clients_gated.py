"""Move the client dashboards behind the sign-in gate.

    python deploy_clients_gated.py            # dry run, prints the plan
    python deploy_clients_gated.py --apply

WHAT THIS CHANGES, AND WHY IT IS NOT RUN AUTOMATICALLY
Today each dashboard is a plain file:

    GET /reports/everythingit/index.html   -> 200, no authentication

The slug is just the client's name, so anyone who guesses one reads that
client's traffic, keywords and AI-crawler data. This script fixes that by
copying each build to /reports/p/<slug>.php behind a guard constant and
deleting the open directory, exactly as the agency view already works.

IT IS A ONE-WAY DOOR UNTIL THE ROSTER IS FILLED IN. The gate fails closed: an
address that is not on the roster reaches nothing. So if the roster is empty
when this runs, every client is locked out with no way back until somebody
adds their address. That is why it is a separate, deliberate step rather than
part of the normal deploy - it should be run when:

  1. ~/.portal-client-state/roster.json lists a real address per client, and
  2. somebody is available to tell those clients the link has changed.

It refuses to run against an empty roster for that reason.
"""

import argparse
import json
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
DIST = HERE / "dist"
SSH = r"C:\Users\yasir\.claude-tools\ssh_run.bat"
REMOTE_REPORTS = "/html/reports"
STATE = "~/.portal-client-state"

GUARD = """<?php
/* Served only through /reports/index.php. A direct request defines nothing,
   so this returns 404 rather than the client's data. */
if (!defined('AZWC_PORTAL_GATE')) { http_response_code(404); exit('Not found'); }
?>
"""


def ssh(cmd: str) -> str:
    return subprocess.run([SSH, cmd], capture_output=True, text=True, timeout=120).stdout.strip()


def remote_roster() -> dict:
    raw = ssh(f"cat {STATE}/roster.json 2>/dev/null")
    try:
        return json.loads(raw) if raw else {}
    except json.JSONDecodeError:
        return {}


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--apply", action="store_true", help="actually move the pages")
    a = ap.parse_args()

    builds = sorted(p for p in DIST.iterdir() if p.is_dir() and p.name != "_owner")
    if not builds:
        sys.exit("no client builds in dist/ - run: python build_portal.py --all")

    roster = remote_roster()
    with_emails = {s: c for s, c in roster.items()
                   if isinstance(c, dict) and c.get("emails")}

    print(f"builds found      : {', '.join(b.name for b in builds)}")
    print(f"roster entries    : {len(roster)}")
    print(f"with an address   : {len(with_emails)}"
          + (f" ({', '.join(with_emails)})" if with_emails else ""))
    print()

    for b in builds:
        page = b / "index.html"
        state = "ok" if page.is_file() else "MISSING index.html"
        gated = "will sign in" if b.name in with_emails else "NO ADDRESS - would be unreachable"
        print(f"  {b.name:<18} {state:<20} {gated}")

    if not with_emails:
        print("\nREFUSING: no client has an address on the roster, so this would take every")
        print("dashboard offline and leave no way back in. Add addresses to")
        print(f"{STATE}/roster.json first.")
        return 1

    missing = [b.name for b in builds if b.name not in with_emails]
    if missing:
        print(f"\nNOTE: {', '.join(missing)} would become unreachable - no address on the roster.")

    if not a.apply:
        print("\nDRY RUN. Re-run with --apply to move the pages behind the gate.")
        return 0

    ssh(f"mkdir -p {REMOTE_REPORTS}/p")
    for b in builds:
        page = b / "index.html"
        if not page.is_file():
            print(f"  skip {b.name}: no build")
            continue
        payload = GUARD + page.read_text(encoding="utf-8")
        tmp = HERE / f".gated-{b.name}.php"
        tmp.write_text(payload, encoding="utf-8")
        subprocess.run(
            f'type "{tmp}" | "{SSH}" "cat > {REMOTE_REPORTS}/p/{b.name}.php"',
            shell=True, check=False,
        )
        tmp.unlink(missing_ok=True)
        print(f"  gated {b.name} -> {REMOTE_REPORTS}/p/{b.name}.php")

    for b in builds:
        ssh(f"rm -rf {REMOTE_REPORTS}/{b.name}")
        print(f"  removed open directory {REMOTE_REPORTS}/{b.name}")

    print("\ndone. Every dashboard is now reachable only through "
          "https://azwebcorp.com/reports/ after signing in.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
