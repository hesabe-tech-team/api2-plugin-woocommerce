<?php

class WC_Hesabe extends WC_Payment_Gateway
{
    public function __construct()
    {
        // construct form //
        // Go wild in here
        $this->id = 'hesabe';
        $this->method_title = __('Hesabe Online Payment');
        $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(__DIR__) . '/images/cards.png';
        $this->has_fields = false;
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->merchantCode = $this->settings['merchantCode'];
        $this->sandbox = $this->settings['sandbox'];
        $this->secretKey = $this->settings['secretKey'];
        $this->ivKey = $this->settings['ivKey'];
        $this->accessCode = $this->settings['accessCode'];
        $this->currencyConvert = (!empty($this->settings['currencyConvert']) && 'yes' === $this->settings['currencyConvert']) ? true : false;

        if ($this->sandbox == 'yes') {
            $this->apiUrl = WC_HESABE_TEST_URL;
        } else {
            $this->apiUrl = WC_HESABE_LIVE_URL;
        }
        $this->notify_url = home_url('/wc-api/wc_hesabe');

        $this->msg['message'] = "";
        $this->msg['class'] = "";

        add_action('woocommerce_api_wc_hesabe', array($this, 'check_hesabe_response'));
        add_action('valid-hesabe-request', array($this, 'successful_request'));
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        } else {
            add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
        }
        add_action('woocommerce_receipt_hesabe', array($this, 'receipt_page'));
    }


    function init_form_fields()
    {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable'),
                'type' => 'checkbox',
                'label' => __('Enable Hesabe Online Payment Module.'),
                'default' => 'no'),

            'sandbox' => array(
                'title' => __('Enable Demo?'),
                'type' => 'checkbox',
                'label' => __('Enable Demo Hesabe OnlinePayment.'),
                'default' => 'no'),

            'currencyConvert' => array(
                'title' => __('Enable Currency Converter?'),
                'type' => 'checkbox',
                'label' => __('Enable Hesabe Online Payment Currency Converter'),
                'default' => 'no'),

            'title' => array(
                'title' => __('Title:'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.'),
                'default' => __('Hesabe Payments')),

            'description' => array(
                'title' => __('Description:'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.'),
                'default' => __('The Hesabe payment gateway provider in Kuwait for e-payment through credit card & debit card')),

            'merchantCode' => array(
                'title' => __('Merchant Code:'),
                'type' => 'text',
                'description' => __('This is Merchant Code.')),

            'accessCode' => array(
                'title' => __('Access Code:'),
                'type' => 'text',
                'description' => __('Access Code'),
            ),
            'secretKey' => array(
                'title' => __('Secret Key:'),
                'type' => 'text',
                'description' => __('Secret Key'),
            ),

            'ivKey' => array(
                'title' => __('IV:'),
                'type' => 'text',
                'description' => __('IV of Secret Key'),
            )
        );
    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     **/
    public function admin_options()
    {
        echo '<h3>' . __('Hesabe Payment Gateway') . '</h3>';
        echo '<p>' . __('Kuwait online payment solutions for all your transactions by Hesabe') . '</p>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';

    }

    /**
     *  There are no payment fields for Hesabe, but we want to show the description if set.
     **/
    function payment_fields()
    {
        if ($this->description) echo wpautop(wptexturize($this->description));
    }


    /**
     * Process the payment and return the result
     **/
    function process_payment($orderId)
    {
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            $order = new WC_Order($orderId);
        } else {
            $order = new woocommerce_order($orderId);
        }
        return array('result' => 'success', 'redirect' => add_query_arg('order',
            $order->id, add_query_arg('key', $order->order_key, $order->get_checkout_payment_url(true)))
        );
    }


    /**
     * Check for valid Hesabe server callback // response processing //
     **/
    function check_hesabe_response()
    {
        global $woocommerce;
        $msg['class'] = 'error';
        $msg['message'] = "This transaction has been declined. Please attempt your purchase again.";
        $responseData = $_REQUEST['data'];
        $decryptedResponse = WC_Hesabe_Crypt::decrypt($responseData, $this->secretKey, $this->ivKey);
        $jsonDecode = json_decode($decryptedResponse);
        if (isset($jsonDecode->response)) {
            $orderInfo = $jsonDecode->response;
            $orderId = $orderInfo->variable1;
            if ($orderId != '') {
                try {
                    if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                        $order = new WC_Order($orderId);
                    } else {
                        $order = new woocommerce_order($orderId);
                    }
                    $orderStatus = $orderInfo->resultCode;
                    //$order->add_order_note("Order Response: " . $decryptedResponse);
                    $order->add_order_note("Status: " . $orderStatus . " Amount: " . $orderInfo->amount);
                    if ($jsonDecode->status == true && ($orderStatus == "CAPTURED" || $orderStatus == "ACCEPT" || $orderStatus == "AUTHORIZED" || $orderStatus == "PARTIALLY_CAPTURED")) {
                        $msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. ";
                        $msg['class'] = 'success';
                        if ($order->status != 'processing') {
                            $order->payment_complete();
                            $order->add_order_note('Hesabe payment successful<br/> Payment Ref Number: ' . $orderInfo->paymentId . ' Payment Token :' . $orderInfo->paymentToken . ' PaidOn :' . $orderInfo->paidOn . ' Amount : ' . $orderInfo->amount);
                            $woocommerce->cart->empty_cart();
                        }
                    }
                    else {
                        $order->update_status('failed');
                        $order->add_order_note('Hesabe payment<br/>Payment Ref Number: ' . $orderInfo->paymentId . ' Payment Token : ' . $orderInfo->paymentToken . ' PaidOn :' . $orderInfo->paidOn . ' Amount : ' . $orderInfo->Amount);
                        $order->add_order_note($msg['message']);
                    }
                } catch (Exception $e) {
                    $msg['class'] = 'error';
                    $msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                }
            }
        }

        if (function_exists('wc_add_notice')) {
            wc_add_notice($msg['message'], $msg['class']);
        } else {
            if ($msg['class'] == 'success') {
                $woocommerce->add_message($msg['message']);
            } else {
                $woocommerce->add_error($msg['message']);
            }
            $woocommerce->set_messages();
        }

        if (!isset($order)) {
            $redirect_url = home_url('/checkout');
        } else {
            $redirect_url = $this->get_return_url($order);
        }
        wp_redirect($redirect_url);
        exit;
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
     * Generate hesabe button link
     * @param $order_id
     */
    public function generate_hesabe_form($order_id)
    {

        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            $order = new WC_Order($order_id);
        } else {
            $order = new woocommerce_order($order_id);
        }
        $order_data = $order->get_data();
        $order_version = $order_data['version']??0;
        $order_billing_first_name = $order_data['billing']['first_name']??"";
        $order_billing_last_name = $order_data['billing']['last_name']??"";
        $order_billing_phone = $order_data['billing']['phone']??"";
        $order_billing_email = $order_data['billing']['email']??"";
        $orderAmount = number_format((float)$order->order_total, 3, '.', '');

        $post_values = array(
            "merchantCode" => $this->merchantCode,
            "amount" => $orderAmount,
            "responseUrl" => $this->notify_url,
            "failureUrl" => $this->notify_url,
            "paymentType" => 0,
            "version" => '2.0',
            "orderReferenceNumber" => $order_id,
            "variable1" => $order_id,
            "variable2" => $order_version,
            "variable3" => $order_billing_first_name." ".$order_billing_last_name,
            "variable4" => $order_billing_phone,
            "variable5" => $order_billing_email,
            "name" => $order_billing_first_name." ".$order_billing_last_name,
            "mobile_number" => $order_billing_phone
        );

        $pattern = "(^[a-zA-Z0-9_.]+[@]{1}[a-z0-9]+[\.][a-z]+$)";
        if (preg_match($pattern, $order_data['billing']['email'])) {
            $post_values['email'] = $order_billing_email;
        }
        if ($this->currencyConvert && $order->get_currency() !== 'KWD') {
            $post_values['currency'] = $order->get_currency();
        }
        $post_string = json_encode($post_values);

        $encrypted_post_string = WC_Hesabe_Crypt::encrypt($post_string, $this->secretKey, $this->ivKey);

        $encrypted_post_string = 'data=' . $encrypted_post_string;

        $header = array();
        $header[] = 'accessCode: ' . $this->accessCode;
        $checkOutUrl = $this->apiUrl . '/checkout';

        $curl = curl_init($checkOutUrl);

        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 12);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $encrypted_post_string);
        $post_response = curl_exec($curl);
        curl_close($curl); // close curl object

        list($responsheader, $responsebody) = explode("\r\n\r\n", $post_response, 2);

        $decrypted_post_response = WC_Hesabe_Crypt::decrypt($responsebody, $this->secretKey, $this->ivKey);

        $decode_response = json_decode($decrypted_post_response);
        if ($decode_response->status != 1 || !(isset($decode_response->response->data))) {
            $responseMessage = "We can not complete order at this moment, Error Code: " . $decode_response->code . " Details : " . $decode_response->message;
            $order->add_order_note('<br/> ' . $responseMessage);
            echo $responseMessage;
            exit;
        }
        $paymentData = $decode_response->response->data;
        header('Location:' . $this->apiUrl . '/payment?data=' . $paymentData);
        exit;
    }
}

