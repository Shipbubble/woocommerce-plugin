<?php

	/**
	 * Plugin Name:  Shipbubble
	 * Description:  Shipbubble is a platform that enables retailers to conveniently delight their customers with multiple shipping options, thereby increasing conversion rates
	 * Contributors: Shipbubble, Mavi Onogomuho
	 * Donate link: https://www.shipbubble.com/
	 * Tags: logistics, deliveries, shipping rates, multiple couriers, post purchase experience
	 * Requires at least: 4.0
	 * Tested up to: 6.1
	 * Stable tag: 1.0
	 * Requires PHP: 5.6
	 * Text Domain:  shipbubble
	 * Domain Path:  /languages
	 * License: GPLv3 or later
	 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
	 */

	// exit if file is called directly
	if (!defined('ABSPATH')) {
		exit;
	}

	// if  admin area
	if (is_admin()) {
		// include dependencies
		require_once plugin_dir_path(__FILE__) . 'admin/wordpress/settings-menu.php';
		require_once plugin_dir_path(__FILE__) . 'admin/wordpress/settings-page.php';
		require_once plugin_dir_path(__FILE__) . 'admin/wordpress/settings-register.php';
		require_once plugin_dir_path(__FILE__) . 'admin/wordpress/settings-callback.php';
		require_once plugin_dir_path(__FILE__) . 'admin/wordpress/async-validate-auth.php';

		// Woocommerce
		// require_once plugin_dir_path( __FILE__ ) . 'admin/woocommerce/shipping-settings.php';
		require_once plugin_dir_path(__FILE__) . 'admin/woocommerce/async-create-shipment.php';
		require_once plugin_dir_path(__FILE__) . 'admin/woocommerce/async-validate-address.php';
		require_once plugin_dir_path(__FILE__) . 'admin/woocommerce/enqueue-styles.php';
	}

	// includes
	require_once plugin_dir_path(__FILE__) . 'includes/constants.php';
	require_once plugin_dir_path(__FILE__) . 'includes/endpoints.php';
	require_once plugin_dir_path(__FILE__) . 'includes/core-methods.php';

	// public
	require_once plugin_dir_path(__FILE__) . 'public/async-checkout-couriers.php';


	// action on activation
	function shipbubble_on_activation()
	{
		if (!current_user_can('activate_plugins')) return;

		if (get_option('shipbubble_init')) {
			$data = array('initialized' => true, 'account_status' => false);
			update_option('shipbubble_init', $data);
		} else {
			$data = array('initialized' => true, 'account_status' => false);
			add_option('shipbubble_init', $data);
		}
	}

	register_activation_hook(__FILE__, 'shipbubble_on_activation');


	// action on deactivation
	function shipbubble_on_deactivation()
	{
		if (!current_user_can('activate_plugins')) return;

		$data = array('initialized' => false, 'account_status' => false);
		update_option('shipbubble_init', $data);
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
			'shipping_category' => '',
			'user_can_ship' => 'yes',
			'activate_shipbubble' => 'no',
			'disable_other_shipping_methods' => 'no',
		);
	}

	if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) return;

	add_action('plugins_loaded', 'shipbubble_wc_api_init', 11);

	function shipbubble_wc_api_init()
	{
		// if( class_exists( 'WC_Payment_Gateway' ) ) {
		// admin
		require_once plugin_dir_path(__FILE__) . 'admin/woocommerce/shipping-settings.php';
		require_once plugin_dir_path(__FILE__) . 'admin/woocommerce/orders.php';

		// public
		require_once plugin_dir_path(__FILE__) . 'public/woocommerce/checkout.php';
		require_once plugin_dir_path(__FILE__) . 'public/woocommerce/enqueue-styles.php';
		// }
	}


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
		$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

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
		if ( ! $order_id )
			return;

		// $options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
	
		$userCanShip = 'yes';
	
		$requestToken = sanitize_text_field($_POST['request_token']);
		$serviceCode = sanitize_text_field($_POST['shipbubble_service_code']);
		$courierId = sanitize_text_field($_POST['shipbubble_courier_id']);
	
		// initialize address
		$streetAddress = sanitize_text_field($_POST['shipping_address_1']);
		$city = sanitize_text_field($_POST['shipping_city']);
		$stateTag = sanitize_text_field($_POST['shipping_state']);
		$countryTag = sanitize_text_field($_POST['shipping_country']);
	
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
		if (isset($_POST['shipbubble_shipment_details'])) {
			$address = sb_create_address($streetAddress, $city, $stateTag, $countryTag);
	
			if (strtolower($userCanShip) == 'yes') {
				$shipmentMeta['user_can_ship'] = true;
	
				if (isset($requestToken, $serviceCode, $courierId) && !empty($requestToken) && !empty($serviceCode) && !empty($courierId)) {
					$shipmentMeta['shipment_payload'] = array(
						'request_token' => $requestToken,
						'service_code' => $serviceCode,
						'courier_id' => $courierId,
					);

					// set shipbubble shipment details json
					update_post_meta($order_id, 'shipbubble_shipment_details', sanitize_text_field($_POST['shipbubble_shipment_details']));

					// set payload to create shipbubble shipment
					update_post_meta($order_id, 'sb_shipment_meta', json_encode($shipmentMeta));

					// setting the delivery address
					update_post_meta($order_id, 'shipbubble_delivery_address', $address);
				}
			}
		}
	}

	add_action('woocommerce_thankyou', 'shipbubble_create_shipment_after_order_created', 10, 1);
	function shipbubble_create_shipment_after_order_created($order_id)
	{
		if ( ! $order_id )
			return;
				
		// error_log(print_r(get_post_meta( $order_id ), true));
		// error_log(print_r(wc_get_order( $order_id ), true));
		// error_log(print_r('check two', true));
		// error_log(print_r(json_decode(get_post_meta( $order_id, 'sb_shipment_meta' )[0], true)['shipment_payload']['request_token'], true));

		if( ! get_post_meta( $order_id, 'shipbubble_shipment_details', true ) )
			return;

		// Allow code execution only once 
		if( ! get_post_meta( $order_id, '_thankyou_action_done', true ) ) {

			// Get an instance of the WC_Order object
			$order = wc_get_order( $order_id );

			$shipmentMeta = json_decode(get_post_meta( $order_id, 'sb_shipment_meta' )[0], true);

			if (count($shipmentMeta)) {
				if ($shipmentMeta['user_can_ship']) {
					$shipmentPayload = $shipmentMeta['shipment_payload'];

					$response = shipbubble_create_shipment($shipmentPayload);
					if (isset($response->response_code) && $response->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
						// set shipbubble order id
						update_post_meta($order_id, 'shipbubble_order_id', $response->data->order_id);
	
						// set shipping status
						update_post_meta($order_id, 'shipbubble_tracking_status', 'pending');
					}
				}
			} else {
				// set empty shipbubble service code
				update_post_meta($order_id, 'shipbubble_shipment_details', json_encode(['service_code' => '']));
		
				// set empty shipbubble order id 
				update_post_meta($order_id, 'shipbubble_order_id', '');
		
				// set empty shipping status
				update_post_meta($order_id, 'shipbubble_tracking_status', '');
			}

			// Flag the action as done (to avoid repetitions on reload for example)
			$order->update_meta_data( '_thankyou_action_done', true );
			$order->save();
		}
	}

	function shipbubble_append_enqueue_script()
	{
		wp_enqueue_script('sweetalert2', plugins_url( 'public/js/sweetalert2.min.js', __FILE__ ), array());
		// here you can enqueue more js / css files 
	}

	add_action('wp_enqueue_scripts', 'shipbubble_append_enqueue_script');
	add_action('admin_enqueue_scripts', 'shipbubble_append_enqueue_script');
