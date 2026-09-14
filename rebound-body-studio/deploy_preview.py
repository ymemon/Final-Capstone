# -*- coding: utf-8 -*-
"""Publish the Rebound site to azwebcorp.com/preview/rebound/.

Yasir wants the link he sends a client to start with azwebcorp.com rather than
claude.ai, so the prototype is hosted on the agency's own domain alongside the
existing /preview/prestige-v2/ build.

Every page carries a robots noindex, added by the builder. This is a prototype
for a real local business; Google indexing it would compete with her own listing
and, until recently, would have indexed a placeholder address. It is a meta
noindex rather than a robots.txt Disallow, because a disallowed URL can still be
indexed from a link - the crawler is never allowed to read the noindex.
"""
import io
import os
import subprocess
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
SITE = os.path.join(HERE, "build", "site")
SCP = os.path.expandvars(r"%USERPROFILE%\.claude-tools\scp_run.bat")
SSH = os.path.expandvars(r"%USERPROFILE%\.claude-tools\ssh_run.bat")
REMOTE = "client_9d34da8b_644762@644762.us8.ssh.myftpupload.com"
DEST = "/html/preview/rebound"

# Files used only for local development/operations must never be copied into
# the public web root. In particular, drain_mail_http.bat contains the secret
# for the protected queue endpoint and booking.db contains customer data.
BOOKING_SKIP_DIRS = {"tests", "migrations", "wordpress", "__pycache__"}
BOOKING_SKIP_FILES = {
    "booking.db",
    "DEPLOYMENT.md",
    "README.md",
    "drain_mail_http.bat",
    "drain_mail_http.log",
    "rebound_mail_worker.py",
    "rebound_mail_daemon.py",
    "rebound_mail_worker.log",
    "run_rebound_mail_worker.bat",
    "query('SELECT",
}


def run(cmd):
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.stdout.strip():
        print(r.stdout.strip())
    if r.returncode != 0:
        print(r.stderr.strip())
        sys.exit("FAILED: %s" % (cmd[:2],))
    return r


def main():
    if not os.path.isdir(SITE):
        sys.exit("no build - run build_site.py first")

    pages, assets, booking_files = [], [], []
    for root, _dirs, files in os.walk(SITE):
        for f in files:
            full = os.path.join(root, f)
            rel = os.path.relpath(full, SITE).replace("\\", "/")
            (assets if rel.startswith("assets/") else pages).append((full, rel))

    # Also upload booking system files
    booking_dir = os.path.join(HERE, "booking")
    if os.path.isdir(booking_dir):
        for root, dirs, files in os.walk(booking_dir):
            dirs[:] = [d for d in dirs if d not in BOOKING_SKIP_DIRS]
            for f in files:
                if ((f.startswith(".") and f != ".htaccess")
                        or f.endswith(".pyc")
                        or f in BOOKING_SKIP_FILES):
                    continue
                full = os.path.join(root, f)
                rel = os.path.relpath(full, HERE).replace("\\", "/")
                booking_files.append((full, rel))

    # Every page must carry the noindex, or a prototype for a real business
    # starts competing with her own search listing.
    for full, rel in pages:
        html = io.open(full, encoding="utf-8").read()
        assert 'content="noindex' in html, "missing noindex: %s" % rel
        assert "%(" not in html, "unsubstituted placeholder: %s" % rel
    print("checked %d pages, %d assets, %d booking files" % (len(pages), len(assets), len(booking_files)))

    dirs = sorted({os.path.dirname(rel) for _f, rel in pages + assets + booking_files} - {""})
    run([SSH, "mkdir -p %s %s" % (DEST, " ".join("%s/%s" % (DEST, d) for d in dirs))])

    for full, rel in pages + assets + booking_files:
        run([SCP, full, "%s:%s/%s" % (REMOTE, DEST, rel)])
        print("  uploaded", rel)

    run([SSH, "find %s -type f | sort" % DEST])


if __name__ == "__main__":
    main()
