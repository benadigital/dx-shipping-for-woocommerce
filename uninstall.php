<?php
/**
 * Uninstall DX Shipping for WooCommerce
 *
 * Removes all plugin data when uninstalled
 *
 * @package DX_Shipping_WooCommerce
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
$options_to_delete = array(
    'dx_shipping_version',
    'dx_shipping_activated',
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Clear any transients
delete_transient('dx_shipping_zones_cache');

// Note: We do NOT delete shipping method settings as they are stored
// in WooCommerce tables and should be preserved in case the plugin
// is reinstalled. Users can manually delete shipping methods through
// WooCommerce settings if needed.