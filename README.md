# DX Shipping for WooCommerce

![WordPress Plugin Version](https://img.shields.io/badge/version-1.0.0-blue)
![WordPress Compatibility](https://img.shields.io/badge/WordPress-5.5.1%2B-green)
![WooCommerce Compatibility](https://img.shields.io/badge/WooCommerce-5.0%2B-purple)
![PHP Version](https://img.shields.io/badge/PHP-7.3%2B-8892BF)
![License](https://img.shields.io/badge/license-GPLv2-orange)

A powerful weight-based shipping plugin for WooCommerce that provides flexible shipping calculations with configurable base rates and per-kg charges. Perfect for UK businesses using DX delivery services.

## 🎯 Features

### Core Functionality
- **Weight-based calculations** - Set a base rate for orders up to a weight threshold
- **Excess weight charges** - Add per-kg charges for weight exceeding your threshold
- **Flexible configuration** - Customize rates for different shipping zones
- **Free shipping option** - Offer free shipping for orders above a certain amount
- **Min/Max constraints** - Set minimum and maximum shipping costs

### Advanced Features
- **Multi-zone support** - Configure different rates for different regions
- **Tax support** - Compatible with WooCommerce tax settings
- **UK postcode exclusions** - Automatically exclude non-mainland UK areas
- **Hidden insurance fees** - Add product/category-specific insurance costs
- **Debug mode** - Log calculations for troubleshooting
- **Admin tools** - Built-in calculator and postcode tester

## 📦 Installation

### Requirements
- WordPress 5.5.1 or higher
- WooCommerce 5.0 or higher
- PHP 7.3 or higher

### Automatic Installation
1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "DX Shipping for WooCommerce"
3. Click "Install Now" and then "Activate"

### Manual Installation
1. Download the plugin zip file
2. Go to **Plugins > Add New > Upload Plugin**
3. Choose the downloaded file and click "Install Now"
4. Activate the plugin

### From GitHub
```bash
cd wp-content/plugins/
git clone https://github.com/benadigital/dx-shipping-for-woocommerce.git
```

## ⚙️ Configuration

### Basic Setup
1. Navigate to **WooCommerce > Settings > Shipping**
2. Add or edit a shipping zone
3. Click "Add shipping method"
4. Select "DX Shipping" from the dropdown
5. Configure your rates:

| Setting | Default | Description |
|---------|---------|-------------|
| Base Cost | £8.00 | Base shipping cost for orders up to weight threshold |
| Weight Threshold | 20kg | Orders above this weight incur additional charges |
| Excess Rate | £0.40/kg | Cost per kg for weight exceeding threshold |
| Free Shipping Amount | - | Optional: Order total for free shipping |
| Min Cost | - | Optional: Minimum shipping cost |
| Max Cost | - | Optional: Maximum shipping cost |

### Advanced Configuration

#### UK Postcode Exclusions
The plugin automatically excludes the following non-mainland UK areas:
- Northern Ireland (BT postcodes)
- Channel Islands (GY, JE)
- Isle of Man (IM)
- Scottish Highlands & Islands (HS, ZE, KW, IV, PH, PA)
- Specific highland areas (FK18, FK19)

#### Insurance Fees (Hidden from customers)
Add insurance costs at product or category level:

**Product Level:**
1. Edit product > Shipping tab
2. Set "Shipping Insurance (£)"

**Category Level:**
1. Products > Categories
2. Edit category > Set "Shipping Insurance"

## 🔧 Developer Information

### Hooks & Filters

#### Actions
```php
// Fired when shipping method is initialized
do_action('dx_shipping_method_init', $this);

// Fired after shipping calculation
do_action('dx_shipping_calculated', $cost, $package);
```

#### Filters
```php
// Modify availability check
add_filter('dx_shipping_is_available', function($available, $package, $instance) {
    // Your custom logic
    return $available;
}, 10, 3);

// Modify calculated cost
add_filter('dx_shipping_cost', function($cost, $weight, $package) {
    // Your custom calculation
    return $cost;
}, 10, 3);
```

### AJAX Endpoints
- `dx_debug_info` - Get debug information (admin only)
- `dx_shipping_test_calculation` - Test shipping calculations

### Debug Mode
Enable debug logging in the shipping method settings to track calculations:
```
WooCommerce > Status > Logs > dx-shipping-{date}
```

## 🧪 Testing

### Using the Admin Calculator
1. Navigate to **WooCommerce > DX Shipping**
2. Use the shipping calculator to test different weights and rates
3. Use the postcode tester to verify exclusions

### Testing on Checkout
Admin users see a debug panel on checkout showing:
- Destination details
- Exclusion status
- Available shipping methods
- Applied insurance fees

## 📊 Example Calculations

### Standard Order
- Order weight: 15kg
- Base rate: £8.00
- **Total shipping: £8.00**

### Heavy Order
- Order weight: 30kg
- Base rate: £8.00 (first 20kg)
- Excess: 10kg × £0.40 = £4.00
- **Total shipping: £12.00**

### With Free Shipping
- Order total: £150
- Free shipping threshold: £100
- **Total shipping: £0.00**

## 🐛 Troubleshooting

### Common Issues

**Shipping method not appearing:**
- Check WooCommerce shipping is enabled
- Verify shipping zones are configured
- Clear shipping transients: WooCommerce > Status > Tools > Clear transients

**Incorrect calculations:**
- Enable debug mode to log calculations
- Check weight units in WooCommerce settings
- Verify product weights are set correctly

**Postcodes not excluding:**
- Test postcode in admin tester
- Check country is set to GB/UK
- Review debug logs for exclusion checks

## 📝 Changelog

### Version 1.0.0 (2024-09-24)
- Initial release
- Weight-based shipping calculations
- Multi-zone support
- UK postcode exclusions
- Admin interface with calculator
- Debug logging functionality
- Product/category insurance fees

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🆘 Support

- **Documentation**: [View full documentation](https://benadigital.com/docs/dx-shipping)
- **Issues**: [Report bugs on GitHub](https://github.com/benadigital/dx-shipping-for-woocommerce/issues)
- **Contact**: [BenaDigital](https://benadigital.com)

## 👥 Authors

- **BenaDigital** - *Initial work* - [Website](https://benadigital.com)

## 🙏 Acknowledgments

- Built for WooCommerce
- Designed for UK businesses using DX delivery services
- Inspired by the need for flexible weight-based shipping calculations

---

**Note**: This plugin is actively maintained and regularly updated to ensure compatibility with the latest versions of WordPress and WooCommerce.