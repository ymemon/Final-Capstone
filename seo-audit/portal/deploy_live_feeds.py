"""
Stand up the GA4 realtime feed for each portal property.

    python deploy_live_feeds.py --check        # who is ready, who is not
    python deploy_live_feeds.py everythingit   # deploy one
    python deploy_live_feeds.py --all          # deploy every ready property

WHAT THIS DOES
For each client with a ga4_property in clients.json, it renders
live-feed.php.tpl with that property id and a per-property cache path, uploads
it to html/reports/<slug>/api/live.php, and copies the shared service-account
key alongside it. The key is copied server-side and never passes through this
machine.

WHAT IT CANNOT DO
Grant the service account access. That is a click in each GA4 property's admin
screen and only the property's owner can do it:

    Google Analytics -> Admin -> (select the property) -> Property access
    management -> + -> add
        gsc-reader@azwebcorp-gsc-77313.iam.gserviceaccount.com
    with the Viewer role, and untick "Notify new users by email".

--check reports exactly which properties still need that, by calling the
realtime API as the service account and reading back what it says.
"""

import argparse
import json
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
TPL = HERE / "live-feed.php.tpl"
CLIENTS_FILE = HERE / "clients.json"
STAGE = HERE / ".deploy-live"
CREDS = Path(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json")

SSH = r"C:\Users\yasir\.claude-tools\ssh_run.bat"
SCP = r"C:\Users\yasir\.claude-tools\scp_run.bat"

# The one service account every property's feed authenticates as. Its key
# already lives on the server for azwebcorp; other properties get a copy.
KEY_SOURCE = "~/html/reports/azwebcorp/api/ga4-key.php"
# The globe's country lookup. Without it the feed still returns city names but
# locations[] comes back empty and no pin is ever drawn.
CENTROIDS_SOURCE = "~/html/reports/azwebcorp/api/country-centroids.php"
SERVICE_ACCOUNT = "gsc-reader@azwebcorp-gsc-77313.iam.gserviceaccount.com"


def remote():
    if not CREDS.exists():
        sys.exit(f"missing {CREDS} - it holds portal_deploy_remote")
    t = json.loads(CREDS.read_text(encoding="utf-8")).get("portal_deploy_remote")
    if not t:
        sys.exit("no 'portal_deploy_remote' in the credentials file")
    return t


def clients():
    return {k: v for k, v in json.loads(CLIENTS_FILE.read_text(encoding="utf-8")).items()
            if not k.startswith("_")}


def sh(cmd, capture=False):
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.returncode != 0 and not capture:
        sys.exit(f"FAILED: {cmd[0]}\n{r.stdout}\n{r.stderr}")
    return (r.stdout or "") + (r.stderr or "")


def deploy(slug, cfg, target):
    prop = str(cfg.get("ga4_property") or "").strip()
    if not prop:
        print(f"  {slug:<18} SKIPPED - no ga4_property in clients.json yet")
        return False

    STAGE.mkdir(exist_ok=True)
    php = (TPL.read_text(encoding="utf-8")
           .replace("__PORTAL_SLUG__", slug)
           .replace("__GA4_PROPERTY__", prop))
    for token in ("__PORTAL_SLUG__", "__GA4_PROPERTY__"):
        if token in php:
            sys.exit(f"{token} survived rendering for {slug}")
    out = STAGE / f"live-{slug}.php"
    out.write_text(php, encoding="utf-8", newline="\n")

    sh([SSH, f"mkdir -p ~/html/reports/{slug}/api"])
    sh([SCP, str(out), f"{target}:html/reports/{slug}/api/live.php"])
    # Copy the key server-side: the private key has no reason to travel.
    sh([SSH, f"cp {KEY_SOURCE} ~/html/reports/{slug}/api/ga4-key.php "
             f"&& cp {CENTROIDS_SOURCE} ~/html/reports/{slug}/api/country-centroids.php "
             f"&& chmod 644 ~/html/reports/{slug}/api/ga4-key.php "
             f"~/html/reports/{slug}/api/country-centroids.php"])
    print(f"  {slug:<18} deployed (GA4 property {prop})")
    return True


def check(slug, cfg):
    """Ask the deployed endpoint what it can actually see. This is the only
    honest way to tell 'access granted' from 'looks configured'."""
    import urllib.error
    import urllib.request
    import time
    url = f"https://azwebcorp.com/reports/{slug}/api/live.php?cb={int(time.time())}"
    try:
        req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
        body = urllib.request.urlopen(req, timeout=30).read().decode("utf-8", "replace")
        d = json.loads(body)
    except urllib.error.HTTPError as e:
        return f"HTTP {e.code} - endpoint not deployed"
    except Exception as e:
        return f"unreachable ({e})"
    if d.get("error"):
        return f"NEEDS ACCESS - {d['error']}"
    return (f"OK - {d.get('activeUsers', 0)} active now, "
            f"geo {'on' if d.get('geoOk') else 'off'}")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("client", nargs="?")
    ap.add_argument("--all", action="store_true")
    ap.add_argument("--check", action="store_true", help="report status, deploy nothing")
    a = ap.parse_args()

    cs = clients()

    if a.check:
        print(f"Service account: {SERVICE_ACCOUNT}\n")
        for slug, cfg in cs.items():
            if cfg.get("pending"):
                continue
            prop = cfg.get("ga4_property")
            state = check(slug, cfg) if prop else "no ga4_property set yet"
            print(f"  {slug:<18} {'prop ' + str(prop) if prop else '':<16} {state}")
        return

    targets = [a.client] if a.client else (list(cs) if a.all else [])
    if not targets:
        sys.exit("pass a client slug, --all, or --check")

    target = remote()
    print("-- deploying live feeds --")
    done = [slug for slug in targets
            if slug in cs and not cs[slug].get("pending") and deploy(slug, cs[slug], target)]

    if done:
        print("\nNow grant the service account Viewer on each GA4 property:")
        print(f"  {SERVICE_ACCOUNT}")
        print("  Analytics -> Admin -> Property access management -> + -> Viewer")
        print("\nThen: python deploy_live_feeds.py --check")


if __name__ == "__main__":
    main()
