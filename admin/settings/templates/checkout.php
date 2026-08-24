<?php
$checkout_type = shipbubble_get_checkout_type();
?>
<div id="shipbubble-settings-checkout" class="shipbubble-tab-content" style="display:none;">
	<form id="shipbubble-checkout-form">
		<?php
		settings_fields('shipbubble_checkout');
		do_settings_sections('shipbubble_checkout');
		?>
		<div class="shipbubble-settings">
			<table class="form-table">
				<tr>
					<th scope="row">Checkout Type</th>
					<td>
						<fieldset>
							<label for="shipbubble_checkout_type_default">
								<input type="radio" name="shipbubble_checkout_type" id="shipbubble_checkout_type_default" value="default" <?php checked($checkout_type, 'default'); ?>>
								<strong>Default</strong>
							</label>
							<p class="description">Customers click Get Delivery Prices, or choose Delivery when Local Pickup is enabled, before rates are displayed.</p>
							<br>
							<label for="shipbubble_checkout_type_dynamic">
								<input type="radio" name="shipbubble_checkout_type" id="shipbubble_checkout_type_dynamic" value="dynamic" <?php checked($checkout_type, 'dynamic'); ?>>
								<strong>Dynamic</strong>
							</label>
							<p class="description">Delivery rates load automatically when the customer's required checkout details are complete.</p>
						</fieldset>
					</td>
				</tr>
			</table>
		</div>
		<div class="shipbubble-actions">
			<?php submit_button(); ?>
		</div>
	</form>
</div>
