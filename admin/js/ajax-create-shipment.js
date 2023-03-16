// JavaScript for Admin Area

(function($) {
	
	$(document).ready(function() {

        var btn = $('#create-shipment');
		var details = $('input#shipment_details');
		var wc_order_id = $('input#wc_order_id');  

		var walletBalance = parseFloat($('input#shipbubble_wallet_balance').val());
		var shippingCost = parseFloat($('input#shipbubble_shipping_cost').val());
        
		// when user submits the form
		btn.on( 'click', function(event) {
            // prevent form submission
            event.preventDefault();
            
            if ( $.isNumeric(walletBalance) && $.isNumeric(shippingCost) && (shippingCost > walletBalance)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Oops...',
                    text: 'Wallet Balance is insufficient, fund your account!',
                    // showConfirmButton: false,
                })
            } else {
                btn.attr('disabled', 'true').html('loading...');

                if (details.val() != '') {
                    console.log(JSON.parse(details.val()).request_token)
                    // let payload = JSON.parse(form.find('#shipment_details'));
    
                    const payload = JSON.parse(details.val());
                    payload['order_id'] = wc_order_id.val();
                    
                    // initiate shipment
                    initiate_shipment(payload);
                }
            }

		});
		
        function initiate_shipment(shipment) {
            $.post(ajaxurl, {
                nonce:  ajax_wc_admin.nonce,
                action: 'initiate_order_shipment',
                // url:    url
                data: { shipment },
                dataType: 'json'
            }, function(data) {
    
                let response = JSON.parse(data);

                console.log(response);
                
                if (response.hasOwnProperty('response_code')) {
                    if (response['response_code'] == 200) {
                        $(`<div id="message" class="notice notice-success is-dismissible">
                            <p>${response['message']}.</p>
                            
                            <button type="button" class="notice-dismiss">
                                <span class="screen-reader-text">Dismiss this notice.</span>
                            </button>
                        </div>`).insertAfter($('.wp-header-end'));

                        btn.html('Completed!!!');

                        // reload page after 5 secs
                        setTimeout(() => location.reload(), 5000);
                    } else {
                        $(`<div id="message" class="notice notice-warning is-dismissible">
                            <p>${response['errors'][0]}.</p>
                            
                            <button type="button" class="notice-dismiss">
                                <span class="screen-reader-text">Dismiss this notice.</span>
                            </button>
                        </div>`).insertAfter($('.wp-header-end'));

                        console.log('no');
                    }
                } else {
                    $(`<div id="message" class="notice notice-error is-dismissible">
                        <p>${response['message']}.</p>
                        
                        <button type="button" class="notice-dismiss">
                            <span class="screen-reader-text">Dismiss this notice.</span>
                        </button>
                    </div>`).insertAfter($('.wp-header-end'));
                    console.log('no');
                }
                
            });
        }
	});

})( jQuery );
