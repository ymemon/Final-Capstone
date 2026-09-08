"""
Deploy the agency (owner) copies of the portal behind the PHP sign-in gate.

    python deploy_owner.py            # build outputs must already exist
    python deploy_owner.py --dry-run

Layout it produces on the server, all under html/reports/_owner/:

    index.php      the gate (owner-gate.php)
    auth.php       returns ['salt' => ..., 'hash' => ...]
    p/<slug>.php   a built page, prefixed with a guard line

The guard is what keeps a page from being fetched directly. Requested on its
own the file executes, finds AZWC_OWNER_GATE undefined, and 404s; only the gate
defines it. This shape is forced by the host: .htaccess auth is ignored here,
and PHP cannot read anything outside the webroot (the GA4 feed keeps its key in
api/ga4-key.php for the same reason).
"""

import argparse
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
DIST_OWNER = HERE / "dist" / "_owner"
STAGE = HERE / ".deploy-owner"

SSH = r"C:\Users\yasir\.claude-tools\ssh_run.bat"
SCP = r"C:\Users\yasir\.claude-tools\scp_run.bat"
REMOTE_DIR = "html/reports/_owner"

# The deploy target is a hosting account name plus a host - half a credential
# pair - and this repository is public, so it lives with the other secrets
# rather than in source. See azwebcorp-creds.json.
CREDS = Path(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json")


def remote():
    import json
    if not CREDS.exists():
        sys.exit(f"missing {CREDS} - it holds portal_deploy_remote")
    target = json.loads(CREDS.read_text(encoding="utf-8")).get("portal_deploy_remote")
    if not target:
        sys.exit(f"{CREDS} has no 'portal_deploy_remote' entry")
    return target

GUARD = ("<?php if (!defined('AZWC_OWNER_GATE')) { http_response_code(404); "
         "exit('Not found'); } ?>\n")


def run(cmd, dry):
    print("   $", " ".join(str(c) for c in cmd[:2]), "...")
    if dry:
        return
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.returncode != 0:
        sys.exit(f"FAILED: {' '.join(str(c) for c in cmd)}\n{r.stdout}\n{r.stderr}")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry-run", action="store_true")
    a = ap.parse_args()

    auth = HERE / "owner-auth.local"
    if not auth.exists():
        sys.exit("missing owner-auth.local - generate the owner password first")
    salt, digest = auth.read_text(encoding="utf-8").strip().split(":", 1)

    pages = sorted(p for p in DIST_OWNER.iterdir() if (p / "index.html").exists())
    if not pages:
        sys.exit(f"no owner builds in {DIST_OWNER} - run: python build_portal.py --all --owner")

    STAGE.mkdir(exist_ok=True)
    (STAGE / "auth.php").write_text(
        "<?php\n"
        "// Salted SHA-256 of the agency portal password. Executed, never served:\n"
        "// a direct request for this file returns an empty body.\n"
        "return ['salt' => '%s', 'hash' => '%s'];\n" % (salt, digest),
        encoding="utf-8", newline="\n")

    staged = []
    for p in pages:
        html = (p / "index.html").read_text(encoding="utf-8")
        if "<?" in html:
            sys.exit(f"{p.name}: built page contains '<?' and cannot be wrapped as PHP")
        out = STAGE / f"{p.name}.php"
        out.write_text(GUARD + html, encoding="utf-8", newline="\n")
        staged.append(out)
        print(f"   staged {p.name:<18} {out.stat().st_size // 1024:>4} KB")

    target = remote()
    print("\n-- deploying --")
    run([SSH, f"mkdir -p ~/{REMOTE_DIR}/p"], a.dry_run)
    run([SCP, str(HERE / "owner-gate.php"), f"{target}:{REMOTE_DIR}/index.php"], a.dry_run)
    run([SCP, str(STAGE / "auth.php"), f"{target}:{REMOTE_DIR}/auth.php"], a.dry_run)
    for f in staged:
        run([SCP, str(f), f"{target}:{REMOTE_DIR}/p/{f.name}"], a.dry_run)

    # Nothing here should ever have been written outside the webroot; clear the
    # earlier attempt so no stale copy of every client's data is left lying about.
    run([SSH, "rm -rf ~/portal-owner ~/.portal-owner-auth"], a.dry_run)
    print("\ndone -> https://azwebcorp.com/reports/_owner/")


if __name__ == "__main__":
    main()
