<div id="shipbubble-settings-sender-tab" class="shipbubble-tab-content" style="display:none;">
	<form id="shipbubble-settings-form">
		<?php
		settings_fields('shipbubble_sender_details');
		do_settings_sections('shipbubble_sender_details');
		$categories_options = shipbubble_get_order_categories();
		$countries_obj = new WC_Countries();
		$countries = $countries_obj->__get('countries');
		$default_country = $countries_obj->get_base_country();

        if(isset($options['pickup_country'])) {
            $default_country = $options['pickup_country'];
        }
        $activate = $options['activate_shipbubble'] === 'yes';
        $other_plugins = $options['disable_other_shipping_methods'] === 'yes';
        $category = $options['shipping_category'];

		?>
        <div class="shipbubble-settings">
            <table class="form-table">
			<tr>
				<th scope="row"><label for="shipbubble_activate">Activate to use</label></th>
				<td>
					<input type="checkbox" name="shipbubble_activate" id="shipbubble_activate" value="1" <?php checked($activate); ?>>
					<label for="shipbubble_activate">Activate to use</label>
					<p class="description">Activate Shipbubble on Checkout.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="shipbubble_sender_name">Sender's Name</label></th>
				<td>
					<input type="text" name="shipbubble_sender_name" id="shipbubble_sender_name" class="regular-text" value="<?php echo esc_attr($options['sender_name'] ?? ''); ?>">
					<p class="description">This is the first and last name of the sender.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="shipbubble_sender_phone">Sender's Phone</label></th>
				<td>
					<input type="text" name="shipbubble_sender_phone" id="shipbubble_sender_phone" class="regular-text" value="<?php echo esc_attr($options['sender_phone'] ?? ''); ?>">
					<p class="description">This is the phone number of the sender.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="shipbubble_sender_email">Sender's Email Address</label></th>
				<td>
					<input type="email" name="shipbubble_sender_email" id="shipbubble_sender_email" class="regular-text" value="<?php echo esc_attr($options['sender_email'] ?? ''); ?>">
					<p class="description">This is the email of the sender.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="shipbubble_sender_address">Sender Address</label></th>
				<td>
					<input type="text" name="shipbubble_sender_address" id="shipbubble_sender_address" class="regular-text" value="<?php echo esc_attr($options['pickup_address'] ?? ''); ?>">
					<p class="description">This is the address setup for pickup.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="shipbubble_sender_state">State</label></th>
				<td><input type="text" name="shipbubble_sender_state" id="shipbubble_sender_state" class="regular-text" value="<?php echo esc_attr($options['pickup_state'] ?? ''); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="shipbubble_country">Country</label></th>
				<td>
					<select name="shipbubble_country" id="shipbubble_country" class="regular-text">
						<?php foreach ($countries as $key => $value): ?>
							<option value="<?php echo esc_attr($key); ?>" <?php selected($default_country, $key); ?>>
								<?php echo esc_html($value); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="shipbubble_category">Category</label></th>
				<td>
					<select name="shipbubble_category" id="shipbubble_category" class="regular-text">
						<?php foreach ($categories_options as $key => $value): ?>
							<option value="<?php echo esc_attr($key); ?>" <?php selected($category, $key); ?>>
								<?php echo esc_html($value); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="shipbubble_deactivate">Disable Other Shipping Method</label></th>
				<td>
					<input type="checkbox" name="shipbubble_deactivate" id="shipbubble_deactivate" value="1" <?php checked($other_plugins); ?>>
					<label for="shipbubble_deactivate">Disable Other Shipping Method</label>
                    <p class="description">Shipbubble will disable other shipping methods.</p>
				</td>
			</tr>
		</table>
        </div>
        <div class="shipbubble-actions">
			<?php submit_button(); ?>
        </div>
	</form>
</div>