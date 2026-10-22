# Affordable Home King: Konstruct → GHL

This site-specific MU plugin forwards two exact, non-secret WordPress HTTP destination aliases to private GHL inbound webhooks. It does not register a public REST endpoint and does not change the shared Konstruct plugin or form fields.

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
  php tests/ahk-ghl-relay-test.php &&
  AHK_GHL_TEST_MODE=missing php tests/ahk-ghl-relay-test.php &&
  AHK_GHL_TEST_MODE=invalid php tests/ahk-ghl-relay-test.php
'
```

The tests use explicit synthetic fixtures. Live verification must independently confirm page-1 persistence, the matching GHL contact, final property-field updates on that same contact, success UI, absence of duplicate contacts, preserved historical records, and cleanup of temporary operation helpers. Keep the clearly labeled production test record visible for client inspection.
