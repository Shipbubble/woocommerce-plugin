jQuery(document).ready(function($) {
	const tabs = document.querySelectorAll('.nav-tab');
	const contents = document.querySelectorAll('.shipbubble-tab-content');
	tabs.forEach(tab => {
		tab.addEventListener('click', function(e) {
			e.preventDefault();

			// Remove active state from all tabs and hide all content
			tabs.forEach(t => t.classList.remove('nav-tab-active'));
			contents.forEach(c => c.style.display = 'none');

			// Add active state to clicked tab and show corresponding content
			this.classList.add('nav-tab-active');
			const target = document.querySelector(this.getAttribute('href'));
			if (target) target.style.display = 'block';
		});
	});

	// api settings
	let sandbox_api_key_input = $('#shipbubble_test_api_key'),
		live_api_key_input = $('#shipbubble_live_api_key'),
		sandbox_api_key_note = $('#sandbox_api_key_note'),
		live_api_key_note = $('#live_api_key_note');

	$('#shipbubble-api-keys-form').on('submit', function (e) {
		e.preventDefault();

		let sandbox_key = sandbox_api_key_input.val(),
			live_key = live_api_key_input.val()

		validateShipbubbleApiKeys(sandbox_key, live_key)
	})

	function validateShipbubbleApiKeys(sandbox_api_key, live_api_key) {
		disableForm('shipbubble-api-keys-form');

		$.post(ajaxurl, {
			nonce: ajax_wc_admin.nonce,
			action: 'validate_api_keys',
			data: { sandbox_api_key, live_api_key },
			dataType: 'json'
		}).done(handleApiKeyValidationResponse)
			.fail(handleApiKeyValidationError);
	}

	function handleApiKeyValidationResponse(data) {
		const response = JSON.parse(data);

		if (response.hasOwnProperty('response_code') && response['response_code'] === 200) {
			sandbox_api_key_note.text('Your API keys are valid').css('color', 'green');
			sandbox_api_key_input.css('border', '2px solid green');
			live_api_key_input.css('border', '2px solid green');
			$.unblockUI()
			Swal.fire({
				icon: 'success',
				title: 'API Validation successful',
				text: 'Your API keys are valid',
				showConfirmButton: false,
				timer: 4500
			}).then(() => {
				location.reload();
			});
		} else {
			handleApiKeyValidationError(response);
		}
	}

	function handleApiKeyValidationError(response = null) {
		sandbox_api_key_input.css('border', '1px solid red');
		live_api_key_input.css('border', '1px solid red');
		sandbox_api_key_note.css('color', 'red').text(response ? response.message : 'API keys are invalid, try again');
		live_api_key_note.css('color', 'red').text(response ? response.message : 'API keys are invalid, try again');

		Swal.fire({
			icon: 'warning',
			title: 'API Validation Failed',
			text: response ? response.message : 'Something went wrong, please try again later',
			showConfirmButton: false,
			timer: 4500
		});

		enableForm('shipbubble-api-keys-form');
	}

	sandbox_api_key_input.on('change', function () {
		validateApiKey($(this), 'sandbox');
	});

	live_api_key_input.on('change', function () {
		validateApiKey($(this), 'live');
	});

	function validateApiKey(input, type) {
		const api_key = input.val();
		const note = type === 'sandbox' ? sandbox_api_key_note : live_api_key_note;
		if (api_key.length < 10 ||
			(type === 'sandbox' && !api_key.startsWith('sb_sandbox')) ||
			(type === 'live' && !api_key.startsWith('sb_prod'))) {
			note.text(`Please provide a valid Shipbubble ${type} API key`).addClass('error');
			input.addClass('input-error');
		} else {
			note.text('').removeClass('error');
			input.removeClass('input-error');
		}
	}

	// address settings
	$('#shipbubble-settings-form').on('submit', function (e) {
		e.preventDefault();

		disableForm('shipbubble-settings-form');

		handleAddressFormSubmit()
	})


	function showValidationFailedAlert() {
		Swal.fire({
			icon: 'warning',
			title: 'Validation Failed',
			text: 'Please fill in all required store information.',
			showConfirmButton: false,
			timer: 4500
		});
	}

	function handleAddressFormSubmit() {
		const senderFields = {
			name: $('#shipbubble_sender_name'),
			phone: $('#shipbubble_sender_phone'),
			email: $('#shipbubble_sender_email'),
			address: $('#shipbubble_sender_address'),
			state: $('#shipbubble_sender_state'),
			country: $('#shipbubble_country'),
			category: $('#shipbubble_category'),
			disableOthers: $('#shipbubble_deactivate'),
		};

		if (Object.values(senderFields).some(field => field.val() === '')) {
			showValidationFailedAlert();
			Object.values(senderFields).forEach(field => {
				if (field.val() === '') field.addClass('input-error');
			});
			enableForm('shipbubble-settings-form');
			return;
		}

		const payload = {
			name: senderFields.name.val(),
			phone: senderFields.phone.val(),
			email: senderFields.email.val(),
			address: senderFields.address.val(),
			full_address: `${senderFields.address.val()}, ${senderFields.state.val()}, ${senderFields.country.find('option:selected').text()}`,
			state: senderFields.state.val(),
			store_category: senderFields.category.find('option:selected').val(),
			pickup_country: senderFields.country.val(),
			activate_shipbubble: $('#shipbubble_activate').is(':checked') ? 'yes' : 'no',
			disable_other_shipping_methods: senderFields.disableOthers.is(':checked') ? 'yes' : 'no',
		};

		validateSenderAddress(payload);
	}

	function validateSenderAddress(payload) {
		disableForm('shipbubble-settings-form');

		$.post(ajaxurl, {
			nonce: ajax_wc_admin.nonce,
			action: 'initiate_validate_sender_address',
			data: { payload },
			dataType: 'json'
		}).done(handleAddressValidationResponse)
			.fail(handleAddressValidationError);
	}

	function handleAddressValidationResponse(data) {
		const response = JSON.parse(data);
		jQuery.unblockUI();
		if (response.hasOwnProperty('response_code') && response['response_code'] === 200) {

			Swal.fire({
				icon: 'success',
				title: 'Address Validation success',
				text: response.message,
				showConfirmButton: false,
				timer: 4500
			});
		} else {
			handleAddressValidationError(response);
		}
		enableForm('shipbubble-settings-form');
	}

	function handleAddressValidationError(response = null) {
		jQuery.unblockUI();
		Swal.fire({
			icon: 'warning',
			title: 'Address Validation Failed',
			text: response ? response.message : 'Something went wrong, please try again later',
			showConfirmButton: false,
			timer: 4500
		});
		enableForm('shipbubble-settings-form');
	}


	var shipbubble_mode = $('#shipbubble_mode');

	if (shipbubble_mode.length) {
		// Add an event listener to update the status text when the checkbox state changes
		shipbubble_mode.on('change', function() {
			var isChecked = shipbubble_mode.is(':checked');
			var confirmMessage = isChecked
				? 'Do you want to switch to Live mode?'
				: 'Do you want to switch to Test mode?';

			if (confirm(confirmMessage)) {
				disableForm('shipbubble-api-keys-form', 'Switching...');
				// Perform AJAX call if the user confirms
				$.post(ajaxurl, {
					nonce: ajax_wc_admin.nonce,
					action: 'shipbubble_switch_mode',
					data: { 'live_mode' : isChecked ? 1 : 0 },
					dataType: 'json'
				}).done(function (data) {
					let response = JSON.parse(data);
					if (response.hasOwnProperty('response_code') && response['response_code'] !== 200) {
						Swal.fire({
							icon: 'warning',
							title: '',
							text:'Error switching mode: ' + response['message'] ?? 'Something went wrong',
							showConfirmButton: false,
							timer: 4500
						});
						// Revert the checkbox state on error
						shipbubble_mode.prop('checked', !isChecked);
					} else {
						Swal.fire({
							icon: 'success',
							title: 'Mode switched successfully!',
							text: response['message'],
							showConfirmButton: false,
							timer: 2000
						});
						$('#shipbubble_notice_div').remove()
						$('ul.subsubsub').before(response['notice']);
					}
					updateStatusText();
					enableForm('shipbubble-api-keys-form');
				}).fail(function (data) {
					let response = JSON.parse(data);
					Swal.fire({
						icon: 'warning',
						title: '',
						text:'Error switching mode: ' + response['message'] ?? 'Something went wrong',
						showConfirmButton: false,
						timer: 4500
					});
					// Revert the checkbox state on error
					shipbubble_mode.prop('checked', !isChecked);
					updateStatusText();
					enableForm('shipbubble-api-keys-form');
				});
			} else {
				// Revert the checkbox state if the user cancels
				shipbubble_mode.prop('checked', !isChecked);
			}
		});


		// Function to update the status text based on the checkbox state
		function updateStatusText() {
			var $statusText = shipbubble_mode.closest('.switch').next('.switch-status');
			var mode = shipbubble_mode.is(':checked') ? 'Live' : 'Test';
			var color = mode === 'Live' ? 'green' : 'grey';
			$statusText.text(mode).css('color', color);
			shipbubble_mode.next('.slider').css('background-color', color);
		}
	}

	$('#shipbubble-local-pickup-form').on('submit', function (e) {
		e.preventDefault();

		var $local_pickup = $('#shipbubble_local_pickup'),
			local_pickup_text = $('#shipbubble_local_pickup_text').val()
		// Add an event listener to update the status text when the checkbox state changes
		var isChecked = $local_pickup.is(':checked');

		disableForm('shipbubble-local-pickup-form');
		// Perform AJAX call if the user confirms
		$.post(ajaxurl, {
			nonce: ajax_wc_admin.nonce,
			action: 'shipbubble_toggle_local_pickup',
			data: {'local_pickup_enabled': isChecked ? 1 : 0, local_pickup_text},
			dataType: 'json'
		}).done(function (data) {
			let response = JSON.parse(data);
			if (response.hasOwnProperty('response_code') && response['response_code'] !== 200) {
				Swal.fire({
					icon: 'warning',
					title: '',
					text: 'Error updating Local Pickup: ' + (response['message'] || 'Something went wrong'),
					showConfirmButton: false,
					timer: 4500
				});
				// Revert the checkbox state on error
				$local_pickup.prop('checked', !isChecked);
			} else {
				Swal.fire({
					icon: 'success',
					title: 'Local Pickup updated successfully!',
					text: response['message'],
					showConfirmButton: false,
					timer: 2000
				});
				$('#shipbubble_local_pickup_notice_div').remove();
				$('ul.subsubsub').before(response['notice']);
			}
			enableForm('shipbubble-local-pickup-form');
		}).fail(function (data) {
			let response = JSON.parse(data);
			Swal.fire({
				icon: 'warning',
				title: '',
				text: 'Error updating Local Pickup: ' + (response['message'] || 'Something went wrong'),
				showConfirmButton: false,
				timer: 4500
			});
			// Revert the checkbox state on error
			enableForm('shipbubble-local-pickup-form');
		});
	});

	// form handlers
	function disableForm(form_id, loading_message = '') {
		showLoadingScreen(loading_message);
		$(`#${form_id} input, #${form_id} select`).prop('disabled', true).removeClass('input-error');
	}

	function enableForm(form_id) {
		$.unblockUI();
		$(`#${form_id} input, #${form_id} select`).prop('disabled', false);
	}

	function showLoadingScreen(message = '') {
		if (!message) {
			message = 'Saving...';
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
			message: '<div style="margin: 8px; font-size:150%;" class="shipbubble_saving_popup"><img src="'+ajax_wc_admin.logo+'" height="80" width="80" style="padding-bottom:10px;"><br>'+ message +'</div>'
		});
	}

	var initial_values = {};
	jQuery('.shipbubble-actions').hide();

	/**
	 * Store initial values of the settings
	 *
	 * @returns {void}
	 */
	function store_values() {
		jQuery('.shipbubble-settings :input').each(function() {
			if (jQuery(this).hasClass('shipbubble-actions-ignore')) {
				return;
			}
			if (jQuery(this).is(':checkbox')) {
				initial_values[jQuery(this).attr('name')] = jQuery(this).is(':checked');
			} else {
				initial_values[jQuery(this).attr('name')] = jQuery(this).val();
			}
		});
	}

	// Store initial values on page load
	store_values();

	// Add change event listener to all inputs
	jQuery('.shipbubble-settings :input').on('change', function()  {
		var all_inputs_back_to_original = true;
		jQuery('.shipbubble-settings :input').each(function() {
			var input_name = jQuery(this).attr('name');

			if (jQuery(this).hasClass('shipbubble-actions-ignore')) {
				return true;
			}

			if (jQuery(this).is(':checkbox')) {
				if (jQuery(this).is(':checked') !== initial_values[input_name]) {
					all_inputs_back_to_original = false;
					return false;
				}
			} else {
				if (jQuery(this).val() !== initial_values[input_name]) {
					all_inputs_back_to_original = false;
					return false;
				}
			}
		});

		if (all_inputs_back_to_original) {
			jQuery('.shipbubble-actions').hide();
		} else {
			jQuery('.shipbubble-actions').show();
		}
	});

	// Add click event listener to the button
	jQuery('.shipbubble-actions :input').on('click', function() {
		// Hide the actions div
		jQuery('.shipbubble-actions').hide();

		// Re-store the values
		store_values();
	});

});