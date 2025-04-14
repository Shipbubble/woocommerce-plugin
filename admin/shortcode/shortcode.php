<?php

add_shortcode('shipbubble_checkout_shortcode', 'shipbubble_checkout_shortcode');

function shipbubble_checkout_shortcode($atts)
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
		}

		if ($isShipbubbleActive == 'yes') {
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
					<!--<input type="hidden" id="shipbubble_reset_cost" name="shipbubble_reset_cost" value="no">-->
					
					<div class="container-card">
						<button id="request_courier_rates" style="background: ' . $btnColor . ';">
							<p>Get Delivery Prices</p>
						</button> 
						<div id="courier-list" class="container-delivery-card"></div>
					</div>
				';

			if ($showLabel) {
				$container .= '
					<div class="sb-slogan-container" style="display:none; !important">
						<div class="sb-slogan">
							<span>Powered by</span>
							<img
								src="https://res.cloudinary.com/delivry/image/upload/v1693997143/app_assets/white-shipbubble-logo_ox2w53.svg" />
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



