from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_CELL_VERTICAL_ALIGNMENT
from docx.shared import Inches, Pt, RGBColor

OUT = Path(r"C:\Users\yasir\Documents\Final-Capstone\seo-audit\reports\AZWebCorp-Google-Reports-2026-08-30\AZ-Web-Corp-Plain-English-Performance-Report.docx")


def shade(cell, fill):
    from docx.oxml import OxmlElement
    from docx.oxml.ns import qn
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:fill"), fill)
    tc_pr.append(shd)


def add_table(doc, headers, rows):
    table = doc.add_table(rows=1, cols=len(headers))
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    table.style = "Table Grid"
    for i, header in enumerate(headers):
        cell = table.rows[0].cells[i]
        cell.text = header
        shade(cell, "F2C94C")
        for run in cell.paragraphs[0].runs:
            run.bold = True
    for row in rows:
        cells = table.add_row().cells
        for i, value in enumerate(row):
            cells[i].text = str(value)
            cells[i].vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
    doc.add_paragraph()


doc = Document()
sec = doc.sections[0]
sec.top_margin = Inches(0.65)
sec.bottom_margin = Inches(0.65)
sec.left_margin = Inches(0.75)
sec.right_margin = Inches(0.75)

styles = doc.styles
styles["Normal"].font.name = "Aptos"
styles["Normal"].font.size = Pt(10.5)
for name, size, color in (("Title", 28, "18202A"), ("Heading 1", 18, "18202A"), ("Heading 2", 13, "B27A00")):
    styles[name].font.name = "Aptos Display"
    styles[name].font.size = Pt(size)
    styles[name].font.color.rgb = RGBColor.from_string(color)

title = doc.add_paragraph(style="Title")
title.alignment = WD_ALIGN_PARAGRAPH.CENTER
title.add_run("AZ Web Corp\nGoogle Performance Report")
sub = doc.add_paragraph()
sub.alignment = WD_ALIGN_PARAGRAPH.CENTER
sub.add_run("Google Search Console + Google Analytics 4\nPrepared 30 August 2026").bold = True

doc.add_heading("The short version", level=1)
doc.add_paragraph(
    "AZ Web Corp is appearing in Google far more often than last year, but those appearances are not yet turning into enough website visits. "
    "The strongest immediate opportunity is web-development searches: several valuable phrases already rank close to page one. "
    "Analytics tracking also needed correction because the website was sending data to a Lootertech property. The correct AZ Web Corp stream has now been deployed at the website origin."
)

add_table(doc, ["What changed", "Current result", "What it means"], [
    ("Google visibility", "101,950 impressions in the latest 12 months", "Nearly twice the previous year's 51,073 impressions"),
    ("Google clicks", "176 clicks in the latest 12 months", "Down from 249; better rankings and snippets are needed"),
    ("Last 28 days", "14 clicks from 4,405 impressions", "A 0.32% click-through rate"),
    ("GA4", "36 sessions and 28 users in the first 3 days", "Tracking history is new, so trends are not mature yet"),
])

doc.add_heading("What is working", level=1)
for text in (
    "Google visibility has almost doubled year over year.",
    "‘Arizona web development’ already averages position 11.3—very close to page one.",
    "‘Web development’ averages position 4.9, which is a strong ranking position despite producing no recorded clicks in the full export window.",
    "The homepage generated 24 of the first 36 GA4 sessions.",
    "Visitors started forms 14 times, showing real interest in contacting the business.",
):
    doc.add_paragraph(text, style="List Bullet")

doc.add_heading("Where the opportunity is", level=1)
add_table(doc, ["Search phrase", "Google appearances", "Average position", "Recommended action"], [
    ("arizona web development", "1,979", "11.3", "Improve the matching service page to reach page one"),
    ("web development arizona", "1,743", "12.9", "Strengthen title, opening copy, FAQs and internal links"),
    ("web development", "1,478", "4.9", "Rewrite the search title and description to earn clicks"),
    ("arizona web developer", "1,131", "15.2", "Add proof, portfolio examples and Arizona relevance"),
    ("arizona web company", "548", "7.5", "Protect and improve this existing page-one position"),
])

doc.add_heading("What needs attention", level=1)
doc.add_heading("1. Visibility is growing, but clicks are falling", level=2)
doc.add_paragraph(
    "The site received roughly twice as many Google impressions as the previous year, but 73 fewer clicks. This usually means many rankings are too low, or the page titles and descriptions are not persuasive enough when the site appears."
)
doc.add_heading("2. Commercial service pages are underperforming", level=2)
doc.add_paragraph(
    "The Arizona SEO services page appeared 1,572 times in the last 28 days but received only one click. The SEO-cost article appeared 348 times and received no clicks. These pages need stronger search snippets and closer alignment with the searches generating impressions."
)
doc.add_heading("3. An old Instagram article is attracting the wrong traffic", level=2)
doc.add_paragraph(
    "The old ‘free Instagram followers’ article generated 8 of the site's 14 clicks in the last 28 days. Those visitors are unlikely to buy web development or SEO services, so headline click totals overstate the amount of commercially useful traffic."
)
doc.add_heading("4. Conversion tracking is incomplete", level=2)
doc.add_paragraph(
    "GA4 recorded 14 form starts and one form submission, but no event is marked as a key event. The business therefore shows zero conversions even though a form was submitted. Form submissions, phone clicks and email clicks should be configured as key events."
)

doc.add_heading("Recommended priority plan", level=1)
add_table(doc, ["Priority", "Action", "Expected benefit"], [
    ("1 — Immediate", "Purge Cloudflare and verify G-R5RNDSH327 on the public site", "Stops AZ Web Corp traffic from being sent to Lootertech"),
    ("2 — Immediate", "Mark form_submit, phone clicks and email clicks as GA4 key events", "Reports real leads instead of only visits"),
    ("3 — This week", "Optimize the web-development page around the five near-page-one phrases", "Fastest realistic route to more qualified organic traffic"),
    ("4 — This week", "Rewrite titles and descriptions for the SEO service and SEO-cost pages", "Improves click-through rate from existing impressions"),
    ("5 — This month", "Remove, redirect or clearly separate irrelevant Instagram-follower content", "Makes performance reporting reflect potential customers"),
    ("6 — Ongoing", "Review the 28-day GA4 and GSC comparison every month", "Shows which work is producing rankings, traffic and leads"),
])

doc.add_heading("Tracking ownership confirmed", level=1)
add_table(doc, ["Item", "Confirmed value"], [
    ("Google account", "yasirmemon1976@gmail.com"),
    ("Access", "Administrator"),
    ("GA4 property", "247570709"),
    ("Correct measurement ID", "G-R5RNDSH327"),
    ("Incorrect Lootertech ID removed", "G-3WMCLVB9LX"),
])

doc.add_paragraph(
    "Technical detail is preserved in the companion GSC and GA4 Excel workbooks. This document is the plain-English decision report.",
).italic = True

doc.save(OUT)
print(OUT)
