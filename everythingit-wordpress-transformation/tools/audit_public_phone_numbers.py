"""Audit all sitemap URLs for EverythingIT telephone-number variants."""

import json
import re
import sys
from collections import defaultdict
import requests


SITE = "https://everythingit.ie"
PHONE_CONTEXT = re.compile(r".{0,35}(?:\+|%2B)?353.{0,35}", re.I)


def main() -> None:
    session = requests.Session()
    session.headers["User-Agent"] = "EIT-Phone-Audit/1.0"
    pages = []
    page_number = 1
    while True:
        response = session.get(
            f"{SITE}/wp-json/wp/v2/pages",
            params={"per_page": 100, "page": page_number, "status": "publish", "_fields": "link,content"},
            timeout=30,
        )
        if response.status_code == 400:
            break
        response.raise_for_status()
        batch = response.json()
        if not batch:
            break
        pages.extend(batch)
        page_number += 1
    urls = sorted({item["link"] for item in pages})
    variants: dict[str, set[str]] = defaultdict(set)
    failures = []
    documents = [(item["link"], item.get("content", {}).get("rendered", "")) for item in pages]
    try:
        homepage = session.get(SITE, timeout=30)
        homepage.raise_for_status()
        documents.append((SITE + "/ [global header/footer]", homepage.text))
    except requests.RequestException as exc:
        failures.append({"url": SITE, "error": str(exc)})
    for url, html in documents:
        for match in PHONE_CONTEXT.findall(html):
            cleaned = re.sub(r"\s+", " ", match).strip()
            variants[cleaned].add(url)
    findings = [
        {"context": context, "pages": sorted(pages), "page_count": len(pages)}
        for context, pages in sorted(variants.items())
    ]
    print(json.dumps({"pages_audited": len(urls), "failures": failures, "findings": findings}, indent=2))


if __name__ == "__main__":
    main()
