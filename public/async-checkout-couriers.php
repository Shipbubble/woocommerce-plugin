<?php // Silence is Golden

    // enqueue scripts
    function ajax_public_enqueue_scripts( $hook ) {

        // check if our page

        if ( '' !== $hook ) return;

        // define script url
        $script_url = plugins_url( '/js/couriers-on-checkout.js', __FILE__ );

        // enqueue script
        wp_enqueue_script( 'ajax-public', $script_url, array( 'jquery' ) );

        // create nonce
        $nonce = wp_create_nonce( 'ajax_public' );

        // define ajax url
        $ajax_url = admin_url( 'admin-ajax.php' );

        // define script
        $script = array( 'nonce' => $nonce, 'ajaxurl' => $ajax_url );

        // localize script
        wp_localize_script( 'ajax-public', 'ajax_public', $script );
    }
    add_action( 'wp_enqueue_scripts', 'ajax_public_enqueue_scripts' );


    // process ajax request
    function shipbubble_request_shipping_rates() {

        // check nonce
        check_ajax_referer( 'ajax_public', 'nonce' );

        $output = array();

        // Any of the WordPress data sanitization functions can be used here
        $postData = array_map( 'sanitize_text_field', $_POST['data'] );

        $data = isset( $postData ) ? (array) $postData : array();

        // error_log(print_r($data, true));

        // Any of the WordPress data sanitization functions can be used here

        if ( empty($_POST['data']) || empty($data['name'])  || empty($data['email']) || empty($data['phone']) || empty($data['address']) ) {

            $output = array('status' => 'failed', 'message' => 'Please check your billing / shipping information for all required details');

            echo json_encode($output);
        } else {
            // validate address
            $addressResponse = shipbubble_validate_address(
                sanitize_text_field($data['name']), 
                sanitize_email($data['email']), 
                sanitize_text_field($data['phone']), 
                sanitize_text_field($data['address']),
	            isset($data['postcode']) ? sanitize_text_field($data['postcode']) : ''
            );

            // successful
            if (isset($addressResponse->response_code) && $addressResponse->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
                $products = shipbubble_get_checkout_orders();
                $products['comments'] = !empty($data['comments']) ? $data['comments'] : 'please handle carefully';
                $addressCode = $addressResponse->data->address_code;
                $output = array();

                $rates = shipbubble_process_shipping_rates($addressCode, $products);

                if (!isset($rates['error'])) {
                    $output = array('status' => 'success', 'data' => $rates);
                } else {
                    $output = array('status' => 'failed', 'data' => $rates['error']);
                }
                
                echo json_encode($output);
                wp_die(); 
            } else {
                echo json_encode($addressResponse); 
            }
        }

        // end processing
        wp_die();
    }

    // ajax hook for logged-in users: wp_ajax_{action}
    add_action( 'wp_ajax_request_shipping_rates', 'shipbubble_request_shipping_rates' );
    add_action( 'wp_ajax_nopriv_request_shipping_rates', 'shipbubble_request_shipping_rates' );


	/**
	 * Request local pickup address
	 *
	 * @return void
	 */
	function shipbubble_request_pickup_address() {

		// check nonce
		check_ajax_referer( 'ajax_public', 'nonce' );

		$data = isset($_POST['data']) ? array_map('sanitize_text_field', $_POST['data']) : array();

		if (empty($data) || !isset($data['address']) || !isset($data['city']) || !isset($data['state']) || !isset($data['country'])) {
			echo json_encode(array('status' => 'failed', 'message' => 'Please provide all required details for the local pickup address'));
			wp_die();
		}

		$address = $data['address'] . ', ' . $data['city'] . ', ' . $data['state'] . ', ' . $data['country'];

		// get local pickup address
		$address = shipbubble_get_local_pickup_address($address);

		if (is_array($address)) {
			echo json_encode($address);
			wp_die();
		}

		echo json_encode(array('status' => 'success', 'data' => array('address' => $address)));

		wp_die();
	}
	add_action('wp_ajax_shipbubble_request_pickup_address', 'shipbubble_request_pickup_address');
	add_action('wp_ajax_nopriv_shipbubble_request_pickup_address', 'shipbubble_request_pickup_address');