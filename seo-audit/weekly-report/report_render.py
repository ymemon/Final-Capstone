"""
Rendering for the weekly SEO report — chart images and branded HTML email.

WHY CHARTS ARE PNGs AND NOT HTML/CSS
Email clients do not run JavaScript, and Outlook ignores most of the CSS a
chart drawn in markup would need. A rendered PNG embedded by Content-ID is the
only approach that looks identical in Gmail, Outlook, Apple Mail and on a
phone. The trade-off is that images can be blocked by default, so every figure
in a chart also appears as text in the tables below it — the email must be
fully readable with images turned off.
"""

import base64
import datetime
import io
import os

import matplotlib
matplotlib.use("Agg")          # no display on this machine
import matplotlib.pyplot as plt
import matplotlib.ticker as mticker

# Brand palette, matching the signature.
INK = "#0d1a1a"
GOLD = "#e6b84d"
GOLD_DARK = "#b8860b"
PAPER = "#ffffff"
MUTE = "#6b7480"
RULE = "#e3e6ea"
LOGO_DARK = "https://azwebcorp.com/wp-content/uploads/2024/06/Azwebcorp-black_logo.png"


def trend_chart(daily, out_path):
    """
    Daily impressions as bars, clicks as a line on a second axis.

    Two axes because the scales are wildly different — impressions in the
    hundreds, clicks in single digits. Plotting them on one axis would flatten
    the click line onto the baseline and make it useless.
    """
    if not daily:
        return None

    dates = [datetime.date.fromisoformat(r["keys"][0]) for r in daily]
    impr = [r["impressions"] for r in daily]
    clicks = [r["clicks"] for r in daily]

    fig, ax1 = plt.subplots(figsize=(8.6, 3.1), dpi=150)
    fig.patch.set_facecolor(PAPER)
    ax1.set_facecolor(PAPER)

    ax1.bar(dates, impr, color=GOLD, width=0.72, label="Impressions", zorder=2)
    ax1.set_ylabel("Impressions", color=GOLD_DARK, fontsize=9)
    ax1.tick_params(axis="y", labelcolor=GOLD_DARK, labelsize=8)
    ax1.tick_params(axis="x", labelsize=8, colors=MUTE)
    ax1.yaxis.set_major_formatter(mticker.FuncFormatter(lambda v, p: f"{int(v):,}"))
    ax1.grid(axis="y", color=RULE, linewidth=0.8, zorder=0)
    ax1.set_axisbelow(True)

    ax2 = ax1.twinx()
    ax2.plot(dates, clicks, color=INK, linewidth=2.0, marker="o",
             markersize=3.5, label="Clicks", zorder=3)
    ax2.set_ylabel("Clicks", color=INK, fontsize=9)
    ax2.tick_params(axis="y", labelcolor=INK, labelsize=8)
    # Whole numbers only — half a click is meaningless.
    ax2.yaxis.set_major_locator(mticker.MaxNLocator(integer=True))
    ax2.set_ylim(bottom=0)

    for s in ("top", "right", "left"):
        ax1.spines[s].set_visible(False)
        ax2.spines[s].set_visible(False)
    ax1.spines["bottom"].set_color(RULE)
    ax2.spines["bottom"].set_color(RULE)

    fig.autofmt_xdate(rotation=0, ha="center")
    ax1.xaxis.set_major_locator(mticker.MaxNLocator(7))
    ax1.xaxis.set_major_formatter(
        matplotlib.dates.DateFormatter("%-d %b") if os.name != "nt"
        else matplotlib.dates.DateFormatter("%d %b"))

    fig.tight_layout(pad=0.6)
    fig.savefig(out_path, facecolor=PAPER)
    plt.close(fig)
    return out_path


def bar_chart(rows, label_key, value_key, out_path, title, colour=GOLD):
    """Horizontal bars for top queries / pages. Longest at the top."""
    if not rows:
        return None
    rows = sorted(rows, key=lambda r: r[value_key], reverse=True)[:8][::-1]
    labels = [r["keys"][0] for r in rows]
    labels = [(l[:46] + "…") if len(l) > 47 else l for l in labels]
    vals = [r[value_key] for r in rows]

    fig, ax = plt.subplots(figsize=(8.6, 0.42 * len(rows) + 0.9), dpi=150)
    fig.patch.set_facecolor(PAPER); ax.set_facecolor(PAPER)
    ax.barh(labels, vals, color=colour, height=0.62, zorder=2)
    ax.set_title(title, fontsize=10, color=INK, loc="left", pad=8)
    ax.tick_params(axis="y", labelsize=8, colors=INK, length=0)
    ax.tick_params(axis="x", labelsize=8, colors=MUTE)
    ax.xaxis.set_major_formatter(mticker.FuncFormatter(lambda v, p: f"{int(v):,}"))
    ax.grid(axis="x", color=RULE, linewidth=0.8, zorder=0)
    ax.set_axisbelow(True)
    for s in ("top", "right", "left"):
        ax.spines[s].set_visible(False)
    ax.spines["bottom"].set_color(RULE)
    for i, v in enumerate(vals):
        ax.text(v, i, f" {v:,.0f}", va="center", fontsize=8, color=MUTE)
    fig.tight_layout(pad=0.6)
    fig.savefig(out_path, facecolor=PAPER)
    plt.close(fig)
    return out_path


# ------------------------------------------------------------------- HTML
def _metric(label, value, sub, good=None):
    """One headline figure. `good` None = neutral, no colour claim."""
    colour = INK if good is None else ("#1e7a4d" if good else "#a3341f")
    return f"""
      <td width="25%" style="padding:0 8px 0 0;vertical-align:top;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
               style="border-collapse:collapse;background-color:#f7f8fa;">
          <tr><td style="padding:14px 14px 12px 14px;">
            <div style="font-size:11px;letter-spacing:.08em;text-transform:uppercase;
                        color:{MUTE};padding-bottom:6px;">{label}</div>
            <div style="font-size:24px;font-weight:700;color:{INK};line-height:1.1;">{value}</div>
            <div style="font-size:12px;color:{colour};padding-top:4px;">{sub}</div>
          </td></tr>
        </table>
      </td>"""


def layman_summary(site, data):
    """What the numbers actually mean, for someone who does not do this for a living.

    Yasir: "at the end it does not put in simple plain words for a layman what
    really the information means."

    The commentary above this states what moved. This says what it means and
    what, if anything, to do about it. Two rules it keeps to:

      - define the jargon in passing, never assume it. "Impressions" and
        "average position" are not everyday words, and a client who has to look
        them up will simply stop reading.
      - no spin. If a number is small, say it is small. A report that dresses up
        one click as momentum is worth nothing the second the client checks.
    """
    cur, prev = data["cur"], data["prev"]
    out = []

    clicks, impr = cur["clicks"], cur["impressions"]
    ctr, pos = cur["ctr"] * 100, cur["position"]

    out.append(
        f"Over these four weeks your site was shown in Google search results "
        f"<strong>{impr:,.0f} times</strong> and people clicked through to it "
        f"<strong>{clicks:,.0f} times</strong>. Being shown is called an impression; "
        f"it means your page appeared on a results page somebody looked at, whether or "
        f"not they clicked.")

    # Average position, explained rather than asserted.
    if pos:
        if pos <= 10:
            where = "which is the first page of results for most searches"
        elif pos <= 20:
            where = "which is roughly the second page &mdash; visible, but well below where clicks happen"
        else:
            where = ("which is several pages deep, where almost nobody scrolls. Most clicks in "
                     "Google go to the top three or four results")
        out.append(
            f"Your average position was <strong>{pos:.1f}</strong>, {where}. That number is the "
            f"average ranking across every search you appeared in, so it moves slowly and a "
            f"change of a point or two is normal noise rather than a signal.")

    # Click-through rate in context.
    if impr:
        out.append(
            f"Of everyone who saw you, <strong>{ctr:.2f}%</strong> clicked. That is the "
            f"click-through rate, and it is mostly a measure of whether your title and "
            f"description in the results made the page look worth opening. A low rate alongside "
            f"high impressions usually means you are being found for searches that are not "
            f"quite what you do.")

    # The honest read on scale, which is where most reports start lying.
    if clicks < 10:
        out.append(
            "Being straight with you: these are small numbers. The site is being seen but very "
            "little of that is turning into visits yet, which is normal early on and is exactly "
            "what the work ahead is meant to change. It is worth watching the direction over the "
            "next couple of months rather than reading much into any single week.")
    elif prev and prev["clicks"] and clicks > prev["clicks"] * 1.2:
        out.append(
            "The direction here is genuinely good, and it is the direction that matters more "
            "than any single week's figure.")

    out.append(
        "Nothing here needs any action from you. If a number looks wrong, or you want any of it "
        "explained differently, just reply to this email.")
    return out


def build_html(site, data, commentary, cids):
    cur, prev = data["cur"], data["prev"]
    a, b = data["window"]
    pa, pb = data["prev_window"]

    def chg(key, higher_is_better=True):
        if not prev:
            return "no prior period", None
        c, p = cur[key], prev[key]
        d = c - p
        pct = (d / p * 100) if p else 0
        if d == 0:
            return "unchanged", None
        word = "up" if d > 0 else "down"
        good = (d > 0) if higher_is_better else (d < 0)
        return f"{word} {abs(d):,.0f} ({pct:+.0f}%)", good

    c_txt, c_good = chg("clicks")
    i_txt, i_good = chg("impressions")
    pos_txt, pos_good = (("unchanged", None) if not prev else (
        (f"{'improved' if cur['position'] < prev['position'] else 'worsened'} "
         f"{abs(cur['position']-prev['position']):.1f}"),
        cur["position"] < prev["position"]))

    ctr_txt = f"was {prev['ctr']*100:.2f}%" if prev else ""

    commentary_html = "".join(
        f'<p style="margin:0 0 14px 0;font-size:14.5px;line-height:1.75;color:{INK};">{p}</p>'
        for p in commentary)

    plain_english = "".join(
        f'<p style="margin:0 0 12px 0;font-size:14.5px;line-height:1.8;color:{INK};">{p}</p>'
        for p in layman_summary(site, data)).rstrip()

    def table(rows, head, path_mode=False):
        if not rows:
            return ""
        out = [f'<tr><td style="padding:0 0 6px 0;font-size:12px;letter-spacing:.06em;'
               f'text-transform:uppercase;color:{MUTE};">{head}</td></tr>']
        body = ""
        for r in sorted(rows, key=lambda x: -x["impressions"])[:8]:
            k = r["keys"][0]
            if path_mode:
                k = k.replace(f"https://{site['domain']}", "") or "/"
            k = (k[:52] + "…") if len(k) > 53 else k
            body += (f'<tr>'
                     f'<td style="padding:7px 10px 7px 0;font-size:13.5px;color:{INK};'
                     f'border-bottom:1px solid {RULE};">{k}</td>'
                     f'<td align="right" style="padding:7px 14px 7px 0;font-size:13.5px;'
                     f'color:{INK};white-space:nowrap;border-bottom:1px solid {RULE};">'
                     f'{r["clicks"]:,.0f}</td>'
                     f'<td align="right" style="padding:7px 0;font-size:13.5px;color:{MUTE};'
                     f'white-space:nowrap;border-bottom:1px solid {RULE};">'
                     f'{r["impressions"]:,.0f}</td></tr>')
        src = (f'<tr><td colspan="3" style="padding:0 0 20px 0;font-size:11px;'
               f'letter-spacing:.07em;text-transform:uppercase;color:{MUTE};">'
               f'Source &middot; Google Search Console</td></tr>')
        return ("".join(out) +
                '<tr><td colspan="3" style="padding:0 0 18px 0;">'
                '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
                f' style="border-collapse:collapse;">'
                f'<tr><td style="padding:0 0 4px 0;font-size:11px;color:{MUTE};">&nbsp;</td>'
                f'<td align="right" style="padding:0 14px 4px 0;font-size:11px;color:{MUTE};">Clicks</td>'
                f'<td align="right" style="padding:0 0 4px 0;font-size:11px;color:{MUTE};">Impressions</td></tr>'
                + body + '</table></td></tr>' + src)

    def img(cid, alt, source="Google Search Console"):
        """A chart with its source named directly underneath.

        Yasir: "make sure that they know what the source is if it's Google
        Search Console, GA4, whatever it is, right under the graph as source."
        A number with no provenance invites the question "says who?", and on a
        report a client is paying for, that question should never come up.
        """
        if cid not in cids:
            return ""
        return (
            f'<tr><td style="padding:4px 0 2px 0;">'
            f'<img src="cid:{cid}" alt="{alt}" width="620" '
            f'style="display:block;width:100%;max-width:620px;height:auto;border:0;"></td></tr>'
            f'<tr><td style="padding:0 0 20px 0;font-size:11px;letter-spacing:.07em;'
            f'text-transform:uppercase;color:{MUTE};border-top:1px solid {RULE};'
            f'padding-top:7px;">Source &middot; {source}</td></tr>')

    return f"""<div style="background-color:#eef1f4;padding:26px 0;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
 <tr><td align="center">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="680"
         style="border-collapse:collapse;width:680px;max-width:100%;background-color:{PAPER};">

   <tr><td style="padding:30px 30px 0 30px;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
     <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
       <tr>
         <td><img src="{LOGO_DARK}" alt="AZ Web Corp" width="150"
                  style="display:block;width:150px;height:auto;border:0;"></td>
         <td align="right" style="font-size:12px;color:{MUTE};">
           Weekly search report<br>{a:%d %b} – {b:%d %b %Y}
         </td>
       </tr>
     </table>
     <div style="border-top:2px solid {GOLD};margin:16px 0 0 0;font-size:0;line-height:0;">&nbsp;</div>
   </td></tr>

   <tr><td style="padding:22px 30px 6px 30px;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
     <div style="font-family:Georgia,'Times New Roman',serif;font-size:21px;color:{INK};">
       {site['domain']}
     </div>
     <div style="font-size:13px;color:{MUTE};padding-top:5px;">
       Compared with {pa:%d %b} – {pb:%d %b}
     </div>
   </td></tr>

   <tr><td style="padding:16px 30px 6px 30px;">
     <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
            style="border-collapse:collapse;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
       <tr>
         {_metric("Clicks", f"{cur['clicks']:,.0f}", c_txt, c_good)}
         {_metric("Impressions", f"{cur['impressions']:,.0f}", i_txt, i_good)}
         {_metric("Click rate", f"{cur['ctr']*100:.2f}%", ctr_txt)}
         {_metric("Avg position", f"{cur['position']:.1f}", pos_txt, pos_good)}
       </tr>
     </table>
   </td></tr>

   <tr><td style="padding:20px 30px 0 30px;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
     <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
       {img("trend", "Daily impressions and clicks")}
     </table>
   </td></tr>

   <tr><td style="padding:4px 30px 0 30px;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
     {commentary_html}
   </td></tr>

   <tr><td style="padding:8px 30px 0 30px;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
     <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
       {table(data['queries'], 'Search terms bringing people to the site')}
       {table(data['pages'], 'Most-seen pages', path_mode=True)}
     </table>
   </td></tr>

   <tr><td style="padding:14px 30px 0 30px;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
     <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
            style="border-collapse:collapse;background-color:#f7f4ec;border-left:3px solid {GOLD};">
       <tr><td style="padding:22px 24px 20px 24px;">
         <div style="font-size:11.5px;letter-spacing:.14em;text-transform:uppercase;
                     color:{GOLD_DARK};padding-bottom:10px;">In plain English</div>
         {plain_english}
       </td></tr>
     </table>
   </td></tr>

   <tr><td style="padding:6px 30px 28px 30px;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
     <div style="border-top:1px solid {RULE};padding-top:14px;font-size:12px;color:{MUTE};line-height:1.7;">
       Figures come directly from Google Search Console and are reported exactly as they are,
       whether the period was good or bad. Reply to this email and it reaches us directly.
     </div>
   </td></tr>

  </table>
 </td></tr>
</table>
</div>"""
