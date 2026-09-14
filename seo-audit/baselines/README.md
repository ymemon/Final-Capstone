# Ranking baselines — measure the campaign honestly

Snapshots taken BEFORE / DURING the SEO campaign so improvement can be
measured against real numbers instead of impressions of progress.

## gsc-baseline-2026-08-17_to_08-23.json

**This is the pre-change baseline.** Every fix from the 2026-08-25 campaign
(title/H1 cannibalization, the SEO pricing article, redirect repointing,
Service schema, city-cluster internal links) landed on **2026-08-25**, i.e.
AFTER this window closed. Nothing in this file reflects any of that work.

Headline numbers, contaminated keyword-research rows filtered out:

| metric | value |
|---|---|
| queries | 455 |
| impressions | 2,092 |
| clicks | **2** |
| avg position | **57.2** |

Only a handful of queries reach page 1-2, and they are low-volume brand-ish
terms (`arizona web company`, `arizona website companies`). Every commercial
head term sits at position 30-90.

## How to compare later

Pull the same shape for a matching-length window once 2-6 weeks have passed:

```
python gsc_query.py '{"startDate":"YYYY-MM-DD","endDate":"YYYY-MM-DD","dimensions":["query","page"],"rowLimit":5000}' > baselines/gsc-<dates>.json
```

Compare on **clicks** and **avg position**, not impressions — impressions move
with Google's whims and were never the problem. Filter rows whose query
contains a comma: those are contaminated keyword-research CSV lines, not real
searches (a recurring data-quality issue on this property).

Also re-run `python gsc_inspect.py <slug>...` on the city cluster: if pages
that read "Discovered - currently not indexed" start reporting a real
`lastCrawlTime`, the internal-linking fix worked.
