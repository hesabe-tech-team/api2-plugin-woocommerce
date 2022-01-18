<?php

class WC_Hesabe extends WC_Payment_Gateway
{
    public function __construct()
    {
        // construct form //
        // Go wild in here
        $this->id = 'hesabe';
        $this->method_title = __('Hesabe Online Payment');
        $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(__DIR__) . '/images/logo.png';
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
                'default' => __('Hesabe Configuration')),

            'description' => array(
                'title' => __('Description:'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.'),
                'default' => __('The best payment gateway provider in Kuwait for e-payment through credit card & debit card')),

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
        $authorisedTransaction = false;
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
                    $order->add_order_note("Status: " . $orderStatus . " Amount: " . $orderInfo->Amount);
                    if ($orderStatus == "CAPTURED") {
                        $authorisedTransaction = true;
                        $msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. ";
                        $msg['class'] = 'success';
                        if ($order->status != 'processing') {
                            $order->payment_complete();
                            $order->add_order_note('Hesabe  payment successful<br/> Payment Ref Number: ' . $orderInfo->paymentId . ' Payment Token :' . $orderInfo->paymentToken . ' PaidOn :' . $orderInfo->paidOn . ' Amount : ' . $orderInfo->Amount);
                            $woocommerce->cart->empty_cart();
                        }
                    }
                    if ($authorisedTransaction == false) {
                        $order->update_status('failed');
                        $order->add_order_note('Hesabe  payment<br/>Payment Ref Number: ' . $orderInfo->paymentId . ' Payment Token : ' . $orderInfo->paymentToken . ' PaidOn :' . $orderInfo->paidOn . ' Amount : ' . $orderInfo->Amount);
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
}

