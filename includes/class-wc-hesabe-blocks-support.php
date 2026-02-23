<?php
/**
 * WooCommerce Blocks Support for Hesabe Gateway
 *
 * Integrates Hesabe payment gateway with WooCommerce Blocks/Checkout Block
 * Extends AbstractPaymentMethodType to provide block-specific functionality
 *
 * @package Hesabe_WooCommerce
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce Blocks is available
if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
    return;
}

/**
 * WC_Hesabe_Blocks_Support Class
 * 
 * This class is essential for WooCommerce Blocks to recognize and render
 * the Hesabe payment gateway in the block-based checkout.
 */
class WC_Hesabe_Blocks_Support extends Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

    /**
     * Payment method name/id
     * Must match the gateway ID
     *
     * @var string
     */
    protected $name = 'hesabe';


    /**
     * Initialize the payment method type
     * This is called by WooCommerce Blocks to set up the payment method
     */
    public function initialize() {
        $this->settings = get_option('woocommerce_hesabe_settings', array());
    }

    /**
     * Check if the payment method is active
     * This determines if the payment method should appear in block checkout
     * Matches MyFatoorah pattern EXACTLY - only check enabled setting
     *
     * @return bool
     */
    public function is_active() {
        // Match MyFatoorah exactly - only check enabled setting
        // Credential validation happens in gateway's is_available() method
        return filter_var($this->get_setting('enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get payment method script handles
     * For Block Checkout, a frontend script must register the payment method via wcBlocksRegistry.
     *
     * @return array
     */
    public function get_payment_method_script_handles() {
        $handle = 'wc-hesabe-blocks-integration';

        // Register the script (plain JS, no build tooling required)
        wp_register_script(
            $handle,
            plugins_url('build/index.js', HESABE_WC_PLUGIN_FILE),
            array(
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ),
            defined('HESABE_WC_VERSION') ? HESABE_WC_VERSION : '6.0.0',
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations($handle, 'hesabe-woocommerce', dirname(HESABE_WC_PLUGIN_FILE) . '/languages');
        }

        return array($handle);
    }

    /**
     * Get payment method data
     * This provides data to the Blocks frontend JavaScript
     * Matches MyFatoorah pattern exactly
     *
     * @return array
     */
    public function get_payment_method_data() {
        // Get settings directly - match MyFatoorah pattern
        $title = $this->get_setting('title', __('Hesabe Payments', 'hesabe-woocommerce'));
        // Default to empty description (avoid showing boilerplate text on checkout)
        $description = $this->get_setting('description', '');
        $icon = $this->get_setting('icon', '');

        // Pay on Hesabe enabled => selection happens on Hesabe page
        $pay_on_hesabe = $this->get_setting('pay_on_hesabe', null);
        if ($pay_on_hesabe === null || $pay_on_hesabe === '') {
            // Back-compat with older "direct" setting
            $old_direct = $this->get_setting('direct', 'no');
            $pay_on_hesabe = ($old_direct === 'yes') ? 'no' : 'yes';
        }
        $pay_on_hesabe_enabled = ($pay_on_hesabe === 'yes');
        $direct_enabled = !$pay_on_hesabe_enabled;

        $methods = array();
        if ($direct_enabled) {
            if ($this->get_setting('direct_knet', 'no') === 'yes') {
                $methods[] = array(
                    'id' => '1',
                    'label' => 'KNET',
                    'icon' => plugins_url('assets/images/knet.png', HESABE_WC_PLUGIN_FILE),
                );
            }
            if ($this->get_setting('direct_visa_mastercard', 'no') === 'yes') {
                $methods[] = array(
                    'id' => '2',
                    'label' => 'Visa/Mastercard',
                    'icon' => plugins_url('assets/images/mastervisa.png', HESABE_WC_PLUGIN_FILE),
                );
            }
            if ($this->get_setting('direct_amex', 'no') === 'yes') {
                $methods[] = array(
                    'id' => '7',
                    'label' => 'AMEX',
                    'icon' => plugins_url('assets/images/amex_new.png', HESABE_WC_PLUGIN_FILE),
                );
            }
            if ($this->get_setting('direct_applepay_knet', 'no') === 'yes') {
                $methods[] = array(
                    'id' => '11',
                    'label' => 'Apple Pay (KNET)',
                    'icon' => plugins_url('assets/images/apple.png', HESABE_WC_PLUGIN_FILE),
                );
            }
            if ($this->get_setting('direct_applepay', 'no') === 'yes') {
                $methods[] = array(
                    'id' => '9',
                    'label' => 'Apple Pay',
                    'icon' => plugins_url('assets/images/apple.png', HESABE_WC_PLUGIN_FILE),
                );
            }
        }

        // Return data for the Blocks frontend - use parent get_supported_features()
        return array(
            'title' => $title,
            'description' => $description,
            'supports' => $this->get_supported_features(),
            'icon' => $icon,
            'name' => $this->name,
            'pay_on_hesabe' => $pay_on_hesabe_enabled,
            'direct' => $direct_enabled,
            'methods' => $methods,
        );
    }
}

