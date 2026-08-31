import json
import re
from collections import Counter, defaultdict
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path
from urllib.parse import urljoin, urlparse, urlunparse

import requests
from bs4 import BeautifulSoup

SITE = "https://azwebcorp.com"
OUT = Path(r"C:\Users\yasir\Documents\Final-Capstone\seo-audit\evidence\azw-live-crawl-2026-08-30.json")
HEADERS = {"User-Agent": "AZWebCorp-SEO-Verification/1.0"}


def get(url):
    return requests.get(url, headers=HEADERS, timeout=35, allow_redirects=True)


def sitemap_urls(url):
    response = get(url)
    response.raise_for_status()
    soup = BeautifulSoup(response.content, "xml")
    locs = [x.get_text(strip=True) for x in soup.find_all("loc")]
    out = []
    for loc in locs:
        if loc.endswith(".xml"):
            out.extend(sitemap_urls(loc))
        elif not re.search(r"\.(?:avif|gif|jpe?g|png|svg|webp)(?:$|\?)", loc, re.I):
            out.append(loc)
    return out


def clean_url(url):
    parsed = urlparse(url)
    return urlunparse((parsed.scheme, parsed.netloc.lower(), parsed.path or "/", "", "", ""))


def schema_types(soup):
    types = []
    invalid = 0
    for tag in soup.select('script[type="application/ld+json"]'):
        try:
            data = json.loads(tag.get_text())
        except Exception:
            invalid += 1
            continue
        stack = [data]
        while stack:
            item = stack.pop()
            if isinstance(item, dict):
                value = item.get("@type")
                if isinstance(value, list):
                    types.extend(str(x) for x in value)
                elif value:
                    types.append(str(value))
                stack.extend(item.values())
            elif isinstance(item, list):
                stack.extend(item)
    return types, invalid


def crawl(url):
    try:
        response = get(url)
        soup = BeautifulSoup(response.text, "html.parser")
        title = soup.title.get_text(" ", strip=True) if soup.title else ""
        desc = soup.select('meta[name="description"]')
        canon = soup.select('link[rel="canonical"]')
        robots = soup.select('meta[name="robots"]')
        h1s = [x.get_text(" ", strip=True) for x in soup.find_all("h1")]
        links = []
        for tag in soup.find_all("a", href=True):
            href = tag.get("href", "").strip()
            if not href or href.startswith(("#", "mailto:", "tel:", "javascript:")):
                continue
            absolute = clean_url(urljoin(response.url, href))
            if urlparse(absolute).netloc.lower() in {"azwebcorp.com", "www.azwebcorp.com"}:
                links.append(absolute.replace("https://www.azwebcorp.com", SITE))
        types, invalid_schema = schema_types(soup)
        return {
            "requested": url,
            "final": response.url,
            "status": response.status_code,
            "redirects": [{"status": x.status_code, "url": x.url, "location": x.headers.get("Location")} for x in response.history],
            "title": title,
            "title_length": len(title),
            "descriptions": [x.get("content", "").strip() for x in desc],
            "canonicals": [x.get("href", "").strip() for x in canon],
            "robots": [x.get("content", "").strip() for x in robots],
            "h1s": h1s,
            "internal_links": sorted(set(links)),
            "schema_types": types,
            "invalid_schema_blocks": invalid_schema,
            "word_count": len(re.findall(r"\b[\w'-]+\b", soup.get_text(" ", strip=True))),
        }
    except Exception as exc:
        return {"requested": url, "error": str(exc)}


def head(url):
    try:
        r = requests.get(url, headers=HEADERS, timeout=25, allow_redirects=False, stream=True)
        return {"url": url, "status": r.status_code, "location": r.headers.get("Location")}
    except Exception as exc:
        return {"url": url, "error": str(exc)}


def main():
    urls = sorted(set(sitemap_urls(SITE + "/sitemap_index.xml")))
    with ThreadPoolExecutor(max_workers=8) as pool:
        pages = list(pool.map(crawl, urls))
    internal = sorted({link for page in pages for link in page.get("internal_links", [])})
    with ThreadPoolExecutor(max_workers=10) as pool:
        link_results = list(pool.map(head, internal))

    titles = defaultdict(list)
    descriptions = defaultdict(list)
    issues = defaultdict(list)
    for page in pages:
        url = page.get("requested")
        if page.get("error"):
            issues["crawl_errors"].append({"url": url, "error": page["error"]}); continue
        titles[page["title"]].append(url)
        if page["descriptions"]:
            descriptions[page["descriptions"][0]].append(url)
        if page["status"] != 200: issues["sitemap_non_200"].append({"url": url, "status": page["status"], "final": page["final"]})
        if len(page["descriptions"]) != 1: issues["description_count"].append({"url": url, "count": len(page["descriptions"])})
        if len(page["canonicals"]) != 1: issues["canonical_count"].append({"url": url, "count": len(page["canonicals"])})
        if len(page["h1s"]) != 1: issues["h1_count"].append({"url": url, "count": len(page["h1s"]), "h1s": page["h1s"]})
        if len(page["robots"]) != 1: issues["robots_count"].append({"url": url, "count": len(page["robots"])})
        if page["invalid_schema_blocks"]: issues["invalid_schema"].append({"url": url, "count": page["invalid_schema_blocks"]})
        if page["title_length"] < 25 or page["title_length"] > 65: issues["title_length"].append({"url": url, "length": page["title_length"], "title": page["title"]})
    issues["duplicate_titles"] = [{"value": k, "urls": v} for k, v in titles.items() if k and len(v) > 1]
    issues["duplicate_descriptions"] = [{"value": k, "urls": v} for k, v in descriptions.items() if k and len(v) > 1]
    issues["broken_internal_links"] = [x for x in link_results if x.get("status", 0) >= 400 or x.get("error")]
    issues["redirecting_internal_links"] = [x for x in link_results if 300 <= x.get("status", 0) < 400]
    report = {"site": SITE, "sitemap_url_count": len(urls), "internal_url_count": len(internal), "pages": pages, "link_results": link_results, "issues": dict(issues)}
    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(json.dumps({"output": str(OUT), "pages": len(urls), "internal_urls": len(internal), "issue_counts": {k: len(v) for k, v in issues.items()}}, indent=2))


if __name__ == "__main__":
    main()
