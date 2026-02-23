# Hesabe WooCommerce Payment Gateway

A complete WordPress WooCommerce payment gateway plugin for Hesabe payment processing. Fully compatible with WooCommerce Blocks and the latest WordPress/WooCommerce versions.

## Features

- ✅ **Full WooCommerce Blocks Support** - Works seamlessly with Checkout Block
- ✅ **Dual Environment** - Staging (Sandbox) and Live (Production) modes
- ✅ **Auto Default Credentials** - Automatic test credentials for staging
- ✅ **Direct Payment Selection** - Customers can select payment method on checkout
- ✅ **Multiple Payment Types** - KNET, Visa/Mastercard, AMEX, Apple Pay
- ✅ **Secure Encryption** - AES-256-CBC encryption following Hesabe standards
- ✅ **WordPress Standards** - Follows WordPress and WooCommerce coding standards

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- WooCommerce Blocks (for block checkout support)

## Installation

1. Upload the plugin to `/wp-content/plugins/hesabe-woocommerce-payment-gateway/`
2. Activate the plugin through the WordPress 'Plugins' menu
3. Navigate to **WooCommerce > Settings > Payments**
4. Find "Hesabe Payment Gateway" and click "Manage"
5. Configure your settings:
   - Select Environment Mode (Staging or Live)
   - Enter credentials (or use default test credentials for staging)
   - Enable the gateway
   - Configure Direct Payment option if needed
6. Save changes

## Configuration

### Environment Modes

#### Staging Mode (Sandbox)
- **Default Test Credentials**: Automatically loaded when enabled
  - Merchant Code: `842217`
  - Access Code: `c333729b-d060-4b74-a49d-7686a8353481`
  - Secret Key: `PkW64zMe5NVdrlPVNnjo2Jy9nOb7v1Xg`
  - IV Key: `5NVdrlPVNnjo2Jy9`
- **Custom Test Credentials**: Uncheck "Use Default Test Credentials" to enter custom staging credentials

#### Live Mode (Production)
- Get credentials from your merchant panel: https://merchant.hesabe.com
- Enter all four credentials:
  - Merchant Code
  - Access Code
  - Secret Key
  - IV Key

### Direct Payment Option

**When Enabled:**
- Customers see payment method selection on checkout page
- Available methods: KNET, Visa/Mastercard, AMEX, Apple Pay, Apple Pay (KNET)
- Selected method is passed directly to Hesabe API

**When Disabled:**
- Payment method selection happens on Hesabe payment page
- Payment Type is set to `0` (Indirect)

### Individual Payment Methods

When Direct Payment is enabled, you can individually enable/disable:
- KNET
- Visa/Mastercard
- AMEX
- Apple Pay
- Apple Pay (KNET)

## Payment Types

| Payment Type | ID | Description |
|-------------|-----|-------------|
| Indirect | 0 | Customer selects on Hesabe page |
| KNET | 1 | KNET payment |
| Visa/Mastercard | 2 | MPGS payment |
| AMEX | 7 | American Express |
| Apple Pay | 9 | Apple Pay |
| Apple Pay (KNET) | 11 | Apple Pay with KNET |

## Testing

### Test Cards (Staging Only)

**KNET:**
- Card: `8888880000000001`
- Expiry: `09/30`
- CVV: `1234`

**Visa:**
- Card: `4012001037141112`
- Expiry: `12/25`
- CVV: `207`

**MasterCard:**
- Card: `5123450000000008`
- Expiry: `01/39`
- CVV: `100`

**AMEX:**
- Card: `373708623186001`
- Expiry: `01/39`
- CVV: `1000`

## API Endpoints

### Staging
- Base URL: `https://sandbox.hesabe.com`
- Checkout: `https://sandbox.hesabe.com/checkout`
- Payment: `https://sandbox.hesabe.com/payment`

### Live
- Base URL: `https://api.hesabe.com`
- Checkout: `https://api.hesabe.com/checkout`
- Payment: `https://api.hesabe.com/payment`

## Documentation

- [Hesabe Developer Documentation](https://developer.hesabe.com/)
- [Sandbox Environment Guide](https://developer.hesabe.com/docs/guides/sandbox-environment)
- [Test Cards](https://developer.hesabe.com/docs/support/support-test-cards)
- [Production Setup](https://developer.hesabe.com/docs/support/production-details)

## Support

For plugin support, please contact Hesabe support team or visit the [Hesabe Developer Portal](https://developer.hesabe.com/).

## Changelog

### 6.0.0
- Initial release
- Full WooCommerce Blocks support
- Staging and Live environment management
- Direct payment method selection
- Automatic default test credentials
- Support for all Hesabe payment types
- Based on official Hesabe PHP integration kit

## License

GPL v3 or later

## Credits

Developed for Hesabe Payment Gateway. Based on the official Hesabe PHP integration kit.

