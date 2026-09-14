# Rebound Square integration — September 12, 2026

Source baseline: `C:/Users/yasir/Documents/Final-Capstone/rebound-body-studio/booking`.
Target: `https://azwebcorp.com/preview/rebound/booking/`.
Account requested by Yasir: `massage@reboundbodystudio.com`.

The website uses Square-hosted checkout for the existing $265 and $360 three-session packages. A purchase remains independent of appointment booking. Completed, server-verified payments activate the existing balance; package gifting, booking, reservations and the session ledger continue to use the existing system.

## Connection needed

An email address identifies Randi's account but does not authorize an API connection. No Square password, API token, developer application or authenticated Square session was supplied in this task. The integration stays disabled without a validated configuration. Existing package ordering and studio-recorded payment remain usable.

The account owner signs in at [Square Developer Console](https://developer.squareup.com/apps) with the Square account associated with `massage@reboundbodystudio.com`. For this single-business installation, a developer application owned by the same Square account can supply a production access token. If an agency-owned application is used instead, use Square OAuth to obtain the seller's authorization and implement token refresh; do not put the agency's personal access token into this installation.

Do not send passwords or access tokens in email or chat. Enter credentials directly into a private operator file or the server's protected configuration through the established SSH connection. `booking/bin/square-config.php` accepts JSON on stdin and never prints credentials. On the production Linux host, the configuration defaults to the hosting account's home directory under `.config/rebound-square/config.php`, outside `/html`, with mode 0600. `REBOUND_SQUARE_CONFIG` can override that private path. The CLI script and new Square classes also reject direct web requests independently of the host's rewrite rules. Windows development defaults to `booking/config.php`, excluded from Git.

1. Run `square-config.php discover` with `environment` and `access_token` on stdin. This reads the merchant and locations. Confirm the business is Rebound Body Studio; do not guess the merchant from the email address.
2. In that same Square application, create a production webhook subscription for `payment.created`, `payment.updated`, `refund.created` and `refund.updated`, API version `2026-08-19`. Its exact notification URL is `https://azwebcorp.com/preview/rebound/booking/api/square-webhook.php`.
3. Run `square-config.php install` with `environment`, `access_token`, the verified `merchant_id` and `location_id`, `webhook_signature_key`, and the exact `webhook_url` on stdin. This verifies an active merchant, active USD location and card-processing capability. Installation leaves online checkout disabled.
4. Send a signed test notification from the Square Developer Console. Confirm a successful delivery. The server records proof tied to that exact merchant, location, environment, URL and signing key. Read-only `square-config.php check` reports readiness.
5. Complete a real Square sandbox checkout and refund on an isolated test database before enabling production. Set `REBOUND_DB_PATH`, `REBOUND_SQUARE_CONFIG`, and `REBOUND_SQUARE_ALLOW_SANDBOX=1` only for that test server. The public production UI rejects sandbox mode by default.
6. Run `square-config.php enable` after connection verification. It requires a matching signed webhook receipt. Verify both package checkout amounts and the receipt merchant, then confirm the customer return page and dashboard. Any real paid test requires the owner's authorization for the charge.

The production checkout does not collect card numbers, charge a stored card, create subscriptions, or add developer application fees. Square collects payment details on its own hosted page. Website package links are never sent to Square; the return page uses the buyer's browser session or the private link already in the order email.

## Payment behavior

- Prices come from the saved server-side package quote in integer USD cents. Tipping, coupons, rewards and shipping collection are disabled for these fixed-price packages.
- An immutable checkout request and idempotency key are saved before contacting Square. Retries after timeouts recover the same checkout.
- Redirects, authorization-only and failed payments do not activate credits. Webhooks require an exact HMAC signature, matching merchant, and a fresh Payments API read. Payment order, location, currency and full amount must match before activation.
- Payment IDs, order IDs and receipts have uniqueness constraints. Package activation and the transactional email are committed together and tolerate duplicate delivery.
- The customer and studio can reconcile a payment directly with Square if a webhook is delayed. Square retries failed notifications; transient failures receive HTTP 503.
- Full refunds block further redemption. Partial refunds or payment mismatches place the package in `payment_review`. Used/reserved session history and existing appointments remain visible; Randi reviews affected appointments in the dashboard. No automatic refund is sent.
- An open or uncertain checkout blocks manual payment entry/cancellation to prevent duplicate collection. The dashboard can close the link and independently verify Square cancelled the order before accepting an offline payment.
- Unrelated Square Stand payments do not automatically create packages. In-person package payments continue through the existing manual receipt workflow.

## Validation

`php booking/tests/square_regression.php` uses a deterministic simulated Square transport with a disposable SQLite database. It covers checkout retries after an uncertain timeout, exact prices, private token handling, signed notifications, wrong merchants/locations, capture-only activation, API failure retries, duplicate events, refund ordering, payment mismatches, cancellation and unrelated POS payments. Existing package, mail queue, studio workflow and admin-login regressions must also pass. These simulated tests are not a completed Square sandbox transaction.

## Official API references

- [CreatePaymentLink](https://developer.squareup.com/reference/square/checkout-api/create-payment-link)
- [Validate webhook notifications](https://developer.squareup.com/docs/webhooks/step3validate)
- [Retrieve payment](https://developer.squareup.com/reference/square/payments-api/get-payment)
- [Manage checkout and cancellation](https://developer.squareup.com/docs/checkout-api/manage-checkout)
- [Checkout options](https://developer.squareup.com/docs/checkout-api/optional-checkout-configurations)

## Release verification

The application changes and additive migration `005_square.sql` were published to the existing Rebound preview. The final release also refreshes package links throughout all fourteen marketing pages, both source generators, the booking form, the dashboard and package emails. Return and gift links carry the new page version. Pending browser requests cannot switch the selected package or redirect to an earlier order after a customer starts another purchase. The final remote backup is `/html/.rebound-backups/20260912-square-3`; the pre-integration backup is `/html/.rebound-backups/20260912-square-1`. Deployment compared file hashes against the inspected live baseline and verified that all existing customer, appointment, package and settings records were unchanged. There were three existing appointments, zero package orders and zero Square checkouts. No live test payment, customer record, or outbound email was created.

All PHP and JavaScript syntax checks, five PHP regression suites and the isolated HTTP booking/package workflow passed. Live HTTP checks matched the four changed public assets to their release hashes, confirmed both package prices, required admin login, confirmed the webhook waits for configuration, and verified the new PHP access guards. The host caches empty responses on unversioned PHP URLs; guard checks used a fresh version query. Browser checks confirmed the unpaid package balance and disabled payment state.

Live credentials and real Square checkout verification remain the final external dependency. Online card checkout is **not enabled**. The website integration is installed and the account connection is pending.
