(function ($) {
    $(document).ready(function () {
        const mainform = $('form#mainform');
        const formBtn = mainform.find('button[type="submit"]');
        const api_key_input = mainform.find('#woocommerce_shipbubble_shipping_services_api_key');
        const activateShipbubble = mainform.find('#woocommerce_shipbubble_shipping_services_activate_shipbubble');
        const editApiKeyBtn = $('<a href="#" id="edit-api-key-btn">Change API Key settings</a>');
        const cancelEditBtn = $('<a href="#" id="cancel-edit-btn">Cancel</a>');
        const api_key_note = $('<p id="api_key_note" class="form_note_shipbubble_api_key"></p>');
        const sandbox_checkbox = mainform.find('#woocommerce_shipbubble_shipping_services_sandbox_mode');

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
            sandbox_checkbox.on('change', function () {
                const placeholder = sandbox_checkbox.is(':checked') ? 'sb_sandbox_xxxxxxxxxxxxxxxxxxxxx' : 'sb_prod_xxxxxxxxxxxxxxxxxxxxx';
                api_key_input.attr('placeholder', placeholder);
            });

            api_key_input.on('change', function () {
                const api_key = $(this).val();
                if (api_key.length < 10) {
                    api_key_note.text(sandbox_checkbox.is(':checked') ? 'Please Provide your Shipbubble sandbox API Key' : 'Please Provide your Shipbubble production API Key').addClass('error');
                } else {
                    api_key_note.text('').removeClass('error');
                }
            });

            $(api_key_note).insertAfter(api_key_input);
        }

        function handleAPIFormSubmit() {
            const api_key = api_key_input.val();
            const sandbox_mode = sandbox_checkbox.is(":checked");

            if (api_key.length <= 10 ||
                (sandbox_mode && !api_key.startsWith('sb_sandbox')) ||
                (!sandbox_mode && !api_key.startsWith('sb_prod'))) {
                api_key_note.text('Please Provide a valid Shipbubble API Key').addClass('error');
                api_key_input.addClass('input-error');
                return;
            }

            api_key_note.text('Validating your API Key...').removeClass('error').addClass('validating');
            api_key_input.removeClass('input-error').addClass('input-validating');

            validateShipbubbleApiKey(api_key, sandbox_mode);
        }

        function validateShipbubbleApiKey(api_key, sandbox_mode) {
            disableForm();
            formBtn.attr('disabled', true);

            $.post(ajaxurl, {
                nonce: ajax_wc_admin.nonce,
                action: 'validate_api_key',
                data: { api_key, sandbox_mode },
                dataType: 'json'
            }).done(handleApiKeyValidationResponse)
                .fail(handleApiKeyValidationError);
        }

        function handleApiKeyValidationResponse(data) {
            const response = JSON.parse(data);

            if (response.hasOwnProperty('response_code') && response['response_code'] === 200) {
                api_key_note.text('Your API Key is valid').css('color', 'green');
                api_key_input.css('border', '2px solid green');

                Swal.fire({
                    icon: 'success',
                    title: 'API Validation successful',
                    text: 'Your API Key is valid',
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
            api_key_input.css('border', '1px solid red');
            api_key_note.css('color', 'red').text(response ? response.message : 'API Key is invalid, try again');

            Swal.fire({
                icon: 'warning',
                title: 'API Validation Failed',
                text: response ? response.message : 'Something went wrong please try again later',
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
                address: `${senderFields.address.val()}, ${senderFields.state.val()}, ${senderFields.country.find('option:selected').text()}`,
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

            Swal.fire({
                icon: 'warning',
                title: 'Address Validation Failed',
                text: response ? response.message : 'Something went wrong please try again later',
                showConfirmButton: false,
                timer: 4500
            });

            formBtn.attr('disabled', false);
            enableForm();
        }

        function disableForm() {
            $('#mainform input, select').prop('disabled', true).removeClass('input-error');
        }

        function enableForm() {
            $('#mainform input, select').prop('disabled', false);
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
})(jQuery);
