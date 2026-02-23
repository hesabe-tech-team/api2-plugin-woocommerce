<?php
/**
 * Plugin Name: Hesabe WooCommerce Payment Gateway
 * Plugin URI: https://hesabe.com/
 * Description: Integrate the Hesabe payment gateway with your WooCommerce store. Supports both staging and production environments with full block editor compatibility.
 * Version: 6.0.0
 * Author: Hesabe Team
 * Author URI: https://hesabe.com/
 * Text Domain: hesabe-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 10.3.4
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Main plugin class
 */
class Hesabe_WooCommerce_Payment_Gateway {
    
    /**
     * Plugin version
     */
    const VERSION = '6.0.0';
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }
    
    /**
     * Define plugin constants
     */
    private function define_constants() {
        define('HESABE_WC_VERSION', self::VERSION);
        define('HESABE_WC_PLUGIN_FILE', __FILE__);
        define('HESABE_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('HESABE_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('HESABE_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));
        
        // API URLs
        define('HESABE_WC_STAGING_URL', 'https://sandbox.hesabe.com');
        define('HESABE_WC_LIVE_URL', 'https://api.hesabe.com');
        
        // Default staging credentials (from Hesabe documentation)
        define('HESABE_WC_DEFAULT_STAGING_MERCHANT_CODE', '842217');
        define('HESABE_WC_DEFAULT_STAGING_ACCESS_CODE', 'c333729b-d060-4b74-a49d-7686a8353481');
        define('HESABE_WC_DEFAULT_STAGING_SECRET_KEY', 'PkW64zMe5NVdrlPVNnjo2Jy9nOb7v1Xg');
        define('HESABE_WC_DEFAULT_STAGING_IV_KEY', '5NVdrlPVNnjo2Jy9');
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Declare compatibility early - before WooCommerce initializes
        add_action('before_woocommerce_init', array($this, 'declare_compatibility'));
        
        // Initialize on plugins_loaded to register gateway early
        add_action('plugins_loaded', array($this, 'init'), 0);
        
        // Load translations on init hook (not plugins_loaded) to avoid early loading warning
        add_action('init', array($this, 'load_translations'));
        
        // Register blocks integration when blocks are loaded (like MyFatoorah)
        add_action('woocommerce_blocks_loaded', array($this, 'woocommerce_blocks_loaded'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Declare compatibility with WooCommerce features
     * This prevents the incompatibility warning
     * Must run on 'before_woocommerce_init' hook
     */
    public function declare_compatibility() {
        // Declare compatibility with WooCommerce features
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            // Compatible with cart and checkout blocks - this is critical for block checkout
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', HESABE_WC_PLUGIN_FILE, true);
            // Compatible with custom order tables (HPOS)
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', HESABE_WC_PLUGIN_FILE, true);
        }
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WC_Payment_Gateway')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Migrate old default description (remove boilerplate text stored in DB)
        $this->maybe_migrate_default_description();

        // Load plugin files
        $this->load_dependencies();
        
        // Register payment gateway
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));
    }
    
    /**
     * Load plugin translations
     * Called on 'init' hook to avoid early loading warning
     */
    public function load_translations() {
        load_plugin_textdomain('hesabe-woocommerce', false, dirname(HESABE_WC_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Remove legacy default description text if it was saved in settings.
     * This prevents showing boilerplate on checkout even after updates.
     *
     * @return void
     */
    private function maybe_migrate_default_description() {
        $option_key = 'woocommerce_hesabe_settings';
        $settings = get_option($option_key);
        if (!is_array($settings)) {
            return;
        }

        if (!isset($settings['description']) || !is_string($settings['description'])) {
            return;
        }

        $legacy = 'The Hesabe payment gateway provider in Kuwait for e-payment through credit card & debit card';
        $desc = trim($settings['description']);

        // If the stored value is the legacy default (exact) or contains it, clear it.
        if ($desc === $legacy || (strpos($desc, $legacy) !== false)) {
            $settings['description'] = '';
            update_option($option_key, $settings);
        }
    }
    
    /**
     * Register blocks integration when WooCommerce Blocks is loaded
     * This matches the MyFatoorah pattern - wait for blocks to be fully loaded
     */
    public function woocommerce_blocks_loaded() {
        if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            // Load blocks support class
            require_once HESABE_WC_PLUGIN_DIR . 'includes/class-wc-hesabe-blocks-support.php';
            
            // Register the payment method - match MyFatoorah pattern exactly
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    // Avoid double registration if already added
                    if (!method_exists($payment_method_registry, 'has_payment_method_type') ||
                        (method_exists($payment_method_registry, 'has_payment_method_type') && !$payment_method_registry->has_payment_method_type('hesabe'))) {
                        $payment_method_registry->register(new WC_Hesabe_Blocks_Support());
                    }
                }
            );
        }
    }

    /**
     * Register Blocks payment method (callback for woocommerce_blocks_payment_method_type_registration)
     *
     * @param \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry
     * @return void
     */
    public function register_block_payment_method($payment_method_registry) {
        if (!class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
            return;
        }

        // Load blocks support class if not already loaded
        if (!class_exists('WC_Hesabe_Blocks_Support')) {
            require_once HESABE_WC_PLUGIN_DIR . 'includes/class-wc-hesabe-blocks-support.php';
        }

        if (class_exists('WC_Hesabe_Blocks_Support')) {
            // Avoid double registration if already added
            if (!method_exists($payment_method_registry, 'has_payment_method_type') ||
                (method_exists($payment_method_registry, 'has_payment_method_type') && !$payment_method_registry->has_payment_method_type('hesabe'))) {
                $payment_method_registry->register(new WC_Hesabe_Blocks_Support());
            }
        }
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once HESABE_WC_PLUGIN_DIR . 'includes/class-wc-hesabe-crypt.php';
        require_once HESABE_WC_PLUGIN_DIR . 'includes/class-wc-hesabe-gateway.php';
    }
    
    /**
     * Add payment gateway to WooCommerce
     */
    public function add_gateway($gateways) {
        $gateways[] = 'WC_Hesabe_Gateway';
        return $gateways;
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . esc_html__('Hesabe Payment Gateway', 'hesabe-woocommerce') . '</strong> ' . esc_html__('requires WooCommerce to be installed and active.', 'hesabe-woocommerce') . '</p></div>';
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Add any activation tasks here
        if (!class_exists('WC_Payment_Gateway')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(esc_html__('Hesabe Payment Gateway requires WooCommerce to be installed and active.', 'hesabe-woocommerce'));
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Add any deactivation tasks here
    }
}

/**
 * Initialize the plugin
 */
function hesabe_woocommerce_init() {
    return Hesabe_WooCommerce_Payment_Gateway::get_instance();
}

// Start the plugin
hesabe_woocommerce_init();

