<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Register the MultiLoca integration.
 *
 * @return void
 */
function shipbubble_multiloca_register_adapter()
{
	static $registered = false;

	if ($registered) {
		return;
	}

	$registered = true;

	add_action('created_locations', 'shipbubble_multiloca_validate_saved_location', 20, 2);
	add_action('edited_locations', 'shipbubble_multiloca_validate_saved_location', 20, 2);
	add_action('locations_edit_form_fields', 'shipbubble_multiloca_render_validation_status', 20, 2);
	add_action('admin_notices', 'shipbubble_multiloca_render_admin_notice');

	// MultiLoca must win after WCFM (priority 10) whenever a cart location applies.
	add_filter('shipbubble_get_address_code', 'shipbubble_multiloca_filter_address_code', 30, 3);
	add_filter('shipbubble_sender_address_error', 'shipbubble_multiloca_filter_sender_error', 30);
	add_filter('shipbubble_get_pickup_address', 'shipbubble_multiloca_filter_pickup_address', 30);
	add_filter('shipbubble_regenerated_sender_address_code', 'shipbubble_multiloca_filter_regenerated_sender_code', 30, 2);
	add_filter('is_shipbubble_active', 'shipbubble_multiloca_filter_shipbubble_active', 30);
	add_filter('shipbubble_checkout_seller_not_ready', 'shipbubble_multiloca_filter_seller_not_ready', 30);

	add_action('woocommerce_before_checkout_form', 'shipbubble_multiloca_validate_checkout_cart', 9);
	add_action('woocommerce_before_checkout_process', 'shipbubble_multiloca_validate_checkout_cart', 4);
	add_action('woocommerce_checkout_create_order', 'shipbubble_multiloca_persist_order_origin', 20, 2);
}

/**
 * Confirm the adapter is enabled and MultiLoca still exists.
 *
 * @return bool
 */
function shipbubble_multiloca_is_active(): bool
{
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

	return 'yes' === ($options['multiloca_enabled'] ?? 'no')
		&& function_exists('shipbubble_is_multiloca_integration_available')
		&& shipbubble_is_multiloca_integration_available();
}

/**
 * Read and normalize the contact/address fields stored by MultiLoca.
 *
 * @param int $term_id Location term ID.
 * @return array|WP_Error
 */
function shipbubble_multiloca_get_location_data($term_id)
{
	$term_id = absint($term_id);
	$term = $term_id ? get_term($term_id, 'locations') : null;

	if (!$term || is_wp_error($term)) {
		return new WP_Error('shipbubble_multiloca_missing_term', __('The selected MultiLoca location no longer exists.', 'shipbubble'));
	}

	$street_number = trim((string) get_term_meta($term_id, 'wcmlim_street_number', true));
	$route = trim((string) get_term_meta($term_id, 'wcmlim_route', true));
	$street = trim(implode(' ', array_filter(array($street_number, $route))));

	if ('' === $street) {
		$street = trim((string) get_term_meta($term_id, 'wcmlim_street_address', true));
	}

	$city = trim((string) get_term_meta($term_id, 'wcmlim_locality', true));
	if ('' === $city) {
		$city = trim((string) get_term_meta($term_id, 'wcmlim_city', true));
	}

	$postcode = trim((string) get_term_meta($term_id, 'wcmlim_postal_code', true));
	if ('' === $postcode) {
		$postcode = trim((string) get_term_meta($term_id, 'wcmlim_postcode', true));
	}

	$country_code = trim((string) get_term_meta($term_id, 'wcmlim_country', true));
	$state_code = trim((string) get_term_meta($term_id, 'wcmlim_administrative_area_level_1', true));
	$country = $country_code;
	$state = $state_code;
	$states = array();

	if (class_exists('WC_Countries')) {
		$countries = new WC_Countries();
		$country_names = $countries->get_countries();
		$states = $country_code ? $countries->get_states($country_code) : array();

		if (isset($country_names[$country_code])) {
			$country = $country_names[$country_code];
		}

		if (is_array($states) && isset($states[$state_code])) {
			$state = $states[$state_code];
		}
	}

	$email = sanitize_email((string) get_term_meta($term_id, 'wcmlim_email', true));
	$phone = sanitize_text_field((string) get_term_meta($term_id, 'wcmlim_phone', true));
	$missing = array();

	if ('' === trim((string) $term->name)) {
		$missing[] = __('name', 'shipbubble');
	}
	if ('' === $email || !is_email($email)) {
		$missing[] = __('valid email', 'shipbubble');
	}
	if ('' === $phone) {
		$missing[] = __('phone', 'shipbubble');
	}
	if ('' === $street) {
		$missing[] = __('street address', 'shipbubble');
	}
	if ('' === $city) {
		$missing[] = __('city/locality', 'shipbubble');
	}
	if ('' === $postcode) {
		$missing[] = __('postal code', 'shipbubble');
	}
	if ('' === $country_code) {
		$missing[] = __('country', 'shipbubble');
	}
	if (is_array($states) && !empty($states) && '' === $state_code) {
		$missing[] = __('state', 'shipbubble');
	}

	$full_address = implode(', ', array_filter(array($street, $city, $state, $country)));
	$formatted_address = implode(', ', array_filter(array($street, $city, $state, $postcode, $country)));

	$data = array(
		'term_id' => $term_id,
		'name' => sanitize_text_field($term->name),
		'email' => $email,
		'phone' => $phone,
		'street' => $street,
		'city' => $city,
		'state' => $state,
		'state_code' => $state_code,
		'postcode' => $postcode,
		'country' => $country,
		'country_code' => $country_code,
		'full_address' => $full_address,
		'formatted_address' => $formatted_address,
		'missing' => $missing,
	);

	$data['fingerprint'] = hash('sha256', wp_json_encode(array(
		$data['name'],
		$data['email'],
		$data['phone'],
		$data['street'],
		$data['city'],
		$data['state'],
		$data['state_code'],
		$data['postcode'],
		$data['country'],
		$data['country_code'],
	)));

	return $data;
}

/**
 * Hash an API key without storing the key itself in term metadata.
 *
 * @param string $key API key.
 * @return string
 */
function shipbubble_multiloca_key_fingerprint($key): string
{
	$key = (string) $key;

	return '' === $key ? '' : hash('sha256', $key);
}

/**
 * Convert a Shipbubble validation response into readable error strings.
 *
 * @param mixed  $response API response.
 * @param string $environment Environment label.
 * @return array
 */
function shipbubble_multiloca_response_errors($response, $environment): array
{
	$errors = array();

	if (is_object($response) && !empty($response->error)) {
		$raw_errors = is_array($response->error) ? $response->error : array($response->error);
		foreach ($raw_errors as $error) {
			if (is_scalar($error)) {
				$errors[] = (string) $error;
			}
		}
	}

	if (empty($errors) && is_object($response) && !empty($response->message)) {
		$errors[] = (string) $response->message;
	}

	if (empty($errors)) {
		$errors[] = __('Address validation failed.', 'shipbubble');
	}

	return array_map(function ($error) use ($environment) {
		return sprintf(__('%1$s: %2$s', 'shipbubble'), $environment, sanitize_text_field($error));
	}, $errors);
}

/**
 * Validate and cache a MultiLoca location for configured Shipbubble environments.
 *
 * @param int  $term_id Location term ID.
 * @param bool $force Force validation even when cached data is fresh.
 * @return array|WP_Error
 */
function shipbubble_multiloca_validate_location($term_id, $force = false)
{
	$data = shipbubble_multiloca_get_location_data($term_id);

	if (is_wp_error($data)) {
		return $data;
	}

	$keys = shipbubble_get_keys();
	$live_key_fingerprint = shipbubble_multiloca_key_fingerprint($keys['live_api_key'] ?? '');
	$sandbox_key_fingerprint = shipbubble_multiloca_key_fingerprint($keys['sandbox_api_key'] ?? '');
	$stored_fingerprint = (string) get_term_meta($term_id, 'shipbubble_address_fingerprint', true);
	$stored_live_key = (string) get_term_meta($term_id, 'shipbubble_live_api_key_fingerprint', true);
	$stored_sandbox_key = (string) get_term_meta($term_id, 'shipbubble_sandbox_api_key_fingerprint', true);

	$stored_validated = 'yes' === get_term_meta($term_id, 'shipbubble_address_validated', true);
	$live_code_is_ready = empty($keys['live_api_key']) || '' !== (string) get_term_meta($term_id, 'shipbubble_address_code', true);
	$sandbox_code_is_ready = empty($keys['sandbox_api_key']) || '' !== (string) get_term_meta($term_id, 'shipbubble_sandbox_address_code', true);

	if (!$force
		&& $stored_fingerprint === $data['fingerprint']
		&& $stored_live_key === $live_key_fingerprint
		&& $stored_sandbox_key === $sandbox_key_fingerprint
		&& $stored_validated
		&& $live_code_is_ready
		&& $sandbox_code_is_ready
	) {
		return array(
			'validated' => true,
			'errors' => (array) get_term_meta($term_id, 'shipbubble_address_validation_errors', true),
		);
	}

	$errors = array();
	$has_key = false;

	if (!empty($data['missing'])) {
		$errors[] = sprintf(
			__('Missing required MultiLoca fields: %s.', 'shipbubble'),
			implode(', ', $data['missing'])
		);
	}

	$environments = array(
		'live' => array(
			'label' => __('Live', 'shipbubble'),
			'key' => $keys['live_api_key'] ?? '',
			'code_meta' => 'shipbubble_address_code',
		),
		'sandbox' => array(
			'label' => __('Sandbox', 'shipbubble'),
			'key' => $keys['sandbox_api_key'] ?? '',
			'code_meta' => 'shipbubble_sandbox_address_code',
		),
	);

	foreach ($environments as $environment) {
		if (empty($environment['key'])) {
			delete_term_meta($term_id, $environment['code_meta']);
			continue;
		}

		$has_key = true;

		if (!empty($data['missing'])) {
			delete_term_meta($term_id, $environment['code_meta']);
			continue;
		}

		$response = shipbubble_validate_address(
			$data['name'],
			$data['email'],
			$data['phone'],
			$data['full_address'],
			$data['postcode'],
			$environment['key']
		);

		if (isset($response->response_code)
			&& (int) $response->response_code === SHIPBUBBLE_RESPONSE_IS_OK
			&& !empty($response->data->address_code)
		) {
			update_term_meta($term_id, $environment['code_meta'], sanitize_text_field($response->data->address_code));
		} else {
			delete_term_meta($term_id, $environment['code_meta']);
			$errors = array_merge($errors, shipbubble_multiloca_response_errors($response, $environment['label']));
		}
	}

	if (!$has_key) {
		$errors[] = __('No Shipbubble API key is configured.', 'shipbubble');
	}

	$validated = $has_key && empty($errors);
	$errors = array_values(array_unique(array_map('sanitize_text_field', $errors)));

	update_term_meta($term_id, 'shipbubble_address_validated', $validated ? 'yes' : 'no');
	update_term_meta($term_id, 'shipbubble_address_validation_errors', $errors);
	update_term_meta($term_id, 'shipbubble_address_fingerprint', $data['fingerprint']);
	update_term_meta($term_id, 'shipbubble_live_api_key_fingerprint', $live_key_fingerprint);
	update_term_meta($term_id, 'shipbubble_sandbox_api_key_fingerprint', $sandbox_key_fingerprint);

	return array('validated' => $validated, 'errors' => $errors);
}

/**
 * Validate a location after MultiLoca has persisted its term metadata.
 *
 * @param int $term_id Location term ID.
 * @param int $term_taxonomy_id Term taxonomy ID.
 * @return void
 */
function shipbubble_multiloca_validate_saved_location($term_id, $term_taxonomy_id = 0)
{
	unset($term_taxonomy_id);

	if (!shipbubble_multiloca_is_active()) {
		return;
	}

	$result = shipbubble_multiloca_validate_location($term_id);

	if (is_wp_error($result)) {
		shipbubble_multiloca_set_admin_notice('error', $result->get_error_message());
		return;
	}

	if ($result['validated']) {
		shipbubble_multiloca_set_admin_notice('success', __('The MultiLoca location was validated with Shipbubble.', 'shipbubble'));
		return;
	}

	shipbubble_multiloca_set_admin_notice(
		'error',
		implode(' ', $result['errors'])
	);
}

/**
 * Save a one-time per-user validation notice across the taxonomy redirect.
 *
 * @param string $type Notice type.
 * @param string $message Notice message.
 * @return void
 */
function shipbubble_multiloca_set_admin_notice($type, $message)
{
	$user_id = get_current_user_id();

	if (!$user_id) {
		return;
	}

	set_transient(
		'shipbubble_multiloca_notice_' . $user_id,
		array('type' => sanitize_key($type), 'message' => sanitize_text_field($message)),
		MINUTE_IN_SECONDS
	);
}

/**
 * Render the one-time validation result notice.
 *
 * @return void
 */
function shipbubble_multiloca_render_admin_notice()
{
	$user_id = get_current_user_id();
	$notice_key = 'shipbubble_multiloca_notice_' . $user_id;
	$notice = $user_id ? get_transient($notice_key) : false;

	if (!$notice || empty($notice['message'])) {
		return;
	}

	delete_transient($notice_key);
	$type = 'success' === ($notice['type'] ?? '') ? 'success' : 'error';

	echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>'
		. esc_html($notice['message'])
		. '</p></div>';
}

/**
 * Show Shipbubble validation state on the MultiLoca edit screen.
 *
 * @param WP_Term $term Location term.
 * @param string  $taxonomy Taxonomy name.
 * @return void
 */
function shipbubble_multiloca_render_validation_status($term, $taxonomy = '')
{
	unset($taxonomy);

	if (!shipbubble_multiloca_is_active() || !($term instanceof WP_Term)) {
		return;
	}

	$data = shipbubble_multiloca_get_location_data($term->term_id);
	$keys = shipbubble_get_keys();
	$errors = (array) get_term_meta($term->term_id, 'shipbubble_address_validation_errors', true);
	$fresh = !is_wp_error($data)
		&& $data['fingerprint'] === (string) get_term_meta($term->term_id, 'shipbubble_address_fingerprint', true)
		&& shipbubble_multiloca_key_fingerprint($keys['live_api_key'] ?? '') === (string) get_term_meta($term->term_id, 'shipbubble_live_api_key_fingerprint', true)
		&& shipbubble_multiloca_key_fingerprint($keys['sandbox_api_key'] ?? '') === (string) get_term_meta($term->term_id, 'shipbubble_sandbox_api_key_fingerprint', true);
	$validated = $fresh && 'yes' === get_term_meta($term->term_id, 'shipbubble_address_validated', true);
	$status = $validated ? __('Validated', 'shipbubble') : __('Needs validation', 'shipbubble');
	$color = $validated ? '#008a20' : '#b32d2e';
	?>
	<tr class="form-field">
		<th scope="row"><?php esc_html_e('Shipbubble sender', 'shipbubble'); ?></th>
		<td>
			<strong style="color: <?php echo esc_attr($color); ?>;"><?php echo esc_html($status); ?></strong>
			<p class="description"><?php esc_html_e('Saving this location validates changed contact or address details against the configured Shipbubble API keys.', 'shipbubble'); ?></p>
			<?php if (!$validated && !empty($errors)) : ?>
				<p class="description" style="color: #b32d2e;"><?php echo esc_html(implode(' ', $errors)); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}

/**
 * Find location terms assigned to a cart item, checking variation before parent.
 *
 * @param array $cart_item WooCommerce cart item.
 * @return array
 */
function shipbubble_multiloca_cart_item_location_ids(array $cart_item): array
{
	$variation_id = absint($cart_item['variation_id'] ?? 0);
	$product_id = absint($cart_item['product_id'] ?? 0);
	$object_ids = array_filter(array($variation_id, $product_id));

	foreach ($object_ids as $object_id) {
		$term_ids = wp_get_object_terms($object_id, 'locations', array('fields' => 'ids'));

		if (!is_wp_error($term_ids) && !empty($term_ids)) {
			return array_values(array_unique(array_map('absint', $term_ids)));
		}
	}

	return array();
}

/**
 * Resolve one common location from every physical cart item.
 *
 * @return array Array containing status, location_id and optional message.
 */
function shipbubble_multiloca_resolve_cart_location(): array
{
	if (!shipbubble_multiloca_is_active() || !function_exists('WC') || !WC()->cart) {
		return array('status' => 'not_applicable', 'location_id' => 0);
	}

	$location_ids = array();
	$physical_items = 0;

	foreach (WC()->cart->get_cart() as $cart_item) {
		$fallback_product_id = !empty($cart_item['variation_id'])
			? absint($cart_item['variation_id'])
			: absint($cart_item['product_id'] ?? 0);
		$product = isset($cart_item['data']) && is_object($cart_item['data'])
			? $cart_item['data']
			: wc_get_product($fallback_product_id);

		if ($product && $product->is_virtual()) {
			continue;
		}

		$physical_items++;
		$product_name = $product ? $product->get_name() : __('product', 'shipbubble');
		$selected_id = absint($cart_item['select_location']['location_termId'] ?? 0);

		if ($selected_id) {
			$selected_term = get_term($selected_id, 'locations');
			if (!$selected_term || is_wp_error($selected_term)) {
				return array(
					'status' => 'error',
					'location_id' => 0,
					'message' => sprintf(__('The selected location for %s is no longer available.', 'shipbubble'), $product_name),
				);
			}
			$location_ids[] = $selected_id;
			continue;
		}

		$assigned_ids = shipbubble_multiloca_cart_item_location_ids($cart_item);

		if (empty($assigned_ids)) {
			return array(
				'status' => 'error',
				'location_id' => 0,
				'message' => sprintf(__('Select a fulfillment location for %s before requesting shipping rates.', 'shipbubble'), $product_name),
			);
		}

		if (count($assigned_ids) > 1) {
			return array(
				'status' => 'error',
				'location_id' => 0,
				'message' => sprintf(__('%s is available at multiple locations. Select one location before requesting shipping rates.', 'shipbubble'), $product_name),
			);
		}

		$location_ids[] = reset($assigned_ids);
	}

	if (!$physical_items) {
		return array('status' => 'not_applicable', 'location_id' => 0);
	}

	$location_ids = array_values(array_unique(array_map('absint', $location_ids)));

	if (count($location_ids) !== 1) {
		return array(
			'status' => 'error',
			'location_id' => 0,
			'message' => __('All physical products must use the same MultiLoca location to ship in one order.', 'shipbubble'),
		);
	}

	return array('status' => 'resolved', 'location_id' => $location_ids[0]);
}

/**
 * Read a fresh environment-specific address code from a location.
 *
 * @param int  $term_id Location term ID.
 * @param bool $is_live Whether live mode is active.
 * @return string|WP_Error
 */
function shipbubble_multiloca_get_location_sender_code($term_id, $is_live)
{
	$data = shipbubble_multiloca_get_location_data($term_id);

	if (is_wp_error($data)) {
		return $data;
	}

	if (!empty($data['missing'])) {
		return new WP_Error(
			'shipbubble_multiloca_incomplete',
			sprintf(__('The selected location is missing: %s. Ask the store administrator to complete and save it.', 'shipbubble'), implode(', ', $data['missing']))
		);
	}

	$keys = shipbubble_get_keys();
	$key = $is_live ? ($keys['live_api_key'] ?? '') : ($keys['sandbox_api_key'] ?? '');
	$key_meta = $is_live ? 'shipbubble_live_api_key_fingerprint' : 'shipbubble_sandbox_api_key_fingerprint';
	$code_meta = $is_live ? 'shipbubble_address_code' : 'shipbubble_sandbox_address_code';
	$environment = $is_live ? __('live', 'shipbubble') : __('sandbox', 'shipbubble');

	if (empty($key)) {
		return new WP_Error('shipbubble_multiloca_missing_key', sprintf(__('No Shipbubble %s API key is configured.', 'shipbubble'), $environment));
	}

	$fingerprint_is_fresh = $data['fingerprint'] === (string) get_term_meta($term_id, 'shipbubble_address_fingerprint', true);
	$key_is_fresh = shipbubble_multiloca_key_fingerprint($key) === (string) get_term_meta($term_id, $key_meta, true);

	if (!$fingerprint_is_fresh || !$key_is_fresh) {
		return new WP_Error(
			'shipbubble_multiloca_stale',
			__('The selected location must be saved again so Shipbubble can validate its latest address and API key.', 'shipbubble')
		);
	}

	$address_code = (string) get_term_meta($term_id, $code_meta, true);

	if ('' === $address_code) {
		$errors = (array) get_term_meta($term_id, 'shipbubble_address_validation_errors', true);
		$message = !empty($errors)
			? implode(' ', array_map('sanitize_text_field', $errors))
			: sprintf(__('The selected location has not been validated for Shipbubble %s mode.', 'shipbubble'), $environment);

		return new WP_Error('shipbubble_multiloca_unvalidated', $message);
	}

	return $address_code;
}

/**
 * Resolve the current cart and its validated sender details.
 *
 * @param bool|null $is_live Optional environment override.
 * @return array|WP_Error
 */
function shipbubble_multiloca_get_current_sender($is_live = null)
{
	$resolved = shipbubble_multiloca_resolve_cart_location();

	if ('not_applicable' === $resolved['status']) {
		return array('status' => 'not_applicable');
	}

	if ('error' === $resolved['status']) {
		return new WP_Error('shipbubble_multiloca_cart', $resolved['message']);
	}

	if (is_null($is_live)) {
		$is_live = shipbubble_is_live_mode();
	}

	$code = shipbubble_multiloca_get_location_sender_code($resolved['location_id'], (bool) $is_live);

	if (is_wp_error($code)) {
		return $code;
	}

	$data = shipbubble_multiloca_get_location_data($resolved['location_id']);
	if (is_wp_error($data)) {
		return $data;
	}

	return array(
		'status' => 'resolved',
		'location_id' => $resolved['location_id'],
		'address_code' => $code,
		'location' => $data,
	);
}

/**
 * Make the MultiLoca address code authoritative for a resolved cart.
 *
 * @param string $address_code Existing WCFM/global code.
 * @param bool   $is_live Current environment.
 * @param mixed  $vendor_id Vendor context.
 * @return string
 */
function shipbubble_multiloca_filter_address_code($address_code, $is_live = true, $vendor_id = null): string
{
	unset($vendor_id);
	$sender = shipbubble_multiloca_get_current_sender((bool) $is_live);

	if (is_wp_error($sender)) {
		$GLOBALS['shipbubble_multiloca_sender_error'] = $sender->get_error_message();
		return '';
	}

	if ('not_applicable' === ($sender['status'] ?? '')) {
		unset($GLOBALS['shipbubble_multiloca_sender_error']);
		return (string) $address_code;
	}

	unset($GLOBALS['shipbubble_multiloca_sender_error']);

	return (string) $sender['address_code'];
}

/**
 * Replace the generic sender error with the actionable MultiLoca error.
 *
 * @param string $message Existing error.
 * @return string
 */
function shipbubble_multiloca_filter_sender_error($message): string
{
	if (!empty($GLOBALS['shipbubble_multiloca_sender_error'])) {
		return sanitize_text_field($GLOBALS['shipbubble_multiloca_sender_error']);
	}

	$sender = shipbubble_multiloca_get_current_sender();

	return is_wp_error($sender) ? $sender->get_error_message() : (string) $message;
}

/**
 * Display the resolved MultiLoca address for Shipbubble local pickup.
 *
 * @param string $pickup_address Existing address.
 * @return string
 */
function shipbubble_multiloca_filter_pickup_address($pickup_address): string
{
	$sender = shipbubble_multiloca_get_current_sender();

	if (is_wp_error($sender) || 'resolved' !== ($sender['status'] ?? '')) {
		return (string) $pickup_address;
	}

	return (string) $sender['location']['formatted_address'];
}

/**
 * Keep Shipbubble visible when MultiLoca owns sender readiness for this cart.
 *
 * @param string $is_active Existing yes/no state.
 * @return string
 */
function shipbubble_multiloca_filter_shipbubble_active($is_active): string
{
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

	if (!shipbubble_multiloca_is_active() || 'yes' !== ($options['activate_shipbubble'] ?? 'no')) {
		return (string) $is_active;
	}

	$resolved = shipbubble_multiloca_resolve_cart_location();

	return 'not_applicable' === $resolved['status'] ? (string) $is_active : 'yes';
}

/**
 * Suppress WCFM address-readiness failures when MultiLoca owns this cart.
 *
 * Multi-vendor cart restrictions remain unchanged; this only changes which
 * sender address must be ready for a single-vendor cart.
 *
 * @param bool $not_ready Existing WCFM readiness state.
 * @return bool
 */
function shipbubble_multiloca_filter_seller_not_ready($not_ready): bool
{
	if (!shipbubble_multiloca_is_active()) {
		return (bool) $not_ready;
	}

	$resolved = shipbubble_multiloca_resolve_cart_location();

	return 'not_applicable' === $resolved['status'] ? (bool) $not_ready : false;
}

/**
 * Add a checkout-blocking notice for unresolved or invalid locations.
 *
 * @return void
 */
function shipbubble_multiloca_validate_checkout_cart()
{
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

	if (!shipbubble_multiloca_is_active() || 'yes' !== ($options['activate_shipbubble'] ?? 'no')) {
		return;
	}

	$sender = shipbubble_multiloca_get_current_sender();

	if (!is_wp_error($sender)) {
		return;
	}

	$message = $sender->get_error_message();
	if (!function_exists('wc_has_notice') || !wc_has_notice($message, 'error')) {
		wc_add_notice($message, 'error');
	}
}

/**
 * Persist the exact MultiLoca origin selected at checkout on the order.
 *
 * @param WC_Order $order Order being created.
 * @param array    $data Posted checkout data.
 * @return void
 */
function shipbubble_multiloca_persist_order_origin($order, $data)
{
	unset($data);

	if (!is_object($order) || !method_exists($order, 'update_meta_data')) {
		return;
	}

	$sender = shipbubble_multiloca_get_current_sender();

	if (is_wp_error($sender) || 'resolved' !== ($sender['status'] ?? '')) {
		return;
	}

	$order->update_meta_data('shipbubble_multiloca_location_id', $sender['location_id']);
	$order->update_meta_data('shipbubble_multiloca_pickup_address', $sender['location']['formatted_address']);
	$order->update_meta_data('shipbubble_multiloca_address_code', $sender['address_code']);
	$order->update_meta_data('shipbubble_multiloca_environment', shipbubble_is_live_mode() ? 'live' : 'sandbox');
}

/**
 * Use the order's exact MultiLoca origin snapshot for rate-token regeneration.
 *
 * @param string|null $address_code Existing explicit sender code.
 * @param WC_Order    $order Order being regenerated.
 * @return string|null
 */
function shipbubble_multiloca_filter_regenerated_sender_code($address_code, $order)
{
	if (!is_object($order) || !method_exists($order, 'get_meta')) {
		return $address_code;
	}

	$location_id = absint($order->get_meta('shipbubble_multiloca_location_id', true));

	if (!$location_id) {
		return $address_code;
	}

	$stored_environment = sanitize_key((string) $order->get_meta('shipbubble_multiloca_environment', true));
	$current_environment = shipbubble_is_live_mode() ? 'live' : 'sandbox';

	if ($stored_environment && $stored_environment !== $current_environment) {
		$GLOBALS['shipbubble_multiloca_sender_error'] = sprintf(
			__('This order was quoted in Shipbubble %1$s mode. Switch back to %1$s mode before regenerating its rate.', 'shipbubble'),
			$stored_environment
		);
		return '';
	}

	$stored_code = sanitize_text_field((string) $order->get_meta('shipbubble_multiloca_address_code', true));

	if ('' === $stored_code) {
		$GLOBALS['shipbubble_multiloca_sender_error'] = __('This order does not contain its original MultiLoca sender code, so its rate cannot be regenerated safely.', 'shipbubble');
		return '';
	}

	unset($GLOBALS['shipbubble_multiloca_sender_error']);

	return $stored_code;
}
