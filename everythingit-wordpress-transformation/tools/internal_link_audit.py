#!/usr/bin/env python3
"""
eit_internal_link_audit.py

Crawls everythingit.ie (via sitemap.xml) and checks every internal link on
every page. No guessing at nav structure -- it reads the live HTML and
tests every href against the live site.

Buckets:
  BROKEN   - link resolves to a 4xx/5xx (real bug; if seen on many pages,
             it's almost certainly a nav/header/footer link, not a one-off)
  LEGACY   - link resolves via a redirect (still pointing at an old URL
             instead of the final page -- fix the href directly, don't
             rely on the redirect)
  OK       - link goes straight to a 200, nothing to do

Requires: pip install requests
(uses stdlib html.parser, no bs4 dependency)

Usage:
    python eit_internal_link_audit.py --domain https://everythingit.ie
    python eit_internal_link_audit.py --domain https://everythingit.ie --max-pages 200
"""
import argparse
import sys
import time
import xml.etree.ElementTree as ET
from collections import defaultdict
from html.parser import HTMLParser
from urllib.parse import urljoin, urlparse

import requests

SITEMAP_NS = {'sm': 'http://www.sitemaps.org/schemas/sitemap/0.9'}


class LinkExtractor(HTMLParser):
    def __init__(self):
        super().__init__()
        self.links = []

    def handle_starttag(self, tag, attrs):
        if tag != 'a':
            return
        for name, value in attrs:
            if name == 'href' and value:
                self.links.append(value)


def fetch_sitemap_urls(domain, sess):
    candidates = [f"{domain}/sitemap.xml", f"{domain}/sitemap_index.xml"]
    urls = set()
    seen_sitemaps = set()

    def crawl_sitemap(sitemap_url):
        if sitemap_url in seen_sitemaps:
            return
        seen_sitemaps.add(sitemap_url)
        try:
            r = sess.get(sitemap_url, timeout=15)
            r.raise_for_status()
            root = ET.fromstring(r.content)
        except Exception:
            return
        # nested sitemap index
        for sm in root.findall('sm:sitemap/sm:loc', SITEMAP_NS):
            crawl_sitemap(sm.text.strip())
        for loc in root.findall('sm:url/sm:loc', SITEMAP_NS):
            urls.add(loc.text.strip())

    for c in candidates:
        crawl_sitemap(c)
    return urls


def is_internal(href, domain_host):
    if href.startswith('#') or href.startswith('mailto:') or href.startswith('tel:') or href.startswith('javascript:'):
        return False
    parsed = urlparse(href)
    if not parsed.netloc:
        return True  # relative link
    return parsed.netloc.replace('www.', '') == domain_host.replace('www.', '')


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--domain', default='https://everythingit.ie')
    ap.add_argument('--max-pages', type=int, default=300, help='cap on pages crawled from the sitemap')
    ap.add_argument('--delay', type=float, default=0.3, help='seconds between requests, be polite to your own server')
    args = ap.parse_args()

    domain_host = urlparse(args.domain).netloc

    sess = requests.Session()
    sess.headers['User-Agent'] = 'EIT-Internal-Link-Audit/1.0'

    print(f"Fetching sitemap from {args.domain} ...")
    pages = fetch_sitemap_urls(args.domain, sess)
    if not pages:
        print("Could not find any URLs via sitemap.xml / sitemap_index.xml. Aborting.")
        sys.exit(1)
    pages = sorted(pages)[:args.max_pages]
    print(f"Crawling {len(pages)} pages from the sitemap...\n")

    # href -> set of source pages it appears on
    link_sources = defaultdict(set)

    for i, page in enumerate(pages, 1):
        try:
            r = sess.get(page, timeout=15)
        except requests.RequestException as e:
            print(f"  [{i}/{len(pages)}] FAILED TO FETCH {page}: {e}")
            continue
        if r.status_code != 200:
            continue
        parser = LinkExtractor()
        try:
            parser.feed(r.text)
        except Exception:
            continue
        for href in parser.links:
            if not is_internal(href, domain_host):
                continue
            absolute = urljoin(page, href)
            absolute = absolute.split('#')[0].split('?')[0]
            if not absolute.rstrip('/').startswith(args.domain.rstrip('/')):
                continue
            link_sources[absolute].add(page)
        time.sleep(args.delay)

    print(f"\nFound {len(link_sources)} unique internal links across {len(pages)} pages. Testing each...\n")

    broken, legacy, ok = [], [], []
    checked = {}
    for link in link_sources:
        if link in checked:
            continue
        try:
            r = sess.get(link, allow_redirects=True, timeout=15)
            checked[link] = r
        except requests.RequestException as e:
            checked[link] = e
        time.sleep(args.delay)

    for link, sources in link_sources.items():
        result = checked[link]
        if isinstance(result, Exception):
            broken.append((link, sources, f"ERROR: {result}"))
            continue
        if result.status_code >= 400:
            broken.append((link, sources, f"{result.status_code}"))
        elif result.history:
            chain = ' -> '.join(str(h.status_code) for h in result.history) + f' -> {result.status_code}'
            legacy.append((link, sources, f"{chain}, final: {result.url}"))
        else:
            ok.append(link)

    def report(title, items):
        print(f"=== {title} ({len(items)}) ===")
        for link, sources, detail in sorted(items, key=lambda x: -len(x[1])):
            print(f"  {link}  [{detail}]")
            print(f"      appears on {len(sources)} page(s): " + ', '.join(sorted(sources)[:5]) +
                  (' ...' if len(sources) > 5 else ''))
        print()

    report("BROKEN LINKS (4xx/5xx)", broken)
    report("LEGACY LINKS (redirect instead of direct)", legacy)
    print(f"=== OK, direct 200 ({len(ok)}) === (not printed, no action needed)")


if __name__ == '__main__':
    main()
