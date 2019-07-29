<?php
/**
 * Plugin Name: WooCommerce TapPay Gateway
 * Description: Adds TapPay to your WooCommerce
 * Version: 1.0
 * Author: Kuo, Yu-Min
 * Author URI: https://blog.cornguo.net
 * License: Reserved
 */

// require class file
if (!defined('ABSPATH')) {
    exit;
}

// This action hook registers our PHP class as a WooCommerce payment gateway
add_filter('woocommerce_payment_gateways', 'tappay_add_gateway_class');
function tappay_add_gateway_class($gateways) {
    $gateways[] = 'WC_TapPay_Gateway'; // your class name is here
    return $gateways;
}

// The class itself, please note that it is inside plugins_loaded action hook
add_action('plugins_loaded', 'tappay_init_gateway_class');
function tappay_init_gateway_class() {
    require_once 'includes/class-wc-tappay-gateway.php';
}

// prevent using other payment method in payment page
function tappay_enable_gateway_order_pay($available_gateways) {
    global $woocommerce;

    if (is_checkout() && is_wc_endpoint_url('order-pay')) {
        $order = wc_get_order(intval($_GET['order-pay']));
        if ('tappay' === $order->get_payment_method()) {
            return array(
                'tappay' => $available_gateways['tappay'],
            );
        }
    }
    return $available_gateways;
}
add_filter('woocommerce_available_payment_gateways', 'tappay_enable_gateway_order_pay');
