<?php

class WC_TapPay_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id = 'tappay';
//        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = 'TapPay Gateway';
        $this->method_description = 'Use TapPay gateway, this enables pay by prime route.';

        $this->supports = array(
            'products'
        );

        $this->init_form_fields();

        $this->init_settings();
        $this->title = $this->get_option('title');
        if (empty($this->title)) {
            $this->title = $this->method_title;
        }
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = $this->get_option('testmode');

        if ('no' === $this->testmode) {
            // production mode
            $this->app_key = $this->get_option('app_key');
            $this->app_id = $this->get_option('app_id');
            $this->partner_key = $this->get_option('partner_key');
            $this->merchant_id = $this->get_option('merchant_id');
            $this->endpoint = 'https://prod.tappaysdk.com/tpc/';
        } else {
            // test mode
            $this->app_key = $this->get_option('test_app_key');
            $this->app_id = $this->get_option('test_app_id');
            $this->partner_key = $this->get_option('test_partner_key');
            $this->merchant_id = $this->get_option('test_merchant_id');
            $this->endpoint = 'https://sandbox.tappaysdk.com/tpc/';
        }

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_api_tappay', array($this, 'webhook'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable TapPay Gateway',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'TapPay',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'description' => 'This controls the description which the user sees during checkout.',
                'default'     => 'Use TapPay',
            ),
            'testmode' => array(
                'title'       => 'Test mode',
                'label'       => 'Enable Test Mode',
                'type'        => 'checkbox',
                'description' => 'Place the payment gateway in test mode using test API keys.',
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'test_app_key' => array(
                'title'       => 'Test APP Key',
                'type'        => 'text',
                'default'     => 'app_whdEWBH8e8Lzy4N6BysVRRMILYORF6UxXbiOFsICkz0J9j1C0JUlCHv1tVJC',
            ),
            'test_app_id' => array(
                'title'       => 'Test APP ID',
                'type'        => 'text',
                'default'     => '11327',
            ),
            'test_partner_key' => array(
                'title'       => 'Test Partner Key',
                'type'        => 'text',
                'default'     => 'partner_6ID1DoDlaPrfHw6HBZsULfTYtDmWs0q0ZZGKMBpp4YICWBxgK97eK3RM',
            ),
            'test_merchant_id' => array(
                'title'       => 'Test Merchant ID',
                'type'        => 'text',
                'default'     => 'GlobalTesting_CTBC',
            ),
            'app_key' => array(
                'title'       => 'APP Key',
                'type'        => 'text',
            ),
            'app_id' => array(
                'title'       => 'APP ID',
                'type'        => 'text',
            ),
            'partner_key' => array(
                'title'       => 'Partner Key',
                'type'        => 'text',
            ),
            'merchant_id' => array(
                'title'       => 'Merchant ID',
                'type'        => 'text',
            ),

        );
    }

    public function payment_fields() {
        if ($this->description) {
            if ('no' !== $this->testmode) {
                $this->description .= ' <span style="color: #F00; font-weight: bold;">TEST MODE ENABLED</span>';
            }
            $this->description = trim($this->description);
            echo wpautop(wp_kses_post($this->description));
        }

        if (is_checkout()) {
            wp_nonce_field('wc_tappay', '_wc_tappay_nonce');

            echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

            do_action('woocommerce_credit_card_form_start', $this->id);

            echo <<<EOT
                <div style="margin: 0 auto;">
                    <label>信用卡支付</label>
                    <div id="tappay_creditcard_container" style="margin: 5px;"></div>
                </div>
                <input type="hidden" name="tappay_prime" id="tappay_prime"></input>
EOT;

            do_action('woocommerce_credit_card_form_end', $this->id);
            echo '<div class="clear"></div></fieldset>';
        }
    }

    public function payment_scripts() {
        if ('no' === $this->enabled) {
            return;
        }

        // only enqueue script in following conditions
        if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }

        if (!$this->testmode && !is_ssl()) {
            return;
        }

        // enqueue scripts
        wp_enqueue_script('tappay_js', 'https://js.tappaysdk.com/tpdirect/v5');

        wp_register_script('woocommerce_tappay', plugins_url('tappay.js', __FILE__), array('jquery', 'tappay_js'), '20190429', true);

        wp_localize_script('woocommerce_tappay', 'tappay_params', array(
            'method' => 'credit',
            'app_id' => $this->app_id,
            'app_key' => $this->app_key,
            'server_type' => ('no' === $this->enabled)? 'production':'sandbox',
        ));

        wp_enqueue_script('woocommerce_tappay');
    }

    public function validate_fields() {
        return true;
    }

    public function process_payment($orderId) {
        global $woocommerce;

        $nonceVerified = wp_verify_nonce($_POST['_wc_tappay_nonce'], 'wc_tappay');

        // nonce was generated in 12 hours ago
        if (1 === $nonceVerified) {
            // nonce verified
            $order = wc_get_order($orderId);

            if (!$order) {
                wc_add_notice('Invalid order', 'error');
                return;
            }

            $response = $this->_payByPrime($order, $_POST['tappay_prime']);

            // get data & process stock
            if(!is_wp_error($response)) {
                $result = json_decode($response['body'], true);

                // success
                if (0 === $result['status']) {
                    $msg = 'Payment completed, trade_id = ' . $result['rec_trade_id'];
                    $order->add_order_note($msg);

                    // save rec_trade_id
                    $order->payment_complete($result['rec_trade_id']);
                    // process stock
                    $order->reduce_order_stock();
                    // add note perhaps?
                    // $order->add_order_note('');
                    $woocommerce->cart->empty_cart();

                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order),
                    );
                } else {
                    // failed payment
                    $msg = 'Payment failed, message: ' . $result['msg'];
                    $order->add_order_note($msg);
                    wc_add_notice($msg, 'error');
                    return;
                }
            } else {
                // connection error
                wc_add_notice('Connection error', 'error');
                return;
            }
        } else {
            // nonce not verified
            wc_add_notice('Invalid nonce', 'error');
            return;
        }
    }

    // webhook for sending prime to server and process payment
    public function webhook() {
        // nada
    }

    private function _payByPrime($order, $prime) {
        // prepare data send to gateway
        $detailText = array();

        foreach ($order->get_items() as $itemKey => $item) {
            $detailText[] = $item->get_name() . ' x' . $item->get_quantity() . PHP_EOL;
        }

        $amount = $order->get_total();

        if ('TWD' === $order->get_currency()) {
            $amount = intval($amount);
        }

        $postData = array(
            'prime' => $prime,
            'partner_key' => $this->partner_key,
            'merchant_id'=> $this->merchant_id,
            'details' => implode(PHP_EOL, $detailText),
            'amount' => $amount,
            'currency' => $order->get_currency(),
            'order_number' => $order->get_order_number(),
            'cardholder'=> array(
                'phone_number' => $order->get_billing_phone(),
                'name' => $order->get_formatted_billing_full_name(),
                'email' => $order->get_billing_email(),
                'zip_code' => $order->get_billing_postcode(),
                'address' => strip_tags($order->get_formatted_billing_address()),
            ),
        );

        // send data
        $response = wp_remote_post(
            $this->endpoint . 'payment/pay-by-prime',
            array(
                'method' => 'POST',
                'timeout' => 30,
                'headers' => array(
                    'content-type' => 'application/json',
                    'x-api-key' => $this->partner_key,
                ),
                'body' => json_encode($postData),
            )
        );

        return $response;
    }
}
