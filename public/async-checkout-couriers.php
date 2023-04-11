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

        $data = isset( $_POST['data'] ) ? (array) $_POST['data'] : array();

        // Any of the WordPress data sanitization functions can be used here
        $data = array_map( 'esc_attr', $data );

        if ( empty($_POST['data']) || empty($data['name'])  || empty($data['email']) || empty($data['phone']) || empty($data['address']) ) {

            $output = array('status' => 'failed', 'data' => 'some items are missing, please fill');

            echo json_encode($output);
        } else {
            // validate address
            $addressResponse = shipbubble_validate_address(
                sanitize_text_field($data['name']), 
                sanitize_email($data['email']), 
                sanitize_text_field($data['phone']), 
                sanitize_text_field($data['address'])
            );

            // successful
            if (isset($addressResponse->response_code) && $addressResponse->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
                $products = shipbubble_get_checkout_orders();
                $addressCode = $addressResponse->data->address_code;
                $output = array();

                $rates = shipbubble_process_shipping_rates($addressCode, $products);

                if (count($rates)) {
                    $output = array('status' => 'success', 'data' => $rates);
                } else {
                    $output = array('status' => 'failed', 'message' => 'Unable to fetch rates, try again later');
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