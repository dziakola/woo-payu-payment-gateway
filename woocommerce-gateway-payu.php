<?php
/**
 * Plugin Name: PayU Payment Gateway
 * Plugin URI: https://github.com/PayU/woo-payu-payment-gateway
 * GitHub Plugin URI: https://github.com/PayU-EMEA/woo-payu-payment-gateway
 * Description: PayU payment gateway for WooCommerce
 * Version: 2.0.18
 * Author: PayU SA
 * Author URI: http://www.payu.com
 * License: LGPL 3.0
 * Text Domain: woo-payu-payment-gateway
 * Domain Path: /lang
 * WC requires at least: 3.0
 * WC tested up to: 6.8.1
 */

define('PAYU_PLUGIN_VERSION', '2.0.18');
define('PAYU_PLUGIN_FILE', __FILE__);
define('PAYU_PLUGIN_STATUS_WAITING', 'payu-waiting');

add_action('plugins_loaded', 'init_gateway_payu');
add_action('admin_init', 'move_old_payu_settings');

/**
 * Init function that runs after plugin install.
 */

function init_gateway_payu()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    load_plugin_textdomain('woo-payu-payment-gateway', false, dirname(plugin_basename(__FILE__)) . '/lang/');

    require_once('includes/WC_PayUGateways.php');
    require_once('includes/PayUSettings.php');
    require_once('includes/WC_Gateway_PayuCreditCard.php');
    require_once('includes/WC_Gateway_PayuListBanks.php');
    require_once('includes/WC_Gateway_PayuStandard.php');
    require_once('includes/WC_Gateway_PayuSecureForm.php');
    require_once('includes/WC_Gateway_PayuBlik.php');
    require_once('includes/WC_Gateway_PayuInstallments.php');
    require_once('includes/WC_Gateway_PayuPaypo.php');
    require_once('includes/WC_Gateway_PayuTwistoPl.php');

    add_filter('woocommerce_payment_gateways', 'add_payu_gateways');
    add_filter('woocommerce_valid_order_statuses_for_payment_complete', 'payu_filter_woocommerce_valid_order_statuses_for_payment_complete', 10, 2 );
    add_filter('woocommerce_email_actions', 'add_payu_order_status_to_email_notifications');
    add_filter('woocommerce_email_classes', 'add_payu_order_status_to_email_notifications_triger');

    if (!is_admin() && isset($_GET['pay_for_order'], $_GET['key'])) {
        add_filter('woocommerce_valid_order_statuses_for_payment',
            'payu_filter_woocommerce_valid_order_statuses_for_payment',
            10, 2);
    }
    add_filter('plugin_row_meta', 'plugin_row_meta', 10, 2);
}

//enable pbl and standard if first install
register_activation_hook(__FILE__, 'payu_plugin_on_activate');
function payu_plugin_on_activate()
{
    if (!get_option('woocommerce_payu_settings') && !get_option('_payu_plugin_version')) {
        add_option('_payu_plugin_version', PAYU_PLUGIN_VERSION);
        add_option('woocommerce_payulistbanks_settings', ['enabled' => 'yes']);
        add_option('woocommerce_payucreditcard_settings', ['enabled' => 'yes']);
        add_option('payu_settings_option_name', ['global_default_on_hold_status' => 'on-hold']);
    }
}

function move_old_payu_settings()
{
    if ($old_payu = get_option('woocommerce_payu_settings')) {
        unset($old_payu['payu_feedback']);

        $global = [];
        $standard = [];
        foreach ($old_payu as $key => $value) {
            if (!in_array($key, ['enabled', 'title', 'sandbox'])) {
                $global['global_' . $key] = $value;
            }
        }
        $global['global_default_on_hold_status'] = 'on-hold';
        update_option('payu_settings_option_name', $global);
        foreach ($old_payu as $key => $value) {
            if (!in_array($key, ['enabled', 'title', 'sandbox', 'description', 'enable_for_shipping'])) {
                $standard[$key] = '';
            } else {
                $standard[$key] = $value;
            }
        }
        $standard['use_global'] = 'yes';
        update_option('woocommerce_payustandard_settings', $standard);
        update_option('_payu_plugin_version', PAYU_PLUGIN_VERSION);
        delete_option('woocommerce_payu_settings');
    }
}

/**
 * @return array
 */
function payu_filter_woocommerce_valid_order_statuses_for_payment()
{
    return ['pending', 'failed', 'on-hold', PAYU_PLUGIN_STATUS_WAITING];
}

/**
 * @param array $statuses
 * @return array
 */
function payu_filter_woocommerce_valid_order_statuses_for_payment_complete($statuses)
{
    $statuses[] = 'payu-waiting';
    return $statuses;
}

/**
 * @param array $gateways
 *
 * @return array
 */
function add_payu_gateways($gateways)
{
    $gateways[] = 'WC_Gateway_PayuCreditCard';
    $gateways[] = 'WC_Gateway_PayuListBanks';
    $gateways[] = 'WC_Gateway_PayUStandard';
    $gateways[] = 'WC_Gateway_PayuSecureForm';
    $gateways[] = 'WC_Gateway_PayuBlik';
    $gateways[] = 'WC_Gateway_PayuInstallments';
    $gateways[] = 'WC_Gateway_PayuPaypo';
    $gateways[] = 'WC_Gateway_PayuTwistoPl';
    return $gateways;
}

/**
 * @param array $actions
 *
 * @return array
 */
function add_payu_order_status_to_email_notifications($actions)
{
    $actions[] = 'woocommerce_order_status_'.PAYU_PLUGIN_STATUS_WAITING.'_to_processing';

    return $actions;
}

/**
 * @param array $clases
 *
 * @return array
 */
function add_payu_order_status_to_email_notifications_triger($clases)
{
    if ($clases['WC_Email_Customer_Processing_Order']) {
        add_action('woocommerce_order_status_' . PAYU_PLUGIN_STATUS_WAITING . '_to_processing_notification', array($clases['WC_Email_Customer_Processing_Order'], 'trigger'), 10, 2);
    }

    return $clases;
}

function plugin_row_meta($links, $plugin_file) {
    if (strpos($plugin_file, 'woo-payu-payment-gateway') === false) {
        return $links;
    }
    $row_meta = array(
        'docs' => '<a href="' . esc_url( 'https://github.com/PayU-EMEA/woo-payu-payment-gateway' ) . '">' . esc_html__( 'Docs', 'woo-payu-payment-gateway' ) . '</a>'
    );

    return array_merge( $links, $row_meta );
}

//enqueue assets
function enqueue_payu_admin_assets()
{
    wp_enqueue_script('payu-admin', plugins_url( '/assets/js/payu-admin.js', PAYU_PLUGIN_FILE ), ['jquery'], PAYU_PLUGIN_VERSION);
    wp_enqueue_style('payu-admin', plugins_url( '/assets/css/payu-admin.css', PAYU_PLUGIN_FILE ), [], PAYU_PLUGIN_VERSION);
}

if (is_admin()) {
    add_action('admin_enqueue_scripts', 'enqueue_payu_admin_assets');
}

add_action('woocommerce_view_order', 'view_order');
function view_order($order_id)
{
    wp_enqueue_style('payu-gateway', plugins_url( '/assets/css/payu-gateway.css', PAYU_PLUGIN_FILE ),
        [], PAYU_PLUGIN_VERSION);

    $order = wc_get_order($order_id);
    $payu_gateways = WC_PayUGateways::gateways_list();
    if (in_array($order->get_status(), ['on-hold', 'pending', 'failed'])) {
        if (@$payu_gateways[$order->get_payment_method()] && isset(get_option('payu_settings_option_name')['global_repayment'])) {
            $pay_now_url = add_query_arg([
                'pay_for_order' => 'true',
                'key' => $order->get_order_key()
            ], wc_get_endpoint_url('order-pay', $order->get_id(), wc_get_checkout_url()));

            ?>
            <a href="<?php echo esc_url($pay_now_url) ?>" class="autonomy-payu-button"><?php esc_html_e('Pay with', 'woo-payu-payment-gateway'); ?>
                <img src="<?php echo esc_url(plugins_url( '/assets/images/logo-payu.svg', PAYU_PLUGIN_FILE )) ?>"/>
            </a>
            <?php
        }
    }
}

// Register new status
function register_waiting_payu_order_status()
{
    register_post_status('wc-'. PAYU_PLUGIN_STATUS_WAITING,
        [
            'label' => __('Awaiting receipt of payment', 'woo-payu-payment-gateway'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
        ]
    );
}

/**
 * @return bool
 */
function woocommerce_payu_is_wmpl_active_and_configure()
{
    global $woocommerce_wpml;

    return $woocommerce_wpml
        && property_exists($woocommerce_wpml, 'multi_currency')
        && $woocommerce_wpml->multi_currency
        && count($woocommerce_wpml->multi_currency->get_currency_codes()) > 1;
}

/**
 * @return bool
 */
function woocommerce_payu_is_currency_custom_config()
{
    return apply_filters('woocommerce_payu_multicurrency_active', false)
        && count(apply_filters('woocommerce_payu_get_currency_codes', [])) > 1;
}

/**
 * @return array
 */
function woocommerce_payu_get_currencies()
{
    global $woocommerce_wpml;

    $currencies = [];

    if (woocommerce_payu_is_wmpl_active_and_configure()) {
        $currencies = $woocommerce_wpml->multi_currency->get_currency_codes();
    } elseif (woocommerce_payu_is_currency_custom_config()) {
        $currencies = apply_filters('woocommerce_payu_get_currency_codes', []);
    }

    return $currencies;
}

add_action('init', 'register_waiting_payu_order_status');

// Add to list of WC Order statuses
function add_waiting_payu_to_order_statuses($order_statuses)
{
    $order_statuses['wc-'.PAYU_PLUGIN_STATUS_WAITING] = __('Awaiting receipt of payment', 'woo-payu-payment-gateway');
    return $order_statuses;
}

add_filter('wc_order_statuses', 'add_waiting_payu_to_order_statuses');

//remove pay button
function filter_woocommerce_my_account_my_orders_actions($actions, $order)
{
    // Get status
    $order_status = $order->get_status();

    if (in_array($order_status,
        [
            'failed',
            PAYU_PLUGIN_STATUS_WAITING,
            get_option('payu_settings_option_name')['global_default_on_hold_status']
        ])) {
        $payu_gateways = WC_PayUGateways::gateways_list();
        if (@$payu_gateways[$order->get_payment_method()] && isset(get_option('payu_settings_option_name')['global_repayment'])) {
            $actions['repayu'] = [
                'name' => __('Pay with PayU', 'woo-payu-payment-gateway'),
                'url' => wc_get_endpoint_url('order-pay', $order->get_id(),
                        wc_get_checkout_url()) . '?pay_for_order=true&key=' . $order->get_order_key()
            ];
            unset($actions['pay']);
        }

    }

    return $actions;
}

add_filter('woocommerce_my_account_my_orders_actions', 'filter_woocommerce_my_account_my_orders_actions', 10, 2);
add_action('woocommerce_order_item_add_action_buttons', 'wc_order_item_add_action_buttons_callback', 10, 1);

function wc_order_item_add_action_buttons_callback($order)
{
    $payu_gateways = WC_PayUGateways::gateways_list();
    if (@$payu_gateways[$order->get_payment_method()] && !isset(get_option('payu_settings_option_name')['global_repayment']) && get_post_meta($order->get_id(),
            '_payu_order_status')) {
        $payu_statuses = WC_PayUGateways::clean_payu_statuses(get_post_meta($order->get_id(), '_payu_order_status'));
        if ((!in_array(OpenPayuOrderStatus::STATUS_COMPLETED,
                    $payu_statuses) && !in_array(OpenPayuOrderStatus::STATUS_CANCELED,
                    $payu_statuses)) && in_array(OpenPayuOrderStatus::STATUS_WAITING_FOR_CONFIRMATION,
                $payu_statuses)) {
            $url_receive = add_query_arg([
                'post' => $order->get_id(),
                'action' => 'edit',
                'receive-payment' => 1
            ], admin_url('post.php'));
            $url_discard = add_query_arg([
                'post' => $order->get_id(),
                'action' => 'edit',
                'discard-payment' => 1
            ], admin_url('post.php'));
            ?>
            <a href="<?php echo esc_url($url_receive) ?>" type="button"
               class="button receive-payment"><?php esc_html_e('Receive payment', 'woo-payu-payment-gateway') ?></a>
            <a href="<?php echo esc_url($url_discard) ?>" type="button"
               class="button discard-payment"><?php esc_html_e('Discard payment', 'woo-payu-payment-gateway') ?></a>
            <?php
            $url_return = add_query_arg([
                'post' => $order->get_id(),
                'action' => 'edit',
            ], admin_url('post.php'));
            if (is_admin() && (isset($_GET['receive-payment']) || isset($_GET['discard-payment']))) {
                global $current_user;
                wp_get_current_user();

                if (isset($_GET['receive-payment']) && !isset($_GET['discard-payment'])) {
                    $orderId = $order->get_transaction_id();
                    $status_update = [
                        "orderId" => $orderId,
                        "orderStatus" => OpenPayuOrderStatus::STATUS_COMPLETED
                    ];
                    $payment_method_name = $order->get_payment_method();
                    $payment_init = WC_PayUGateways::gateways_list()[$payment_method_name]['api'];
                    $payment = new $payment_init;
                    $payment->init_OpenPayU($order->get_currency());
                    OpenPayU_Order::statusUpdate($status_update);
                    $order->add_order_note(sprintf(__('[PayU] User %s accepted payment', 'woo-payu-payment-gateway'),
                        $current_user->user_login));
                    wp_redirect($url_return);
                }
                if (!isset($_GET['receive-payment']) && isset($_GET['discard-payment'])) {
                    $payment_method_name = $order->get_payment_method();
                    $payment_init = WC_PayUGateways::gateways_list()[$payment_method_name]['api'];
                    $payment = new $payment_init;
                    $payment->init_OpenPayU($order->get_currency());
                    $orderId = $order->get_transaction_id();
                    OpenPayU_Order::cancel($orderId);
                    $order->add_order_note(sprintf(__('[PayU] User %s rejected payment', 'woo-payu-payment-gateway'),
                        $current_user->user_login));
                    wp_redirect($url_return);
                }
            }
        }
    }
}
