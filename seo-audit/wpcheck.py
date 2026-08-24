#!/usr/bin/env python3
"""
WordPress REST API auth diagnostic for azwebcorp.com.

Figures out exactly why authentication is failing by reading the actual
error code WordPress returns, which PowerShell/curl hide by default.

Run:  python wpcheck.py
"""
import base64
import json
import os
import sys
import urllib.error
import urllib.request

SITE = "https://azwebcorp.com"
USERNAME = "admin"
APP_PASSWORD = "P8kz OExK QxNJ WoBB P7A4 tTrm"

# Everything printed also gets saved here, so the output survives the
# console window closing (Windows closes it instantly on double-click).
OUTPUT_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)),
                           "wpcheck-output.txt")
_lines = []


def log(msg=""):
    print(msg)
    _lines.append(str(msg))


def request(path, use_auth=True):
    """Return (status_code, parsed_body_or_text)."""
    url = SITE.rstrip("/") + path
    req = urllib.request.Request(url)
    req.add_header("User-Agent", "wpcheck/1.0")
    if use_auth:
        token = base64.b64encode(f"{USERNAME}:{APP_PASSWORD}".encode()).decode()
        req.add_header("Authorization", "Basic " + token)
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            return resp.status, json.loads(resp.read().decode())
    except urllib.error.HTTPError as e:
        raw = e.read().decode(errors="replace")
        try:
            return e.code, json.loads(raw)
        except ValueError:
            return e.code, raw
    except Exception as e:
        return None, f"{type(e).__name__}: {e}"


def main():
    log("=" * 68)
    log(f"Site:     {SITE}")
    log(f"Username: {USERNAME}")
    log(f"Password: {len(APP_PASSWORD)} chars "
          f"({len(APP_PASSWORD.replace(' ', ''))} without spaces)")
    log("=" * 68)

    log("\n[1] REST API reachable at all? (no auth)")
    status, body = request("/wp-json/", use_auth=False)
    if status == 200:
        name = body.get("name") if isinstance(body, dict) else "?"
        log(f"    OK  {status} - REST API is live. Site name: {name}")
    else:
        log(f"    FAIL {status} - {str(body)[:300]}")
        log("\n    The REST API itself isn't responding. It may be disabled")
        log("    by a security plugin. Nothing else here will work until")
        log("    that's resolved.")
        return

    log("\n[2] Authenticated request (the real test)")
    status, body = request("/wp-json/wp/v2/users/me")
    code = body.get("code") if isinstance(body, dict) else None

    if status == 200:
        log(f"    OK  200 - authenticated as: {body.get('name')} "
              f"(id={body.get('id')}, slug={body.get('slug')})")
        log("\n[3] Listing existing drafts")
        status, drafts = request("/wp-json/wp/v2/pages?status=draft&per_page=50")
        if status == 200 and isinstance(drafts, list):
            if not drafts:
                log("    No drafts found - the pages were never created.")
                log("    Next: run publish_all_pages.py")
            else:
                log(f"    Found {len(drafts)} draft(s):")
                for d in drafts:
                    log(f"      #{d['id']:<6} {d['slug']}")
        else:
            log(f"    {status} - {str(drafts)[:300]}")
        log("\nAUTH WORKS. Everything is ready to go.")
        return

    log(f"    FAIL {status} - code: {code}")
    log(f"    message: {body.get('message') if isinstance(body, dict) else str(body)[:300]}")

    log("\n" + "=" * 68)
    log("DIAGNOSIS")
    log("=" * 68)

    if code == "rest_not_logged_in":
        log("""
The Authorization header is being STRIPPED before it reaches WordPress.
WordPress never saw any credentials at all.

This is standard behavior on GoDaddy Managed WordPress (Apache/CGI drops
the header unless told to pass it through). It is NOT a bad password.

Fix - add this to the TOP of .htaccess in your WordPress root, via SSH:

    SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1

Or the equivalent rewrite rule:

    RewriteEngine On
    RewriteCond %{HTTP:Authorization} ^(.*)
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%1]

Then re-run this script.
""")
    elif code in ("incorrect_password", "invalid_username",
                  "invalid_email", "authentication_failed"):
        log(f"""
WordPress received the credentials and rejected them (code: {code}).

The header IS getting through - this is a credentials problem:

  - '{USERNAME}' may not be the real username. The REST API needs the
    USERNAME (login), not the display name. Check Users in wp-admin.
  - The application password may have been revoked or regenerated.
    Generate a fresh one: Users > Profile > Application Passwords.
  - Application passwords require WordPress 5.6+ and HTTPS.

Edit USERNAME / APP_PASSWORD at the top of this file and re-run.
""")
    else:
        log(f"""
Unexpected response (status {status}, code {code}).

If a security plugin (Wordfence, iThemes, Solid Security) is active, it
may be blocking REST authentication or blocking this IP. Check its logs.
""")


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        log(f"\nUNEXPECTED ERROR: {type(e).__name__}: {e}")
    finally:
        try:
            with open(OUTPUT_FILE, "w", encoding="utf-8") as f:
                f.write("\n".join(_lines))
            print(f"\n\nOutput also saved to:\n  {OUTPUT_FILE}")
            print("Open that file and paste its contents back to Claude.")
        except Exception as e:
            print(f"(couldn't write output file: {e})")
        print()
        try:
            input("Press Enter to close this window...")
        except EOFError:
            pass
