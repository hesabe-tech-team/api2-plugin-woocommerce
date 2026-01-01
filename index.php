<?php
/*
Plugin Name: WooCommerce Hesabe Payment Gateway
Plugin URI: https://hesabe.com/
Description: Integrate the Hesabe payment gateway with your website.
This plugin included with two payment methods(Knet & MPGS).
This plugin developed using encryption Hesabe API
More secure and easy configuration.
Version: 4.0
Author: HesabeTeam
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
if (!defined('ABSPATH'))
    exit;

define( 'WC_HESABE_ABSPATH', __DIR__ . '/' );
define( 'WC_HESABE_PLUGIN_URL', plugins_url( '', __FILE__ ) );

add_action('plugins_loaded', 'woocommerce_hesabe_init', 0);

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

function woocommerce_hesabe_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    /**
     * Required minimums and constants
     */
    define( 'WC_HESABE_VERSION', '4.0' );
    define( 'WC_HESABE_TEST_URL', 'https://sandbox.hesabe.com' );
    define( 'WC_HESABE_LIVE_URL', 'https://api.hesabe.com' );
    //define( 'WC_HESABE_INDIRECT_METHOD', true ); // Displaying Hesabe payment method(indirect
   
    /**
     * Gateway class
     */
    require_once __DIR__ . '/include/class-wc-hesabe-crypt.php';
    require_once __DIR__ . '/include/class-wc-hesabe-settings.php';
    /**
     * Add the Gateway to WooCommerce
     * @param $methods
     * @return array
     */
    function woocommerce_add_hesabe_gateway($methods)
    {
        $methods[] = 'WC_Hesabe';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_hesabe_gateway');

    /**
     * Register WooCommerce Blocks integration
     */
    require_once __DIR__ . '/include/class-wc-hesabe-blocks.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry ) {
            $registry->register( new WC_Hesabe_Blocks() );
        }
    );

    /**
     * Filter to add Hesabe to compatible payment methods for checkout blocks
     */
    add_filter( 'woocommerce_blocks_payment_method_list', function( $methods ) {
        $methods[] = 'hesabe';
        return $methods;
    });

    /**
     * Manage hesabe payment gateway for user interface
     */

    add_filter('woocommerce_available_payment_gateways', 'hesabe_setting_enable_manager');

    /** Hesabe Setting Enable Manager
     * @param $available_gateways
     * @return mixed
     */
    function hesabe_setting_enable_manager($available_gateways)
    {         
        return $available_gateways;
    }
}

?>
