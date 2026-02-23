<?php
/**
 * WooCommerce Hesabe Payment Gateway
 *
 * Main gateway class for Hesabe payment processing
 * Based on Hesabe PHP Kit integration pattern
 *
 * @package Hesabe_WooCommerce
 * @since 6.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WC_Hesabe_Gateway Class
 */
class WC_Hesabe_Gateway extends WC_Payment_Gateway {

    /**
     * Environment mode (staging or live)
     *
     * @var string
     */
    public $environment_mode;

    /**
     * Merchant code
     *
     * @var string
     */
    public $merchant_code;

    /**
     * Access code
     *
     * @var string
     */
    public $access_code;

    /**
     * Secret key
     *
     * @var string
     */
    public $secret_key;

    /**
     * IV key
     *
     * @var string
     */
    public $iv_key;

    /**
     * API URL
     *
     * @var string
     */
    public $api_url;

    /**
     * Notify URL for callbacks
     *
     * @var string
     */
    public $notify_url;

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize properties with default values
        $this->environment_mode = 'staging';
        $this->merchant_code = '';
        $this->access_code = '';
        $this->secret_key = '';
        $this->iv_key = '';
        $this->api_url = HESABE_WC_STAGING_URL;
        $this->notify_url = '';

        $this->id = 'hesabe';
        $this->icon = HESABE_WC_PLUGIN_URL . 'assets/images/hesabe-new.png';
        $this->has_fields = true;
        $this->method_title = __('Hesabe Payment Gateway', 'hesabe-woocommerce');
        $this->method_description = __('Accept payments via Hesabe payment gateway. Supports KNET, Visa/Mastercard, AMEX, and Apple Pay.', 'hesabe-woocommerce');
        
        // Supported features
        $this->supports = array(
            'products',
            'refunds',
        );

        // Initialize form fields and settings
        $this->init_form_fields();
        $this->init_settings();

        // Load settings
        $this->title = $this->get_option('title', __('Hesabe Payments', 'hesabe-woocommerce'));
        // Default to empty description (avoid showing boilerplate text on checkout)
        $this->description = $this->get_option('description', '');

        // Back-compat: if an older default description is present, suppress it at runtime
        if ($this->description === 'The Hesabe payment gateway provider in Kuwait for e-payment through credit card & debit card') {
            $this->description = '';
        }
        $this->enabled = $this->get_option('enabled', 'no');
        
        // Load environment and credentials
        $this->load_environment_settings();
        
        // Set API URL based on environment
        $this->api_url = ($this->environment_mode === 'staging') ? HESABE_WC_STAGING_URL : HESABE_WC_LIVE_URL;
        $this->notify_url = home_url('/wc-api/wc_hesabe');

        // Ensure credentials are set if using defaults
        if ($this->environment_mode === 'staging' && $this->get_option('use_default_staging', 'yes') === 'yes') {
            $this->merchant_code = HESABE_WC_DEFAULT_STAGING_MERCHANT_CODE;
            $this->access_code = HESABE_WC_DEFAULT_STAGING_ACCESS_CODE;
            $this->secret_key = HESABE_WC_DEFAULT_STAGING_SECRET_KEY;
            $this->iv_key = HESABE_WC_DEFAULT_STAGING_IV_KEY;
        }

        // Hooks
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_wc_hesabe', array($this, 'check_hesabe_response'));
        add_action('woocommerce_receipt_hesabe', array($this, 'receipt_page'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_styles'));
        
        // Block support - must be registered for WooCommerce Blocks
        add_filter('woocommerce_blocks_payment_gateway_supports', array($this, 'add_block_support'), 10, 2);
    }

    /**
     * Load environment settings and credentials
     */
    private function load_environment_settings() {
        $this->environment_mode = $this->get_option('environment_mode', 'staging');
        $use_default_staging = $this->get_option('use_default_staging', 'yes');

        if ($this->environment_mode === 'staging') {
            if ($use_default_staging === 'yes') {
                // Use default staging credentials
                $this->merchant_code = HESABE_WC_DEFAULT_STAGING_MERCHANT_CODE;
                $this->access_code = HESABE_WC_DEFAULT_STAGING_ACCESS_CODE;
                $this->secret_key = HESABE_WC_DEFAULT_STAGING_SECRET_KEY;
                $this->iv_key = HESABE_WC_DEFAULT_STAGING_IV_KEY;
            } else {
                // Use custom staging credentials
                $this->merchant_code = $this->get_option('staging_merchantCode', '');
                $this->access_code = $this->get_option('staging_accessCode', '');
                $this->secret_key = $this->get_option('staging_secretKey', '');
                $this->iv_key = $this->get_option('staging_ivKey', '');
            }
        } else {
            // Live mode credentials
            $this->merchant_code = $this->get_option('live_merchantCode', '');
            $this->access_code = $this->get_option('live_accessCode', '');
            $this->secret_key = $this->get_option('live_secretKey', '');
            $this->iv_key = $this->get_option('live_ivKey', '');
        }
    }

    /**
     * Initialize form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'hesabe-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Hesabe Payment Gateway', 'hesabe-woocommerce'),
                'default' => 'no'
            ),

            // Environment Mode Toggle
            'environment_mode' => array(
                'title' => __('Environment Mode', 'hesabe-woocommerce'),
                'type' => 'select',
                'options' => array(
                    'staging' => __('Staging (Sandbox)', 'hesabe-woocommerce'),
                    'live' => __('Live (Production)', 'hesabe-woocommerce')
                ),
                'default' => 'staging',
                'description' => __('Select the environment. Use Staging for development and testing.', 'hesabe-woocommerce'),
                'class' => 'hesabe-environment-toggle'
            ),

            // Staging Mode Section
            'staging_section_title' => array(
                'title' => __('Staging Credentials', 'hesabe-woocommerce'),
                'type' => 'title',
                'description' => __('Configure sandbox/staging environment credentials.', 'hesabe-woocommerce'),
                'class' => 'hesabe-staging-credentials'
            ),

            'use_default_staging' => array(
                'title' => __('Use Default Test Credentials', 'hesabe-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Use Hesabe default sandbox credentials', 'hesabe-woocommerce'),
                'default' => 'yes',
                'description' => __('Check this to use pre-configured test credentials. Uncheck to use custom staging credentials.', 'hesabe-woocommerce'),
                'class' => 'hesabe-staging-credentials'
            ),

            'staging_merchantCode' => array(
                'title' => __('Staging Merchant Code', 'hesabe-woocommerce'),
                'type' => 'text',
                'description' => __('Enter your staging merchant code. Default: 842217', 'hesabe-woocommerce'),
                'default' => HESABE_WC_DEFAULT_STAGING_MERCHANT_CODE,
                'class' => 'hesabe-staging-credentials hesabe-custom-staging-credentials'
            ),

            'staging_accessCode' => array(
                'title' => __('Staging Access Code', 'hesabe-woocommerce'),
                'type' => 'text',
                'description' => __('Enter your staging access code.', 'hesabe-woocommerce'),
                'default' => HESABE_WC_DEFAULT_STAGING_ACCESS_CODE,
                'class' => 'hesabe-staging-credentials hesabe-custom-staging-credentials'
            ),

            'staging_secretKey' => array(
                'title' => __('Staging Secret Key', 'hesabe-woocommerce'),
                'type' => 'password',
                'description' => __('Enter your staging secret key.', 'hesabe-woocommerce'),
                'default' => HESABE_WC_DEFAULT_STAGING_SECRET_KEY,
                'class' => 'hesabe-staging-credentials hesabe-custom-staging-credentials'
            ),

            'staging_ivKey' => array(
                'title' => __('Staging IV Key', 'hesabe-woocommerce'),
                'type' => 'text',
                'description' => __('Enter your staging IV key.', 'hesabe-woocommerce'),
                'default' => HESABE_WC_DEFAULT_STAGING_IV_KEY,
                'class' => 'hesabe-staging-credentials hesabe-custom-staging-credentials'
            ),

            // Live Mode Section
            'live_section_title' => array(
                'title' => __('Live Credentials', 'hesabe-woocommerce'),
                'type' => 'title',
                'description' => sprintf(
                    __('Configure production environment credentials. Get these from your merchant panel: %s', 'hesabe-woocommerce'),
                    '<a href="https://merchant.hesabe.com" target="_blank">https://merchant.hesabe.com</a>'
                ),
                'class' => 'hesabe-live-credentials'
            ),

            'live_merchantCode' => array(
                'title' => __('Live Merchant Code', 'hesabe-woocommerce'),
                'type' => 'text',
                'description' => __('Enter your production merchant code from merchant panel.', 'hesabe-woocommerce'),
                'class' => 'hesabe-live-credentials'
            ),

            'live_accessCode' => array(
                'title' => __('Live Access Code', 'hesabe-woocommerce'),
                'type' => 'text',
                'description' => __('Enter your production access code from merchant panel.', 'hesabe-woocommerce'),
                'class' => 'hesabe-live-credentials'
            ),

            'live_secretKey' => array(
                'title' => __('Live Secret Key', 'hesabe-woocommerce'),
                'type' => 'password',
                'description' => __('Enter your production secret key from merchant panel.', 'hesabe-woocommerce'),
                'class' => 'hesabe-live-credentials'
            ),

            'live_ivKey' => array(
                'title' => __('Live IV Key', 'hesabe-woocommerce'),
                'type' => 'text',
                'description' => __('Enter your production IV key from merchant panel.', 'hesabe-woocommerce'),
                'class' => 'hesabe-live-credentials'
            ),

            // Pay on Hesabe (Indirect) - when enabled, user selects on Hesabe landing page
            'pay_on_hesabe' => array(
                'title' => __('Pay on Hesabe', 'hesabe-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Pay on Hesabe (redirect to Hesabe payment page)', 'hesabe-woocommerce'),
                'default' => 'yes',
                'description' => __('When enabled, the customer will be redirected to Hesabe landing payment page to choose a payment method. When disabled, the customer chooses a payment method on your checkout page.', 'hesabe-woocommerce')
            ),

            // Payment Methods (used when Pay on Hesabe is disabled)
            'direct_knet' => array(
                'title' => __('KNET', 'hesabe-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable KNET', 'hesabe-woocommerce'),
                'default' => 'no',
                'class' => 'direct-toggle'
            ),

            'direct_visa_mastercard' => array(
                'title' => __('Visa/Mastercard', 'hesabe-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Visa/Mastercard', 'hesabe-woocommerce'),
                'default' => 'no',
                'class' => 'direct-toggle'
            ),

            'direct_amex' => array(
                'title' => __('AMEX', 'hesabe-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable AMEX', 'hesabe-woocommerce'),
                'default' => 'no',
                'class' => 'direct-toggle'
            ),

            'direct_applepay' => array(
                'title' => __('Apple Pay', 'hesabe-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Apple Pay', 'hesabe-woocommerce'),
                'default' => 'no',
                'class' => 'direct-toggle'
            ),

            'direct_applepay_knet' => array(
                'title' => __('Apple Pay (KNET)', 'hesabe-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Apple Pay (KNET)', 'hesabe-woocommerce'),
                'default' => 'no',
                'class' => 'direct-toggle'
            ),

            'currencyConvert' => array(
                'title' => __('Enable Currency Converter', 'hesabe-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Hesabe Currency Converter', 'hesabe-woocommerce'),
                'default' => 'no',
                'description' => __('Enable this if you want to process payments in currencies other than KWD.', 'hesabe-woocommerce')
            ),

            'title' => array(
                'title' => __('Title', 'hesabe-woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'hesabe-woocommerce'),
                'default' => __('Hesabe Payments', 'hesabe-woocommerce')
            ),

            'description' => array(
                'title' => __('Description', 'hesabe-woocommerce'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'hesabe-woocommerce'),
                'default' => ''
            ),
        );
    }

    /**
     * Admin options
     */
    public function admin_options() {
        echo '<h3>' . esc_html__('Hesabe Payment Gateway', 'hesabe-woocommerce') . '</h3>';
        echo '<p>' . esc_html__('Kuwait online payment solutions for all your transactions by Hesabe', 'hesabe-woocommerce') . '</p>';
        
        // Environment indicator
        $env_mode = $this->get_option('environment_mode', 'staging');
        $env_class = ($env_mode === 'staging') ? 'staging' : 'live';
        $env_text = ($env_mode === 'staging') ? __('Staging Mode', 'hesabe-woocommerce') : __('Live Mode', 'hesabe-woocommerce');
        echo '<p><span class="hesabe-environment-indicator ' . esc_attr($env_class) . '">' . esc_html($env_text) . '</span></p>';
        
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    public function process_admin_options() {
        $saved = parent::process_admin_options();

        $settings = get_option('woocommerce_hesabe_settings', array());
        if (!is_array($settings)) {
            return $saved;
        }

        // Back-compat: if older "direct" exists and new "pay_on_hesabe" not set, migrate it
        if (!isset($settings['pay_on_hesabe']) && isset($settings['direct'])) {
            $settings['pay_on_hesabe'] = ($settings['direct'] === 'yes') ? 'no' : 'yes';
        }

        // If Pay on Hesabe is enabled, force all method toggles off (since selection happens on Hesabe page)
        $pay_on_hesabe = isset($settings['pay_on_hesabe']) ? $settings['pay_on_hesabe'] : 'yes';
        if ($pay_on_hesabe === 'yes') {
            $settings['direct_knet'] = 'no';
            $settings['direct_visa_mastercard'] = 'no';
            $settings['direct_amex'] = 'no';
            $settings['direct_applepay'] = 'no';
            $settings['direct_applepay_knet'] = 'no';
        }

        update_option('woocommerce_hesabe_settings', $settings);
        return $saved;
    }

    /**
     * Check if the gateway is available for use
     * This method is called by WooCommerce to determine if the gateway should appear
     * Simplified version - matches MyFatoorah pattern
     *
     * @return bool
     */
    public function is_available() {
        // Check if gateway is enabled
        if ($this->enabled !== 'yes') {
            return false;
        }

        // Reload environment settings to ensure credentials are current
        $this->load_environment_settings();
        $this->api_url = ($this->environment_mode === 'staging') ? HESABE_WC_STAGING_URL : HESABE_WC_LIVE_URL;

        // Ensure credentials are set if using defaults
        if ($this->environment_mode === 'staging' && $this->get_option('use_default_staging', 'yes') === 'yes') {
            $this->merchant_code = HESABE_WC_DEFAULT_STAGING_MERCHANT_CODE;
            $this->access_code = HESABE_WC_DEFAULT_STAGING_ACCESS_CODE;
            $this->secret_key = HESABE_WC_DEFAULT_STAGING_SECRET_KEY;
            $this->iv_key = HESABE_WC_DEFAULT_STAGING_IV_KEY;
        }

        // Check if credentials are set
        if (empty($this->merchant_code) || empty($this->access_code) || empty($this->secret_key) || empty($this->iv_key)) {
            return false;
        }

        // Return true if enabled and credentials are set
        // WooCommerce will handle the rest
        return true;
    }

    /**
     * Payment fields
     */
    public function payment_fields() {
        $desc = is_string($this->description) ? $this->description : '';
        $legacy = 'The Hesabe payment gateway provider in Kuwait for e-payment through credit card & debit card';
        if ($desc && strpos($desc, $legacy) === false) {
            echo '<p>' . wp_kses_post($desc) . '</p>';
        }

        // Pay on Hesabe enabled => selection happens on Hesabe page
        $pay_on_hesabe = $this->get_option('pay_on_hesabe', null);
        if ($pay_on_hesabe === null || $pay_on_hesabe === '') {
            // Back-compat with older "direct" setting
            $old_direct = $this->get_option('direct', 'no');
            $pay_on_hesabe = ($old_direct === 'yes') ? 'no' : 'yes';
        }
        $pay_on_hesabe_enabled = ($pay_on_hesabe === 'yes');

        if (!$pay_on_hesabe_enabled) {
            $this->render_payment_method_selection();
        } else {
            // When direct is disabled, show message that payment method will be selected on Hesabe page
            echo '<p class="hesabe-payment-info">' . esc_html__('You will be able to select your preferred payment method on the Hesabe payment page.', 'hesabe-woocommerce') . '</p>';
        }
    }

    /**
     * Render payment method selection
     */
    private function render_payment_method_selection() {
        // Get enabled payment methods
        $enabled_methods = array();
        
        if ($this->get_option('direct_knet') === 'yes') {
            $enabled_methods[] = array('type' => '1', 'label' => 'KNET', 'image' => 'knet.png');
        }
        if ($this->get_option('direct_visa_mastercard') === 'yes') {
            $enabled_methods[] = array('type' => '2', 'label' => 'Visa/Mastercard', 'image' => 'mastervisa.png');
        }
        if ($this->get_option('direct_amex') === 'yes') {
            $enabled_methods[] = array('type' => '7', 'label' => 'AMEX', 'image' => 'amex_new.png');
        }
        if ($this->get_option('direct_applepay_knet') === 'yes') {
            $enabled_methods[] = array('type' => '11', 'label' => 'Apple Pay (KNET)', 'image' => 'apple.png');
        }
        if ($this->get_option('direct_applepay') === 'yes') {
            $enabled_methods[] = array('type' => '9', 'label' => 'Apple Pay', 'image' => 'apple.png');
        }

        if (empty($enabled_methods)) {
            echo '<p class="hesabe-payment-warning">' . esc_html__('No payment methods are enabled. Please enable at least one payment method in the gateway settings.', 'hesabe-woocommerce') . '</p>';
            return;
        }

        // Output styles
        echo '<style>
            .hesabe-payment-methods {
                margin: 15px 0;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #f9f9f9;
            }
            .hesabe-payment-methods-title {
                font-weight: bold;
                margin-bottom: 10px;
                color: #333;
            }
            .hesabe-payment-option {
                margin: 8px 0;
                padding: 10px;
                border: 2px solid #e0e0e0;
                border-radius: 4px;
                background: #fff;
                transition: all 0.3s ease;
            }
            .hesabe-payment-option:hover {
                border-color: #0073aa;
                background: #f0f8ff;
            }
            .hesabe-payment-option label {
                display: flex;
                align-items: center;
                cursor: pointer;
                margin: 0;
            }
            .hesabe-payment-option input[type="radio"] {
                margin-right: 12px;
                width: 18px;
                height: 18px;
                cursor: pointer;
            }
            .hesabe-payment-option img {
                max-height: 35px;
                max-width: 50px;
                margin-right: 12px;
                object-fit: contain;
            }
            .hesabe-payment-option input[type="radio"]:checked ~ span {
                font-weight: bold;
                color: #0073aa;
            }
            .hesabe-payment-option.selected {
                border-color: #0073aa;
                background: #e6f2ff;
            }
            .hesabe-payment-option input[type="radio"]:checked {
                accent-color: #0073aa;
            }
            .hesabe-payment-option.hidden {
                display: none;
            }
            .hesabe-payment-warning {
                color: #d63638;
                padding: 10px;
                background: #fff3cd;
                border-left: 4px solid #ffc107;
            }
            .hesabe-payment-info {
                color: #666;
                font-style: italic;
            }
            .hesabe-payment-error {
                color: #d63638;
                margin-top: 10px;
                display: none;
            }
        </style>';

        echo '<div class="hesabe-payment-methods">';
        echo '<div class="hesabe-payment-methods-title">' . esc_html__('Select Payment Method:', 'hesabe-woocommerce') . '</div>';

        foreach ($enabled_methods as $method) {
            $class = '';

            $img_src = HESABE_WC_PLUGIN_URL . 'assets/images/' . $method['image'];

            echo '<div class="hesabe-payment-option ' . esc_attr($class) . '">';
            echo '<label>';
            echo '<input type="radio" name="hesabe_payment_option" value="' . esc_attr($method['type']) . '" required />';
            echo '<img src="' . esc_url($img_src) . '" alt="' . esc_attr($method['label']) . '" />';
            echo '<span>' . esc_html($method['label']) . '</span>';
            echo '</label>';
            echo '</div>';
        }

        echo '</div>';

        // Hidden field to store selected payment type
        echo '<input type="hidden" id="hesabe_selected_payment_type" name="hesabe_selected_payment_type" value="0">';
        echo '<div class="hesabe-payment-error" id="hesabe_payment_error">' . esc_html__('Please select a payment method to continue.', 'hesabe-woocommerce') . '</div>';

        // JavaScript to handle payment method selection and validation
        echo '<script>
            (function($) {
                $(document).ready(function() {
                    var $paymentOptions = $("input[name=\'hesabe_payment_option\']");
                    var $selectedField = $("#hesabe_selected_payment_type");
                    var $errorMsg = $("#hesabe_payment_error");
                    
                    // Initialize
                    $paymentOptions.prop("checked", false);
                    $selectedField.val("0");
                    
                    // Handle selection change
                    $paymentOptions.on("change", function() {
                        var selectedValue = $(this).val();
                        $selectedField.val(selectedValue);
                        $errorMsg.hide();
                        
                        // Update visual feedback
                        $(".hesabe-payment-option").removeClass("selected");
                        $(this).closest(".hesabe-payment-option").addClass("selected");
                    });
                    
                    // Validate before form submission (for classic checkout)
                    if ($("form.checkout").length) {
                        $("form.checkout").on("checkout_place_order", function() {
                            if ($selectedField.val() === "0" || $selectedField.val() === "") {
                                $errorMsg.show();
                                return false;
                            }
                        });
                    }
                    
                    // For block checkout, validate on payment method selection
                    $(document.body).on("change", "input[name=\'hesabe_payment_option\']", function() {
                        $selectedField.val($(this).val());
                    });
                });
            })(jQuery);
        </script>';
    }

    /**
     * Process payment
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(__('Order not found.', 'hesabe-woocommerce'), 'error');
            return false;
        }

        // Pay on Hesabe (Indirect) enabled => paymentType = 0
        $pay_on_hesabe = $this->get_option('pay_on_hesabe', null);
        if ($pay_on_hesabe === null || $pay_on_hesabe === '') {
            // Back-compat with older "direct" setting
            $old_direct = $this->get_option('direct', 'no');
            $pay_on_hesabe = ($old_direct === 'yes') ? 'no' : 'yes';
        }
        $pay_on_hesabe_enabled = ($pay_on_hesabe === 'yes');
        
        if (!$pay_on_hesabe_enabled) {
            // Direct payment enabled - validate that a payment method was selected
            $selected_payment_type = isset($_POST['hesabe_selected_payment_type']) ? 
                sanitize_text_field($_POST['hesabe_selected_payment_type']) : '0';

            // Validate selection
            if ($selected_payment_type === '0' || empty($selected_payment_type)) {
                wc_add_notice(__('Please select a payment method to continue.', 'hesabe-woocommerce'), 'error');
                return array(
                    'result' => 'fail',
                    'redirect' => wc_get_checkout_url()
                );
            }

            // Validate that the selected payment method is enabled in settings
            $enabled_map = array(
                '1' => ($this->get_option('direct_knet', 'no') === 'yes'),
                '2' => ($this->get_option('direct_visa_mastercard', 'no') === 'yes'),
                '7' => ($this->get_option('direct_amex', 'no') === 'yes'),
                '9' => ($this->get_option('direct_applepay', 'no') === 'yes'),
                '11' => ($this->get_option('direct_applepay_knet', 'no') === 'yes'),
            );
            if (!isset($enabled_map[$selected_payment_type]) || !$enabled_map[$selected_payment_type]) {
                wc_add_notice(__('Invalid payment method selected.', 'hesabe-woocommerce'), 'error');
                return array(
                    'result' => 'fail',
                    'redirect' => wc_get_checkout_url()
                );
            }

            // Save payment type to order meta
            update_post_meta($order_id, '_hesabe_payment_type', $selected_payment_type);
            $order->add_meta_data('_hesabe_payment_type', $selected_payment_type, true);
            $order->save();
        } else {
            // Pay on Hesabe enabled - customer selects on Hesabe landing page
            update_post_meta($order_id, '_hesabe_payment_type', '0');
            $order->add_meta_data('_hesabe_payment_type', '0', true);
            $order->save();
        }

        // Redirect to receipt page
        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }

    /**
     * Receipt page
     */
    public function receipt_page($order_id) {
        echo '<p>' . esc_html__('Thank you for your order. Please click the button below to proceed with payment.', 'hesabe-woocommerce') . '</p>';
        echo $this->generate_hesabe_form($order_id);
    }

    /**
     * Generate Hesabe payment form
     * Based on PHP kit PaymentHandler::checkoutRequest() pattern
     */
    public function generate_hesabe_form($order_id) {
        $order = wc_get_order($order_id);

        if (!$order) {
            return '<p>' . esc_html__('Order not found.', 'hesabe-woocommerce') . '</p>';
        }

        // Get order data
        $order_data = $order->get_data();
        
        // Get payment type from order meta (try both methods for compatibility)
        $payment_type = $order->get_meta('_hesabe_payment_type', true);
        if (empty($payment_type)) {
            $payment_type = get_post_meta($order_id, '_hesabe_payment_type', true);
        }
        
        // Default to '0' if not set (customer selects on Hesabe page)
        if (empty($payment_type)) {
            $payment_type = '0';
        }
        
        // Check if direct payment is enabled
        $direct_enabled = $this->get_option('direct', 'no');
        if ($direct_enabled !== 'yes') {
            // Force to 0 if direct payment is disabled
            $payment_type = '0';
        }
        
        $billing_first_name = $order_data['billing']['first_name'] ?? '';
        $billing_last_name = $order_data['billing']['last_name'] ?? '';
        $billing_phone = $order_data['billing']['phone'] ?? '';
        $billing_email = $order_data['billing']['email'] ?? '';
        
        $name = htmlspecialchars(trim($billing_first_name . ' ' . $billing_last_name), ENT_QUOTES, 'UTF-8');
        $name = $this->utf8_substr($name, 0, 50);
        
        $order_amount = number_format((float)$order->get_total(), 3, '.', '');
        $phone_clean = preg_replace('/[^0-9]/', '', $billing_phone);
        $email_clean = $this->utf8_substr($billing_email, 0, 100);

        // Build request payload - following PHP kit HesabeCheckoutRequestModel pattern
        $post_values = array(
            'merchantCode' => $this->merchant_code,
            'amount' => $order_amount,
            'paymentType' => $payment_type,
            'orderReferenceNumber' => (string)$order->get_id(),
            'version' => '2.0',
            'responseUrl' => $this->notify_url,
            'failureUrl' => $this->notify_url,
            'variable1' => (string)$order_id,
            'variable2' => $order_data['version'] ?? '0',
            'variable3' => $name,
            'variable4' => $phone_clean,
            'variable5' => $email_clean,
        );

        // Add currency if converter is enabled and not KWD
        if ($this->get_option('currencyConvert') === 'yes' && $order->get_currency() !== 'KWD') {
            $post_values['currency'] = $order->get_currency();
        }

        // Encrypt data - following PHP kit pattern
        $requestDataJson = json_encode($post_values);
        $encrypted_data = WC_Hesabe_Crypt::encrypt($requestDataJson, $this->secret_key, $this->iv_key);

        if ($encrypted_data === false) {
            $order->add_order_note(__('Error: Failed to encrypt payment data.', 'hesabe-woocommerce'));
            return '<p>' . esc_html__('Payment processing error. Please try again.', 'hesabe-woocommerce') . '</p>';
        }

        // Make API call - following PHP kit PaymentHandler::checkoutRequest() pattern
        $checkout_url = $this->api_url . '/checkout';
        $post_fields = array('data' => $encrypted_data);
        
        $headers = array(
            'accessCode: ' . $this->access_code,
            'Accept: application/json',
        );

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $checkout_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $post_fields,
            CURLOPT_HTTPHEADER => $headers,
        ));

        $response = curl_exec($curl);
        $curl_error = curl_errno($curl);
        $curl_error_msg = curl_error($curl);
        curl_close($curl);

        if ($curl_error) {
            $order->add_order_note(sprintf(__('CURL Error: %s', 'hesabe-woocommerce'), $curl_error_msg));
            return '<p>' . esc_html__('Payment gateway connection error. Please try again later.', 'hesabe-woocommerce') . '</p>';
        }

        // Decrypt response - following PHP kit pattern
        $decrypted_response = WC_Hesabe_Crypt::decrypt($response, $this->secret_key, $this->iv_key);

        if ($decrypted_response === false) {
            $order->add_order_note(__('Error: Failed to decrypt payment response.', 'hesabe-woocommerce'));
            return '<p>' . esc_html__('Payment processing error. Please try again.', 'hesabe-woocommerce') . '</p>';
        }

        $decode_response = json_decode($decrypted_response, true);

        // Validate response - following PHP kit pattern
        if (!$decode_response || !isset($decode_response['status']) || !isset($decode_response['response']['data'])) {
            $error_code = isset($decode_response['code']) ? $decode_response['code'] : 'Unknown';
            $error_message = isset($decode_response['message']) ? $decode_response['message'] : __('No additional details available.', 'hesabe-woocommerce');
            $response_message = sprintf(
                __('We cannot complete the order at this moment. Please try again later or contact support. Error Code: %s Details: %s', 'hesabe-woocommerce'),
                $error_code,
                $error_message
            );
            $order->add_order_note($response_message);
            return '<p>' . esc_html($response_message) . '</p>';
        }

        $payment_data = $decode_response['response']['data'];
        
        // Handle Apple Pay wallet flow ONLY on Safari.
        // On non-Safari browsers, Hesabe landing page will show QR/barcode flow.
        if (in_array($payment_type, array('9', '11'), true)) {
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
            $is_safari = (strpos($ua, 'Safari') !== false && strpos($ua, 'Chrome') === false && strpos($ua, 'Chromium') === false);
            if ($is_safari) {
                $this->handle_apple_pay($payment_data, $order);
            }
        }

        // Redirect to payment page - following PHP kit PaymentController::redirectToPayment() pattern
        wp_redirect($this->api_url . '/payment?data=' . urlencode($payment_data));
        exit;
    }

    /**
     * Handle Apple Pay
     */
    private function handle_apple_pay($payment_data, $order) {
        echo '<script type="text/javascript">
            jQuery(function($) {
                var applePayScript = document.createElement("script");
                applePayScript.src = "https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js";
                document.body.appendChild(applePayScript);
                
                $.get("' . esc_js($this->api_url) . '/applepay?data=' . esc_js($payment_data) . '", function(response) {
                    var scriptContent = response.replace(/<\/?script>/g, "");
                    var inlineScript = document.createElement("script");
                    inlineScript.type = "text/javascript";
                    inlineScript.text = scriptContent;
                    document.body.appendChild(inlineScript);
                });
            });
        </script>';
    }

    /**
     * Check Hesabe response
     * Based on PHP kit PaymentController::getPaymentResponse() pattern
     */
    public function check_hesabe_response() {
        global $woocommerce;

        $msg = array(
            'class' => 'error',
            'message' => __('This transaction has been declined. Please attempt your purchase again.', 'hesabe-woocommerce')
        );

        if (!isset($_REQUEST['data'])) {
            wc_add_notice($msg['message'], $msg['class']);
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $response_data = sanitize_text_field($_REQUEST['data']);
        
        // Decrypt response - following PHP kit pattern
        $decrypted_response = WC_Hesabe_Crypt::decrypt($response_data, $this->secret_key, $this->iv_key);

        if ($decrypted_response === false) {
            wc_add_notice(__('Invalid payment response. Please contact support.', 'hesabe-woocommerce'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $json_decode = json_decode($decrypted_response, true);

        if (!isset($json_decode['response'])) {
            wc_add_notice($msg['message'], $msg['class']);
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        // Parse response - following PHP kit HesabePaymentResponseModel pattern
        $order_info = $json_decode['response'];
        $order_id = isset($order_info['variable1']) ? intval($order_info['variable1']) : 0;

        if ($order_id <= 0) {
            wc_add_notice($msg['message'], $msg['class']);
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice($msg['message'], $msg['class']);
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        $order_status = isset($order_info['resultCode']) ? $order_info['resultCode'] : '';
        $order->add_order_note(sprintf(__('Hesabe Response - Status: %s, Amount: %s', 'hesabe-woocommerce'), $order_status, isset($order_info['amount']) ? $order_info['amount'] : ''));

        // Success status codes - following PHP kit pattern
        $success_statuses = array('CAPTURED', 'ACCEPT', 'AUTHORIZED', 'PARTIALLY_CAPTURED', 'SUCCESS');

        if (isset($json_decode['status']) && $json_decode['status'] === true && in_array($order_status, $success_statuses)) {
            $msg['message'] = __('Thank you for shopping with us. Your account has been charged and your transaction is successful.', 'hesabe-woocommerce');
            $msg['class'] = 'success';

            if ($order->get_status() !== 'processing' && $order->get_status() !== 'completed') {
                $order->payment_complete();
                $payment_id = isset($order_info['paymentId']) ? $order_info['paymentId'] : '';
                $payment_token = isset($order_info['paymentToken']) ? $order_info['paymentToken'] : '';
                $paid_on = isset($order_info['paidOn']) ? $order_info['paidOn'] : '';
                $amount = isset($order_info['amount']) ? $order_info['amount'] : '';
                
                $order->add_order_note(sprintf(
                    __('Hesabe payment successful. Payment Ref Number: %s, Payment Token: %s, Paid On: %s, Amount: %s', 'hesabe-woocommerce'),
                    $payment_id,
                    $payment_token,
                    $paid_on,
                    $amount
                ));
                
                WC()->cart->empty_cart();
            }
        } else {
            $order->update_status('failed');
            $payment_id = isset($order_info['paymentId']) ? $order_info['paymentId'] : '';
            $payment_token = isset($order_info['paymentToken']) ? $order_info['paymentToken'] : '';
            $paid_on = isset($order_info['paidOn']) ? $order_info['paidOn'] : '';
            
            $order->add_order_note(sprintf(
                __('Hesabe payment failed. Payment Ref Number: %s, Payment Token: %s, Paid On: %s', 'hesabe-woocommerce'),
                $payment_id,
                $payment_token,
                $paid_on
            ));
            $order->add_order_note($msg['message']);
        }

        wc_add_notice($msg['message'], $msg['class']);

        $redirect_url = $this->get_return_url($order);
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * UTF-8 safe substring
     */
    private function utf8_substr($str, $start, $length) {
        if (function_exists('mb_substr')) {
            return mb_substr($str, $start, $length, 'UTF-8');
        } else {
            preg_match_all('/./u', $str, $matches);
            return implode('', array_slice($matches[0], $start, $length));
        }
    }

    /**
     * Add block support
     * This ensures the gateway is recognized by WooCommerce Blocks
     */
    public function add_block_support($supports, $gateway_id) {
        // Support both 'hesabe' (gateway ID) and the class name for compatibility
        if ($gateway_id === $this->id || $gateway_id === 'hesabe' || strpos($gateway_id, 'hesabe') !== false) {
            if (!in_array('blocks', $supports, true)) {
                $supports[] = 'blocks';
            }
        }
        return $supports;
    }

    /**
     * Enqueue admin scripts
     */
    public function admin_scripts($hook) {
        // Only load on WooCommerce settings page
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }

        // Check if we're on the payment gateways settings page
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
            return;
        }

        // Check if we're on the Hesabe gateway settings
        if (!isset($_GET['section']) || $_GET['section'] !== $this->id) {
            return;
        }

        wp_enqueue_script(
            'hesabe-admin-script',
            HESABE_WC_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            HESABE_WC_VERSION,
            true
        );
    }

    /**
     * Enqueue admin styles
     */
    public function admin_styles($hook) {
        // Only load on WooCommerce settings page
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }

        // Check if we're on the payment gateways settings page
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'checkout') {
            return;
        }

        // Check if we're on the Hesabe gateway settings
        if (!isset($_GET['section']) || $_GET['section'] !== $this->id) {
            return;
        }

        wp_enqueue_style(
            'hesabe-admin-style',
            HESABE_WC_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            HESABE_WC_VERSION
        );
    }
}

