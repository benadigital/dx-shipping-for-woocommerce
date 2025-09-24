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

        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

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

            <!-- WordPress Native Tabs -->
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="?page=dx-shipping-settings&tab=overview" class="nav-tab <?php echo $current_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Overview', 'dx-shipping-woocommerce'); ?>
                </a>
                <a href="?page=dx-shipping-settings&tab=zones" class="nav-tab <?php echo $current_tab === 'zones' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Shipping Zones', 'dx-shipping-woocommerce'); ?>
                </a>
                <a href="?page=dx-shipping-settings&tab=insurance" class="nav-tab <?php echo $current_tab === 'insurance' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Insurance', 'dx-shipping-woocommerce'); ?>
                </a>
                <a href="?page=dx-shipping-settings&tab=tools" class="nav-tab <?php echo $current_tab === 'tools' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Tools', 'dx-shipping-woocommerce'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ($current_tab) {
                    case 'zones':
                        $this->render_zones_tab($dx_shipping_instances);
                        break;
                    case 'insurance':
                        $this->render_insurance_tab();
                        break;
                    case 'tools':
                        $this->render_tools_tab();
                        break;
                    case 'overview':
                    default:
                        $this->render_overview_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Overview Tab
     */
    private function render_overview_tab() {
        ?>
        <div class="postbox">
            <div class="inside">
                <h2><?php _e('Welcome to DX Shipping for WooCommerce', 'dx-shipping-woocommerce'); ?></h2>
                <p><?php _e('This plugin provides weight-based shipping calculations with configurable base rates and per-kg charges for excess weight.', 'dx-shipping-woocommerce'); ?></p>

                <h3><?php _e('Key Features', 'dx-shipping-woocommerce'); ?></h3>
                <ul class="ul-disc">
                    <li><?php _e('Weight-based shipping calculations', 'dx-shipping-woocommerce'); ?></li>
                    <li><?php _e('Configurable base rates and excess weight charges', 'dx-shipping-woocommerce'); ?></li>
                    <li><?php _e('UK postcode exclusions for non-mainland areas', 'dx-shipping-woocommerce'); ?></li>
                    <li><?php _e('Hidden insurance fees for products and categories', 'dx-shipping-woocommerce'); ?></li>
                    <li><?php _e('Free shipping threshold based on order value', 'dx-shipping-woocommerce'); ?></li>
                    <li><?php _e('Debug mode for troubleshooting', 'dx-shipping-woocommerce'); ?></li>
                </ul>

                <h3><?php _e('Quick Links', 'dx-shipping-woocommerce'); ?></h3>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping'); ?>" class="button button-primary">
                        <?php _e('Configure Shipping Zones', 'dx-shipping-woocommerce'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wc-status&tab=logs'); ?>" class="button">
                        <?php _e('View Debug Logs', 'dx-shipping-woocommerce'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=general'); ?>" class="button">
                        <?php _e('Currency & Weight Settings', 'dx-shipping-woocommerce'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render Zones Tab
     */
    private function render_zones_tab($dx_shipping_instances) {
        ?>
        <div class="postbox">
            <div class="inside">
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
                                        <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=shipping&zone_id=' . $instance['zone_id']); ?>" class="button button-small">
                                            <?php _e('Edit Zone', 'dx-shipping-woocommerce'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render Insurance Tab
     */
    private function render_insurance_tab() {
        $products_with_insurance = $this->get_products_with_insurance();
        $categories_with_insurance = $this->get_categories_with_insurance();
        ?>
        <div class="postbox">
            <div class="inside">
                <h2><?php _e('Shipping Insurance Configuration', 'dx-shipping-woocommerce'); ?></h2>
                <p><?php _e('Products and categories can have hidden insurance fees that are automatically added to the shipping cost.', 'dx-shipping-woocommerce'); ?></p>

                <!-- Sub-tabs for Products and Categories -->
                <ul class="subsubsub">
                    <li><a href="#" class="current" data-tab="products"><?php _e('Products', 'dx-shipping-woocommerce'); ?> <span class="count">(<?php echo count($products_with_insurance); ?>)</span></a> |</li>
                    <li><a href="#" data-tab="categories"><?php _e('Categories', 'dx-shipping-woocommerce'); ?> <span class="count">(<?php echo count($categories_with_insurance); ?>)</span></a></li>
                </ul>
                <br class="clear">

                <!-- Products Table -->
                <div id="insurance-products" class="insurance-tab-content">
                    <h3><?php _e('Products with Insurance', 'dx-shipping-woocommerce'); ?></h3>
                    <?php if (empty($products_with_insurance)): ?>
                        <p><?php _e('No products have insurance configured.', 'dx-shipping-woocommerce'); ?></p>
                        <p><em><?php _e('To add insurance to a product, edit the product and look for the "Shipping Insurance" field in the Shipping tab.', 'dx-shipping-woocommerce'); ?></em></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Product', 'dx-shipping-woocommerce'); ?></th>
                                    <th><?php _e('SKU', 'dx-shipping-woocommerce'); ?></th>
                                    <th><?php _e('Insurance Amount', 'dx-shipping-woocommerce'); ?></th>
                                    <th><?php _e('Actions', 'dx-shipping-woocommerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products_with_insurance as $product): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($product['name']); ?></strong>
                                        </td>
                                        <td><?php echo $product['sku'] ? esc_html($product['sku']) : '—'; ?></td>
                                        <td><?php echo get_woocommerce_currency_symbol(); ?><?php echo number_format($product['insurance'], 2); ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('post.php?post=' . $product['id'] . '&action=edit'); ?>" class="button button-small">
                                                <?php _e('Edit', 'dx-shipping-woocommerce'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Categories Table -->
                <div id="insurance-categories" class="insurance-tab-content" style="display: none;">
                    <h3><?php _e('Categories with Insurance', 'dx-shipping-woocommerce'); ?></h3>
                    <?php if (empty($categories_with_insurance)): ?>
                        <p><?php _e('No categories have insurance configured.', 'dx-shipping-woocommerce'); ?></p>
                        <p><em><?php _e('To add insurance to a category, edit the category and look for the "Shipping Insurance" field.', 'dx-shipping-woocommerce'); ?></em></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Category', 'dx-shipping-woocommerce'); ?></th>
                                    <th><?php _e('Product Count', 'dx-shipping-woocommerce'); ?></th>
                                    <th><?php _e('Insurance Amount', 'dx-shipping-woocommerce'); ?></th>
                                    <th><?php _e('Actions', 'dx-shipping-woocommerce'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories_with_insurance as $category): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($category['name']); ?></strong>
                                        </td>
                                        <td><?php echo $category['count']; ?></td>
                                        <td><?php echo get_woocommerce_currency_symbol(); ?><?php echo number_format($category['insurance'], 2); ?></td>
                                        <td>
                                            <a href="<?php echo admin_url('term.php?taxonomy=product_cat&tag_ID=' . $category['id'] . '&post_type=product'); ?>" class="button button-small">
                                                <?php _e('Edit', 'dx-shipping-woocommerce'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Insurance sub-tabs
                $('.subsubsub a').on('click', function(e) {
                    e.preventDefault();
                    var tab = $(this).data('tab');
                    $('.subsubsub a').removeClass('current');
                    $(this).addClass('current');
                    $('.insurance-tab-content').hide();
                    $('#insurance-' + tab).show();
                });
            });
        </script>
        <?php
    }

    /**
     * Render Tools Tab
     */
    private function render_tools_tab() {
        ?>
        <!-- Postcode Tester -->
        <div class="postbox">
            <div class="inside">
                <h2><?php _e('Postcode Exclusion Tester', 'dx-shipping-woocommerce'); ?></h2>
                <p><?php _e('Check if a postcode is excluded from DX delivery:', 'dx-shipping-woocommerce'); ?></p>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test_postcode_country"><?php _e('Country', 'dx-shipping-woocommerce'); ?></label>
                        </th>
                        <td>
                            <select id="test_postcode_country" class="regular-text">
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
                            <input type="text" id="test_postcode" class="regular-text" placeholder="e.g. BT1 1AA" />
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="button" id="test-postcode" class="button button-primary">
                        <?php _e('Test Postcode', 'dx-shipping-woocommerce'); ?>
                    </button>
                </p>

                <div id="postcode-result" style="display: none; margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #72aee6;">
                    <h3 style="margin-top: 0;"><?php _e('Result:', 'dx-shipping-woocommerce'); ?></h3>
                    <p id="postcode-result-text"></p>
                </div>
            </div>
        </div>

        <!-- Calculator Tool -->
        <div class="postbox">
            <div class="inside">
                <h2><?php _e('Shipping Calculator', 'dx-shipping-woocommerce'); ?></h2>
                <p><?php _e('Calculate shipping cost based on weight using your configured rates.', 'dx-shipping-woocommerce'); ?></p>

                <?php
                // Get first available DX shipping instance to use its settings
                $default_settings = null;
                $zones = WC_Shipping_Zones::get_zones();
                foreach ($zones as $zone) {
                    foreach ($zone['shipping_methods'] as $method) {
                        if ('dx_shipping' === $method->id && 'yes' === $method->enabled) {
                            $default_settings = array(
                                'base_cost' => $method->get_option('base_cost', '8.00'),
                                'weight_threshold' => $method->get_option('weight_threshold', '20'),
                                'excess_rate' => $method->get_option('excess_rate', '0.40'),
                                'zone_name' => $zone['zone_name']
                            );
                            break 2;
                        }
                    }
                }

                if (!$default_settings) {
                    // Check Rest of the World zone
                    $zone_0 = WC_Shipping_Zones::get_zone(0);
                    foreach ($zone_0->get_shipping_methods() as $method) {
                        if ('dx_shipping' === $method->id && 'yes' === $method->enabled) {
                            $default_settings = array(
                                'base_cost' => $method->get_option('base_cost', '8.00'),
                                'weight_threshold' => $method->get_option('weight_threshold', '20'),
                                'excess_rate' => $method->get_option('excess_rate', '0.40'),
                                'zone_name' => __('Rest of the World', 'dx-shipping-woocommerce')
                            );
                            break;
                        }
                    }
                }

                // Use defaults if no configured method found
                if (!$default_settings) {
                    $default_settings = array(
                        'base_cost' => '8.00',
                        'weight_threshold' => '20',
                        'excess_rate' => '0.40',
                        'zone_name' => __('Default', 'dx-shipping-woocommerce')
                    );
                }
                ?>

                <div style="background: #f0f0f1; padding: 10px; margin-bottom: 20px; border-radius: 3px;">
                    <strong><?php _e('Using rates from shipping zone:', 'dx-shipping-woocommerce'); ?></strong> <?php echo esc_html($default_settings['zone_name']); ?>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test_base_cost"><?php _e('Base Cost', 'dx-shipping-woocommerce'); ?></label>
                        </th>
                        <td>
                            <span><?php echo get_woocommerce_currency_symbol(); ?></span>
                            <input type="number" id="test_base_cost" class="small-text" step="0.01" min="0" value="<?php echo esc_attr($default_settings['base_cost']); ?>" readonly style="background-color: #f0f0f1;" />
                            <span class="description"><?php _e('(configured in shipping zone)', 'dx-shipping-woocommerce'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="test_threshold"><?php _e('Weight Threshold', 'dx-shipping-woocommerce'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="test_threshold" class="small-text" step="0.01" min="0" value="<?php echo esc_attr($default_settings['weight_threshold']); ?>" readonly style="background-color: #f0f0f1;" />
                            <span class="description"><?php echo get_option('woocommerce_weight_unit'); ?> <?php _e('(configured in shipping zone)', 'dx-shipping-woocommerce'); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="test_excess_rate"><?php _e('Excess Rate', 'dx-shipping-woocommerce'); ?></label>
                        </th>
                        <td>
                            <span><?php echo get_woocommerce_currency_symbol(); ?></span>
                            <input type="number" id="test_excess_rate" class="small-text" step="0.01" min="0" value="<?php echo esc_attr($default_settings['excess_rate']); ?>" readonly style="background-color: #f0f0f1;" />
                            <span class="description">/ <?php echo get_option('woocommerce_weight_unit'); ?> <?php _e('(configured in shipping zone)', 'dx-shipping-woocommerce'); ?></span>
                        </td>
                    </tr>
                    <tr style="border-top: 1px solid #c3c4c7;">
                        <th scope="row">
                            <label for="test_weight"><strong><?php _e('Package Weight', 'dx-shipping-woocommerce'); ?></strong></label>
                        </th>
                        <td>
                            <input type="number" id="test_weight" class="regular-text" step="0.01" min="0" value="25" style="font-weight: bold;" />
                            <span class="description"><?php echo get_option('woocommerce_weight_unit'); ?></span>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="button" id="calculate-shipping" class="button button-primary">
                        <?php _e('Calculate Shipping Cost', 'dx-shipping-woocommerce'); ?>
                    </button>
                </p>

                <div id="calculation-result" style="display: none; margin-top: 20px; padding: 15px; background: #f0f8ff; border-left: 4px solid #0073aa;">
                    <h3 style="margin-top: 0;"><?php _e('Result:', 'dx-shipping-woocommerce'); ?></h3>
                    <p id="result-text"></p>
                </div>
            </div>
        </div>

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
                        $('#postcode-result').css('border-left-color', '#d63638');
                        resultText = '<span style="color: #d63638;">✕ <?php _e('EXCLUDED', 'dx-shipping-woocommerce'); ?></span><br>' + reason;
                    } else {
                        $('#postcode-result').css('border-left-color', '#00a32a');
                        resultText = '<span style="color: #00a32a;">✓ <?php _e('AVAILABLE for DX delivery', 'dx-shipping-woocommerce'); ?></span>';
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

                    var resultText = '<div style="font-size: 24px; color: #0073aa; margin-bottom: 15px;">' +
                                   '<strong><?php _e('Total Shipping Cost:', 'dx-shipping-woocommerce'); ?></strong> ' +
                                   '<span style="color: #00a32a; font-size: 28px;"><?php echo get_woocommerce_currency_symbol(); ?>' + cost.toFixed(2) + '</span>' +
                                   '</div>';

                    if (weight <= 0) {
                        resultText = '<div style="font-size: 18px; color: #d63638;">' +
                                   '<strong><?php _e('Please enter a valid weight', 'dx-shipping-woocommerce'); ?></strong>' +
                                   '</div>';
                        $('#calculation-result').css('border-left-color', '#d63638');
                    } else if (excessWeight > 0) {
                        $('#calculation-result').css('border-left-color', '#0073aa');
                        resultText += '<div style="background: #f0f0f1; padding: 10px; border-radius: 3px; margin-top: 10px;">';
                        resultText += '<strong><?php _e('Calculation Breakdown:', 'dx-shipping-woocommerce'); ?></strong>';
                        resultText += '<table style="width: 100%; margin-top: 10px;">';
                        resultText += '<tr><td><?php _e('Base cost', 'dx-shipping-woocommerce'); ?> (' + threshold + ' <?php echo get_option('woocommerce_weight_unit'); ?>):</td>' +
                                    '<td style="text-align: right;"><strong><?php echo get_woocommerce_currency_symbol(); ?>' + baseCost.toFixed(2) + '</strong></td></tr>';
                        resultText += '<tr><td><?php _e('Excess weight:', 'dx-shipping-woocommerce'); ?></td>' +
                                    '<td style="text-align: right;"><strong>' + excessWeight.toFixed(2) + ' <?php echo get_option('woocommerce_weight_unit'); ?></strong></td></tr>';
                        resultText += '<tr><td><?php _e('Excess charge', 'dx-shipping-woocommerce'); ?> (' + excessWeight.toFixed(2) + ' × <?php echo get_woocommerce_currency_symbol(); ?>' + excessRate + '):</td>' +
                                    '<td style="text-align: right;"><strong><?php echo get_woocommerce_currency_symbol(); ?>' + (excessWeight * excessRate).toFixed(2) + '</strong></td></tr>';
                        resultText += '<tr style="border-top: 2px solid #c3c4c7;"><td><strong><?php _e('Total:', 'dx-shipping-woocommerce'); ?></strong></td>' +
                                    '<td style="text-align: right;"><strong style="color: #00a32a; font-size: 16px;"><?php echo get_woocommerce_currency_symbol(); ?>' + cost.toFixed(2) + '</strong></td></tr>';
                        resultText += '</table>';
                        resultText += '</div>';
                    } else {
                        $('#calculation-result').css('border-left-color', '#00a32a');
                        resultText += '<div style="background: #f0f0f1; padding: 10px; border-radius: 3px; margin-top: 10px;">';
                        resultText += '<strong><?php _e('Weight is within base threshold', 'dx-shipping-woocommerce'); ?></strong><br>';
                        resultText += '<?php _e('Package weight:', 'dx-shipping-woocommerce'); ?> ' + weight + ' <?php echo get_option('woocommerce_weight_unit'); ?><br>';
                        resultText += '<?php _e('Base rate applies (up to', 'dx-shipping-woocommerce'); ?> ' + threshold + ' <?php echo get_option('woocommerce_weight_unit'); ?>)';
                        resultText += '</div>';
                    }

                    $('#result-text').html(resultText);
                    $('#calculation-result').show();
                });
            });
        </script>
        <?php
    }

    /**
     * Get products with insurance values
     */
    private function get_products_with_insurance() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_dx_shipping_insurance',
                    'value' => '',
                    'compare' => '!=',
                ),
                array(
                    'key' => '_dx_shipping_insurance',
                    'value' => '0',
                    'compare' => '!=',
                ),
            ),
        );

        $query = new WP_Query($args);
        $products = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);

                if ($product) {
                    $insurance = get_post_meta($product_id, '_dx_shipping_insurance', true);
                    if ($insurance && $insurance > 0) {
                        $products[] = array(
                            'id' => $product_id,
                            'name' => $product->get_name(),
                            'sku' => $product->get_sku(),
                            'insurance' => floatval($insurance),
                        );
                    }
                }
            }
            wp_reset_postdata();
        }

        return $products;
    }

    /**
     * Get categories with insurance values
     */
    private function get_categories_with_insurance() {
        $args = array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'meta_query' => array(
                array(
                    'key' => 'dx_shipping_insurance',
                    'value' => '',
                    'compare' => '!=',
                ),
                array(
                    'key' => 'dx_shipping_insurance',
                    'value' => '0',
                    'compare' => '!=',
                ),
            ),
        );

        $terms = get_terms($args);
        $categories = array();

        if (!is_wp_error($terms) && !empty($terms)) {
            foreach ($terms as $term) {
                $insurance = get_term_meta($term->term_id, 'dx_shipping_insurance', true);
                if ($insurance && $insurance > 0) {
                    $categories[] = array(
                        'id' => $term->term_id,
                        'name' => $term->name,
                        'count' => $term->count,
                        'insurance' => floatval($insurance),
                    );
                }
            }
        }

        return $categories;
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

        // Add some custom CSS for better styling
        ?>
        <style>
            .postbox {
                margin-top: 20px;
            }
            .postbox .inside {
                padding: 20px;
            }
            .nav-tab-wrapper {
                margin-bottom: 0;
            }
            .tab-content {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-top: none;
                padding: 0;
            }
            .form-table th {
                width: 200px;
            }
            .insurance-tab-content {
                margin-top: 20px;
            }
        </style>
        <?php
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