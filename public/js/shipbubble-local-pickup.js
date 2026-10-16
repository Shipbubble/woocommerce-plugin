jQuery(document).ready(function($) {
	if ($('#shipbubble-local-pickup-address').length === 0) return;

	let lastAddressKey = '';
	let activeRequest = null;
	let requestSequence = 0;

	/**
	 * Read optional checkout fields and resolve fixed country codes locally.
	 *
	 * @param {string} type Billing or shipping field prefix.
	 * @param {string} field Checkout address field.
	 * @returns {string} Field text, or an empty string when absent.
	 */
	function getFieldValue(type, field) {
		const selector = `#${type}_${field}`;
		const select = $(`select${selector}`);
		if (select.length) {
			return select.find('option:selected').text().trim();
		}

		const value = String($(selector).val() || '').trim();
		if (field === 'country') {
			const countries = shipbubble_local_pickup.countries || {};
			return countries[value.toUpperCase()] || value;
		}
		return value;
	}

	/**
	 * Refresh pickup locations for the currently selected checkout address.
	 * Optional lookups must always release their checkout loading overlay.
	 *
	 * @returns {void}
	 */
	function updateLocalPickupAddress() {
		const type = $('#ship-to-different-address-checkbox').is(':checked') ? 'shipping' : 'billing';
		const address = {
			address: getFieldValue(type, 'address_1'),
			city: getFieldValue(type, 'city'),
			state: getFieldValue(type, 'state'),
			country: getFieldValue(type, 'country')
		};
		const addressKey = JSON.stringify(address);
		if (addressKey === lastAddressKey) return;
		lastAddressKey = addressKey;

		const sequence = ++requestSequence;
		if (activeRequest) {
			activeRequest.abort();
			activeRequest = null;
		}

		if (Object.values(address).some(value => !value)) {
			$.unblockUI();
			return;
		}

		showLoadingScreen();
		activeRequest = $.ajax({
			url: shipbubble_local_pickup.ajaxurl,
			type: 'POST',
			dataType: 'json',
			timeout: 30000,
			data: {
				nonce: shipbubble_local_pickup.nonce,
				action: 'shipbubble_request_pickup_address',
				data: address
			}
		}).done(function(response) {
			if (sequence !== requestSequence) return;
			const pickupAddress = response && response.data && response.data.address;
			if (typeof pickupAddress === 'string' && pickupAddress.trim()) {
				$('#shipbubble-local-pickup-address').val(pickupAddress);
				$('#shipbubble-local-pickup-address-text').text(pickupAddress);
			}
		}).fail(function(xhr, status) {
			if (sequence !== requestSequence || status === 'abort') return;
			lastAddressKey = '';
			console.error('Failed to update local pickup address.');
		}).always(function() {
			if (sequence !== requestSequence) return;
			activeRequest = null;
			$.unblockUI();
		});
	}

	$(document).on('change', [
		'#shipping_address_1', '#shipping_city', '#shipping_state', '#shipping_country',
		'#billing_address_1', '#billing_city', '#billing_state', '#billing_country',
		'#ship-to-different-address-checkbox'
	].join(', '), updateLocalPickupAddress);

	updateLocalPickupAddress();

	/**
	 * Display the pickup address lookup loading screen.
	 *
	 * @returns {void}
	 */
	function showLoadingScreen() {
		$.blockUI({
			css: {
				width: '300px',
				border: 'none',
				'border-radius': '10px',
				left: 'calc(50% - 150px)',
				top: 'calc(50% - 150px)',
				padding: '20px'
			},
			message: '<div style="margin: 8px; font-size:100%;" class="shipbubble_saving_popup"><img src="'+shipbubble_local_pickup.logo+'" height="80" width="80" style="padding-bottom:10px;"><br>Fetching pickup address...</div>'
		});
	}
});
