<?php

class WC_Hesabe_Mpgs extends WC_Payment_Gateway
{
    public function __construct()
    {
        // General configuration set
        $this->id = 'hesabe_mpgs';
        $this->method_title = __('MPGS Online Payment');
        $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/mpgs.png';
        $this->has_fields = false;
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $mainSettings = get_option('woocommerce_hesabe_settings');
        $this->merchantcode = (!empty($mainSettings['merchantcode'])) ? $mainSettings['merchantcode'] : '';
        $this->user1 = (!empty($mainSettings['user1'])) ? $mainSettings['user1'] : '';
        $this->secretKey = (!empty($mainSettings['secretKey'])) ? $mainSettings['secretKey'] : '';
        $this->ivKey = (!empty($mainSettings['ivKey'])) ? $mainSettings['ivKey'] : '';
        $this->accessCode = (!empty($mainSettings['accessCode'])) ? $mainSettings['accessCode'] : '';
        $this->sandbox = (!empty($mainSettings['sandbox']) && 'yes' === $mainSettings['sandbox']) ? true : false;

        if ($this->sandbox) {
            $this->apiUrl = WC_HESABE_TEST_URL;
        } else {
            $this->apiUrl = WC_HESABE_LIVE_URL;
        }

        $this->notify_url = home_url('/wc-api/wc_hesabe');

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        } else {
            add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
        }
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
    }


    function init_form_fields()
    {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable'),
                'type' => 'checkbox',
                'label' => __('Enable Hesabe Online Payment Module.'),
                'default' => 'no'),

            'title' => array(
                'title' => __('Title:'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.'),
                'default' => __('MPGS Online Payment via Hesabe')),

            'description' => array(
                'title' => __('Description:'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.'),
                'default' => __('The best payment gateway provider in Kuwait for e-payment through credit card & debit card')),
        );

    }


    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     **/
    public function admin_options()
    {
        echo '<h3>' . __('Hesabe Payment Gateway') . '</h3>';
        echo '<p>' . __('Kuwait online payment solutions for all your transactions by hesabe') . '</p>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';

    }

    /**
     *  There are no payment fields for hesabe, but we want to show the description if set.
     **/
    function payment_fields()
    {
        if ($this->description) echo wpautop(wptexturize($this->description));
    }

    /**
     * Receipt Page
     **/
    function receipt_page($order)
    {
        echo '<p>' . __('Thank you for your order, Your order has initiated for payment!!') . '</p>';
        echo $this->generate_hesabe_form($order);
    }

    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id)
    {
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            $order = new WC_Order($order_id);
        } else {
            $order = new woocommerce_order($order_id);
        }
        return array('result' => 'success', 'redirect' => add_query_arg('order',
            $order->id, add_query_arg('key', $order->order_key, $order->get_checkout_payment_url(true)))
        );
    }

    /**
     * Generate hesabe button link
     **/
    public function generate_hesabe_form($order_id)
    {
        global $woocommerce;
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            $order = new WC_Order($order_id);
        } else {
            $order = new woocommerce_order($order_id);
        }

        $orderAmount = number_format((float)$order->order_total, 3, '.', '');

        $post_values = array(
            "merchantCode" => $this->merchantcode,
            "amount" => $orderAmount,
            "responseUrl" => $this->notify_url,
            "failureUrl" => $this->notify_url,
            "paymentType" => 2,
            "version" => '2.0',
            "orderReferenceNumber" => $order_id,
            "variable1" => $this->user1,
            "variable2" => $order_id
        );
        $post_string = json_encode($post_values);

        $encrypted_post_string = WC_Hesabe_Crypt::encrypt($post_string, $this->secretKey, $this->ivKey);

        $encrypted_post_string = 'data=' . $encrypted_post_string;

        $header = array();
        $header[] = 'accessCode: ' . $this->accessCode;
        $checkOutUrl = $this->apiUrl . '/checkout';

        $curl = curl_init($checkOutUrl);

        if (!$this->sandbox) {
            //curl_setopt($curl, CURLOPT_PORT, 443);
        }

        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 12);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $encrypted_post_string);
        $post_response = curl_exec($curl);
        if ($post_response === false) {
            //echo 'Curl error: ' . curl_error($curl);exit;
        }
        curl_close($curl); // close curl object

        list($responsheader, $responsebody) = explode("\r\n\r\n", $post_response, 2);

        $decrypted_post_response = WC_Hesabe_Crypt::decrypt($responsebody, $this->secretKey, $this->ivKey);

        $decode_response = json_decode($decrypted_post_response);

        if ($decode_response->status != 1 || !(isset($decode_response->response->data))) {
            echo "We can not complete order at this moment";
            exit;
        }
        $paymentData = $decode_response->response->data;
        header('Location:' . $this->apiUrl . '/payment?data=' . $paymentData);
        exit;
    }
}