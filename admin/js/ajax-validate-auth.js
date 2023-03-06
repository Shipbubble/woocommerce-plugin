// JavaScript for Admin Area

(function($) {
	
	$(document).ready(function() {

        var api_key_input = $('#shipbubble_options_form #shipbubble_options_shipbubble_api_key');
		var api_key_note = $('.form_note_shipbubble_api_key');
        var btn = $('form#shipbubble_options_form input#submit');

        btn.attr('disabled', true);

		// when user submits the form
		api_key_input.on( 'change', function(event) {
			
			// prevent form submission
			event.preventDefault();

            // define url
			var api_key = $(this).val();

            if (api_key.length > 10 && api_key.startsWith('sb_prod')) {
                api_key_note.text('Validating your API Key...');

                validate_shipbubble_api_key(api_key);
            } else {
                api_key_note.text('Please Provide your shipbubble production API Key');
                api_key_note.css('color', 'red');
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
    
                if (response.hasOwnProperty('response_code')) {
                    if (response['response_code'] == 200) {

                        btn.attr('disabled', false);

                        api_key_input.css('border', '2px solid green');
                        api_key_note.css('color', 'green');
                        api_key_note.text('Your API Key is valid');

                        
                    } else {
                        console.log('no');

                        btn.attr('disabled', true);

                        // alert('API Key is invalid, try again');
                        api_key_input.css('border', '1px solid red');
                        api_key_note.css('color', 'red');
                        api_key_note.text('API Key is invalid, try again');
                    }
                } else {
                    btn.attr('disabled', true);
                    
                    api_key_input.css('border', '1px solid red');
                    api_key_note.css('color', 'red');
                    api_key_note.text('API Key is invalid, try again');
                    // alert('API Key is invalid, try again');
                }
                
            });
        }
	});

})( jQuery );
