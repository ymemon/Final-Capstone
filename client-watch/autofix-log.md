# Audit auto-fix log

Append-only. Every change applied autonomously under Lane B
(see `prompt.txt`) gets one entry. Never overwrite this file — it is the
only record of what was changed on client sites without a human in the loop,
and it is the first thing to read when a client asks "who changed this?".

One entry per fix, newest at the bottom:

```
## <UTC timestamp> — <site>
- **Alert:** <sender>, <subject>, received <when>
- **Finding:** what the tool reported
- **Independently verified:** how, and what the live site actually showed
- **Change:** the exact change made (field, page ID, before → after)
- **Verified after:** rendered cache-busted check + result
- **Backup:** path on server
- **Rollback:** exact command
```

No entries yet — Lane B has not fired. The first audit email from a sender in
`audit_sources` will produce one.

## 2026-09-09 — azwebcorp.com, Ahrefs Site Audit (5 meta descriptions)

Trigger: Ahrefs Site Audit mail, forwarded by Yasir. Every finding re-derived
against the live site first; two of the three issues in the mail were wrong.

**Applied** (may-list: "meta title and description problems"), Rank Math
`rank_math_description`, backup in
`seo-audit/azwebcorp-audit-20260909/metadesc-backup-20260909.txt`:

| post | page | was | now |
|---|---|---|---|
| 117 | `/` (front page) | 176 | 131 |
| 88 | `/how-much-does-seo-cost-in-arizona/` | 167 | 132 |
| 2095 | `/web-design-phoenix-az/` | 165 | 139 |
| 2100 | `/web-design-chandler-az/` | 174 | 148 |
| 2099 | `/web-design-mesa-az/` | 191 | 148 |

Wrote our own trims rather than pasting the mail's suggestions (the mail is a
trigger, not a source of truth). Every business fact in the originals is
preserved — services, Gilbert base, Price Road Corridor, Falcon Field B2B,
winter visitors, "a site you own". Phone standardised to (480) 818-5761;
`/web-design-phoenix-az/` previously had it unformatted as 480-818-5761.

Verified on the rendered cache-busted page after `wp cache flush` +
`do_ban()`/`flush_cdn()`: all five 200, all five under 160.

**Not applied, and why**
- *"Add og:type to /ssl/ and /website-security/"* — **finding is wrong.** Both
  already have `og:type`. What they actually lack is `og:updated_time`, which is
  not on the may-list and is not part of the core Open Graph spec anyway.
- *"noindex or remove /2020/ and /2020/07/29/"* — **already done.** Both serve
  `robots: follow, noindex`. Removal would be a deletion: must stage regardless.
- *Slow pages / caching / CDN* — infrastructure, not on the may-list. Reported.

**Found while checking, not in the audit:** 8 of 9 pages checked declare
`twitter:card: summary_large_image` but have **no `og:image`**, so shared links
render with no picture on LinkedIn, Facebook, Slack and WhatsApp. Only
`/how-much-does-seo-cost-in-arizona/` has one. Reported, not auto-applied —
choosing an image per page is a design decision.

### Same run — og:image, applied (not staged)

Yasir, 2026-09-09, on being told this had been left for him: *"I do not have to
wait for your approval for things like this."* Correct — applied immediately.

8 of 9 pages checked declared `twitter:card: summary_large_image` with **no
`og:image`**, so every share of azwebcorp.com on LinkedIn, Facebook, Slack or
WhatsApp rendered as a bare text link. Rank Math had no default social image set
at all (`open_graph_image` absent from `rank-math-options-titles`).

Built a proper 1200x630 card rather than pointing at the logo — a 452x146
wordmark gets letterboxed into a 1.91:1 frame and looks soft upscaled. Composited
the full-resolution original (2172x724) on the brand ink field; its baked black
background was removed by un-premultiplying (alpha = max(R,G,B)), which keeps
anti-aliased edges and leaves the gold Z alone, per [[azwebcorp-logo-2026]].

- attachment **2524**, `/wp-content/uploads/2026/09/azwebcorp-og-card.png`
- set as Rank Math default via `wp option patch insert` — note `patch update`
  fails with "No data exists for key" when the key is absent; `insert` is right
- staging copy at `/html/azwebcorp-og-card.png` deleted after import

Verified on 12 rendered cache-busted pages: all 200, all now carry `og:image`
plus `og:image:width/height` 1200x630, and the image itself returns 200. Page-
specific images still win — `/how-much-does-seo-cost-in-arizona/` correctly keeps
its own `seo-pricing-guide` image; the card only fills the gaps.

Not done: `og:updated_time` on `/ssl/` and `/website-security/`. It is not part
of the core Open Graph spec, no platform renders it, and Ahrefs flags it only
because it counts tags. Cosmetic, deliberately skipped — say the word if wanted.
