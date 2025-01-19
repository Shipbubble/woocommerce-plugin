<div id="shipbubble-settings-local-pickup" class="shipbubble-tab-content" style="display:none;">
    <form method="post" action="options.php">
		<?php
		settings_fields('shipbubble_local_pickup');
		do_settings_sections('shipbubble_local_pickup');

		$switch_status = shipbubble_is_local_pickup_active() ? 'On' : 'Off';
		$switch_color = shipbubble_is_local_pickup_active() ? 'green' : 'grey';
        ?>
        <div class="shipbubble-settings">
            <table class="form-table">
            <tr>
                <th scope="row"><label for="shipbubble_local_pickup">Activate Local Pickup</label></th>
                <td class="forminp">
                    <fieldset>
                        <legend class="screen-reader-text"><span>Change Mode</span></legend>
                        <label class="switch">
                            <input type="checkbox" id="shipbubble_local_pickup" name="shipbubble_local_pickup" value="1" class="switch-checkbox shipbubble-actions-ignore " <?php checked(shipbubble_is_local_pickup_active()); ?>>
                            <span class="slider round" style="background-color: <?php echo esc_attr($switch_color); ?>;"></span>
                        </label>
                        <span class="switch-status" style="color: <?php echo esc_attr($switch_color); ?>; margin-left: 20px;"><?php echo esc_html($switch_status); ?></span>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="shipbubble_local_pickup_text">Local Pickup Text</label></th>
                <td>
                    <input type="text" name="shipbubble_local_pickup_text" id="shipbubble_local_pickup_text" class="regular-text" value="<?php echo esc_attr($options['local_pickup_text'] ?? ''); ?>">
                    <p class="description">Customize the text displayed for in-store pickup option.</p>
                </td>
            </tr>
        </table>
        </div>
        <div class="shipbubble-actions">
            <?php submit_button(); ?>
        </div>
    </form>
</div>