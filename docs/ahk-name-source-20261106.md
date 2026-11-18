# AHK name autofill and lead source

## Name diagnosis and correction
Three real Konstruct rows already contained identical full names in fname/lname before GHL received them. An independent GHL read matched the WordPress data. The renderer tested the substring name before specific first/last-name tokens, so both inputs rendered autocomplete="name". Specific tokens now take precedence. Public markup now renders given-name/family-name. This prevents the identified autofill cause; arbitrary manual entry remains possible. Historical CRM/WordPress names were not rewritten.

## Source mapping
All four published GHL workflows previously used the static source Affordable Home King Website. They now map {{inboundWebhookRequest.formData.lead_source}} from the existing private relay. Values distinguish Konstruct Cash Offer, Elementor Property Inquiry, and Elementor Contact Message. Both Konstruct steps share a label. Current-page same-site UTM source/medium are included when present. Missing tags do not imply direct/organic. This is current-submission source, not persistent first-touch attribution; later submissions update GHL's single Source field. No historical channels were guessed.

## Verified November 6, 2026
- Both new regressions failed before implementation, then passed. All PHP regressions and both Node tests passed.
- Both deployed files read back; original renderer and relay backed up under /home/ahkllc/backups/ahk-{renderer,relay}-before-20261106.php. Preserved live-specific renderer differences with a targeted patch.
- Four workflows read back published, active, exact source mapping; other action fields unchanged.
- LiteSpeed purged; ordinary public autocomplete corrected.
- Real browser homepage Next created GHL contact qh8ZExmCCN0O8Ek64CK1 with Facebook / paid source. Final submission enriched the same contact and retained that source.
- Real Elementor property submission updated source to Google / cpc plus form name and preserved address/timeline.
- Real 390px mobile email-only Contact submission succeeded, updated source to its form-specific fallback, and preserved four property fields. No horizontal overflow. One matching contact by email and phone.
- Clearly labeled test email ahk-source-20261106@example.invalid; Konstruct submission #20 completed at page 2. Tests retained for inspection.
- Every original Konstruct/Elementor submission/value and form configuration unchanged. Existing Elementor and portal adapters unchanged. Temporary export/purge helpers independently absent.

## Requested CSV
12 real-presenting submissions from 11 distinct emails: 7 Konstruct, 5 Elementor. Tests and obvious spam excluded. Two separate inquiries from one seller retained. One plausible business-assistance inquiry labeled not a seller. Exact repeated full names displayed once with a note. No identity/contactability verification is implied. No lead PII committed.

## Rollback
Restore the two PHP backups, restore workflow Source values to Affordable Home King Website, and purge LiteSpeed. Never restore old submission snapshots over new leads.
