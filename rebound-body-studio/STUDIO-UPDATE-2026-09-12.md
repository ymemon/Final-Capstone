# Studio dashboard update — September 12, 2026

Follow-up package, catalog, Google Maps and directions work is published. See [PACKAGES-AND-MAPS-2026-09-12.md](PACKAGES-AND-MAPS-2026-09-12.md) for the current package payment workflow, verification and remaining Square connection dependency.

## Temporary login test address

At Yasir's request, the studio dashboard currently accepts `yasirmemon1976@gmail.com` through `admin_login_email`, with `admin_login_name=Yasir`. Studio alerts and replies retain `massage@reboundbodystudio.com`. The override applies only to dashboard authentication. Clearing the override returns login authorization to the studio address; also clear `admin_login_name` to restore the default name. Previous source/settings are backed up at `/html/.rebound-backups/20260912-login-test`. A fresh login email was requested through the actual login endpoint for Yasir to test delivery. Do not restore the address while he is testing unless requested.

Published to https://azwebcorp.com/preview/rebound/booking/public/admin/index.html.

- Added a month calendar with Arizona dates, day details, month navigation, today and refresh controls, and the existing appointment list.
- Added customer Marketing emails Yes/No controls and a campaign composer with preview, recipient count, send deduplication and campaign history. No campaign was sent during implementation.
- New booking customers receive marketing by default unless they select the clearly labeled opt-out. Existing preferences and unsubscribes survive rebooking. Transactional appointment mail remains independent.
- Corrected unsubscribe URLs. Email links open a POST confirmation form so mail scanners do not change preferences; native email unsubscribe uses POST directly. Both affect marketing only.
- Added the supplied 5–10 minute arrival reminder and cancellation policy to confirmation/reminder emails. Added the policy to booking and cancellation pages. Less than 24 hours: may charge 50%; less than 12 hours, missed appointment or no-show: may charge 100%. Late arrival may shorten the session without reducing the scheduled fee. These are notices, not automatic payment charges.
- Fixed dashboard authorization so the studio owner can approve multiple requests during the existing 15-minute session. Revoked, expired and non-owner tokens cannot access the dashboard endpoints.
- The SMTP worker now preserves List-Unsubscribe headers and rechecks queue eligibility immediately before sending, including opt-outs occurring after a message was leased. The running S4U daemon was restarted and logged version 20260912.

## Validation and deployment

PHP mail-queue and studio-workflow regression tests pass. Isolated HTTP checks pass for month boundaries, repeated approvals, owner access, campaign preview/send retries, GET-safe and POST unsubscribe, and public routes. Public deployed files match local hashes. Authenticated live checks found the existing 3 appointments and 2 pending requests; no bookings or customer preferences were changed. The queue had 0 pending messages. No test email was sent.

Applied `booking/migrations/002_campaigns.sql` on the existing database. Future clean initialization also applies this migration. Runtime release contains 21 source/template/public files; it does not upload local database files, credential files, worker scripts, tests or the intake PDF.

Server backup: `/html/.rebound-backups/20260912-calendar-marketing` includes original files and a consistent SQLite backup. Release and validation scripts are saved locally under `C:/Users/yasir/Documents/Codex/rebound-update-20260912`.

The existing `AZWebCorp Mail IDLE Watch` task and its launcher were restored to their original settings after restarting only the Rebound daemon. `Rebound Mail Drain` remains disabled. Delivery retains the existing dependency on the AZWebCorp Windows host being running and connected.

Policy source: Randi’s `MassageBook-CANCELSTUFF.pdf`, attached to her September 10 email; cancellation/late-arrival wording is on page 6 and marketing preference wording on page 7. The intake form and medical questions remain outside the public site.
