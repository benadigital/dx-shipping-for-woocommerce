<?php
/**
 * Plugin Name: DX Shipping for WooCommerce
 * Plugin URI: https://francoshopfitters.co.uk
 * Description: Custom weight-based shipping method for WooCommerce with configurable base rates and per-kg charges. Default: £8.00 base + £0.40/kg over 20kg.
 * Version: 1.0.0
 * Author: Franco Shopfitters
 * Author URI: https://francoshopfitters.co.uk
 * Text Domain: dx-shipping-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DX_SHIPPING_VERSION', '1.0.0');
define('DX_SHIPPING_PLUGIN_FILE', __FILE__);
define('DX_SHIPPING_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DX_SHIPPING_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DX_SHIPPING_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class DX_Shipping_Plugin {

    /**
     * Instance of this class
     *
     * @var DX_Shipping_Plugin
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return DX_Shipping_Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Check dependencies
        add_action('plugins_loaded', array($this, 'init'));

        // Activation/Deactivation hooks
        register_activation_hook(DX_SHIPPING_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(DX_SHIPPING_PLUGIN_FILE, array($this, 'deactivate'));

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . DX_SHIPPING_PLUGIN_BASENAME, array($this, 'add_settings_link'));

        // Load text domain for translations
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load shipping method classes
        add_action('woocommerce_shipping_init', array($this, 'load_shipping_classes'));

        // Register shipping methods
        add_filter('woocommerce_shipping_methods', array($this, 'register_shipping_methods'));

        // Load admin functionality
        if (is_admin()) {
            require_once DX_SHIPPING_PLUGIN_DIR . 'includes/class-dx-shipping-admin.php';
            new DX_Shipping_Admin();
        }

        // Add product/category insurance meta fields
        $this->init_insurance_meta_fields();

        // Add checkout notices for excluded postcodes
        add_action('woocommerce_checkout_update_order_review', array($this, 'check_excluded_postcode_on_checkout'));

        // Add debug panel and AJAX handlers
        $this->init_debug_features();
    }

    /**
     * Load shipping method classes
     */
    public function load_shipping_classes() {
        require_once DX_SHIPPING_PLUGIN_DIR . 'includes/class-dx-shipping-method.php';
    }

    /**
     * Register shipping methods with WooCommerce
     *
     * @param array $methods
     * @return array
     */
    public function register_shipping_methods($methods) {
        $methods['dx_shipping'] = 'DX_Shipping_Method';
        return $methods;
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create default options
        $default_options = array(
            'dx_shipping_version' => DX_SHIPPING_VERSION,
            'dx_shipping_activated' => current_time('mysql'),
        );

        foreach ($default_options as $key => $value) {
            if (false === get_option($key)) {
                add_option($key, $value);
            }
        }

        // Clear shipping transients to ensure our method appears
        if (function_exists('WC')) {
            WC_Cache_Helper::get_transient_version('shipping', true);
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear shipping transients
        if (function_exists('WC')) {
            WC_Cache_Helper::get_transient_version('shipping', true);
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'dx-shipping-woocommerce',
            false,
            dirname(DX_SHIPPING_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links
     * @return array
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=wc-settings&tab=shipping&section=dx_shipping">' .
                        __('Settings', 'dx-shipping-woocommerce') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Display WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php _e('DX Shipping for WooCommerce', 'dx-shipping-woocommerce'); ?></strong>
                <?php _e('requires WooCommerce to be installed and activated.', 'dx-shipping-woocommerce'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Initialize insurance meta fields
     */
    private function init_insurance_meta_fields() {
        // Product meta box
        add_action('woocommerce_product_options_shipping', array($this, 'add_product_insurance_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_insurance_field'));

        // Category meta fields
        add_action('product_cat_add_form_fields', array($this, 'add_category_insurance_field'));
        add_action('product_cat_edit_form_fields', array($this, 'edit_category_insurance_field'), 10);
        add_action('created_product_cat', array($this, 'save_category_insurance_field'));
        add_action('edited_product_cat', array($this, 'save_category_insurance_field'));
    }

    /**
     * Add insurance field to product shipping options
     */
    public function add_product_insurance_field() {
        global $post;

        woocommerce_wp_text_input(
            array(
                'id' => '_dx_shipping_insurance',
                'label' => __('Shipping Insurance (£)', 'dx-shipping-woocommerce'),
                'desc_tip' => true,
                'description' => __('Additional insurance fee added to shipping cost (hidden from customer)', 'dx-shipping-woocommerce'),
                'type' => 'number',
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min' => '0',
                ),
                'value' => get_post_meta($post->ID, '_dx_shipping_insurance', true),
            )
        );
    }

    /**
     * Save product insurance field
     */
    public function save_product_insurance_field($post_id) {
        $insurance = isset($_POST['_dx_shipping_insurance']) ? sanitize_text_field($_POST['_dx_shipping_insurance']) : '';
        update_post_meta($post_id, '_dx_shipping_insurance', $insurance);
    }

    /**
     * Add category insurance field (new category)
     */
    public function add_category_insurance_field() {
        ?>
        <div class="form-field">
            <label for="dx_shipping_insurance"><?php _e('Shipping Insurance (£)', 'dx-shipping-woocommerce'); ?></label>
            <input type="number" name="dx_shipping_insurance" id="dx_shipping_insurance" step="0.01" min="0" />
            <p class="description"><?php _e('Additional insurance fee for products in this category (hidden from customer)', 'dx-shipping-woocommerce'); ?></p>
        </div>
        <?php
    }

    /**
     * Edit category insurance field (existing category)
     */
    public function edit_category_insurance_field($term) {
        $insurance = get_term_meta($term->term_id, 'dx_shipping_insurance', true);
        ?>
        <tr class="form-field">
            <th scope="row" valign="top">
                <label for="dx_shipping_insurance"><?php _e('Shipping Insurance (£)', 'dx-shipping-woocommerce'); ?></label>
            </th>
            <td>
                <input type="number" name="dx_shipping_insurance" id="dx_shipping_insurance" step="0.01" min="0" value="<?php echo esc_attr($insurance); ?>" />
                <p class="description"><?php _e('Additional insurance fee for products in this category (hidden from customer)', 'dx-shipping-woocommerce'); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save category insurance field
     */
    public function save_category_insurance_field($term_id) {
        if (isset($_POST['dx_shipping_insurance'])) {
            $insurance = sanitize_text_field($_POST['dx_shipping_insurance']);
            update_term_meta($term_id, 'dx_shipping_insurance', $insurance);
        }
    }

    /**
     * Check for excluded postcodes on checkout
     */
    public function check_excluded_postcode_on_checkout() {
        if (!function_exists('WC') || !WC()->customer) {
            return;
        }

        $country = WC()->customer->get_shipping_country();
        $postcode = WC()->customer->get_shipping_postcode();

        if (function_exists('dx_is_excluded_postcode') && dx_is_excluded_postcode($country, $postcode)) {
            wc_add_notice(
                __('We currently only deliver via DX to UK Mainland addresses. Please contact us for delivery options to your region.', 'dx-shipping-woocommerce'),
                'error'
            );
        }
    }

    /**
     * Initialize debug features
     */
    private function init_debug_features() {
        // Add debug panel to checkout
        add_action('woocommerce_after_checkout_form', array($this, 'add_checkout_debug_panel'));

        // AJAX handlers
        add_action('wp_ajax_dx_debug_info', array($this, 'ajax_debug_info'));
        add_action('wp_ajax_nopriv_dx_debug_info', array($this, 'ajax_debug_info'));

        // Debug panel JavaScript
        add_action('wp_footer', array($this, 'add_debug_javascript'));
    }

    /**
     * Add debug panel to checkout
     */
    public function add_checkout_debug_panel() {
        if (current_user_can('manage_options') || current_user_can('edit_pages')) {
            ?>
            <div id="dx-debug-panel" style="margin:15px 0; padding:10px; border:2px dashed red; background:#fff0f0; font-size:14px; line-height:1.4;">
                <strong>DX Shipping Debug (admins/editors only):</strong>
                <div id="dx-debug-content">Waiting for checkout refresh...</div>
            </div>
            <?php
        }
    }

    /**
     * AJAX debug info handler
     */
    public function ajax_debug_info() {
        if (!current_user_can('manage_options') && !current_user_can('edit_pages')) {
            wp_send_json_error('Not allowed');
        }

        if (WC()->customer) {
            $destination = array(
                'country' => WC()->customer->get_shipping_country(),
                'state' => WC()->customer->get_shipping_state(),
                'postcode' => WC()->customer->get_shipping_postcode(),
                'city' => WC()->customer->get_shipping_city(),
                'address_1' => WC()->customer->get_shipping_address(),
                'address_2' => WC()->customer->get_shipping_address_2(),
            );

            $is_excluded = function_exists('dx_is_excluded_postcode') ?
                          dx_is_excluded_postcode($destination['country'], $destination['postcode']) : false;

            $packages = WC()->shipping()->get_packages();
            $methods_debug = array();
            $insurance_debug = array();

            foreach ($packages as $i => $package) {
                $rates = isset($package['rates']) ? $package['rates'] : [];
                foreach ($rates as $rate_id => $rate) {
                    $methods_debug[] = "Package {$i} - {$rate_id}: " . $rate->label . " (£" . $rate->cost . ")";
                }

                // Calculate insurance for debug display
                if (!empty($package['contents'])) {
                    foreach ($package['contents'] as $item) {
                        $_product = $item['data'];
                        $product_insurance = get_post_meta($_product->get_id(), '_dx_shipping_insurance', true);
                        if ($product_insurance) {
                            $insurance_debug[] = $_product->get_name() . ": £" . $product_insurance . " × " . $item['quantity'];
                        }
                    }
                }
            }

            wp_send_json_success(array(
                'destination' => $destination,
                'excluded' => $is_excluded ? 'YES 🚫' : 'NO ✅',
                'methods' => $methods_debug,
                'insurance' => $insurance_debug,
            ));
        }

        wp_send_json_error('No customer info');
    }

    /**
     * Add debug JavaScript
     */
    public function add_debug_javascript() {
        if (!is_checkout()) {
            return;
        }
        ?>
        <script>
        (function($){
            function loadDxDebug(){
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', { action:'dx_debug_info' }, function(resp){
                    if(resp.success){
                        var html = '';
                        html += '<pre>'+JSON.stringify(resp.data.destination, null, 2)+'</pre>';
                        html += '<p><strong>DX Excluded:</strong> '+resp.data.excluded+'</p>';
                        if(resp.data.methods.length){
                            html += '<p><strong>Available Methods:</strong></p><ul>';
                            resp.data.methods.forEach(function(m){ html += '<li>'+m+'</li>'; });
                            html += '</ul>';
                        } else {
                            html += '<p><strong>Available Methods:</strong> None</p>';
                        }
                        if(resp.data.insurance.length){
                            html += '<p><strong>Insurance Applied:</strong></p><ul>';
                            resp.data.insurance.forEach(function(i){ html += '<li>'+i+'</li>'; });
                            html += '</ul>';
                        }
                        $('#dx-debug-content').html(html);
                    } else {
                        $('#dx-debug-content').html('<em>'+resp.data+'</em>');
                    }
                });
            }

            $(document.body).on('updated_checkout', loadDxDebug);
            $(document).on('change keyup', '#shipping_postcode,#billing_postcode,#shipping_country,#billing_country', loadDxDebug);
            $(function(){ setTimeout(loadDxDebug, 800); });
        })(jQuery);
        </script>
        <?php
    }
}

// Initialize plugin
DX_Shipping_Plugin::get_instance();