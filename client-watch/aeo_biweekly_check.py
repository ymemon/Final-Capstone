"""
Biweekly AEO/GEO check - free substitute for paid AI-citation tracking
(Ahrefs Brand Radar is not available on the current plan).

What this actually measures: real AI/answer-engine crawler traffic (GPTBot,
ClaudeBot, PerplexityBot, Amazonbot, Bingbot, meta-externalagent, etc.) and
/llms.txt requests, from ai-crawler-logger.php's own JSONL logs on each site.
This is a leading indicator (are AI engines crawling us at all), NOT a
measure of actual citations/mentions in AI-generated answers - that still
needs a paid data source.

Runs the existing php ai-crawler-summary.php on each site over SSH (via the
plink wrapper .bat files already set up for each client), collects the
output, and writes one combined dated report.

No AI/API calls - pure SSH + PHP + text parsing, zero ongoing cost.

Usage:
    python aeo_biweekly_check.py
"""

import subprocess
import datetime
import os

HERE = os.path.dirname(os.path.abspath(__file__))
REPORT_DIR = os.path.join(HERE, "aeo-reports")
TOOLS = r"C:\Users\yasir\.claude-tools"

SITES = [
    {
        "name": "azwebcorp.com",
        "bat": os.path.join(TOOLS, "ssh_run.bat"),
        "summary_script": "~/ai-crawler-summary.php",
        "log_dir": "/html/wp-content/ai-crawler-logs",
    },
    {
        "name": "everythingit.ie",
        "bat": os.path.join(TOOLS, "ssh_run_eit.bat"),
        "summary_script": "/home/client_c47ef96dfe_198185/ai-crawler-summary.php",
        "log_dir": "/home/client_c47ef96dfe_198185/html/wp-content/ai-crawler-logs",
    },
    {
        "name": "prestigewindowsaz.com",
        "bat": os.path.join(TOOLS, "ssh_run_prestige.bat"),
        "summary_script": "/home/client_7e42794e6c_1224967/ai-crawler-summary.php",
        "log_dir": "/html/wp-content/ai-crawler-logs",
    },
]

DAYS = 14  # matches the biweekly cadence


def run_site(site):
    cmd_remote = f'php {site["summary_script"]} {site["log_dir"]} {DAYS}'
    try:
        result = subprocess.run(
            [site["bat"], cmd_remote],
            capture_output=True, text=True, timeout=60,
        )
        out = result.stdout.strip() or result.stderr.strip()
        return out or "(no output)"
    except Exception as e:
        return f"ERROR running check: {e}"


def main():
    os.makedirs(REPORT_DIR, exist_ok=True)
    now = datetime.datetime.now(datetime.timezone.utc)
    stamp = now.strftime("%Y-%m-%d")

    lines = [
        f"# AEO/GEO biweekly check - {stamp}",
        "",
        "Free substitute for Ahrefs Brand Radar (not on current plan) - real AI",
        "crawler traffic from server-side logs, not AI-answer citations.",
        "",
    ]

    for site in SITES:
        lines.append(f"## {site['name']}")
        lines.append("")
        lines.append("```")
        lines.append(run_site(site))
        lines.append("```")
        lines.append("")

    report = "\n".join(lines)

    out_path = os.path.join(REPORT_DIR, f"{stamp}.md")
    with open(out_path, "w", encoding="utf-8") as f:
        f.write(report)

    latest_path = os.path.join(REPORT_DIR, "latest.md")
    with open(latest_path, "w", encoding="utf-8") as f:
        f.write(report)

    print(f"Report written: {out_path}")
    print(report)


if __name__ == "__main__":
    main()
