# AZWebCorp Outreach Pitch Drafts (2026-08-22)

Ready-to-use pitch copy for every verified target in `outreach-tracker.csv` that
has a real, confirmed contact path. Nothing here has been sent — these are
drafts for you (or whoever sends as AZWebCorp) to review, personalize with a
real name, and send. SMTP creds for `info@azwebcorp.com` already exist at
`~/.claude-tools/azwebcorp-smtp-creds.json` if you want me to actually send
any of these once you've reviewed them — just say which ones.

Every pitch below uses real, already-documented AZWebCorp work (from
`SESSION-STATUS.md`) as the content angle — nothing fabricated.

## Sender identity (2026-08-22 update)

Sending as **ceo@azwebcorp.com** (an alias on the same mailbox as
`info@azwebcorp.com` — same SMTP login/password, different From address),
signed by **Yasir Memon**. Logo pulled from the live site:
`https://azwebcorp.com/wp-content/uploads/2024/06/Azwebcorp-black_logo.png`.

Signature used on these pitches (trimmed from the full company signature —
the shopazwebcorp hosting/support catalog and 5-country phone list are
right for a support email, not for a personal editorial pitch):

> **[AZWebCorp logo]**
> **Yasir Memon**
> CEO & Founder, AZWebCorp
> Web/App Development, SEO & Digital Marketing
> 📞 (480) 818-5761
> azwebcorp.com

Phone number confirmed 2026-08-22: using the canonical `(480) 818-5761`,
not `623-670-1611` (the number already flagged elsewhere as a data error).

---

## 1. In Business Magazine Phoenix — email pitch (highest-confidence target)

**To:** editorial@inmediacompany.com (RaeAnne Marsh, Editor)
**Method:** direct email — this one has a real, confirmed inbox, no form.

> **Subject:** Story idea: why your business might be invisible to ChatGPT (even if it ranks fine on Google)
>
> Hi RaeAnne,
>
> I'm Yasir Memon, CEO & Founder of AZWebCorp, a web design/development/SEO agency based in Gilbert. I noticed In Business Magazine has a Guest Columnists section and wanted to pitch something relevant to a lot of your Phoenix-metro small-business readers.
>
> **Idea:** "Why Your Business Might Be Invisible to ChatGPT (Even If You Rank Fine on Google)" — a practical look at what's now called Generative Engine Optimization (GEO): what small businesses need to do so AI tools (ChatGPT, Perplexity, Google AI Overviews) actually surface and describe them correctly, since a growing share of local search now happens through an AI assistant instead of a results page. I'd cover:
> - Why traditional SEO doesn't automatically carry over to AI visibility
> - 3-4 concrete, non-technical things a business owner can check this week (crawler access, structured business info, a plain-language "about us" page)
> - What we've learned first-hand doing this for our own agency site and client work
>
> Happy to write this as a guest column, or just be a source/quote if that fits something you're already working on. Let me know if either's useful.
>
> Thanks,
> Yasir Memon
> CEO & Founder, AZWebCorp | (480) 818-5761 | azwebcorp.com

---

## 2. Lilach Bullock — pitch for their write-for-me form

**Where:** `lilachbullock.com/write-for-me/` (form, not email — paste this as the pitch/message field)
**Fit check:** their guidelines explicitly want page-speed/UX pieces backed by measured results, not portfolio pieces — this is a strong match.

> **Pitch:** "Why Your Site Is Still Slow After Every Standard Fix" — a first-hand technical case study from optimizing our own site's Time-to-First-Byte. We'd already done everything the standard checklist says (PHP 8.2, CDN active, Redis object caching active) and TTFB was still 800ms-1s. The real culprit: 1,205 WordPress post revisions, each carrying a 10-60KB serialized page-builder data blob, silently bloating every database query.
>
> I'd walk through:
> - How we isolated true origin TTFB from cached TTFB to find the real bottleneck
> - Why the standard "just add caching" checklist can leave real bloat sitting underneath it
> - The actual cleanup process, including a database quirk we hit mid-cleanup (MySQL strict mode rejecting a legacy default value) and how we resolved it without data loss
> - What this means for any WordPress/page-builder site with years of edit history
>
> This is our own project, not a client showcase — real before/after numbers, one clear lesson. Happy to write it at whatever length fits your 1,500-3,500 word range.

---

## 3. That! Company — ⚠️ submission path doesn't match what it claims

**Correction from the original research:** their `write-for-us` page does point to `thatcompany.com/writer-introduction`, but that form is actually a **generic sales-lead intake form** (Company / Name / Email / Phone / "Do you run a marketing agency?" / "What services are you interested in?" / "What offer would you like?" — all multiple-choice, no free-text pitch field found, plus a text-marketing consent checkbox). This doesn't look like a real guest-post submission mechanism, despite their own page pointing there.

**Recommendation:** hold off on this one. Submitting a "guest post pitch" through what's structurally a sales-lead form risks getting AZWebCorp's contact info dropped into their sales/marketing funnel (the consent checkbox authorizes "text messages with offers") rather than reaching an actual editor. If you still want to try it, here's pitch copy for whatever open field exists (there may be one past what the fetch captured):

> Guest post pitch: AZWebCorp (Gilbert, AZ web design/SEO agency) — proposed piece: "What Actually Breaks Local SEO Schema (And How We Found It)." A technical, first-hand case study covering a real geo-coordinate schema bug we found and fixed (space-separated lat/long silently breaking a site's entire Local Business schema block due to a plugin's comma-only string-split logic), plus our GEO/AI-crawler-visibility audit process (crawler access, llms.txt, FAQPage schema hygiene). Real numbers, real mechanism, no fluff.

I've flagged this correction in `outreach-tracker.csv` too.

---

## 4. Digital Marketing Gyaan — guest form pitch text

**Where:** `bit.ly/DMG-GuestForm` (a Google Form — exact fields weren't visible without opening it, but this covers the standard "proposed topic + bio" fields most guest forms ask for)

> **Proposed topic:** "Debugging Local SEO Schema and AI-Crawler Visibility for Small Business Sites" — real case studies from running SEO/technical audits for a Gilbert, AZ digital agency and its clients: structured-data bugs that silently break Local Business rich results, and a practical GEO (generative engine optimization) framework for making a small business visible to AI assistants, not just Google.
>
> **Bio:** Yasir Memon, CEO & Founder of AZWebCorp, a web design/development/SEO agency based in Gilbert, AZ, serving the Phoenix metro area.

---

## 5. More Than a Few Words — guest pitch (via contact form / guests-wanted page)

**Where:** `morethanafewwords.com/guests-wanted/` or the "Let's Chat" contact form — no direct email found, they note guests are currently being evaluated for episodes releasing mid-2026.

> Hi Lorraine — I'm Yasir Memon, CEO & Founder of AZWebCorp, a web design/marketing agency in Gilbert, AZ. Really like the "coffee with a colleague, not a TED Talk" format.
>
> Pitch: the unglamorous reality of website speed/SEO fixes for small businesses — what actually moves the needle vs. what gets pitched as a fix — told through specific, real stories (a wrong lat/long coordinate silently killing a client's Google Business schema; a years-old database bloat issue that crept up from normal page-builder use). Practical, no jargon, happy to keep it tight to your format.

---

## 6. Qwoted — not a pitch, an ongoing channel (setup notes)

Not a one-time pitch — sign up free at qwoted.com under `info@azwebcorp.com`, then set up saved-search alerts for: `SEO`, `web design`, `small business marketing`, `local SEO`, `WordPress`, `AI search` / `GEO`. When a matching reporter query shows up, respond fast with a short (2-4 sentence), specific, quotable answer plus name/title/AZWebCorp/website. A link isn't guaranteed — it depends on actually being quoted — so this needs someone checking it periodically rather than a single action.

---

## Not drafted — no real contact path found

- **Page 2 Podcast** — real/active but no self-serve guest form; would need a cold pitch to host Jon Clark's agency (Moving Traffic Media) with a genuinely standout angle given how established the show is. Didn't draft this one — a generic pitch to an industry-leading show without a specific hook is more likely to be ignored than to land.
- **Business Growth Lab** — recommend dropping (see `outreach-tracker.csv` note — niche mismatch, no guest-application path found).
- **Gilbert Chamber**, **ASBA**, **DesignRush**, **The Phoenix Review**, **Phoenix Business Journal**, **AZ Big Media** — these need either a phone call for real pricing (Chamber/ASBA), a paid-tier decision (DesignRush), or a genuine news hook rather than a cold pitch (the press outlets, Phoenix Review roundup) — not pitch-copy problems, so nothing to draft yet.
