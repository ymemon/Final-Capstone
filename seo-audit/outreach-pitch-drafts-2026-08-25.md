# 2026-08-25 — REVISED PITCHES (v2: "found it on ourselves, now we catch it everywhere")

Supersedes pitches #2, #4 and #5 in `outreach-pitch-drafts.md`, and adds a
follow-up (not a resend) for In Business Magazine.

## The framing, and why v2 changed

**v1 framing (dropped):** "we audited our own site and found embarrassing
problems." Honest, but it reads as a confession, and a reader could fairly
conclude the SEO agency had SEO problems.

**v2 framing (use this):** we found it on our own site first, which is exactly
why it is now a standing first-pass check on every site we take on — and we
keep finding it. The self-audit is the *origin* of the method, not the
headline. Same honesty, but the story is about a repeatable diagnostic rather
than a mistake, and it ends with clients benefiting instead of with an
apology.

This is also simply more accurate. The same family of problems has since shown
up on multiple sites AZWebCorp has taken over — pages nothing links to,
published pages with no real content on them, and in one case an entire site
suppressed from search by a single WordPress setting. Finding our own first is
what made those fast to spot.

## Confidentiality rule for every pitch here

**Never name a client, or describe their site specifically enough to identify
it.** AZWebCorp's own findings are ours to publish in as much detail as we
like; a client's are not. Client evidence appears only as anonymised pattern
("sites we have taken over", "a recent engagement", "more than one site we
inherited"). No names, no URLs, no industries specific enough to pin down, and
no before/after numbers that could be traced back.

## Fact-check reference — AZWebCorp's own site only

Every number below is measured, not illustrative. Do not round up or restate
more dramatically; the specificity is the credibility.

| Claim | Source |
|---|---|
| 3,203 impressions, avg position 23.5, across 81 queries | GSC API, 2026-05-27 to 08-25 |
| that page contained 43 characters (an H1, no body) | `strlen(post_content)` on post 88 |
| its legacy URL 301'd to a page that did not answer the query | `.htaccess` line 26, pre-fix |
| 6 real-content pages (1,100-2,400 words) at 0 impressions | GSC API |
| `Discovered - currently not indexed`, `lastCrawlTime: None`, live 8+ months | GSC URL Inspection API |
| XML sitemap was the only referring URL for several | URL Inspection API `referringUrls` |
| homepage linked to 21 internal pages, none in the cluster | live HTML |
| not duplicate content: pairwise 6-gram Jaccard 0.03-0.09 | computed across all 6 pages |

The claim that this recurs on other sites is **directionally true and
documented internally**, but no client figures are published — keep it
qualitative in every pitch.

---

## R1. Lilach Bullock — strongest fit, replaces pitch #2

**Where:** `lilachbullock.com/write-for-me/` (form)

**Why it fits:** their guidelines reject showcase pieces and ask for measured
lessons. This is a diagnostic method with real numbers behind it.

> **Pitch:** "Sitemap Presence Is Not Discovery: A First-Pass Indexing Check We Now Run on Every Site"
>
> A few years ago I would have told you a page that is written, published and sitting in the XML sitemap is "live". I no longer believe that, and the reason is a check we now run before anything else on a new site — one we only built because we ran it on ourselves first.
>
> On our own agency site, two things turned up that I have not seen written about honestly.
>
> Our single best-ranking URL — 3,203 impressions at an average position of 23.5 across 81 queries — was an empty page. Forty-three characters of HTML: a heading, no body. Its legacy URL had been 301'd to a generic services page that did not answer the question anyone was searching. It had been quietly collecting impressions and zero clicks for years.
>
> Separately, six landing pages carrying 1,100-2,400 words each had zero impressions. Not low — zero. Instead of theorising, I queried Google's URL Inspection API directly. It returned `Discovered - currently not indexed` with `lastCrawlTime: None` on a page that had been live for eight months, and the `referringUrls` field explained it: for several of them the XML sitemap was the only thing pointing at them. Our own homepage linked to 21 internal pages and not one was in that cluster. I ruled out the obvious suspect quantitatively — pairwise 6-gram Jaccard similarity across the six pages was 0.03-0.09, so they were genuinely distinct pages, not spun templates.
>
> That became a standing check, and it keeps paying off: on sites we have since taken over we have found the same family of problems — pages nothing internally links to, published pages with no real content on them, and in one case an entire site suppressed from search by a single WordPress setting. Because we had already worked out how to spot it, those were caught in the first pass instead of six months in.
>
> The article:
> - Why "it's in the sitemap" is a weak enough discovery signal to ignore
> - Reading `coverageState` properly: `URL is unknown to Google`, `Discovered - currently not indexed` and `Crawled - currently not indexed` are three different problems with three different fixes, and they get treated as one
> - Using the URL Inspection API instead of guessing — auth flow and a working script
> - Telling "not indexed because it is thin" apart from "not indexed because nothing links to it", including the similarity check
> - The counter-intuitive one: strong impressions with zero clicks can mean the page is literally empty, and nobody opens it to look
>
> Real numbers throughout, from our own property so I can publish the specifics. 1,500-3,500 words fits comfortably.

---

## R2. In Business Magazine Phoenix — FOLLOW-UP, not a resend

**To:** editorial@inmediacompany.com (RaeAnne Marsh, Editor)

**Status:** the GEO pitch was **already sent 2026-08-22**. This is a short
follow-up on the same thread offering a second, more concrete option. **Send
only if there has been no reply.**

> **Subject:** Re: Story idea — second option, with more concrete data
>
> Hi RaeAnne,
>
> Following up on the AI-visibility idea from a few days ago — no pressure either way. Since then I have been running a diagnostic across the sites we manage that I think is more useful to your readers than my original pitch, because it is concrete and checkable rather than forward-looking.
>
> **"Your Website Might Be Invisible — And Not for the Reason You Think."** Most owners assume being invisible on Google means they need to spend more. Often the cause is duller and free to fix: pages that Google has never actually looked at, because nothing on the site links to them. Being in your sitemap is not enough on its own.
>
> We found this on our own site first — including, humblingly, a page that ranked reasonably well while being completely blank — and turned it into a standard first check. We have since found the same class of problem on other Phoenix-area sites we have taken over, which is why I think it is worth writing up for owners rather than for technicians.
>
> The practical takeaway: before paying anyone for SEO, there are two things an owner can check themselves in about ten minutes that reveal whether they have a visibility problem or a content problem. Non-technical, specific, no product pitch.
>
> Happy to write it as a guest column, or to be a source if you have something in flight where an Arizona agency perspective helps.
>
> Thanks,
> Yasir Memon
> CEO & Founder, AZWebCorp | (480) 818-5761 | azwebcorp.com

---

## R3. More Than a Few Words — replaces pitch #5

**Where:** `morethanafewwords.com/guests-wanted/`

**Why revised:** conversational, story-led format. The arc "found it on
ourselves, now we catch it for everyone" suits it better than a schema bug.

> Hi Lorraine — I'm Yasir Memon, CEO & Founder of AZWebCorp, a web design and SEO agency in Gilbert, AZ. The "coffee with a colleague, not a TED Talk" framing is exactly why I'm pitching this rather than a polished case study.
>
> The story starts with us. Auditing our own site, I found that our best-performing page in Google — thousands of impressions, ranking around page two for dozens of searches — was completely empty. A headline and nothing underneath. It had been like that for years, quietly appearing in search results and earning no clicks at all. Then I found six more pages, properly written, that Google had never even looked at. Not ranked badly — never crawled. Nothing on our own site linked to them.
>
> The useful part is what happened next: that became the first thing we check on any new site, and we keep finding it. Pages nothing links to, published pages with nothing on them. Because we had already been through it ourselves, we now catch it in week one instead of after a year of wondering why the traffic never came.
>
> What I would want to talk about: why the boring structural stuff quietly beats the clever stuff, why "we have a sitemap" is not the reassurance people think, and how an owner can sanity-check their own site without buying anything. Happy to be the cautionary tale in my own story.

---

## R4. Digital Marketing Gyaan — replaces pitch #4

**Where:** `bit.ly/DMG-GuestForm` (Google Form)

> **Proposed topic:** "The First-Pass Indexing Check: Using Google's URL Inspection API Instead of Guessing"
>
> A practical, case-study-driven walkthrough of a diagnostic we developed on our own agency site and now run on every site we take on. Covers: reading `coverageState` correctly (`URL is unknown to Google`, `Discovered - currently not indexed` and `Crawled - currently not indexed` are three distinct problems); why sitemap inclusion is a weak discovery signal by itself; distinguishing a thin-content rejection from an internal-linking failure, including a similarity check to rule out duplicate content quantitatively; and the counter-intuitive case where strong impressions with zero clicks meant the page had no content on it at all. Real, publishable numbers from our own property, plus the anonymised pattern of how often the same issues turn up on sites we inherit.
>
> **Bio:** Yasir Memon, CEO & Founder of AZWebCorp, a web design/development/SEO agency based in Gilbert, AZ, serving the Phoenix metro area.

---

## R5. Qwoted — sharpened saved searches

Unchanged as a channel. Add these alongside the originals, since this material
is directly quotable against them:
`indexing`, `Google Search Console`, `crawl budget`, `technical SEO audit`,
`site not indexed`.

Ready-to-paste line (Qwoted rewards speed):

> "Most 'my site isn't ranking' problems turn out not to be ranking problems at all — the page was never crawled. Being in your sitemap isn't the same as being discovered. If nothing on your own site links to a page, Google is entitled to conclude it doesn't matter. We learned that on our own site, and now it's the first thing we check on anyone else's."

---

## Still not recommended

- **That! Company** — sales-lead form, not editorial. Unchanged.
- **Page 2 Podcast** — no self-serve path; still a cold pitch to an
  established show, though the angle is stronger than before.
- **Business Growth Lab** — niche mismatch.
- **Press outlets / Chamber / ASBA / DesignRush** — need a phone call, a
  paid-tier decision, or a news hook. Not pitch-copy problems.
