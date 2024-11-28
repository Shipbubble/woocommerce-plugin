<?php

    // enqueue scripts
    function ajax_enqueue_scripts_validate_address( $hook ) 
    {
        // check if our page
        if ( !(isset($_GET['page']) && $_GET['page'] == 'wc-settings' && isset($_GET['tab']) && $_GET['tab'] == 'shipping' && isset($_GET['section']) && $_GET['section'] == 'shipbubble_shipping_services') ) return;
        
        // define script url
        $script_url = plugins_url( '/js/ajax-validate-address.js', plugin_dir_path( __FILE__ ) );

	    // Generate a random version number
	    $version = rand(1000, 9999); // or use another method to generate a version string

	    // Enqueue script with random version
	    wp_enqueue_script( 'ajax-wc-admin', $script_url, array( 'jquery' ), $version );

        // create nonce
        $nonce = wp_create_nonce( 'ajax_wc_admin' );

        // define script
        $script = array( 'nonce' => $nonce, 'logo' => SHIPBUBBLE_LOGO_URL );

        // localize script
        wp_localize_script( 'ajax-wc-admin', 'ajax_wc_admin', $script );

    }
    

    add_action( 'admin_enqueue_scripts', 'ajax_enqueue_scripts_validate_address' );


    // process ajax request
    function shipbubble_initiate_validate_sender_address() {

        // check nonce
        check_ajax_referer( 'ajax_wc_admin', 'nonce' );

        // check user
        if ( ! current_user_can( 'manage_options' ) ) return;

        $payload = array_map( 'sanitize_text_field', $_POST['data']['payload'] );

        $data = $payload;

        // Any of the WordPress data sanitization functions can be used here

        if ( empty($data) || empty($data['name']) || empty($data['email']) || empty($data['phone']) || 
            empty($data['full_address']) ) {

            $output = array('status' => 'failed', 'data' => 'some items are missing, please fill');

            // echo json_encode('near');
            echo json_encode($output);
        } else {

			$keys = shipbubble_get_keys();
            // validate address
            $live_key_response = shipbubble_validate_address(
                sanitize_text_field($data['name']), 
                sanitize_email($data['email']), 
                sanitize_text_field($data['phone']), 
                sanitize_text_field($data['full_address']),
	            $keys['live_api_key']
            );
	        $shipbubble_init = get_option(SHIPBUBBLE_INIT);
	        $options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

	        if ('200' == $live_key_response->response_code) {
		        $shipbubble_init[SHIPBUBBLE_ADDRESS_VALIDATED] = true;
		        $options["activate_shipbubble"] = $data['activate_shipbubble'];
				$options['sender_name'] = sanitize_text_field($data['name']);
		        $options['sender_email'] = sanitize_email($data['email']);
				$options['sender_phone'] =  sanitize_text_field($data['phone']);
		        $options['store_category'] = sanitize_text_field($data['store_category']);
				$options['address_code'] = $live_key_response->data->address_code;
				$options['disable_other_shipping_methods'] = sanitize_text_field($data['disable_other_shipping_methods']);
				$address = sanitize_text_field($data['address']);
				$state = sanitize_text_field($data['state']);
		        $options['pickup_address'] = $address;
		        $options['pickup_state'] = $state;
		        $options['pickup_country'] = sanitize_text_field($data['pickup_country']);
		        $options['local_pickup_text'] = sanitize_text_field($data['local_pickup_text']);

		        update_option( SHIPBUBBLE_INIT, $shipbubble_init);
		        update_option( WC_SHIPBUBBLE_ID, $options);
	        }

			if (!empty($keys['sandbox_api_key'])) {
				$sandbox_key_response = shipbubble_validate_address(
					sanitize_text_field($data['name']),
					sanitize_email($data['email']),
					sanitize_text_field($data['phone']),
					sanitize_text_field($data['full_address']),
					$keys['sandbox_api_key']
				);

				if ('200' == $sandbox_key_response->response_code) {
					$shipbubble_init[SHIPBUBBLE_SANDBOX_ADDRESS_VALIDATED] = true;
					$options['sandbox_address_code'] = $sandbox_key_response->data->address_code;
					update_option( SHIPBUBBLE_INIT, $shipbubble_init);
					update_option( WC_SHIPBUBBLE_ID, $options);
				}

				if (!shipbubble_is_live_mode()) {
					echo json_encode($sandbox_key_response);
					wp_die();
				}
			}


            echo json_encode($live_key_response);
        }
        
        // end processing
        wp_die();

    }

    // ajax hook for logged-in users: wp_ajax_{action}
    add_action( 'wp_ajax_initiate_validate_sender_address', 'shipbubble_initiate_validate_sender_address' );

	function shipbubble_switch_mode_ajax() {
		// check nonce
		check_ajax_referer( 'ajax_wc_admin', 'nonce' );

		// check user
		if ( ! current_user_can( 'manage_options' ) ) return;

		$live_mode = $_POST['data']['live_mode'] ?? 0;
		$storedKeys = shipbubble_get_keys();
		$live_mode = '0' != $live_mode;
		$mode = $live_mode ? 'Live' : 'Sandbox';

		if ($live_mode) {
			if (shipbubble_is_live_mode()) return;
			$key = $storedKeys['live_api_key'];
		} else {
			$key = $storedKeys['sandbox_api_key'];
		}

		if (empty($key)) return;

		$response = shipbubble_get_wallet_balance($key);

		if (isset($response->response_code) && $response->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
			shipbubble_switch_mode($live_mode ? 'yes' : 'no');
			$response->message = 'You have successfully switched to ' . $mode . ' mode';
			$response->notice = generate_shipbubble_notice();
		}

		echo json_encode($response);
		wp_die();
	}
	add_action( 'wp_ajax_shipbubble_switch_mode', 'shipbubble_switch_mode_ajax' );

	function shipbubble_toggle_local_pickup_ajax() {
	// check nonce
	check_ajax_referer( 'ajax_wc_admin', 'nonce' );

	// check user
	if ( ! current_user_can( 'manage_options' ) ) return;

	$local_pickup = $_POST['data']['local_pickup_enabled'] ?? 0;

	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
	$options['local_pickup'] = 1 == $local_pickup ? 'yes' : 'no';

	update_option(WC_SHIPBUBBLE_ID, $options);

	$response = array(
		'message' => 'Success',
		'response_code' => 200
	);

	echo json_encode($response);
	wp_die();
}
	add_action( 'wp_ajax_shipbubble_toggle_local_pickup', 'shipbubble_toggle_local_pickup_ajax' );
