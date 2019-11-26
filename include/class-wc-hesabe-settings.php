<?php
class WC_Hesabe extends WC_Payment_Gateway
{


    public function __construct()
    {
        // construct form //
        // Go wild in here
        $this->id = 'hesabe';
        $this->method_title = __('Hesabe Online Payment');
        $this->icon = WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/images/logo.png';
        $this->has_fields = false;
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->merchantcode = $this->settings['merchantcode'];

        $this->user1 = ( ! empty( $this->settings['user1']) ) ? $this->settings['user1'] : '';;
        //$this->user2 = $this->settings['user2'];
        //$this->user3 = $this->settings['user3'];
        //$this->user4 = $this->settings['user4'];
        $this->sandbox = $this->settings['sandbox'];
        $this->secretKey = $this->settings['secretKey'];
        $this->ivKey = $this->settings['ivKey'];
        $this->accessCode = $this->settings['accessCode'];

        if ($this->sandbox == 'yes') {
            $this->apiUrl = 'http://payment-api.eu-central-1.elasticbeanstalk.com';
        } else {
            $this->apiUrl = 'liveUrl';
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

            'merchantcode' => array(
                'title' => __('Merchant Code'),
                'type' => 'text',
                'description' => __('This is Merchant Code."')),

            'accessCode' => array(
                'title' => __('Access Code'),
                'type' => 'text',
                'description' => __('Access Code'),
            ),
            'secretKey' => array(
                'title' => __('Secret Key'),
                'type' => 'text',
                'description' => __('Secret Key'),
            ),

            'ivKey' => array(
                'title' => __('IV'),
                'type' => 'text',
                'description' => __('IV of Secret Key'),
            ),
            'user1' => array(
                'title' => __('Variable'),
                'type' => 'text',
                'description' => __('Custom return value'),
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
        //echo 'Knet <input type="radio" name="paymentOption" value="1" required/>';

        //echo "<br>";
        //echo 'Mpgs <input type="radio" name="paymentOption" value="2" required/>';

        if ($this->description) echo wpautop(wptexturize($this->description));
    }

    /**
     * Receipt Page
     **/
    function receipt_page($order)
    {
        echo '<p>' . __('Thank you for your order, please click the button below to pay with hesabe.') . '</p>';
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
     * Check for valid hesabe server callback // response processing //
     **/
    function check_hesabe_response()
    {
        global $woocommerce;
        $msg['class'] = 'error';
        $msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
        $responseData = $_REQUEST['data'];
        $decryptedResponse=WC_Hesabe_Crypt::decrypt($responseData,$this->secretKey,$this->ivKey);
        $jsonDecode = json_decode($decryptedResponse);
        //print_r($jsonDecode);exit;
        if(isset($jsonDecode->status) && $jsonDecode->status==1) {

            //if (isset($_REQUEST['Variable5']) && $_REQUEST['Variable5'] != "") {

            $orderInfo = $jsonDecode->response;
//$resultData = response
            $order_id = $orderInfo->variable2;

            if ($order_id != '') {
                $authorisedTransaction = false;
                try {
                    if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                        $order = new WC_Order($order_id);
                    } else {
                        $order = new woocommerce_order($order_id);
                    }

                    $orderStatus = $orderInfo->resultCode;

                    if ($orderStatus == "CAPTURED") {
                        $authorisedTransaction = true;
                        $msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. ";
                        $msg['class'] = 'success';
                        if ($order->status != 'processing') {
                            $order->payment_complete();
                            $order->add_order_note('Hesabe  payment successful<br/> Payment Ref Number: ' . $orderInfo->paymentId . ' Payment Token :' . $orderInfo->paymentToken . ' PaidOn :' . $orderInfo->paidOn);
                            $woocommerce->cart->empty_cart();

                        }

                    }
                    if ($authorisedTransaction == false) {
                        $order->update_status('failed');
                        $order->add_order_note('Hesabe  payment<br/>Payment Ref Number: ' . $orderInfo->paymentId . ' Payment Token : ' . $orderInfo->paymentToken . ' PaidOn :' . $orderInfo->paidOn);
                        $order->add_order_note($this->msg['message']);
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
        //$redirect_url = get_permalink(woocommerce_get_page_id('myaccount'));
        $redirect_url = $this->get_return_url($order);
        wp_redirect($redirect_url);
        exit;
    }


    /**
     * Generate hesabe button link
     **/
    public function generate_hesabe_form($order_id)
    {
        global $woocommerce;
        $txnDate = date('Y-m-d');
        $milliseconds = (int)(1000 * (strtotime(date('Y-m-d'))));
        if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
            $order = new WC_Order($order_id);
        } else {
            $order = new woocommerce_order($order_id);
        }

        $orderAmount = number_format((float)$order->order_total, 3, '.', '');
        //$str = "2";
        //$key = "OGzgrmqyDEnlALQRNvzPv8NJ4BwWM019";
        //$ivKey = 'WjnegZ76y3PO3dwZ';
        //$text = WC_Hesabe_Crypt::encrypt($str, $key, $ivKey);

        $post_values = array(
            "merchantCode" => $this->merchantcode,
            "amount" => $orderAmount,
            "responseUrl" => $this->notify_url,
            "paymentType" =>1,
            "version" => '2.0',
            "variable1" => $this->user1,
            "variable2" => $order_id
        );

        $post_string=json_encode($post_values);

        $encrypted_post_string=WC_Hesabe_Crypt::encrypt($post_string,$this->secretKey,$this->ivKey);
//print_r($encrypted_post_string);exit;
        $encrypted_post_string= 'data='.$encrypted_post_string;

        $header = array();


        //$header[] = 'accessCode: '.$this->accessCode;

        $header[] = 'accessCode: ab035967-3e92-4e07-9555-e7e4faf40a3f';
        //$header[] = 'content-type: multipart/form-data';

        //api/checkout
        $checkOutUrl = $this->apiUrl.'/api/checkout';
        $curl = curl_init($checkOutUrl);

        if ($this->sandbox == 'yes') {
            //curl_setopt($curl, CURLOPT_PORT, 443);
        }

        curl_setopt($curl, CURLOPT_HEADER,1);
        curl_setopt($curl, CURLOPT_HTTPHEADER,$header);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 12);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $encrypted_post_string);
        $post_response = curl_exec($curl);
        if($post_response === false)
        {
            //echo 'Curl error: ' . curl_error($curl);exit;
        }
        curl_close ($curl); // close curl object

        list($responsheader, $responsebody) = explode("\r\n\r\n", $post_response, 2);

        $decrypted_post_response=WC_Hesabe_Crypt::decrypt($responsebody,$this->secretKey,$this->ivKey);

        $decode_response=json_decode($decrypted_post_response);
        if($decode_response->status !=1 || !(isset($decode_response->response->data))) {
            echo "We can take order at this moment";exit;
        }
        $paymentData = $decode_response->response->data;
        //header('Location: http://www.example.com/');exit;
        header('Location:'.$this->apiUrl.'/api/payment?data='.$paymentData);exit;
    }


    /*
     * End hesabe Essential Functions
     **/
    // get all pages

    function get_pages($title = false, $indent = true)
    {
        $wp_pages = get_pages('sort_column=menu_order');
        $page_list = array();
        if ($title) $page_list[] = $title;
        foreach ($wp_pages as $page) {
            $prefix = '';
            // show indented child pages?
            if ($indent) {
                $has_parent = $page->post_parent;
                while ($has_parent) {
                    $prefix .= ' - ';
                    $next_page = get_page($has_parent);
                    $has_parent = $next_page->post_parent;
                }
            }
            // add to page list array array
            $page_list[$page->ID] = $prefix . $page->post_title;
        }
        return $page_list;
    }

}

