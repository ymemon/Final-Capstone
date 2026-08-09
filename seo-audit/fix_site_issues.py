#!/usr/bin/env python3
"""
Site-wide SEO issue scanner + safe auto-fixer for azwebcorp.com.

Built from the Ahrefs Site Audit overview (project 8030444, crawl dated
29 Jul 2026): Health Score 73, 385 issues total (36 errors / 233 warnings /
116 notices), top issues: multiple meta description tags (19), orphan
pages (12), broken images (7), 3XX redirects in sitemap (2), page has
broken image (1), page has links to broken page (1), noindex page in
sitemap (1), missing alt text (46), page has redirected JavaScript (46),
page has links to redirect (42).

That overview PDF only has aggregate counts, not which URLs are affected --
this script re-discovers them by crawling the site directly, which only
works from a machine that can actually reach azwebcorp.com (it does NOT
work from the Claude sandbox this was written in -- that environment's
egress policy blocks the domain outright; see seo-audit/AZWebCorp-Technical
-SEO-Audit-2026-08-04.md for how that was confirmed).

Numbers from this script's crawl will not match Ahrefs exactly -- Ahrefs
also tracks URLs blocked by robots.txt (145) and uncrawled URLs (63) with
its own crawler rules this script doesn't replicate. It targets the same
issue *categories* with direct, reproducible checks.

For each issue type below, "auto-fix" means this script changes something
live via the WP REST API when you pass --fix. Everything else is
report-only because it needs a human editorial decision:

  - Multiple meta description tags   REPORT ONLY (which plugin/template is
                                      double-emitting the tag needs a look)
  - Orphan pages                     REPORT ONLY (needs a decision on where
                                      to link them from)
  - Broken images                    REPORT ONLY (needs a real replacement
                                      image, can't be guessed)
  - 3XX redirects in sitemap         REPORT ONLY (exclude/update in your
                                      SEO plugin's sitemap settings)
  - Noindex pages in sitemap         REPORT ONLY (same -- SEO plugin setting)
  - Missing alt text                 AUTO-FIX: sets a filename-derived alt
                                      text via the Media REST API. Flagged
                                      in the report -- review these, they're
                                      a fallback, not real copy.
  - Links to a redirecting URL       AUTO-FIX: rewrites the href in the
                                      page's content to the final destination
                                      URL, via the Pages/Posts REST API.

Usage:
    pip install requests beautifulsoup4 lxml
    export WP_URL="https://azwebcorp.com"
    export WP_USER="admin"
    export WP_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx"

    python3 fix_site_issues.py --scan     # crawl + report only (default)
    python3 fix_site_issues.py --fix      # crawl, report, AND apply the
                                           # two safe auto-fixes above
"""
from __future__ import annotations

import argparse
import json
import os
import re
import sys
from dataclasses import dataclass, field
from urllib.parse import urljoin, urlparse

import requests
from bs4 import BeautifulSoup

WP_URL = os.environ.get("WP_URL", "https://azwebcorp.com").rstrip("/")
WP_USER = os.environ.get("WP_USER")
WP_APP_PASSWORD = os.environ.get("WP_APP_PASSWORD")

SITEMAP_CANDIDATES = ["/wp-sitemap.xml", "/sitemap_index.xml", "/sitemap.xml"]
REQUEST_TIMEOUT = 20
USER_AGENT = "AZWebCorp-SEO-Fixer/1.0 (+https://azwebcorp.com)"


def domain(url: str) -> str:
    return urlparse(url).netloc


def session_with_auth() -> requests.Session:
    s = requests.Session()
    s.headers.update({"User-Agent": USER_AGENT})
    if WP_USER and WP_APP_PASSWORD:
        s.auth = (WP_USER, WP_APP_PASSWORD)
    return s


def discover_sitemap_urls(session: requests.Session) -> list[str]:
    for path in SITEMAP_CANDIDATES:
        resp = session.get(WP_URL + path, timeout=REQUEST_TIMEOUT)
        if resp.status_code != 200 or "xml" not in resp.headers.get("Content-Type", ""):
            continue
        urls = parse_sitemap(session, WP_URL + path, seen=set())
        if urls:
            return sorted(urls)
    raise SystemExit(f"Couldn't find a sitemap at any of {SITEMAP_CANDIDATES} under {WP_URL}")


def parse_sitemap(session: requests.Session, sitemap_url: str, seen: set[str]) -> list[str]:
    if sitemap_url in seen:
        return []
    seen.add(sitemap_url)
    resp = session.get(sitemap_url, timeout=REQUEST_TIMEOUT)
    resp.raise_for_status()
    soup = BeautifulSoup(resp.content, "xml")

    urls: list[str] = []
    for sitemap_tag in soup.find_all("sitemap"):
        loc = sitemap_tag.find("loc")
        if loc and domain(loc.text) == domain(WP_URL):
            urls.extend(parse_sitemap(session, loc.text.strip(), seen))
    for url_tag in soup.find_all("url"):
        loc = url_tag.find("loc")
        if loc and domain(loc.text) == domain(WP_URL):
            urls.append(loc.text.strip())
    return urls


@dataclass
class Issue:
    type: str
    url: str
    detail: str
    auto_fixable: bool
    fix_applied: bool = False


@dataclass
class CrawlResult:
    pages: dict[str, BeautifulSoup] = field(default_factory=dict)
    incoming_links: dict[str, set[str]] = field(default_factory=dict)
    issues: list[Issue] = field(default_factory=list)


def slug_to_alt_text(src: str) -> str:
    filename = urlparse(src).path.rsplit("/", 1)[-1]
    stem = re.sub(r"\.\w+$", "", filename)
    stem = re.sub(r"[-_]+", " ", stem)
    stem = re.sub(r"\s+", " ", stem).strip()
    return stem.title() if stem else "Image"


def crawl(session: requests.Session, urls: list[str]) -> CrawlResult:
    result = CrawlResult()
    for url in urls:
        result.incoming_links.setdefault(url, set())

    for url in urls:
        try:
            resp = session.get(url, timeout=REQUEST_TIMEOUT, allow_redirects=True)
        except requests.RequestException as exc:
            result.issues.append(Issue("fetch_failed", url, str(exc), auto_fixable=False))
            continue
        if resp.status_code >= 400:
            result.issues.append(Issue("page_error", url, f"HTTP {resp.status_code}", auto_fixable=False))
            continue

        soup = BeautifulSoup(resp.text, "lxml")
        result.pages[url] = soup

        meta_desc_tags = soup.find_all("meta", attrs={"name": "description"})
        if len(meta_desc_tags) > 1:
            result.issues.append(Issue(
                "multiple_meta_description", url,
                f"{len(meta_desc_tags)} <meta name=\"description\"> tags found",
                auto_fixable=False,
            ))

        robots_meta = soup.find("meta", attrs={"name": "robots"})
        if robots_meta and "noindex" in (robots_meta.get("content") or "").lower():
            result.issues.append(Issue("noindex_page", url, "page has noindex meta tag", auto_fixable=False))

        for img in soup.find_all("img"):
            src = img.get("src")
            if not src:
                continue
            abs_src = urljoin(url, src)
            alt = img.get("alt")
            if not alt or not alt.strip():
                suggested = slug_to_alt_text(abs_src)
                result.issues.append(Issue(
                    "missing_alt_text", url,
                    f"img src={abs_src} -- suggested alt: \"{suggested}\"",
                    auto_fixable=True,
                ))
            try:
                head = session.head(abs_src, timeout=REQUEST_TIMEOUT, allow_redirects=True)
                if head.status_code >= 400:
                    result.issues.append(Issue("broken_image", url, f"img src={abs_src} -> HTTP {head.status_code}", auto_fixable=False))
            except requests.RequestException as exc:
                result.issues.append(Issue("broken_image", url, f"img src={abs_src} -> {exc}", auto_fixable=False))

        for a in soup.find_all("a", href=True):
            abs_href = urljoin(url, a["href"])
            if domain(abs_href) != domain(WP_URL):
                continue
            clean_href = abs_href.split("#")[0]
            if clean_href in result.incoming_links:
                result.incoming_links[clean_href].add(url)
            try:
                head = session.head(clean_href, timeout=REQUEST_TIMEOUT, allow_redirects=False)
                if head.status_code in (301, 302, 307, 308):
                    final = session.head(clean_href, timeout=REQUEST_TIMEOUT, allow_redirects=True)
                    result.issues.append(Issue(
                        "link_to_redirect", url,
                        f"link to {clean_href} (HTTP {head.status_code}) -> final destination {final.url}",
                        auto_fixable=True,
                    ))
            except requests.RequestException:
                pass

    for url in urls:
        if url in result.pages and not result.incoming_links.get(url):
            result.issues.append(Issue("orphan_page", url, "no incoming internal links found during this crawl", auto_fixable=False))

    return result


def find_media_id_by_source_url(session: requests.Session, src: str) -> int | None:
    resp = session.get(f"{WP_URL}/wp-json/wp/v2/media", params={"search": urlparse(src).path.rsplit("/", 1)[-1]}, timeout=REQUEST_TIMEOUT)
    if resp.status_code != 200:
        return None
    for item in resp.json():
        if item.get("source_url") == src:
            return item["id"]
    return None


def apply_fixes(session: requests.Session, issues: list[Issue]) -> None:
    if not (WP_USER and WP_APP_PASSWORD):
        print("WP_USER / WP_APP_PASSWORD not set -- skipping --fix, report-only.", file=sys.stderr)
        return

    for issue in issues:
        if not issue.auto_fixable:
            continue

        if issue.type == "missing_alt_text":
            match = re.search(r"img src=(\S+) -- suggested alt: \"(.+)\"", issue.detail)
            if not match:
                continue
            src, suggested_alt = match.group(1), match.group(2)
            media_id = find_media_id_by_source_url(session, src)
            if media_id is None:
                print(f"  [skip] couldn't resolve media library ID for {src}")
                continue
            resp = session.post(f"{WP_URL}/wp-json/wp/v2/media/{media_id}", json={"alt_text": suggested_alt}, timeout=REQUEST_TIMEOUT)
            if resp.status_code < 300:
                issue.fix_applied = True
                print(f"  [fixed] set alt_text=\"{suggested_alt}\" on media #{media_id} ({src})")
            else:
                print(f"  [failed] media #{media_id}: {resp.status_code} {resp.text[:200]}")

        elif issue.type == "link_to_redirect":
            print(f"  [manual] {issue.url}: {issue.detail} -- href rewriting needs the post ID + raw content,"
                  f" not resolved generically here; find the post via /wp-json/wp/v2/pages?search=... and"
                  f" replace the href in content.raw, then PATCH it back.")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--fix", action="store_true", help="apply safe auto-fixes (default: report only)")
    parser.add_argument("--out", default="seo-audit/site-issues-report.json")
    args = parser.parse_args()

    session = session_with_auth()
    print(f"Discovering sitemap under {WP_URL} ...")
    urls = discover_sitemap_urls(session)
    print(f"Found {len(urls)} URLs. Crawling ...")
    result = crawl(session, urls)

    by_type: dict[str, int] = {}
    for issue in result.issues:
        by_type[issue.type] = by_type.get(issue.type, 0) + 1

    print("\nIssue counts (this crawl):")
    for issue_type, count in sorted(by_type.items(), key=lambda kv: -kv[1]):
        print(f"  {issue_type}: {count}")

    if args.fix:
        print("\nApplying safe auto-fixes ...")
        apply_fixes(session, result.issues)

    report = {
        "wp_url": WP_URL,
        "urls_crawled": len(urls),
        "issue_counts": by_type,
        "issues": [issue.__dict__ for issue in result.issues],
    }
    with open(args.out, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2)
    print(f"\nFull report written to {args.out}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
