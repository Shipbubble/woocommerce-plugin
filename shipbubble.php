<?php

/**
 * Plugin Name:  Shipbubble
 * Description:  Shipbubble is a platform that enables retailers to conveniently delight their customers with multiple shipping options, thereby increasing conversion rates
 * Contributors: Shipbubble, Mavi Onogomuho
 * Donate link: https://www.shipbubble.com/
 * Tags: logistics, deliveries, shipping rates, multiple couriers, post purchase experience
 * Requires at least: 4.0
 * Tested up to: 6.5
 * Stable tag: 1.0
 * Requires PHP: 5.6
 * Text Domain:  shipbubble
 * Domain Path:  /languages
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires Plugins: woocommerce
 */

// exit if file is called directly
if (!defined('ABSPATH')) {
	exit;
}

// if  admin area
if (is_admin()) {
	// include dependencies
	require_once plugin_dir_path(__FILE__) . 'admin/wordpress/async-validate-auth.php';

	// Woocommerce
	require_once plugin_dir_path(__FILE__) . 'admin/woocommerce/async-create-shipment.php';
	require_once plugin_dir_path(__FILE__) . 'admin/woocommerce/async-validate-address.php';
	require_once plugin_dir_path(__FILE__) . 'admin/woocommerce/enqueue-styles.php';

	// settings menu
	require_once plugin_dir_path(__FILE__) . 'admin/settings/settings-menu.php';
}

// includes
require_once plugin_dir_path(__FILE__) . 'includes/constants.php';
require_once plugin_dir_path(__FILE__) . 'includes/endpoints.php';
require_once plugin_dir_path(__FILE__) . 'includes/core-methods.php';
require_once plugin_dir_path(__FILE__) . 'includes/compatibilities.php';

// public
require_once plugin_dir_path(__FILE__) . 'public/async-checkout-couriers.php';

define('SHIPBUBBLE_PLUGIN_URL', plugins_url('', __FILE__));
define('SHIPBUBBLE_LOGO_URL', SHIPBUBBLE_PLUGIN_URL . '/public/images/logo.svg');


// action on activation
function shipbubble_on_activation()
{
	if (!current_user_can('activate_plugins')) return;

	$data = array('initialized' => true, 'account_status' => false, SHIPBUBBLE_ADDRESS_VALIDATED => false, SHIPBUBBLE_SANDBOX_ADDRESS_VALIDATED => false);
	if (!get_option(SHIPBUBBLE_INIT)) {
		add_option(SHIPBUBBLE_INIT, $data);
	}

	add_option('shipbubble_first_time_redirection', true);
}

register_activation_hook(__FILE__, 'shipbubble_on_activation');


// action on deactivation
function shipbubble_on_deactivation()
{
	if (!current_user_can('activate_plugins')) return;

    flush_rewrite_rules();
}

register_deactivation_hook(__FILE__, 'shipbubble_on_deactivation');

// default plugin options
function shipbubble_options_default(): array
{
	return array(
		'shipbubble_api_key'     	=> '',
	);
}

function shipbubble_wc_options_default(): array
{
	return array(
		'extra_charges' => '0',
		'courier_list' =>  array('all'),
		'shipping_price' => 'default',
		'store_category' => '',
		'user_can_ship' => 'yes',
		'activate_shipbubble' => 'no',
		'disable_other_shipping_methods' => 'no',
		'live_api_key' => '',
		'sandbox_api_key' => '',
		'live_mode' => 'yes',
		'local_pickup' => 'no',
		'local_pickup_text' => ''
	);
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

add_action('plugins_loaded', 'shipbubble_wc_api_init', 11);

function shipbubble_wc_api_init()
{
	// if( class_exists( 'WC_Payment_Gateway' ) ) {
	// admin
	require_once plugin_dir_path(__FILE__) . 'admin/woocommerce/orders.php';

	// public
	require_once plugin_dir_path(__FILE__) . 'public/woocommerce/checkout.php';
	require_once plugin_dir_path(__FILE__) . 'public/woocommerce/enqueue-styles.php';
	// }

	$version = '2.6';
	$shipbubble_version = get_option(SHIPBUBBLE_PLUGIN_VERSION, '');

	// Check if the shipbubble_version is empty or less than the specified version
	if (empty($shipbubble_version) || version_compare($shipbubble_version, $version, '<')) {
		// Get the current options
		$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

		// If the API key is not set, try to get it from the old options
		if (empty($options['live_api_key'])) {
			$old_options = get_option('shipbubble_options', shipbubble_options_default());
			$options['live_api_key'] = isset($old_options['shipbubble_api_key']) ? sanitize_text_field($old_options['shipbubble_api_key']) : '';
			update_option(WC_SHIPBUBBLE_ID, $options);
		}

		if (!empty($options['live_api_key'])) {
			$data = array('initialized' => true, 'account_status' => true, SHIPBUBBLE_ADDRESS_VALIDATED => !empty($options['address_code']), SHIPBUBBLE_SANDBOX_ADDRESS_VALIDATED => false);
			update_option(SHIPBUBBLE_INIT, $data);
		}

		// Update the shipbubble version in the database
		update_option(SHIPBUBBLE_PLUGIN_VERSION, $version);
	}

	$update_time = get_option('shipbubble_db_update_time');
	if (empty($update_time)) {
		add_option('shipbubble_db_update_time', time());
	}

}

function shipbubble_settings_redirect() {

	if( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

	if (get_option('shipbubble_first_time_redirection', false)) {
		delete_option('shipbubble_first_time_redirection');
		exit(wp_redirect(SHIPBUBBLE_EXT_BASE_URL  . '/wp-admin/admin.php?page=shipbubble-settings'));
	}
}
add_action('admin_init', 'shipbubble_settings_redirect');

function shipbubble_show_plugin_settings_link($links, $file) {
	if (plugin_basename(__FILE__) == $file) {
		$settings_link = '<a href="admin.php?page=shipbubble-settings">' . __('Settings', 'shipbubble') . '</a>';
		array_unshift($links, $settings_link);
	}
	return $links;
}
add_filter('plugin_action_links', 'shipbubble_show_plugin_settings_link', 10, 2);


// Disable Shipping methods if not in checkout page
add_filter('woocommerce_package_rates', 'shipbubble_keep_shipping_methods_on_checkout', 100, 2);
function shipbubble_keep_shipping_methods_on_checkout($rates, $package)
{
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
	$disableOtherShippingMethods = isset($options['disable_other_shipping_methods']) ? sanitize_text_field($options['disable_other_shipping_methods']) : 'no';

	if (!is_checkout()) {
		// Loop through shipping methods rates
		foreach ($rates as $rate_key => $rate) {
			if (SHIPBUBBLE_ID === $rate->method_id) {
				unset($rates[$rate_key]);
			} else {
				if (strtolower($disableOtherShippingMethods) == 'yes') {
					unset($rates[$rate_key]); // Remove other shipping methods
				}
			}
		}
	}
	return $rates;
}

// Shipping packages
add_filter('woocommerce_shipping_packages', 'shipbubble_keep_shipping_packages_on_checkout', 20, 1);
add_filter('woocommerce_cart_shipping_packages', 'shipbubble_keep_shipping_packages_on_checkout', 20, 1);
function shipbubble_keep_shipping_packages_on_checkout($packages)
{
	if (!is_checkout()) {
		foreach ($packages as $key => $package) {
			WC()->session->__unset('shipping_for_package_' . $key); // Remove
			unset($packages[$key]); // Remove
		}
	}
	return $packages;
}

// prevent proceed to order if shipping method has not been selected
add_filter('woocommerce_order_button_html', 'shipbubble_disable_place_order_button_html');
function shipbubble_disable_place_order_button_html($button)
{
	// HERE define your targeted shipping method id
	$targeted_shipping_method = "flat_rate:14";

	// Get the chosen shipping method (if it exist)
	$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods') ?? array();

	// If the targeted shipping method is selected, we disable the button
	if (in_array($targeted_shipping_method, $chosen_shipping_methods)) {
		$style  = 'style="background:Silver !important; color:white !important; cursor: not-allowed !important; text-align:center;"';
		$text   = apply_filters('woocommerce_order_button_text', __('Place order', 'woocommerce'));
		$button = '<a class="button" ' . $style . '>' . $text . '</a>';
	}
	return $button;
}

// Append custom checkout fields to db when order has been paid for
add_action('woocommerce_checkout_update_order_meta', 'shipbubble_update_order_meta_on_checkout', 10, 1);
function shipbubble_update_order_meta_on_checkout($order_id)
{
	if (!$order_id)
	{
		return;
	}

	if (!isset($_POST['shipping_method']))
	{
		return;
	}

	// Get an instance of the WC_Order object
	$order = wc_get_order($order_id);

	if (!$order->has_shipping_method(SHIPBUBBLE_ID))
	{
		return;
	}

	$userCanShip = 'yes';
	$isValid = false;

	if (isset($_POST['request_token'], $_POST['shipbubble_service_code'], $_POST['shipbubble_courier_id'])) 
	{
		$requestToken = sanitize_text_field($_POST['request_token']);
		$serviceCode = sanitize_text_field($_POST['shipbubble_service_code']);
		$courierId = sanitize_text_field($_POST['shipbubble_courier_id']);
		$isValid = true;
	}

	if (!$isValid) {
		return;
	}
	
	// initialize address
	// TODO: use js address
	$streetAddress = sanitize_text_field($_POST['shipping_address_1']);
	$city = sanitize_text_field($_POST['shipping_city']);
	$stateTag = sanitize_text_field($_POST['shipping_state']);
	$countryTag = sanitize_text_field($_POST['shipping_country']);
	$phone = sanitize_text_field($_POST['billing_phone']);

	if (strlen($streetAddress) < 1) {
		$streetAddress = sanitize_text_field($_POST['billing_address_1']);
	}

	if (strlen($city) < 1) {
		$city = sanitize_text_field($_POST['billing_city']);
	}

	if (strlen($stateTag) < 1) {
		$stateTag = sanitize_text_field($_POST['billing_state']);
	}

	if (strlen($countryTag) < 1) {
		$countryTag = sanitize_text_field($_POST['billing_country']);
	}

	$shipmentMeta = [];
	$address = sb_create_address($streetAddress, $city, $stateTag, $countryTag);

	if (strtolower($userCanShip) == 'yes') {
		$shipmentMeta['user_can_ship'] = true;

		if (!empty($requestToken) && !empty($serviceCode) && !empty($courierId)) {
			$shipmentMeta['shipment_payload'] = array(
				'request_token' => $requestToken,
				'service_code' => $serviceCode,
				'courier_id' => $courierId,
			);

			// new shipment details meta
			$shipbubbleShipmentDetails = [
				'request_token' => $_POST['request_token'],
				'courier_id' => $_POST['shipbubble_courier_id'],
				'courier_name' => $_POST['shipbubble_selected_courier'],
				'service_code' => $_POST['shipbubble_service_code'],
				'shipment_cost' => $_POST['shipbubble_cost'],
				'request_datetime' => $_POST['shipbubble_rate_datetime']
			];

			// set order request time
			$shipbubbleShipmentDetails['order_request_time'] = date('Y-m-d H:i:s');

			// set shipbubble_shipment_details data to db (for admin create)
			shipbubble_update_order_meta($order_id, 'shipbubble_shipment_details', serialize($shipbubbleShipmentDetails));

			// set payload to create shipbubble shipment (for checkout create)
			shipbubble_update_order_meta($order_id, 'sb_shipment_meta', serialize($shipmentMeta));

			// setting the delivery address
			shipbubble_update_order_meta($order_id, 'shipbubble_delivery_address', $address);

			// setting the phone number
			shipbubble_update_order_meta($order_id, 'shipbubble_delivery_phone', $phone);
		}
	}
}

// add_action( 'woocommerce_checkout_order_processed', 'handle_processed', 10, 1 );
// function handle_processed($order_id)
// {
// 	$order = new WC_Order( $order_id );
// 	$shipping_items = $order->get_items('shipping');
// 	$shipping_total = $order->get_shipping_total();
	
//     if ($order->has_shipping_method(SHIPBUBBLE_ID) && (empty($shipping_items) || "0" == $shipping_total))
// 	{
// 		$order->delete();
//         wp_send_json_error();
//     }
// }

add_action('woocommerce_thankyou', 'shipbubble_create_shipment_after_order_created', 10, 1);
add_action('woocommerce_order_status_pending_to_processing', 'shipbubble_create_shipment_after_order_created', 10, 1);
/**
 * Creates a shipment via Shipbubble after an order is created.
 *
 * This function checks if the shipment for the order has already been created. If not, and if the order is not marked for local pickup,
 * it processes the shipment creation through the Shipbubble API and updates the order's metadata accordingly.
 *
 * @param int $order_id The ID of the WooCommerce order.
 *
 * @return void
 */
function shipbubble_create_shipment_after_order_created($order_id)
{
	if (!$order_id)
		return;

	if (!shipbubble_get_order_meta($order_id, 'shipbubble_shipment_details', true))
		return;

	// Allow code execution only once 
	if (!shipbubble_get_order_meta($order_id, '_thankyou_action_done', true)) {

		// Get an instance of the WC_Order object
		$order = wc_get_order($order_id);

		if (!shipbubble_get_order_meta($order_id, 'shipbubble_local_pickup')) {
			$shipmentMeta = unserialize(shipbubble_get_order_meta($order_id, 'sb_shipment_meta'));

			if (count($shipmentMeta)) {
				if ($shipmentMeta['user_can_ship']) {
					$shipmentPayload = $shipmentMeta['shipment_payload'];

					$response = shipbubble_create_shipment($shipmentPayload);
					if (isset($response->response_code) && $response->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
						// set shipbubble order id
						shipbubble_update_order_meta($order_id, 'shipbubble_order_id', $response->data->order_id);

						// set shipping status
						shipbubble_update_order_meta($order_id, 'shipbubble_tracking_status', 'pending');

						$shipmentDetailsArray = unserialize(shipbubble_get_order_meta($order_id, 'shipbubble_shipment_details'));

						if (count($shipmentDetailsArray)) {
							$shipmentDetailsArray['create_shipment_time'] = date('Y-m-d H:i:s');
							shipbubble_update_order_meta($order_id, 'shipbubble_shipment_details', serialize($shipmentDetailsArray));
						}
					}
				}
			} else {
				// set empty shipbubble service code
				shipbubble_update_order_meta($order_id, 'shipbubble_shipment_details', serialize(['service_code' => '']));

				// set empty shipbubble order id
				shipbubble_update_order_meta($order_id, 'shipbubble_order_id', '');

				// set empty shipping status
				shipbubble_update_order_meta($order_id, 'shipbubble_tracking_status', '');
			}
		} else {
			$order->update_meta_data('shipbubble_local_pickup', true);
		}

		// Flag the action as done (to avoid repetitions on reload for example)
		$order->update_meta_data('_thankyou_action_done', true);
		$order->save();
	}
}

add_action( 'woocommerce_before_checkout_process', 'shipbubble_validate_checkout_order' , 10, 1 );
add_action( 'woocommerce_checkout_order_processed', 'shipbubble_validate_checkout_order', 10, 1 );
/**
 * Validates the checkout order based on shipping and payment conditions.
 *
 * This function checks if the order contains virtual products, validates the shipping method, and deletes the order if certain conditions are met.
 * Additionally, it handles the case for local pickup orders.
 *
 * @param int $order_id The ID of the WooCommerce order being validated.
 *
 * @return void This function does not return any value. It either deletes the order or updates its meta data.
 */
function shipbubble_validate_checkout_order($order_id)
{
	$order = wc_get_order($order_id);

	if (!$order) {
		return;
	}
	$shipping_items = $order->get_items('shipping');
	$shipping_total = $order->get_shipping_total();
	$payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
	$enabled_gateways = [];
	$delete_order = false;

	$gateways = WC()->payment_gateways->get_available_payment_gateways();
	if($gateways) {
		foreach($gateways as $gateway ) {
			if( $gateway->is_available() ) {
				$enabled_gateways[] = $gateway->id;
			}
		}
	}

	// Assume all products are virtual until proven otherwise
    $all_virtual = true;

	// Loop through order items
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();

        // Check if the product is virtual
        if (!$product || !$product->is_virtual()) {
            $all_virtual = false;
            break; // Exit the loop early if a non-virtual product is found
        }
    }

	// Get the selected shipping method from the checkout object
	$chosen_shipping_method = WC()->checkout->get_value('shipping_method');
	$is_local_pickup = 'local_pickup' === $_POST['shipbubble_courier_id'];

	// check
	if (!$all_virtual && !empty($payment_method) && !empty($enabled_gateways) && !$is_local_pickup) {
		if (in_array($payment_method, $enabled_gateways)) {
			// check shipping items is empty or shipping total is 0
            if (empty($shipping_items) || (!empty($chosen_shipping_method) && $chosen_shipping_method[0] == SHIPBUBBLE_ID && $shipping_total == "0")) {
                $delete_order = true;
				error_log(print_r('selected sb & 0', true));
            }
		}
	}

	if ($is_local_pickup) {
		$order->update_meta_data('shipbubble_local_pickup', true);
		$pickup_address = $_POST['shippbuble_local_pickup_address'] ?? shipbubble_get_local_pickup_default();
		$order->update_meta_data('shipbubble_local_pickup_address', $pickup_address);
	}

	if ($delete_order) {
        $order->delete();
        wp_send_json_error();
    } else {
		$order->save();
	}
}

function shipbubble_append_enqueue_script()
{
	wp_enqueue_script('sweetalert2', plugins_url('public/js/sweetalert2.min.js', __FILE__), array());
	wp_enqueue_script('blockui', plugins_url('public/js/blockui/jquery.blockUI.js', __FILE__), array());
	// here you can enqueue more js / css files
}

add_action('wp_enqueue_scripts', 'shipbubble_append_enqueue_script');
add_action('admin_enqueue_scripts', 'shipbubble_append_enqueue_script');

add_action('before_woocommerce_init',  'shipbubble_checkout_block_incompatibilty');

function shipbubble_checkout_block_incompatibilty() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, false );
	}
}

add_action('admin_init', 'hook_shipbubble_admin_notices');

function hook_shipbubble_admin_notices() {
	if (!current_user_can('update_plugins')) {
		return;
	}

	global $pagenow;

//	// If it's not the admin dashboard page and not the Shipbubble shipping services page, then bail
//	if ('index.php' != $pagenow && !('admin.php' == $pagenow && isset($_GET['page']) && $_GET['page'] == 'wc-settings' && isset($_GET['tab']) && $_GET['tab'] == 'shipping' && isset($_GET['section']) && $_GET['section'] == 'shipbubble_shipping_services')) {
//		return;
//	}

	add_action('all_admin_notices', 'render_shipbubble_admin_notices');
}

function render_shipbubble_admin_notices() {
    echo generate_shipbubble_notice();
}

/**
 * Conditionally removes the WooCommerce local pickup shipping method.
 *
 * @param array $methods The array of available shipping methods.
 *
 * @return array The modified array of shipping methods with local pickup removed if the option is active.
 */
function maybe_remove_woocommerce_local_pickup($methods) {
	if (shipbubble_is_option_active('local_pickup')) {
		unset($methods['local_pickup']);
	}
	return $methods;
}
add_filter('woocommerce_shipping_methods', 'maybe_remove_woocommerce_local_pickup');


function shipbubble_shipping_service_init()
{
	if ( ! class_exists( 'WC_SHIPBUBBLE_SHIPPING_METHOD' ) )  {
		class WC_SHIPBUBBLE_SHIPPING_METHOD extends WC_Shipping_Method
		{
			/**
			 * Constructor for your shipping class
			 *
			 * @access public
			 * @return void
			 */
			public function __construct()
			{
				$this->id                 = SHIPBUBBLE_ID; // Id for your shipping method. Should be uunique.
				$this->method_title       = __( 'Shipbubble' );  // Title shown in admin

				$this->method_description = __( '' ); // Description shown in admin

				// Define user set variables
				$this->enabled = 'yes';
				$this->title              = "Shipbubble"; // This can be added as an setting but for this example its forced.

				$this->init();
			}

			/**
			 * Init your settings
			 *
			 * @access public
			 * @return void
			 */
			public function init()
			{
				$this->display_errors();
			}

			/**
			 * calculate_shipping function.
			 *
			 * @access public
			 * @param array $package optional – multi-dimensional array of cart items to calc shipping for.
			 * @return void
			 */
			public function calculate_shipping( $package = array() )
			{
				// This is where you'll add your rates
				$rate = array(
					'id'     => $this->id,
					'label' => $this->title,
					'cost' => '50000',
					// 'calc_tax' => 'per_item'
				);
				// This will add custom cost to shipping method

				// Register the rate
				$this->add_rate( $rate );
			}
		}
	}
}

add_action( 'woocommerce_shipping_init', 'shipbubble_shipping_service_init' );


function shipbubble_couriers_methods( $methods )
{
	$methods[SHIPBUBBLE_ID] = 'WC_SHIPBUBBLE_SHIPPING_METHOD';
	return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'shipbubble_couriers_methods' );

add_action('admin_init', function () {
	if (
		is_admin() &&
		isset($_GET['page'], $_GET['tab'], $_GET['section']) &&
		$_GET['page'] === 'wc-settings' &&
		$_GET['tab'] === 'shipping' &&
		$_GET['section'] === 'shipbubble_shipping_services'
	) {
		wp_redirect(admin_url('admin.php?page=shipbubble-settings'));
		exit;
	}
});
