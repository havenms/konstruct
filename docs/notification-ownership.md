# Staff notification ownership

## Root cause

`frontend/form.js` sends `/step-notification` after Next independently of its `/webhook` call. Final submit saves `/submissions` (which sends the complete submission email), then calls `/webhook`.

`includes/class-webhook-handler.php::process_webhook()` additionally sent a step email on every webhook attempt, including non-2xx responses. That duplicated the Next email and resent mail on delivery retries. On the final page it invoked the step template, while `includes/class-email-handler.php::send_step_notification()` filters data down to the current page. Contact placeholders therefore remained literal in the property-only final-page email.

Make the webhook handler transport-only. Keep the existing dedicated step and final notification owners unchanged. No frontend, recipients, template configuration, GHL destinations, relay transport, or Meta settings change.

## Scope and limits

- Removes webhook-generated staff mail, including retry and failed-delivery mail.
- Preserves the independent Next notification for forms with or without webhooks and the complete final save notification.
- This does not add global exactly-once email delivery. Replaying `/step-notification` or a final `/submissions` request can still resend their emails. External callers relying on `/webhook` alone for mail must use the existing notification/submission paths instead.
- No production deployment or live WordPress files changed. This branch is stacked on `fix/ahk-name-autofill-lead-source` (PR #11), the maintained AHK source chain, not on stale main.

## Verification

`tests/notification-ownership-test.php` executes real REST, webhook, and email classes with synthetic storage, HTTP and mail boundaries. No network or real mail is used. Before the fix it failed on duplicate/retry notifications and unresolved placeholders. After the fix: `notification ownership regressions passed (17 checks)` on PHP 7.4.33.

Run in a PHP 7.4 CLI environment:

```sh
php tests/notification-ownership-test.php
php tests/webhook-progress-test.php
php tests/webhook-handler-test.php
php tests/partial-persistence-test.php
node --test tests/*.test.js
```

The other PHP test files were also exercised. Two unrelated existing test files fail on both this branch and its unchanged base: `tests/ahk-ghl-relay-test.php` and `tests/ahk-source-test.php`. The base contains duplicate declarations of `ahk_ghl_lead_source()` at `deploy/ahk-ghl-webhook-relay.php:12` and `:28`, causing PHP's `Cannot redeclare` fatal error. Missing/invalid relay fixture modes encounter the same pre-existing fatal error. This notification-only patch does not modify that relay. All includes PHP files passed syntax checks; both Node test files passed.
