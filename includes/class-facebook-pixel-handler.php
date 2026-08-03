<?php
/**
 * Facebook Pixel Handler Class
 *
 * Installs the Meta Pixel base code in the page head and fires a chosen
 * standard event when a form is submitted, so no custom JavaScript is needed.
 *
 * The base code must be present when the page loads: Meta's Pixel Helper only
 * detects a pixel it can see at load time, and fbevents.js has to finish
 * loading before any event will send. Initialising from custom JavaScript at
 * submit time fails on both counts.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Form_Builder_Facebook_Pixel_Handler {

    /**
     * Standard Meta events offered in the builder.
     * Restricted to the standard set: a custom event name would not appear in
     * Ads Manager reporting without extra setup.
     *
     * @return array Event name => human readable description
     */
    public static function get_events() {
        return array(
            'Lead'                 => __('Lead - someone submitted an enquiry', 'form-builder-microsaas'),
            'CompleteRegistration' => __('CompleteRegistration - someone signed up', 'form-builder-microsaas'),
            'Contact'              => __('Contact - someone got in touch', 'form-builder-microsaas'),
            'Schedule'             => __('Schedule - someone booked an appointment', 'form-builder-microsaas'),
            'SubmitApplication'    => __('SubmitApplication - someone applied', 'form-builder-microsaas'),
            'Subscribe'            => __('Subscribe - someone subscribed', 'form-builder-microsaas'),
            'InitiateCheckout'     => __('InitiateCheckout - someone started checking out', 'form-builder-microsaas'),
            'AddToCart'            => __('AddToCart - someone added an item', 'form-builder-microsaas'),
            'ViewContent'          => __('ViewContent - someone viewed a key page', 'form-builder-microsaas'),
            'Purchase'             => __('Purchase - someone bought something', 'form-builder-microsaas'),
        );
    }

    /**
     * Longest name Meta accepts for a custom event
     */
    const MAX_EVENT_NAME_LENGTH = 40;

    /**
     * Read the pixel settings from a form configuration
     *
     * @param array $form_config
     * @return array{enabled:bool,pixel_id:string,event:string,track_steps:bool}
     */
    public function get_form_settings($form_config) {
        $defaults = array(
            'enabled'     => false,
            'pixel_id'    => '',
            'event'       => 'Lead',
            'track_steps' => false,
            // 0 means "when the form is submitted"; a positive number means
            // "after that page is completed"
            'fire_on_page' => 0,
        );

        if (!is_array($form_config) || empty($form_config['facebook_pixel']) || !is_array($form_config['facebook_pixel'])) {
            return $defaults;
        }

        $settings = array_merge($defaults, $form_config['facebook_pixel']);

        $page_count = (is_array($form_config) && !empty($form_config['pages']))
            ? count($form_config['pages'])
            : 0;

        return array(
            'enabled'      => !empty($settings['enabled']),
            'pixel_id'     => $this->clean_pixel_id($settings['pixel_id']),
            'event'        => $this->normalise_event($settings['event']),
            'track_steps'  => !empty($settings['track_steps']),
            'fire_on_page' => $this->normalise_fire_on_page($settings['fire_on_page'], $page_count),
        );
    }

    /**
     * Decide which page completion fires the conversion.
     *
     * Falls back to firing on submit when the stored page no longer exists,
     * which happens when pages are deleted after the setting was saved. The
     * alternative would be a conversion that silently never fires.
     *
     * @param mixed $value
     * @param int   $page_count Pages the form currently has
     * @return int 0 for submit, otherwise the page number
     */
    public function normalise_fire_on_page($value, $page_count) {
        $page = intval($value);

        if ($page < 1) {
            return 0;
        }

        // The last page has no Next button, so "after the last page" is the
        // same thing as firing on submit
        if ($page_count > 0 && $page >= $page_count) {
            return 0;
        }

        return $page;
    }

    /**
     * Build the prefix for this form's per-step custom events.
     *
     * Names are generated rather than typed so they stay predictable: an
     * audience built on "Konstruct_ContactUs_Step2" keeps working when the
     * form is edited. The step number is appended by the frontend.
     *
     * @param array $form Form row, or anything with a form_slug
     * @return string e.g. "Konstruct_ContactUs_Step"
     */
    public function get_step_event_prefix($form) {
        $slug = '';
        if (is_array($form) && isset($form['form_slug'])) {
            $slug = (string) $form['form_slug'];
        } elseif (is_string($form)) {
            $slug = $form;
        }

        // "contact-us" becomes "ContactUs": Meta allows a limited character
        // set, and camel case keeps the name readable in the audience builder
        $words = preg_replace('/[^A-Za-z0-9]+/', ' ', $slug);
        $words = trim($words);
        $name  = $words === '' ? 'Form' : str_replace(' ', '', ucwords(strtolower($words)));

        $prefix = 'Konstruct_' . $name . '_Step';

        // Leave room for a two digit step number inside Meta's limit
        $limit = self::MAX_EVENT_NAME_LENGTH - 2;
        if (strlen($prefix) > $limit) {
            $prefix = substr($prefix, 0, $limit);
        }

        return $prefix;
    }

    /**
     * The full custom event name for a given step
     *
     * @param array $form
     * @param int   $step
     * @return string
     */
    public function get_step_event_name($form, $step) {
        return $this->get_step_event_prefix($form) . intval($step);
    }

    /**
     * Strip a pasted pixel ID down to digits.
     * Copying from Events Manager often brings spaces or stray characters.
     *
     * @param string $pixel_id
     * @return string
     */
    public function clean_pixel_id($pixel_id) {
        return preg_replace('/\D/', '', (string) $pixel_id);
    }

    /**
     * Coerce a stored event to one Meta recognises
     *
     * @param string $event
     * @return string
     */
    public function normalise_event($event) {
        $event  = (string) $event;
        $events = self::get_events();

        return isset($events[$event]) ? $event : 'Lead';
    }

    /**
     * Validate a pixel ID
     *
     * @param string $pixel_id
     * @return true|WP_Error
     */
    public function validate_pixel_id($pixel_id) {
        $clean = $this->clean_pixel_id($pixel_id);

        if ($clean === '') {
            return new WP_Error(
                'pixel_missing',
                __('Enter your Pixel ID. You will find it in Meta Events Manager under Data Sources.', 'form-builder-microsaas')
            );
        }

        // Meta pixel IDs are currently 15-16 digits. The range is kept loose so
        // a future length change does not lock anyone out.
        $length = strlen($clean);
        if ($length < 10 || $length > 20) {
            return new WP_Error(
                'pixel_bad_length',
                __('That does not look like a Pixel ID. It is a number around 15 digits long, not the full snippet.', 'form-builder-microsaas')
            );
        }

        return true;
    }

    /**
     * Collect the pixels that should be initialised for the current page.
     *
     * Runs on wp_head, before the shortcode has rendered, so the forms on the
     * page are found by reading the post content directly.
     *
     * @param string $content Post content
     * @return array List of array{pixel_id:string,event:string}
     */
    public function collect_pixels_from_content($content) {
        if (!is_string($content) || strpos($content, '[form_builder') === false) {
            return array();
        }

        // Pull the id attribute out of every form_builder shortcode
        preg_match_all(
            '/\[form_builder[^\]]*\bid=["\']?([^"\'\]\s]+)/i',
            $content,
            $matches
        );

        if (empty($matches[1])) {
            return array();
        }

        $storage = new Form_Builder_Storage();
        $pixels  = array();
        $seen    = array();

        foreach (array_unique($matches[1]) as $identifier) {
            $form = is_numeric($identifier)
                ? $storage->get_form_by_id($identifier)
                : $storage->get_form_by_slug($identifier);

            if (!$form) {
                continue;
            }

            $settings = $this->get_form_settings($form['form_config']);

            if (empty($settings['enabled']) || $settings['pixel_id'] === '') {
                continue;
            }

            // The same pixel used by two forms on one page is initialised once
            if (isset($seen[$settings['pixel_id']])) {
                continue;
            }
            $seen[$settings['pixel_id']] = true;

            $pixels[] = array(
                'pixel_id' => $settings['pixel_id'],
                'event'    => $settings['event'],
            );
        }

        return $pixels;
    }

    /**
     * Print the Meta Pixel base code.
     *
     * Only the base code and PageView are emitted here. The form event fires
     * later, from the frontend script, once the visitor actually submits.
     *
     * @param array $pixels
     * @return void
     */
    public function render_base_code($pixels) {
        if (empty($pixels)) {
            return;
        }

        $ids = array();
        foreach ($pixels as $pixel) {
            $ids[] = $pixel['pixel_id'];
        }

        ?>
<!-- Meta Pixel Code - Konstruct Form Builder -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
<?php foreach ($ids as $id): ?>
fbq('init', '<?php echo esc_js($id); ?>');
<?php endforeach; ?>
fbq('track', 'PageView');
</script>
<noscript><?php foreach ($ids as $id): ?><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id=<?php echo esc_attr($id); ?>&ev=PageView&noscript=1" /><?php endforeach; ?></noscript>
<!-- End Meta Pixel Code -->
        <?php
    }
}
