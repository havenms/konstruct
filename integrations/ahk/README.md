# Affordable Home King client portal delivery

Site-specific MU plugin, not loaded by Konstruct automatically. Install only `ahk-client-portal.php` into this site's `wp-content/mu-plugins/`.

Depends on the existing AHK GHL relay at `pre_http_request` priority 10 and the existing Elementor GHL adapter. This additive priority-20 observer covers the two Konstruct step aliases and the Elementor offer/contact aliases. No GHL target, payload, response, form field, notification, or tracking setting is replaced.

Create a dedicated key for the existing Affordable Home King client through Agency Manager. Store a PHP array with a `key` entry at `/home/ahkllc/.ahk-portal.php`, outside the document root, mode 0600. Never commit that file or the real key.

Portal endpoint is fixed to `https://clients.havenmediasolutions.com/api/leads`, HTTPS verified, no redirects. Absent fields and lead status are omitted, so a partial/contact-only request does not blank phone/property fields or reset a worked lead's status. The portal deduplicates by client/email/phone. Property fields are represented in the portal message.

Failures retry up to eight times using WP-Cron, subject to normal cron execution. Last 50 attempts are in `ahk_portal_delivery` with hashed identity and HTTP status, no contact values or credentials. Exhausted retries remain failed in that bounded delivery log; this is not an unlimited durable queue or SLA. GHL's response remains authoritative for the existing frontend success UI even if portal delivery needs a retry.

Rollback: remove this MU plugin; retain or revoke the dedicated portal key as appropriate. Existing GHL plugins and private webhook configuration remain untouched.

Tests (PHP 7.4 is the verified site runtime):

```sh
php integrations/ahk/tests/portal-test.php
php -l integrations/ahk/ahk-client-portal.php
```

Tests replace only network/storage boundaries. Live browser and both destination readbacks are separate deployment acceptance checks. Test fixture keys are intentionally fake.
