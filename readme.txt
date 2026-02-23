=== Hesabe WooCommerce Payment Gateway ===
Contributors: hesabeteam
Tags: woocommerce, payment, gateway, hesabe, kuwait, knet, visa, mastercard, amex, apple pay
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 6.0.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Integrate the Hesabe payment gateway with your WooCommerce store. Supports both staging and production environments with full block editor compatibility.

== Description ==

Hesabe WooCommerce Payment Gateway is a complete payment solution for WooCommerce stores, providing seamless integration with the Hesabe payment gateway.

**Key Features:**

* Full WooCommerce Blocks/Checkout Block compatibility
* Staging (Sandbox) and Live (Production) environments
* Automatic default test credentials for staging
* Direct payment method selection on checkout
* Support for multiple payment types:
  * KNET
  * Visa/Mastercard (MPGS)
  * AMEX
  * Apple Pay
  * Apple Pay (KNET)
* Secure AES-256-CBC encryption
* Based on official Hesabe PHP integration kit
* WordPress and WooCommerce coding standards compliant

**Environment Management:**

* **Staging Mode**: Automatically uses default test credentials for quick setup
* **Live Mode**: Configure with your production credentials from merchant panel
* Easy switching between environments

**Direct Payment Option:**

* When enabled: Customers select payment method directly on checkout page
* When disabled: Customers select payment method on Hesabe payment page
* Individual payment method toggles for fine-grained control

**Block Editor Support:**

* Fully compatible with WooCommerce Checkout Block
* Works with classic checkout shortcode
* Seamless integration with Gutenberg/block editor

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hesabe-woocommerce-payment-gateway` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to WooCommerce > Settings > Payments and configure the Hesabe gateway.
4. Select your environment (Staging or Live) and enter your credentials.
5. Enable the gateway and save settings.

== Frequently Asked Questions ==

= Does this plugin work with WooCommerce Blocks? =

Yes! This plugin is fully compatible with WooCommerce Checkout Block and all block-based checkout experiences.

= What are the default test credentials? =

The plugin automatically uses Hesabe's default sandbox credentials when in Staging mode:
* Merchant Code: 842217
* Access Code: c333729b-d060-4b74-a49d-7686a8353481
* Secret Key: PkW64zMe5NVdrlPVNnjo2Jy9nOb7v1Xg
* IV Key: 5NVdrlPVNnjo2Jy9

= Where do I get my production credentials? =

Production credentials are available in your merchant panel at https://merchant.hesabe.com

= What payment methods are supported? =

The plugin supports:
* KNET (Payment Type: 1)
* Visa/Mastercard/MPGS (Payment Type: 2)
* AMEX (Payment Type: 7)
* Apple Pay (Payment Type: 9)
* Apple Pay (KNET) (Payment Type: 11)

= What is the Direct Payment option? =

When Direct Payment is enabled, customers can select their preferred payment method directly on your checkout page. When disabled, customers will be redirected to the Hesabe payment page to select their payment method.

== Screenshots ==

1. Admin settings page with environment toggle
2. Payment method selection on checkout
3. Staging mode with default credentials
4. Live mode configuration

== Changelog ==

= 6.0.0 =
* Initial release
* Full WooCommerce Blocks support
* Staging and Live environment management
* Direct payment method selection
* Automatic default test credentials
* Support for all Hesabe payment types
* Based on official Hesabe PHP integration kit

== Upgrade Notice ==

= 6.0.0 =
Initial release. Install and configure your credentials to start accepting payments.

