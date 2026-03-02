<?php
/**
 * WooCommerce Checkout Blocks integration for Shipbubble.
 *
 * Registers an IntegrationInterface so the blocks checkout can load our
 * frontend script, exposes a Store-API extension schema for the checkout
 * payload, handles the real-time cart update callback (extensionCartUpdate),
 * and saves order meta when the blocks checkout is submitted.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fired on `woocommerce_blocks_loaded`.
 * Registers everything needed for blocks compatibility.
 */
function shipbubble_register_blocks_integration() {

	// Guard: IntegrationInterface must exist (WC Blocks 7+).
	if ( ! interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
		return;
	}

	// -------------------------------------------------------------------------
	// 1.  IntegrationInterface class
	// -------------------------------------------------------------------------
	class Shipbubble_Blocks_Integration implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {

		public function get_name() {
			return 'shipbubble';
		}

		public function initialize() {
			$script_path = plugin_dir_path( SHIPBUBBLE_PLUGIN_FILE ) . 'public/js/shipbubble-blocks-checkout.js';
			$script_url  = plugins_url( 'public/js/shipbubble-blocks-checkout.js', SHIPBUBBLE_PLUGIN_FILE );
			$version     = file_exists( $script_path ) ? filemtime( $script_path ) : '1.0';

			wp_register_script(
				'shipbubble-blocks-checkout',
				$script_url,
				[ 'wc-blocks-checkout', 'wp-element', 'wp-plugins', 'wp-data', 'jquery' ],
				$version,
				true
			);
		}

		public function get_script_handles() {
			return [ 'shipbubble-blocks-checkout' ];
		}

		public function get_editor_script_handles() {
			return [];
		}

		public function get_script_data() {
			$options = get_option( WC_SHIPBUBBLE_ID, shipbubble_wc_options_default() );

			return [
				'ajaxurl'         => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'ajax_public' ),
				'logo'            => SHIPBUBBLE_LOGO_URL,
				'isActive'        => ( 'yes' === ( $options['activate_shipbubble'] ?? 'no' ) ),
				'isLocalPickup'   => shipbubble_is_local_pickup_active(),
				'localPickupText' => shipbubble_get_option( 'local_pickup_text' ) ?: 'Pickup in store',
				'pickupAddress'   => shipbubble_get_local_pickup_default(),
			];
		}
	}

	// Register with the checkout block.
	add_action(
		'woocommerce_blocks_checkout_block_registration',
		function ( $registry ) {
			$registry->register( new Shipbubble_Blocks_Integration() );
		}
	);

	// -------------------------------------------------------------------------
	// 2.  Store-API: checkout extension data schema
	//     Allows the blocks frontend to send shipbubble data in
	//     POST /wc/store/v1/checkout  → extensions.shipbubble
	// -------------------------------------------------------------------------
	if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
		woocommerce_store_api_register_endpoint_data( [
			'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
			'namespace'       => 'shipbubble',
			'schema_callback' => function () {
				return [
					'request_token'       => [ 'description' => 'Request token',       'type' => 'string', 'readonly' => false ],
					'service_code'        => [ 'description' => 'Service code',        'type' => 'string', 'readonly' => false ],
					'courier_id'          => [ 'description' => 'Courier ID',          'type' => 'string', 'readonly' => false ],
					'selected_courier'    => [ 'description' => 'Courier name',        'type' => 'string', 'readonly' => false ],
					'cost'                => [ 'description' => 'Shipping cost',       'type' => 'string', 'readonly' => false ],
					'rate_datetime'       => [ 'description' => 'Rate datetime',       'type' => 'string', 'readonly' => false ],
					'is_local_pickup'     => [ 'description' => 'Local pickup flag',   'type' => 'string', 'readonly' => false ],
					'local_pickup_address'=> [ 'description' => 'Pickup address',      'type' => 'string', 'readonly' => false ],
				];
			},
			'schema_type'     => ARRAY_A,
		] );
	}

	// -------------------------------------------------------------------------
	// 3.  Store-API: cart extension update callback
	//     Called when the blocks frontend fires extensionCartUpdate({ namespace: 'shipbubble', data: … })
	//     Stores the selected courier in the WC session so the package-rates
	//     filter can apply the correct cost.
	// -------------------------------------------------------------------------
	if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
		woocommerce_store_api_register_update_callback( [
			'namespace' => 'shipbubble',
			'callback'  => 'shipbubble_blocks_handle_cart_update',
		] );
	}
}
add_action( 'woocommerce_blocks_loaded', 'shipbubble_register_blocks_integration' );


/**
 * Cart extension update callback.
 *
 * Stores the selected courier in the WC session and clears the cached
 * shipping rates so WooCommerce recalculates using shipbubble_change_rates().
 *
 * @param array $data  Data sent from the frontend via extensionCartUpdate().
 */
function shipbubble_blocks_handle_cart_update( $data ) {
	if ( ! WC()->session ) {
		return;
	}

	if ( ! empty( $data['clear'] ) ) {
		WC()->session->__unset( 'shipbubble_blocks_courier' );
	} else {
		WC()->session->set( 'shipbubble_blocks_courier', [
			'courier_id'   => sanitize_text_field( $data['courier_id']   ?? '' ),
			'service_code' => sanitize_text_field( $data['service_code'] ?? '' ),
			'courier_name' => sanitize_text_field( $data['courier_name'] ?? '' ),
			'request_token'=> sanitize_text_field( $data['request_token']?? '' ),
			'cost'         => sanitize_text_field( $data['cost']         ?? '0' ),
			'is_local_pickup' => ! empty( $data['is_local_pickup'] ),
		] );
	}

	// Clear shipping cache so WC recalculates rates (calls shipbubble_change_rates).
	if ( WC()->cart ) {
		$packages = WC()->cart->get_shipping_packages();
		foreach ( $packages as $key => $package ) {
			WC()->session->__unset( 'shipping_for_package_' . $key );
		}
	}
}


/**
 * Save Shipbubble order meta when the blocks checkout order is placed.
 * Mirrors the logic in shipbubble_update_order_meta_on_checkout() (classic).
 *
 * @param WC_Order                                    $order
 * @param \Automattic\WooCommerce\StoreApi\Routes\RouteInterface|\WP_REST_Request $request
 */
add_action( 'woocommerce_store_api_checkout_update_order_from_request', 'shipbubble_blocks_save_order_meta', 10, 2 );

function shipbubble_blocks_save_order_meta( $order, $request ) {
	if ( ! $order->has_shipping_method( SHIPBUBBLE_ID ) ) {
		return;
	}

	$extensions = $request->get_param( 'extensions' );
	$data       = $extensions['shipbubble'] ?? [];

	if ( empty( $data ) ) {
		return;
	}

	$is_local_pickup     = ( 'true' === ( $data['is_local_pickup'] ?? '' ) );
	$request_token       = sanitize_text_field( $data['request_token']        ?? '' );
	$service_code        = sanitize_text_field( $data['service_code']         ?? '' );
	$courier_id          = sanitize_text_field( $data['courier_id']           ?? '' );
	$selected_courier    = sanitize_text_field( $data['selected_courier']     ?? '' );
	$cost                = sanitize_text_field( $data['cost']                 ?? '' );
	$rate_datetime       = sanitize_text_field( $data['rate_datetime']        ?? '' );
	$local_pickup_addr   = sanitize_text_field( $data['local_pickup_address'] ?? '' );

	if ( $is_local_pickup ) {
		$order->update_meta_data( 'shipbubble_local_pickup', true );
		$order->update_meta_data(
			'shipbubble_local_pickup_address',
			$local_pickup_addr ?: shipbubble_get_local_pickup_default()
		);
	} elseif ( $request_token && $service_code && $courier_id ) {
		$shipment_details = [
			'request_token'    => $request_token,
			'courier_id'       => $courier_id,
			'courier_name'     => $selected_courier,
			'service_code'     => $service_code,
			'shipment_cost'    => $cost,
			'request_datetime' => $rate_datetime,
			'order_request_time' => date( 'Y-m-d H:i:s' ),
		];
		$order->update_meta_data( 'shipbubble_shipment_details', serialize( $shipment_details ) );

		$shipment_meta = [
			'user_can_ship'    => true,
			'shipment_payload' => [
				'request_token' => $request_token,
				'service_code'  => $service_code,
				'courier_id'    => $courier_id,
			],
		];
		$order->update_meta_data( 'sb_shipment_meta', serialize( $shipment_meta ) );

		// Delivery address and phone (from order, already set by blocks checkout).
		$address = $order->get_shipping_address_1() . ', ' . $order->get_shipping_city();
		$order->update_meta_data( 'shipbubble_delivery_address', $address );
		$order->update_meta_data( 'shipbubble_delivery_phone', $order->get_billing_phone() );
	}

	// Clear session courier flag.
	if ( WC()->session ) {
		WC()->session->__unset( 'shipbubble_blocks_courier' );
	}

	$order->save();
}
