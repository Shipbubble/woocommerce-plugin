// JavaScript for Public Checkout Area
(function($) {
	
	$(document).ready(function() {

        var requestRatesBtn = $('#request_courier_rates');
        
        requestRatesBtn.click(function (e) { 
            
            e.preventDefault();
            $('#shipping-notice').remove();
            
            // initialize variables
            let firstName = $('input#billing_first_name').val();
            let lastName = $('input#billing_last_name').val();
            let email = $('input#billing_email').val();
            let phone = $('input#billing_phone').val();

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
            if(firstName != '' && lastName != '' && email != '' && phone != '' && streetAddress != '' && city != '' && selectedState != '' && selectedCountry != '') {
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
                $(this).attr({
                    class: 'loading',
                    disabled: true
                });

                // Request shipping rates
                fetch_shipping_rates(addressPayload);
            } else {
                // Display notice
                let errorBox = [];
                let containerObject = {firstName, lastName, email, phone, streetAddress, city, selectedState, selectedCountry}

                for (const key in containerObject) {
                    if (containerObject[key] == '') {
                        errorBox.push(`${key}`);
                    }
                }

                $('<div>', {
                    id: 'shipping-notice',
                    class: 'woocommerce-error',
                }).text(`Ensure that you have filled your ${errorBox.join(', ')}`).prependTo('#courier-section').show();

                // Swal.fire({
                //     position: 'top-end',
                //     icon: 'error',
                //     title: ``,
                //     showConfirmButton: false,
                //     timer: 5000,
                // })

                // $('.shipping-notice').show();
            }
            
        });

		
        function fetch_shipping_rates(payload) {
            // submit the data
            let ajaxUrl = ajax_public.ajaxurl;

            // set ajax payload
            let data = {
                nonce:     ajax_public.nonce,
				action:    'request_shipping_rates',
                data: payload
            };

            // initialize courier listing html container
            let list = $('#courier-list');

            // add loading message
			// list.html('Loading...').insertAfter('#request_courier_rates');

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

                    } else {
                        console.log(response['data']);

                        list.empty();

                        $('<div>', {
                            id: 'shipping-notice',
                            class: 'woocommerce-info',
                        }).text(`${response['message']}`).prependTo('#courier-section').show();

                    }
                }

                requestRatesBtn.attr({
                    class: '',
                    disabled: false
                }).text('Request Courier Rates');
                
            }).fail(function () { 
                console.log("failed");

                $('<div>', {
                    id: 'shipping-notice',
                    class: 'woocommerce-error',
                }).text(`Unable to display Couriers List, Please Try again later`).prependTo('#courier-section').show();

            });
        }

	});
	
})( jQuery );
