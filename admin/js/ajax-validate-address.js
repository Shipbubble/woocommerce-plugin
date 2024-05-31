// JavaScript for Admin Area

(function ($) {

    $(document).ready(function () {

        var mainform = $('form#mainform');
        var formBtn = mainform.find('button[type="submit"]');
        var api_key_input =  mainform.find('#woocommerce_shipbubble_shipping_services_api_key');

        if (api_key_input.length) {
            var api_key_note = $('<p class="form_note_shipbubble_api_key"></p>').insertAfter(api_key_input);

            var sandbox_checkbox = mainform.find('#woocommerce_shipbubble_shipping_services_sandbox_mode')

            // Set placeholder based on sandbox mode checkbox
            sandbox_checkbox.on('change', function() {
                if (sandbox_checkbox.is(':checked')) {
                    api_key_input.attr('placeholder', 'sb_sandbox_xxxxxxxxxxxxxxxxxxxxx');
                } else {
                    api_key_input.attr('placeholder', 'sb_prod_xxxxxxxxxxxxxxxxxxxxx');
                }
            });


            api_key_input.on('change', function() {
                var api_key = $(this).val();
                if (api_key.length < 10) {
                    if (sandbox_checkbox.is(':checked')) {
                        api_key_note.text('Please Provide your Shipbubble sandbox API Key');
                    } else {
                        api_key_note.text('Please Provide your Shipbubble production API Key');
                    }
                    api_key_note.addClass('error');
                } else {
                    api_key_note.text('').removeClass('error');
                }
            });

            mainform.submit(function (event) {

                // prevent form submission
                event.preventDefault();

                var api_key = api_key_input.val();
                var sandbox_mode = sandbox_checkbox.is(":checked");

                if (api_key.length <= 10) {
                    api_key_note.text('Please Provide your Shipbubble API Key').addClass('error');
                    api_key_input.addClass('input-error');
                    return;
                }

                if (sandbox_mode && !api_key.startsWith('sb_sandbox')) {
                    api_key_note.text('Please Provide your Shipbubble sandbox API Key').addClass('error');
                    api_key_input.addClass('input-error');
                    return;
                } else if (!sandbox_mode && !api_key.startsWith('sb_prod')) {
                    api_key_note.text('Please Provide your Shipbubble production API Key').addClass('error');
                    api_key_input.addClass('input-error');
                    return;
                }

                api_key_note.text('Validating your API Key...').removeClass('error').addClass('validating');
                api_key_input.removeClass('input-error').addClass('input-validating');

                validate_shipbubble_api_key(api_key, sandbox_mode);
            });

            function validate_shipbubble_api_key(api_key, sandbox_mode) {
                disableForm('#mainform')
                formBtn.attr('disabled', true);
                $.post(ajaxurl, {
                    nonce: ajax_wc_admin.nonce,
                    action: 'validate_api_key',
                    // url:    url
                    data: { api_key, sandbox_mode },
                    dataType: 'json'
                }, function (data) {

                    let response = JSON.parse(data);

                    if (response.hasOwnProperty('response_code')) {
                        if (200 == response['response_code']) {

                            formBtn.attr('disabled', false);

                            api_key_input.css('border', '2px solid green');
                            api_key_note.css('color', 'green');
                            api_key_note.text('Your API Key is valid');

                            Swal.fire({
                                icon: 'success',
                                title: 'API Validation successful',
                                text: 'Your API Key is valid',
                                showConfirmButton: false,
                                timer: 4500
                            });

                            jQuery("#mainform").off('submit').submit();
                        } else {

                            formBtn.attr('disabled', false);

                            // alert('API Key is invalid, try again');
                            api_key_input.css('border', '1px solid red');
                            api_key_note.css('color', 'red');
                            api_key_note.text('API Key is invalid, try again');

                            Swal.fire({
                                icon: 'warning',
                                title: 'API Validation Failed',
                                text: `${response['message']}`,
                                showConfirmButton: false,
                                timer: 4500
                            });
                            enableForm('#mainform')
                        }
                    } else {
                        formBtn.attr('disabled', false);

                        api_key_input.css('border', '1px solid red');
                        api_key_note.css('color', 'red');
                        api_key_note.text('API Key is invalid, try again');
                        // alert('API Key is invalid, try again');
                        Swal.fire({
                            icon: 'warning',
                            title: 'API Validation Failed',
                            text: 'Something went wrong please try again later',
                            showConfirmButton: false,
                            timer: 4500
                        });
                        enableForm('#mainform')
                    }

                }).fail(function (){
                    formBtn.attr('disabled', false);

                    api_key_input.css('border', '1px solid red');
                    api_key_note.css('color', 'red');
                    api_key_note.text('API Key is invalid, try again');
                    // alert('API Key is invalid, try again');
                    Swal.fire({
                        icon: 'warning',
                        title: 'API Validation Failed',
                        text: 'Something went wrong please try again later',
                        showConfirmButton: false,
                        timer: 4500
                    });
                    enableForm('#mainform')
                });
            }
        } else {
            var senderName = mainform.find('#woocommerce_shipbubble_shipping_services_sender_name');
            var senderPhone = mainform.find('#woocommerce_shipbubble_shipping_services_sender_phone');
            var senderEmail = mainform.find('#woocommerce_shipbubble_shipping_services_sender_email');
            var senderAddress = mainform.find('#woocommerce_shipbubble_shipping_services_pickup_address');
            var addressCodeField = mainform.find('#woocommerce_shipbubble_shipping_services_address_code');
            var senderState = mainform.find('#woocommerce_shipbubble_shipping_services_pickup_state');
            var senderCountry = mainform.find('#woocommerce_shipbubble_shipping_services_pickup_country');

            const addressCodeInitValue = addressCodeField.val();

            if (senderName.val() == '' && senderPhone.val() == '' && senderEmail.val() == '' &&
                senderAddress.val() == '' && senderState.val() == '' && senderCountry.find('option:selected').val() == ''
            ) {
                formBtn.attr('disabled', true);
            } else {
                formBtn.attr('disabled', false);
            }

            $('.address_form_field').change(function (e) {
                e.preventDefault();

                formBtn.attr('disabled', true);

                if (senderName.val() != '' && senderPhone.val() != '' && senderEmail.val() != '' &&
                    senderAddress.val() != '' && senderState.val() != '' && senderCountry.find('option:selected').val() != ''
                ) {
                    var addressPayload = {
                        name: senderName.val(),
                        phone: senderPhone.val(),
                        email: senderEmail.val(),
                        address: `${senderAddress.val()}, ${senderState.val()}, ${senderCountry.find('option:selected').text()}`
                    };

                    validate_sender_address(addressPayload);
                }

            });

            function validate_sender_address(payload) {
                $.post(ajaxurl, {
                    nonce: ajax_wc_admin.nonce,
                    action: 'initiate_validate_sender_address',
                    // url:    url
                    data: { payload },
                    dataType: 'json'
                }, function (data) {

                    let response = JSON.parse(data);

                    if (response.hasOwnProperty('response_code')) {
                        if (response['response_code'] == 200) {

                            addressCodeField.val(response['data'].address_code);

                            formBtn.attr('disabled', false);

                        } else {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Address Validation Failed',
                                text: `${response['message']}`,
                                showConfirmButton: false,
                                timer: 4500
                            });

                            addressCodeField.val(addressCodeInitValue);

                            formBtn.attr('disabled', true);
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Address Validation Failed',
                            text: `${response['message']}`,
                            showConfirmButton: false,
                            timer: 4500
                        });

                        addressCodeField.val(addressCodeInitValue);

                        formBtn.attr('disabled', true);
                    }

                });
            }
        }

    });

    function disableForm(formID){
        $(formID + ' input').prop('disabled', true);
    }

    function enableForm(formID){
        $(formID + ' input').prop('disabled', false);
    }

})(jQuery);
