"""
Weekly SEO report — runs every Monday, emails each client their own figures.

Yasir's instruction, 2026-08-27: run this for every site every Monday, send
the client the result whether it is good or bad, copy ceo@azwebcorp.com on
everything, and keep a record so nobody has to ask whether it went out.

WHAT IT WILL NOT DO
Send a client an email with no data in it. Search Console access currently
exists for azwebcorp.com only; the other properties have none. A weekly
"we have no figures for you" is worse than silence, so a site without a
readable GSC property produces an INTERNAL notice to Yasir instead, naming
what is missing. Nothing is ever estimated, inferred or filled in.

HONESTY RULES, since these go to clients unreviewed
  - Report the numbers as they are. A bad week is reported as a bad week.
  - Never describe a CTR rise caused by impressions falling faster than clicks
    as an improvement. That is arithmetic, not progress.
  - State the click base when it is small enough to make percentages noisy.
  - Attribute a change only where the cause is actually known.

THE LEDGER
Every send appends to sent_ledger.jsonl and sent-log.md. Both are append-only.
"Did the Monday report go out?" is answered by reading the log, not by asking.

Usage
    python weekly_seo_report.py              # dry run, writes nothing, sends nothing
    python weekly_seo_report.py --send       # send for real and record it
"""

import datetime
import json
import os
import subprocess
import sys
import textwrap

HERE = os.path.dirname(os.path.abspath(__file__))
SEO_DIR = os.path.dirname(HERE)
sys.path.insert(0, os.path.join(os.path.expanduser("~"), "Documents",
                                "Final-Capstone", "azwebcorp-email"))
sys.path.insert(0, os.path.join(os.path.expanduser("~"), "Documents",
                                "Final-Capstone", "azwebcorp-email", "signature"))
sys.path.insert(0, os.path.join(os.path.expanduser("~"), "Documents",
                                "Final-Capstone", "azwebcorp-email", "approval"))

CONFIG = os.path.join(HERE, "sites.json")
LEDGER = os.path.join(HERE, "sent_ledger.jsonl")
LOGMD = os.path.join(HERE, "sent-log.md")

SEND = "--send" in sys.argv


# --------------------------------------------------------------- data access
def gsc(property_url, body):
    """Run gsc_query.py against a specific property. Returns rows or None."""
    env = dict(os.environ, GSC_SITE=property_url, PYTHONIOENCODING="utf-8")
    try:
        r = subprocess.run(
            [sys.executable, os.path.join(SEO_DIR, "gsc_query.py"), json.dumps(body)],
            capture_output=True, text=True, timeout=180, env=env, cwd=SEO_DIR)
    except subprocess.TimeoutExpired:
        return None
    if r.returncode != 0:
        return None
    try:
        return json.loads(r.stdout)
    except json.JSONDecodeError:
        return None


def commentary(site, data):
    """
    Plain-English reading of the figures. Derived from the data, never spin.

    The rule that matters: the headline 28-day comparison can hide the shape of
    the period. A month that is down against the previous month but climbing
    steeply in its final week is a different story from one that is simply
    down, and a client is entitled to be told which they are looking at.
    """
    cur, prev = data["cur"], data["prev"]
    daily = data.get("daily") or []
    out = []

    if prev:
        di = cur["impressions"] - prev["impressions"]
        pct = (di / prev["impressions"] * 100) if prev["impressions"] else 0
        if di < 0:
            out.append(
                f"Impressions were down {abs(di):,.0f} ({abs(pct):.0f}%) against the "
                f"previous four weeks, and average position slipped from "
                f"{prev['position']:.1f} to {cur['position']:.1f}.")
        elif di > 0:
            out.append(
                f"Impressions rose {di:,.0f} ({pct:+.0f}%) against the previous "
                f"four weeks, with average position moving from "
                f"{prev['position']:.1f} to {cur['position']:.1f}.")

    # Shape of the period: last 7 days vs the first 7.
    if len(daily) >= 14:
        first = sum(r["impressions"] for r in daily[:7]) / 7
        last = sum(r["impressions"] for r in daily[-7:]) / 7
        if first > 0 and last > first * 1.5:
            out.append(
                f"The trend within the period matters more than that headline. "
                f"Daily impressions averaged {first:,.0f} in the first week and "
                f"{last:,.0f} in the last — a sharp climb concentrated in the "
                f"final days, which the four-week comparison hides entirely.")
        elif last > 0 and first > last * 1.5:
            out.append(
                f"The decline is recent rather than uniform: daily impressions "
                f"averaged {first:,.0f} in the first week of the period and "
                f"{last:,.0f} in the last.")

    # Never let a mechanical CTR rise read as good news.
    if prev and cur["ctr"] > prev["ctr"] and cur["clicks"] <= prev["clicks"]:
        out.append(
            "The click-through rate is higher than last period, but only because "
            "impressions fell faster than clicks did. That is arithmetic rather "
            "than an improvement, and we would rather say so.")

    if cur["clicks"] < 50:
        out.append(
            f"One caveat on reading these numbers: {cur['clicks']:,.0f} clicks is a "
            f"small base, so percentage swings look dramatic for reasons that are "
            f"not always meaningful. The impression trend is the steadier signal.")

    if site.get("commentary_note"):
        out.append(site["commentary_note"])

    return out


def collect(site, end):
    """Pull the numbers for one site. Returns None if the property is unreadable."""
    prop = site.get("gsc_property")
    if not prop:
        return None

    start = end - datetime.timedelta(days=27)
    pend = start - datetime.timedelta(days=1)
    pstart = pend - datetime.timedelta(days=27)

    def totals(a, b):
        d = gsc(prop, {"startDate": str(a), "endDate": str(b), "dimensions": []})
        if not d or not d.get("rows"):
            return None
        return d["rows"][0]

    cur, prev = totals(start, end), totals(pstart, pend)
    if not cur:
        return None

    pages = gsc(prop, {"startDate": str(start), "endDate": str(end),
                       "dimensions": ["page"], "rowLimit": 200}) or {}
    queries = gsc(prop, {"startDate": str(start), "endDate": str(end),
                         "dimensions": ["query"], "rowLimit": 25}) or {}
    daily = gsc(prop, {"startDate": str(start), "endDate": str(end),
                       "dimensions": ["date"], "rowLimit": 60}) or {}

    return {
        "window": (start, end), "prev_window": (pstart, pend),
        "cur": cur, "prev": prev,
        "pages": pages.get("rows", []), "queries": queries.get("rows", []),
        "daily": sorted(daily.get("rows", []), key=lambda r: r["keys"][0]),
    }


def compose(site, data, to, cc):
    """Branded HTML email with an embedded chart, plus a plain-text fallback."""
    from email.message import EmailMessage
    from email.utils import formatdate, make_msgid
    import report_render as R
    import azwc_signature_v2 as SIG

    notes = commentary(site, data)
    cids = {}
    charts = []

    chart_path = os.path.join(HERE, f"chart_{site['domain'].replace('.','_')}.png")
    if R.trend_chart(data["daily"], chart_path):
        cids["trend"] = chart_path
        charts.append(("trend", chart_path))

    msg = EmailMessage()
    msg["From"] = "Team AZ Web Corp <requests@azwebcorp.com>"
    msg["To"] = to
    # Only set Cc when there is one. `msg["Cc"] = None` does not omit the
    # header - it writes the literal string "None", which is a malformed
    # address that a stricter mail server can reject outright.
    if cc:
        msg["Cc"] = cc
    msg["Subject"] = (f"{site['domain']} — search performance, "
                      f"{data['window'][0]:%d %b} to {data['window'][1]:%d %b}")
    msg["Date"] = formatdate(localtime=True)
    msg["Message-ID"] = make_msgid(domain="azwebcorp.com")
    msg["Reply-To"] = "requests@azwebcorp.com"

    greeting = f"Dear {site['client_name']}," if site.get("client_name") else "Hello,"
    msg.set_content(
        f"{greeting}\n\n" + build_report_text(site, data) + "\n" +
        "\n".join(textwrap.fill(n, 72) + "\n" for n in notes) +
        "\nFigures come directly from Google Search Console and are reported\n"
        "exactly as they are, whether the period was good or bad.\n\n"
        "With kind regards,\n\n" +
        SIG.text(name=None, sender="requests@azwebcorp.com"))

    msg.add_alternative(R.build_html(site, data, notes, cids), subtype="html")

    # Attach charts to the HTML part so cid: references resolve.
    html_part = msg.get_payload()[-1]
    for cid, path in charts:
        with open(path, "rb") as fh:
            html_part.add_related(fh.read(), "image", "png", cid=f"<{cid}>",
                                  filename=os.path.basename(path))
    return msg


# ------------------------------------------------------------------ wording
def delta_line(cur, prev, key, fmt="{:,.0f}"):
    c = cur.get(key, 0)
    if not prev:
        return fmt.format(c)
    p = prev.get(key, 0)
    diff = c - p
    pct = (diff / p * 100) if p else 0
    arrow = "up" if diff > 0 else ("down" if diff < 0 else "level")
    return f"{fmt.format(c)}  ({arrow} {abs(diff):,.0f}, {pct:+.0f}%)"


def build_report_text(site, data):
    """The body a client reads. Plain, honest, no spin."""
    cur, prev = data["cur"], data["prev"]
    a, b = data["window"]
    pa, pb = data["prev_window"]

    clicks = cur["clicks"]
    small_base = clicks < 50

    L = []
    L.append(f"Search performance for {site['domain']}")
    L.append(f"{a:%d %b} to {b:%d %b %Y}, compared with {pa:%d %b} to {pb:%d %b}.")
    L.append("")
    L.append(f"  Clicks         {delta_line(cur, prev, 'clicks')}")
    L.append(f"  Impressions    {delta_line(cur, prev, 'impressions')}")
    if prev:
        L.append(f"  Click rate     {cur['ctr']*100:.2f}%   "
                 f"(was {prev['ctr']*100:.2f}%)")
        L.append(f"  Avg position   {cur['position']:.1f}   "
                 f"(was {prev['position']:.1f})")
    L.append("")

    # Do not let a mechanical CTR rise be read as good news.
    if prev and cur["ctr"] > prev["ctr"] and cur["clicks"] <= prev["clicks"]:
        L += textwrap.wrap(
            "Note on the click rate: it rose only because impressions fell "
            "faster than clicks did. That is arithmetic rather than an "
            "improvement, and we would rather say so than present it as a win.",
            width=72, initial_indent="  ", subsequent_indent="  ")
        L.append("")

    if small_base:
        L += textwrap.wrap(
            f"This site recorded {clicks:,} clicks in the period. At that "
            "volume percentage swings are noisy, so the impression trend is "
            "the more reliable signal week to week.",
            width=72, initial_indent="  ", subsequent_indent="  ")
        L.append("")

    if data["queries"]:
        L.append("  Top search terms bringing people to the site:")
        for r in data["queries"][:8]:
            L.append(f"    {r['clicks']:>4,.0f} clicks  {r['impressions']:>7,.0f} impressions   "
                     f"{r['keys'][0][:46]}")
        L.append("")

    if data["pages"]:
        top = sorted(data["pages"], key=lambda r: -r["impressions"])[:6]
        L.append("  Most-seen pages:")
        for r in top:
            path = r["keys"][0].replace(f"https://{site['domain']}", "") or "/"
            L.append(f"    {r['impressions']:>7,.0f} impressions   {path[:52]}")
        L.append("")

    return "\n".join(L)


# ------------------------------------------------------------------- ledger
def record(entries):
    stamp = datetime.datetime.now(datetime.timezone.utc)
    with open(LEDGER, "a", encoding="utf-8") as fh:
        for e in entries:
            fh.write(json.dumps({**e, "at": stamp.isoformat()}) + "\n")

    lines = [f"\n## {stamp:%Y-%m-%d %H:%M UTC} — weekly run\n"]
    for e in entries:
        status = e["status"]
        who = e.get("to") or "(not sent)"
        lines.append(f"- **{e['site']}** — {status} — {who}")
        if e.get("detail"):
            lines.append(f"  - {e['detail']}")
    with open(LOGMD, "a", encoding="utf-8") as fh:
        fh.write("\n".join(lines) + "\n")


# --------------------------------------------------------------------- main
def main():
    cfg = json.load(open(CONFIG, encoding="utf-8"))
    cc = cfg["cc"]
    # GSC lags ~3 days.
    end = datetime.date.today() - datetime.timedelta(days=3)

    print(f"MODE: {'SEND' if SEND else 'DRY RUN'}   window ends {end}\n")

    import azwc_mail
    import azwc_signature_v2 as SIG
    from email.message import EmailMessage
    from email.utils import formatdate, make_msgid

    entries = []
    internal_notes = []

    for site in cfg["sites"]:
        name = site["name"]
        data = collect(site, end)

        if data is None:
            reason = ("no Search Console access" if not site.get("gsc_property")
                      else "Search Console returned no data")
            print(f"  {name:<30} SKIPPED — {reason}")
            internal_notes.append(f"{name} ({site['domain']}): {reason}. {site.get('notes','')}")
            entries.append({"site": name, "status": f"not sent — {reason}",
                            "to": None, "detail": site.get("notes", "")})
            continue

        body = build_report_text(site, data)
        print(f"  {name:<30} data OK — {data['cur']['clicks']:,.0f} clicks, "
              f"{data['cur']['impressions']:,.0f} impressions")

        to = site.get("client_email") if site.get("send_to_client") else None
        if not to:
            # No client to send to, but Yasir still gets the full branded
            # report rather than a paragraph buried in a plain-text digest.
            if SEND:
                azwc_mail.send_and_file(compose(site, data, cc, None))
            entries.append({"site": name, "status": "sent (internal)", "to": cc,
                            "detail": f"{data['cur']['clicks']:,.0f} clicks, "
                                      f"{data['cur']['impressions']:,.0f} impressions"})
            continue

        # Some clients have a standing rule about who else must be copied.
        # Everything IT is the live example: every email to Faraz also goes to
        # Ahson, a director. A global cc cannot express that, so each site may
        # add its own recipients on top of it.
        site_cc = ", ".join([cc] + list(site.get("cc_extra") or []))

        if not SEND:
            entries.append({"site": name, "status": "dry run", "to": to,
                            "detail": "cc " + site_cc})
            continue

        detail = (f"{data['cur']['clicks']:,.0f} clicks, "
                  f"{data['cur']['impressions']:,.0f} impressions")
        built = compose(site, data, to, site_cc)

        # Client-facing reports go to Yasir for approval first, not straight to
        # the client. He asked for this explicitly before the first automated
        # send: "I need to see first what you sent." Flip require_approval to
        # false in sites.json once he is happy, and this reverts to sending
        # directly. Internal copies to ceo@ are unaffected - approving your own
        # mail is pointless.
        if cfg.get("require_approval", True):
            import approval  # resolved via the path added at import time
            approval.queue_draft(
                built,
                summary=f"Weekly SEO report — {name}",
                why=(f"{detail} for the week ending {end}. Goes to {to}, cc {site_cc}. "
                     f"Approve and it sends; Hold and it does not."))
            entries.append({"site": name, "status": "queued for approval", "to": to,
                            "detail": detail})
            continue

        azwc_mail.send_and_file(built)
        entries.append({"site": name, "status": "sent", "to": to, "detail": detail})

    # One internal summary to Yasir, always - including what could not be run.
    if SEND:
        msg = EmailMessage()
        msg["From"] = "Team AZ Web Corp <requests@azwebcorp.com>"
        msg["To"] = cc
        msg["Subject"] = f"Weekly SEO run — {datetime.date.today():%d %b %Y}"
        msg["Date"] = formatdate(localtime=True)
        msg["Message-ID"] = make_msgid(domain="azwebcorp.com")
        summary = "\n\n".join(internal_notes) if internal_notes else "(nothing internal)"
        status = "\n".join(f"  {e['site']}: {e['status']}"
                           + (f" -> {e['to']}" if e.get("to") else "")
                           for e in entries)
        msg.set_content(
            "Weekly SEO run completed.\n\nWhat happened:\n" + status +
            "\n\n" + "-" * 62 + "\n\n" + summary +
            "\n\nFull record: seo-audit/weekly-report/sent-log.md\n")
        azwc_mail.send_and_file(msg)
        entries.append({"site": "(internal summary)", "status": "sent", "to": cc})

    if SEND:
        record(entries)
        print(f"\nRecorded {len(entries)} entries in sent-log.md")
    else:
        print("\nDRY RUN — nothing sent, nothing recorded. Re-run with --send")


if __name__ == "__main__":
    main()
