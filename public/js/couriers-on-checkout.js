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

            let shippingStateRequired = billingStateRequired = 0;

            let useShippingAddress = $('input#ship-to-different-address-checkbox');

            // order comments
            orderComments = $('textarea#order_comments').val();

            // use shipping variables
            if (useShippingAddress.is(':checked')) {

                firstName = $('input#shipping_first_name').val();
                lastName = $('input#shipping_last_name').val();
                selectedCountry = $('select#shipping_country option:selected').text();
                city = $('input#shipping_city').val();
                streetAddress = $('input#shipping_address_1').val();

                if ($('input#shipping_email').val() == undefined) {
                    email = $('input#billing_email').val();
                } else {
                    email = $('input#shipping_email').val();
                }

                if ($('input#shipping_phone').val() == undefined) {
                    phone = $('input#billing_phone').val();
                } else {
                    phone = $('input#shipping_phone').val();
                }

                if ($('select#shipping_city').length) {
                    city = $('select#shipping_city option:selected').text();
                } else {
                    city = $('input#shipping_city').val();
                }

                billingStateRequired = $('label[for="billing_state"]').find('abbr.required').length;

                if ($('select#shipping_state').length) {
                    selectedState = $('select#shipping_state option:selected').text();
                } else {
                    selectedState = $('input#shipping_state').val();
                }

            } else {

                // use billing variables
                firstName = $('input#billing_first_name').val();
                lastName = $('input#billing_last_name').val();
                email = $('input#billing_email').val();
                phone = $('input#billing_phone').val();
                selectedCountry = $('select#billing_country option:selected').text();
                streetAddress = $('input#billing_address_1').val();

                if ($('select#billing_city').length) {
                    city = $('select#billing_city option:selected').text();
                } else {
                    city = $('input#billing_city').val();
                }

                shippingStateRequired = $('label[for="shipping_state"]').find('abbr.required').length;

                if ($('select#billing_state').length) {
                    selectedState = $('select#billing_state option:selected').text();
                } else {
                    selectedState = $('input#billing_state').val();
                }
            }

            // check requirements are met
            if (
                (((!billingStateRequired || !shippingStateRequired) && selectedState.length >= 0)
                    || (billingStateRequired || shippingStateRequired) && selectedState.length > 0)
                &&
                firstName != '' && lastName != '' && email != '' && phone != '' && streetAddress != '' && city != '' && selectedCountry != '') {
                // hide notice
                $('#shipping-notice').remove();

                // Assemble payload
                let addressPayload = {
                    name: firstName + ' ' + lastName,
                    email,
                    phone,
                    address: streetAddress + ', ' + city + ', ' + selectedState + ', ' + selectedCountry,
                    comments: orderComments,
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
                let containerObject = { firstName, lastName, email, phone, streetAddress, city, selectedCountry }

                if ((billingStateRequired || shippingStateRequired)) {
                    containerObject['selectedState'] = '';
                }

                for (const key in containerObject) {
                    if (containerObject[key] == '') {
                        let kName = '';

                        if (key.includes('selectedState') && (billingStateRequired || shippingStateRequired)) {
                            kName = 'selected state or county';
                        } else {
                            kName = key.split(/(?=[A-Z])/).join(' ').toLowerCase();
                        }

                        errorBox.push(`${kName}`);
                    }
                }

                $('<div>', {
                    id: 'shipping-notice',
                    class: 'woocommerce-error',
                    style: 'font-size:16px',
                }).text(`Ensure that you have filled your ${errorBox.join(', ')}`).appendTo('#order_review_heading').show();
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

                        // dynamically add each courier
                        $('#sb-status-text').html('Select a delivery option');

                        // log time of data fetch
                        var json_fetch_date = new Date().toLocaleString();
                        $('input[name="shipbubble_rate_datetime"]').val(json_fetch_date);

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

                        courier_radio_btn.change(function () {
                            //first remove class from all
                            courier_radio_btn.parent().parent().removeClass('active');

                            if ($(this).is(':checked')) {
                                $(this).parent().parent().addClass('active')
                            }
                        });


                    } else {
                        let sbSlogan = $('.sb-slogan-container');
                        sbSlogan.hide();

                        list.empty();

                        let responseMessage = '';

                        if (response.hasOwnProperty('errors')) {
                            responseMessage = response['errors'][0];
                        } else if (response.hasOwnProperty('message')) {
                            responseMessage = response['message'];
                        } else if (response.hasOwnProperty('data')) {
                            responseMessage = response['data'];
                        } else {
                            responseMessage = 'unable to fetch rates, contact admin';
                        }

                        $('<div>', {
                            id: 'shipping-notice',
                            class: 'woocommerce-info',
                            style: 'font-size:16px',
                        }).text(`${responseMessage}`).appendTo('#order_review_heading').show();

                    }
                }

                // var requestRatesBtn = $('#request_courier_rates');
                // requestRatesBtn.prop('disabled', false);
                // requestRatesBtn.removeClass('load');

            }).fail(function () {
                let sbSlogan = $('.sb-slogan-container');
                sbSlogan.hide();

                list.empty();

                $('<div>', {
                    id: 'shipping-notice',
                    class: 'woocommerce-error',
                    style: 'font-size:16px',
                }).text(`unable to display couriers list, please try again later`).appendTo('#order_review_heading').show();

            });

            var requestRatesBtn = $('#request_courier_rates');
            requestRatesBtn.prop('disabled', false);

        }

    });

})(jQuery);
