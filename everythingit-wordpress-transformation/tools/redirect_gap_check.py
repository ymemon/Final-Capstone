#!/usr/bin/env python3
"""
eit_redirect_gap_check.py

Cross-references eit_index_status.csv (output of eit_index_check.py) against
the live .htaccess redirect rules to find:

  1. Flagged URLs (NOT_INDEXED / LEGACY_STILL_INDEXED / SUPPRESSED / JUNK)
     that have NO matching RewriteRule at all -> real gap, needs a rule.
  2. Flagged URLs that DO have a rule -> the problem isn't a missing
     redirect, it's either crawl lag or the rule isn't firing live.
     Use --live-check to find out which.

Usage:
    python eit_redirect_gap_check.py --csv eit_index_status.csv --htaccess htaccess-current.txt
    python eit_redirect_gap_check.py --csv eit_index_status.csv --htaccess htaccess-current.txt --live-check

Requires: pip install requests
"""
import argparse
import csv
import re

RULE_LINE_RE = re.compile(r'RewriteRule\s+(\^\S+)\s+(\S+)\s+\[([^\]]*)\]', re.IGNORECASE)


def load_rules(htaccess_path):
    rules = []
    with open(htaccess_path, encoding='utf-8', errors='replace') as f:
        for line in f:
            line = line.strip()
            if not line.upper().startswith('REWRITERULE'):
                continue
            m = RULE_LINE_RE.match(line)
            if not m:
                continue
            pattern, target, flags = m.groups()
            if 'R=301' not in flags.upper() and 'R=302' not in flags.upper():
                continue
            rules.append({'pattern': pattern, 'target': target, 'flags': flags})
    return rules


def pattern_to_path(pattern):
    """Best-effort turn a RewriteRule pattern into a real testable /path/."""
    p = pattern.lstrip('^')
    p = re.sub(r'/\?\$?$', '', p)
    p = p.rstrip('$')
    p = p.replace('\\-', '-').replace('\\.', '.').replace('\\/', '/')
    return '/' + p.strip('/') + '/'


def load_audit_rows(csv_path):
    with open(csv_path, encoding='utf-8', newline='') as f:
        sample = f.read(4096)
        f.seek(0)
        try:
            dialect = csv.Sniffer().sniff(sample, delimiters=',\t;')
        except csv.Error:
            dialect = csv.excel  # comma-delimited fallback
        rows = list(csv.DictReader(f, dialect=dialect))
    if rows and (not rows[0].get('bucket') and None not in rows[0]):
        raise SystemExit(
            "Parsed the CSV but found no 'bucket' column — check that "
            f"--csv points at eit_index_status.csv. Columns seen: {list(rows[0].keys())}"
        )
    return rows


FLAGGED_BUCKETS = {
    'PROBLEM_NOT_INDEXED',
    'LEGACY_STILL_INDEXED',
    'PROBLEM_SUPPRESSED',
    'JUNK_INDEXED',
}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--csv', required=True, help='eit_index_status.csv from eit_index_check.py')
    ap.add_argument('--htaccess', required=True, help='current live .htaccess file')
    ap.add_argument('--live-check', action='store_true',
                     help='also fire live HTTP requests to confirm each rule actually redirects')
    args = ap.parse_args()

    rules = load_rules(args.htaccess)
    rule_paths = {pattern_to_path(r['pattern']).rstrip('/') for r in rules}

    audit = load_audit_rows(args.csv)

    gaps, covered = [], []
    for row in audit:
        bucket = row.get('bucket', '')
        if bucket not in FLAGGED_BUCKETS:
            continue
        url = row['url']
        path = re.sub(r'^https?://(www\.)?everythingit\.ie', '', url).rstrip('/')
        if not path:
            continue
        if path in rule_paths:
            covered.append((url, bucket))
        else:
            gaps.append((url, bucket))

    print(f"Loaded {len(rules)} redirect rules from {args.htaccess}")
    print(f"Loaded {len(audit)} audited URLs from {args.csv}\n")

    print(f"=== NO REDIRECT RULE AT ALL ({len(gaps)}) — real gaps, need a new rule ===")
    for url, bucket in sorted(gaps, key=lambda x: x[1]):
        print(f"  [{bucket:22}] {url}")

    print(f"\n=== HAS A RULE, STILL FLAGGED ({len(covered)}) — crawl lag or broken rule, verify live ===")
    for url, bucket in sorted(covered, key=lambda x: x[1]):
        print(f"  [{bucket:22}] {url}")

    if args.live_check:
        import requests
        print("\n=== LIVE REDIRECT CHECK ===")
        sess = requests.Session()
        sess.headers['User-Agent'] = 'EIT-Redirect-Checker/1.0'
        for url, bucket in covered:
            try:
                r = sess.get(url, allow_redirects=True, timeout=15)
                statuses = [str(h.status_code) for h in r.history] + [str(r.status_code)]
                fired = 'OK' if r.history else 'NOT FIRING'
                print(f"  [{fired:10}] {url}")
                print(f"               {' -> '.join(statuses)}   final: {r.url}")
            except requests.RequestException as e:
                print(f"  [ERROR     ] {url}\n               {e}")


if __name__ == '__main__':
    main()
