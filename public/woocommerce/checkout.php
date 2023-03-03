<?php

	add_action( 'woocommerce_after_checkout_billing_form', 'shipbubble_courier_list_container' );
	function shipbubble_courier_list_container()
	{
		$container = '
			<div id="courier-section">
				<div id="courier-list"></div>
				<input type="hidden" id="shipbubble_shipment_details" name="shipbubble_shipment_details" value="">
				<input type="hidden" id="shipbubble_selected_courier" name="shipbubble_selected_courier" value="">
				<input type="hidden" id="shipbubble_cost" name="shipbubble_cost" value="">

				<input type="hidden" id="request_token" name="request_token" value="">
				<input type="hidden" id="shipbubble_service_code" name="shipbubble_service_code" value="">
				<input type="hidden" id="shipbubble_courier_id" name="shipbubble_courier_id" value="">

				<button id="request_courier_rates" type="button" style="background: #D83854; color: #FFF; font-size: 12px; padding 16px 8px;">
					Request Courier Rates
				</button>
			</div>
		';

		$container .= '';

		echo $container;
	}

	add_action( 'wp_footer', 'shipbubble_courier_setup_on_change' );
	function shipbubble_courier_setup_on_change() 
	{
		if ( is_checkout() ) {
			?>

			<script type="text/javascript">
				jQuery( document ).ready(
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

									console.log('value is s ', checked_courier.attr('data-request_token'));

									const shipment = {
										request_token: checked_courier.attr('data-request_token'),
										shipment_cost: total,
										courier_id,
										courier_name,
										service_code,
									};
		
									$('#shipbubble_shipment_details').val( JSON.stringify(shipment) );
									$('#shipbubble_selected_courier').val( courier_name );
									$('#shipbubble_cost').val( total );

									$('#request_token').val( checked_courier.attr('data-request_token') );
									$('#shipbubble_service_code').val( service_code );
									$('#shipbubble_courier_id').val( courier_id );
		
									jQuery('body').trigger('update_checkout');
		
								}
							});
						});

					}
				);
			</script>

			<?php
		}
	}

	// Change rates on select courier 
	add_filter( 'woocommerce_package_rates', 'shipbubble_change_rates', 100, 2 );
	function shipbubble_change_rates( $rates, $packages ) 
	{
		if ( isset( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $post_data );
		} else {
			$post_data = $_POST;
		}

		// $customer = WC()->customer;
		
		// error_log(print_r($packages, true));

		// if (empty($packages['destination']['address']) ) {
		// 	foreach( $rates as $rate_key => $rate ) {
		// 		if ( 'shipbubble_shipping_services' === $rate->method_id ) {
		// 			unset( $rates[$rate_key] );
		// 		}
		// 	}
		// }

		if ( isset( $post_data['delivery_option'] ) ) {
			foreach( $rates as $rate_key => $rate ) {
				if ( SHIPBUBBLE_ID === $rate->method_id ) {
					// set rate cost
					if (!empty($post_data['shipbubble_selected_courier']) && strlen($post_data['shipbubble_selected_courier'])) {
						$rates[$rate_key]->label = $post_data['shipbubble_selected_courier'];
					}
					$rates[$rate_key]->cost = $post_data['shipbubble_cost'];
				} 
				// else {
				// 	unset($rates[$rate_key]); // Remove
				// }
			}
		} else {
			foreach( $rates as $rate_key => $rate ) {
				unset($rates[$rate_key]);
			}
		}
		return $rates;
	}

	// update the order review 
	add_action( 'woocommerce_checkout_update_order_review', 'shipbubble_checkout_update_order_review');
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

	