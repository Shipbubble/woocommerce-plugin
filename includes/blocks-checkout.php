<?php

defined('ABSPATH') || exit;

/**
 * Return whether this WooCommerce version supports the Shipbubble Blocks path.
 *
 * @return bool
 */
function shipbubble_blocks_is_supported(): bool
{
	return defined('WC_VERSION')
		&& version_compare(WC_VERSION, '9.2', '>=')
		&& function_exists('woocommerce_store_api_register_update_callback');
}

/**
 * Return whether the rendered checkout page uses the Checkout block.
 *
 * @return bool
 */
function shipbubble_blocks_is_checkout_page(): bool
{
	return shipbubble_blocks_is_supported()
		&& function_exists('is_checkout')
		&& is_checkout()
		&& function_exists('has_block')
		&& has_block('woocommerce/checkout');
}

/**
 * Return whether the current Woo session is serving the Checkout Blocks flow.
 *
 * @return bool
 */
function shipbubble_blocks_is_checkout_context(): bool
{
	return function_exists('WC')
		&& WC()->session
		&& 'yes' === WC()->session->get('shipbubble_blocks_checkout_context');
}

/**
 * Require WooCommerce's existing phone field for physical checkouts using an
 * active Shipbubble integration. WooCommerce uses this option for both its
 * classic billing field and the Blocks address schema/validation.
 *
 * @param string $visibility Configured WooCommerce phone visibility.
 * @return string
 */
function shipbubble_blocks_require_phone_field($visibility): string
{
	if (!function_exists('WC')) {
		return (string) $visibility;
	}

	if (function_exists('is_cart') && is_cart()) {
		return (string) $visibility;
	}
	$is_checkout_page = function_exists('is_checkout') && is_checkout();
	$is_classic_checkout_request = isset($_REQUEST['wc-ajax'])
		&& 'checkout' === sanitize_key(wp_unslash($_REQUEST['wc-ajax']));
	if (!$is_checkout_page && !$is_classic_checkout_request && !shipbubble_blocks_is_checkout_context()) {
		return (string) $visibility;
	}

	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
	$is_active = apply_filters('is_shipbubble_active', $options['activate_shipbubble'] ?? 'no');
	if ('yes' !== $is_active || (WC()->cart && !WC()->cart->needs_shipping())) {
		return (string) $visibility;
	}

	return 'required';
}
add_filter('option_woocommerce_checkout_phone_field', 'shipbubble_blocks_require_phone_field');
add_filter('default_option_woocommerce_checkout_phone_field', 'shipbubble_blocks_require_phone_field');

/**
 * Keep the classic checkout field required on WooCommerce versions that do
 * not use the phone-visibility option for their billing field definition.
 *
 * @param array $fields Classic checkout fields.
 * @return array
 */
function shipbubble_require_classic_checkout_phone(array $fields): array
{
	if (!shipbubble_blocks_is_checkout_page()
		&& 'required' === shipbubble_blocks_require_phone_field('optional')
		&& isset($fields['billing']['billing_phone'])) {
		$fields['billing']['billing_phone']['required'] = true;
	}

	return $fields;
}
add_filter('woocommerce_checkout_fields', 'shipbubble_require_classic_checkout_phone', 20);

/**
 * Clear cached package rates so the Store API response is recalculated.
 *
 * @return void
 */
function shipbubble_blocks_clear_shipping_cache()
{
	if (!function_exists('WC') || !WC()->session || !WC()->cart) {
		return;
	}

	shipbubble_blocks_invalidate_package_cache();

	$chosen_methods = (array) WC()->session->get('chosen_shipping_methods', array());
	foreach ($chosen_methods as $package_key => $chosen_method) {
		if (0 === strpos((string) $chosen_method, SHIPBUBBLE_ID . ':') || SHIPBUBBLE_ID === $chosen_method) {
			unset($chosen_methods[$package_key]);
		}
	}
	WC()->session->set('chosen_shipping_methods', $chosen_methods);
}

/**
 * Recalculate packages without discarding a shopper's selected courier.
 *
 * @return void
 */
function shipbubble_blocks_invalidate_package_cache()
{
	if (!function_exists('WC') || !WC()->session || !WC()->cart) {
		return;
	}

	foreach (WC()->cart->get_shipping_packages() as $package_key => $package) {
		WC()->session->__unset('shipping_for_package_' . $package_key);
	}
}

/**
 * Remove the private quote from the current customer session.
 *
 * @param bool $clear_context Also leave the Blocks checkout context.
 * @return void
 */
function shipbubble_blocks_clear_quote($clear_context = false)
{
	if (!function_exists('WC') || !WC()->session) {
		return;
	}

	WC()->session->__unset('shipbubble_blocks_quote');
	if ($clear_context) {
		WC()->session->__unset('shipbubble_blocks_checkout_context');
	}

	shipbubble_blocks_clear_shipping_cache();
}

/**
 * Prevent a quote selected on Checkout from leaking back into Cart or classic checkout.
 *
 * @return void
 */
function shipbubble_blocks_reset_page_context()
{
	if (!function_exists('WC') || !WC()->session) {
		return;
	}

	$is_checkout_block = shipbubble_blocks_is_checkout_page();

	if (is_cart() || (is_checkout() && !$is_checkout_block)) {
		shipbubble_blocks_clear_quote(true);
	} elseif ($is_checkout_block && !shipbubble_blocks_is_checkout_context()) {
		// The first Store API cart request should already use Checkout rules.
		WC()->session->set('shipbubble_blocks_checkout_context', 'yes');
		shipbubble_blocks_invalidate_package_cache();
	}
}
add_action('template_redirect', 'shipbubble_blocks_reset_page_context', 5);

/**
 * Register the frontend bundle only for the Checkout block.
 *
 * @param Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry $integration_registry Registry.
 * @return void
 */
function shipbubble_register_blocks_integration($integration_registry)
{
	if (!shipbubble_blocks_is_supported()
		|| !interface_exists('Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface')) {
		return;
	}

	require_once __DIR__ . '/class-shipbubble-blocks-integration.php';
	$integration_registry->register(new Shipbubble_Blocks_Integration());
}
add_action('woocommerce_blocks_checkout_block_registration', 'shipbubble_register_blocks_integration');

/**
 * Register the locked inner block so WordPress can preserve it in Checkout.
 * The React component is supplied by the Blocks integration bundle.
 *
 * @return void
 */
function shipbubble_blocks_register_selected_courier_block()
{
	if (!shipbubble_blocks_is_supported() || !function_exists('register_block_type')) {
		return;
	}

	register_block_type(
		'shipbubble/selected-courier',
		array(
			'api_version' => 3,
			'parent' => array('woocommerce/checkout-shipping-methods-block'),
			'attributes' => array(
				'lock' => array(
					'type' => 'object',
					'default' => array(
						'remove' => true,
						'move' => true,
					),
				),
			),
			'supports' => array(
				'html' => false,
				'multiple' => false,
				'reusable' => false,
			),
		)
	);
}
add_action('init', 'shipbubble_blocks_register_selected_courier_block', 20);

/**
 * Allow WooCommerce to attach the data attributes used to mount our component.
 *
 * @param array $namespaces Allowed block namespaces.
 * @return array
 */
function shipbubble_blocks_allow_data_attributes(array $namespaces): array
{
	$namespaces[] = 'shipbubble';
	return array_values(array_unique($namespaces));
}
add_filter(
	'__experimental_woocommerce_blocks_add_data_attributes_to_namespace',
	'shipbubble_blocks_allow_data_attributes'
);

/**
 * Add the inner-block mount to checkouts saved before Shipbubble registered it.
 * WooCommerce automatically inserts forced blocks when a merchant next saves
 * Checkout; this render fallback makes the component available immediately on
 * existing Checkout block pages as well.
 *
 * @param string $block_content Rendered Shipping Methods block markup.
 * @return string
 */
function shipbubble_blocks_inject_selected_courier_mount($block_content)
{
	if (false !== strpos($block_content, 'shipbubble/selected-courier')) {
		return $block_content;
	}

	$mount = '<div data-block-name="shipbubble/selected-courier" class="wp-block-shipbubble-selected-courier"></div>';
	$closing_tag = strrpos($block_content, '</div>');

	if (false === $closing_tag) {
		return $block_content . $mount;
	}

	return substr($block_content, 0, $closing_tag)
		. $mount
		. substr($block_content, $closing_tag);
}
add_filter(
	'render_block_woocommerce/checkout-shipping-methods-block',
	'shipbubble_blocks_inject_selected_courier_mount',
	20,
	1
);

/**
 * Register the namespaced Store API cart update callback.
 *
 * @return void
 */
function shipbubble_blocks_register_store_api()
{
	if (!shipbubble_blocks_is_supported()) {
		return;
	}

	woocommerce_store_api_register_update_callback(
		array(
			'namespace' => 'shipbubble',
			'callback' => 'shipbubble_blocks_update_quote',
		)
	);
}
add_action('woocommerce_blocks_loaded', 'shipbubble_blocks_register_store_api');

/**
 * Normalize a scalar Store API field.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function shipbubble_blocks_clean_field($value): string
{
	return is_scalar($value) ? sanitize_text_field(wp_unslash((string) $value)) : '';
}

/**
 * Normalize the request body sent by the checkout controller.
 *
 * @param array $data Request body.
 * @return array
 */
function shipbubble_blocks_normalize_request(array $data): array
{
	$recipient = isset($data['recipient']) && is_array($data['recipient']) ? $data['recipient'] : array();
	$destination = isset($data['destination']) && is_array($data['destination']) ? $data['destination'] : array();

	return array(
		'action' => isset($data['action']) ? sanitize_key($data['action']) : 'clear',
		'recipient' => array(
			'first_name' => shipbubble_blocks_clean_field($recipient['first_name'] ?? ''),
			'last_name' => shipbubble_blocks_clean_field($recipient['last_name'] ?? ''),
			'email' => sanitize_email(wp_unslash((string) ($recipient['email'] ?? ''))),
			'phone' => shipbubble_blocks_clean_field($recipient['phone'] ?? ''),
		),
		'destination' => array(
			'address_1' => shipbubble_blocks_clean_field($destination['address_1'] ?? ''),
			'city' => shipbubble_blocks_clean_field($destination['city'] ?? ''),
			'state' => shipbubble_blocks_clean_field($destination['state'] ?? ''),
			'country' => strtoupper(shipbubble_blocks_clean_field($destination['country'] ?? '')),
			'postcode' => shipbubble_blocks_clean_field($destination['postcode'] ?? ''),
		),
		'delivery_instructions' => shipbubble_blocks_clean_field($data['delivery_instructions'] ?? ''),
	);
}

/**
 * Check whether a quote request contains WooCommerce's required address fields.
 *
 * @param array $request Normalized request.
 * @return bool
 */
function shipbubble_blocks_request_is_complete(array $request): bool
{
	$recipient = $request['recipient'];
	$destination = $request['destination'];
	$required_values = array(
		$recipient['first_name'],
		$recipient['last_name'],
		$recipient['email'],
		$recipient['phone'],
		$destination['address_1'],
		$destination['city'],
		$destination['country'],
	);

	if (in_array('', $required_values, true) || !is_email($recipient['email'])) {
		return false;
	}

	if (function_exists('WC') && WC()->countries) {
		$fields = WC()->countries->get_address_fields($destination['country'], 'shipping_');
		if (!empty($fields['shipping_state']['required']) && '' === $destination['state']) {
			return false;
		}
	}

	return true;
}

/**
 * Build the human-readable address expected by Shipbubble's address API.
 *
 * @param array $destination Normalized destination.
 * @return string
 */
function shipbubble_blocks_format_destination(array $destination): string
{
	$country = $destination['country'];
	$state = $destination['state'];
	$country_name = $country;
	$state_name = $state;

	if (function_exists('WC') && WC()->countries) {
		$countries = WC()->countries->get_countries();
		$states = WC()->countries->get_states($country);
		$country_name = $countries[$country] ?? $country;
		$state_name = is_array($states) && isset($states[$state]) ? $states[$state] : $state;
	}

	return implode(', ', array_filter(array(
		$destination['address_1'],
		$destination['city'],
		$state_name,
		$country_name,
	)));
}

/**
 * Create a stable signature for a destination.
 *
 * @param array $destination Destination fields.
 * @return string
 */
function shipbubble_blocks_destination_signature(array $destination): string
{
	$normalized = array(
		'address_1' => strtolower(trim((string) ($destination['address_1'] ?? $destination['address'] ?? ''))),
		'city' => strtolower(trim((string) ($destination['city'] ?? ''))),
		'state' => strtoupper(trim((string) ($destination['state'] ?? ''))),
		'country' => strtoupper(trim((string) ($destination['country'] ?? ''))),
		'postcode' => strtoupper(trim((string) ($destination['postcode'] ?? ''))),
	);

	return hash('sha256', wp_json_encode($normalized));
}

/**
 * Build a request fingerprint including the cart and rate-affecting settings.
 *
 * @param array $request Normalized request.
 * @param array $options Shipbubble settings.
 * @return string
 */
function shipbubble_blocks_quote_fingerprint(array $request, array $options): string
{
	$cart_hash = function_exists('WC') && WC()->cart ? WC()->cart->get_cart_hash() : '';
	$vendor_id = function_exists('shipbubble_get_cart_vendor_id') ? shipbubble_get_cart_vendor_id() : null;

	return hash('sha256', wp_json_encode(array(
		'request' => $request,
		'cart' => $cart_hash,
		'couriers' => array_values((array) ($options['courier_list'] ?? array('all'))),
		'extra_charges' => (string) ($options['extra_charges'] ?? '0'),
		'live_mode' => (string) ($options['live_mode'] ?? 'yes'),
		'vendor_id' => $vendor_id,
	)));
}

/**
 * Convert a Shipbubble error response into a safe checkout message.
 *
 * @param mixed  $response API response.
 * @param string $fallback Fallback message.
 * @return string
 */
function shipbubble_blocks_error_message($response, $fallback): string
{
	if (is_object($response)) {
		if (!empty($response->message) && is_scalar($response->message)) {
			return sanitize_text_field((string) $response->message);
		}
		if (!empty($response->error)) {
			$error = is_array($response->error) ? reset($response->error) : $response->error;
			if (is_scalar($error)) {
				return sanitize_text_field((string) $error);
			}
		}
	}

	if (is_array($response)) {
		foreach (array('error', 'message') as $key) {
			if (!empty($response[$key])) {
				$value = is_array($response[$key]) ? reset($response[$key]) : $response[$key];
				if (is_scalar($value)) {
					return sanitize_text_field((string) $value);
				}
			}
		}
	}

	return $fallback;
}

/**
 * Throw a Store API error that Checkout Blocks renders as a notice.
 *
 * @param string $code Error code.
 * @param string $message Error message.
 * @return void
 * @throws Automattic\WooCommerce\StoreApi\Exceptions\RouteException Always.
 */
function shipbubble_blocks_throw_error($code, $message)
{
	throw new Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
		$code,
		esc_html($message),
		400
	);
}

/**
 * Process a checkout-driven Store API quote refresh.
 *
 * @param array $data Extension request body.
 * @return void
 */
function shipbubble_blocks_update_quote($data)
{
	if (!function_exists('WC') || !WC()->session) {
		shipbubble_blocks_throw_error(
			'shipbubble_session_unavailable',
			__('Unable to initialize shipping. Please refresh checkout.', 'shipbubble')
		);
	}

	WC()->session->set('shipbubble_blocks_checkout_context', 'yes');
	$request = shipbubble_blocks_normalize_request(is_array($data) ? $data : array());

	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
	$is_active = apply_filters('is_shipbubble_active', $options['activate_shipbubble'] ?? 'no');
	$needs_shipping = WC()->cart && WC()->cart->needs_shipping();

	if ('quote' !== $request['action'] || 'yes' !== $is_active || !$needs_shipping || !shipbubble_blocks_request_is_complete($request)) {
		shipbubble_blocks_clear_quote(false);
		return;
	}

	if (function_exists('shipbubble_cart_has_multiple_vendors') && shipbubble_cart_has_multiple_vendors()) {
		shipbubble_blocks_clear_quote(false);
		shipbubble_blocks_throw_error(
			'shipbubble_multiple_vendors',
			__('You cannot checkout with products from multiple vendors. Please purchase from one vendor at a time.', 'shipbubble')
		);
	}

	if (apply_filters('shipbubble_checkout_seller_not_ready', false)) {
		shipbubble_blocks_clear_quote(false);
		shipbubble_blocks_throw_error(
			'shipbubble_seller_not_ready',
			__('This vendor has not completed their shipping setup, so this order cannot be placed yet. Please contact the store owner.', 'shipbubble')
		);
	}

	$fingerprint = shipbubble_blocks_quote_fingerprint($request, $options);
	$cached_quote = WC()->session->get('shipbubble_blocks_quote');
	$expiry = SHIPBUBBLE_REQUEST_TOKEN_EXPIRY * HOUR_IN_SECONDS;

	if (is_array($cached_quote)
		&& hash_equals((string) ($cached_quote['fingerprint'] ?? ''), $fingerprint)
		&& (time() - (int) ($cached_quote['created_at'] ?? 0)) < $expiry) {
		return;
	}

	$recipient = $request['recipient'];
	$destination = $request['destination'];
	$address_response = shipbubble_validate_address(
		trim($recipient['first_name'] . ' ' . $recipient['last_name']),
		$recipient['email'],
		$recipient['phone'],
		shipbubble_blocks_format_destination($destination),
		$destination['postcode']
	);

	if (!isset($address_response->response_code)
		|| (int) $address_response->response_code !== (int) SHIPBUBBLE_RESPONSE_IS_OK
		|| empty($address_response->data->address_code)) {
		shipbubble_blocks_clear_quote(false);
		shipbubble_blocks_throw_error(
			'shipbubble_invalid_address',
			shipbubble_blocks_error_message(
				$address_response,
				__('Shipbubble could not validate this delivery address.', 'shipbubble')
			)
		);
	}

	$products = shipbubble_get_checkout_orders();
	$products['comments'] = $request['delivery_instructions'] ?: __('Please handle carefully', 'shipbubble');
	$courier_filter = array_values((array) ($options['courier_list'] ?? array('all')));
	$service_codes = in_array('all', $courier_filter, true) ? array() : $courier_filter;
	$rate_response = shipbubble_process_shipping_rates(
		$address_response->data->address_code,
		$products,
		$service_codes
	);

	if (!empty($rate_response['error']) || empty($rate_response['couriers'])) {
		shipbubble_blocks_clear_quote(false);
		shipbubble_blocks_throw_error(
			'shipbubble_rates_unavailable',
			shipbubble_blocks_error_message(
				$rate_response,
				__('No Shipbubble delivery rates are available for this address.', 'shipbubble')
			)
		);
	}

	$private_rates = array();
	$extra_charges = (float) ($rate_response['extra_charges'] ?? 0);
	$request_token = shipbubble_blocks_clean_field($rate_response['request_token'] ?? '');
	if ('' === $request_token) {
		shipbubble_blocks_clear_quote(false);
		shipbubble_blocks_throw_error(
			'shipbubble_rates_unavailable',
			__('Shipbubble returned an incomplete delivery quote. Please try again.', 'shipbubble')
		);
	}

	foreach ((array) $rate_response['couriers'] as $courier) {
		if (!is_object($courier)
			|| empty($courier->courier_id)
			|| empty($courier->service_code)
			|| empty($courier->courier_name)) {
			continue;
		}

		$courier_id = shipbubble_blocks_clean_field($courier->courier_id);
		$service_code = shipbubble_blocks_clean_field($courier->service_code);
		$quote_key = substr(hash_hmac(
			'sha256',
			$request_token . '|' . $courier_id . '|' . $service_code . '|' . $fingerprint,
			wp_salt('nonce')
		), 0, 32);
		$pickup_address = '';
		if (!empty($courier->pickup_station) && !empty($courier->pickup_station->address)) {
			$pickup_address = shipbubble_blocks_clean_field($courier->pickup_station->address);
		}

		$private_rates[$quote_key] = array(
			'request_token' => $request_token,
			'courier_id' => $courier_id,
			'service_code' => $service_code,
			'courier_name' => shipbubble_blocks_clean_field($courier->courier_name),
			'courier_image' => esc_url_raw((string) ($courier->courier_image ?? '')),
			'cost' => max(0, (float) ($courier->rate_card_amount ?? 0) + $extra_charges),
			'delivery_eta' => html_entity_decode(
				shipbubble_blocks_clean_field($courier->delivery_eta ?? ''),
				ENT_QUOTES | ENT_HTML5,
				'UTF-8'
			),
			'pickup_address' => $pickup_address,
		);
	}

	if (empty($private_rates)) {
		shipbubble_blocks_clear_quote(false);
		shipbubble_blocks_throw_error(
			'shipbubble_rates_unavailable',
			__('No valid Shipbubble delivery rates were returned.', 'shipbubble')
		);
	}

	WC()->session->set('shipbubble_blocks_quote', array(
		'fingerprint' => $fingerprint,
		'destination_signature' => shipbubble_blocks_destination_signature($destination),
		'recipient' => $recipient,
		'created_at' => time(),
		'request_datetime' => current_time('mysql'),
		'rates' => $private_rates,
	));

	shipbubble_blocks_clear_shipping_cache();
}

/**
 * Return native Woo rate definitions for the active Blocks quote.
 *
 * @param array $package WooCommerce shipping package.
 * @return array
 */
function shipbubble_blocks_get_native_rates(array $package): array
{
	if (!shipbubble_blocks_is_checkout_context()) {
		return array();
	}

	$rates = array();
	if (shipbubble_is_local_pickup_active()) {
		$rates[] = array(
			'id_suffix' => 'local-pickup',
			'label' => shipbubble_get_local_pickup_text(),
			'cost' => 0,
			'description' => shipbubble_get_local_pickup_default(),
			'delivery_time' => '',
			'meta_data' => array('_shipbubble_local_pickup' => 'yes'),
		);
	}

	$quote = WC()->session->get('shipbubble_blocks_quote');
	if (!is_array($quote) || empty($quote['rates'])) {
		return $rates;
	}

	$expiry = SHIPBUBBLE_REQUEST_TOKEN_EXPIRY * HOUR_IN_SECONDS;
	$destination = isset($package['destination']) && is_array($package['destination'])
		? $package['destination']
		: array();
	if ((time() - (int) ($quote['created_at'] ?? 0)) >= $expiry
		|| !hash_equals(
			(string) ($quote['destination_signature'] ?? ''),
			shipbubble_blocks_destination_signature($destination)
		)) {
		return $rates;
	}

	foreach ($quote['rates'] as $quote_key => $courier) {
		$rates[] = array(
			'id_suffix' => $quote_key,
			'label' => $courier['courier_name'],
			'cost' => $courier['cost'],
			'description' => $courier['pickup_address'],
			'delivery_time' => $courier['delivery_eta'],
			'meta_data' => array(
				'_shipbubble_quote_key' => $quote_key,
				'_shipbubble_courier_image' => $courier['courier_image'] ?? '',
			),
		);
	}

	return $rates;
}

/**
 * Apply checkout-only visibility rules to native Blocks rates.
 *
 * @param array $rates Package rates.
 * @return array
 */
function shipbubble_blocks_filter_package_rates(array $rates): array
{
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
	$disable_others = 'yes' === strtolower((string) ($options['disable_other_shipping_methods'] ?? 'no'));
	$shipbubble_rates = array();
	$other_rates = array();

	foreach ($rates as $rate_key => $rate) {
		if (SHIPBUBBLE_ID === $rate->method_id) {
			$shipbubble_rates[$rate_key] = $rate;
		} else {
			$other_rates[$rate_key] = $rate;
		}
	}

	$is_active = apply_filters('is_shipbubble_active', $options['activate_shipbubble'] ?? 'no');
	return $disable_others && 'yes' === $is_active
		? $shipbubble_rates
		: array_merge($shipbubble_rates, $other_rates);
}

/**
 * Persist the private selected quote using the order shipping item as the key.
 *
 * @param WC_Order $order Store API order.
 * @return void
 */
function shipbubble_blocks_save_order_meta($order)
{
	if (!$order instanceof WC_Order) {
		return;
	}

	if (function_exists('shipbubble_cart_has_multiple_vendors') && shipbubble_cart_has_multiple_vendors()) {
		shipbubble_blocks_throw_error(
			'shipbubble_multiple_vendors',
			__('You cannot checkout with products from multiple vendors. Please purchase from one vendor at a time.', 'shipbubble')
		);
	}

	if (apply_filters('shipbubble_checkout_seller_not_ready', false)) {
		shipbubble_blocks_throw_error(
			'shipbubble_seller_not_ready',
			__('This vendor has not completed their shipping setup, so this order cannot be placed yet. Please contact the store owner.', 'shipbubble')
		);
	}

	$quote = function_exists('WC') && WC()->session ? WC()->session->get('shipbubble_blocks_quote') : array();

	foreach ($order->get_items('shipping') as $shipping_item) {
		if (SHIPBUBBLE_ID !== $shipping_item->get_method_id()) {
			continue;
		}
		if ('' === trim((string) ($order->get_shipping_phone() ?: $order->get_billing_phone()))) {
			shipbubble_blocks_throw_error(
				'shipbubble_phone_required',
				__('Please enter a phone number to use Shipbubble shipping.', 'shipbubble')
			);
		}

		if ('yes' === $shipping_item->get_meta('_shipbubble_local_pickup', true)) {
			$order->update_meta_data('shipbubble_local_pickup', true);
			$order->update_meta_data('shipbubble_local_pickup_address', shipbubble_get_local_pickup_default());
			$order->save();
			return;
		}

		$quote_key = (string) $shipping_item->get_meta('_shipbubble_quote_key', true);
		$courier = is_array($quote) && isset($quote['rates'][$quote_key]) ? $quote['rates'][$quote_key] : null;

		if (!$courier) {
			shipbubble_blocks_throw_error(
				'shipbubble_quote_expired',
				__('Your Shipbubble delivery rate has expired. Please refresh checkout and select a delivery option again.', 'shipbubble')
			);
		}

		$shipment_details = array(
			'request_token' => $courier['request_token'],
			'courier_id' => $courier['courier_id'],
			'courier_name' => $courier['courier_name'],
			'service_code' => $courier['service_code'],
			'shipment_cost' => (string) $courier['cost'],
			'request_datetime' => $quote['request_datetime'] ?? current_time('mysql'),
			'order_request_time' => current_time('mysql'),
		);
		$shipment_meta = array(
			'user_can_ship' => true,
			'shipment_payload' => array(
				'request_token' => $courier['request_token'],
				'service_code' => $courier['service_code'],
				'courier_id' => $courier['courier_id'],
			),
		);

		$address_1 = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
		$city = $order->get_shipping_city() ?: $order->get_billing_city();
		$state = $order->get_shipping_state() ?: $order->get_billing_state();
		$country = $order->get_shipping_country() ?: $order->get_billing_country();
		$delivery_address = shipbubble_blocks_format_destination(array(
			'address_1' => $address_1,
			'city' => $city,
			'state' => $state,
			'country' => $country,
			'postcode' => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
		));

		$order->update_meta_data('shipbubble_shipment_details', serialize($shipment_details));
		$order->update_meta_data('sb_shipment_meta', serialize($shipment_meta));
		$order->update_meta_data('shipbubble_delivery_address', $delivery_address);
		$delivery_phone = $order->get_shipping_phone()
			?: $order->get_billing_phone()
			?: (string) ($quote['recipient']['phone'] ?? '');
		if (!$order->get_billing_phone() && $delivery_phone) {
			$order->set_billing_phone($delivery_phone);
		}
		$order->update_meta_data('shipbubble_delivery_phone', $delivery_phone);
		$order->save();
		return;
	}
}
add_action('woocommerce_store_api_checkout_order_processed', 'shipbubble_blocks_save_order_meta', 10, 1);
