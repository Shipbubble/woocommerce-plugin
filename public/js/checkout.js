jQuery(document).ready(function($) {

	$('#courier-section').click(function() {

		const courier_radio_btn = $('input[name="delivery_option"]');
		courier_radio_btn.change(function() {
			if (courier_radio_btn.is(':checked')) {
				const checked_courier = $('input[type="radio"][name="delivery_option"]:checked');
				const courier_name = checked_courier.attr('data-courier_name');
				const total = checked_courier.attr('data-cost');
				const courier_id = checked_courier.attr('data-courier_id');
				const service_code = checked_courier.attr('data-service_code');

				const request_datetime = $('#shipbubble_rate_datetime').val();

				// console.log(request_datetime);

				// $('#shipbubble_shipment_details').val(JSON.stringify(shipment));
				$('#shipbubble_selected_courier').val(courier_name);
				$('#shipbubble_cost').val(total);

				$('#request_token').val(checked_courier.attr('data-request_token'));
				$('#shipbubble_service_code').val(service_code);
				$('#shipbubble_courier_id').val(courier_id);

				// $('#shipbubble_reset_cost').val('no');

				// set flag that courier has been set
				$('#shipbubble_courier_set').val('true');

				$('html, body').animate({
					scrollTop: $(".woocommerce-shipping-totals.shipping").offset().top
				}, 1000);

				jQuery('body').trigger('update_checkout');

			}
		});
	});

	// Original handler for billing/shipping changes
	$('div#customer_details').on('change', 'input[name^="billing"], input[name^="shipping"], select[name^="billing"], select[name^="shipping"]', function handleShippingChanges() {
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
});