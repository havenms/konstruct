<?php
/**
 * Zoho Flow Connection Page
 * Site-wide connection configured once and reused by every form.
 */

if (!defined('ABSPATH')) {
    exit;
}

$zoho       = new Form_Builder_Zoho_Flow_Handler();
$connection = $zoho->get_connection();
$connected  = $zoho->is_connected();
$region     = $zoho->get_region_label($connection['url']);
?>

<div class="wrap form-builder-admin form-builder-zoho">
    <h1><?php _e('Zoho Flow Connection', 'form-builder-microsaas'); ?></h1>

    <p class="form-builder-zoho-intro">
        <?php _e('Connect once here, then switch Zoho Flow on for any form with a single checkbox. You never need to paste this URL again.', 'form-builder-microsaas'); ?>
    </p>

    <div class="form-builder-zoho-card">
        <div class="form-builder-zoho-status-row">
            <span id="zoho-status-badge" class="form-builder-zoho-badge <?php echo $connected ? 'is-connected' : 'is-disconnected'; ?>">
                <?php echo $connected
                    ? esc_html__('Connected', 'form-builder-microsaas')
                    : esc_html__('Not connected', 'form-builder-microsaas'); ?>
            </span>
            <?php if ($connected && $region): ?>
                <span id="zoho-region" class="form-builder-zoho-region">
                    <?php echo esc_html(sprintf(__('Data centre: %s', 'form-builder-microsaas'), $region)); ?>
                </span>
            <?php else: ?>
                <span id="zoho-region" class="form-builder-zoho-region"></span>
            <?php endif; ?>
        </div>

        <label for="zoho-webhook-url" class="form-builder-zoho-label">
            <?php _e('Zoho Flow webhook URL', 'form-builder-microsaas'); ?>
        </label>
        <input
            type="text"
            id="zoho-webhook-url"
            class="regular-text form-builder-zoho-input"
            value="<?php echo esc_attr($connection['url']); ?>"
            placeholder="https://flow.zoho.com/123456789/flow/webhook/incoming?zapikey=..."
            autocomplete="off"
            spellcheck="false"
        />

        <p class="description">
            <?php _e('Paste the full URL, including everything after the question mark.', 'form-builder-microsaas'); ?>
        </p>

        <div class="form-builder-zoho-actions">
            <button type="button" id="zoho-save" class="button button-primary">
                <?php _e('Save Connection', 'form-builder-microsaas'); ?>
            </button>
            <button type="button" id="zoho-test" class="button button-secondary">
                <?php _e('Send Test', 'form-builder-microsaas'); ?>
            </button>
            <span id="zoho-spinner" class="spinner"></span>
        </div>

        <div id="zoho-message" class="form-builder-zoho-message" style="display:none;"></div>
    </div>

    <div class="form-builder-zoho-card">
        <h2 class="form-builder-zoho-heading"><?php _e('Where do I get this URL?', 'form-builder-microsaas'); ?></h2>
        <ol class="form-builder-zoho-steps">
            <li><?php _e('Sign in to Zoho Flow and click <strong>Create Flow</strong>. Give it a name such as "Website Forms".', 'form-builder-microsaas'); ?></li>
            <li><?php _e('In the trigger list, choose <strong>Webhook</strong> and click <strong>Configure</strong>.', 'form-builder-microsaas'); ?></li>
            <li><?php _e('Set the data format to <strong>JSON</strong>.', 'form-builder-microsaas'); ?></li>
            <li><?php _e('Copy the generated URL and paste it into the box above, then click <strong>Send Test</strong>.', 'form-builder-microsaas'); ?></li>
            <li><?php _e('Back in Zoho Flow, click <strong>Test</strong> to capture the sample payload. Your field names will now be available for mapping.', 'form-builder-microsaas'); ?></li>
        </ol>
        <p class="description">
            <?php _e('One flow can serve every form. Add a <strong>Decision</strong> box after the trigger and branch on <code>form_name</code> to route each form to its own actions.', 'form-builder-microsaas'); ?>
        </p>
    </div>

    <div class="form-builder-zoho-card">
        <h2 class="form-builder-zoho-heading"><?php _e('What gets sent', 'form-builder-microsaas'); ?></h2>
        <p class="description">
            <?php _e('Every field sits at the top level of the payload, so Zoho Flow lists it directly with no nesting to dig through.', 'form-builder-microsaas'); ?>
        </p>
        <pre class="form-builder-zoho-payload">{
  "source": "konstruct",
  "form_id": 3,
  "form_name": "Contact Us",
  "form_slug": "contact-us",
  "page_number": 2,
  "is_final": true,
  "submission_uuid": "a1b2c3d4-...",
  "submitted_at": "<?php echo esc_html(gmdate('c')); ?>",
  "first_name": "Jane",
  "email": "jane@example.com",
  "phone": "+15551234567"
}</pre>
    </div>
</div>
