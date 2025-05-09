<?php

//if (is_shipbubble_multivendor_active()) {
    add_action('dokan_settings_after_store_phone', 'add_shipbubble_multivendor_form', 10, 2);

    function add_shipbubble_multivendor_form($current_user, $profile_info) {
        $info = shipbubble_get_vendor_info($current_user);
		$category = $info['store_category'];
		$categories_options = shipbubble_get_order_categories();
		?>

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

		<?php
	}

    add_action('dokan_store_profile_saved', 'shipbubble_handle_saved_profile', 10, 2);
    function shipbubble_handle_saved_profile($store_id, $dokan_settings) {
        $countries_obj = new WC_Countries();
        $countries = $countries_obj->get_countries();

        $info = shipbubble_get_vendor_info($store_id);
        $category = sanitize_text_field($_POST['shipbubble_category'] ?? '');

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

    //	if (
    //		$info['pickup_address'] === $address &&
    //		$info['pickup_state'] === $state &&
    //		$info['pickup_country'] === $country &&
    //		$info['sender_name'] === $store_name &&
    //		$info['sender_email'] === $email &&
    //		$info['sender_phone'] === $phone
    //	) {
    //		$revalidate_address = false;
    //	}

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


    add_action('dokan_settings_before_form', 'shipbubble_add_address_validate_notice', 10, 2);
    function shipbubble_add_address_validate_notice($current_user, $profile_info) {
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

//	add_filter('dokan_ajax_settings_response', 'check_ajax_settings_response', 10, 1);
//}