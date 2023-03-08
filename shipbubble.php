<?php 

	/**
	 * Plugin Name:  ShipBubble
	 * Description:  Plugin for enabling & managing logistics & deliveries for orders on eCommerce platforms
	 * Plugin URI:   https://profiles.wordpress.org/oghenemavo
	 * Contributors: oghenemavo
	 * Author:       Mavi Onogomuho
	 * Author URI:   https://linkedin.com/in/mavi-onogomuho
	 * Tags:         delivery, logistics, eCommerce
	 * Version:      1.0
	 * Stable tag:   1.0
	 * Requires at least: 5.6
	 * Tested up to:      8.1
	 * Text Domain:  shipbubble
	 * Domain Path:  /languages
	 * License:      GPL v3 or later
	 * License URI:  https://www.gnu.org/licenses/gpl-3.0.txt
	 */

	// exit if file is called directly
	if ( ! defined( 'ABSPATH' ) ) 
	{
		exit;
	}

	// if  admin area
	if ( is_admin() ) 
	{
		// include dependencies
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/settings-menu.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/settings-page.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/settings-register.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/settings-callback.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/async-validate-auth.php';
		
		// Woocommerce
		// require_once plugin_dir_path( __FILE__ ) . 'admin/woocommerce/shipping-settings.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/woocommerce/async-create-shipment.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/woocommerce/async-validate-address.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/woocommerce/enqueue-styles.php';
	}

	// includes
	require_once plugin_dir_path( __FILE__ ) . 'includes/constants.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/endpoints.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/core-methods.php';

	// public
	require_once plugin_dir_path( __FILE__ ) . 'public/async-checkout-couriers.php';


	// action on activation
	function shipbubble_on_activation() 
	{
		if ( ! current_user_can( 'activate_plugins' ) ) return;

		if (get_option('shipbubble_init')) {
			$data = array('initialized' => true, 'account_status' => false);
			update_option( 'shipbubble_init', $data );
		} else {
			$data = array('initialized' => true, 'account_status' => false);
			add_option( 'shipbubble_init', $data );
		}

	}

	register_activation_hook( __FILE__, 'shipbubble_on_activation' );


	// action on deactivation
	function shipbubble_on_deactivation() 
	{
		if ( ! current_user_can( 'activate_plugins' ) ) return;
		
		$data = array('initialized' => false, 'account_status' => false);
		update_option( 'shipbubble_init', $data );
	}

	register_deactivation_hook( __FILE__, 'shipbubble_on_deactivation' );

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
			'shipping_category' => '',
			'user_can_ship' => 'no',
			'activate_shipbubble' => 'no',
		);
	}

	if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) return;

	add_action( 'plugins_loaded', 'shipbubble_wc_api_init', 11 );

	function shipbubble_wc_api_init() {
		// if( class_exists( 'WC_Payment_Gateway' ) ) {
			// admin
			require_once plugin_dir_path( __FILE__ ) . 'admin/woocommerce/shipping-settings.php';
			require_once plugin_dir_path( __FILE__ ) . 'admin/woocommerce/orders.php';

			// public
			require_once plugin_dir_path( __FILE__ ) . 'public/woocommerce/checkout.php';
			require_once plugin_dir_path( __FILE__ ) . 'public/woocommerce/enqueue-styles.php';
		// }
	}

	// 

	// function lab_pacakge_cost() 
	// {
		
	// 	global $woocommerce;
		
	// 	// $flat_fee    = get_option( 'techiepress_vat_pricing_flat_fee' );
	// 	// $dynamic_fee = get_option( 'techiepress_vat_pricing_dynamic_fee' );
		
	// 	if ( ! $_POST || ( is_admin() && ! is_ajax() ) ) {
	// 		return;
	// 	}
		
	// 	if ( isset( $_POST['post_data'] ) ) {
	// 		parse_str( $_POST['post_data'], $post_data );
	// 	} else {
	// 		$post_data = $_POST;
	// 	}
		
	// 	if ( isset( $post_data['techiepress_vat_cancel'] ) ) {

	// 		// WC()->cart->calculate_shipping();
	// 		$taxable = 250 + ( $woocommerce->cart->cart_contents_total * 1 );
			
	// 		$woocommerce->cart->add_fee( __( 'VAT', 'om-service-widget' ), $taxable );
	// 	}
		
	// 	return;
		
	// }
	// add_action( 'woocommerce_cart_calculate_fees', 'lab_pacakge_cost');


	

	// Disable Shipping methods if not in checkout page
	add_filter( 'woocommerce_package_rates', 'keep_shipping_methods_on_checkout', 100, 2 );
	function keep_shipping_methods_on_checkout( $rates, $package ) {
		if ( ! is_checkout() ) {
			// Loop through shipping methods rates
			foreach( $rates as $rate_key => $rate ){
				unset($rates[$rate_key]); // Remove
			}
		}
		return $rates;
	}

	// Shipping packages
	add_filter( 'woocommerce_shipping_packages', 'keep_shipping_packages_on_checkout', 20, 1 );
	add_filter( 'woocommerce_cart_shipping_packages', 'keep_shipping_packages_on_checkout', 20, 1 );
	function keep_shipping_packages_on_checkout( $packages ) {
		if ( ! is_checkout() ) {
			foreach( $packages as $key => $package ) {
				WC()->session->__unset('shipping_for_package_'. $key); // Remove
				unset($packages[$key]); // Remove
			}
		}
		return $packages;
	}

	// prevent proceed to order if shipping method has not been selected
	add_filter('woocommerce_order_button_html', 'disable_place_order_button_html' );
	function disable_place_order_button_html( $button ) {
		// HERE define your targeted shipping method id
		$targeted_shipping_method = "flat_rate:14";

		// Get the chosen shipping method (if it exist)
		$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
		
		// If the targeted shipping method is selected, we disable the button
		if( in_array( $targeted_shipping_method, $chosen_shipping_methods ) ) {
			$style  = 'style="background:Silver !important; color:white !important; cursor: not-allowed !important; text-align:center;"';
			$text   = apply_filters( 'woocommerce_order_button_text', __( 'Place order', 'woocommerce' ) );
			$button = '<a class="button" '.$style.'>' . $text . '</a>';
		}
		return $button;
	}

	// Append custom checkout fields to db when order has been paid for
	add_action( 'woocommerce_checkout_update_order_meta', 'shipbubble_checkout_update_order_meta', 10, 1 );
	function shipbubble_checkout_update_order_meta( $order_id ) 
	{
		$options = get_option( WC_SHIPBUBBLE_ID, shipbubble_wc_options_default() );

        $userCanShip = isset( $options['user_can_ship'] ) ? sanitize_text_field( $options['user_can_ship'] ) : 'no';

		$requestToken = $_POST['request_token'];
		$serviceCode = $_POST['shipbubble_service_code'];
		$courierId = $_POST['shipbubble_courier_id'];

		// initialize address
		$streetAddress = $_POST['shipping_address_1'];
		$city = $_POST['shipping_city'];
		$stateTag = $_POST['shipping_state'];
		$countryTag = $_POST['shipping_country'];

		if (strlen($streetAddress) < 1) {
			$streetAddress = $_POST['billing_address_1'];
		}

		if (strlen($city) < 1) {
			$city = $_POST['billing_city'];
		}

		if (strlen($stateTag) < 1) {
			$stateTag = $_POST['billing_state'];
		}

		if (strlen($countryTag) < 1) {
			$countryTag = $_POST['billing_country'];
		}

		$address = sb_create_address($streetAddress, $city, $stateTag, $countryTag);

		// error_log(print_r($address, true));
		// error_log(print_r(json_decode(json_decode($_POST['shipbubble_shipment_details'])), true));
		// die;
		
		if( isset( $_POST['shipbubble_shipment_details'] ) ) {

			if (strtolower($userCanShip) == 'yes') {

				// error_log(print_r($requestToken, true));
				// error_log(print_r($serviceCode, true));
				// error_log(print_r($courierId, true));

				// die;

				if (isset($requestToken, $serviceCode, $courierId) && !empty($requestToken) && !empty($serviceCode) && !empty($courierId) ) {
					$shipmentPayload = array(
						'request_token' => $requestToken,
						'service_code' => $serviceCode,
						'courier_id' => $courierId,
					);
	
					$response = shipbubble_create_shipment($shipmentPayload); 

					if (isset($response->response_code) && $response->response_code == HTTP_RESPONSE_OK) {
						// set shipbubble order id
						update_post_meta( $order_id, 'shipbubble_order_id', $response->data->order_id );
	
						// set shipping status
						update_post_meta( $order_id, 'shipbubble_tracking_status', 'pending' );
					}
				}

			} else {
				// set shipbubble order id
				update_post_meta( $order_id, 'shipbubble_order_id', '' );
		
				// set shipping status
				update_post_meta( $order_id, 'shipbubble_tracking_status', '' );
			}

			// set shipbubble shipment details json
		   update_post_meta( $order_id, 'shipbubble_shipment_details', $_POST['shipbubble_shipment_details'] );
		   
		} else {
			$code = $serviceCode ?? 'speedaf-express';
			update_post_meta( $order_id, 'shipbubble_shipment_details', json_encode(['service_code' => $code]) );

			// set shipbubble order id
			update_post_meta( $order_id, 'shipbubble_order_id', '' );
 
			// set shipping status
			update_post_meta( $order_id, 'shipbubble_tracking_status', '' );
		}
		
		// setting the delivery address
		update_post_meta( $order_id, 'shipbubble_delivery_address', $address );
	}

	function themeslug_enqueue_script() {
		wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', false );
		// here you can enqueue more js / css files 
	}
	
	add_action( 'wp_enqueue_scripts', 'themeslug_enqueue_script' );
	add_action( 'admin_enqueue_scripts', 'themeslug_enqueue_script' );

	