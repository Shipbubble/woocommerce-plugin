<?php

    // enqueue scripts
    function ajax_enqueue_scripts_create_shipment( $hook ) 
    {
        // check if our page
        if ( 'post.php' !== $hook ) return;
        
        // define script url
        $script_url = plugins_url( '/js/ajax-create-shipment.js', plugin_dir_path( __FILE__ ) );

        // enqueue script
        wp_enqueue_script( 'ajax-wc-admin', $script_url, array( 'jquery' ) );

        // create nonce
        $nonce = wp_create_nonce( 'ajax_wc_admin' );

        // define script
        $script = array( 'nonce' => $nonce );

        // localize script
        wp_localize_script( 'ajax-wc-admin', 'ajax_wc_admin', $script );

    }
    

    add_action( 'admin_enqueue_scripts', 'ajax_enqueue_scripts_create_shipment' );


    // process ajax request
    function shipbubble_initiate_order_shipment() {

        // check nonce
        check_ajax_referer( 'ajax_wc_admin', 'nonce' );

        // check user
        if ( ! current_user_can( 'manage_options' ) ) return;

        $orderId = sanitize_text_field( $_POST['data']['order_id'] );

        // get shipment details
        $shipmentPayload = unserialize(shipbubble_get_order_meta($orderId, 'shipbubble_shipment_details'));

        // set time meta to initiate the request
        $shipmentPayload['admin_initiate_shipment_time'] = date('Y-m-d H:i:s');
        shipbubble_update_order_meta($orderId, 'shipbubble_shipment_details', serialize($shipmentPayload));

        if (!isset($shipmentPayload) || empty($shipmentPayload['request_token']) || empty($shipmentPayload['courier_id']) || empty($shipmentPayload['service_code'])) {
	        $checkoutPayload = unserialize(shipbubble_get_order_meta($orderId, 'sb_shipment_meta'));

	        // TODO: check token expiry
	        if (empty($shipmentPayload['request_token'])) {
		        $shipmentPayload['request_token'] = $checkoutPayload['shipment_payload']['request_token'] ?? '';
	        }

	        if (empty($shipmentPayload['courier_id'])) {
		        $shipmentPayload['courier_id'] = $checkoutPayload['shipment_payload']['courier_id'] ?? '';
	        }
	        if (empty($shipmentPayload['service_code'])) {
		        $shipmentPayload['service_code'] = $checkoutPayload['shipment_payload']['service_code'] ?? '';
	        }

	        $shipmentPayload['swap_occurred'] = true;

	        // initiate request
        }
	    $response = shipbubble_create_shipment($shipmentPayload);


        if (isset($response->response_code) && $response->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
            // set shipbubble order id
            shipbubble_update_order_meta( $orderId, 'shipbubble_order_id', $response->data->order_id );

            // set time meta
            $shipmentPayload['admin_create_shipment_time'] = date('Y-m-d H:i:s');
            shipbubble_update_order_meta($orderId, 'shipbubble_shipment_details', serialize($shipmentPayload));

            // set shipping status
            shipbubble_update_order_meta( $orderId, 'shipbubble_tracking_status', 'pending' );
        }

        echo json_encode($response); 
        
        // end processing
        wp_die();

    }

    // ajax hook for logged-in users: wp_ajax_{action}
    add_action( 'wp_ajax_initiate_order_shipment', 'shipbubble_initiate_order_shipment' );
