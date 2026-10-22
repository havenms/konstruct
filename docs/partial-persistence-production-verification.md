# Partial persistence production verification

Verified on affordablehomeking.com on 2026-10-22 with explicit production-test authorization.

## Result

- Blank required contact fields and malformed email prevented navigation and produced no persistence requests.
- Desktop Next issued one real multipart POST with `partial=1`, completed page 1, and all four contact fields. The server independently showed row **4**, page **1**, `delivered=0`, with no notification events.
- Reload resumed page 2 and the same UUID `07f9a158-0f7e-4bc8-95c4-ed698a2764ca`, preserving all contact data.
- Missing property fields prevented final submission.
- Mobile final submission issued one real POST with `partial=0`. It updated the same row **4** to page **2**, `delivered=1`, preserving original creation time and all eight contact/property fields. The success UI appeared and resume storage was cleared.
- The test was visibly labeled **AHKTEST Hermes-20261022**, with fictional property details explicitly marked not a real lead.
- Final notification: one WordPress mail attempt and `wp_mail_succeeded` acceptance per configured recipient, at **14:16:04 UTC**. Inbox receipt was not independently checked. No partial mail was attempted.
- Raw form configuration, notification recipients, disabled Meta Pixel settings, homepage content/meta, and all three historical submission rows were unchanged.
- The real dashboard rendered IDs `4,3,2,1`, each once. After exact-identity cleanup, original IDs `1,2,3` remained and test rows were zero. Evidence was retained privately outside the document root. The temporary mail observer and operation helpers were removed.

## Cache and deployment

Both the post-content asset manager and the shortcode renderer must version the frontend script. The homepage uses the latter path. The ordinary homepage was verified to emit `form.js?ver=1.3.1.1792678038` after LiteSpeed purge.

Production's renderer has pre-existing differences from the repository. Only the exact cache-version line was changed; every other live byte was preserved. The rest of the five deployed files matched the PR contents byte-for-byte.

| Production file | SHA-256 |
| --- | --- |
| `frontend/form.js` | `5c28d86ca8b5089b81582d7165300fc15a04dd925ce6ed485d7d9b4f6ee217d5` |
| `includes/class-form-storage.php` | `32cf0fea837ca83bd2c697b6b8cc134bd1e65a3c8d30e494a222c9e9a09dcd55` |
| `includes/class-rest-api.php` | `684bdd72ed0a63612406fc1a7a74b9ef9e3232b8b067d9e4c17cd49619ec27ee` |
| `includes/class-asset-manager.php` | `6384ef349bc1760ffcd940a006c0cc28377d6da56dc7262a16d45bc1830188dc` |
| `includes/class-form-renderer.php` | `f01e467267d1a1ee406d3eef64e8c512b811adb3925821768c9bd65a70d8253d` |
| `admin/submissions.php` | `266b4d8eff021a7f2fd4925ade7cb31225f4df57eef9b0a53e4b416d02733214` |

## Automated checks

- Node frontend partial persistence and final submission tests: pass.
- PHP **7.4** storage, REST, dashboard rendering, both asset enqueue paths, and legacy webhook tests: pass.
- PHP syntax checks for changed production PHP files: pass.
- Added shortcode-renderer cache-version regression test was observed failing against the old renderer, then passing after the one-line fix.

## Limits

- Mail acceptance does not prove inbox receipt.
- Existing page-level mobile overflow from surrounding layout/decorations was observed; form controls fit the 390px viewport. No unrelated presentation changes were made.
- Final/partial inference uses configured page count; changing a form's page count for existing submissions remains a compatibility consideration.
- Review was requested from DevSrijit; production authorization did not constitute reviewer approval.
