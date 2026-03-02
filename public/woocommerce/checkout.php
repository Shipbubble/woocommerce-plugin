<?php

add_action('woocommerce_checkout_before_order_review', 'shipbubble_courier_list_container');
function shipbubble_courier_list_container()
{
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

	$isShipbubbleActive = isset($options['activate_shipbubble']) ? sanitize_text_field($options['activate_shipbubble']) : 'no';
	
	$container = '';
	$btnColor = '';
	$showLabel = true;

	$cartItemCount = WC()->cart->get_cart_contents_count();
	$isPhysicalProduct = false;
	$isVirtualProduct = false;

	// Check if any product in the cart is virtual
    foreach (WC()->cart->get_cart() as $cart_item) {
		$product_id = $cart_item['product_id'];
        $product = wc_get_product($product_id);

		if ($product && $product->is_virtual()) {
			// Your custom actions for virtual products in the cart
			$isVirtualProduct = true;
		} else {
			$isPhysicalProduct = true;
		}
    }
	
	if ($isPhysicalProduct) {
		$response = shipbubble_get_color_code();
		if (isset($response->response_code) && $response->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
			$btnColor = strlen($response->data->brand_color) > 1 ? $response->data->brand_color . ' !important' : '';
			$showLabel = (bool) $response->data->powered_by_label;

            ?>
            <style>
                :root {
                    --shipbubble-btn-color: <?= htmlspecialchars($btnColor) ?>;
                }
            </style>
            <?php
		}

		if ($isShipbubbleActive == 'yes') {
			$is_local_pickup_enabled = shipbubble_is_local_pickup_active();
			$local_pickup_text = shipbubble_get_option('local_pickup_text') ?: 'Pickup in store';
			$pickup_address = shipbubble_get_local_pickup_default();

			$container = '<div class="shipbubble-delivery-method-container">';

			if ($is_local_pickup_enabled) {
				$container .= '<div class="shipbubble-delivery-options-card">
            <div class="shipbubble-delivery-option" onclick="document.getElementById(\'shipbubble-pickup-option\').click();">
                <div class="shipbubble-option-container">
                    <div class="shipbubble-radio-label">
                        <input type="radio" id="shipbubble-pickup-option" name="delivery_method" value="pickup">
                        <input type="hidden" name="shippbuble_local_pickup_address" id="shipbubble-local-pickup-address" value="' . esc_attr($pickup_address) . '">
                        <div class="shipbubble-pickup-text-container">
                            <label for="shipbubble-pickup-option">' . esc_html($local_pickup_text) . '</label>
                            ' . ($pickup_address ? '<div class="shipbubble-pickup-address" id="shipbubble-local-pickup-address-text">' . esc_html($pickup_address) . '</div>' : '') . '
                        </div>
                    </div>
                </div>
            </div>
            <div class="shipbubble-delivery-option" onclick="document.getElementById(\'shipbubble-shipping-option\').click();">
                <div class="shipbubble-option-container">
                    <div class="shipbubble-radio-label">
                        <input type="radio" id="shipbubble-shipping-option" name="delivery_method" value="shipping">
                        <div class="shipbubble-pickup-text-container">
                        <label for="shipbubble-shipping-option">Get Delivery Prices</label>
                        <div class="shipbubble-pickup-address">(Click here to get shipping rates)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
			}

			$container .= '
    <div id="courier-section">
        <input type="hidden" id="shipbubble_rate_datetime" name="shipbubble_rate_datetime" value="">
        <input type="hidden" id="shipbubble_shipment_details" name="shipbubble_shipment_details" value="">
        <input type="hidden" id="shipbubble_selected_courier" name="shipbubble_selected_courier" value="">
        <input type="hidden" id="shipbubble_cost" name="shipbubble_cost" value="">
        <input type="hidden" id="shipbubble_courier_set" name="shipbubble_courier_set" value="false">
        <input type="hidden" id="shipbubble_reset_shipping_method" name="shipbubble_reset_shipping_method" value="false">
        <input type="hidden" id="request_token" name="request_token" value="">
        <input type="hidden" id="shipbubble_service_code" name="shipbubble_service_code" value="">
        <input type="hidden" id="shipbubble_courier_id" name="shipbubble_courier_id" value="">
        
        <div class="container-card">
            ' . (!$is_local_pickup_enabled ? '<button id="request_courier_rates" style="background: ' . $btnColor . ';">
                <p>Get Delivery Prices</p>
            </button>' : '') . '
            <div id="courier-list" class="container-delivery-card"></div>
        </div>
    </div>';

			if ($showLabel) {
				$container .= '
            <div class="sb-slogan-container" style="display:none; !important">
                <div class="sb-slogan">
                    <span>Powered by</span>
                    <img src="https://res.cloudinary.com/delivry/image/upload/v1693997143/app_assets/white-shipbubble-logo_ox2w53.svg" />
                </div>
            </div>
        ';
			} else {
				$container .= '<div style="margin: 8px 0;"></div>';
			}

			$container .= '</div>';
		}
	}


	echo $container;
}

add_action('wp_footer', 'shipbubble_courier_setup_on_change');
function shipbubble_courier_setup_on_change()
{
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

	$isShipbubbleActive = isset($options['activate_shipbubble']) ? sanitize_text_field($options['activate_shipbubble']) : 'no';
    $isShipbubbleActive = $isShipbubbleActive == 'yes';
	if (is_checkout() && $isShipbubbleActive) {
?>

		<script type="text/javascript">
			jQuery(document).ready(
				function($) {

                    // Use event delegation on the courier section or document
// This ensures the handler is always attached even for dynamically added radio buttons
                    $(document).on('change', '#courier-section input[name="delivery_option"]', function() {
                        if ($(this).is(':checked')) {
                            const checked_courier = $(this);
                            const courier_name = checked_courier.attr('data-courier_name');
                            const total = checked_courier.attr('data-cost');
                            const courier_id = checked_courier.attr('data-courier_id');
                            const service_code = checked_courier.attr('data-service_code');
                            const request_token = checked_courier.attr('data-request_token');

                            const request_datetime = $('#shipbubble_rate_datetime').val();

                            // console.log(request_datetime);

                            // Update hidden fields
                            // $('#shipbubble_shipment_details').val(JSON.stringify(shipment));
                            $('#shipbubble_selected_courier').val(courier_name);
                            $('#shipbubble_cost').val(total);
                            $('#request_token').val(request_token);
                            $('#shipbubble_service_code').val(service_code);
                            $('#shipbubble_courier_id').val(courier_id);

                            // $('#shipbubble_reset_cost').val('no');

                            // Set flag that courier has been set
                            $('#shipbubble_courier_set').val('true');

                            // Scroll to shipping totals if exists
                            let shippingTotals = $(".woocommerce-shipping-totals.shipping");

                            if (shippingTotals.length) {
                                $('html, body').animate({
                                    scrollTop: shippingTotals.offset().top
                                }, 1000);
                            }

                            // Trigger WooCommerce checkout update
                            jQuery('body').trigger('update_checkout');
                        }
                    });// Remove the click wrapper and use event delegation
                    $(document).on('change', 'input[name="delivery_option"]', function() {
                        if ($(this).is(':checked')) {
                            const checked_courier = $(this);
                            const courier_name = checked_courier.attr('data-courier_name');
                            const total = checked_courier.attr('data-cost');
                            const courier_id = checked_courier.attr('data-courier_id');
                            const service_code = checked_courier.attr('data-service_code');
                            const request_token = checked_courier.attr('data-request_token');

                            const request_datetime = $('#shipbubble_rate_datetime').val();

                            // Update hidden fields
                            $('#shipbubble_selected_courier').val(courier_name);
                            $('#shipbubble_cost').val(total);
                            $('#request_token').val(request_token);
                            $('#shipbubble_service_code').val(service_code);
                            $('#shipbubble_courier_id').val(courier_id);

                            // Set flag that courier has been set
                            $('#shipbubble_courier_set').val('true');

                            // Scroll to shipping totals if exists
                            let shippingTotals = $(".woocommerce-shipping-totals.shipping");

                            if (shippingTotals.length) {
                                $('html, body').animate({
                                    scrollTop: shippingTotals.offset().top
                                }, 1000);
                            }

                            // Trigger WooCommerce checkout update
                            jQuery('body').trigger('update_checkout');
                        }
                    });

					// Original handler for billing/shipping changes
					$('form').on('change', 'input[name^="billing"], input[name^="shipping"], select[name^="billing"], select[name^="shipping"]', function handleShippingChanges() {
						let list = $('#courier-list')
                        sbSlogan = $('.sb-slogan-container');

                        if ($('#shipbubble_courier_set').val() == 'false' && $('#shipbubble_rate_datetime').val().length !== 0) {
                            list.empty();
                            sbSlogan.hide();
                        }

                        if ($('#shipbubble_courier_set').val() == 'true') {
                            $('#shipbubble_reset_shipping_method').val('true');

                            // set flag that previously set courier should be removed
                            $('#shipbubble_courier_set').val('false');

                            list.empty();
                            sbSlogan.hide();
                        }

						const shippingRadio = $('input[name="delivery_method"]');
						if (shippingRadio.length > 0) {
							shippingRadio.prop('checked', false);
						}

						$(document.body).trigger('update_checkout');
                    })
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
	} elseif (isset($_POST['shipbubble_courier_set'])) {
		$post_data = $_POST;
	}

	// WooCommerce Blocks (Store API) context — read courier selection from session
	// set by the extensionCartUpdate callback in blocks-integration.php.
	if ( empty( $post_data ) && WC()->session ) {
		$blocks_courier = WC()->session->get( 'shipbubble_blocks_courier' );
		if ( ! empty( $blocks_courier ) ) {
			$post_data = [
				'delivery_option'              => '1',
				'shipbubble_selected_courier'  => $blocks_courier['courier_name'] ?? '',
				'shipbubble_cost'              => $blocks_courier['cost'] ?? '0',
				'shipbubble_courier_id'        => $blocks_courier['courier_id'] ?? '',
				'shipbubble_courier_set'       => 'true',
			];
		}
	}

	if (!empty($post_data)) {
		foreach ($post_data as $key => $value) {
			if (is_array($value)) {
				$post_data[$key] = $value;
			} else {
				$post_data[$key] = sanitize_text_field($value);
			}
		}
	}

	if (count($post_data) > 0 && isset($post_data['shipbubble_reset_shipping_method'])) {
		$remove_shipbubble_method = sanitize_text_field($post_data['shipbubble_reset_shipping_method']);
		$is_courier_set = sanitize_text_field($post_data['shipbubble_courier_set']);

		if (strtolower($remove_shipbubble_method) == 'true' && strtolower($is_courier_set) == 'false') {
			foreach ($rates as $rate_key => $rate) {
				if (SHIPBUBBLE_ID === $rate->method_id) {
					unset($rates[$rate_key]);
				}
			}
		}
	}

	if ((count($post_data) > 0 && isset($post_data['delivery_option'])) || (isset($post_data['shipbubble_courier_id']) && 'local_pickup' === $post_data['shipbubble_courier_id'])) {
		$selectedCourier = sanitize_text_field($post_data['shipbubble_selected_courier']);
		$cost = (float) sanitize_text_field($post_data['shipbubble_cost']);

		// Check if the desired shipping method exists among the rates
		$found_desired_shipping = false;

		foreach ($rates as $rate_key => $rate) {
			if (SHIPBUBBLE_ID === $rate->method_id) {
				// set rate cost
				if (!empty($selectedCourier) && strlen($selectedCourier)) {
					$rates[$rate_key]->label = $selectedCourier;
				}
				$rates[$rate_key]->cost = $cost;

				$found_desired_shipping = true;
			} else {
				if (strtolower($disableOtherShippingMethods) == 'yes') {
					unset($rates[$rate_key]); // Remove other shipping methods
				}
			}
		}

		if ($found_desired_shipping) {
			$rates = place_shipbubble_first_at_checkout($rates);
		}
	} else {
		// In the WooCommerce Blocks / Store API context, keep the Shipbubble rate
		// visible when no courier has been selected yet.  The React UI renders
		// below the shipping radios and lets the customer pick a courier; once
		// selected, extensionCartUpdate triggers a recalculation that enters the
		// if-branch above with the correct cost.
		$is_store_api = shipbubble_is_store_api_request();

		foreach ($rates as $rate_key => $rate) {
			if (SHIPBUBBLE_ID === $rate->method_id) {
				if ( ! $is_store_api ) {
					unset($rates[$rate_key]);
				}
				// In blocks context: leave the rate intact so it appears in the
				// shipping method list while the customer picks a courier.
			} else {
				if (strtolower($disableOtherShippingMethods) == 'yes') {
					unset($rates[$rate_key]); // Remove other shipping methods
				}
			}
		}
	}
	return $rates;
}

function place_shipbubble_first_at_checkout($rates)
{
	$shippingMethodKey = SHIPBUBBLE_ID;
	if (isset($rates[$shippingMethodKey])) {
        $rates1[$shippingMethodKey] = $rates[$shippingMethodKey];
        unset($rates[$shippingMethodKey]);
    }
	// select shipbubble on checkout
	WC()->session->set( 'chosen_shipping_methods', [$shippingMethodKey] );

    return isset($rates1) ? array_merge($rates1, $rates) : $rates;
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
