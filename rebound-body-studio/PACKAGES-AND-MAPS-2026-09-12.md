# Published package and directions update — September 12, 2026

Published to https://azwebcorp.com/preview/rebound/.

September 12 follow-up: fixed missing navigation below desktop widths by adding a native, accessible Menu disclosure on all 14 pages. Added Google Maps to the homepage with a prominent jump link, retained the contact-page map, and changed both embeds to Google's direct embed URL with immediate loading. Fresh review URL: https://azwebcorp.com/preview/rebound/?b=20260912-navigation-map-3#studio-map. All 14 actual directory URLs (including the exact query strings used by navigation) matched the deployed file hashes. Browser checks confirmed the mobile menu, navigation to the separate contact page, and live Google Maps controls/location link at 33.436626,-111.721018. Screenshot capture was unavailable in the in-app browser; verification used browser DOM/accessibility state. Old unversioned cached links can still show earlier pages; use the fresh review link. No booking code, database, email settings or customer records changed. Static source backup: `/html/.rebound-backups/20260912-navigation-map`; local release: `C:/Users/yasir/Documents/Codex/rebound-navigation-map-20260912`.

## Customer flow

- Two three-session packages: 60-minute massage for $265; 90-minute massage for $360.
- Customers can place an order without selecting an appointment. The form clearly states that no payment is collected online. Payment is arranged through the studio's existing Square workflow or in person.
- Orders remain `pending_payment` until the studio records the full payment, payment method and receipt reference. Recording payment does not charge a card. Duplicate payment references and mismatched amounts are rejected.
- Customers receive a private package link by email. It displays available, reserved and used sessions, expiry and session history. They can recover links through the original purchase email.
- The link authorizes redemption; knowing a purchase ID or buyer email is insufficient. Tokens are hashed in the access table, kept out of URL query strings, and isolated from dashboard login tokens. Recovery links expire after 24 hours. The original package link remains subject to package status, balance and expiry.
- Package links can be shared as gifts. Recipients book with their own name and email. The interface explicitly explains that anyone with the link can use all remaining sessions; a gift link does not reveal the buyer's contact details or payment reference.
- Redemption is limited to therapeutic, sports and myofascial massage at the purchased duration. A booking request reserves one session. Cancellation or decline returns it once. Completion/no-show retains usage. Studio adjustments record an audit reason and support safe retries.
- New customers see the marketing opt-out. Existing opt-outs and customer identity are preserved on repeat package orders.

## Studio dashboard

New Packages section shows orders, payment status, customer contact details and balances. It supports recording payment, cancelling unpaid orders, recording an offline session, restoring a used session and reviewing adjustment history. Reserved sessions must be released through the appointment's cancellation/decline action.

Confirmed appointments now have Completed, No-show and Cancel controls. Package appointment confirmations and management pages identify payment as a prepaid package session. Terminal appointments no longer retain queued reminders.

## Website and catalog

Added Randi's supplied parking and entrance instructions: Speakeasy Salon Suites, purple sign near Knuckle Sandwiches, Suite 109 down the first hall near the back, front TV directory and waiting room instructions. Contact page includes Google Maps and directions. Confirmation and reminder emails also contain directions and the Maps link.

Removed visible memberships, obsolete provider links, outdated booking/widget claims and internal implementation notes from the 14 generated marketing pages. Package links now lead to the website's own order/balance page.

Corrected the live booking catalog, which still contained a sample six-session package and obsolete service variants. It now offers the confirmed 17 Randi service/duration combinations and two real three-packs. Wendy's corrective exercise sessions remain arranged separately. Uncertified cupping stays unpublished and is no longer offered by the public booking API. Historical appointments and their old service rows were preserved.

## Validation and release

- Package regression tests cover order/payment retries, proof of access, cross-package and admin isolation, eligible services/durations, gift bookings, cancellation/decline refunds, no-shows, adjustments, ledger agreement, expiry, recovery, opt-outs and throttling.
- Isolated HTTP integration tests cover the complete order-to-redemption workflow through real endpoints. No external messages were sent.
- Existing mail queue, studio workflow and admin email override regressions passed. PHP/JavaScript syntax and all generated internal routes checked.
- Live checks verified all 20 public release files by hash, the correct service/package catalog, authenticated owner reads and rejection of unauthenticated admin access. Google Maps embed returned HTTP 200.
- 36 files published, plus migrations `003_packages.sql` and `004_current_catalog.sql`. Consistent SQLite backup and original sources are at `/html/.rebound-backups/20260912-packages-maps`; follow-up cache-link release backup is `/html/.rebound-backups/20260912-packages-cache-links`.
- The existing 3 appointments, 2 pending requests, all customers and all settings were preserved. No live package orders, payment records or package emails were created during verification. The temporary Yasir Gmail dashboard override remains active.

The host can cache the unversioned `booking/public/index.html`. Package redemption links explicitly use `?v=20260912-packages-2`, verified against the new form's hash. Static marketing links also carry build stamps. For review, use https://azwebcorp.com/preview/rebound/booking/public/packages.html?b=20260912-packages-maps-2.

## Remaining external dependency

No Square credentials or payment integration settings were available. Automatic online card checkout/capture is not connected. Current package ordering, studio-recorded payment activation and session redemption/tracking are operational. Connecting Square requires access to the studio's Square account. Office photographs and Wendy's certification details remain separate outstanding client-supplied content from the earlier handoff.

Release and verification scripts: `C:/Users/yasir/Documents/Codex/rebound-packages-20260912`. The mail worker did not require modification or restart; new transactional messages use its existing durable queue.
