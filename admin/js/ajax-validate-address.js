// JavaScript for Admin Area

(function ($) {

    $(document).ready(function () {

        var mainform = $('form#mainform');
        var formBtn = mainform.find('button[type="submit"]');
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

            if (senderName.val() != '' && senderPhone.val() != '' && senderEmail.val() != '' &&
                senderAddress.val() != '' && senderState.val() != '' && senderCountry.find('option:selected').val() != ''
            ) {
                var addressPayload = {
                    name: senderName.val(),
                    phone: senderPhone.val(),
                    email: senderEmail.val(),
                    address: `${senderAddress.val()} ${senderState.val()} ${senderCountry.find('option:selected').text()}`
                };

                validate_sender_address(addressPayload);
            } else {
                formBtn.attr('disabled', true);
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
    });

})(jQuery);
