// JavaScript for Public Checkout Area
(function ($) {

    $(document).ready(function () {

        var requestRatesBtn = $('#request_courier_rates');

        requestRatesBtn.click(function (e) {

            e.preventDefault();
            $('#shipping-notice').remove();

            // initialize variables
            let firstName = lastName = email = phone = selectedCountry = selectedState = city = streetAddress = '';
            //08036922
            
            let useShippingAddress = $('#ship-to-different-address-checkbox');
            
            // use shipping variables
            if (useShippingAddress.is(':checked')) {
                console.log('checked here');
                firstName = $('input#shipping_first_name').val();
                lastName = $('input#shipping_last_name').val();
                email = $('input#shipping_email').val();
                phone = $('input#shipping_phone').val();
                selectedCountry = $('select#shipping_country').val();
                selectedState = $('select#shipping_state').val();
                city = $('input#shipping_city').val();
                streetAddress = $('input#shipping_address_1').val();

            } else {
                console.log('NOT oo checked here');
                // use billing variables
                firstName = $('input#billing_first_name').val();
                lastName = $('input#billing_last_name').val();
                email = $('input#billing_email').val();
                phone = $('input#billing_phone').val();
                selectedCountry = $('select#billing_country').val();
                selectedState = $('select#billing_state').val();
                city = $('input#billing_city').val();
                streetAddress = $('input#billing_address_1').val();
            }

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

                let sbSlogan = $('.sb-slogan-container');
                sbSlogan.show();

                // disable request btn
                $(this).prop('disabled', true);
                // $(this).addClass('load');

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

            list.empty();

            list.append(`
                <div class="container-delivery-card-header">
                    <p id="sb-status-text">Fetching delivery prices...</p>
                </div>
            `);
            
            let newCourierList = $('<div class="container-delivery-card-list loading"></div');

            list.append(newCourierList);

            let loaders = $(`<div class="loading">
                <span></span>
                <span></span>
                <span></span>
                <span></span>
            </div>`);

            $(loaders).insertAfter(newCourierList);

            loaders.show();

            $.post(
                ajaxUrl,
                data,
            ).done(function (data) {

                let response = JSON.parse(data);

                if (response.hasOwnProperty('status')) {
                    if (response['status'] == 'success') {
                        let output = response['data'];
                        // display data
                        // console.log('token ==> ', output.request_token);
                        // console.log('data ==> ', output);
                        // var section = $("#courier-section");

                        // dynamically add each courier
                        $('#sb-status-text').html('Select a delivery option');

                        loaders.hide();
                        
                        newCourierList.removeClass('loading');

                        $.each(output.couriers, function (i, value) {
                            // set total charge
                            let total = parseFloat(value.rate_card_amount) + parseFloat(output.extra_charges);

                            newCourierList.append(`
                                <div class="container-delivery-card-list-item">
                                    <div class="container-delivery-card-list-item-top">
                                        <img
                                            src="${value.courier_image}" />
                                        <div class="message">
                                            <p class="title">${value.courier_name}</p>
                                            <span>
                                                <span>${value.delivery_eta}</span>
                                            </span>
                                        </div>
                                    </div>
            
                                    <div class='radio-item special-radio'>
                                        <input type='radio' id="${value.courier_id}" name="delivery_option" 
                                        data-request_token="${output.request_token}" data-courier_name="${value.courier_name}" data-cost="${total}" data-service_code="${value.service_code}" data-courier_id="${value.courier_id}"
                                        />
                                        <label for='${value.courier_id}'>
                                            <p>
                                                ₦ ${total.toLocaleString()}
                                            </p>
                                            <span class='address-span'></span>
            
                                        </label>
                                    </div>
                                </div>
                            `);

                        });

                        const courier_radio_btn = $('input[name="delivery_option"]');

                        courier_radio_btn.change(function(){ 
                            //first remove class from all
                            courier_radio_btn.parent().parent().removeClass('active');
                        
                            if ($(this).is(':checked')) {
                                $(this).parent().parent().addClass('active')
                            }                                        
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

                var requestRatesBtn = $('#request_courier_rates');
                requestRatesBtn.prop('disabled', false);
                // requestRatesBtn.removeClass('load');

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
