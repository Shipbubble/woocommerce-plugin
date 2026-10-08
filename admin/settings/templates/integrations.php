<?php
$multi_vendor = ($options['multi_vendor'] ?? 'no') === 'yes';
$multiloca_enabled = ($options['multiloca_enabled'] ?? 'no') === 'yes';
$wcfm_available = function_exists('shipbubble_is_wcfm_integration_available')
	&& shipbubble_is_wcfm_integration_available();
$multiloca_available = function_exists('shipbubble_is_multiloca_integration_available')
	&& shipbubble_is_multiloca_integration_available();
?>
<div id="shipbubble-settings-integrations" class="shipbubble-accordion-panel" hidden>
	<?php if (!$wcfm_available && !$multiloca_available) : ?>
		<div class="shipbubble-integrations-empty" role="status">
			<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
			<h3><?php esc_html_e('No supported integrations are active', 'shipbubble'); ?></h3>
			<p><?php esc_html_e('Install and activate WCFM Marketplace or MultiLoca to configure its Shipbubble integration here.', 'shipbubble'); ?></p>
		</div>
	<?php else : ?>
		<form id="shipbubble-integrations-form">
			<?php
			settings_fields('shipbubble_integrations');
			do_settings_sections('shipbubble_integrations');
			?>
			<div class="shipbubble-settings">
				<table class="form-table">
					<?php if ($wcfm_available) : ?>
						<tr>
							<th scope="row"><label for="shipbubble_multi_vendor">WCFM Marketplace</label></th>
							<td>
								<input type="checkbox" name="shipbubble_multi_vendor" id="shipbubble_multi_vendor" value="1" <?php checked($multi_vendor); ?>>
								<label for="shipbubble_multi_vendor">Use vendor addresses as Shipbubble senders</label>
								<p class="description">Uses each WCFM vendor's validated sender address while keeping the store API keys global.</p>
							</td>
						</tr>
					<?php endif; ?>

					<?php if ($multiloca_available) : ?>
						<tr>
							<th scope="row"><label for="shipbubble_multiloca_enabled">MultiLoca</label></th>
							<td>
								<input type="checkbox" name="shipbubble_multiloca_enabled" id="shipbubble_multiloca_enabled" value="1" <?php checked($multiloca_enabled); ?>>
								<label for="shipbubble_multiloca_enabled">Use product locations as Shipbubble senders</label>
								<p class="description">Validates MultiLoca locations with Shipbubble and requires all physical cart items to use one common location. After enabling, save each MultiLoca location once to validate it.</p>
							</td>
						</tr>
					<?php endif; ?>
				</table>
			</div>
			<div class="shipbubble-actions">
				<?php submit_button(); ?>
			</div>
		</form>
	<?php endif; ?>
</div>
