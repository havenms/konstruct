# AHK Elementor forwarding and GHL verification

## Production result: October 27, 2026

- The reported latest first-step-only submission (Konstruct #10) was already present in GHL as contact `yZy9uTmJF3lmFVap9n6b`, created October 26 at 06:24:59 UTC. No replay or backfill was needed.
- Existing Konstruct workflows remain unchanged and published. Page one creates/updates a Contact; page two updates that same Contact. These actions do not create an Opportunity/pipeline card. Partial-step email notifications remain disabled; existing final-submission notifications remain unchanged.
- Added `deploy/ahk-elementor-ghl.php` as an AHK-only MU plugin using Elementor's native validated `elementor_pro/forms/new_record` hook. It forwards all Elementor forms on this site, including shared templates, without changing any widget settings, IDs, email actions, content, or layout.
- Audited all four public Elementor form pages: About Us, Properties, How It Works, and Contact. The WordPress inventory also includes five published saved templates. All nine widget configurations are preserved byte-for-byte.
- Map the actual email by field type, not custom ID: the property forms misleadingly use `email` for the phone input. Split full names, normalize US phone numbers, and support Contact's email-only form without fabricating a phone.

## Routing

The shared private relay keeps its original `step-1` and `step-2` destinations and adds two exact server-only aliases:

- `elementor-offer`: **AHK Website - Elementor Property Inquiry**, workflow `4b6b6d87-adba-489b-b25a-d8f5463e2034`. Maps name, email, phone, source, property type, and message only.
- `elementor-contact`: **AHK Website - Elementor Contact Message**, workflow `3caf891b-d518-4df0-9c7d-c44d6a48c43d`. Maps name, email, optional phone, source, and message only.

Both use native Create/Update Contact actions, verified mapping references, published workflows, and active inbound triggers. Private hook destinations remain in `/home/ahkllc/.ahk-ghl-webhooks.php`, outside the document root, mode 0600. Do not commit private destinations.

Separate field mappings matter: live testing reproduced GHL clearing an existing custom property field when a contact-only payload was sent through the all-property mapping. The final Elementor mappings exclude fields their form does not collect, preventing those erasures.

The adapter records only the last 20 delivery outcomes (UTC time, SHA-256 identity digest, alias, HTTP status, accepted/failed) in a non-autoloaded WordPress option. No raw contact fields or webhook destinations are logged. An HTTP or transport error adds a visible form error rather than reporting success; HTTP acceptance still requires independent CRM readback for end-to-end verification.

## Live verification

Clearly labeled synthetic records remain visible for inspection:

- `AHKTEST-20261027 FirstStep Only`: Konstruct **#12**, GHL **Z9Uz8R353kvJMKAnOiMp**. Clicking Next created the contact with no property fields, without final submission. GHL creation was asynchronous, approximately nine seconds after WordPress persistence.
- `AHKTEST-20261027 Elementor Full`: Konstruct **#11**, GHL **USjU8VsIJQcxClwvwyGA**. The ordinary homepage saved page one then updated the same WordPress row/UUID and GHL contact on final submission. Public Elementor property and email-only Contact submissions reused that same GHL contact.
- After the final property inquiry, the existing address and selling timeline were retained. After the final mobile Contact submission, the phone, address, property type, and selling timeline all remained intact; only the submitted message changed.
- Independent searches by email and phone each returned exactly one contact. Desktop property-form and 390px mobile Contact success states were observed through real AJAX responses, with no stubbed network responses.
- All original WordPress submissions, all Elementor metadata, Konstruct fields/settings, notification recipients, and disabled Meta tracking were preserved. Mail inbox receipt was not independently tested.
- Test-only browser resume storage was cleared. Temporary verification helpers were deleted and their absence independently confirmed by the hosting directory listing. The two intended MU plugins remain installed. Do not delete the labeled test records during cleanup.

Evidence: `verification/ahk-elementor-20261027.json` and `verification/ahk-elementor-mobile-20261027.png`.

## Checks and rollback

PHP 7.4 syntax, all PHP regression files, relay missing/unsafe-destination guards, and Node frontend tests pass. Added regression tests cover field mapping, one delivery per hook, email-only identity, exact per-form routes, missing identity, transport/non-2xx failures, and PII-free outcome telemetry. New routing assertions were observed failing before implementation.

Rollback snapshot: `/home/ahkllc/backups/ahk-elementor-ghl-20261027/`. It contains the original relay, private destination configuration, all Elementor metadata, Konstruct form configuration, and historical submissions. Restore the original relay and private configuration and remove the new Elementor adapter to roll back. Do not restore the submissions snapshot over newer leads.

Code is submitted for review, not merged. Production deployment does not imply reviewer approval.
