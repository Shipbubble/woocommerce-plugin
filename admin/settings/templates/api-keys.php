<div id="shipbubble-settings-api-tab" class="shipbubble-tab-content">
	<form id="shipbubble-api-keys-form">
		<?php
		settings_fields('shipbubble_api_keys');
		do_settings_sections('shipbubble_api_keys');
		$switch_status = shipbubble_is_live_mode() ? 'Live' : 'Test';
		$switch_color = shipbubble_is_live_mode() ? 'green' : 'grey';
        $live_key = $options['live_api_key'] ?? '';
        $test_key = $options['sandbox_api_key'] ?? '';
		?>
        <div class="shipbubble-settings">
            <table class="form-table">
			<tr>
				<th scope="row"><label for="shipbubble_mode">Change Mode</label></th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Change Mode</span></legend>
                        <label class="switch">
                            <input type="checkbox" id="shipbubble_mode" name="shipbubble_mode" value="1" class="switch-checkbox shipbubble-actions-ignore" <?php checked(shipbubble_is_live_mode()); ?>>
                            <span class="slider round" style="background-color: <?php echo esc_attr($switch_color); ?>;"></span>
                        </label>
                        <span class="switch-status" style="color: <?php echo esc_attr($switch_color); ?>; margin-left: 20px;"><?php echo esc_html($switch_status); ?></span>
                    </fieldset>
                </td>
            </tr>
			<tr>
				<th scope="row"><label for="shipbubble_live_api_key">Live API Key</label></th>
				<td>
                    <input type="text" name="shipbubble_live_api_key" id="shipbubble_live_api_key" class="regular-text" value="<?php echo esc_attr($live_key); ?>">
                    <p id="live_api_key_note" class="form_note_shipbubble_api_key"></p>
                </td>
			</tr>
			<tr>
				<th scope="row"><label for="shipbubble_test_api_key">Test API Key</label></th>
				<td>
                    <input type="text" name="shipbubble_test_api_key" id="shipbubble_test_api_key" class="regular-text" value="<?php echo esc_attr($test_key); ?>">
                    <p id="sandbox_api_key_note" class="form_note_shipbubble_api_key"></p>
                </td>
			</tr>
		</table>
        </div>
        <div class="shipbubble-actions">
			<?php submit_button(); ?>
        </div>
	</form>
</div>