<?php
/**
 * Zoho Flow Handler Class
 * Manages the site-wide Zoho Flow connection and payload delivery.
 *
 * The connection is stored once for the whole site so that individual forms
 * only need a single on/off toggle. The webhook URL contains a secret
 * (zapikey) and is therefore resolved server-side only - it is never exposed
 * to the browser.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_Zoho_Flow_Handler {

    /**
     * Option key holding the site-wide connection
     */
    const OPTION_KEY = 'form_builder_zoho_flow';

    /**
     * Zoho Flow hosts across all data centres
     */
    private static $allowed_hosts = array(
        'flow.zoho.com',
        'flow.zoho.eu',
        'flow.zoho.in',
        'flow.zoho.com.au',
        'flow.zoho.jp',
        'flow.zoho.ca',
        'flow.zoho.sa',
        'flow.zoho.com.cn',
        'flow.zohocloud.ca',
    );

    /**
     * Human readable data centre labels keyed by host
     */
    private static $host_labels = array(
        'flow.zoho.com'     => 'United States',
        'flow.zoho.eu'      => 'Europe',
        'flow.zoho.in'      => 'India',
        'flow.zoho.com.au'  => 'Australia',
        'flow.zoho.jp'      => 'Japan',
        'flow.zoho.ca'      => 'Canada',
        'flow.zoho.sa'      => 'Saudi Arabia',
        'flow.zoho.com.cn'  => 'China',
        'flow.zohocloud.ca' => 'Canada',
    );

    /**
     * Metadata keys reserved for routing inside Zoho Flow.
     * A form field using one of these names is prefixed to avoid collisions.
     */
    private static $reserved_keys = array(
        'form_id',
        'form_name',
        'form_slug',
        'page_number',
        'submission_uuid',
        'submitted_at',
        'is_final',
        'source',
    );

    /**
     * Get the stored site-wide connection
     *
     * @return array{url:string,connected_at:string}
     */
    public function get_connection() {
        $defaults = array(
            'url'          => '',
            'connected_at' => '',
        );

        $stored = get_option(self::OPTION_KEY, array());
        if (!is_array($stored)) {
            $stored = array();
        }

        return array_merge($defaults, $stored);
    }

    /**
     * Persist the site-wide connection URL
     *
     * @param string $url Raw URL submitted by an administrator
     * @return true|WP_Error
     */
    public function save_connection($url) {
        $url = trim((string) $url);

        // An empty URL clears the connection
        if ($url === '') {
            update_option(self::OPTION_KEY, array(
                'url'          => '',
                'connected_at' => '',
            ));
            return true;
        }

        $validation = $this->validate_url($url);
        if (is_wp_error($validation)) {
            return $validation;
        }

        update_option(self::OPTION_KEY, array(
            'url'          => $url,
            'connected_at' => current_time('mysql'),
        ));

        return true;
    }

    /**
     * Validate that a URL is a usable Zoho Flow incoming webhook
     *
     * @param string $url
     * @return true|WP_Error
     */
    public function validate_url($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return new WP_Error(
                'zoho_invalid_url',
                __('That does not look like a valid URL. Paste the full webhook URL from Zoho Flow.', 'form-builder-microsaas')
            );
        }

        $parts = wp_parse_url($url);

        if (empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https') {
            return new WP_Error(
                'zoho_not_https',
                __('The Zoho Flow webhook URL must start with https://', 'form-builder-microsaas')
            );
        }

        $host = isset($parts['host']) ? strtolower($parts['host']) : '';
        if (!in_array($host, self::$allowed_hosts, true)) {
            return new WP_Error(
                'zoho_bad_host',
                __('That URL is not a Zoho Flow address. It should begin with https://flow.zoho.com/ (or your regional equivalent such as flow.zoho.eu).', 'form-builder-microsaas')
            );
        }

        $path = isset($parts['path']) ? $parts['path'] : '';
        if (strpos($path, '/flow/webhook/incoming') === false) {
            return new WP_Error(
                'zoho_bad_path',
                __('That is a Zoho Flow link but not a webhook URL. In Zoho Flow open your flow, click Configure on the Webhook trigger, and copy the URL shown there.', 'form-builder-microsaas')
            );
        }

        // The zapikey query parameter carries authentication
        $query = array();
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        if (empty($query['zapikey'])) {
            return new WP_Error(
                'zoho_missing_key',
                __('The webhook URL is missing its zapikey. Copy the complete URL from Zoho Flow, including everything after the question mark.', 'form-builder-microsaas')
            );
        }

        return true;
    }

    /**
     * Friendly data centre label for a stored URL
     *
     * @param string $url
     * @return string Empty string when the host is unknown
     */
    public function get_region_label($url) {
        $parts = wp_parse_url($url);
        $host  = isset($parts['host']) ? strtolower($parts['host']) : '';

        return isset(self::$host_labels[$host]) ? self::$host_labels[$host] : '';
    }

    /**
     * Mask a URL for display so the zapikey is never shown in full
     *
     * @param string $url
     * @return string
     */
    public function mask_url($url) {
        if (empty($url)) {
            return '';
        }

        return preg_replace('/(zapikey=)([^&]{0,6})[^&]*/i', '$1$2' . '...', $url);
    }

    /**
     * Whether the site has a usable connection
     *
     * @return bool
     */
    public function is_connected() {
        $connection = $this->get_connection();

        return !empty($connection['url']) && $this->validate_url($connection['url']) === true;
    }

    /**
     * Resolve which URL a given form should post to.
     * A per-form override wins over the site-wide connection.
     *
     * @param array $form_config Decoded form configuration
     * @return string Empty string when the form should not send
     */
    public function get_effective_url($form_config) {
        $settings = $this->get_form_settings($form_config);

        if (empty($settings['enabled'])) {
            return '';
        }

        if (!empty($settings['override_url'])) {
            return $this->validate_url($settings['override_url']) === true
                ? $settings['override_url']
                : '';
        }

        $connection = $this->get_connection();
        if (empty($connection['url'])) {
            return '';
        }

        return $this->validate_url($connection['url']) === true ? $connection['url'] : '';
    }

    /**
     * Read the Zoho settings block from a form configuration
     *
     * @param array $form_config
     * @return array{enabled:bool,override_url:string,send_on_steps:bool}
     */
    public function get_form_settings($form_config) {
        $defaults = array(
            'enabled'       => false,
            'override_url'  => '',
            'send_on_steps' => false,
        );

        if (!is_array($form_config) || empty($form_config['zoho_flow']) || !is_array($form_config['zoho_flow'])) {
            return $defaults;
        }

        $settings = array_merge($defaults, $form_config['zoho_flow']);

        return array(
            'enabled'       => !empty($settings['enabled']),
            'override_url'  => is_string($settings['override_url']) ? trim($settings['override_url']) : '',
            'send_on_steps' => !empty($settings['send_on_steps']),
        );
    }

    /**
     * Strip secrets from a form configuration before it is handed to the browser.
     * The renderer prints the whole configuration into the page, so the
     * override URL (which contains a zapikey) must be removed first.
     *
     * @param array $form_config
     * @return array
     */
    public function scrub_config_for_frontend($form_config) {
        if (!is_array($form_config) || empty($form_config['zoho_flow'])) {
            return $form_config;
        }

        $settings = $this->get_form_settings($form_config);

        // The frontend only needs to know whether and when to call us back
        $form_config['zoho_flow'] = array(
            'enabled'       => $settings['enabled'],
            'send_on_steps' => $settings['send_on_steps'],
        );

        return $form_config;
    }

    /**
     * Build the flat payload delivered to Zoho Flow.
     *
     * Zoho Flow maps top-level keys directly, so field values are placed at the
     * root rather than nested under a container. Routing metadata is included
     * so a single flow can serve every form via a Decision box.
     *
     * @param array  $form            Form row including form_config
     * @param int    $page_number
     * @param array  $form_data       Raw submitted values
     * @param string $submission_uuid
     * @param bool   $is_final
     * @return array
     */
    public function build_payload($form, $page_number, $form_data, $submission_uuid, $is_final) {
        $payload = array(
            'source'          => 'konstruct',
            'form_id'         => isset($form['id']) ? intval($form['id']) : 0,
            'form_name'       => isset($form['form_name']) ? (string) $form['form_name'] : '',
            'form_slug'       => isset($form['form_slug']) ? (string) $form['form_slug'] : '',
            'page_number'     => intval($page_number),
            'is_final'        => (bool) $is_final,
            'submission_uuid' => (string) $submission_uuid,
            'submitted_at'    => gmdate('c'),
        );

        if (!is_array($form_data)) {
            return $payload;
        }

        $used     = array();
        $position = 0;

        foreach ($form_data as $raw_key => $value) {
            $position++;
            $key = $this->normalise_key($raw_key);

            // A name made entirely of unsupported characters still needs a slot
            // so the value is not silently dropped
            if ($key === '') {
                $key = 'field_' . $position;
            }

            // Never let a field overwrite routing metadata
            if (in_array($key, self::$reserved_keys, true)) {
                $key = 'field_' . $key;
            }

            // Two different field names can normalise to the same key
            // ("First Name" and "firstname" both become "firstname").
            // Suffix rather than overwrite so no value is lost.
            if (isset($used[$key])) {
                $suffix = 2;
                while (isset($used[$key . '_' . $suffix])) {
                    $suffix++;
                }
                $key = $key . '_' . $suffix;
            }

            $used[$key]    = true;
            $payload[$key] = $this->flatten_value($value);
        }

        return $payload;
    }

    /**
     * Reduce a submitted value to something Zoho Flow can map directly
     *
     * @param mixed $value
     * @return string|int|float|bool
     */
    private function flatten_value($value) {
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            // File fields arrive as array('url' => ..., 'name' => ...)
            if (isset($value['url'])) {
                return (string) $value['url'];
            }

            // Checkbox groups arrive as a list of selected options
            $scalars = array();
            foreach ($value as $item) {
                if (is_scalar($item)) {
                    $scalars[] = (string) $item;
                }
            }

            if (!empty($scalars)) {
                return implode(', ', $scalars);
            }

            // Anything else is encoded so no data is silently dropped
            return wp_json_encode($value);
        }

        if (is_null($value)) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Normalise a field name into a safe top-level payload key
     *
     * @param string $key
     * @return string
     */
    private function normalise_key($key) {
        $key = sanitize_key((string) $key);

        return $key;
    }

    /**
     * POST a payload to Zoho Flow
     *
     * @param string $url
     * @param array  $payload
     * @return array{status_code:?int,body:string,success:bool,error:?string}
     */
    public function send($url, $payload) {
        $response = wp_remote_post($url, array(
            'headers'   => array(
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ),
            'body'      => wp_json_encode($payload),
            'timeout'   => 20,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            return array(
                'status_code' => null,
                'body'        => '',
                'success'     => false,
                'error'       => $response->get_error_message(),
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);

        return array(
            'status_code' => $status_code,
            'body'        => wp_remote_retrieve_body($response),
            'success'     => $status_code >= 200 && $status_code < 300,
            'error'       => null,
        );
    }

    /**
     * Deliver a submission to Zoho Flow for a given form.
     *
     * Resolves the destination server-side, builds the flat payload, sends it
     * and records the attempt in the existing webhook log table.
     *
     * @param array  $form            Form row including form_config
     * @param int    $page_number
     * @param array  $form_data
     * @param string $submission_uuid
     * @param bool   $is_final
     * @return array{sent:bool,reason?:string,status_code?:int,error?:string}
     */
    public function deliver($form, $page_number, $form_data, $submission_uuid, $is_final) {
        $form_config = isset($form['form_config']) ? $form['form_config'] : array();
        $settings    = $this->get_form_settings($form_config);

        if (empty($settings['enabled'])) {
            return array('sent' => false, 'reason' => 'disabled');
        }

        // Intermediate pages only send when the form opts into step delivery
        if (!$is_final && empty($settings['send_on_steps'])) {
            return array('sent' => false, 'reason' => 'steps_disabled');
        }

        $url = $this->get_effective_url($form_config);
        if ($url === '') {
            return array('sent' => false, 'reason' => 'not_connected');
        }

        $payload = $this->build_payload($form, $page_number, $form_data, $submission_uuid, $is_final);

        $start    = microtime(true);
        $result   = $this->send($url, $payload);
        $duration = round((microtime(true) - $start) * 1000);

        $this->log_delivery($form, $page_number, $url, $result, $duration, $submission_uuid);

        if (!empty($result['error'])) {
            return array('sent' => false, 'reason' => 'request_failed', 'error' => $result['error']);
        }

        return array(
            'sent'        => !empty($result['success']),
            'status_code' => $result['status_code'],
        );
    }

    /**
     * Record a delivery attempt in the webhook log table
     *
     * @param array  $form
     * @param int    $page_number
     * @param string $url
     * @param array  $result
     * @param int    $duration
     * @param string $submission_uuid
     * @return void
     */
    private function log_delivery($form, $page_number, $url, $result, $duration, $submission_uuid) {
        if (!class_exists('Form_Builder_Storage')) {
            return;
        }

        global $wpdb;

        $submission_id = null;
        if (!empty($submission_uuid)) {
            $submission_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}form_builder_submissions WHERE submission_uuid = %s",
                $submission_uuid
            ));
        }

        $storage = new Form_Builder_Storage();
        $storage->log_webhook(
            $submission_id,
            isset($form['id']) ? intval($form['id']) : 0,
            intval($page_number),
            // Store the masked URL so the zapikey is not written to the database
            $this->mask_url($url),
            isset($result['status_code']) ? $result['status_code'] : null,
            $duration,
            isset($result['error']) ? $result['error'] : null
        );
    }

    /**
     * Send a representative sample payload so an administrator can confirm the
     * connection and map fields inside Zoho Flow before building a form.
     *
     * @param string $url Optional URL to test; defaults to the stored connection
     * @return array|WP_Error
     */
    public function test_connection($url = '') {
        $url = trim((string) $url);

        if ($url === '') {
            $connection = $this->get_connection();
            $url        = $connection['url'];
        }

        if ($url === '') {
            return new WP_Error(
                'zoho_not_configured',
                __('Add your Zoho Flow webhook URL first, then test the connection.', 'form-builder-microsaas')
            );
        }

        $validation = $this->validate_url($url);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $payload = array(
            'source'          => 'konstruct',
            'form_id'         => 0,
            'form_name'       => __('Konstruct Test Connection', 'form-builder-microsaas'),
            'form_slug'       => 'konstruct-test-connection',
            'page_number'     => 1,
            'is_final'        => true,
            'submission_uuid' => 'test-' . wp_generate_password(8, false, false),
            'submitted_at'    => gmdate('c'),
            'first_name'      => 'Test',
            'last_name'       => 'Submission',
            'email'           => 'test@example.com',
            'phone'           => '+15551234567',
            'message'         => __('This is a test payload sent from Konstruct Form Builder.', 'form-builder-microsaas'),
        );

        $result = $this->send($url, $payload);

        if (!empty($result['error'])) {
            return new WP_Error(
                'zoho_request_failed',
                sprintf(
                    /* translators: %s: error message from the HTTP request */
                    __('Could not reach Zoho Flow: %s', 'form-builder-microsaas'),
                    $result['error']
                )
            );
        }

        if (empty($result['success'])) {
            return new WP_Error(
                'zoho_rejected',
                sprintf(
                    /* translators: %d: HTTP status code returned by Zoho Flow */
                    __('Zoho Flow rejected the test with status %d. Check that the flow is switched on and the webhook URL is current.', 'form-builder-microsaas'),
                    intval($result['status_code'])
                )
            );
        }

        return array(
            'success'     => true,
            'status_code' => $result['status_code'],
            'payload'     => $payload,
        );
    }
}
