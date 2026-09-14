# Prestige Windows — City Landing Pages, Scope

**Drafted 26 Aug 2026.** Follows the `areaServed` schema fix shipped the same
day. Service area confirmed by the client: **statewide Arizona**, concentrated
in Scottsdale / Gilbert / Chandler / Queen Creek / the Biltmore area of Phoenix.

---

## 1. What shipped already (don't re-do)

`areaServed` is now live on the `GeneralContractor`/`Organization` node —
`State: Arizona` plus `City` nodes for Scottsdale, Phoenix, Gilbert, Chandler
and Queen Creek. Delivered by mu-plugin `prestige-area-served.php`. Before
this, the site published **no machine-readable service-area signal at all**.

Verified: homepage 200 after deploy, WP-CLI bootstraps clean, full graph is
`Place / GeneralContractor+Organization (+areaServed) / WebSite / ImageObject /
WebPage`, and the existing Article/Person strip still works.

That was the cheap half. City pages are the expensive half.

---

## 2. The main risk, stated first

**Five pages built from one template with the city name swapped will not rank,
and may actively hurt the site.**

This is not hypothetical. On AZWebCorp's own site, 77 near-duplicate reseller
product pages had to be bulk-noindexed for exactly this reason — see
[[azwebcorp-seo-ranking-campaign]]. Google treats templated location pages as
doorway pages. The failure mode is not "ranks a bit worse"; it is "the whole
cluster gets ignored, and thin-content signals bleed onto the rest of the site."

So the design constraint that drives everything below: **each city page must
carry something true that appears on no other page.** If we cannot write that
for a given city, that city does not get a page yet.

Current site is 13 pages and 8 posts. Adding 5 thin pages would be a ~38%
increase in page count with near-zero increase in real substance. That ratio is
the thing to avoid.

---

## 3. What makes a city page non-duplicate

Ranked by how much they actually differentiate:

| Signal | Differentiates | Have it? |
|---|---|---|
| Real projects in that city — photos, street/neighbourhood, what was installed | **Very high** | **NO — blocked** |
| HOA / permit rules specific to that municipality | High | Needs research + client confirmation |
| Local housing stock: dominant build era, common window types being replaced | High | Researchable, needs client sanity-check |
| Named neighbourhoods and landmarks | Medium | Researchable |
| City-specific FAQ (different questions, not reworded ones) | Medium | Writable |
| Distinct internal links in/out | Low but free | Writable |
| Testimonials from that city | High | **NO — client has none yet** |

**Two of the three strongest signals are blocked on the client**, and both have
been outstanding since 21 Aug: real project photography and testimonials. This
is the same dependency that is holding up the social launch.

---

## 4. Recommended sequencing

**Do not build five pages at once.** Build one, properly, and see what it does.

### Phase 1 — Scottsdale only
The head office is in Scottsdale (34462 N Scottsdale Rd) and it is the only
city already named anywhere on the site, so it is the one page we can make
genuinely substantive today without inventing anything. It is also the
strongest commercial market of the five.

Build it as the template-setter: whatever depth Scottsdale gets is the bar
every later city has to clear.

### Phase 2 — Gilbert, Chandler, Queen Creek
Only once Scottsdale has real photos and has been indexed long enough to read a
signal. These three are a coherent East Valley cluster and share housing-stock
characteristics, which is precisely why they are the highest duplicate risk of
the set — they need the most deliberate differentiation.

### Phase 3 — Biltmore / Phoenix
Deliberately last, and **not** as a city page. "Biltmore" is a district of
Phoenix, not a municipality. The right shape is either a Phoenix page that
treats Biltmore as one named area within it, or a genuine neighbourhood page if
there is enough project work there to justify one. A page titled "Biltmore"
sitting alongside "Gilbert" and "Chandler" reads as unfamiliar with the market
to exactly the affluent local buyer it is meant to attract.

### Not now
Statewide coverage is real, but it does **not** justify pages for Tucson,
Flagstaff, Mesa, Tempe, Peoria or Glendale. Those would be pure template fills
with no projects behind them — the doorway-page pattern in section 2. The
`areaServed` schema and the FAQ answer already carry the statewide claim.

---

## 5. Page spec (per city)

- **URL:** `/window-replacement-<city>-az/` — descriptive, matches how people
  search, and leaves room for a future `/doors-<city>-az/`. A bare `/scottsdale/`
  says nothing about what is offered.
- **H1:** service + city, not the city alone.
- **Above the fold:** what is offered, the service area statement, one CTA.
- **Body, in priority order:** local projects → city-specific considerations
  (HOA/permits/heat exposure/housing stock) → product range → city FAQ →
  contact.
- **Schema:** `Service` node with `areaServed` narrowed to that one city,
  `provider` pointing at the existing Organization `@id`. Must **not** duplicate
  the sitewide Organization node — that is the `@id` collision that had to be
  fixed on AZWebCorp's own page schema.
- **Internal links in:** FAQ service-area answer, footer, Products, Gallery.
- **Internal links out:** to relevant blog posts (the AZ-heat post is a natural
  fit for every city page) and to Products.
- **Word count:** not a target. Substance is the target. If it cannot exceed
  ~600 words without padding, it is not ready to publish.

---

## 6. Blockers, honestly

1. **Project photos — hard blocker for Phase 1 quality.** No genuine Prestige
   photography exists anywhere in this project. The media library's
   "Marketing Prestige Windows" images are stock, and one is an MLS listing
   photo with a visible ARMLS watermark. A Scottsdale page with stock imagery
   is a worse page than no page.
2. **Testimonials — none exist for Prestige.** Asked again 26 Aug.
3. **No Search Console access.** This is the one that should worry us most:
   **we will have no way to measure whether any of this works.** GSC and GA4
   are both blocked on Google account access and have been since the start of
   the engagement. Building a local-SEO campaign we cannot measure is a real
   problem — this should be escalated to the client ahead of the page build,
   not after.
4. HOA/permit claims must be verified before publishing. Getting a municipal
   requirement wrong on a contractor's site is a liability issue, not just an
   SEO one.

---

## 7. Separate finding — thin testimonials page

`/testimonials/` ("Voices of Prestige") is published, indexable, and contains
no testimonials — just a holding message. The copy is honest, which is right,
but an empty indexable page is the exact "empty page ranking" pattern found
during the AZWebCorp campaign.

Recommend `noindex` until real testimonials exist, then remove it. Costs
nothing and removes a thin-content signal before we start adding pages.

---

## 8. Recommended immediate next actions

1. Escalate GSC/GA4 access to the client — blocks measurement of everything here.
2. Chase project photos, framed as blocking the Scottsdale page specifically
   rather than as a generic ask. A concrete blocker gets answered; a wishlist
   does not.
3. `noindex` the empty testimonials page.
4. Build Scottsdale as soon as photos land.
