// JavaScript for Public Checkout Area

(function($) {
	
	$(document).ready(function() {

        let firstName = $('input#billing_first_name').val();
        let lastName = $('input#billing_last_name').val();
        let email = $('input#billing_email').val();
        let phone = $('input#billing_phone').val();
        let streetAddress = $('input#shipping_address_1').val();
        let city = $('input#shipping_city').val();
        let selectedState = $('select#shipping_state').val();
        let selectedCountry = $('select#shipping_country').val();

        let section = $('#courier-section');
        let list = $('#courier-list');

        // When page loads for the first time && requirements are met
        if(firstName != '' && lastName != '' && email != '' && phone != '' && streetAddress != '' && city != '' && selectedState != '' && selectedCountry != '') {
            // get shipping methods
            $('.shipping-notice').hide();
            let addressPayload = {
                name: firstName + ' ' + lastName,
                email,
                phone,
                address: streetAddress + ', ' + city + ', ' + selectedState + ', ' + selectedCountry,
            }
            fetch_shipping_rates(addressPayload);
        } else {
            $('.shipping-notice').show();
        }

        // When user is trying to meet requirements
        $('input#billing_first_name, input#billing_last_name, input#billing_email, input#billing_phone, input#billing_address_1, input#billing_city, select#billing_state, select#billing_country')
        .change(function() {
            // clear the body of section
            // remove dynamically set items
            section.find('input[type="text"]').remove();
            list.empty();

            console.log("object");
            let firstName = $('input#billing_first_name').val();
            let lastName = $('input#billing_last_name').val();
            let email = $('input#billing_email').val();
            let phone = $('input#billing_phone').val();
            let streetAddress = $('input#billing_address_1').val();
            let city = $('input#billing_city').val();
            let selectedState = $('select#billing_state').val();
            let selectedCountry = $('select#billing_country').val();

            if(firstName != '' && lastName != '' && email != '' && phone != '' && streetAddress != '' && city != '' && selectedState != '' && selectedCountry != '') {
                // get shipping methods
                $('.shipping-notice').hide();
                let addressPayload = {
                    name: firstName + ' ' + lastName,
                    email,
                    phone,
                    address: streetAddress + ', ' + city + ', ' + selectedState + ', ' + selectedCountry,
                }

                fetch_shipping_rates(addressPayload);
            } else {
                $('.shipping-notice').show();
            }
        });
		
        function fetch_shipping_rates(payload) {
            // submit the data
            let ajaxUrl = ajax_public.ajaxurl;

            let data = {
                nonce:     ajax_public.nonce,
				action:    'request_shipping_rates',
                data: payload
            };

            let section = $('#courier-section');
            let list = $('#courier-list');

            // add loading message
			list.html('Loading...');

			$.post(
                ajaxUrl,
                data,
            ).done(function(data) {
                console.log("final data below: >>>");
                console.log(data);
                
                let response = JSON.parse(data);
                list.empty();
                
                if (response.hasOwnProperty('status')) {
                    if (response['status'] == 'success') {
                        let output = response['data'];
                        // display data
                        console.log('token ==> ', output.request_token);
                        // var section = $("#courier-section");

                        
                        // dynamically add each courier
                        list.append("<h3>Delivery Options <sup>*</sup></h3>");
                        $.each(output.couriers, function(i, value){
                            // set total charge
                            
                            let total = parseFloat(value.total) + parseFloat(output.extra_charges);

                            list.append(`<p><input type="radio" name="delivery_option" data-request_token="${output.request_token}" data-courier_name="${value.courier_name}" data-cost="${total}" data-service_code="${value.service_code}" data-courier_id="${value.courier_id}" required /> ${value.currency}${total} ${value.courier_name} </p>`);
                        });

                        // dynamically add hidden form fields
                        // section.append(`<input type="hidden" id="shipbubble_selected_courier" name="shipbubble_selected_courier" value="">`);
                        // section.append(`<input type="hidden" id="shipbubble_cost" name="shipbubble_cost" value="">`);
                        // section.append(`<input type="hidden" id="shipbubble_service_code" name="shipbubble_service_code" value="">`);
                        // section.append(`<input type="hidden" id="shipbubble_courier_id" name="shipbubble_courier_id" value="">`);
                    } else {
                        console.log(response['data']);

                        list.empty();

                        $('.shipping-notice').removeAttr('class').attr('class', 'shipping-notice woocommerce-info').text(response['message']).show();
                    }
                }
                
            }).fail(function () { 
                console.log("failed");
                
                $('.shipping-notice').removeAttr('class').attr('class', 'shipping-notice woocommerce-info').text('Unable to display Couriers List, Please Try again later').show();
            });
        }

	});
	
})( jQuery );
