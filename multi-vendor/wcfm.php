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

	// Our own collapsible panel on the vendor settings page.
	add_action('end_wcfm_vendor_settings', 'shipbubble_wcfm_vendor_settings_panel', 15);

	// Persistent warning across the whole vendor dashboard until setup is complete.
	add_action('before_wcfm_dashboard', 'shipbubble_wcfm_setup_incomplete_banner');

	// Hide WCFM's native shipping type / processing-time fields.
	add_filter('wcfmmp_settings_fields_shipping', 'shipbubble_wcfm_settings_fields_shipping', 50, 3);
	add_filter('wcfmmp_shipping_types', 'shipbubble_wcfm_remove_shipping_types');

	// Remove WCFM's whole Shipping section from vendor settings — our panel replaces it.
	// The WCFM Marketplace settings view gates that section on these two filters, and
	// `wcfm_is_allow_store_shipping` additionally disables WCFM's own vendor shipping
	// gateways (by zone/weight/distance/country) so they can't compete with Shipbubble
	// rates at checkout.
	add_filter('wcfm_is_allow_store_shipping', '__return_false', 500);
	add_filter('wcfm_is_allow_vshipping_settings', '__return_false', 500);
	add_action('wcfm_vendor_settings_update', 'shipbubble_wcfm_handle_saved_profile', 50, 2);

	add_filter('is_shipbubble_active', 'shipbubble_wcfm_is_shipbubble_active');
	add_filter('shipbubble_get_address_code', 'shipbubble_wcfm_get_vendor_address_code', 10, 3);
	add_filter('shipbubble_get_store_category', 'shipbubble_wcfm_get_vendor_store_category', 10, 2);
	add_filter('shipbubble_get_cart_vendor_ids', 'shipbubble_wcfm_get_cart_vendor_ids');
	add_filter('shipbubble_get_cart_vendor_id', 'shipbubble_wcfm_normalize_cart_vendor_id');
	add_filter('shipbubble_checkout_has_multi_vendor', 'shipbubble_wcfm_checkout_has_multi_vendor');
	add_filter('shipbubble_checkout_seller_not_ready', 'shipbubble_wcfm_checkout_seller_not_ready');
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

function shipbubble_wcfm_remove_shipping_types(array $types): array
{
	// Shipbubble handles all shipping — collapse WCFM's type dropdown to a single inert option.
	return array('' => __('Managed by Shipbubble', 'shipbubble'));
}

function shipbubble_wcfm_settings_fields_shipping($settings_fields, $user_id = 0, $wcfmmp_shipping = array())
{
	if (!shipbubble_wcfm_is_adapter_active()) {
		return $settings_fields;
	}

	// Strip WCFM's native shipping type and processing-time rows — our panel replaces them.
	unset($settings_fields['wcfmmp_shipping_type'], $settings_fields['wcfmmp_pt']);

	return $settings_fields;
}

/**
 * Dashboard-wide warning shown to a vendor until their Shipbubble setup is complete.
 *
 * Renders on every WCFM dashboard page and disappears on its own once the vendor
 * is ready. The copy branches on *why* they are not ready, so a vendor whose
 * address was rejected is not told to go and fill in fields they already filled.
 *
 * Both API keys are validated together during admin setup and vendor addresses are
 * validated against both, so a vendor is never live-only or sandbox-only — the two
 * cases below are the only ones reachable.
 */
function shipbubble_wcfm_setup_incomplete_banner()
{
	if (!shipbubble_wcfm_is_adapter_active() || !function_exists('wcfm_is_vendor')) {
		return;
	}

	$vendor_id = absint(get_current_user_id());

	if (!$vendor_id || !wcfm_is_vendor($vendor_id) || shipbubble_wcfm_vendor_ready($vendor_id)) {
		return;
	}

	$vendor_info = shipbubble_get_vendor_info($vendor_id);

	// Every field the save handler requires before it will attempt validation.
	$required = array('sender_name', 'sender_email', 'sender_phone', 'pickup_address', 'pickup_state', 'pickup_country', 'store_category');
	$details_complete = true;

	foreach ($required as $field) {
		if (empty($vendor_info[$field])) {
			$details_complete = false;
			break;
		}
	}

	if (!$details_complete) {
		// Nothing saved yet, or a partial save — ask them to finish the form.
		$heading = __('Shipbubble setup incomplete', 'shipbubble');
		$message = __('Customers cannot place orders from your store until you add and verify your pickup details.', 'shipbubble');
		$link    = __('Complete setup', 'shipbubble');
		$accent  = '#f0ad4e';
		$bg      = '#fff8e1';
		$text    = '#856404';
	} else {
		// Everything filled in, but Shipbubble rejected the address.
		$heading = __('We could not verify your pickup address', 'shipbubble');
		$message = __('Customers cannot place orders from your store until your address is verified. Please check your shipping details and save again.', 'shipbubble');
		$link    = __('Check details', 'shipbubble');
		$accent  = '#dc3232';
		$bg      = '#fdecea';
		$text    = '#8a1f1f';
	}

	$settings_url = function_exists('get_wcfm_settings_url') ? get_wcfm_settings_url() : '';
	?>
	<div class="shipbubble-vendor-setup-notice" style="border-left:4px solid <?php echo esc_attr($accent); ?>;background:<?php echo esc_attr($bg); ?>;color:<?php echo esc_attr($text); ?>;padding:14px 18px;border-radius:4px;margin:0 0 20px;">
		<strong><?php echo esc_html($heading); ?></strong>
		<p style="margin:6px 0 0;">
			<?php echo esc_html($message); ?>
			<?php if ($settings_url) : ?>
				<a href="<?php echo esc_url($settings_url); ?>"><?php echo esc_html($link); ?></a>
			<?php endif; ?>
		</p>
	</div>
	<?php
}

function shipbubble_wcfm_vendor_settings_panel($vendor_id)
{
	if (!shipbubble_wcfm_is_adapter_active()) {
		return;
	}

	global $WCFM;

	$vendor_id   = absint($vendor_id);
	$vendor_info = shipbubble_get_vendor_info($vendor_id);
	$categories  = shipbubble_get_order_categories();

	if (empty($categories)) {
		$categories = array('' => __('Select category', 'shipbubble'));
	} else {
		$categories = array_merge(array('' => __('Select category', 'shipbubble')), $categories);
	}

	?>
	<!-- collapsible -->
	<div class="page_collapsible" id="wcfm_settings_form_shipbubble_head">
		<label class="wcfmfa fa-truck"></label>
		<?php esc_html_e('Shipbubble Shipping', 'shipbubble'); ?><span></span>
	</div>
	<div class="wcfm-container">
		<div id="wcfm_settings_form_shipbubble_expander" class="wcfm-content">
			<div class="wcfm_clearfix"></div>
			<?php
			$fields = array(
				'shipbubble_sender_name' => array(
					'label'       => __('Contact Name', 'shipbubble'),
					'name'        => 'vendor_data[shipbubble_sender_name]',
					'type'        => 'text',
					'class'       => 'wcfm-text wcfm_ele',
					'label_class' => 'wcfm_title wcfm_ele',
					'value'       => $vendor_info['sender_name'] ?? '',
					'placeholder' => __('Full name', 'shipbubble'),
					'hints'       => __('Name of the person handling shipments from this store.', 'shipbubble'),
				),
				'shipbubble_sender_email' => array(
					'label'       => __('Contact Email', 'shipbubble'),
					'name'        => 'vendor_data[shipbubble_sender_email]',
					'type'        => 'text',
					'class'       => 'wcfm-text wcfm_ele',
					'label_class' => 'wcfm_title wcfm_ele',
					'value'       => $vendor_info['sender_email'] ?? '',
					'placeholder' => __('email@example.com', 'shipbubble'),
					'hints'       => __('Email address used for shipping notifications.', 'shipbubble'),
				),
				'shipbubble_sender_phone' => array(
					'label'       => __('Contact Phone', 'shipbubble'),
					'name'        => 'vendor_data[shipbubble_sender_phone]',
					'type'        => 'text',
					'class'       => 'wcfm-text wcfm_ele',
					'label_class' => 'wcfm_title wcfm_ele',
					'value'       => $vendor_info['sender_phone'] ?? '',
					'placeholder' => __('e.g. 08012345678', 'shipbubble'),
					'hints'       => __('Phone number for the pickup contact.', 'shipbubble'),
				),
				'shipbubble_pickup_address' => array(
					'label'       => __('Pickup Address', 'shipbubble'),
					'name'        => 'vendor_data[shipbubble_pickup_address]',
					'type'        => 'text',
					'class'       => 'wcfm-text wcfm_ele',
					'label_class' => 'wcfm_title wcfm_ele',
					'value'       => $vendor_info['pickup_address'] ?? '',
					'placeholder' => __('Street address', 'shipbubble'),
					'hints'       => __('Street address where couriers will pick up orders.', 'shipbubble'),
				),
				'shipbubble_pickup_state' => array(
					'label'       => __('State', 'shipbubble'),
					'name'        => 'vendor_data[shipbubble_pickup_state]',
					'type'        => 'text',
					'class'       => 'wcfm-text wcfm_ele',
					'label_class' => 'wcfm_title wcfm_ele',
					'value'       => $vendor_info['pickup_state'] ?? '',
					'placeholder' => __('e.g. Lagos', 'shipbubble'),
				),
				'shipbubble_pickup_country' => array(
					'label'       => __('Country', 'shipbubble'),
					'name'        => 'vendor_data[shipbubble_pickup_country]',
					'type'        => 'select',
					'options'     => array_merge(
						array('' => __('Select country', 'shipbubble')),
						(new WC_Countries())->get_countries()
					),
					'class'       => 'wcfm-select wcfm_ele',
					'label_class' => 'wcfm_title wcfm_ele',
					'value'       => $vendor_info['pickup_country'] ?? '',
				),
				'shipbubble_store_category' => array(
					'label'       => __('Shipping Category', 'shipbubble'),
					'name'        => 'vendor_data[shipbubble_store_category]',
					'type'        => 'select',
					'options'     => $categories,
					'class'       => 'wcfm-select wcfm_ele',
					'label_class' => 'wcfm_title wcfm_ele',
					'value'       => $vendor_info['store_category'] ?? '',
					'hints'       => __('Select the category that best describes your products. Used for Shipbubble rate calculation.', 'shipbubble'),
				),
			);

			if (shipbubble_is_option_active('local_pickup')) {
				$fields['shipbubble_local_pickup'] = array(
					'label'       => __('Enable Local Pickup', 'shipbubble'),
					'name'        => 'vendor_data[shipbubble_local_pickup]',
					'type'        => 'select',
					'options'     => array(
						'no'  => __('No', 'shipbubble'),
						'yes' => __('Yes', 'shipbubble'),
					),
					'class'       => 'wcfm-select wcfm_ele',
					'label_class' => 'wcfm_title wcfm_ele',
					'value'       => $vendor_info['local_pickup'] ?? 'no',
					'hints'       => __('Allow customers to pick up orders directly from your store.', 'shipbubble'),
				);
				$fields['shipbubble_local_pickup_text'] = array(
					'label'       => __('Pickup Label', 'shipbubble'),
					'name'        => 'vendor_data[shipbubble_local_pickup_text]',
					'type'        => 'text',
					'class'       => 'wcfm-text wcfm_ele',
					'label_class' => 'wcfm_title wcfm_ele',
					'value'       => $vendor_info['local_pickup_text'] ?? '',
					'placeholder' => __('e.g. Pick up in store', 'shipbubble'),
					'hints'       => __('Label shown to customers for the local pickup option at checkout.', 'shipbubble'),
				);
			}

			$WCFM->wcfm_fields->wcfm_generate_form_field($fields);
			?>
		</div>
	</div>
	<div class="wcfm_clearfix"></div>
	<!-- end collapsible -->
	<?php
}

function shipbubble_wcfm_handle_saved_profile($vendor_id, $wcfm_settings_form = array())
{
	if (!shipbubble_wcfm_is_adapter_active()) {
		return;
	}

	$vendor_id = absint($vendor_id);

	if (!$vendor_id || !is_array($wcfm_settings_form)) {
		return;
	}

	// Our fields use name="vendor_data[shipbubble_...]" so they land in the vendor_data sub-array.
	$submitted = is_array($wcfm_settings_form['vendor_data'] ?? null)
		? $wcfm_settings_form['vendor_data']
		: array();

	$vendor_info = shipbubble_get_vendor_info($vendor_id);

	// Read submitted values; fall back to what's already stored so partial saves don't blank fields.
	$name    = sanitize_text_field($submitted['shipbubble_sender_name'] ?? $vendor_info['sender_name']);
	$email   = sanitize_email($submitted['shipbubble_sender_email'] ?? $vendor_info['sender_email']);
	$phone   = sanitize_text_field($submitted['shipbubble_sender_phone'] ?? $vendor_info['sender_phone']);
	$street  = sanitize_text_field($submitted['shipbubble_pickup_address'] ?? $vendor_info['pickup_address']);
	$state   = sanitize_text_field($submitted['shipbubble_pickup_state'] ?? $vendor_info['pickup_state']);
	$country = sanitize_text_field($submitted['shipbubble_pickup_country'] ?? $vendor_info['pickup_country']);
	$category = sanitize_text_field($submitted['shipbubble_store_category'] ?? $vendor_info['store_category']);

	$local_pickup      = sanitize_text_field($submitted['shipbubble_local_pickup'] ?? $vendor_info['local_pickup']);
	$local_pickup      = 'yes' === $local_pickup ? 'yes' : 'no';
	$local_pickup_text = sanitize_text_field($submitted['shipbubble_local_pickup_text'] ?? $vendor_info['local_pickup_text']);

	// Detect if the address fields changed so we know whether to re-validate.
	$address_changed = ($name !== $vendor_info['sender_name'])
		|| ($email !== $vendor_info['sender_email'])
		|| ($phone !== $vendor_info['sender_phone'])
		|| ($street !== $vendor_info['pickup_address'])
		|| ($state !== $vendor_info['pickup_state'])
		|| ($country !== $vendor_info['pickup_country']);

	// Update the stored values but preserve existing validation state until we re-validate.
	$vendor_info['sender_name']       = $name;
	$vendor_info['sender_email']      = $email;
	$vendor_info['sender_phone']      = $phone;
	$vendor_info['pickup_address']    = $street;
	$vendor_info['pickup_state']      = $state;
	$vendor_info['pickup_country']    = $country;
	$vendor_info['store_category']    = $category;
	$vendor_info['local_pickup']      = $local_pickup;
	$vendor_info['local_pickup_text'] = $local_pickup_text;

	// Not all required fields filled — save progress without touching validation state.
	if (empty($name) || empty($email) || empty($phone) || empty($street) || empty($state) || empty($country) || empty($category)) {
		$vendor_info['address_validated']   = 'no';
		$vendor_info['address_code']        = '';
		$vendor_info['sandbox_address_code'] = '';
		update_user_meta($vendor_id, 'shipbubble_vendor_info', $vendor_info);
		return;
	}

	// If nothing address-related changed and already validated, skip the API call.
	$keys_cached = shipbubble_get_keys();
	$already_live    = empty($keys_cached['live_api_key']) || !empty($vendor_info['address_code']);
	$already_sandbox = empty($keys_cached['sandbox_api_key']) || !empty($vendor_info['sandbox_address_code']);
	if (!$address_changed && $vendor_info['address_validated'] === 'yes' && $already_live && $already_sandbox) {
		update_user_meta($vendor_id, 'shipbubble_vendor_info', $vendor_info);
		return;
	}

	$vendor_info['address_code']        = '';
	$vendor_info['sandbox_address_code'] = '';
	$vendor_info['address_validated']   = 'no';

	$api_name    = shipbubble_wcfm_normalize_sender_name($name);
	$api_address = implode(', ', array_filter(array($street, $state, $country)));
	$keys        = $keys_cached;

	if (!empty($keys['live_api_key'])) {
		$response = shipbubble_validate_address($api_name, $email, $phone, $api_address, '', $keys['live_api_key']);
		if (isset($response->response_code) && (int) $response->response_code === SHIPBUBBLE_RESPONSE_IS_OK) {
			$vendor_info['address_code'] = $response->data->address_code ?? '';
		}
	}

	if (!empty($keys['sandbox_api_key'])) {
		$response = shipbubble_validate_address($api_name, $email, $phone, $api_address, '', $keys['sandbox_api_key']);
		if (isset($response->response_code) && (int) $response->response_code === SHIPBUBBLE_RESPONSE_IS_OK) {
			$vendor_info['sandbox_address_code'] = $response->data->address_code ?? '';
		}
	}

	$live_valid    = empty($keys['live_api_key']) || !empty($vendor_info['address_code']);
	$sandbox_valid = empty($keys['sandbox_api_key']) || !empty($vendor_info['sandbox_address_code']);
	$has_key       = !empty($keys['live_api_key']) || !empty($keys['sandbox_api_key']);
	$vendor_info['address_validated'] = ($has_key && $live_valid && $sandbox_valid) ? 'yes' : 'no';

	update_user_meta($vendor_id, 'shipbubble_vendor_info', $vendor_info);
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

/**
 * Whether the cart's vendor still needs to finish their Shipbubble setup.
 *
 * Admin-owned carts (vendor id 0) fall back to the store-level address code, so
 * they are never treated as "not ready" here.
 */
function shipbubble_wcfm_checkout_seller_not_ready($not_ready): bool
{
	if (!shipbubble_wcfm_is_adapter_active()) {
		return (bool) $not_ready;
	}

	$vendor_id = shipbubble_wcfm_get_current_cart_vendor_id();

	if (is_null($vendor_id) || !$vendor_id) {
		return (bool) $not_ready;
	}

	return !shipbubble_wcfm_vendor_ready($vendor_id);
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
