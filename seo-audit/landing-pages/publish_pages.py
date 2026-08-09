#!/usr/bin/env python3
"""
Publishes the 7 SEO bridge pages to azwebcorp.com as WordPress drafts via
the REST API. Run this from a machine that can reach azwebcorp.com over
HTTPS (this cannot run from the Claude sandbox -- that host is blocked
by this session's egress policy).

Usage:
    export WP_URL="https://azwebcorp.com"
    export WP_USER="admin"
    export WP_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"
    python3 publish_pages.py

Pages are created as DRAFTS, not published live -- several still have
placeholder pricing (marked <!-- CONFIRM PRICE --> in the source .html
files) that should be filled in before anything goes public. Review each
draft in wp-admin, fill in placeholders, and hit Publish yourself.

This script sets title/slug/content only. Meta title, meta description,
canonical URL, and JSON-LD schema depend on which SEO plugin is active
(Yoast, RankMath, AIOSEO, etc.) and are NOT set automatically here --
set those in the plugin's metabox on each page after creating it. If
your SEO plugin already auto-generates Organization/BreadcrumbList
schema, don't also paste the JSON-LD blocks from the .html source files --
that would create duplicate schema.
"""
import html
import os
import re
import sys
from pathlib import Path

import requests

WP_URL = os.environ.get("WP_URL", "https://azwebcorp.com").rstrip("/")
WP_USER = os.environ["WP_USER"]
WP_APP_PASSWORD = os.environ["WP_APP_PASSWORD"]

PAGES_DIR = Path(__file__).parent

PAGES = [
    {"file": "hosting-domains.html", "slug": "hosting-domains", "title": "Web Hosting, Domains & WordPress Plans"},
    {"file": "web-hosting.html", "slug": "web-hosting", "title": "Web Hosting Plus — cPanel Hosting Plans"},
    {"file": "wordpress-hosting.html", "slug": "wordpress-hosting", "title": "Managed WordPress Hosting"},
    {"file": "domain-registration.html", "slug": "domain-registration", "title": "Domain Registration"},
    {"file": "domain-transfer.html", "slug": "domain-transfer", "title": "Domain Transfer"},
    {"file": "website-builder.html", "slug": "website-builder", "title": "Website Builder"},
    {"file": "business-email.html", "slug": "business-email-hosting", "title": "Business Email Hosting"},
]


def extract_body(html_text: str) -> str:
    match = re.search(r"<body>(.*)</body>", html_text, re.DOTALL)
    if not match:
        raise ValueError("No <body> block found")
    return match.group(1).strip()


def main() -> int:
    session = requests.Session()
    session.auth = (WP_USER, WP_APP_PASSWORD)

    for page in PAGES:
        path = PAGES_DIR / page["file"]
        raw = path.read_text(encoding="utf-8")
        body = extract_body(raw)
        has_placeholder = "CONFIRM" in raw

        resp = session.post(
            f"{WP_URL}/wp-json/wp/v2/pages",
            json={
                "title": page["title"],
                "slug": page["slug"],
                "content": body,
                "status": "draft",
            },
            timeout=30,
        )
        if resp.status_code >= 300:
            print(f"FAILED {page['slug']}: {resp.status_code} {resp.text[:300]}", file=sys.stderr)
            continue

        data = resp.json()
        flag = "  [HAS UNCONFIRMED PLACEHOLDER -- review before publishing]" if has_placeholder else ""
        print(f"Created draft: {page['slug']} -> {data.get('link')}{flag}")

    print("\nAll pages created as drafts. Next: set SEO title/meta description/canonical")
    print("in your SEO plugin's metabox on each page, review placeholders, then Publish.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
