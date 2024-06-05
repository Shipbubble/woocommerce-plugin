<?php
    // process ajax request
    function shipbubble_validate_api_key() {

        // check nonce
	    check_ajax_referer( 'ajax_wc_admin', 'nonce' );

        // check user
        if ( ! current_user_can( 'manage_options' ) ) return;

        $apiKey = sanitize_text_field($_POST['data']['api_key']);
		$live_mode = sanitize_text_field($_POST['data']['live_mode']);

		$result = shipbubble_get_wallet_balance($apiKey);

		if ('200' == $result->response_code) {
			$shipbubble_init = get_option(SHIPBUBBLE_INIT);
			$shipbubble_init['account_status'] = true;

			$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
			$options['api_key'] = $apiKey;
			$options['live_mode'] = $live_mode;

			update_option(WC_SHIPBUBBLE_ID, $options);
			update_option( SHIPBUBBLE_INIT, $shipbubble_init);
		}

        echo json_encode($result);

        // end processing
        wp_die();

    }

    // ajax hook for logged-in users: wp_ajax_{action}
    add_action( 'wp_ajax_validate_api_key', 'shipbubble_validate_api_key' );
