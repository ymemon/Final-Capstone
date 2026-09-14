# Everything IT — cannibalisation analysis, CORRECTED 2026-09-09 (evening)

> **Read this section before the rest of the file.** The analysis below it was
> made against Search Console data and the *staging* site. Checked against
> **production** the same evening, most of its conclusions do not hold, and the
> redirect map it produced would have taken three live pages down. The original
> is kept underneath, unedited, because the keyword evidence in it is still good
> and it should be obvious what changed.

## What production actually looks like

Every URL below was requested with a cache-busting query string and followed to
its final destination on 2026-09-09.

**1. The "duplicate pages" are unpublished drafts.** `about-us-2`, `contact-2`,
`faq-2`, `our-team-2`, `it-support-galway-2`, `it-support-limerick-2`,
`it-support-waterford-2`, `it-services-2`, `galway`, `limerick`, `waterford`,
`it-support-south-dublin`, `managed-services` — all `post_status = draft`. A
draft is not publicly reachable and cannot compete for a query. Their Search
Console impressions are historical, from before they were unpublished.

**2. Nine of the URLs in the map do not exist on production at all** —
`it-support-cork-2`, `it-support-ireland-nationwide-2`, `south-dublin`,
`dublin-city-centre`, `it-support-dublin-city-centre`, `it-support-cork`,
`it-support-ireland-nationwide`, `business-continuity-services`.

**3. Eleven of the twenty redirects were already live**, via
`wp-content/mu-plugins/eit-legacy-slug-redirects.php` (a substantial,
well-maintained map that the analysis did not know about) or via WordPress's own
canonical guessing for retired slugs.

**4. Five rules pointed the wrong way, and three of those were loops.**
Production already sends the nested URL to the flat one:

| production does | the map would have added | result |
|---|---|---|
| `/business-continuity-services/procurement/` → `/procurement/` | `/procurement/` → `/business-continuity-services/procurement/` | **infinite loop** |
| `/business-continuity-services/third-party-services/` → `/third-party-services/` | the reverse | **infinite loop** |
| `/business-continuity-services/` → `/business-continuity/` | the reverse | **infinite loop** |
| `/business-continuity-services/managed-services/` → `/managed-it-services/` | `/managed-services/` → the nested URL | chain onto a 404 source |
| `/business-continuity-services/professional-services/` → `/managed-it-services/` | the reverse | chain |

`/procurement/`, `/third-party-services/` and `/business-continuity/` are all
live, published, trafficked pages. They would have returned
ERR_TOO_MANY_REDIRECTS until the plugin was removed. The loop guard in the
staged plugin only catches a rule that points at itself; it cannot see a loop
formed with a redirect that already exists elsewhere on the site.

**5. `/it-services/` is not missing — it is the homepage.** Post 146 has the
slug `it-services` and is set as `page_on_front`, so `/it-services/` 301s to
`/`. The claim that the "it services" term is answered by an orphaned duplicate
was wrong; it is answered by the homepage, like the other terms.

## What was actually deployable, and why

`eit-redirect-chain-flatten.php` — five entries, each verified individually:

| URL | today | after |
|---|---|---|
| `/it-support-cork-2/` | 301 → `/it-support-cork/` → 301 → `/cork/` | one 301 to `/cork/` |
| `/it-support-ireland-nationwide-2/` | two hops → `/managed-it-services/` | one 301 |
| `/it-support-south-dublin/` | two hops → `/managed-it-services/` | one 301 |
| `/dublin-city-centre/` | two hops → `/managed-it-services/` | one 301 |
| `/managed-services/` | **404**, with 419 impressions | 301 → `/managed-it-services/` |

Every destination returns 200 and does not itself redirect, so no rule here can
form a loop. It runs at `template_redirect` priority −1, ahead of both the
legacy map (0) and WordPress's `redirect_canonical` (10), which are what supply
the first hop in each chain.

**Status: written, linted, BOM-checked, NOT yet uploaded** — the production
write was refused by the auto-mode classifier and needs explicit authorisation.

## What still stands from the original analysis

The keyword evidence is unaffected and remains the real finding:

- **Every money term ranks the homepage**, and there is no dedicated
  `/it-support-dublin/` or `/managed-it-services-dublin/` page. `managed it
  services dublin` alone is 1,300 searches a month.
- Note before building the latter: `/managed-it-services-dublin` is currently
  301'd to `/managed-it-services/` by the legacy map. That rule has to come out
  first or the new page will redirect away from itself.
- `pbx-admin.everythingit.ie` and `pbx-core.everythingit.ie` are indexed.
- `/eit-menu-styles/` is indexed and should be `noindex`.

## The lesson worth keeping

Staging is not production on this account, and production had a redirect layer
nobody had looked at. Any redirect map for this site must be validated by
following every source *and every destination* on production before it is
deployed — a destination that already redirects turns a rule into a chain, and a
destination that redirects *back* turns it into an outage.

---
---

# ORIGINAL ANALYSIS, UNEDITED — see corrections above

## Everything IT — why the money keywords are stuck at 3–6 instead of 1

Analysis run 2026-09-09 against Search Console (`sc-domain:everythingit.ie`),
1 Jun – 6 Sep 2026.

## The short version

Every one of the target keywords ranks **the homepage**. Not one has a dedicated
page. Meanwhile the site carries **20 pairs of near-duplicate pages** competing
with each other for the same intent, with **11,400 impressions** sitting on the
weaker twin of each pair.

That combination is keyword cannibalisation. When several pages on one site
answer the same query, Google has to pick, and where none is clearly the best
answer it commonly falls back to the homepage — which is precisely the pattern
in the data. A homepage will rarely outrank a competitor's dedicated, focused
service page, which is why these terms plateau around position 3–6.

## Evidence: which page ranks for each target term

Every row below is the homepage.

| Keyword | Volume | Ranking page | Position |
|---|---|---|---|
| it support | 480 | `/` | 5.0 |
| managed it support dublin | 20 | `/` | 3.1 |
| managed it services dublin | 1,300 | `/` | 4.0 |
| it support companies | 1,000 | `/` | 1.8 |
| it support ireland | 10 | `/` | 2.3 |
| it support in dublin | 140 | `/` | 1.4 |
| it support services | 260 | `/` | 1.4 |
| it services | 390 | `/` | 8.7 |

## Evidence: the duplicate pages

**A. Exact `-2` duplicates** — the same page published twice, usually a
migration artefact:

`/about-us/` + `/about-us-2/` · `/contact/` + `/contact-2/` · `/faq/` + `/faq-2/`
· `/our-team/` + `/our-team-2/` · `/it-support-cork/` + `/it-support-cork-2/` ·
`/it-support-galway/` + `-2` · `/it-support-limerick/` + `-2` ·
`/it-support-waterford/` + `-2` · `/it-support-ireland-nationwide/` + `-2`

**B. Same location, two URL patterns** — both indexed, both ranking, splitting
the authority for the same search:

`/cork/` vs `/it-support-cork/` · `/galway/` vs `/it-support-galway/` ·
`/limerick/` vs `/it-support-limerick/` · `/waterford/` vs
`/it-support-waterford/` · `/south-dublin/` vs `/it-support-south-dublin/` ·
`/dublin-city-centre/` vs `/it-support-dublin-city-centre/`

**C. Same service, flat vs nested** — the whole service tree exists twice:

`/managed-services/` vs `/business-continuity-services/managed-services/` ·
`/procurement/` vs `/business-continuity-services/procurement/` ·
`/professional-services/` vs nested · `/third-party-services/` vs nested ·
`/business-continuity/` vs `/business-continuity-services/`

## Evidence: the money pages that do not exist

There is no `/it-support-dublin/`, no `/managed-it-services-dublin/`, and no
`/it-services/` (only an orphaned `/it-services-2/`). Dublin is covered only by
`/north-dublin/`, `/south-dublin/`, `/west-dublin/` and
`/it-support-dublin-city-centre/` — four fragments of a city, none of which
targets the city itself, and between them they compete with each other too.

`managed it services dublin` alone is 1,300 searches a month and is currently
answered by the homepage.

## Also found

- **`/it-services-2/` is indexed (208 impressions) and `/it-services/` does not
  exist.** The "it services" term — 390 searches a month, currently position 8.7
  on the homepage — is partly being answered by an orphaned duplicate URL with no
  clean counterpart. Renaming it to `/it-services/` is a small change with a
  clear payoff.
- `/eit-menu-styles/` is indexed. It is a theme styling helper, not a page for
  the public, and should carry `noindex`.
- Staging is **not** a byte-copy of production: `it-support-cork-2` exists live
  but not on staging, which is why that one URL took two hops in testing there.
  On production the map hits it directly in one.

## What actually needs doing, in order

1. **Consolidate the duplicates.** One canonical page per intent, everything
   else 301'd into it. This is the single biggest lever and it costs nothing but
   care. Redirect map: `redirect-map.csv` in this folder.
2. **Build the two missing money pages** — `/it-support-dublin/` and
   `/managed-it-services-dublin/` — as genuine, focused service pages, then link
   them from the homepage and the relevant location pages.
3. **Re-point internal links** at the surviving URLs so the site itself stops
   voting for the pages we are retiring.
4. **Then** reassess. Steps 1–3 are the ones that move a page from 3 to 1;
   further link building only helps once the site stops competing with itself.

## What cannot be promised

A #1 ranking cannot be guaranteed by anyone. What the above does is remove the
specific, measurable reasons this site is currently held back — a homepage
answering eight different commercial queries, and twenty pairs of pages
competing with each other. That is a real ceiling, and it is liftable.

## Two smaller things found on the way

- `pbx-admin.everythingit.ie` and `pbx-core.everythingit.ie` are indexed by
  Google. They serve empty bodies and carry no `noindex`. Not a ranking issue,
  but internal PBX hostnames should not be in a search index — worth a
  `noindex` header or a robots rule.
- `www.everythingit.ie` **is** correctly 301'd to the apex domain. An earlier
  read of mine suggested otherwise; that was a bad test on my side
  (`-MaximumRedirection 0` reports a redirect as a failure). No action needed.
