jQuery(document).ready(function($) {
	var address_values = {};

	/**
	 * Store address values for shipping and billing addresses.
	 *
	 * @returns {void}
	 */
	function store_address_values() {
		let shipping_address = jQuery('#shipping_address_1').val(),
			shipping_city = jQuery('#shipping_city').val(),
			shipping_state = '',
			shipping_country = '',
			billing_address = jQuery('#billing_address_1').val(),
			billing_city = jQuery('#billing_city').val(),
			billing_state = '',
			billing_country = '';

		if ($('select#billing_country').length) {
			billing_country = $('select#billing_country option:selected').text();
		} else {
			billing_country = $('input#billing_country').val();
			billing_country = getCountryCode(billing_country);
		}

		if ($('select#shipping_country').length) {
			shipping_country = $('select#shipping_country option:selected').text();
		} else {
			shipping_country = $('input#shipping_country').val();
			shipping_country = getCountryCode(shipping_country);
		}

		if ($('select#billing_state').length) {
			billing_state = $('select#billing_state option:selected').text();
		} else {
			billing_state = $('input#billing_state').val();
		}

		if ($('select#shipping_state').length) {
			shipping_state = $('select#shipping_state option:selected').text();
		} else {
			shipping_state = $('input#shipping_state').val();
		}

		address_values = {
			'shipping_address': shipping_address,
			'shipping_city': shipping_city,
			'shipping_state': shipping_state,
			'shipping_country': shipping_country,
			'billing_address': billing_address,
			'billing_city': billing_city,
			'billing_state': billing_state,
			'billing_country': billing_country
		}

		let useShippingAddress = $('input#ship-to-different-address-checkbox');

		if (useShippingAddress.is(':checked')) {
			if (shipping_address.length > 0 && shipping_city.length > 0 && shipping_state.length > 0 && shipping_country.length > 0) {
				update_local_pickup_address('shipping');
			}
		} else {
			if (billing_address.length > 0 && billing_city.length > 0 && billing_state.length > 0 && billing_country.length > 0) {
				update_local_pickup_address('billing');
			}
		}
	}

	/**
	 * Check if the address has changed since the last stored values.
	 *
	 * @param {string} type - 'shipping' or 'billing'
	 *
	 * @returns {boolean}
	 */
	function has_address_changed(type) {
		let changed = false;

		let prefix = (type === 'shipping') ? 'shipping' : 'billing';

		let fields = {
			address: getFieldValue(type, 'address_1'),
			city: getFieldValue(type, 'city'),
			state: getFieldValue(type, 'state'),
			country: getFieldValue(type, 'country')
		};

		for (let key in fields) {
			let fullKey = `${prefix}_${key}`;
			if (fields[key] !== address_values[fullKey]) {
				changed = true;
				address_values[fullKey] = fields[key]; // update stored value
			}
		}

		return changed;
	}

	/**
	 * Handle address change for shipping or billing.
	 *
	 * @param {string} type - 'shipping' or 'billing'
	 *
	 * @returns {void}
	 */
	function handle_address_change(type) {
		if (has_address_changed(type)) {
			let required_fields = [
				address_values[`${type}_address`],
				address_values[`${type}_city`],
				address_values[`${type}_state`],
				address_values[`${type}_country`]
			];

			// If any field is missing or empty, do not proceed
			if (required_fields.some(val => !val || val.trim() === '')) {
				console.warn(`${type} address is incomplete. Skipping AJAX.`);
				return;
			}

			update_local_pickup_address(type);
		}
	}

	$('#ship-to-different-address-checkbox').on('change', function () {
		let useShippingAddress = $(this).is(':checked');

		let shippingValues = {
			address: getFieldValue('shipping', 'address_1'),
			city: getFieldValue('shipping', 'city'),
			state: getFieldValue('shipping', 'state'),
			country: getFieldValue('shipping', 'country')
		};

		let billingValues = {
			address: getFieldValue('billing', 'address_1'),
			city: getFieldValue('billing', 'city'),
			state: getFieldValue('billing', 'state'),
			country: getFieldValue('billing', 'country')
		};

		let isDifferent = Object.keys(shippingValues).some(key => {
			return shippingValues[key] !== billingValues[key];
		});

		if (isDifferent) {
			const type = useShippingAddress ? 'shipping' : 'billing';
			update_local_pickup_address(type);
		}

		// Update stored values to reflect current selection
		store_address_values();
	});

	function getFieldValue(type, field) {
		const selector = `#${type}_${field}`;
		if ($(`select${selector}`).length) {
			return $(`select${selector} option:selected`).text().trim();
		} else {
			return $(`input${selector}`).val()?.trim() || '';
		}
	}


	/**
	 * Update the local pickup address via AJAX.
	 *
	 * @param {string} type - 'shipping' or 'billing'
	 *
	 * @returns {void}
	 */
	function update_local_pickup_address(type) {
		showLoadingScreen()
		let data = {
			nonce: shipbubble_local_pickup.nonce,
			action: 'shipbubble_request_pickup_address',
			data: {
				address: address_values[`${type}_address`],
				city: address_values[`${type}_city`],
				state: address_values[`${type}_state`],
				country: address_values[`${type}_country`],
			}
		};

		$.post(shipbubble_local_pickup.ajaxurl, data).done(
			function(data) {
				let response = JSON.parse(data);

				if (response.hasOwnProperty('data')) {
					let address = response['data']['address'];
					if (address.length) {
						$('#shipbubble-local-pickup-address').val(address);
						$('#shipbubble-local-pickup-address-text').text(address);
					}
				}

				jQuery.unblockUI()
			}
		);
	}

	store_address_values();

	$('#shipping_address_1, #shipping_city, #shipping_state, #shipping_country').on('change', function() {
		if ($('input[name="delivery_method"]').length === 0) return;
		handle_address_change('shipping');
	});

	$('#billing_address_1, #billing_city, #billing_state, #billing_country').on('change', function() {
		if ($('input[name="delivery_method"]').length === 0) return;
		handle_address_change('billing');
	});

	/**
	 * Display a loading screen with a message.
	 *
	 * @param {string} [message='Processing...']
	 */
	function showLoadingScreen(message = '') {
		if (!message) {
			message = 'Fetching pickup address...';
		}
		$.blockUI({
			css: {
				width: '300px',
				border: 'none',
				'border-radius': '10px',
				left: 'calc(50% - 150px)',
				top: 'calc(50% - 150px)',
				padding: '20px'
			},
			message: '<div style="margin: 8px; font-size:100%;" class="shipbubble_saving_popup"><img src="'+shipbubble_local_pickup.logo+'" height="80" width="80" style="padding-bottom:10px;"><br>'+ message +'</div>'
		});
	}

});