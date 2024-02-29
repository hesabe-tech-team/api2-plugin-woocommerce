<?php
/*
Plugin Name: WooCommerce Hesabe Payment Gateway
Plugin URI: https://hesabe.com/
Description: Integrate the Hesabe payment gateway with your website.
This plugin included with two payment methods(Knet & MPGS).
This plugin developed using encryption Hesabe API
More secure and easy configuration.
Version: 2.0
Author: HesabeTeam
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
if (!defined('ABSPATH'))
    exit;
add_action('plugins_loaded', 'woocommerce_hesabe_init', 0);

function woocommerce_hesabe_init()
{
    if (!class_exists('WC_Payment_Gateway')) return;

    /**
     * Required minimums and constants
     */
    define( 'WC_HESABE_VERSION', '2.0' );
    define( 'WC_HESABE_TEST_URL', 'https://sandbox.hesabe.com' );
    define( 'WC_HESABE_LIVE_URL', 'https://api.hesabe.com' );
    define( 'WC_HESABE_INDIRECT_METHOD', true ); // Displaying Hesabe payment method(indirect)

    /**
     * Gateway class
     */
    require_once __DIR__ . '/include/class-wc-hesabe-crypt.php';
    require_once __DIR__ . '/include/class-wc-hesabe-settings.php';
    require_once __DIR__ . '/include/class-wc-hesabe-knet.php';
    require_once __DIR__ . '/include/class-wc-hesabe-mpgs.php';
    require_once __DIR__ . '/include/class-wc-hesabe-apple.php';
    require_once __DIR__ . '/include/class-wc-hesabe-amex.php';

    /**
     * Add the Gateway to WooCommerce
     * @param $methods
     * @return array
     */
    function woocommerce_add_hesabe_gateway($methods)
    {
        $methods[] = 'WC_Hesabe';
        $methods[] = 'WC_Hesabe_Knet';
        $methods[] = 'WC_Hesabe_Mpgs';
        $methods[] = 'WC_Hesabe_ApplePay';
        $methods[] = 'WC_Hesabe_Amex';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_hesabe_gateway');

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
        if (!isset($available_gateways['hesabe'])) {
            unset($available_gateways['hesabe_mpgs']);
            unset($available_gateways['hesabe_knet']);
            unset($available_gateways['hesabe_apple']);
            unset($available_gateways['hesabe_amex']);
        }

        if(!WC_HESABE_INDIRECT_METHOD){
            unset($available_gateways['hesabe']);
        }
          // Check if the browser is Safari
          $is_safari = strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') && !strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome');

          if($is_safari){
              $available_gateways['hesabe_apple'] = $available_gateways['hesabe_apple'];
          } else {
              unset($available_gateways['hesabe_apple']);
          }
        return $available_gateways;
    }
}

?>
