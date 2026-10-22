# Affordable Home King: Konstruct → GHL

This site-specific MU plugin forwards two exact, non-secret WordPress HTTP destination aliases to private GHL inbound webhooks. It does not register a public REST endpoint or change form fields. The native webhook handler also uses Konstruct's existing UUID-locked cumulative save method, preventing late page-one callbacks from erasing a completed submission.

## Deployment

- Site: affordablehomeking.com, Konstruct form 1 (`affordable-home-king-cash-offer`).
- Install `deploy/ahk-ghl-webhook-relay.php` as `wp-content/mu-plugins/ahk-ghl-webhook-relay.php`.
- Store a PHP array with `step-1` and `step-2` destinations in `/home/ahkllc/.ahk-ghl-webhooks.php`, outside the web root, mode 0600. Never commit production webhook destinations.
- Set the native form's page-1 and page-2 webhook URLs to their respective aliases in the plugin, enable both, and keep POST. Preserve all field objects, page numbering, notifications, and pixel settings.
- Step 1 workflow: **AHK Website - Step 1 Contact Capture**, `d8183017-f413-4956-95e5-19ea622e4347`.
- Step 2 workflow: **AHK Website - Step 2 Property Details**, `9aa500dc-d300-480f-92b2-cf9514a13fe6`.
- Location: **Affordable Home King**, `dxXZxNmnEaDuqgVSIyxr`.
- The first workflow maps name/email/phone/source using the native Create/Update Contact action. The second maps the same identity plus property address, type, timeline, and description. Each uses a verified inbound sample mapping reference.
- Property address and description use existing CRM fields. Website Property Type and Website Selling Timeline are additional text fields, preserving the form's exact choices without changing existing select options.
- Existing GHL campaigns, global duplicate-contact preferences, WordPress mail recipients, and Meta conversion policy are not changed.

## Tests

```sh
docker run --rm -v "$PWD:/work:ro" -w /work php:7.4-cli sh -c '
  php -l deploy/ahk-ghl-webhook-relay.php &&
  php -l includes/class-webhook-handler.php &&
  php tests/partial-persistence-test.php &&
  php tests/webhook-progress-test.php &&
  php tests/webhook-handler-test.php &&
  php tests/frontend-asset-test.php &&
  php tests/admin-submissions-test.php &&
  php tests/ahk-ghl-relay-test.php &&
  AHK_GHL_TEST_MODE=missing php tests/ahk-ghl-relay-test.php &&
  AHK_GHL_TEST_MODE=invalid php tests/ahk-ghl-relay-test.php
'
node --test tests/form-partial.test.js tests/form-submit.test.js
```

The tests use explicit synthetic fixtures. Keep clearly labeled production test records visible for client inspection.

## Production verification — October 22, 2026

**Passed against the ordinary public homepage and independent WordPress/GHL reads.**

- Test name: `AHKTEST-GHL-20261022-2012 Integration Test`; email `ahk-ghl-20261022-2012@example.invalid`; phone is a reserved fictional US 555 number.
- First run: Next saved submission **6**, UUID `0208a584-c12e-4e9a-b1d5-2ad22c118564`, as page 1. GHL created contact **nMysu4FLYiRGuCFXjrdK** with correct name/email/phone/source and no property fields. No partial-step email was attempted.
- Reload restored page 2, contact values, and the same UUID. Final submit updated submission 6 without changing its creation timestamp; all eight fields were retained. GHL updated the same contact with address, property type, selling timeline, and property description. Contact counts were independently checked by email and phone.
- A second complete run under mobile emulation intentionally reused the contact identity with a new form UUID (`12c45fec-a6ba-4022-a80f-a89c60d65ccf`, submission **7**). GHL still had **one contact**, with the second run's exact property choice, Unicode timeline, and description. Two Konstruct rows are expected because these were two intentional form submissions, not a duplicate from one submission.
- Empty required fields and invalid phone/email stopped progression without sending requests. Rapid double-clicks on Next and Submit caused no additional request pairs. Back/Next reused the second run's UUID. Both success text and cleared resume storage were verified; desktop and narrow-mobile confirmation screenshots are in `verification/`.
- WordPress accepted one final notification per recipient per completed run: `team@affordablehomeking.com` and `bill@affordablehomeking.com`. Subject: **New cash offer request from AHKTEST-GHL-20261022-2012 Integration Test**. Across both deliberate completed tests this is two notifications to each recipient. Inbox receipt was not independently verified.
- A new regression test first reproduced the legacy webhook handler erasing a completed row after a delayed page-one callback. The handler now uses the existing locked, cumulative storage method. The full PHP/Node checks pass. After deploying that guard, actual stale page-one and repeated final-page webhook requests returned HTTP 200; independent reads confirmed completed rows, fields, UUIDs, creation timestamps, and notification count remained unchanged. GHL retained one contact and the final property data.
- Both workflows were reread as **published**, each with an active trigger. Every observed test webhook returned HTTP 200.
- Form fields, non-webhook page settings, other form options, homepage/Elementor content, and all four pre-deployment historical rows were preserved. Private webhook configuration remains outside the web root with mode **0600**. Public form configuration contains aliases, not private GHL webhook destinations.
- Temporary authenticated verification/deployment helpers and the mail observer were removed. Hosting API directory listings independently confirmed no operation helpers in the document root and only the intended relay in `mu-plugins`.
- The test records **remain visible** for Jane. Do not delete them during deployment cleanup.

Rollback copies of the original form configuration/historical records and pre-guard webhook handler are retained outside the web root under `/home/ahkllc/backups/ahk-ghl-20261022-183912/`. The live code is tracked in PR #8, stacked on the partial-persistence PR #7; review remains requested from `devsrijit`.
