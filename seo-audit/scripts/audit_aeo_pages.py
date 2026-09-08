"""
Audit the pages AI crawlers actually spend their budget on.

    python audit_aeo_pages.py

For each page it reports the four things an answer engine needs in order to
name you as the answer rather than merely read you:

  city/region   is the target location stated in text, title and headings
  pricing       is there a real price, in text and/or in schema Offer
  NAP           name, address and phone present and matching canonical values
  schema        which JSON-LD types are on the page, and whether Service /
                LocalBusiness carry the fields that make them useful

Read-only. Prints findings; changes nothing.
"""

import json
import re
import sys
import urllib.error
import urllib.request

# Canonical NAP. Anything that disagrees with these on a live page is a finding,
# not a variant.
NAP = {
    "name": "AZ Web Corp",
    "phone_digits": "4808185761",
    "street": "4690 E Laurel Ave",
    "city": "Gilbert",
    "region": "AZ",
    "postal": "85234",
}

PAGES = [
    ("/web-design-queen-creek-az/", "Queen Creek"),
    ("/seo-company-phoenix-az/", "Phoenix"),
    ("/website-builder/", None),
    ("/website-security/", None),
    ("/business-email/", None),
]

BASE = "https://azwebcorp.com"


def fetch(path):
    req = urllib.request.Request(BASE + path, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=40) as r:
        return r.read().decode("utf-8", "replace")


def visible_text(html):
    h = re.sub(r"<(script|style|noscript)[^>]*>.*?</\1>", " ", html, flags=re.S | re.I)
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", h))


def json_ld(html):
    """Every JSON-LD block on the page, flattened through @graph."""
    out = []
    for m in re.finditer(r'<script[^>]+application/ld\+json[^>]*>(.*?)</script>', html, re.S | re.I):
        raw = m.group(1).strip()
        try:
            data = json.loads(raw)
        except json.JSONDecodeError:
            out.append({"@type": "!! UNPARSEABLE JSON-LD"})
            continue
        for node in (data if isinstance(data, list) else [data]):
            if isinstance(node, dict) and "@graph" in node:
                out.extend(n for n in node["@graph"] if isinstance(n, dict))
            elif isinstance(node, dict):
                out.append(node)
    return out


def types_of(nodes):
    names = []
    for n in nodes:
        t = n.get("@type")
        names.extend(t if isinstance(t, list) else [t])
    return [t for t in names if t]


def find_node(nodes, wanted):
    for n in nodes:
        t = n.get("@type")
        t = t if isinstance(t, list) else [t]
        if wanted in t:
            return n
    return None


def audit(path, city):
    try:
        html = fetch(path)
    except urllib.error.HTTPError as e:
        return {"path": path, "fatal": f"HTTP {e.code}"}

    text = visible_text(html)
    low = text.lower()
    nodes = json_ld(html)
    types = types_of(nodes)

    title = (re.search(r"<title[^>]*>(.*?)</title>", html, re.S | re.I) or [None, ""])[1].strip()
    h1s = [re.sub(r"<[^>]+>", "", m).strip()
           for m in re.findall(r"<h1[^>]*>(.*?)</h1>", html, re.S | re.I)]
    h2s = [re.sub(r"<[^>]+>", "", m).strip()
           for m in re.findall(r"<h2[^>]*>(.*?)</h2>", html, re.S | re.I)]

    prices = sorted(set(re.findall(r"\$\s?\d[\d,]*(?:\.\d{2})?", text)))
    digits = re.sub(r"\D", "", text)

    # Schema Offer / price anywhere in the graph.
    schema_price = []
    for n in nodes:
        for key in ("offers", "priceSpecification", "price"):
            if key in n:
                schema_price.append(n.get("@type"))
                break

    return {
        "path": path,
        "city": city,
        "title": title,
        "h1": h1s,
        "h2_count": len(h2s),
        "words": len(text.split()),
        "types": sorted(set(types)),
        "schema_price_on": sorted({str(t) for t in schema_price}),
        "prices_in_text": prices[:8],
        "nap": {
            "phone": NAP["phone_digits"] in digits,
            "street": NAP["street"].lower() in low,
            "citystate": (NAP["city"].lower() in low and NAP["region"].lower() in low.replace(",", " ")),
            "postal": NAP["postal"] in text,
        },
        "city_anchors": None if not city else {
            "in_title": city.lower() in title.lower(),
            "in_h1": any(city.lower() in h.lower() for h in h1s),
            "in_h2": any(city.lower() in h.lower() for h in h2s),
            "body_mentions": low.count(city.lower()),
            "arizona_named": ("arizona" in low or " az " in low),
        },
        "service_node": bool(find_node(nodes, "Service")),
        "localbusiness_node": bool(find_node(nodes, "LocalBusiness")),
        "faq_node": bool(find_node(nodes, "FAQPage")),
        "product_node": bool(find_node(nodes, "Product")),
    }


def main():
    results = [audit(p, c) for p, c in PAGES]
    for r in results:
        print("=" * 78)
        print(r["path"])
        if r.get("fatal"):
            print("  FATAL:", r["fatal"])
            continue
        print(f"  title      : {r['title'][:96]}")
        print(f"  h1         : {r['h1']}")
        print(f"  size       : {r['words']} words, {r['h2_count']} h2s")
        print(f"  schema     : {', '.join(r['types']) or 'NONE'}")
        print(f"               Service={r['service_node']} LocalBusiness={r['localbusiness_node']} "
              f"FAQ={r['faq_node']} Product={r['product_node']}")
        print(f"  price/schema: {r['schema_price_on'] or 'no Offer/price in any node'}")
        print(f"  price/text  : {r['prices_in_text'] or 'none found'}")
        n = r["nap"]
        print(f"  NAP        : phone={n['phone']} street={n['street']} "
              f"city+state={n['citystate']} zip={n['postal']}")
        if r["city_anchors"]:
            c = r["city_anchors"]
            print(f"  city anchor: title={c['in_title']} h1={c['in_h1']} h2={c['in_h2']} "
                  f"body={c['body_mentions']}x arizona={c['arizona_named']}")
    print("=" * 78)
    return results


if __name__ == "__main__":
    sys.exit(0 if main() else 0)
