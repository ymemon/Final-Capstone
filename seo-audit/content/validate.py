#!/usr/bin/env python3
"""
Validate the service/location content pages.

The bridge-page auditor (../scripts/audit_pages.py) does not apply here: it
requires Product schema and a shopazwebcorp.com CTA on every non-hub page, and
these are service pages with neither. This checks what actually matters for
this content type.

The FAQ check is the one that carries real risk. Google requires FAQPage
structured data to match the text visible on the page; schema that has drifted
from the copy is a structured-data violation, not a cosmetic problem. Run this
after any edit to an FAQ.

Usage:
    python3 seo-audit/content/validate.py
"""

import html
import json
import re
import sys
from pathlib import Path

PAGES_DIR = Path(__file__).resolve().parent
SITE = "https://azwebcorp.com"

TITLE_MAX = 60
DESC_MIN, DESC_MAX = 120, 160

REQUIRED_SCHEMA = ("BreadcrumbList", "ProfessionalService", "FAQPage")

# Authoritative NAP. Must stay byte-identical to the Google Business Profile,
# the site footer, and landing-pages/hosting-domains.html.
NAP_PHONE = "+1-480-818-5761"
NAP_STREET = "4690 E Laurel Ave"

# Placeholders that must be resolved before the page goes live. Matches a
# bracketed block opening with an ALL-CAPS marker, e.g. "[CLIENT EXAMPLE - ...]";
# the body after the marker is prose, so it cannot be constrained to caps.
PLACEHOLDER = re.compile(r"\[[A-Z]{2,}[^\]]*\]")


def text_of(fragment: str) -> str:
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", "", fragment))).strip()


class Page:
    def __init__(self, path: Path):
        self.path = path
        self.slug = path.stem
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
        parsed = []
        blocks = re.findall(
            r'<script type="application/ld\+json">\s*(.*?)\s*</script>', self.src, re.DOTALL
        )
        for i, raw in enumerate(blocks):
            try:
                parsed.append(json.loads(raw))
            except json.JSONDecodeError as e:
                self.errors.append(f"JSON-LD block {i + 1} is invalid JSON: {e}")
        return parsed

    def word_count(self):
        return len(text_of(self.body).split())

    # -- checks ---------------------------------------------------------

    def check(self):
        self._check_head()
        self._check_schema()
        self._check_faq_match()
        self._check_nap()
        self._check_placeholders()

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

        if len(self.h1s) != 1:
            self.errors.append(f"{len(self.h1s)} <h1> tags (must be exactly 1)")

    def _check_schema(self):
        types = {s.get("@type") for s in self.schemas}
        for required in REQUIRED_SCHEMA:
            if required not in types:
                self.errors.append(f"missing {required} schema")

        # Product schema here means a bridge page landed in the wrong directory.
        if "Product" in types:
            self.errors.append("Product schema present — belongs in ../landing-pages/")

        for s in self.schemas:
            if s.get("@type") != "BreadcrumbList":
                continue
            items = s.get("itemListElement", [])
            if items:
                last = items[-1].get("item")
                if last != f"{SITE}/{self.slug}/":
                    self.errors.append(f"breadcrumb ends at {last}, not this page")
            positions = [i.get("position") for i in items]
            if positions != list(range(1, len(positions) + 1)):
                self.errors.append(f"breadcrumb positions not sequential: {positions}")

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
                self.errors.append(f"FAQPage Q&A not in visible text: {pair[0]!r}")

    def _check_nap(self):
        """A drifted phone or street address dilutes local ranking signals."""
        for s in self.schemas:
            if s.get("@type") != "ProfessionalService":
                continue
            if s.get("telephone") != NAP_PHONE:
                self.errors.append(f"telephone {s.get('telephone')!r} != {NAP_PHONE!r}")
            street = (s.get("address") or {}).get("streetAddress")
            if street != NAP_STREET:
                self.errors.append(f"streetAddress {street!r} != {NAP_STREET!r}")

    def _check_placeholders(self):
        for hit in set(PLACEHOLDER.findall(self.body)):
            self.warnings.append(f"unresolved placeholder {hit} — fill or delete before publishing")


def main() -> int:
    paths = sorted(p for p in PAGES_DIR.glob("*.html"))
    if not paths:
        print(f"No HTML files in {PAGES_DIR}", file=sys.stderr)
        return 1

    pages = [Page(p) for p in paths]
    for p in pages:
        p.check()

    # Duplicate titles/descriptions across the set compete with each other.
    cross = []
    for field in ("title", "desc"):
        seen: dict[str, list[str]] = {}
        for p in pages:
            value = getattr(p, field)
            if value:
                seen.setdefault(value, []).append(p.slug)
        for value, owners in seen.items():
            if len(owners) > 1:
                cross.append(f"DUPLICATE {field} across {owners}")

    errs = sum(len(p.errors) for p in pages) + len(cross)
    warns = sum(len(p.warnings) for p in pages)

    for p in pages:
        status = "FAIL" if p.errors else ("warn" if p.warnings else "ok")
        print(f"[{status:>4}] {p.slug:<28} {p.word_count():>5} words")
        for e in p.errors:
            print(f"         ERROR   {e}")
        for w in p.warnings:
            print(f"         warn    {w}")

    for c in cross:
        print(f"         ERROR   {c}")

    print(f"\n{len(pages)} pages | {errs} errors | {warns} warnings")
    if warns and not errs:
        print("Warnings do not block publishing, except unresolved placeholders — those do.")
    return 1 if errs else 0


if __name__ == "__main__":
    raise SystemExit(main())
