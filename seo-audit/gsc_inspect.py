"""
Ask Google directly what it thinks of a URL (indexed? why not?) via the
Search Console URL Inspection API. Reuses the same service-account auth as
gsc_query.py.

Usage:  python gsc_inspect.py <path-or-url> [more...]
"""
import json, sys, urllib.request, urllib.error
from gsc_query import get_token, SITE

INSPECT_URL = "https://searchconsole.googleapis.com/v1/urlInspection/index:inspect"


def inspect(token, page_url):
    body = {
        "inspectionUrl": page_url,
        "siteUrl": SITE,
    }
    req = urllib.request.Request(
        INSPECT_URL,
        data=json.dumps(body).encode(),
        headers={"Authorization": "Bearer " + token, "Content-Type": "application/json"},
    )
    try:
        with urllib.request.urlopen(req) as resp:
            return json.load(resp)
    except urllib.error.HTTPError as e:
        return {"error": e.code, "detail": e.read().decode()[:400]}


if __name__ == "__main__":
    token = get_token()
    args = sys.argv[1:]
    for a in args:
        url = a if a.startswith("http") else SITE.rstrip("/") + "/" + a.strip("/") + "/"
        r = inspect(token, url)
        if "error" in r:
            print(f"{url}\n   ERROR {r['error']}: {r['detail']}\n")
            continue
        idx = r.get("inspectionResult", {}).get("indexStatusResult", {})
        print(url)
        print(f"   verdict         : {idx.get('verdict')}")
        print(f"   coverageState   : {idx.get('coverageState')}")
        print(f"   robotsTxtState  : {idx.get('robotsTxtState')}")
        print(f"   indexingState   : {idx.get('indexingState')}")
        print(f"   lastCrawlTime   : {idx.get('lastCrawlTime')}")
        print(f"   pageFetchState  : {idx.get('pageFetchState')}")
        print(f"   googleCanonical : {idx.get('googleCanonical')}")
        print(f"   userCanonical   : {idx.get('userCanonical')}")
        refs = idx.get("referringUrls")
        if refs:
            print(f"   referringUrls   : {len(refs)} -> {refs[:3]}")
        else:
            print("   referringUrls   : (none reported)")
        print()
