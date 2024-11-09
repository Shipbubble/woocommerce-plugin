(function ($) {
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

    $(document).ready(function () {
        const mainform = $('form#mainform');
        const formBtn = mainform.find('button[type="submit"]');
        const sandbox_api_key_input = mainform.find('#woocommerce_shipbubble_shipping_services_sandbox_api_key');
        const live_api_key_input = mainform.find('#woocommerce_shipbubble_shipping_services_live_api_key');
        const activateShipbubble = mainform.find('#woocommerce_shipbubble_shipping_services_activate_shipbubble');
        const editApiKeyBtn = $('<a href="#" id="edit-api-key-btn">Change API Key settings</a>');
        const cancelEditBtn = $('<a href="#" id="cancel-edit-btn">Cancel</a>');
        const sandbox_api_key_note = $('<p id="sandbox_api_key_note" class="form_note_shipbubble_api_key"></p>');
        const live_api_key_note = $('<p id="live_api_key_note" class="form_note_shipbubble_api_key"></p>');

        function toggleFormFields(showApiKey) {
            mainform.find('.address_form_field').closest('tr').toggle(!showApiKey);
            mainform.find('.api_form_field').closest('tr').toggle(showApiKey);
            editApiKeyBtn.toggle(!showApiKey);
            cancelEditBtn.toggle(showApiKey);
        }

        function updateFormHandlers(handler) {
            mainform.off('submit').on('submit', function (e) {
                e.preventDefault();
                handler();
            });
            formBtn.off('click').on('click', function (e) {
                e.preventDefault();
                handler();
            });
        }

        function showApiKeyForm() {
            toggleFormFields(true);
            updateFormHandlers(handleAPIFormSubmit);
            $(cancelEditBtn).insertBefore(formBtn.closest('p'));
        }

        function hideApiKeyForm() {
            toggleFormFields(false);
            updateFormHandlers(handleAddressFormSubmit);
        }

        function setupApiKeyInputHandlers() {
            sandbox_api_key_input.on('change', function () {
                validateApiKey($(this), 'sandbox');
            });

            live_api_key_input.on('change', function () {
                validateApiKey($(this), 'live');
            });

            $(sandbox_api_key_note).insertAfter(sandbox_api_key_input);
            $(live_api_key_note).insertAfter(live_api_key_input);
        }
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

        function handleAPIFormSubmit() {
            const sandbox_api_key = sandbox_api_key_input.val();
            const live_api_key = live_api_key_input.val();
            let input_error = false;

            if (sandbox_api_key.length <= 10 || !sandbox_api_key.startsWith('sb_sandbox') ) {
                sandbox_api_key_note.text('Please provide valid Shipbubble test API key').addClass('error');
                sandbox_api_key_input.addClass('input-error');
                input_error = true;
            }

            if (live_api_key.length <= 10 || !live_api_key.startsWith('sb_prod')) {
                live_api_key_note.text('Please provide valid Shipbubble API key').addClass('error');
                live_api_key_input.addClass('input-error');
                input_error = true;
            }

            if (input_error) {
                return;
            }

            sandbox_api_key_note.text('Validating your API keys...').removeClass('error').addClass('validating');
            // live_api_key_note.text('Validating your API keys...').removeClass('error').addClass('validating');
            sandbox_api_key_input.removeClass('input-error').addClass('input-validating');
            live_api_key_input.removeClass('input-error').addClass('input-validating');

            validateShipbubbleApiKeys(sandbox_api_key, live_api_key);
        }

        function validateShipbubbleApiKeys(sandbox_api_key, live_api_key) {
            disableForm();
            formBtn.attr('disabled', true);

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
                });

                mainform.off('submit').submit();
            } else {
                handleApiKeyValidationError(response);
            }
        }

        function handleApiKeyValidationError(response = null) {
            formBtn.attr('disabled', false);
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

            enableForm();
        }

        function handleAddressFormSubmit() {
            const senderFields = {
                name: mainform.find('#woocommerce_shipbubble_shipping_services_sender_name'),
                phone: mainform.find('#woocommerce_shipbubble_shipping_services_sender_phone'),
                email: mainform.find('#woocommerce_shipbubble_shipping_services_sender_email'),
                address: mainform.find('#woocommerce_shipbubble_shipping_services_pickup_address'),
                state: mainform.find('#woocommerce_shipbubble_shipping_services_pickup_state'),
                country: mainform.find('#woocommerce_shipbubble_shipping_services_pickup_country'),
                category: mainform.find('#woocommerce_shipbubble_shipping_services_store_category'),
                disableOthers: mainform.find('#woocommerce_shipbubble_shipping_services_disable_other_shipping_methods')
            };

            if (Object.values(senderFields).some(field => field.val() === '')) {
                showValidationFailedAlert();
                Object.values(senderFields).forEach(field => {
                    if (field.val() === '') field.addClass('input-error');
                });
                enableForm();
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
                activate_shipbubble: activateShipbubble.is(':checked') ? 'yes' : 'no',
                disable_other_shipping_methods: senderFields.disableOthers.is(':checked') ? 'yes' : 'no'
            };

            validateSenderAddress(payload);
        }

        function showValidationFailedAlert() {
            Swal.fire({
                icon: 'warning',
                title: 'Address Validation Failed',
                text: 'Required fields are empty',
                showConfirmButton: false,
                timer: 4500
            });
        }

        function validateSenderAddress(payload) {
            disableForm();
            formBtn.attr('disabled', true);

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
                mainform.find('#woocommerce_shipbubble_shipping_services_address_code').val(response['data'].address_code);

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

            formBtn.attr('disabled', false);
            enableForm();
        }

        function handleAddressValidationError(response = null) {
            const addressCodeField = mainform.find('#woocommerce_shipbubble_shipping_services_address_code');
            addressCodeField.val(addressCodeField.data('initial-value'));
            jQuery.unblockUI();
            Swal.fire({
                icon: 'warning',
                title: 'Address Validation Failed',
                text: response ? response.message : 'Something went wrong, please try again later',
                showConfirmButton: false,
                timer: 4500
            });

            formBtn.attr('disabled', false);
            enableForm();
        }

        // Initial setup
        setupApiKeyInputHandlers();
        if (activateShipbubble.length) {
            mainform.find('.api_form_field').closest('tr').hide();
            $(editApiKeyBtn).insertBefore(formBtn.closest('p'));
            updateFormHandlers(handleAddressFormSubmit);
        } else {
            updateFormHandlers(handleAPIFormSubmit);
        }

        // Event handlers
        editApiKeyBtn.on('click', showApiKeyForm);
        cancelEditBtn.on('click', hideApiKeyForm);
    });

    function disableForm(loading_message = '') {
        showLoadingScreen(loading_message);
        $('#mainform input, select').prop('disabled', true).removeClass('input-error');
    }

    function enableForm() {
        $.unblockUI();
        $('#mainform input, select').prop('disabled', false);
    }

    $(document).ready(function($) {
        var $checkbox = $('#woocommerce_shipbubble_shipping_services_live_mode');

        if ($checkbox.length) {
            // Add an event listener to update the status text when the checkbox state changes
            $checkbox.on('change', function() {
                var isChecked = $checkbox.is(':checked');
                var confirmMessage = isChecked
                    ? 'Do you want to switch to Live mode?'
                    : 'Do you want to switch to Test mode?';

                if (confirm(confirmMessage)) {
                    disableForm('Switching...');
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
                            $checkbox.prop('checked', !isChecked);
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
                        enableForm();
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
                        $checkbox.prop('checked', !isChecked);
                        updateStatusText();
                        enableForm();
                    });
                } else {
                    // Revert the checkbox state if the user cancels
                    $checkbox.prop('checked', !isChecked);
                }
            });


            // Function to update the status text based on the checkbox state
            function updateStatusText() {
                var $statusText = $checkbox.closest('.switch').next('.switch-status');
                var mode = $checkbox.is(':checked') ? 'Live' : 'Test';
                var color = mode === 'Live' ? 'green' : 'grey';
                $statusText.text(mode).css('color', color);
                $checkbox.next('.slider').css('background-color', color);
            }
        }

    });

})(jQuery);