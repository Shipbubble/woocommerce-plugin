// JavaScript for Admin Area

(function($) {
	
	$(document).ready(function() {

        // var btn = $('#create-shipment');
		// var details = $('input#shipment_details');
		// var wc_order_id = $('input#wc_order_id');    

		var mainform = $('form#mainform'); 
        var formBtn = mainform.find('button[type="submit"]');
        var senderName = mainform.find('#woocommerce_shipbubble_shipping_services_sender_name');
        var senderPhone = mainform.find('#woocommerce_shipbubble_shipping_services_sender_phone');
        var senderEmail = mainform.find('#woocommerce_shipbubble_shipping_services_sender_email');
        var senderAddress = mainform.find('#woocommerce_shipbubble_shipping_services_pickup_address');
        var senderState = mainform.find('#woocommerce_shipbubble_shipping_services_pickup_state');
        var senderCountry = mainform.find('#woocommerce_shipbubble_shipping_services_pickup_country');
        
        // console.log(senderCountry.find('option:selected').text());
        
        $(senderName, senderPhone, senderEmail, senderAddress, senderState, senderCountry).change(function(e) {
            console.log('mainform');
            if (senderName.val() != '' && senderPhone.val() != '' && senderEmail.val() != '' && 
            senderAddress.val() != '' && senderState.val() != '' && senderCountry.find('option:selected').val() != ''
            ) {
                console.log('done');
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
                nonce:  ajax_wc_admin.nonce,
                action: 'initiate_validate_sender_address',
                // url:    url
                data: { payload },
                dataType: 'json'
            }, function(data) {
    
                let response = JSON.parse(data);

                console.log(response);
                
                if (response.hasOwnProperty('status')) {
                    if (response['status'] == 'success') {

                        formBtn.attr('disabled', false);

                    } else {
                        $(`<div id="message" class="notice notice-warning is-dismissible">
                            <p>${response['message']}.</p>
                            
                            <button type="button" class="notice-dismiss">
                                <span class="screen-reader-text">Dismiss this notice.</span>
                            </button>
                        </div>`).insertAfter($('ul.subsubsub'));

                        formBtn.attr('disabled', true);

                        console.log('no');
                    }
                } else {
                    $(`<div id="message" class="notice notice-error is-dismissible">
                        <p>${response['message']}.</p>
                        
                        <button type="button" class="notice-dismiss">
                            <span class="screen-reader-text">Dismiss this notice.</span>
                        </button>
                    </div>`).insertAfter($('ul.subsubsub'));

                    formBtn.attr('disabled', true);
                    console.log('no');
                }
                
            });
        }
	});

})( jQuery );
