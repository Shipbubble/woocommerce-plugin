// JavaScript for Admin Area

(function($) {
	
	$(document).ready(function() {

        var api_key_input = $('#shipbubble_options_form #shipbubble_options_shipbubble_api_key');
		var api_key_note = $('.form_note_shipbubble_api_key');

		// when user submits the form
		api_key_input.on( 'change', function(event) {
			
			// prevent form submission
			event.preventDefault();

            // define url
			var api_key = $(this).val();

            if (api_key.length > 10) {
                validate_shipbubble_api_key(api_key);
            }
			
		});
		
        function validate_shipbubble_api_key(api_key) {
            $.post(ajaxurl, {
                nonce:  ajax_admin.nonce,
                action: 'validate_api_key',
                // url:    url
                data: { api_key },
                dataType: 'json'
            }, function(data) {
    
                let response = JSON.parse(data);
    
                if (response.hasOwnProperty('status')) {
                    if (response['status'] == 'success') {
                        api_key_input.css('border', '2px solid green');
                        api_key_note.css('color', 'green');
                        api_key_note.text('Your API Key is valid');
                    } else {
                        console.log('no');
                        alert('API Key is invalid, try again');
                        api_key_input.css('border', '1px solid red');
                        api_key_note.css('color', 'red');
                        api_key_note.text('API Key is invalid, try again');
                    }
                } else {
                    api_key_input.css('border', '1px solid red');
                    api_key_note.css('color', 'red');
                    api_key_note.text('API Key is invalid, try again');
                    alert('API Key is invalid, try again');
                }
                
            });
        }
	});

})( jQuery );
