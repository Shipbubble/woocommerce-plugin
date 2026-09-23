jQuery(document).ready(function($) {
	$('.shipbubble-accordion-toggle').on('click', function() {
		const $toggle = $(this);
		const $item = $toggle.closest('.shipbubble-accordion-item');
		const panel = document.getElementById($toggle.attr('aria-controls'));
		const isOpen = $toggle.attr('aria-expanded') === 'true';

		$('.shipbubble-accordion-item').removeClass('is-open');
		$('.shipbubble-accordion-toggle').attr('aria-expanded', 'false');
		$('.shipbubble-accordion-panel').prop('hidden', true);

		if (!isOpen && panel) {
			$item.addClass('is-open');
			$toggle.attr('aria-expanded', 'true');
			panel.hidden = false;
		}
	});

	// api settings
	let sandbox_api_key_input = $('#shipbubble_test_api_key'),
		live_api_key_input = $('#shipbubble_live_api_key'),
		sandbox_api_key_note = $('#sandbox_api_key_note'),
		live_api_key_note = $('#live_api_key_note');

	$('#shipbubble-api-keys-form').on('submit', function (e) {
		e.preventDefault();

		let sandbox_key = sandbox_api_key_input.val(),
			live_key = live_api_key_input.val(),
			activateShipbubble = $('#shipbubble_activate').is(':checked') ? 'yes' : 'no'

		validateShipbubbleApiKeys(sandbox_key, live_key, activateShipbubble)
	})

	function validateShipbubbleApiKeys(sandbox_api_key, live_api_key, activate_shipbubble) {
		disableForm('shipbubble-api-keys-form');

		$.post(ajaxurl, {
			nonce: ajax_wc_admin.nonce,
			action: 'validate_api_keys',
			data: { sandbox_api_key, live_api_key, activate_shipbubble },
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
			markFormSaved('shipbubble-settings-form');

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
				markFormSaved('shipbubble-local-pickup-form');
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

	/**
	 * Save the selected checkout type through the admin AJAX endpoint.
	 */
	$('#shipbubble-checkout-form').on('submit', function (e) {
		e.preventDefault();

		const checkoutType = $('input[name="shipbubble_checkout_type"]:checked').val();
		disableForm('shipbubble-checkout-form');

		$.post(ajaxurl, {
			nonce: ajax_wc_admin.nonce,
			action: 'shipbubble_update_checkout_type',
			data: {checkout_type: checkoutType},
			dataType: 'json'
		}).done(function (response) {
			if (!response || !response.success) {
				const message = response && response.data && response.data.message
					? response.data.message
					: 'Something went wrong';
				Swal.fire({
					icon: 'warning',
					title: '',
					text: 'Error updating Checkout Type: ' + message,
					showConfirmButton: false,
					timer: 4500
				});
				return;
			}

			Swal.fire({
				icon: 'success',
				title: 'Checkout Type updated successfully!',
				text: response.data.message,
				showConfirmButton: false,
				timer: 2000
			});
			markFormSaved('shipbubble-checkout-form');
		}).fail(function (xhr) {
			const response = xhr.responseJSON;
			const message = response && response.data && response.data.message
				? response.data.message
				: 'Something went wrong';
			Swal.fire({
				icon: 'warning',
				title: '',
				text: 'Error updating Checkout Type: ' + message,
				showConfirmButton: false,
				timer: 4500
			});
		}).always(function () {
			enableForm('shipbubble-checkout-form');
		});
	});

	$('#shipbubble-integrations-form').on('submit', function(e) {
		e.preventDefault();
		disableForm('shipbubble-integrations-form');
		const integrationData = {};

		if ($('#shipbubble_multi_vendor').length) {
			integrationData.multi_vendor = $('#shipbubble_multi_vendor').is(':checked') ? 1 : 0;
		}
		if ($('#shipbubble_multiloca_enabled').length) {
			integrationData.multiloca_enabled = $('#shipbubble_multiloca_enabled').is(':checked') ? 1 : 0;
		}

		$.post(ajaxurl, {
			nonce: ajax_wc_admin.nonce,
			action: 'shipbubble_update_integrations',
			data: integrationData,
			dataType: 'json'
		}).done(function(response) {
			if (!response || !response.success) {
				const message = response && response.data && response.data.message
					? response.data.message
					: 'Something went wrong';
				Swal.fire({
					icon: 'warning',
					title: 'Integrations update failed',
					text: message,
					showConfirmButton: false,
					timer: 4500
				});
				return;
			}

			markFormSaved('shipbubble-integrations-form');
			Swal.fire({
				icon: 'success',
				title: 'Integrations updated successfully!',
				text: response.data.message,
				showConfirmButton: false,
				timer: 2000
			});
		}).fail(function(xhr) {
			const response = xhr.responseJSON;
			const message = response && response.data && response.data.message
				? response.data.message
				: 'Something went wrong';
			Swal.fire({
				icon: 'warning',
				title: 'Integrations update failed',
				text: message,
				showConfirmButton: false,
				timer: 4500
			});
		}).always(function() {
			enableForm('shipbubble-integrations-form');
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

	const formInitialValues = new Map();

	function getFormValues($form) {
		const values = {};
		$form.find('.shipbubble-settings :input').each(function() {
			const $input = $(this);
			if ($input.hasClass('shipbubble-actions-ignore') || !$input.attr('name')) {
				return;
			}
			values[$input.attr('name')] = $input.is(':checkbox') || $input.is(':radio')
				? $input.is(':checked')
				: $input.val();
		});
		return values;
	}

	function updateFormActions($form) {
		const formId = $form.attr('id');
		const initialValues = formInitialValues.get(formId) || {};
		const currentValues = getFormValues($form);
		$form.find('.shipbubble-actions').toggle(JSON.stringify(initialValues) !== JSON.stringify(currentValues));
	}

	function markFormSaved(formId) {
		const $form = $('#' + formId);
		if (!$form.length) {
			return;
		}
		formInitialValues.set(formId, getFormValues($form));
		$form.find('.shipbubble-actions').hide();
	}

	$('.shipbubble-accordion-panel form').each(function() {
		const $form = $(this);
		markFormSaved($form.attr('id'));
		$form.find('.shipbubble-settings :input').on('change input', function() {
			updateFormActions($form);
		});
	});

});
