import sys
from datetime import date
from pathlib import Path

import matplotlib.pyplot as plt
import pandas as pd
from docx import Document
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor
from google.oauth2.credentials import Credentials
from googleapiclient.discovery import build

ROOT = Path(r"C:\Users\yasir\Documents\Final-Capstone\seo-audit")
OUT = ROOT / "reports" / "Everything-IT-Monthly-Report-2026-08-30"
DOCX = OUT / "Everything-IT-Monthly-Comparison-Customer-Report-BRANDED.docx"
XLSX = OUT / "Everything-IT-Monthly-Comparison-Data.xlsx"
# Resolved rather than hardcoded: ga4-oauth-token.json expired and took this
# report down with it, with no symptom until a RefreshError mid-run.
GA4_SCOPES = [
    "https://www.googleapis.com/auth/analytics.readonly",
    "https://www.googleapis.com/auth/analytics.manage.users.readonly",
]
sys.path.insert(0, str(ROOT))
from ga4_token import resolve_ga4_token
from gsc_query_oauth import query as gsc_api
from scripts.document_branding import apply_branding

CUR_START, CUR_END = date(2026, 8, 1), date(2026, 8, 27)
PREV_START, PREV_END = date(2026, 7, 1), date(2026, 7, 27)


def gsc(dimensions=None, start=CUR_START, end=CUR_END, search_type="web"):
    body = {"startDate": start.isoformat(), "endDate": end.isoformat(), "type": search_type, "rowLimit": 25000, "dataState": "final"}
    if dimensions:
        body["dimensions"] = dimensions
    result = gsc_api("sc-domain:everythingit.ie", body)
    records = []
    for item in result.get("rows", []):
        row = {d: v for d, v in zip(dimensions or [], item.get("keys", []))}
        row.update({k: item.get(k, 0) for k in ("clicks", "impressions", "ctr", "position")})
        records.append(row)
    return pd.DataFrame(records)


def ga4(dimensions, metrics, start="2026-08-01", end="2026-08-29"):
    scopes = GA4_SCOPES
    creds = Credentials.from_authorized_user_file(str(resolve_ga4_token(scopes)), scopes)
    svc = build("analyticsdata", "v1beta", credentials=creds, cache_discovery=False)
    body = {"dateRanges": [{"startDate": start, "endDate": end}], "dimensions": [{"name": d} for d in dimensions], "metrics": [{"name": m} for m in metrics], "limit": "100000"}
    result = svc.properties().runReport(property="properties/552051066", body=body).execute()
    rows = []
    for item in result.get("rows", []):
        values = [x.get("value", "") for x in item.get("dimensionValues", []) + item.get("metricValues", [])]
        rows.append(dict(zip(dimensions + metrics, values)))
    frame = pd.DataFrame(rows, columns=dimensions + metrics)
    for m in metrics:
        if m in frame:
            frame[m] = pd.to_numeric(frame[m], errors="coerce").fillna(0)
    return frame


def merge(cur, prev, key):
    cols = [key, "clicks", "impressions", "ctr", "position"]
    c = cur.reindex(columns=cols).rename(columns={x: f"aug_{x}" for x in cols if x != key})
    p = prev.reindex(columns=cols).rename(columns={x: f"jul_{x}" for x in cols if x != key})
    out = c.merge(p, on=key, how="outer").fillna(0)
    out["click_change"] = out.aug_clicks - out.jul_clicks
    out["impression_change"] = out.aug_impressions - out.jul_impressions
    out["position_improvement"] = out.jul_position - out.aug_position
    return out


def shade(cell, fill):
    pr = cell._tc.get_or_add_tcPr(); shd = OxmlElement("w:shd"); shd.set(qn("w:fill"), fill); pr.append(shd)


def signal(value):
    s = str(value).strip().lower()
    if s.startswith("+") or s.startswith("↑") or s in {"positive", "yes", "new", "completed"}:
        return "008A3B", "E2F4E8"
    if s.startswith("-") or s.startswith("↓") or s in {"needs improvement", "no"}:
        return "C62828", "FCE4E4"


def table(doc, headers, rows):
    t = doc.add_table(rows=1, cols=len(headers)); t.style = "Table Grid"; t.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, h in enumerate(headers):
        t.rows[0].cells[i].text = str(h); shade(t.rows[0].cells[i], "F2C94C")
        for run in t.rows[0].cells[i].paragraphs[0].runs: run.bold = True
    for row in rows:
        cells = t.add_row().cells
        for i, value in enumerate(row):
            shown = str(value)
            if shown.startswith("+"): shown = "↑ " + shown
            elif shown.startswith("-"): shown = "↓ " + shown
            cells[i].text = shown
            sig = signal(shown)
            if sig:
                color, fill = sig; shade(cells[i], fill)
                for run in cells[i].paragraphs[0].runs: run.bold = True; run.font.color.rgb = RGBColor.from_string(color)
    doc.add_paragraph(); return t


def fmt(v, kind="n"):
    if kind == "pct": return f"{float(v):.2%}"
    if kind == "pos": return f"{float(v):.1f}"
    return f"{float(v):,.0f}"


def change(a, b):
    return "New" if not b and a else ("0%" if not b else f"{(a-b)/b:+.1%}")


def graph(path, labels, values, title, ylabel):
    colors = ["#19A55A", "#6B7280", "#F2C94C"][:len(values)]
    plt.figure(figsize=(7.4, 3.8)); bars = plt.bar(labels, values, color=colors); plt.title(title, fontsize=15, fontweight="bold"); plt.ylabel(ylabel); plt.grid(axis="y", alpha=.2)
    for b, v in zip(bars, values): plt.text(b.get_x()+b.get_width()/2, b.get_height(), f"{v:,.0f}", ha="center", va="bottom", fontweight="bold")
    plt.tight_layout(); plt.savefig(path, dpi=180, bbox_inches="tight"); plt.close()


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    cur_t, prev_t = gsc(), gsc(start=PREV_START, end=PREV_END)
    empty = {k: 0 for k in ("clicks", "impressions", "ctr", "position")}
    cur = cur_t.iloc[0].to_dict() if not cur_t.empty else empty; prev = prev_t.iloc[0].to_dict() if not prev_t.empty else empty
    reports = {}
    for name, dims in {"queries":["query"],"pages":["page"],"countries":["country"],"devices":["device"],"query_page":["query","page"],"appearance":["searchAppearance"]}.items():
        reports[name+"_aug"] = gsc(dims); reports[name+"_jul"] = gsc(dims, PREV_START, PREV_END)
    reports["queries_compare"] = merge(reports["queries_aug"], reports["queries_jul"], "query")
    reports["pages_compare"] = merge(reports["pages_aug"], reports["pages_jul"], "page")
    reports["countries_compare"] = merge(reports["countries_aug"], reports["countries_jul"], "country")
    reports["devices_compare"] = merge(reports["devices_aug"], reports["devices_jul"], "device")
    reports["daily_aug"] = gsc(["date"]); reports["daily_jul"] = gsc(["date"], PREV_START, PREV_END)
    metrics = ["sessions","totalUsers","screenPageViews","engagementRate","keyEvents"]
    for name, dims in {"ga_channels":["sessionDefaultChannelGroup"],"ga_sources":["sessionSourceMedium"],"ga_referrers":["pageReferrer"],"ga_countries":["country"],"ga_cities":["city"],"ga_devices":["deviceCategory"],"ga_browsers":["browser"],"ga_landing":["landingPagePlusQueryString"],"ga_events":["eventName"]}.items():
        reports[name] = ga4(dims, metrics if name != "ga_events" else ["eventCount","totalUsers","keyEvents"])
    reports["ga_month"] = ga4(["yearMonth"], metrics, "2026-07-01", "2026-08-29")
    with pd.ExcelWriter(XLSX, engine="openpyxl") as writer:
        pd.DataFrame([{"period":"August 1-27",**cur},{"period":"July 1-27",**prev}]).to_excel(writer, sheet_name="KPI comparison", index=False)
        for n, f in reports.items(): f.to_excel(writer, sheet_name=n[:31], index=False)

    clicks = OUT/"chart-clicks.png"; impressions = OUT/"chart-impressions.png"; countries_img = OUT/"chart-countries.png"
    graph(clicks,["August","July"],[cur["clicks"],prev["clicks"]],"Google Search Clicks","Clicks")
    graph(impressions,["August","July"],[cur["impressions"],prev["impressions"]],"Google Search Appearances","Impressions")
    top3ga = reports["ga_countries"].sort_values("sessions",ascending=False).head(3)
    if top3ga.empty:
        top3gsc = reports["countries_aug"].sort_values("impressions",ascending=False).head(3); graph(countries_img,top3gsc.country.tolist(),top3gsc.impressions.tolist(),"Top 3 Countries by Google Visibility","Impressions")
    else: graph(countries_img,top3ga.country.tolist(),top3ga.sessions.tolist(),"Top 3 Countries by Website Traffic","Sessions")

    doc = Document(); apply_branding(doc, "Everything IT"); sec=doc.sections[0]; sec.top_margin=sec.bottom_margin=Inches(.65); sec.left_margin=sec.right_margin=Inches(.7)
    doc.styles["Normal"].font.name="Aptos"; doc.styles["Normal"].font.size=Pt(10.5)
    for n,s,c in (("Title",27,"18202A"),("Heading 1",18,"18202A"),("Heading 2",13,"A56F00")): doc.styles[n].font.name="Aptos Display"; doc.styles[n].font.size=Pt(s); doc.styles[n].font.color.rgb=RGBColor.from_string(c)
    p=doc.add_paragraph(style="Title"); p.alignment=WD_ALIGN_PARAGRAPH.CENTER; p.add_run("Everything IT\nMonthly Google Performance Report")
    p=doc.add_paragraph(); p.alignment=WD_ALIGN_PARAGRAPH.CENTER; p.add_run("August 1–27, 2026 compared with July 1–27, 2026\nGoogle Search Console + Google Analytics 4").bold=True
    doc.add_heading("KEY POINTS AT A GLANCE",1)
    table(doc,["Status","What immediately matters"],[
        ("Positive","GA4 G-927C6L1W1C is publicly live across the homepage and Cork, Galway, Limerick and Waterford pages."),
        ("Positive" if cur["clicks"]>=prev["clicks"] else "Needs improvement",f"Google clicks changed from {fmt(prev['clicks'])} in July to {fmt(cur['clicks'])} in August."),
        ("Positive" if cur["impressions"]>=prev["impressions"] else "Needs improvement",f"Google appearances changed from {fmt(prev['impressions'])} to {fmt(cur['impressions'])}."),
        ("Needs improvement","GA4 is newly installed, so reliable traffic and lead trends need another full month of collection."),
    ])
    doc.add_heading("Monthly results",1)
    table(doc,["Measure","August 1–27","July 1–27","Change"],[
        ("Clicks",fmt(cur["clicks"]),fmt(prev["clicks"]),change(cur["clicks"],prev["clicks"])),
        ("Impressions",fmt(cur["impressions"]),fmt(prev["impressions"]),change(cur["impressions"],prev["impressions"])),
        ("CTR",fmt(cur["ctr"],"pct"),fmt(prev["ctr"],"pct"),f"{(cur['ctr']-prev['ctr'])*100:+.2f} points"),
        ("Average position",fmt(cur["position"],"pos"),fmt(prev["position"],"pos"),f"{prev['position']-cur['position']:+.1f} positions"),
    ])
    for image in (clicks,impressions): doc.add_picture(str(image),width=Inches(6.7)); doc.paragraphs[-1].alignment=WD_ALIGN_PARAGRAPH.CENTER
    doc.add_heading("Where traffic came from",1)
    if reports["ga_sources"].empty: doc.add_paragraph("GA4 was installed at the end of August, so no dependable source rows are available yet. Search Console clicks below are all unpaid Google Search clicks.")
    else:
        table(doc,["Source / medium","Sessions","Users","Page views","Key events"],[(r.sessionSourceMedium,fmt(r.sessions),fmt(r.totalUsers),fmt(r.screenPageViews),fmt(r.keyEvents)) for _,r in reports["ga_sources"].sort_values("sessions",ascending=False).iterrows()])
    if not reports["ga_referrers"].empty: table(doc,["Referring page","Sessions","Users","Page views"],[(r.pageReferrer or "Direct / not provided",fmt(r.sessions),fmt(r.totalUsers),fmt(r.screenPageViews)) for _,r in reports["ga_referrers"].sort_values("sessions",ascending=False).head(15).iterrows()])
    doc.add_heading("Top queries and monthly movement",1)
    topq=reports["queries_aug"].sort_values(["clicks","impressions"],ascending=False).head(20)
    table(doc,["Query","Clicks","Impressions","CTR","Position"],[(r.query,fmt(r.clicks),fmt(r.impressions),fmt(r.ctr,"pct"),fmt(r.position,"pos")) for _,r in topq.iterrows()])
    movers=reports["queries_compare"].sort_values(["click_change","impression_change"],ascending=False).head(12)
    table(doc,["Query","Click change","Impression change","Position improvement"],[(r.query,f"{r.click_change:+.0f}",f"{r.impression_change:+.0f}",f"{r.position_improvement:+.1f}") for _,r in movers.iterrows()])
    doc.add_heading("Location pages: Cork, Galway, Limerick and Waterford",1)
    pages=reports["pages_aug"].copy(); mask=pages.page.str.contains("cork|galway|limerick|waterford",case=False,regex=True,na=False); locations=pages[mask].sort_values("impressions",ascending=False)
    table(doc,["Location page","Clicks","Impressions","CTR","Position"],[(r.page.replace("https://everythingit.ie","") or "/",fmt(r.clicks),fmt(r.impressions),fmt(r.ctr,"pct"),fmt(r.position,"pos")) for _,r in locations.iterrows()])
    locq=reports["queries_aug"]; locq=locq[locq["query"].str.contains("cork|galway|limerick|waterford",case=False,regex=True,na=False)].sort_values(["clicks","impressions"],ascending=False).head(25)
    table(doc,["Location keyword","Clicks","Impressions","Position"],[(r["query"],fmt(r.clicks),fmt(r.impressions),fmt(r.position,"pos")) for _,r in locq.iterrows()])
    doc.add_heading("Products and services customers searched for",1)
    products=reports["queries_aug"]; products=products[products["query"].str.contains("support|managed|cyber|cloud|microsoft|office|backup|security|helpdesk|consulting|services",case=False,regex=True,na=False)].sort_values(["clicks","impressions"],ascending=False).head(25)
    table(doc,["Product / service keyword","Clicks","Impressions","CTR","Position"],[(r["query"],fmt(r.clicks),fmt(r.impressions),fmt(r.ctr,"pct"),fmt(r.position,"pos")) for _,r in products.iterrows()])
    doc.add_heading("Top pages",1)
    topp=reports["pages_compare"].sort_values("aug_impressions",ascending=False).head(20)
    table(doc,["Page","Aug clicks","Jul clicks","Click change","Aug impressions","Jul impressions","Impression change"],[(r.page.replace("https://everythingit.ie","") or "/",fmt(r.aug_clicks),fmt(r.jul_clicks),f"{r.click_change:+.0f}",fmt(r.aug_impressions),fmt(r.jul_impressions),f"{r.impression_change:+.0f}") for _,r in topp.iterrows()])
    doc.add_heading("Top three countries",1)
    if top3ga.empty: table(doc,["Rank","Country","Google impressions"],[(i+1,r.country,fmt(r.impressions)) for i,(_,r) in enumerate(top3gsc.iterrows())])
    else: table(doc,["Rank","Country","Sessions","Users","Page views"],[(i+1,r.country,fmt(r.sessions),fmt(r.totalUsers),fmt(r.screenPageViews)) for i,(_,r) in enumerate(top3ga.iterrows())])
    doc.add_picture(str(countries_img),width=Inches(6.7)); doc.paragraphs[-1].alignment=WD_ALIGN_PARAGRAPH.CENTER
    doc.add_heading("Devices and technical audience",1)
    dev=reports["devices_compare"].sort_values("aug_impressions",ascending=False)
    table(doc,["Device","Aug clicks","Jul clicks","Click change","Aug impressions","Jul impressions","Impression change","CTR"],[(r.device.title(),fmt(r.aug_clicks),fmt(r.jul_clicks),f"{r.click_change:+.0f}",fmt(r.aug_impressions),fmt(r.jul_impressions),f"{r.impression_change:+.0f}",fmt(r.aug_ctr,"pct")) for _,r in dev.iterrows()])
    doc.add_heading("Overall assessment",1)
    assessment=[
        ("Search visibility","Positive" if cur["impressions"]>=prev["impressions"] else "Needs improvement","Visibility is " + ("higher" if cur["impressions"]>=prev["impressions"] else "lower") + " than the matching July period."),
        ("Search clicks","Positive" if cur["clicks"]>=prev["clicks"] else "Needs improvement","Clicks are " + ("higher" if cur["clicks"]>=prev["clicks"] else "lower") + " than the matching July period."),
        ("Location visibility","Positive","Cork, Galway, Limerick and Waterford performance is now separated and measurable."),
        ("Analytics setup","Positive","The correct GA4 ID is live publicly across the tested pages."),
        ("Trend reliability","Needs improvement","GA4 needs a full month before customer traffic and leads can be compared reliably."),
    ]
    table(doc,["Area","Assessment","What it means"],assessment)
    doc.add_heading("What the customer should remember",2)
    for text in [
        "Everything IT now has working analytics across the main site and four location pages.",
        "The report shows which locations, services, pages and keywords generated the strongest Google visibility this month.",
        "Green upward arrows show improvement; red downward arrows identify the areas that need attention.",
        "The next report will be more valuable because GA4 will have a complete month of source, referral and engagement data.",
    ]: doc.add_paragraph(text,style="List Bullet")
    doc.save(DOCX); print(DOCX); print(XLSX)


if __name__ == "__main__": main()
