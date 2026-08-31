import json
import re
import sys
from urllib.request import Request, urlopen

url = sys.argv[1]
html = urlopen(Request(url, headers={"User-Agent": "Mozilla/5.0"}), timeout=30).read().decode("utf-8", "replace")
blocks = re.findall(r'<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>', html, re.I | re.S)
found = []
for block in blocks:
    try:
        data = json.loads(block)
    except Exception:
        continue
    stack = [data]
    while stack:
        value = stack.pop()
        if isinstance(value, dict):
            types = value.get("@type", [])
            types = types if isinstance(types, list) else [types]
            if any(t in ("Organization", "LocalBusiness", "ProfessionalService") for t in types):
                found.append({k: value.get(k) for k in ("@type", "name", "telephone", "email", "address", "geo", "foundingDate", "sameAs")})
            if "Product" in types:
                found.append({k: value.get(k) for k in ("@type", "name", "description", "image", "brand", "offers", "sku")})
            if "Service" in types:
                found.append({k: value.get(k) for k in ("@type", "@id", "name", "provider", "areaServed", "offers")})
            stack.extend(value.values())
        elif isinstance(value, list):
            stack.extend(value)
print(json.dumps(found, indent=2))
