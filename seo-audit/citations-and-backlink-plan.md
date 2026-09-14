# AZWebCorp Citation & Backlink Plan (started 2026-08-22)

## Canonical NAP (use exactly, everywhere)
- **Business name:** AZWebCorp
- **Phone:** (480) 818-5761
- **Address:** 4690 E Laurel Ave, Gilbert, AZ 85234
- **Email:** info@azwebcorp.com
- **Website:** https://azwebcorp.com
- **Category:** Web Design / Web Development / SEO Agency

## Already have a citation/link (confirmed via GSC Links report, 2026-08-21)
- yellowpages.com
- superpages.com
- mapquest.com
- poyst.com
- linkedin.com (article mention)

## Target list — legitimate directories, not yet confirmed present

### Tier 1 — general/major, do first
| Directory | Status | Notes |
|---|---|---|
| Google Business Profile | needs verification | check if already claimed; if not this is the single highest-value one |
| Bing Places for Business | not confirmed | |
| Apple Business Connect | not confirmed | |
| Facebook Business Page | not confirmed | blocked earlier this project on Meta credentials |
| Better Business Bureau (BBB) | not confirmed | |
| Yelp for Business | not confirmed | |
| Nextdoor Business | not confirmed | |

### Tier 2 — industry-specific (web design/dev agencies)
| Directory | Status | Notes |
|---|---|---|
| Clutch.co | not confirmed | requires client reviews to rank, still worth a profile |
| UpCity | not confirmed | |
| DesignRush | not confirmed | |
| The Manifest | not confirmed | |
| GoodFirms | not confirmed | |
| Expertise.com | not confirmed | editorial selection, can't self-submit |

### Tier 3 — local Arizona
| Directory | Status | Notes |
|---|---|---|
| Gilbert Chamber of Commerce | not confirmed | membership-based, likely paid |
| Arizona Chamber of Commerce | not confirmed | membership-based |
| AZ Central / local business directories | not confirmed | |

## Automation status (updated 2026-08-22)

Playwright confirmed working on this machine. Real limits found already:

**Wrong phone number bug — traced, not yet fixed.** `local.yahoo.com`
shows AZWebCorp's phone as `(623) 670-1611` (wrong — should be (480)
818-5761). Traced the source: Yahoo Local mirrors data from
YellowPages.com's network (confirmed via a "claim your Yahoo listing via
yellowpages.com" link on the Yahoo page itself). YellowPages.com blocks
automated/headless browsers via Cloudflare — did not attempt to
circumvent this. **Needs you**: go to yellowpages.com, search "AZWebCorp"
in Gilbert AZ, claim the listing, correct the phone number. Should
propagate to Yahoo Local and other YP-network mirrors afterward — no
separate fix needed there once the source is corrected.

**Email identity gap for verification/outreach.** My Gmail access is
`ymemon@asu.edu` (personal), not `info@azwebcorp.com`. Fine for
citation-signup email verification codes (nobody sees that address
publicly), but wrong identity for outreach pitches representing
AZWebCorp — holding outreach sends until this is resolved (add
info@azwebcorp.com access, or you send from drafts I write).

**Directory automation reality check — CONCLUDED 2026-08-22.** Tested 3
major directories directly via Playwright, hit a hard structural block on
all 3, via 3 different mechanisms:
- YellowPages: Cloudflare bot-detection (hard challenge page)
- Bing: requires Microsoft account login (no credentials available)
- Yelp for Business: silent bot-block (empty page served to headless
  browser — a defensive pattern specifically against this kind of
  automation)

**Conclusion: fully-automated signup on major citation directories is not
achievable.** This isn't a tooling gap — every major directory actively
defends against exactly this kind of automated account creation, for the
same underlying reason Google prohibits automated link schemes: mass
automated business-listing creation is itself a spam vector they have to
defend against. Stopped testing further directories here; the pattern is
consistent enough not to expect a different result from BBB/Clutch/etc.

**What's actually left as genuinely legitimate and low-risk:**
1. Fix the wrong phone number at its YellowPages source (needs a human,
   see above) — highest-value single action, corrects an existing trust
   signal rather than adding a new one.
2. SMTP sending capability is live and tested
   (info@azwebcorp.com via GoDaddy Secure Server) for whenever there's
   real content or a real relationship to build outreach around.
3. Cold outreach to strangers without unique content behind it was
   assessed and deliberately not pursued — near-zero realistic yield,
   real risk of creating exactly the kind of link this project just
   spent effort cleaning up (see 2026-08-21/22 disavow work above).

**Recommendation:** the highest-leverage remaining move isn't
automatable at all — it's the user (or someone at AZWebCorp) personally
claiming the Google Business Profile, Bing Places, and Yelp listings
through their own real, logged-in accounts, which sidesteps every
blocker hit here entirely (a real human session doesn't trigger bot
detection). I can prep the exact NAP data and walk through each flow
step by step whenever that's picked back up.

## Outreach targets (earned links, not directories)
See `outreach-tracker.csv` — separate file, tracks status per pitch since
each of these requires actual human/editorial approval on the other end.

---

## 2026-08-25 update — the "no unique content" blocker has lifted

The 2026-08-22 conclusion above was right at the time: automated directory
signup is structurally blocked everywhere, and cold outreach with nothing
behind it is near-zero yield and mildly risky. **Two of those inputs changed
today**, which re-opens the earned-link side specifically.

### 1. There is now a real linkable asset
`/how-much-does-seo-cost-in-arizona/` went from **43 characters** (an H1 and
nothing else, while ranking at position ~23 for 81 pricing queries) to a
~1,150-word article that takes an unusual and defensible position: it explains
what drives SEO cost and **publishes no price list at all**, on the grounds
that a number quoted before seeing the site is marketing rather than a quote.
That is a genuine editorial angle, not a portfolio piece — which matters,
because the strongest guest-post target on the tracker (Lilach Bullock)
explicitly rejects showcase pieces and asks for lessons.

### 2. There is now an original first-party data story
Today's diagnostic work produced findings that are genuinely uncommon, all
verifiable from our own Search Console:

- **"Our best-ranking page was empty."** The single strongest URL on the site
  by impressions (3,203 impressions, avg position 23.5, 81 queries) contained
  43 characters of content, and its legacy URL was 301'd to a page that did
  not answer the query. A concrete, slightly embarrassing, very teachable
  finding.
- **An entire 13-page city cluster was invisible to Google.** Diagnosed with
  the **URL Inspection API** (not guesswork): some pages read
  `Discovered - currently not indexed` with `lastCrawlTime: None` after eight
  months; others `URL is unknown to Google`. Root cause was that the XML
  sitemap was their only referring URL — and the homepage, which linked to 21
  internal pages, linked to none of them. Ruled out duplicate content
  quantitatively (pairwise 6-gram Jaccard 0.03-0.09).
- **A recurring GSC data-contamination pattern**: keyword-research CSV rows
  (`...,210.00,low,2,approved`) ingested as search queries, inflating a dead
  URL into a 574-impression "opportunity".

The through-line — *"sitemap presence is not discovery, and impressions
without clicks can mean the page is literally empty"* — is a real article, and
it is exactly the technical-lessons genre the drafted pitches target.

### Revised priority order (value x achievability)

| # | Action | Who | Why this rank |
|---|---|---|---|
| 1 | **Claim/verify Google Business Profile** | **you** (real login) | For local commercial terms this outweighs any single backlink. Still unverified whether it is claimed. Walkthrough ready in `claim-listings-walkthrough.md`. |
| 2 | **Fix the wrong phone at its YellowPages source** | **you** (Cloudflare blocks automation) | Corrects an *active wrong trust signal* — (623) 670-1611 is not AZWebCorp's number. Repairing an existing citation beats adding a new one. Propagates to Yahoo Local and other YP mirrors. |
| 3 | **Re-aim the drafted pitches at the data story** | me to draft, you to send | The pitches in `outreach-pitch-drafts.md` were written before this data existed. The findings above are a materially stronger hook than the original angles. |
| 4 | Qwoted signup (free, ongoing) | you (account creation) | Not a one-off pitch — a recurring channel where an Arizona agency-owner voice can earn genuine editorial links. |
| 5 | Directory listings that do not bot-block | you (real sessions) | Bing Places, Apple Business Connect, BBB, Clutch. Data ready in `claim-listings-walkthrough.md`. |

### What has NOT changed
Automated directory signup is still off the table — that was a structural
finding, not a tooling gap, and nothing today alters it. Buying links, mass
directory submission, and reciprocal-link schemes remain out of scope: this
project just spent effort *disavowing* 223 reviewed linking pages including
forum-spam networks, and re-creating that problem would be self-defeating.

**Honest expectation setting:** authority is the slowest lever in SEO. Even
everything above executed well is a 3-6 month arc before it moves
`/arizona-seo-services/` off position 57 — unlike the on-page fixes, which
should show within weeks. Worth starting now precisely because it is slow.
