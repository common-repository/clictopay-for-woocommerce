<?php
/*
 * Plugin Name: ClicToPay for WooCommerce
 * Description: This plugin allows you to accept online payments by SPS Clictopay SMT in WooComerce.
 * Version: 1.0.0
 * Author: Dalinovate
 * Author URI: https://dalinovate.com/en/wordpress-plugin-development-agency/
 * License: GPL2
 */

defined('ABSPATH') or die('No script kiddies please!');
function cfw_ctp_add_clictopay_check_payment_page() {
    if (null === get_page_by_title('ClicToPay Check Payment', OBJECT, 'page')) {
        $page = array(
            'post_title'   => esc_html__('ClicToPay Check Payment', 'clictopay-for-woocommerce'),
            'post_content' => '[clictopay_check_payment]',
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
            'post_type'    => 'page',
        );
        wp_insert_post($page);
    }
}
register_activation_hook(__FILE__, 'cfw_ctp_add_clictopay_check_payment_page');

function cfw_ctp_add_failed_payment_page() {
    if (null === get_page_by_title('Failed Payment', OBJECT, 'page')) {
        $page = array(
            'post_title'   => esc_html__('Failed Payment', 'clictopay-for-woocommerce'),
            'post_content' => 'Failed Payment',
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
            'post_type'    => 'page',
        );
        wp_insert_post($page);
    }
}
register_activation_hook(__FILE__, 'cfw_ctp_add_failed_payment_page');
add_filter('woocommerce_payment_gateways', 'cfw_ctp_add_credit_card_gateway_class');
function cfw_ctp_add_credit_card_gateway_class($gateways) {
    $gateways[] = 'CFW_ClicToPay_Credit_Card_Gateway';
    return $gateways;
}

add_action('plugins_loaded', 'cfw_ctp_init_credit_card_gateway_class');
function cfw_ctp_init_credit_card_gateway_class() {

    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class CFW_ClicToPay_Credit_Card_Gateway extends WC_Payment_Gateway {
        public function __construct() {
            $this->id                 = 'cc_ctp';
            $this->icon               = '';
            $this->has_fields         = false;
            $this->method_title       = __('Credit Card using ClicToPay', 'clictopay-for-woocommerce');
            $this->method_description = __('Enable paying with Credit Card using ClicToPay', 'clictopay-for-woocommerce');

            $this->supports = array('products');

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled     = $this->get_option('enabled');
            $this->testmode    = 'yes' === $this->get_option('testmode');
            $this->username    = $this->get_option('username');
            $this->password    = $this->get_option('password');
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Credit Card using ClicToPay',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Carte de crédit',
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Payer avec votre carte bancaire à travers le service ClicToPay.',
                ),
                'testmode' => array(
                    'title' => 'Test mode',
                    'label' => 'Enable Test Mode',
                    'type' => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'username' => array(
                    'title' => 'Api-User Login',
                    'type' => 'text',
                    'description' => 'Provided by ClicToPay'
                ),
                'password' => array(
                    'title' => 'Api-User Password',
                    'type' => 'password',
                    'description' => 'Provided by ClicToPay'
                )
            );
        }
    
        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $request_url = ($this->testmode ? 'https://test.' : 'https://ipay.') . 'clictopay.com/payment/rest/register.do';
            $args = array(
                'body' => array(
                'currency' => 788,
                'amount' => $order->get_total() * 1000,
                'orderNumber' => $order_id,
                'password' => $this->password,
                'returnUrl' => get_site_url() . '/clictopay-check-payment',
                'userName' => $this->username,
                ),
            );

            $response = wp_remote_post($request_url, $args);
            if (is_wp_error($response)) {
                wc_add_notice('Connection error.', 'error');
                return;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['errorCode']) && (int)$body['errorCode'] !== 0) {
                wc_add_notice($body['errorMessage'], 'error');
                return;
            }

            return array(
                'result' => 'success',
                'redirect' => $body['formUrl']
            );
        }    

        public function clictopay_check_payment() {
            $orderId = isset($_GET['orderId']) ? sanitize_text_field($_GET['orderId']) : '';

            $request_url = ($this->testmode ? 'https://test.' : 'https://ipay.') . 'clictopay.com/payment/rest/getOrderStatus.do';
            $args = array(
                'body' => array(
                'orderId' => $orderId,
                'password' => $this->password,
                'userName' => $this->username,
                ),
            );
            
            $response = wp_remote_post($request_url, $args);
            if (is_wp_error($response)) {
                wp_enqueue_script('redirect-script', plugin_dir_url(__FILE__) . 'js/redirect-script.js', array('jquery'), '1.0', true);
                wp_localize_script('redirect-script', 'failed_payment_url', get_site_url() . '/failed-payment/');
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['ErrorMessage']) && $body['ErrorMessage'] === 'Success') {
                $order = wc_get_order($body['OrderNumber']);
                $order->payment_complete();
                $order->reduce_order_stock();
                wc()->cart->empty_cart();
                wp_enqueue_script('redirect-script', plugin_dir_url(__FILE__) . 'js/redirect-script.js', array('jquery'), '1.0', true);
                wp_localize_script('redirect-script', 'return_url', $this->get_return_url($order));
            } else {
                wp_enqueue_script('redirect-script', plugin_dir_url(__FILE__) . 'js/redirect-script.js', array('jquery'), '1.0', true);
                wp_localize_script('redirect-script', 'failed_payment_url', get_site_url() . '/failed-payment/');
            }   
        }
    }
    add_shortcode('clictopay_check_payment', [new CFW_ClicToPay_Credit_Card_Gateway(), 'clictopay_check_payment']);
}
?>