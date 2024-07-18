<?php

    // enqueue scripts
    function ajax_enqueue_scripts_validate_address( $hook ) 
    {
        // check if our page
        if ( !(isset($_GET['page']) && $_GET['page'] == 'wc-settings' && isset($_GET['tab']) && $_GET['tab'] == 'shipping' && isset($_GET['section']) && $_GET['section'] == 'shipbubble_shipping_services') ) return;
        
        // define script url
        $script_url = plugins_url( '/js/ajax-validate-address.js', plugin_dir_path( __FILE__ ) );

        // enqueue script
        wp_enqueue_script( 'ajax-wc-admin', $script_url, array( 'jquery' ) );

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
            empty($data['address']) ) {

            $output = array('status' => 'failed', 'data' => 'some items are missing, please fill');

            // echo json_encode('near');
            echo json_encode($output);
        } else {

			$keys = shipbubble_get_keys();
            // validate address
            $response = shipbubble_validate_address(
                sanitize_text_field($data['name']), 
                sanitize_email($data['email']), 
                sanitize_text_field($data['phone']), 
                sanitize_text_field($data['address'])
            );

	        if ('200' == $response->response_code) {
		        $shipbubble_init = get_option(SHIPBUBBLE_INIT);
		        $shipbubble_init['address_validated'] = true;
		        $options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
		        $options["activate_shipbubble"] = $data['activate_shipbubble'];
				$options['sender_name'] = sanitize_text_field($data['name']);
		        $options['sender_email'] = sanitize_email($data['email']);
				$options['sender_phone'] =  sanitize_text_field($data['phone']);
		        $options['store_category'] = sanitize_text_field($data['store_category']);
				$options['address_code'] = $response->data->address_code;
				$options['disable_other_shipping_methods'] = sanitize_text_field($data['disable_other_shipping_methods']);
				$address = sanitize_text_field($data['address']);
				$address = explode(',', $address);
		        $options['pickup_address'] = isset($address[0]) ? trim($address[0]) : '';
		        $options['pickup_state'] = isset($address[1]) ? trim($address[1]) : '';
		        $options['pickup_country'] = sanitize_text_field($data['pickup_country']);


		        update_option( SHIPBUBBLE_INIT, $shipbubble_init);
		        update_option( WC_SHIPBUBBLE_ID, $options);
	        }

            // echo json_encode('heere');
            echo json_encode($response);
        }
        
        // end processing
        wp_die();

    }

    // ajax hook for logged-in users: wp_ajax_{action}
    add_action( 'wp_ajax_initiate_validate_sender_address', 'shipbubble_initiate_validate_sender_address' );