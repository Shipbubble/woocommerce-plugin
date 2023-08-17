<?php

add_action('woocommerce_checkout_before_order_review', 'shipbubble_courier_list_container');
function shipbubble_courier_list_container()
{
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

	$isShipbubbleActive = isset($options['activate_shipbubble']) ? sanitize_text_field($options['activate_shipbubble']) : 'no';

	$container = '';

	if ($isShipbubbleActive == 'yes') {
		$container .= '
				<div id="courier-section">
					<input type="hidden" id="shipbubble_shipment_details" name="shipbubble_shipment_details" value="">
					<input type="hidden" id="shipbubble_selected_courier" name="shipbubble_selected_courier" value="">
					<input type="hidden" id="shipbubble_cost" name="shipbubble_cost" value="">
	
					<input type="hidden" id="request_token" name="request_token" value="">
					<input type="hidden" id="shipbubble_service_code" name="shipbubble_service_code" value="">
					<input type="hidden" id="shipbubble_courier_id" name="shipbubble_courier_id" value="">
					<input type="hidden" id="shipbubble_reset_cost" name="shipbubble_reset_cost" value="no">

					<div class="container-card">
						<button id="request_courier_rates">
							<p>Get Delivery Prices</p>
						</button>

						<div id="courier-list" class="container-delivery-card"></div>
					</div>
					<div class="sb-slogan-container" style="display:none; !important">
						<div class="sb-slogan">
							<span>Powered by</span>
							<img
								src="https://res.cloudinary.com/delivry/image/upload/v1684423516/app_assets/shipbubble-logo-black_t0gonq.svg" />
						</div>
					</div>

				</div>
			';
	}

	echo $container;
}

add_action('wp_footer', 'shipbubble_courier_setup_on_change');
function shipbubble_courier_setup_on_change()
{
	if (is_checkout()) {
?>

		<script type="text/javascript">
			jQuery(document).ready(
				function($) {

					$('#courier-section').click(function() {

						const courier_radio_btn = $('input[name="delivery_option"]');
						courier_radio_btn.change(function() {
							if (courier_radio_btn.is(':checked')) {
								const checked_courier = $('input[type="radio"][name="delivery_option"]:checked');
								const courier_name = checked_courier.attr('data-courier_name');
								const total = checked_courier.attr('data-cost');
								const courier_id = checked_courier.attr('data-courier_id');
								const service_code = checked_courier.attr('data-service_code');

								const shipment = {
									request_token: checked_courier.attr('data-request_token'),
									shipment_cost: total,
									courier_id,
									courier_name,
									service_code,
								};

								$('#shipbubble_shipment_details').val(JSON.stringify(shipment));
								$('#shipbubble_selected_courier').val(courier_name);
								$('#shipbubble_cost').val(total);

								$('#request_token').val(checked_courier.attr('data-request_token'));
								$('#shipbubble_service_code').val(service_code);
								$('#shipbubble_courier_id').val(courier_id);

								$('#shipbubble_reset_cost').val('no');

								jQuery('body').trigger('update_checkout');

							}
						});

						const request_rates_btn = $('button#request_courier_rates');
						request_rates_btn.click(function() {
							$('#shipbubble_reset_cost').val('yes');

							jQuery('body').trigger('update_checkout');
						});
					});

				}
			);
		</script>

<?php
	}
}

// Change rates on select courier 
add_filter('woocommerce_package_rates', 'shipbubble_change_rates', 100, 2);
function shipbubble_change_rates($rates, $packages)
{
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
	$disableOtherShippingMethods = isset($options['disable_other_shipping_methods']) ? sanitize_text_field($options['disable_other_shipping_methods']) : 'no';

	$post_data = [];
	if (isset($_POST['post_data'])) {
		wp_parse_str($_POST['post_data'], $post_data);
		// $post_data = array_map( 'sanitize_text_field', $post_data );
		foreach ($post_data as $key => $value) {
			if (is_array($value)) {
				$post_data[$key] = $value;
			} else {
				$post_data[$key] = sanitize_text_field($value);
			}
		}

		error_log(print_r($post_data, true));
	}

	if (count($post_data) > 0 && isset($post_data['shipbubble_reset_cost'])) {
		$reset_shipbubble_cost = sanitize_text_field($post_data['shipbubble_reset_cost']);
		if (strtolower($reset_shipbubble_cost) == 'yes') {
			foreach ($rates as $rate_key => $rate) {
				if (SHIPBUBBLE_ID === $rate->method_id) {
					unset($rates[$rate_key]);
				}
			}
		}
	}

	if (count($post_data) > 0 && isset($post_data['delivery_option'])) {
		$selectedCourier = sanitize_text_field($post_data['shipbubble_selected_courier']);
		$cost = (float) sanitize_text_field($post_data['shipbubble_cost']);

		foreach ($rates as $rate_key => $rate) {
			if (SHIPBUBBLE_ID === $rate->method_id) {
				// set rate cost
				if (!empty($selectedCourier) && strlen($selectedCourier)) {
					$rates[$rate_key]->label = $selectedCourier . ' (via Shipbubble)';
				}
				$rates[$rate_key]->cost = $cost;
			} else {
				if (strtolower($disableOtherShippingMethods) == 'yes') {
					unset($rates[$rate_key]); // Remove other shipping methods
				}
			}
		}
	} else {
		foreach ($rates as $rate_key => $rate) {
			if (SHIPBUBBLE_ID === $rate->method_id) {
				unset($rates[$rate_key]);
			} else {
				if (strtolower($disableOtherShippingMethods) == 'yes') {
					unset($rates[$rate_key]); // Remove other shipping methods
				}
			}
		}
	}
	return $rates;
}

// update the order review 
add_action('woocommerce_checkout_update_order_review', 'shipbubble_checkout_update_order_review');
function shipbubble_checkout_update_order_review($posted_data)
{
	global $woocommerce;

	$packages = $woocommerce->cart->get_shipping_packages();
	foreach ($packages as $package_key => $package) {
		$session_key = 'shipping_for_package_' . $package_key;

		// Clears the session
		// Woocommerce would recalculate and recall your calculate_shipping() function
		$stored_rates = WC()->session->__unset($session_key);
	}
}
