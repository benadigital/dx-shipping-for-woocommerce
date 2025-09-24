# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress/WooCommerce plugin developed by BenaDigital that provides a custom weight-based shipping method for UK-based deliveries. The plugin calculates shipping costs based on package weight with configurable base rates and per-kg charges for excess weight.

## Architecture

### Plugin Structure
- **Main Plugin File**: `dx-shipping-for-woocommerce.php` - Handles plugin initialization, hooks, WordPress admin integration, and coordinates all functionality
- **Shipping Method Class**: `includes/class-dx-shipping-method.php` - Extends WC_Shipping_Method to provide the actual shipping calculation logic
- **Admin Interface**: `includes/class-dx-shipping-admin.php` - Manages admin menu, settings page, debugging tools, and insurance overview

### Admin Features
The admin interface provides:
- **Settings Page**: Main DX Shipping settings page under WooCommerce menu
- **Active Zones Display**: Shows all shipping zones with DX Shipping enabled
- **Shipping Calculator**: Test shipping calculations with different weights and rates
- **Postcode Tester**: Verify if postcodes are excluded from DX delivery
- **Insurance Overview**: Display all products and categories with insurance values
  - Two-column layout showing products and categories
  - Shows insurance amounts with currency symbol
  - Quick edit links for easy access
  - Helper methods: `get_products_with_insurance()` and `get_categories_with_insurance()`
- **Quick Links**: Direct access to WooCommerce shipping settings, debug logs, and currency settings

### Key Design Patterns
1. **Singleton Pattern**: Main plugin class uses singleton to ensure single instance
2. **WooCommerce Integration**: Properly hooks into WooCommerce's shipping zones system
3. **WordPress Coding Standards**: Follows WordPress naming conventions and security practices

## Development Commands

This plugin has no build process or package dependencies. It's a standard WordPress plugin that works directly with PHP files.

### Testing the Plugin
1. Activate plugin in WordPress admin
2. Navigate to WooCommerce > Settings > Shipping to configure zones
3. Use the DX Shipping admin page (WooCommerce > DX Shipping) for:
   - Testing calculations with the shipping calculator
   - Testing postcode exclusions
   - Viewing products and categories with insurance values
4. Enable debug mode in settings to log calculations to WooCommerce > Status > Logs

### Debugging
- Debug panel appears on checkout for admin users
- AJAX endpoints available for testing: `dx_debug_info`, `dx_shipping_test_calculation`
- Logs written to WooCommerce logs when debug mode enabled

## Core Functionality

### Weight-Based Calculation Logic
The shipping cost calculation follows this formula:
- Base cost applies up to weight threshold (default 20kg)
- Excess weight charged at configurable per-kg rate
- Optional min/max cost constraints
- Free shipping threshold based on order value

### UK Postcode Exclusions
The plugin excludes non-mainland UK postcodes using regex patterns in `dx_is_excluded_postcode()`:
- Northern Ireland (BT)
- Channel Islands (GY, JE)
- Scottish Islands (HS, ZE, KW, IV, PH, PA)
- Specific highland areas (FK18, FK19)

### Hidden Insurance Fees
Products and categories can have hidden insurance fees added to shipping:
- Product-level: `_dx_shipping_insurance` post meta
- Category-level: `dx_shipping_insurance` term meta
- Fees are added to shipping cost but not shown separately to customers
- Admin page shows overview of all products/categories with insurance configured

## WordPress/WooCommerce Hooks Used

### Critical Action Hooks
- `woocommerce_shipping_init` - Load shipping method class
- `woocommerce_shipping_methods` - Register shipping method
- `woocommerce_update_options_shipping_{id}` - Save settings
- `woocommerce_checkout_update_order_review` - Check excluded postcodes

### Filter Hooks
- `plugin_action_links_{basename}` - Add settings link
- `woocommerce_no_shipping_available_html` - Custom message for excluded areas
- `dx_shipping_is_available` - Allow customization of availability check

## Important Notes

1. **Weight Conversion**: All weights internally converted to kg for consistency
2. **Tax Handling**: Respects WooCommerce tax settings for shipping
3. **Cache Management**: Clears shipping transients on activation/deactivation
4. **Multisite**: Not tested for multisite installations
5. **PHP Version**: Requires PHP 7.3+ (compatible with WordPress 5.5.1+)