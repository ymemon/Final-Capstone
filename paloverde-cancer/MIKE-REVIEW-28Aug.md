# Palo Verde — full review of correspondence with Mike Bustard

Reviewed 28 Aug 2026. **83 messages** with `pvcancer.com` across all folders,
June 2025 to date, checked against what is actually live on
`875051.us16.myftpupload.com`.

---

## Where the relationship actually stands

**Mike is not ignoring us — we have his answer and have not closed the loop.**

- **24 Apr 2026** — we told him the site was ready for review and asked for
  the doctors per location, which was the one thing blocking us.
- **2 Jun 2026** — he replied with exactly that list. Nothing was asked of him.
- **19 Aug 2026** — we wrote "still waiting… Please advise."
- **26 Aug 2026** — we sent the revised SEO/marketing roadmap.

So between 2 June and 19 August, **the ball was with us for eleven weeks**, and
the "still waiting" note was sent to a client who had already answered. He has
had no reply to his 2 June email at all.

---

## His requests, verified against the live site

### 2 June 2026 — doctors per location — **all correct**

| Location | Mike asked for | Live |
|---|---|---|
| WVO / Estrella | Zafar, Mamani, Rakkar | matches |
| TBO / Glendale | Rakkar, Mamani, Grover, Ahmad | matches |
| SDO / Scottsdale | Halepota, Grover, Ahmad | matches |
| GTO / Gilbert | Grover, Halepota | matches |

Gilbert lives at **`/east-valley-location/`** (1488 W Elliot Rd, Gilbert AZ),
not a "gilbert" URL — worth knowing before anyone reports it missing.

### 26 January 2026 — feedback fix list — 9 of 10 done

| # | Item | Status |
|---|---|---|
| 1 | PV logo stretched wide in header | **STILL STRETCHED** |
| 2 | Header missing social media links | done |
| 3 | "Our physicians" link not functioning | page since removed |
| 4 | Remove Dr. Langford from landing page | done — 0 mentions |
| 5 | Remove Deirdre and Sydney | done — 0 mentions |
| 6 | Gaps on advanced provider page | page since removed |
| 7 | Locations / Conditions dropdowns broken on some pages | needs interactive testing |
| 8 | Estrella — remove Dr. Chadha | done |
| 9 | Glendale — remove Chadha and Langford | done |
| 10 | Scottsdale — remove Langford and Allissa Ramirez | done |

### 6 March 2026 — PVHOMED changes — 8 of 11 confirmed done

| Item | Status |
|---|---|
| Remove Advanced Provider page | done — 404 |
| Remove Physician page | done — 404 |
| Physician photos clickable to bio | done — `/dr-amol-rakkar/`, `/dr-haider-zafar/` etc. |
| Remove advanced-provider photos/placeholders from Home | done — Shannon, Joanne, Alissa, Tiffany all absent |
| Location picture → map | done — 2 map embeds on each of the four location pages |
| Social links at top of page | done — all four URLs live |
| **Add PET scan location, 16641 N. 40th St, Phoenix AZ 85032** | **NOT DONE** |
| Add GTO to hero rotation | not verifiable from outside |
| Standardise page layout across pages | subjective — needs his eye |
| Remove highlighted sentence in second-opinions section | cannot verify without his screenshot |

---

## The two concrete gaps — BOTH NOW FIXED (28 Aug)

**1. The PET scan address was nowhere on the site — now added.** The
`/pet-scan-imaging/` page existed but carried **no address at all**. Mike
supplied it on 6 March, nearly six months ago.

Added as a fifth entry in the "Our Locations" block, matching the existing
markup exactly, on **both** pages that carry that block — `pet-scan-imaging`
(961) and `medical-oncology` (841). All five locations now list. Verified
live: "16641" and "85032" both present on both pages.

**No telephone number was added.** Every other entry has one, so the gap is
visible — but Mike gave only an address, and a wrong phone number on a cancer
centre's location listing is far worse than a missing one. Worth asking him
for it.

**2. The logo was stretched — now fixed.** It rendered at 98×50 from a 140×81
source: ratio 1.96 against a natural 1.73, roughly **13% horizontal squeeze**.
Item 1 on his January list, the oldest outstanding request in the thread.

Cause: both width and height were being forced, and an image given two fixed
dimensions cannot keep its shape. The fix frees the width and keeps the height,
so the header band is unchanged and the logo is simply ~86px wide instead of
98px. `object-fit: contain` is set as a safety net — if anything ever forces
both dimensions again the logo will letterbox rather than silently distort,
because distortion is the failure that goes unnoticed for seven months and
empty space is the one somebody reports.

Verified: desktop 86×50 (ratio 1.727 vs natural 1.728), mobile 66×38
(1.727 vs 1.728).

---

## What I would say to him

He answered our question in June and heard nothing for eleven weeks. Any note
that opens by chasing him will land badly.

The honest opening is that his 2 June list was applied — all four locations
are correct — and that two older items were missed: the PET address and the
logo. Fix both first, then write. That turns an overdue apology into a status
report with the work already done.

The site is otherwise ready to go live and has been since April.
