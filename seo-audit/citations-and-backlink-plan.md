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
