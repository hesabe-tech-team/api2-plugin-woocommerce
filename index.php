<?php
/*
Plugin Name: WooCommerce Hesabe gateway
Plugin URI: https://hesabe.com/
Description: Used to integrate the hesabe payment gateway with your website.
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
    require_once dirname( __FILE__ ) . '/include/class-wc-hesabe-crypt.php';
    require_once dirname( __FILE__ ) . '/include/class-wc-hesabe-knet.php';
    //require_once dirname( __FILE__ ) . '/include/class-wc-hesabe-mpgs.php';
    /**
     * Gateway class
     */



    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_hesabe_gateway($methods)
    {
        $methods[] = 'WC_hesabe';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_hesabe_gateway');
}

?>
