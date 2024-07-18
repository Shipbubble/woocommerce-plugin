<?php
    // process ajax request
    function shipbubble_validate_api_key() {

        // check nonce
	    check_ajax_referer( 'ajax_wc_admin', 'nonce' );

        // check user
        if ( ! current_user_can( 'manage_options' ) ) return;


        $liveKey = sanitize_text_field($_POST['data']['live_api_key']);
		$sandboxKey = sanitize_text_field($_POST['data']['sandbox_api_key']);

		$keys = array( 'live' => $liveKey, 'sandbox' => $sandboxKey );

		$errors = array();

	    $options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
	    $shipbubble_init = get_option(SHIPBUBBLE_INIT);

		foreach ($keys as $index => $key) {
			$result = shipbubble_get_wallet_balance($key);

			if ('200' == $result->response_code) {
				$index = $index . '_api_key';

				$options[$index] = $key;

				update_option(WC_SHIPBUBBLE_ID, $options);
			} else {
				$errors[] = $index . ' key error: ' . $result->message;
			}

		}

		if (empty($errors)) {
			$shipbubble_init['account_status'] = true;
			update_option( SHIPBUBBLE_INIT, $shipbubble_init);
			$result = shipbubble_base_response('success', 'API Key validation was successful');
		} else {
			// Concatenate the errors into a single string with a separator (break or newline)
//			$error_message = implode('<br>', $errors); // Using HTML line break as the separator

			// Alternatively, you can use newline
			$error_message = implode("\n", $errors);

			$result = shipbubble_base_response('failed', $error_message);
		}

	    echo $result;

        // end processing
        wp_die();

    }

    // ajax hook for logged-in users: wp_ajax_{action}
    add_action( 'wp_ajax_validate_api_keys', 'shipbubble_validate_api_key' );
