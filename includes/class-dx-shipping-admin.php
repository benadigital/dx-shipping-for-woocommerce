<?php
/**
 * DX Shipping Admin Class
 *
 * Handles admin functionality for the DX Shipping plugin
 *
 * @package DX_Shipping_WooCommerce
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class
 */
class DX_Shipping_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu items
        add_action('admin_menu', array($this, 'add_admin_menu'), 99);

        // Add admin notices
        add_action('admin_notices', array($this, 'admin_notices'));

        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Add plugin information to WooCommerce system status
        add_action('woocommerce_system_status_report', array($this, 'add_system_status_info'));

        // AJAX handlers for admin operations
        add_action('wp_ajax_dx_shipping_test_calculation', array($this, 'ajax_test_calculation'));
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Add submenu under WooCommerce
        add_submenu_page(
            'woocommerce',
            __('DX Shipping Settings', 'dx-shipping-woocommerce'),
            __('DX Shipping', 'dx-shipping-woocommerce'),
            'manage_woocommerce',
            'dx-shipping-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Settings page content
     */
    public function settings_page() {
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Get shipping zones using our method
        $zones = WC_Shipping_Zones::get_zones();
        $dx_shipping_instances = array();

        foreach ($zones as $zone) {
            foreach ($zone['shipping_methods'] as $method) {
                if ('dx_shipping' === $method->id) {
                    $dx_shipping_instances[] = array(
                        'zone_id' => $zone['id'],
                        'zone_name' => $zone['zone_name'],
                        'instance_id' => $method->instance_id,
                        'enabled' => $method->enabled,
                        'title' => $method->title,
                    );
                }
            }
        }

        // Check "Rest of the World" zone
        $zone_0 = WC_Shipping_Zones::get_zone(0);
        foreach ($zone_0->get_shipping_methods() as $method) {
            if ('dx_shipping' === $method->id) {
                $dx_shipping_instances[] = array(
                    'zone_id' => 0,
                    'zone_name' => __('Rest of the World', 'dx-shipping-woocommerce'),
                    'instance_id' => $method->instance_id,
                    'enabled' => $method->enabled,
                    'title' => $method->title,
                );
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="dx-shipping-admin-wrapper">
                <!-- Overview Section -->
                <div class="card">
                    <h2><?php _e('Overview', 'dx-shipping-woocommerce'); ?></h2>
                    <p><?php _e('DX Shipping provides weight-based shipping calculations for WooCommerce.', 'dx-shipping-woocommerce'); ?></p>
                    <p><?php _e('Configure your shipping zones and rates below.', 'dx-shipping-woocommerce'); ?></p>
                </div>

                <!-- Active Instances -->
                <div class="card">
                    <h2><?php _e('Active Shipping Zones', 'dx-shipping-woocommerce'); ?></h2>
                    <?php if (empty($dx_shipping_instances)): ?>
                        <p><?php _e('No DX Shipping methods have been configured yet.', 'dx-shipping-woocommerce'); ?></p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping'); ?>" class="button button-primary">
                                <?php _e('Configure Shipping Zones', 'dx-shipping-woocommerce'); ?>
                            </a>
                        </p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Zone', 'dx-shipping-woocommerce'); ?></th>
                                    <th><?php _e('Method Title', 'dx-shipping-woocommerce'); ?></th>
                                    <th><?php _e('Status', 'dx-shipping-woocommerce'); ?></th>
                                    <th><?php _e('Actions', 'dx-shipping-woocommerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dx_shipping_instances as $instance): ?>
                                    <tr>
                                        <td><?php echo esc_html($instance['zone_name']); ?></td>
                                        <td><?php echo esc_html($instance['title']); ?></td>
                                        <td>
                                            <?php if ('yes' === $instance['enabled']): ?>
                                                <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                                                <?php _e('Enabled', 'dx-shipping-woocommerce'); ?>
                                            <?php else: ?>
                                                <span class="dashicons dashicons-dismiss" style="color: red;"></span>
                                                <?php _e('Disabled', 'dx-shipping-woocommerce'); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping&zone_id=' . $instance['zone_id']); ?>" class="button">
                                                <?php _e('Edit Zone', 'dx-shipping-woocommerce'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Postcode Tester -->
                <div class="card">
                    <h2><?php _e('Postcode Exclusion Tester', 'dx-shipping-woocommerce'); ?></h2>
                    <p><?php _e('Check if a postcode is excluded from DX delivery:', 'dx-shipping-woocommerce'); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="test_postcode_country"><?php _e('Country', 'dx-shipping-woocommerce'); ?></label>
                            </th>
                            <td>
                                <select id="test_postcode_country">
                                    <option value="GB">United Kingdom (GB)</option>
                                    <option value="UK">United Kingdom (UK)</option>
                                    <option value="IE">Ireland</option>
                                    <option value="FR">France</option>
                                    <option value="US">United States</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="test_postcode"><?php _e('Postcode', 'dx-shipping-woocommerce'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="test_postcode" placeholder="e.g. BT1 1AA" />
                            </td>
                        </tr>
                    </table>

                    <p>
                        <button type="button" id="test-postcode" class="button button-primary">
                            <?php _e('Test Postcode', 'dx-shipping-woocommerce'); ?>
                        </button>
                    </p>

                    <div id="postcode-result" style="display: none;">
                        <h3><?php _e('Result:', 'dx-shipping-woocommerce'); ?></h3>
                        <p id="postcode-result-text"></p>
                    </div>
                </div>

                <!-- Calculator Tool -->
                <div class="card">
                    <h2><?php _e('Shipping Calculator', 'dx-shipping-woocommerce'); ?></h2>
                    <p><?php _e('Test your shipping calculations:', 'dx-shipping-woocommerce'); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="test_weight"><?php _e('Weight', 'dx-shipping-woocommerce'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="test_weight" step="0.01" min="0" value="25" />
                                <span><?php echo get_option('woocommerce_weight_unit'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="test_base_cost"><?php _e('Base Cost', 'dx-shipping-woocommerce'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="test_base_cost" step="0.01" min="0" value="8.00" />
                                <span><?php echo get_woocommerce_currency_symbol(); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="test_threshold"><?php _e('Weight Threshold', 'dx-shipping-woocommerce'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="test_threshold" step="0.01" min="0" value="20" />
                                <span><?php echo get_option('woocommerce_weight_unit'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="test_excess_rate"><?php _e('Excess Rate', 'dx-shipping-woocommerce'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="test_excess_rate" step="0.01" min="0" value="0.40" />
                                <span><?php echo get_woocommerce_currency_symbol(); ?>/<?php echo get_option('woocommerce_weight_unit'); ?></span>
                            </td>
                        </tr>
                    </table>

                    <p>
                        <button type="button" id="calculate-shipping" class="button button-primary">
                            <?php _e('Calculate', 'dx-shipping-woocommerce'); ?>
                        </button>
                    </p>

                    <div id="calculation-result" style="display: none;">
                        <h3><?php _e('Result:', 'dx-shipping-woocommerce'); ?></h3>
                        <p id="result-text"></p>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="card">
                    <h2><?php _e('Quick Links', 'dx-shipping-woocommerce'); ?></h2>
                    <ul>
                        <li>
                            <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping'); ?>">
                                <?php _e('WooCommerce Shipping Settings', 'dx-shipping-woocommerce'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo admin_url('admin.php?page=wc-status&tab=logs'); ?>">
                                <?php _e('View Debug Logs', 'dx-shipping-woocommerce'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=general'); ?>">
                                <?php _e('Currency & Weight Units', 'dx-shipping-woocommerce'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <style>
            .dx-shipping-admin-wrapper .card {
                max-width: 800px;
                margin-top: 20px;
                padding: 20px;
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .dx-shipping-admin-wrapper .card h2 {
                margin-top: 0;
            }
            #calculation-result {
                margin-top: 20px;
                padding: 15px;
                background: #f0f8ff;
                border-left: 4px solid #0073aa;
            }
        </style>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Postcode tester
                $('#test-postcode').on('click', function() {
                    var country = $('#test_postcode_country').val();
                    var postcode = $('#test_postcode').val();

                    if (!postcode) {
                        alert('<?php _e('Please enter a postcode', 'dx-shipping-woocommerce'); ?>');
                        return;
                    }

                    // Test exclusion
                    var excluded = false;
                    var reason = '';

                    // Check country
                    if (country !== 'GB' && country !== 'UK') {
                        excluded = true;
                        reason = '<?php _e('Country not supported (only GB/UK)', 'dx-shipping-woocommerce'); ?>';
                    } else {
                        // Check postcode patterns
                        var pc = postcode.toUpperCase().trim();
                        var patterns = [
                            {pattern: /^BT/, name: 'Northern Ireland'},
                            {pattern: /^GY/, name: 'Guernsey'},
                            {pattern: /^JE/, name: 'Jersey'},
                            {pattern: /^IM/, name: 'Isle of Man'},
                            {pattern: /^HS/, name: 'Outer Hebrides'},
                            {pattern: /^ZE/, name: 'Shetland'},
                            {pattern: /^KW/, name: 'Orkney'},
                            {pattern: /^IV/, name: 'Highlands'},
                            {pattern: /^PH/, name: 'Highlands'},
                            {pattern: /^PA/, name: 'Argyll & Islands'},
                            {pattern: /^FK18/, name: 'Trossachs Highlands'},
                            {pattern: /^FK19/, name: 'Trossachs Highlands'}
                        ];

                        for (var i = 0; i < patterns.length; i++) {
                            if (patterns[i].pattern.test(pc)) {
                                excluded = true;
                                reason = '<?php _e('Excluded area:', 'dx-shipping-woocommerce'); ?> ' + patterns[i].name;
                                break;
                            }
                        }
                    }

                    var resultText;
                    if (excluded) {
                        resultText = '<span style="color: red;">❌ <?php _e('EXCLUDED', 'dx-shipping-woocommerce'); ?></span><br>' + reason;
                    } else {
                        resultText = '<span style="color: green;">✅ <?php _e('AVAILABLE for DX delivery', 'dx-shipping-woocommerce'); ?></span>';
                    }

                    $('#postcode-result-text').html(resultText);
                    $('#postcode-result').show();
                });

                // Shipping calculator
                $('#calculate-shipping').on('click', function() {
                    var weight = parseFloat($('#test_weight').val()) || 0;
                    var baseCost = parseFloat($('#test_base_cost').val()) || 0;
                    var threshold = parseFloat($('#test_threshold').val()) || 0;
                    var excessRate = parseFloat($('#test_excess_rate').val()) || 0;

                    var cost = baseCost;
                    var excessWeight = 0;

                    if (weight > threshold) {
                        excessWeight = weight - threshold;
                        cost += excessWeight * excessRate;
                    }

                    var resultText = '<?php _e('Shipping cost:', 'dx-shipping-woocommerce'); ?> ' +
                                   '<?php echo get_woocommerce_currency_symbol(); ?>' + cost.toFixed(2);

                    if (excessWeight > 0) {
                        resultText += '<br><?php _e('Excess weight:', 'dx-shipping-woocommerce'); ?> ' +
                                    excessWeight.toFixed(2) + ' <?php echo get_option('woocommerce_weight_unit'); ?>';
                        resultText += '<br><?php _e('Excess charge:', 'dx-shipping-woocommerce'); ?> ' +
                                    '<?php echo get_woocommerce_currency_symbol(); ?>' +
                                    (excessWeight * excessRate).toFixed(2);
                    }

                    $('#result-text').html(resultText);
                    $('#calculation-result').show();
                });
            });
        </script>
        <?php
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Check if we're on a relevant page
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('woocommerce_page_dx-shipping-settings', 'woocommerce_page_wc-settings'))) {
            return;
        }

        // Check if WooCommerce shipping is enabled
        if ('yes' !== get_option('woocommerce_calc_shipping')) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('Shipping is currently disabled in WooCommerce.', 'dx-shipping-woocommerce'); ?>
                    <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=general'); ?>">
                        <?php _e('Enable shipping', 'dx-shipping-woocommerce'); ?>
                    </a>
                </p>
            </div>
            <?php
        }

        // Check weight unit
        $weight_unit = get_option('woocommerce_weight_unit');
        if ('kg' !== $weight_unit) {
            ?>
            <div class="notice notice-info">
                <p>
                    <?php
                    printf(
                        __('Your store is using %s as the weight unit. DX Shipping will calculate based on this unit.', 'dx-shipping-woocommerce'),
                        '<strong>' . $weight_unit . '</strong>'
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if ('woocommerce_page_dx-shipping-settings' !== $hook) {
            return;
        }

        // You can add custom admin CSS/JS here if needed
        // wp_enqueue_style('dx-shipping-admin', DX_SHIPPING_PLUGIN_URL . 'assets/css/admin.css', array(), DX_SHIPPING_VERSION);
        // wp_enqueue_script('dx-shipping-admin', DX_SHIPPING_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), DX_SHIPPING_VERSION, true);
    }

    /**
     * Add information to WooCommerce system status
     */
    public function add_system_status_info() {
        ?>
        <table class="wc_status_table widefat" cellspacing="0">
            <thead>
                <tr>
                    <th colspan="3" data-export-label="DX Shipping">
                        <?php _e('DX Shipping', 'dx-shipping-woocommerce'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td data-export-label="Version"><?php _e('Version:', 'dx-shipping-woocommerce'); ?></td>
                    <td class="help"></td>
                    <td><?php echo DX_SHIPPING_VERSION; ?></td>
                </tr>
                <tr>
                    <td data-export-label="Active Zones"><?php _e('Active Zones:', 'dx-shipping-woocommerce'); ?></td>
                    <td class="help"></td>
                    <td>
                        <?php
                        $count = 0;
                        $zones = WC_Shipping_Zones::get_zones();
                        foreach ($zones as $zone) {
                            foreach ($zone['shipping_methods'] as $method) {
                                if ('dx_shipping' === $method->id && 'yes' === $method->enabled) {
                                    $count++;
                                }
                            }
                        }
                        echo $count;
                        ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * AJAX handler for test calculation
     */
    public function ajax_test_calculation() {
        check_ajax_referer('dx-shipping-test', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die();
        }

        $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 0;
        $base_cost = isset($_POST['base_cost']) ? floatval($_POST['base_cost']) : 0;
        $threshold = isset($_POST['threshold']) ? floatval($_POST['threshold']) : 0;
        $excess_rate = isset($_POST['excess_rate']) ? floatval($_POST['excess_rate']) : 0;

        $cost = $base_cost;
        $excess_weight = 0;

        if ($weight > $threshold) {
            $excess_weight = $weight - $threshold;
            $cost += $excess_weight * $excess_rate;
        }

        wp_send_json_success(array(
            'cost' => $cost,
            'excess_weight' => $excess_weight,
            'excess_charge' => $excess_weight * $excess_rate,
        ));
    }
}