<?php

class WC_Hesabe extends WC_Payment_Gateway
{
    public $id;
    public $method_title;
    public $icon;
    public $has_fields;
    public $settings;
    public $title;
    public $description;

    public $merchantCode;
    public $sandbox;
    public $secretKey;
    public $ivKey;
    public $accessCode;
    public $currencyConvert;

    public $direct1;
    public $direct2;
    public $direct3;
    public $direct4;
    public $direct5;

    public $apiUrl;
    public $notify_url;

    public $msg = array();

    public function __construct()
    {
        $this->id = 'hesabe';
        $this->method_title = __('Hesabe Online Payment');
        $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(__DIR__) . '/images/hesabe-new.png';
        $this->has_fields = false;
        $this->supports = ['products', 'refunds'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->merchantCode = $this->settings['merchantCode'];
        $this->sandbox = $this->settings['sandbox'];
        $this->secretKey = $this->settings['secretKey'];
        $this->ivKey = $this->settings['ivKey'];
        $this->accessCode = $this->settings['accessCode'];
        $this->currencyConvert = (!empty($this->settings['currencyConvert']) && $this->settings['currencyConvert'] === 'yes');

        $this->direct1 = ($this->settings['direct1'] ?? 'no') === 'yes';
        $this->direct2 = ($this->settings['direct2'] ?? 'no') === 'yes';
        $this->direct3 = ($this->settings['direct3'] ?? 'no') === 'yes';
        $this->direct4 = ($this->settings['direct4'] ?? 'no') === 'yes';
        $this->direct5 = ($this->settings['direct5'] ?? 'no') === 'yes';

        $this->apiUrl = ($this->sandbox === 'yes') ? WC_HESABE_TEST_URL : WC_HESABE_LIVE_URL;
        $this->notify_url = home_url('/wc-api/wc_hesabe');

        // Hooks
        add_action('woocommerce_api_wc_hesabe', [$this, 'check_hesabe_response']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_receipt_hesabe', [$this, 'receipt_page']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_hesabe_payment_subtype']);

        // Admin
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'hesabe_admin_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'hesabe_admin_styles']);

        // AJAX for block checkout
        add_action('wp_ajax_nopriv_hesabe_create_payment', [$this, 'ajax_create_hesabe_payment']);
        add_action('wp_ajax_hesabe_create_payment', [$this, 'ajax_create_hesabe_payment']);
    }

    /**
     * Save selected payment type reliably for both block and classic checkout
     */
    public function save_hesabe_payment_subtype($order_id)
    {
        $order = wc_get_order($order_id);

        if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'hesabe') {

            // Get from hidden input
            $payment_type = $_POST['hesabe_selected_payment_type'] ?? '0';

            // Fallback: if hidden is empty, use radio input
            if (empty($payment_type) || $payment_type === '0') {
                $payment_type = $_POST['payment_option'] ?? '0';
            }

            $payment_type = sanitize_text_field(wp_unslash($payment_type));
            $order->update_meta_data('_hesabe_payment_type', $payment_type);
            $order->save();

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[HESABE] Payment type saved for order ' . $order_id . ': ' . $payment_type);
            }
        }
    }

    /**
     * Process payment
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        // Ensure payment type is captured
        $payment_type = $_POST['hesabe_selected_payment_type'] ?? '0';
        if (empty($payment_type) || $payment_type === '0') {
            $payment_type = $_POST['payment_option'] ?? '0';
        }
        $payment_type = sanitize_text_field(wp_unslash($payment_type));

        $order->update_meta_data('_hesabe_payment_type', $payment_type);
        $order->save();

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[HESABE] Captured payment type in process_payment: ' . $payment_type . ' for order ' . $order_id);
        }

        return [
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        ];
    }

    /**
     * Admin scripts/styles
     */
    public function hesabe_admin_scripts()
    {
        wp_enqueue_script('hesabe-admin-custom', plugins_url('/js/admin-custom.js', __FILE__), ['jquery'], '1.0', true);
    }

    public function hesabe_admin_styles()
    {
        wp_enqueue_style('hesabe-admin-custom', plugins_url('/css/admin-custom.css', __FILE__));
    }

    public function enqueue_admin_scripts($hook)
    {
        if ($hook !== 'woocommerce_page_wc-settings') return;
        wp_enqueue_script('hesabe-settings-script', plugin_dir_url(__FILE__) . 'js/hesabe-settings.js', ['jquery'], '1.0.0', true);
    }

    /**
     * Form fields
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => ['title' => __('Enable/Disable'), 'type' => 'checkbox', 'label' => __('Enable Hesabe Online Payment Module.'), 'default' => 'no'],
            'direct1' => ['title' => __('Knet'), 'type' => 'checkbox', 'label' => __('Enable Knet.'), 'default' => 'no'],
            'direct2' => ['title' => __('Applepay (Knet)'), 'type' => 'checkbox', 'label' => __('Enable Knet Applepay.'), 'default' => 'no'],
            'direct3' => ['title' => __('Visa/Mastercard'), 'type' => 'checkbox', 'label' => __('Enable Visa/Mastercard.'), 'default' => 'no'],
            'direct4' => ['title' => __('Amex'), 'type' => 'checkbox', 'label' => __('Enable Amex.'), 'default' => 'no'],
            'direct5' => ['title' => __('ApplePay'), 'type' => 'checkbox', 'label' => __('Enable Applepay.'), 'default' => 'no'],
            'sandbox' => ['title' => __('Enable Demo?'), 'type' => 'checkbox', 'label' => __('Enable Demo Hesabe OnlinePayment.'), 'default' => 'no'],
            'currencyConvert' => ['title' => __('Enable Currency Converter?'), 'type' => 'checkbox', 'label' => __('Enable Hesabe Online Payment Currency Converter'), 'default' => 'no'],
            'title' => ['title' => __('Title:'), 'type' => 'text', 'default' => __('Hesabe Payments')],
            'description' => ['title' => __('Description:'), 'type' => 'textarea', 'default' => __('Hesabe payment gateway for Kuwait')],
            'merchantCode' => ['title' => __('Merchant Code:'), 'type' => 'text'],
            'accessCode' => ['title' => __('Access Code:'), 'type' => 'text'],
            'secretKey' => ['title' => __('Secret Key:'), 'type' => 'text'],
            'ivKey' => ['title' => __('IV:'), 'type' => 'text']
        ];
    }

    public function admin_options()
    {
        echo '<h3>' . __('Hesabe Payment Gateway') . '</h3>';
        echo '<p>' . __('Kuwait online payment solution by Hesabe.') . '</p>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    /**
     * Receipt Page
     */
    public function receipt_page($order)
    {
        echo '<p>' . __('Thank you for your order, your order is being processed for payment.') . '</p>';
        echo $this->generate_hesabe_form($order);
    }

    /**
     * Generate Hesabe checkout form / redirect
     */
    public function generate_hesabe_form($order_id)
    {
        $order = wc_get_order($order_id);
        $order_data = $order->get_data();
        $payment_type = $order->get_meta('_hesabe_payment_type', true) ?: '0';

        $orderAmount = number_format((float)$order->get_total(), 3, '.', '');
        $order_billing_first_name = $order_data['billing']['first_name'] ?? '';
        $order_billing_last_name  = $order_data['billing']['last_name'] ?? '';
        $order_billing_phone      = $order_data['billing']['phone'] ?? '';
        $order_billing_email      = $order_data['billing']['email'] ?? '';

        $name = htmlspecialchars($order_billing_first_name . " " . $order_billing_last_name, ENT_QUOTES, 'UTF-8');

        $post_values = array(
            "merchantCode" => $this->merchantCode,
            "amount" => $orderAmount,
            "responseUrl" => $this->notify_url,
            "failureUrl" => $this->notify_url,
            "paymentType" => $payment_type,
            "version" => '2.0',
            "orderReferenceNumber" => $order->get_id(),
            "variable1" => $order_id,
            "variable2" => $order_data['version'] ?? 0,
            "variable3" => $this->utf8_substr($name, 0, 50),
            "variable4" => preg_replace('/[^0-9]/', '', $order_billing_phone),
            "variable5" => $this->utf8_substr($order_billing_email, 0, 100),
            "name" => $this->utf8_substr($name, 0, 50),
            "mobile_number" => preg_replace('/[^0-9]/', '', $order_billing_phone),
        );

        if ($this->currencyConvert && $order->get_currency() !== 'KWD') {
            $post_values['currency'] = $order->get_currency();
        }

        $post_string = json_encode($post_values, true);
        $encrypted_post_string = WC_Hesabe_Crypt::encrypt($post_string, $this->secretKey, $this->ivKey);
        $post_fields = http_build_query(['data' => $encrypted_post_string]);

        $headers = [
            'accessCode: ' . $this->accessCode,
            'Accept: application/json',
        ];

        $checkOutUrl = $this->apiUrl . '/checkout';
        $curl = curl_init($checkOutUrl);

        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => $post_fields,
        ]);

        $post_response = curl_exec($curl);
        if (curl_errno($curl)) {
            throw new Exception('CURL Error: ' . curl_error($curl));
        }
        curl_close($curl);

        $decrypted_post_response = WC_Hesabe_Crypt::decrypt($post_response, $this->secretKey, $this->ivKey);
        $decode_response = json_decode($decrypted_post_response, true);

        if (!$decode_response || !isset($decode_response['status']) || !isset($decode_response['response']['data'])) {
            $errorMessage = $decode_response['message'] ?? 'No additional details available.';
            $responseMessage = "Cannot complete the order. Error: " . $errorMessage;
            $order->add_order_note($responseMessage);
            echo $responseMessage;
            exit;
        }

        $paymentData = $decode_response['response']['data'];
        $paymentUrl = $this->apiUrl . '/payment?data=' . $paymentData;

        wp_redirect($paymentUrl);
        exit;
    }

    /**
     * AJAX handler for Blocks checkout
     */
    public function ajax_create_hesabe_payment()
    {
        if (empty($_REQUEST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['nonce'])), 'wc_hesabe_blocks_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce'], 400);
        }

        $order_id = intval($_REQUEST['order_id'] ?? 0);
        $payment_type = sanitize_text_field($_REQUEST['payment_type'] ?? 0);

        if (!$order_id) {
            wp_send_json_error(['message' => 'Missing order id'], 400);
        }

        $order = wc_get_order($order_id);

        try {
            $order_data = $order->get_data();
            $order_billing_first_name = $order_data['billing']['first_name'] ?? '';
            $order_billing_last_name  = $order_data['billing']['last_name'] ?? '';
            $order_billing_phone      = $order_data['billing']['phone'] ?? '';
            $order_billing_email      = $order_data['billing']['email'] ?? '';
            $orderAmount = number_format((float)$order->get_total(), 3, '.', '');

            $name = htmlspecialchars($order_billing_first_name . " " . $order_billing_last_name, ENT_QUOTES, 'UTF-8');

            $post_values = [
                "merchantCode" => $this->merchantCode,
                "amount" => $orderAmount,
                "responseUrl" => $this->notify_url,
                "failureUrl" => $this->notify_url,
                "paymentType" => $payment_type,
                "version" => '2.0',
                "orderReferenceNumber" => $order->get_id(),
                "variable1" => $order_id,
                "variable2" => $order_data['version'] ?? 0,
                "variable3" => $this->utf8_substr($name, 0, 50),
                "variable4" => preg_replace('/[^0-9]/', '', $order_billing_phone),
                "variable5" => $this->utf8_substr($order_billing_email, 0, 100),
                "name" => $this->utf8_substr($name, 0, 50),
                "mobile_number" => preg_replace('/[^0-9]/', '', $order_billing_phone),
            ];

            if ($this->currencyConvert && $order->get_currency() !== 'KWD') {
                $post_values['currency'] = $order->get_currency();
            }

            $post_string = json_encode($post_values, true);
            $encrypted_post_string = WC_Hesabe_Crypt::encrypt($post_string, $this->secretKey, $this->ivKey);
            $post_fields = http_build_query(['data' => $encrypted_post_string]);

            $headers = ['accessCode: ' . $this->accessCode, 'Accept: application/json'];
            $checkOutUrl = $this->apiUrl . '/checkout';

            $curl = curl_init($checkOutUrl);
            curl_setopt_array($curl, [
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_POSTFIELDS => $post_fields,
            ]);

            $post_response = curl_exec($curl);
            if (curl_errno($curl)) {
                wp_send_json_error(['message' => 'CURL Error: ' . curl_error($curl)], 500);
            }
            curl_close($curl);

            $decrypted_post_response = WC_Hesabe_Crypt::decrypt($post_response, $this->secretKey, $this->ivKey);
            $decode_response = json_decode($decrypted_post_response, true);

            if (!$decode_response || !isset($decode_response['status']) || !isset($decode_response['response']['data'])) {
                wp_send_json_error(['message' => 'Invalid response from Hesabe'], 500);
            }

            $paymentData = $decode_response['response']['data'];
            $paymentUrl = $this->apiUrl . '/payment?data=' . $paymentData;

            // Save selected payment type to order meta
            update_post_meta($order_id, '_hesabe_payment_type', $payment_type);

            wp_send_json_success(['payment_url' => $paymentUrl]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * UTF8 substring helper
     */
    private function utf8_substr($str, $start, $length)
    {
        if (function_exists('mb_substr')) {
            return mb_substr($str, $start, $length, 'UTF-8');
        }
        preg_match_all('/./u', $str, $matches);
        return implode('', array_slice($matches[0], $start, $length));
    }
}
