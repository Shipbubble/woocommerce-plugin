<?php

if (is_shipbubble_dokan_multivendor_active() && !is_shipbubble_admin_page()) {
    add_action('dokan_settings_after_store_phone', 'add_shipbubble_multivendor_form', 10, 2);
	add_action('dokan_store_profile_saved', 'shipbubble_handle_saved_profile', 10, 2);
	add_action('dokan_settings_before_form', 'shipbubble_add_address_validate_notice', 10, 2);
	add_filter('shipbubble_get_local_pickup_text', 'shipbubble_get_vendor_local_pickup_text');
	add_filter('shipbubble_get_pickup_address', 'shipbubble_get_vendor_pickup_address');
	add_filter('shipbubble_is_local_pickup_active', 'shipbubble_is_vendor_local_pickup_active');
	add_filter('is_shipbubble_active', 'is_vendor_shipbubble_active');
	add_filter('shipbubble_get_address_code', 'get_vendor_address_code', 10, 2);
	add_filter('shipbubble_checkout_has_multi_vendor', 'shipbubble_checkout_has_multi_vendor');


	/**
	 * Add extra vendor settings fields to Dokan store settings page.
	 *
	 * @param int $current_user
	 * @param array $profile_info
	 * @return void
	 */
    function add_shipbubble_multivendor_form(int $current_user, $profile_info) {
        $info = shipbubble_get_vendor_info($current_user);
		$category = $info['store_category'];
		$categories_options = shipbubble_get_order_categories();
        $local_pickup = $info['local_pickup'] ?? 'no';
        $pickup_text = $info['local_pickup_text'] ?? '';
		?>
            <fieldset id="shipbubble_dokan_vendor_settings">
                <div class="dokan-form-group">
                <label class="dokan-w3 dokan-control-label" for="shipbubble_category">
                    <?php esc_html_e( 'Shipping Category ', 'shipbubble' ); ?>
                </label>
                <div class="dokan-w5 dokan-text-left">
                    <select name="shipbubble_category" id="shipbubble_category" class="regular-text dokan-form-control" required>
                    <?php foreach ($categories_options as $key => $value): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($category, $key); ?>>
                            <?php echo esc_html($value); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                </div>
            </div>
                <div class="dokan-form-group">
                <label class="dokan-w3 dokan-control-label"><?php esc_html_e( 'Local Pickup', 'shipbubble' ); ?></label>
                <div class="dokan-w5 dokan-text-left dokan_tock_check">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" id="shipbubble_local_pickup" value="on" <?php echo $local_pickup === 'yes' ? 'checked' : ''; ?> name="shipbubble_local_pickup"> <?php esc_html_e( 'Allow local pickup', 'shipbubble' ); ?>
                        </label>
                    </div>
                </div>
            </div>
                <div class="dokan-form-group">
                    <label class="dokan-w3 dokan-control-label" for="shipbubble_local_pickup_text"><?php esc_html_e( 'Local Pickup Text', 'shipbubble' ); ?></label>

                    <div class="dokan-w5 dokan-text-left">
                        <input id="shipbubble_local_pickup_text" required value="<?php echo esc_attr( $pickup_text ); ?>" name="shipbubble_local_pickup_text" placeholder="<?php esc_attr_e( 'Pickup in store', 'shipbubble' ); ?>" class="dokan-form-control" type="text">
                    </div>
                </div>
            </fieldset>

	    <?php
	}

	/**
	 * Handle and save custom Shipbubble fields when Dokan store settings are saved.
	 *
	 * @param int $store_id
	 * @param array $dokan_settings
	 * @return void
	 */
    function shipbubble_handle_saved_profile(int $store_id, array $dokan_settings) {
        $countries_obj = new WC_Countries();
        $countries = $countries_obj->get_countries();

        $info = shipbubble_get_vendor_info($store_id);
        $category = sanitize_text_field($_POST['shipbubble_category'] ?? '');
        $local_pickup = isset($_POST['shipbubble_local_pickup']) ? 'yes' : 'no';
        $local_pickup_text = sanitize_text_field($_POST['shipbubble_local_pickup_text']) ?: '';

        $address_settings = $dokan_settings['address'] ?? [];

        $address_parts = [];
        if (!empty($address_settings['street_1'])) $address_parts[] = $address_settings['street_1'];
        if (!empty($address_settings['street_2'])) $address_parts[] = $address_settings['street_2'];
        if (!empty($address_settings['city']))     $address_parts[] = $address_settings['city'];

        $address = implode(', ', $address_parts);

        $country_code = $address_settings['country'] ?? '';
        $state_code   = $address_settings['state'] ?? '';

        $country = $countries[$country_code] ?? $country_code;
        $states  = $countries_obj->get_states($country_code);
        $state   = $states[$state_code] ?? $state_code;

        $store_name = sanitize_text_field($dokan_settings['store_name'] ?? '');
        $phone      = sanitize_text_field($dokan_settings['phone'] ?? '');

        $user  = get_userdata($store_id);
        $email = $user && !empty($user->user_email) ? $user->user_email : '';

        $revalidate_address = true;

        if (empty($address) || empty($state) || empty($country) || empty($category)) {
            $info['address_validated'] = 'no';
            $revalidate_address = false;
        }


        if ($revalidate_address) {
            $store_name = trim($store_name);
            if (str_word_count($store_name) === 1) {
                $store_name = "$store_name $store_name";
            }

            $vendor_info = [
                'sender_name'          => $store_name,
                'sender_email'         => $email,
                'sender_phone'         => $phone,
                'store_category'       => $category,
                'pickup_address'       => $address,
                'pickup_state'         => $state,
                'pickup_country'       => $country,
                'address_code'         => '',
                'sandbox_address_code' => '',
                'address_validated'    => 'no',
                'local_pickup'         => $local_pickup,
                'local_pickup_text'    => $local_pickup_text
            ];

            $keys = shipbubble_get_keys();
            $full_address = implode(', ', array_filter([$address, $state, $country]));


            $sandbox_key_response = shipbubble_validate_address($store_name, $email, $phone, $full_address, $keys['sandbox_api_key']);
            if ('200' == $sandbox_key_response->response_code) {
                $vendor_info['sandbox_address_code'] = $sandbox_key_response->data->address_code;
            }

            $live_key_response = shipbubble_validate_address($store_name, $email, $phone, $full_address, $keys['live_api_key']);
            if ('200' == $live_key_response->response_code) {
                $vendor_info['address_code'] = $live_key_response->data->address_code;
            }

            $info = array_merge($info, $vendor_info);

            if (!empty($info['address_code']) && !empty($info['sandbox_address_code'])) {
                $info['address_validated'] = 'yes';
            }

            update_user_meta($store_id, 'shipbubble_vendor_info', $info);
        }
    }


	/**
	 * Display a warning notice if the vendor's address hasn't been validated.
	 *
	 * @param int $current_user
	 * @param array $profile_info
	 * @return void
	 */
    function shipbubble_add_address_validate_notice(int $current_user, array $profile_info) {
        $info = shipbubble_get_vendor_info($current_user);

        if (!isset($info['address_validated']) || $info['address_validated'] === 'no') {
            ?>
            <div style="border: 1px solid #dc3232; background: #fdd; color: #a00; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                <strong>Shipping Setup Incomplete:</strong><br>
                Please fill in the required store information to activate shipping features.
            </div>
            <?php
        }
    }

	/**
	 * Filter the pickup address returned by Shipbubble to use the vendor's address if available.
	 *
	 * @param string $pickup_address
	 * @return string
	 */
    function shipbubble_get_vendor_pickup_address(string $pickup_address): string
    {
        $vendor_info = get_dokan_vendor();
        if (!$vendor_info) return $pickup_address;

        if ($vendor_info['address_validated']  == 'no') return $pickup_address;

        $local_pickup_enabled = $vendor_info['local_pickup'] ?? 'no';

        if ($local_pickup_enabled === 'yes' && !empty($vendor_info['pickup_address'])) {
            $full_address = $vendor_info['pickup_address'];

            if (!empty($vendor_info['pickup_state'])) {
                $full_address .= ', ' . $vendor_info['pickup_state'];
            }

            if (!empty($vendor_info['pickup_country'])) {
                $full_address .= ', ' . $vendor_info['pickup_country'];
            }

            return $full_address;
	}

        return $pickup_address;
    }

	/**
	 * Determine whether the vendor has local pickup enabled and address validated.
	 *
	 * @param bool $is_active
	 * @return bool
	 */
    function shipbubble_is_vendor_local_pickup_active(bool $is_active): bool
    {
	    $vendor_info = get_dokan_vendor();

        if (!$vendor_info || $vendor_info['address_validated']  == 'no' || !isset($vendor_info['local_pickup'])) return false;

        return $is_active && $vendor_info['local_pickup'] === 'yes';
    }

	/**
	 * Check whether Shipbubble is active and vendor address is validated.
	 *
	 * @param string $is_active
	 * @return bool
	 */
    function is_vendor_shipbubble_active(string $is_active) {
	    $vendor_info = get_dokan_vendor();

	    if (!$vendor_info) return $is_active;

        return ($is_active == 'yes' && $vendor_info['address_validated'] == 'yes') ? 'yes' : 'no';
    }

	/**
	 * Return the local pickup text for the vendor, if set.
	 *
	 * @param string $pickup_text
	 * @return string
	 */
    function shipbubble_get_vendor_local_pickup_text(string $pickup_text): string
    {
	    $vendor_info = get_dokan_vendor();

	    if (!$vendor_info) return $pickup_text;

        return $vendor_info['local_pickup_text'] ?: $pickup_text;
    }

	/**
	 * Retrieve the Dokan vendor info for the first vendor found in the cart.
	 *
	 * @return array|false Returns vendor info array or false if not found.
	 */
    function get_dokan_vendor()
    {
        $vendor_id = 0;

	    foreach (WC()->cart->get_cart() as $cart_item) {
		    $product_id = $cart_item['product_id'];
		    $vendor     = dokan_get_vendor_by_product( $product_id );
		    $vendor_id  = $vendor && $vendor->get_id() ? $vendor->get_id() : 0;
	    }

        if (!$vendor_id) return false;

	    return shipbubble_get_vendor_info($vendor_id);
    }

	/**
	 * Retrieves the validated address code for a vendor.
	 *
	 * This function fetches the vendor's address code based on the environment (live or sandbox).
	 * It first checks if a valid vendor exists and whether their address has been validated.
	 *
	 * @param string $address_code Default fallback address code.
	 * @param bool   $is_live Optional. Whether to return the live address code. Default is true.
	 *
	 * @return string The vendor's address code (live or sandbox), or an empty string if not validated.
	 */
	function get_vendor_address_code(string $address_code, bool $is_live = true): string
	{
		$vendor_info = get_dokan_vendor();

		if (!$vendor_info) return $address_code;

		if ($vendor_info['address_validated'] != 'yes') return '';

		if ($is_live) return $vendor_info['address_code'];

		return $vendor_info['sandbox_address_code'];
	}


	/**
     * Check if the cart has multiple vendors.
	 * @param $has_multi_vendor
	 * @return bool
	 */
    function shipbubble_checkout_has_multi_vendor($has_multi_vendor): bool
    {
        $vendors = [];

	    foreach (WC()->cart->get_cart() as $cart_item) {
		    $product_id = $cart_item['product_id'];
		    $vendor     = dokan_get_vendor_by_product($product_id);
            if ($vendor && $vendor->get_id()) {
                $vendors[] = $vendor->get_id();
            }
	    }

        $vendors = array_unique($vendors);

        if (count($vendors) > 1) {
            $has_multi_vendor = true;
        } else {
            $has_multi_vendor = false;
        }

        return $has_multi_vendor;
    }
}