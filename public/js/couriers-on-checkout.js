// JavaScript for Public Checkout Area
(function ($) {

    $(document).ready(function () {

        var requestRatesBtn = $('#request_courier_rates');

        requestRatesBtn.click(function (e) {

            e.preventDefault();
            $('#shipping-notice').remove();

            // initialize variables
            let firstName = $('input#billing_first_name').val();
            let lastName = $('input#billing_last_name').val();
            let email = $('input#billing_email').val();
            let phone = $('input#billing_phone').val();
            //08036922
            let streetAddress = $('input#shipping_address_1').val();

            if (streetAddress == '') {
                streetAddress = $('input#billing_address_1').val();
            }

            let city = $('input#shipping_city').val();
            if (city == '') {
                city = $('input#billing_city').val();
            }

            let selectedState = $('select#shipping_state').val();
            let selectedCountry = $('select#shipping_country').val();

            // check requirements are met
            if (firstName != '' && lastName != '' && email != '' && phone != '' && streetAddress != '' && city != '' && selectedState != '' && selectedCountry != '') {
                // hide notice
                $('#shipping-notice').remove();

                // Assemble payload
                let addressPayload = {
                    name: firstName + ' ' + lastName,
                    email,
                    phone,
                    address: streetAddress + ', ' + city + ', ' + selectedState + ', ' + selectedCountry,
                }

                // disable request btn
                // $(this).attr({
                //     class: 'loading sb_request_btn',
                //     disabled: true
                // });
                this.disabled = true;
                this.setAttribute('class', 'loading sb_request_btn');

                // Request shipping rates
                fetch_shipping_rates(addressPayload);
            } else {
                // Display notice
                let errorBox = [];
                let containerObject = { firstName, lastName, email, phone, streetAddress, city, selectedState, selectedCountry }

                for (const key in containerObject) {
                    if (containerObject[key] == '') {
                        errorBox.push(`${key}`);
                    }
                }

                $('<div>', {
                    id: 'shipping-notice',
                    class: 'woocommerce-error',
                }).text(`Ensure that you have filled your ${errorBox.join(', ')}`).prependTo('#courier-section').show();

            }

        });


        function fetch_shipping_rates(payload) {
            // submit the data
            let ajaxUrl = ajax_public.ajaxurl;

            // set ajax payload
            let data = {
                nonce: ajax_public.nonce,
                action: 'request_shipping_rates',
                data: payload
            };

            // initialize courier listing html container
            let list = $('#courier-list');

            $.post(
                ajaxUrl,
                data,
            ).done(function (data) {

                let response = JSON.parse(data);
                list.empty();

                if (response.hasOwnProperty('status')) {
                    if (response['status'] == 'success') {
                        let output = response['data'];
                        // display data
                        console.log('token ==> ', output.request_token);
                        // var section = $("#courier-section");

                        // dynamically add each courier
                        list.append("<h3>Select a delivery option <sup>*</sup></h3>");

                        $.each(output.couriers, function (i, value) {
                            // set total charge
                            let total = parseFloat(value.total) + parseFloat(output.extra_charges);

                            list.append(`
                                <div class="sb-card">
                                    <label for="${value.courier_id}">
                                        <input id="${value.courier_id}" type="radio" name="delivery_option" class="card-input-element" data-request_token="${output.request_token}" data-courier_name="${value.courier_name}" data-cost="${total}" data-service_code="${value.service_code}" data-courier_id="${value.courier_id}" required />
                                        
                                        <div class="card-input">
                                            <div class="flex-container-sb">
                                                <div style="display:flex;">
                                                    <img src="${value.courier_image}" />

                                                    <span>${value.courier_name}</span>
                                                </div>
                                    
                                                <span class="sb-price">₦${total}</span>
                                            </div>
                        
                                            <div style="flex-container-sb">
                                                <span>Delivery Time EST:</span>
                                                <span>${value.delivery_eta}</span>
                                            </div>
                                        </div>
                                    </label>						 
                                </div>
                            `);

                        });

                    } else {
                        console.log(response['data']);

                        list.empty();

                        $('<div>', {
                            id: 'shipping-notice',
                            class: 'woocommerce-info',
                        }).text(`${response['message']}`).prependTo('#courier-section').show();

                    }
                }

                requestRatesBtn = document.querySelector('#request_courier_rates');

                requestRatesBtn.disabled = false;
                requestRatesBtn.setAttribute('class', 'sb_request_btn');
                requestRatesBtn.innerHTML = '<span style="margin-left: auto;">Get Delivery Prices</span>&nbsp;&nbsp;<img style="margin-right: auto;" width="120" height="80" src="https://res.cloudinary.com/delivry/image/upload/v1678320403/app_assets/powered-by_rr4pbc.svg" alt="powered_by">';

                // requestRatesBtn.attr({
                //     class: 'sb_request_btn',
                //     disabled: false
                // }).html('<span style="margin-left: auto;">Get Delivery Prices</span>&nbsp;&nbsp;<img style="margin-right: auto;" width="120" height="80" src="https://res.cloudinary.com/delivry/image/upload/v1678320403/app_assets/powered-by_rr4pbc.svg" alt="powered_by">');

            }).fail(function () {
                console.log("failed");

                $('<div>', {
                    id: 'shipping-notice',
                    class: 'woocommerce-error',
                }).text(`Unable to display Couriers List, Please Try again later`).prependTo('#courier-section').show();

            });
        }

    });

})(jQuery);
