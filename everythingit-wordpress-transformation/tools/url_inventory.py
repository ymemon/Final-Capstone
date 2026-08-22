import os, glob, datetime as dt
from pathlib import Path
from google.oauth2.credentials import Credentials
from google_auth_oauthlib.flow import InstalledAppFlow
from googleapiclient.discovery import build

D = Path(__file__).resolve().parent
SC = ["https://www.googleapis.com/auth/webmasters"]
TOK = D / "token_rw.json"
SITE = "sc-domain:everythingit.ie"
SM = "https://everythingit.ie/sitemap_index.xml"

if TOK.exists():
    c = Credentials.from_authorized_user_file(str(TOK), SC)
else:
    cs = sorted(glob.glob(str(D / "client_secret*.json")))
    if not cs:
        raise SystemExit("No client_secret*.json in " + str(D))
    c = InstalledAppFlow.from_client_secrets_file(cs[0], SC).run_local_server(port=0)
    TOK.write_text(c.to_json())

s = build("searchconsole", "v1", credentials=c, cache_discovery=False)

try:
    s.sitemaps().submit(siteUrl=SITE, feedpath=SM).execute()
    print("sitemap resubmitted")
except Exception as e:
    print("sitemap resubmit failed:", e)

end = dt.date.today()
start = end - dt.timedelta(days=90)
urls, off = [], 0
while True:
    r = s.searchanalytics().query(siteUrl=SITE, body={
        "startDate": start.isoformat(), "endDate": end.isoformat(),
        "dimensions": ["page"], "rowLimit": 25000, "startRow": off}).execute()
    rows = r.get("rows", [])
    if not rows:
        break
    urls += [x["keys"][0] for x in rows]
    if len(rows) < 25000:
        break
    off += len(rows)

seen, out = set(), []
for u in urls:
    if u not in seen:
        seen.add(u)
        out.append(u)

(D / "eit_urls.txt").write_text("\n".join(out), encoding="utf-8")
print(f"{len(out)} URLs with impressions -> eit_urls.txt")
