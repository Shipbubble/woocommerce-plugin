<?php

if (!defined('ABSPATH')) {
	exit;
}

function shipbubble_wcfm_register_adapter()
{
	static $registered = false;

	if ($registered || !shipbubble_wcfm_is_adapter_active()) {
		return;
	}

	$registered = true;

	add_filter('wcfm_marketplace_settings_fields_general', 'shipbubble_wcfm_settings_fields_general', 50, 2);
	add_action('wcfm_vendor_settings_update', 'shipbubble_wcfm_handle_saved_profile', 50, 2);

	add_filter('is_shipbubble_active', 'shipbubble_wcfm_is_shipbubble_active');
	add_filter('shipbubble_get_address_code', 'shipbubble_wcfm_get_vendor_address_code', 10, 3);
	add_filter('shipbubble_get_store_category', 'shipbubble_wcfm_get_vendor_store_category', 10, 2);
	add_filter('shipbubble_get_cart_vendor_ids', 'shipbubble_wcfm_get_cart_vendor_ids');
	add_filter('shipbubble_get_cart_vendor_id', 'shipbubble_wcfm_normalize_cart_vendor_id');
	add_filter('shipbubble_checkout_has_multi_vendor', 'shipbubble_wcfm_checkout_has_multi_vendor');
	add_filter('shipbubble_get_pickup_address', 'shipbubble_wcfm_get_vendor_pickup_address');
	add_filter('shipbubble_get_local_pickup_text', 'shipbubble_wcfm_get_vendor_local_pickup_text');
	add_filter('shipbubble_is_local_pickup_active', 'shipbubble_wcfm_is_vendor_local_pickup_active');

	add_filter('woocommerce_add_to_cart_validation', 'shipbubble_wcfm_single_vendor_add_to_cart', 50, 5);
	add_action('woocommerce_check_cart_items', 'shipbubble_wcfm_check_cart_items', 50);
	add_action('woocommerce_before_checkout_process', 'shipbubble_wcfm_check_cart_items', 5);
}

function shipbubble_wcfm_is_adapter_active(): bool
{
	return function_exists('shipbubble_multivendor_enabled')
		&& shipbubble_multivendor_enabled()
		&& function_exists('wcfm_get_vendor_id_by_post');
}

function shipbubble_wcfm_settings_fields_general($settings_fields, $vendor_id = 0)
{
	if (!shipbubble_wcfm_is_adapter_active()) {
		return $settings_fields;
	}

	$vendor_id = absint($vendor_id);
	$vendor_info = shipbubble_get_vendor_info($vendor_id);
	$categories = shipbubble_get_order_categories();

	if (empty($categories)) {
		$categories = array('' => __('Select category', 'shipbubble'));
	}

	$shipbubble_fields = array(
		'shipbubble_store_category' => array(
			'label' => __('Shipbubble Category', 'shipbubble'),
			'type' => 'select',
			'options' => $categories,
			'class' => 'wcfm-select wcfm_ele',
			'label_class' => 'wcfm_title wcfm_ele',
			'value' => $vendor_info['store_category'] ?? '',
		),
	);

	if (shipbubble_is_option_active('local_pickup')) {
		$shipbubble_fields['shipbubble_local_pickup'] = array(
			'label' => __('Shipbubble Local Pickup', 'shipbubble'),
			'type' => 'select',
			'options' => array(
				'no' => __('No', 'shipbubble'),
				'yes' => __('Yes', 'shipbubble'),
			),
			'class' => 'wcfm-select wcfm_ele',
			'label_class' => 'wcfm_title wcfm_ele',
			'value' => $vendor_info['local_pickup'] ?? 'no',
		);
		$shipbubble_fields['shipbubble_local_pickup_text'] = array(
			'label' => __('Shipbubble Pickup Text', 'shipbubble'),
			'type' => 'text',
			'class' => 'wcfm-text wcfm_ele',
			'label_class' => 'wcfm_title wcfm_ele',
			'value' => $vendor_info['local_pickup_text'] ?? '',
			'placeholder' => __('Pickup in store', 'shipbubble'),
		);
	}

	return array_merge($settings_fields, $shipbubble_fields);
}

function shipbubble_wcfm_handle_saved_profile($vendor_id, $wcfm_settings_form = array())
{
	if (!shipbubble_wcfm_is_adapter_active()) {
		return;
	}

	if (is_array($vendor_id) && is_numeric($wcfm_settings_form)) {
		$temp = $vendor_id;
		$vendor_id = $wcfm_settings_form;
		$wcfm_settings_form = $temp;
	}

	$vendor_id = absint($vendor_id);

	if (!$vendor_id) {
		return;
	}

	if (!is_array($wcfm_settings_form)) {
		$wcfm_settings_form = array();
	}

	$profile = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
	if (!is_array($profile)) {
		$profile = array();
	}

	$vendor_info = shipbubble_get_vendor_info($vendor_id);
	$sender = shipbubble_wcfm_get_sender_details($vendor_id, $wcfm_settings_form, $profile);
	$category = sanitize_text_field($wcfm_settings_form['shipbubble_store_category'] ?? ($vendor_info['store_category'] ?? ''));
	$local_pickup = sanitize_text_field($wcfm_settings_form['shipbubble_local_pickup'] ?? 'no');
	$local_pickup = 'yes' === $local_pickup ? 'yes' : 'no';
	$local_pickup_text = sanitize_text_field($wcfm_settings_form['shipbubble_local_pickup_text'] ?? '');

	$vendor_info = array_merge($vendor_info, array(
		'sender_name' => $sender['name'],
		'sender_email' => $sender['email'],
		'sender_phone' => $sender['phone'],
		'store_category' => $category,
		'pickup_address' => $sender['pickup_address'],
		'pickup_state' => $sender['state_label'],
		'pickup_country' => $sender['country_label'],
		'address_code' => '',
		'sandbox_address_code' => '',
		'address_validated' => 'no',
		'local_pickup' => $local_pickup,
		'local_pickup_text' => $local_pickup_text,
	));

	if (empty($sender['name']) || empty($sender['email']) || empty($sender['phone']) || empty($sender['full_address']) || empty($category)) {
		update_user_meta($vendor_id, 'shipbubble_vendor_info', $vendor_info);
		return;
	}

	$keys = shipbubble_get_keys();
	$name = shipbubble_wcfm_normalize_sender_name($sender['name']);

	if (!empty($keys['live_api_key'])) {
		$live_response = shipbubble_validate_address(
			$name,
			$sender['email'],
			$sender['phone'],
			$sender['full_address'],
			'',
			$keys['live_api_key']
		);

		if (isset($live_response->response_code) && (int) $live_response->response_code === SHIPBUBBLE_RESPONSE_IS_OK) {
			$vendor_info['address_code'] = $live_response->data->address_code ?? '';
		}
	}

	if (!empty($keys['sandbox_api_key'])) {
		$sandbox_response = shipbubble_validate_address(
			$name,
			$sender['email'],
			$sender['phone'],
			$sender['full_address'],
			'',
			$keys['sandbox_api_key']
		);

		if (isset($sandbox_response->response_code) && (int) $sandbox_response->response_code === SHIPBUBBLE_RESPONSE_IS_OK) {
			$vendor_info['sandbox_address_code'] = $sandbox_response->data->address_code ?? '';
		}
	}

	$live_valid = empty($keys['live_api_key']) || !empty($vendor_info['address_code']);
	$sandbox_valid = empty($keys['sandbox_api_key']) || !empty($vendor_info['sandbox_address_code']);
	$has_key = !empty($keys['live_api_key']) || !empty($keys['sandbox_api_key']);
	$vendor_info['address_validated'] = ($has_key && $live_valid && $sandbox_valid) ? 'yes' : 'no';

	update_user_meta($vendor_id, 'shipbubble_vendor_info', $vendor_info);
}

function shipbubble_wcfm_get_sender_details($vendor_id, array $form, array $profile): array
{
	$address = array();

	if (isset($form['address']) && is_array($form['address'])) {
		$address = $form['address'];
	} elseif (isset($profile['address']) && is_array($profile['address'])) {
		$address = $profile['address'];
	}

	$street_1 = sanitize_text_field($address['street_1'] ?? '');
	$street_2 = sanitize_text_field($address['street_2'] ?? '');
	$city = sanitize_text_field($address['city'] ?? '');
	$postcode = sanitize_text_field($address['zip'] ?? ($address['postcode'] ?? ''));
	$state_code = sanitize_text_field($address['state'] ?? '');
	$country_code = sanitize_text_field($address['country'] ?? '');
	$labels = shipbubble_wcfm_get_location_labels($country_code, $state_code);

	$store_name = sanitize_text_field($form['store_name'] ?? ($profile['store_name'] ?? ''));
	$phone = sanitize_text_field($form['phone'] ?? ($profile['phone'] ?? ''));
	$email = sanitize_email($form['store_email'] ?? ($profile['store_email'] ?? ''));

	if (empty($store_name) && function_exists('wcfm_get_vendor_store_name')) {
		$store_name = sanitize_text_field(wcfm_get_vendor_store_name($vendor_id));
	}

	$store = shipbubble_wcfm_get_store($vendor_id);
	if ($store) {
		if (empty($store_name) && method_exists($store, 'get_shop_name')) {
			$store_name = sanitize_text_field($store->get_shop_name());
		}
		if (empty($phone) && method_exists($store, 'get_phone')) {
			$phone = sanitize_text_field($store->get_phone());
		}
		if (empty($email) && method_exists($store, 'get_email')) {
			$email = sanitize_email($store->get_email());
		}
	}

	if (empty($email)) {
		$user = get_userdata($vendor_id);
		$email = $user && !empty($user->user_email) ? sanitize_email($user->user_email) : '';
	}

	$pickup_address_parts = array_filter(array($street_1, $street_2, $city, $postcode));
	$full_address_parts = array_filter(array($street_1, $street_2, $city, $postcode, $labels['state'], $labels['country']));

	return array(
		'name' => $store_name,
		'email' => $email,
		'phone' => $phone,
		'pickup_address' => implode(', ', $pickup_address_parts),
		'state_label' => $labels['state'],
		'country_label' => $labels['country'],
		'full_address' => implode(', ', $full_address_parts),
	);
}

function shipbubble_wcfm_get_location_labels($country_code, $state_code): array
{
	$countries_obj = new WC_Countries();
	$countries = $countries_obj->get_countries();
	$country_label = $countries[$country_code] ?? $country_code;
	$states = $countries_obj->get_states($country_code);
	$state_label = is_array($states) && isset($states[$state_code]) ? $states[$state_code] : $state_code;

	return array(
		'country' => $country_label,
		'state' => $state_label,
	);
}

function shipbubble_wcfm_get_store($vendor_id)
{
	if (function_exists('wcfmmp_get_store')) {
		return wcfmmp_get_store($vendor_id);
	}

	return false;
}

function shipbubble_wcfm_normalize_sender_name($name): string
{
	$name = trim($name);

	if (!empty($name) && str_word_count($name) === 1) {
		return $name . ' ' . $name;
	}

	return $name;
}

function shipbubble_wcfm_get_product_seller_key($product_id)
{
	$product = wc_get_product($product_id);

	if (!$product || $product->is_virtual()) {
		return '';
	}

	$lookup_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
	$vendor_id = absint(wcfm_get_vendor_id_by_post($lookup_id));

	if ($vendor_id) {
		return 'vendor:' . $vendor_id;
	}

	return 'admin';
}

function shipbubble_wcfm_get_cart_seller_keys(): array
{
	if (!function_exists('WC') || !WC()->cart) {
		return array();
	}

	$seller_keys = array();

	foreach (WC()->cart->get_cart() as $cart_item) {
		$product_id = $cart_item['variation_id'] ?? 0;
		if (!$product_id) {
			$product_id = $cart_item['product_id'] ?? 0;
		}

		$seller_key = shipbubble_wcfm_get_product_seller_key($product_id);

		if (!empty($seller_key)) {
			$seller_keys[] = $seller_key;
		}
	}

	return array_values(array_unique($seller_keys));
}

function shipbubble_wcfm_get_cart_vendor_ids($vendor_ids): array
{
	if (!shipbubble_wcfm_is_adapter_active()) {
		return $vendor_ids;
	}

	return array_values(array_unique(array_merge((array) $vendor_ids, shipbubble_wcfm_get_cart_seller_keys())));
}

function shipbubble_wcfm_normalize_cart_vendor_id($vendor_id)
{
	if (is_string($vendor_id) && strpos($vendor_id, 'vendor:') === 0) {
		return absint(str_replace('vendor:', '', $vendor_id));
	}

	if ('admin' === $vendor_id) {
		return 0;
	}

	return $vendor_id;
}

function shipbubble_wcfm_get_current_cart_vendor_id()
{
	$vendor_id = shipbubble_get_cart_vendor_id();

	if (is_string($vendor_id) && strpos($vendor_id, 'vendor:') === 0) {
		return absint(str_replace('vendor:', '', $vendor_id));
	}

	if ('admin' === $vendor_id) {
		return 0;
	}

	return is_numeric($vendor_id) ? absint($vendor_id) : null;
}

function shipbubble_wcfm_checkout_has_multi_vendor($has_multi_vendor): bool
{
	if (!shipbubble_wcfm_is_adapter_active()) {
		return $has_multi_vendor;
	}

	return count(shipbubble_wcfm_get_cart_seller_keys()) > 1;
}

function shipbubble_wcfm_single_vendor_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variations = array())
{
	if (!$passed || !shipbubble_wcfm_is_adapter_active()) {
		return $passed;
	}

	$new_product_id = $variation_id ? $variation_id : $product_id;
	$new_seller_key = shipbubble_wcfm_get_product_seller_key($new_product_id);

	if (empty($new_seller_key)) {
		return $passed;
	}

	foreach (shipbubble_wcfm_get_cart_seller_keys() as $seller_key) {
		if ($seller_key !== $new_seller_key) {
			shipbubble_wcfm_add_notice(__('You cannot add products from multiple vendors to the same cart. Please purchase from one vendor at a time.', 'shipbubble'));
			return false;
		}
	}

	return $passed;
}

function shipbubble_wcfm_check_cart_items()
{
	if (!shipbubble_wcfm_is_adapter_active()) {
		return;
	}

	$seller_keys = shipbubble_wcfm_get_cart_seller_keys();

	if (count($seller_keys) > 1) {
		shipbubble_wcfm_add_notice(__('You cannot checkout with products from multiple vendors. Please purchase from one vendor at a time.', 'shipbubble'));
		return;
	}

	$vendor_id = shipbubble_wcfm_get_current_cart_vendor_id();

	if ($vendor_id && !shipbubble_wcfm_vendor_ready($vendor_id)) {
		shipbubble_wcfm_add_notice(__('This vendor has not completed Shipbubble shipping setup. Please contact the store owner or choose another product.', 'shipbubble'));
	}
}

function shipbubble_wcfm_add_notice($message)
{
	if (function_exists('wc_has_notice') && wc_has_notice($message, 'error')) {
		return;
	}

	wc_add_notice($message, 'error');
}

function shipbubble_wcfm_vendor_ready($vendor_id): bool
{
	$vendor_info = shipbubble_get_vendor_info($vendor_id);

	if (($vendor_info['address_validated'] ?? 'no') !== 'yes') {
		return false;
	}

	if (shipbubble_is_live_mode()) {
		return !empty($vendor_info['address_code']);
	}

	return !empty($vendor_info['sandbox_address_code']);
}

function shipbubble_wcfm_is_shipbubble_active($is_active)
{
	if (!shipbubble_wcfm_is_adapter_active() || 'yes' !== $is_active) {
		return $is_active;
	}

	if (shipbubble_wcfm_checkout_has_multi_vendor(false)) {
		return 'no';
	}

	$vendor_id = shipbubble_wcfm_get_current_cart_vendor_id();

	if (is_null($vendor_id) || !$vendor_id) {
		return $is_active;
	}

	return shipbubble_wcfm_vendor_ready($vendor_id) ? 'yes' : 'no';
}

function shipbubble_wcfm_get_vendor_address_code($address_code, $is_live = true, $vendor_id = null): string
{
	if (!shipbubble_wcfm_is_adapter_active()) {
		return $address_code;
	}

	if (is_null($vendor_id)) {
		$vendor_id = shipbubble_wcfm_get_current_cart_vendor_id();
	}

	$vendor_id = absint($vendor_id);

	if (!$vendor_id) {
		return $address_code;
	}

	$vendor_info = shipbubble_get_vendor_info($vendor_id);

	if (($vendor_info['address_validated'] ?? 'no') !== 'yes') {
		return '';
	}

	return $is_live ? ($vendor_info['address_code'] ?? '') : ($vendor_info['sandbox_address_code'] ?? '');
}

function shipbubble_wcfm_get_vendor_store_category($category, $vendor_id = null): string
{
	if (!shipbubble_wcfm_is_adapter_active()) {
		return $category;
	}

	if (is_null($vendor_id)) {
		$vendor_id = shipbubble_wcfm_get_current_cart_vendor_id();
	}

	$vendor_id = absint($vendor_id);

	if (!$vendor_id) {
		return $category;
	}

	$vendor_info = shipbubble_get_vendor_info($vendor_id);

	return !empty($vendor_info['store_category']) ? $vendor_info['store_category'] : $category;
}

function shipbubble_wcfm_get_vendor_pickup_address($pickup_address): string
{
	if (!shipbubble_wcfm_is_adapter_active()) {
		return $pickup_address;
	}

	$vendor_id = shipbubble_wcfm_get_current_cart_vendor_id();

	if (is_null($vendor_id) || !$vendor_id) {
		return $pickup_address;
	}

	if (!shipbubble_wcfm_vendor_ready($vendor_id)) {
		return '';
	}

	$vendor_info = shipbubble_get_vendor_info($vendor_id);

	if (($vendor_info['local_pickup'] ?? 'no') !== 'yes' || empty($vendor_info['pickup_address'])) {
		return $pickup_address;
	}

	return implode(', ', array_filter(array(
		$vendor_info['pickup_address'] ?? '',
		$vendor_info['pickup_state'] ?? '',
		$vendor_info['pickup_country'] ?? '',
	)));
}

function shipbubble_wcfm_get_vendor_local_pickup_text($pickup_text): string
{
	if (!shipbubble_wcfm_is_adapter_active()) {
		return $pickup_text;
	}

	$vendor_id = shipbubble_wcfm_get_current_cart_vendor_id();

	if (is_null($vendor_id) || !$vendor_id) {
		return $pickup_text;
	}

	if (!shipbubble_wcfm_vendor_ready($vendor_id)) {
		return '';
	}

	$vendor_info = shipbubble_get_vendor_info($vendor_id);

	return !empty($vendor_info['local_pickup_text']) ? $vendor_info['local_pickup_text'] : $pickup_text;
}

function shipbubble_wcfm_is_vendor_local_pickup_active($is_active): bool
{
	if (!shipbubble_wcfm_is_adapter_active() || !$is_active) {
		return $is_active;
	}

	$vendor_id = shipbubble_wcfm_get_current_cart_vendor_id();

	if (is_null($vendor_id) || !$vendor_id) {
		return $is_active;
	}

	if (!shipbubble_wcfm_vendor_ready($vendor_id)) {
		return false;
	}

	$vendor_info = shipbubble_get_vendor_info($vendor_id);

	return ($vendor_info['local_pickup'] ?? 'no') === 'yes';
}
