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
		$storedKeys = shipbubble_get_keys();

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
			$result = array(
				'response_code' => 200,
				'status' => 'success',
				'message' => 'API Key validation was successful',
			);
			$result = json_encode($result);
			if ($storedKeys['live_api_key'] != $liveKey) {
				$shipbubble_init[SHIPBUBBLE_ADDRESS_VALIDATED] = false;
			}
			if ($storedKeys['sandbox_api_key'] != $sandboxKey) {
				$shipbubble_init[SHIPBUBBLE_SANDBOX_ADDRESS_VALIDATED] = false;
			}

			update_option( SHIPBUBBLE_INIT, $shipbubble_init);
		} else {
			$error_message = implode("\n", $errors);

			$result = shipbubble_base_response('failed', $error_message);
		}

	    echo $result;

        // end processing
        wp_die();

    }

    // ajax hook for logged-in users: wp_ajax_{action}
    add_action( 'wp_ajax_validate_api_keys', 'shipbubble_validate_api_key' );
