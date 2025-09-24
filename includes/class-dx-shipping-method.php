<?php
/**
 * DX Shipping Method Class
 *
 * Custom weight-based shipping method for WooCommerce
 *
 * @package DX_Shipping_WooCommerce
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function to detect excluded postcodes
 *
 * @param string $country
 * @param string $postcode
 * @return bool
 */
function dx_is_excluded_postcode($country, $postcode) {
    if (empty($country)) {
        return false;
    }

    // Allow GB & UK; anything else excluded
    if (!in_array(strtoupper($country), array('GB', 'UK'), true)) {
        return true;
    }

    if (empty($postcode)) {
        return false;
    }

    $pc = strtoupper(trim($postcode));

    // Excluded postcode patterns
    $patterns = array(
        '/^BT/',    // Northern Ireland
        '/^GY/',    // Guernsey
        '/^JE/',    // Jersey
        '/^IM/',    // Isle of Man
        '/^HS/',    // Outer Hebrides
        '/^ZE/',    // Shetland
        '/^KW/',    // Orkney
        '/^IV/',    // Highlands
        '/^PH/',    // Highlands
        '/^PA/',    // Argyll & islands
        '/^FK18/',  // Trossachs highlands
        '/^FK19/',  // Trossachs highlands
    );

    foreach ($patterns as $p) {
        if (preg_match($p, $pc)) {
            return true;
        }
    }

    return false;
}

/**
 * DX Shipping Method
 */
class DX_Shipping_Method extends WC_Shipping_Method {

    /**
     * Constructor
     *
     * @param int $instance_id
     */
    public function __construct($instance_id = 0) {
        $this->id                 = 'dx_shipping';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('DX Shipping', 'dx-shipping-woocommerce');
        $this->method_description = __('Weight-based shipping with configurable base rate and per-kg charges for excess weight.', 'dx-shipping-woocommerce');
        $this->supports           = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );

        // Initialize settings
        $this->init();
    }

    /**
     * Initialize shipping method
     */
    public function init() {
        // Load the settings API
        $this->init_form_fields();
        $this->init_instance_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->enabled          = $this->get_option('enabled');
        $this->title            = $this->get_option('title');
        $this->base_cost        = $this->get_option('base_cost');
        $this->weight_threshold = $this->get_option('weight_threshold');
        $this->excess_rate      = $this->get_option('excess_rate');
        $this->min_cost         = $this->get_option('min_cost');
        $this->max_cost         = $this->get_option('max_cost');
        $this->free_shipping_amount = $this->get_option('free_shipping_amount');
        $this->tax_status       = $this->get_option('tax_status');
        $this->debug_mode       = $this->get_option('debug_mode');

        // Save settings in admin
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Initialize global form fields (legacy support)
     */
    public function init_form_fields() {
        $this->form_fields = $this->get_settings_fields();
    }

    /**
     * Initialize instance form fields for shipping zones
     */
    public function init_instance_form_fields() {
        $this->instance_form_fields = $this->get_settings_fields();
    }

    /**
     * Get settings fields
     *
     * @return array
     */
    private function get_settings_fields() {
        $currency_symbol = get_woocommerce_currency_symbol();
        $weight_unit = get_option('woocommerce_weight_unit');

        return array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'dx-shipping-woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Enable DX Shipping', 'dx-shipping-woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => __('Method Title', 'dx-shipping-woocommerce'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'dx-shipping-woocommerce'),
                'default'     => __('Standard Shipping', 'dx-shipping-woocommerce'),
                'desc_tip'    => true,
            ),
            'tax_status' => array(
                'title'       => __('Tax Status', 'dx-shipping-woocommerce'),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'default'     => 'taxable',
                'options'     => array(
                    'taxable' => __('Taxable', 'dx-shipping-woocommerce'),
                    'none'    => __('None', 'dx-shipping-woocommerce'),
                ),
            ),
            'base_cost' => array(
                'title'       => sprintf(__('Base Cost (%s)', 'dx-shipping-woocommerce'), $currency_symbol),
                'type'        => 'decimal',
                'description' => __('Base shipping cost for orders up to the weight threshold.', 'dx-shipping-woocommerce'),
                'default'     => '8.00',
                'desc_tip'    => true,
                'placeholder' => '0.00',
            ),
            'weight_threshold' => array(
                'title'       => sprintf(__('Weight Threshold (%s)', 'dx-shipping-woocommerce'), $weight_unit),
                'type'        => 'decimal',
                'description' => sprintf(__('Weight threshold in %s. Orders above this weight incur additional charges.', 'dx-shipping-woocommerce'), $weight_unit),
                'default'     => '20',
                'desc_tip'    => true,
                'placeholder' => '0',
            ),
            'excess_rate' => array(
                'title'       => sprintf(__('Excess Weight Rate (%s/%s)', 'dx-shipping-woocommerce'), $currency_symbol, $weight_unit),
                'type'        => 'decimal',
                'description' => sprintf(__('Cost per %s for weight exceeding the threshold.', 'dx-shipping-woocommerce'), $weight_unit),
                'default'     => '0.40',
                'desc_tip'    => true,
                'placeholder' => '0.00',
            ),
            'min_cost' => array(
                'title'       => sprintf(__('Minimum Cost (%s)', 'dx-shipping-woocommerce'), $currency_symbol),
                'type'        => 'decimal',
                'description' => __('Minimum shipping cost regardless of weight. Leave blank for no minimum.', 'dx-shipping-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => __('No minimum', 'dx-shipping-woocommerce'),
            ),
            'max_cost' => array(
                'title'       => sprintf(__('Maximum Cost (%s)', 'dx-shipping-woocommerce'), $currency_symbol),
                'type'        => 'decimal',
                'description' => __('Maximum shipping cost regardless of weight. Leave blank for no maximum.', 'dx-shipping-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => __('No maximum', 'dx-shipping-woocommerce'),
            ),
            'free_shipping_amount' => array(
                'title'       => sprintf(__('Free Shipping Amount (%s)', 'dx-shipping-woocommerce'), $currency_symbol),
                'type'        => 'decimal',
                'description' => __('Offer free shipping for orders above this amount. Leave blank to disable.', 'dx-shipping-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
                'placeholder' => __('Disabled', 'dx-shipping-woocommerce'),
            ),
            'debug_mode' => array(
                'title'       => __('Debug Mode', 'dx-shipping-woocommerce'),
                'type'        => 'checkbox',
                'label'       => __('Enable debug logging', 'dx-shipping-woocommerce'),
                'description' => __('Log shipping calculations to WooCommerce > Status > Logs', 'dx-shipping-woocommerce'),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Calculate shipping cost
     *
     * @param array $package
     */
    public function calculate_shipping($package = array()) {
        // Check if method is enabled
        if ('yes' !== $this->enabled) {
            return;
        }

        // Check for free shipping based on order total
        if ($this->free_shipping_amount && $package['contents_cost'] >= $this->free_shipping_amount) {
            $this->add_rate(array(
                'id'       => $this->get_rate_id(),
                'label'    => $this->title,
                'cost'     => 0,
                'calc_tax' => 'per_order',
                'meta_data' => array(
                    'free_shipping_applied' => true,
                ),
            ));

            if ('yes' === $this->debug_mode) {
                $this->log_debug('Free shipping applied. Order total: ' . $package['contents_cost']);
            }

            return;
        }

        // Calculate total weight
        $total_weight = $this->calculate_package_weight($package);

        // Calculate shipping cost (including hidden insurance)
        $cost = $this->calculate_shipping_cost($total_weight, $package);

        // Apply min/max constraints
        if ($this->min_cost && $cost < $this->min_cost) {
            $cost = $this->min_cost;
        }
        if ($this->max_cost && $cost > $this->max_cost) {
            $cost = $this->max_cost;
        }

        // Debug logging if enabled
        if ('yes' === $this->debug_mode) {
            $this->log_debug(sprintf(
                'Weight: %s%s, Base: %s, Threshold: %s%s, Excess rate: %s/%s, Final cost: %s',
                $total_weight,
                get_option('woocommerce_weight_unit'),
                wc_price($this->base_cost),
                $this->weight_threshold,
                get_option('woocommerce_weight_unit'),
                wc_price($this->excess_rate),
                get_option('woocommerce_weight_unit'),
                wc_price($cost)
            ));
        }

        // Create shipping rate
        $rate = array(
            'id'       => $this->get_rate_id(),
            'label'    => $this->title,
            'cost'     => $cost,
            'calc_tax' => 'per_order',
            'meta_data' => array(
                'total_weight' => $total_weight,
                'base_cost' => $this->base_cost,
                'excess_weight' => max(0, $total_weight - $this->weight_threshold),
                'excess_charge' => max(0, ($total_weight - $this->weight_threshold) * $this->excess_rate),
            ),
        );

        // Handle tax
        if ('none' === $this->tax_status) {
            $rate['taxes'] = false;
        }

        // Add the rate
        $this->add_rate($rate);
    }

    /**
     * Calculate total package weight
     *
     * @param array $package
     * @return float
     */
    private function calculate_package_weight($package) {
        $weight = 0;

        if (empty($package['contents'])) {
            return $weight;
        }

        foreach ($package['contents'] as $item_id => $values) {
            $_product = $values['data'];

            if ($_product && $_product->has_weight()) {
                $product_weight = (float) $_product->get_weight();
                $quantity = (int) $values['quantity'];
                $weight += $product_weight * $quantity;
            }
        }

        // Convert to kg if needed (for consistency)
        $weight = wc_get_weight($weight, 'kg');

        return round($weight, 2);
    }

    /**
     * Calculate shipping cost based on weight
     *
     * @param float $weight
     * @param array $package
     * @return float
     */
    private function calculate_shipping_cost($weight, $package = array()) {
        $base_cost = (float) $this->base_cost;
        $weight_threshold = (float) $this->weight_threshold;
        $excess_rate = (float) $this->excess_rate;

        $cost = $base_cost;

        // Add excess weight charges
        if ($weight > $weight_threshold && $excess_rate > 0) {
            $excess_weight = $weight - $weight_threshold;
            $cost += $excess_weight * $excess_rate;
        }

        // Add insurance fees (hidden from customer)
        $insurance = $this->calculate_insurance_fee($package);
        $cost += $insurance;

        // Ensure minimum cost
        $cost = max($cost, 0);

        return round($cost, 2);
    }

    /**
     * Calculate insurance fee for products
     *
     * @param array $package
     * @return float
     */
    private function calculate_insurance_fee($package) {
        $insurance_total = 0;

        if (empty($package['contents'])) {
            return $insurance_total;
        }

        foreach ($package['contents'] as $item_id => $values) {
            $_product = $values['data'];
            $quantity = (int) $values['quantity'];

            // Check for product-level insurance
            $product_insurance = get_post_meta($_product->get_id(), '_dx_shipping_insurance', true);

            if ($product_insurance && is_numeric($product_insurance)) {
                $insurance_total += (float) $product_insurance * $quantity;
                continue;
            }

            // Check for category-level insurance
            $categories = $_product->get_category_ids();
            $category_insurance = 0;

            foreach ($categories as $cat_id) {
                $cat_insurance = get_term_meta($cat_id, 'dx_shipping_insurance', true);
                if ($cat_insurance && is_numeric($cat_insurance)) {
                    // Use the highest category insurance if product belongs to multiple categories
                    $category_insurance = max($category_insurance, (float) $cat_insurance);
                }
            }

            if ($category_insurance > 0) {
                $insurance_total += $category_insurance * $quantity;
            }
        }

        return $insurance_total;
    }

    /**
     * Check if shipping method is available
     *
     * @param array $package
     * @return bool
     */
    public function is_available($package) {
        // Basic availability check
        if ('yes' !== $this->enabled) {
            return false;
        }

        // Check if package has items
        if (empty($package['contents'])) {
            return false;
        }

        // Check for UK mainland exclusions
        $country = isset($package['destination']['country']) ? $package['destination']['country'] : '';
        $postcode = isset($package['destination']['postcode']) ? $package['destination']['postcode'] : '';

        $excluded = dx_is_excluded_postcode($country, $postcode);

        if ($excluded) {
            // Add custom message filters
            add_filter('woocommerce_no_shipping_available_html', array($this, 'excluded_area_message'));
            add_filter('woocommerce_cart_no_shipping_available_html', array($this, 'excluded_area_message'));
            return false;
        }

        // Only allow GB/UK countries
        if (!in_array(strtoupper($country), array('GB', 'UK'), true)) {
            return false;
        }

        // Allow filtering of availability
        return apply_filters('dx_shipping_is_available', true, $package, $this);
    }

    /**
     * Message for excluded areas
     *
     * @param string $message
     * @return string
     */
    public function excluded_area_message($message) {
        return __('We currently only deliver via DX to UK Mainland addresses. Please contact us for delivery options to your region.', 'dx-shipping-woocommerce');
    }

    /**
     * Log debug information
     *
     * @param string $message
     */
    private function log_debug($message) {
        $logger = wc_get_logger();
        $context = array('source' => 'dx-shipping');

        $logger->info($message, $context);
    }

    /**
     * Validate decimal field
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    public function validate_decimal_field($key, $value) {
        $value = is_null($value) ? '' : $value;
        $value = wp_unslash(trim($value));

        if ($value && !is_numeric($value)) {
            $this->add_error(sprintf(__('%s must be a number.', 'dx-shipping-woocommerce'), $this->form_fields[$key]['title']));
            $value = '';
        }

        return $value;
    }

    /**
     * Process admin options
     *
     * @return bool
     */
    public function process_admin_options() {
        // Process and save options
        $result = parent::process_admin_options();

        // Refresh settings after save
        $this->init_settings();
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->base_cost = $this->get_option('base_cost');
        $this->weight_threshold = $this->get_option('weight_threshold');
        $this->excess_rate = $this->get_option('excess_rate');
        $this->min_cost = $this->get_option('min_cost');
        $this->max_cost = $this->get_option('max_cost');
        $this->free_shipping_amount = $this->get_option('free_shipping_amount');
        $this->tax_status = $this->get_option('tax_status');
        $this->debug_mode = $this->get_option('debug_mode');

        // Clear shipping transients
        WC_Cache_Helper::get_transient_version('shipping', true);

        return $result;
    }
}