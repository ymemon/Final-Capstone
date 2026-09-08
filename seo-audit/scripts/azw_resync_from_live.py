#!/usr/bin/env python3
"""
Reconcile content/*.html with what is actually live, and rebuild the JSON-LD
from the live copy.

    python3 scripts/azw_resync_from_live.py <slug> <live-dump.html>

Two pages diverged from the repo: /arizona-seo-services/ was rewritten
wholesale on the site, and /seo-company-phoenix-az/ gained sections and FAQ
entries. Live is authoritative, so the repo copy is rebuilt from it rather
than the other way round.

Both pages also serve zero JSON-LD, because editing them in the WordPress
editor strips <script> tags. The schema is regenerated here rather than
restored from the old local blocks, because the old FAQPage no longer matches
the questions on the page -- Phoenix has six live, the stale block had four.

Every FAQ string is copied out of the live HTML programmatically and never
retyped. validate.py requires the schema answer to appear byte-identically in
the visible copy, and Google treats a mismatch as a structured-data violation,
so a stray curly quote introduced by hand would be a real defect.
"""

import html
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

META = {
    "seo-company-phoenix-az": {
        "title": "SEO Company Serving Phoenix, AZ | AZWebCorp",
        "description": (
            "An Arizona SEO company serving Phoenix from nearby Gilbert. Straight "
            "assessments, realistic timelines, no guaranteed-ranking claims. "
            "Call (480) 818-5761."
        ),
        "breadcrumb": "SEO Company Serving Phoenix",
        "area": "Phoenix",
    },
}

TAG = re.compile(r"<[^>]+>")


def text_of(fragment: str) -> str:
    """Visible text of an HTML fragment, entities resolved, whitespace collapsed."""
    return re.sub(r"\s+", " ", html.unescape(TAG.sub("", fragment))).strip()


def faq_pairs(src: str):
    """
    Pull question/answer pairs from either markup style in use:
      <h3>Q</h3><p>A</p>                              (Phoenix)
      <details><summary>Q</summary><p>A</p></details> (Arizona accordion)
    """
    pairs = [
        (text_of(q), text_of(a))
        for q, a in re.findall(
            r"<details[^>]*>\s*<summary[^>]*>(.*?)</summary>\s*<p[^>]*>(.*?)</p>",
            src, re.S | re.I,
        )
    ]
    if pairs:
        return pairs

    heading = re.search(r"<h2[^>]*>\s*(?:FAQ|Frequently Asked Questions)\s*</h2>", src, re.I)
    if not heading:
        return []
    tail = src[heading.end():]
    nxt = re.search(r"<h2[^>]*>", tail)
    block = tail[: nxt.start()] if nxt else tail
    return [
        (text_of(q), text_of(a))
        for q, a in re.findall(r"<h3[^>]*>(.*?)</h3>\s*<p[^>]*>(.*?)</p>", block, re.S | re.I)
    ]


def build_schema(slug: str, live: str) -> str:
    meta = META[slug]
    url = f"https://azwebcorp.com/{slug}/"

    crumbs = [
        {"@type": "ListItem", "position": 1, "name": "Home", "item": "https://azwebcorp.com/"},
    ]
    if slug != "arizona-seo-services":
        crumbs.append({
            "@type": "ListItem", "position": 2, "name": "Arizona SEO Services",
            "item": "https://azwebcorp.com/arizona-seo-services/",
        })
    crumbs.append({
        "@type": "ListItem", "position": len(crumbs) + 1,
        "name": meta["breadcrumb"], "item": url,
    })

    service = {
        "@context": "https://schema.org",
        "@type": "ProfessionalService",
        "name": "AZ Web Corp",
        "url": url,
        "telephone": "+1-480-818-5761",
        "email": "info@azwebcorp.com",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "4690 E Laurel Ave",
            "addressLocality": "Gilbert",
            "addressRegion": "AZ",
            "postalCode": "85234",
            "addressCountry": "US",
        },
    }
    if meta["area"]:
        service["areaServed"] = {
            "@type": "City", "name": meta["area"],
            "containedInPlace": {"@type": "State", "name": "Arizona"},
        }
    else:
        service["areaServed"] = {"@type": "State", "name": "Arizona"}

    blocks = [
        {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": crumbs},
        service,
    ]

    # Rank Math already emits FAQPage for pages whose Q&A it can read from the
    # visible copy. Emitting a second one is duplicate schema, so FAQPage is
    # opt-in per page rather than automatic.
    pairs = faq_pairs(live) if meta.get("own_faq_schema") else []
    if pairs:
        blocks.append({
            "@context": "https://schema.org",
            "@type": "FAQPage",
            "mainEntity": [
                {
                    "@type": "Question",
                    "name": q,
                    "acceptedAnswer": {"@type": "Answer", "text": a},
                }
                for q, a in pairs
            ],
        })

    return "\n".join(
        '<script type="application/ld+json">\n'
        + json.dumps(b, indent=2, ensure_ascii=False)
        + "\n</script>"
        for b in blocks
    ), len(pairs)


def main():
    slug, dump = sys.argv[1], Path(sys.argv[2])
    live = dump.read_text(encoding="utf-8")

    # The live post_content carries no <html>/<body> wrapper and, for Arizona,
    # its own <h1>. The publish script moves that <h1> into post_title, so it
    # has to stay in the local file's <body> for a round trip to be lossless.
    # Any <script> blocks already in the live copy belong in the schema section
    # of the file, not the body: the publish script harvests schema by pattern
    # from anywhere in the file and would otherwise emit each block twice.
    body = re.sub(
        r'<script[^>]*type="application/ld\+json"[^>]*>.*?</script>\s*',
        "", live, flags=re.S | re.I,
    ).strip()

    if not re.search(r"<h1[^>]*>", body):
        title_guess = {
            "seo-company-phoenix-az": "SEO Company Serving Phoenix, AZ",
        }[slug]
        body = f"<h1>{title_guess}</h1>\n\n" + body

    schema, n_faq = build_schema(slug, live)
    meta = META[slug]

    out = (
        f"<!--\n"
        f"  Target URL: https://azwebcorp.com/{slug}/\n\n"
        f"  RESYNCED FROM LIVE. The site copy had diverged from this file and is the\n"
        f"  authoritative version; this file was rebuilt from it, not the reverse.\n"
        f"  The JSON-LD below is generated from the live copy too, because editing\n"
        f"  these pages in the WordPress editor strips <script> tags and the page had\n"
        f"  lost all of its schema.\n\n"
        f"  Do not hand-edit the FAQ answers in the schema. They are copied verbatim\n"
        f"  from the visible copy and must stay byte-identical to it.\n"
        f"-->\n"
        f"<title>{meta['title']}</title>\n"
        f'<meta name="description" content="{meta["description"]}">\n'
        f'<link rel="canonical" href="https://azwebcorp.com/{slug}/">\n\n'
        f"{schema}\n\n"
        f"<body>\n{body}\n</body>\n"
    )

    dest = ROOT / "content" / f"{slug}.html"
    dest.write_text(out, encoding="utf-8")
    print(f"{slug}: wrote {len(out)} bytes, {n_faq} FAQ pair(s) into schema -> {dest}")


if __name__ == "__main__":
    main()
