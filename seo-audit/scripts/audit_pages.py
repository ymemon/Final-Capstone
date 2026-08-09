#!/usr/bin/env python3
"""
Audit the bridge landing pages for on-page SEO correctness.

Checks each page for the things that actually cost rankings or break rich
results, and cross-checks the set for duplicates and orphans. Exits non-zero
if any ERROR-level problem is found, so it can gate a build.

Usage:
    python3 scripts/audit_pages.py           # offline checks on the local files
    python3 scripts/audit_pages.py --live    # additionally verify every URL is
                                             # really reachable on the live site
"""

import html
import json
import re
import sys
import time
import urllib.error
import urllib.request
from collections import defaultdict
from pathlib import Path

PAGES_DIR = Path(__file__).resolve().parent.parent / "landing-pages"
SITE = "https://azwebcorp.com"
HUB_SLUG = "hosting-domains"
RESELLER_HOST = "www.shopazwebcorp.com"

# Google truncates around these; treated as warnings, not errors.
TITLE_MAX = 60
DESC_MIN, DESC_MAX = 120, 160

# Internal links that point at pages outside this bridge-page set. They exist
# on the wider site and can't be verified from here.
EXTERNAL_INTERNAL = {"/arizona-web-development/", "/"}


def text_of(fragment: str) -> str:
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", "", fragment))).strip()


def slug_of(path: Path) -> str:
    return path.stem


class Page:
    def __init__(self, path: Path):
        self.path = path
        self.slug = slug_of(path)
        self.src = path.read_text(encoding="utf-8")
        self.errors: list[str] = []
        self.warnings: list[str] = []

        self.title = self._one(r"<title>(.*?)</title>")
        self.desc = self._one(r'<meta name="description" content="(.*?)">')
        self.canonical = self._one(r'<link rel="canonical" href="(.*?)">')
        self.h1s = re.findall(r"<h1>(.*?)</h1>", self.src, re.DOTALL)
        self.schemas = self._schemas()
        self.body = self.src.split("<body>", 1)[1] if "<body>" in self.src else ""

    def _one(self, pattern):
        m = re.search(pattern, self.src, re.DOTALL)
        return text_of(m.group(1)) if m else None

    def _schemas(self):
        blocks = re.findall(
            r'<script type="application/ld\+json">\s*(.*?)\s*</script>',
            self.src,
            re.DOTALL,
        )
        parsed = []
        for i, raw in enumerate(blocks):
            try:
                parsed.append(json.loads(raw))
            except json.JSONDecodeError as e:
                self.errors.append(f"JSON-LD block {i + 1} is invalid JSON: {e}")
        return parsed

    def types(self):
        return {s.get("@type") for s in self.schemas}

    # -- checks ---------------------------------------------------------

    def check(self, all_slugs):
        self._check_head()
        self._check_headings()
        self._check_schema()
        self._check_faq_match()
        self._check_links(all_slugs)

    def _check_head(self):
        if not self.title:
            self.errors.append("missing <title>")
        elif len(self.title) > TITLE_MAX:
            self.warnings.append(f"title {len(self.title)} chars (>{TITLE_MAX}, may truncate)")

        if not self.desc:
            self.errors.append("missing meta description")
        elif not (DESC_MIN <= len(self.desc) <= DESC_MAX):
            self.warnings.append(
                f"meta description {len(self.desc)} chars (target {DESC_MIN}-{DESC_MAX})"
            )

        expected = f"{SITE}/{self.slug}/"
        if not self.canonical:
            self.errors.append("missing canonical")
        elif self.canonical != expected:
            self.errors.append(f"canonical {self.canonical} != expected {expected}")

    def _check_headings(self):
        if len(self.h1s) == 0:
            self.errors.append("no <h1>")
        elif len(self.h1s) > 1:
            self.errors.append(f"{len(self.h1s)} <h1> tags (must be exactly 1)")

    def _check_schema(self):
        types = self.types()
        if "BreadcrumbList" not in types:
            self.errors.append("missing BreadcrumbList schema")
        if self.slug != HUB_SLUG and "Product" not in types:
            self.errors.append("missing Product schema")
        if re.search(r"<h2>\s*FAQ\s*</h2>", self.src, re.I) and "FAQPage" not in types:
            self.errors.append("has visible FAQ but no FAQPage schema")

        # Breadcrumb must terminate on this page's own canonical.
        for s in self.schemas:
            if s.get("@type") != "BreadcrumbList":
                continue
            items = s.get("itemListElement", [])
            if items and self.slug != HUB_SLUG:
                last = items[-1].get("item")
                if last != f"{SITE}/{self.slug}/":
                    self.errors.append(f"breadcrumb ends at {last}, not this page")
            positions = [i.get("position") for i in items]
            if positions != list(range(1, len(positions) + 1)):
                self.errors.append(f"breadcrumb positions not sequential: {positions}")

        # Product with an offers array/object must carry price + currency.
        for s in self.schemas:
            if s.get("@type") != "Product":
                continue
            offers = s.get("offers")
            for off in offers if isinstance(offers, list) else [offers] if offers else []:
                if not off.get("price") or not off.get("priceCurrency"):
                    self.errors.append(f"Product offer missing price/currency: {off}")

    def _check_faq_match(self):
        """Google requires FAQPage text to match what renders on the page."""
        faq = next((s for s in self.schemas if s.get("@type") == "FAQPage"), None)
        if not faq:
            return
        m = re.search(r"<h2>\s*FAQ\s*</h2>", self.src, re.I)
        if not m:
            self.errors.append("FAQPage schema present but no visible FAQ section")
            return
        rest = self.src[m.end():]
        nxt = re.search(r"<h2[\s>]", rest)
        section = rest[: nxt.start()] if nxt else rest
        visible = {
            (text_of(q), text_of(a))
            for q, a in re.findall(r"<h3>(.*?)</h3>\s*<p>(.*?)</p>", section, re.DOTALL | re.I)
        }
        for entry in faq.get("mainEntity", []):
            pair = (entry.get("name", ""), entry.get("acceptedAnswer", {}).get("text", ""))
            if pair not in visible:
                self.errors.append(
                    f"FAQPage Q&A not found in visible text: {pair[0]!r}"
                )

    def _check_links(self, all_slugs):
        hrefs = re.findall(r'href="([^"]+)"', self.body)

        # Reseller CTA: every product page must bridge to the storefront.
        reseller = [h for h in hrefs if "shopazwebcorp.com" in h]
        if self.slug != HUB_SLUG:
            if not reseller:
                self.errors.append("no reseller CTA link (bridge page must bridge)")
            for h in reseller:
                if RESELLER_HOST not in h:
                    self.errors.append(f"reseller link missing www host (404s): {h}")
                if "utm_source=" not in h:
                    self.warnings.append(f"reseller link has no UTM tagging: {h}")

        # Internal links must resolve to a page in this set.
        for h in hrefs:
            if not h.startswith("/") or h in EXTERNAL_INTERNAL:
                continue
            target = h.strip("/")
            if target not in all_slugs:
                self.errors.append(f"internal link to non-existent page: {h}")


class NoRedirect(urllib.request.HTTPRedirectHandler):
    """Report the redirect itself rather than silently following it."""

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def probe(url: str, timeout: int = 20):
    """Return (status, location|None, error|None) for a single URL."""
    opener = urllib.request.build_opener(NoRedirect)
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (compatible; azw-audit)"})
    try:
        with opener.open(req, timeout=timeout) as r:
            return r.status, None, None
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get("Location"), None
    except Exception as e:  # DNS, TLS, timeout, connection reset
        return None, None, str(e)


def live_check(pages, delay: float = 1.0) -> list[str]:
    """Verify every canonical, internal link and reseller CTA actually resolves.

    The offline checks only prove the local file set is self-consistent; a page
    can pass those and still 404 in production because it was never published.
    Requests are paced because firing them back-to-back trips rate limiting and
    returns connection resets that look like server faults.
    """
    targets: dict[str, set[str]] = defaultdict(set)
    for p in pages:
        if p.canonical:
            targets[p.canonical].add(f"{p.slug} (canonical)")
        for raw in re.findall(r'href="([^"]+)"', p.body):
            # hrefs carry HTML-escaped ampersands; unescape before requesting.
            href = html.unescape(raw)
            if href.startswith("/"):
                targets[SITE.rstrip("/") + href].add(p.slug)
            elif "shopazwebcorp.com" in href:
                targets[href].add(f"{p.slug} (CTA)")

    problems = []
    print(f"\nLive-checking {len(targets)} unique URLs (~{int(len(targets) * delay)}s)...\n")
    for url in sorted(targets):
        status, location, error = probe(url)
        who = ", ".join(sorted(targets[url]))
        if error:
            print(f"  FAIL {url}\n         {error}")
            problems.append(f"LIVE unreachable: {url} ({error}) — linked from {who}")
        elif status == 200:
            print(f"  200  {url}")
        else:
            arrow = f"  ->  {location}" if location else ""
            print(f"  {status:<4} {url}{arrow}")
            problems.append(f"LIVE {status}: {url}{arrow} — linked from {who}")
        time.sleep(delay)
    return problems


def main() -> int:
    paths = sorted(PAGES_DIR.glob("*.html"))
    if not paths:
        print(f"No HTML files in {PAGES_DIR}", file=sys.stderr)
        return 1

    pages = [Page(p) for p in paths]
    slugs = {p.slug for p in pages}
    for p in pages:
        p.check(slugs)

    # --- cross-page checks ---
    by_title, by_desc = defaultdict(list), defaultdict(list)
    for p in pages:
        if p.title:
            by_title[p.title].append(p.slug)
        if p.desc:
            by_desc[p.desc].append(p.slug)

    cross = []
    for title, owners in by_title.items():
        if len(owners) > 1:
            cross.append(f"DUPLICATE title across {owners}: {title!r}")
    for desc, owners in by_desc.items():
        if len(owners) > 1:
            cross.append(f"DUPLICATE meta description across {owners}")

    # Orphans: every non-hub page must be linked from the hub.
    hub = next((p for p in pages if p.slug == HUB_SLUG), None)
    if hub:
        linked = {h.strip("/") for h in re.findall(r'href="(/[^"]*)"', hub.body)}
        for p in pages:
            if p.slug != HUB_SLUG and p.slug not in linked:
                cross.append(f"ORPHAN: /{p.slug}/ is not linked from the hub page")
    else:
        cross.append(f"hub page {HUB_SLUG}.html not found")

    # --- report ---
    errs = sum(len(p.errors) for p in pages) + len(cross)
    warns = sum(len(p.warnings) for p in pages)

    for p in pages:
        marks = []
        t = p.types()
        for label, key in (("crumb", "BreadcrumbList"), ("product", "Product"), ("faq", "FAQPage")):
            marks.append(f"{label}:{'y' if key in t else '-'}")
        status = "FAIL" if p.errors else ("warn" if p.warnings else "ok")
        print(f"[{status:>4}] {p.slug:<28} {' '.join(marks)}")
        for e in p.errors:
            print(f"         ERROR   {e}")
        for w in p.warnings:
            print(f"         warn    {w}")

    if cross:
        print("\nCross-page:")
        for c in cross:
            print(f"  ERROR   {c}")

    if "--live" in sys.argv:
        live = live_check(pages)
        errs += len(live)
        if live:
            print("\nLive site:")
            for problem in live:
                print(f"  ERROR   {problem}")
        else:
            print("\nLive site: every URL resolved 200.")

    print(f"\n{len(pages)} pages | {errs} errors | {warns} warnings")
    return 1 if errs else 0


if __name__ == "__main__":
    raise SystemExit(main())
