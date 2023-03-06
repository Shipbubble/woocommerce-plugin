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

        // $shipmentPayload = $_POST['data'];
        $shipmentPayload = $_POST['data']['shipment'];
        $orderId = $shipmentPayload['order_id'];

        // var_dump($shipmentPayload);
        // die;

        $response = shipbubble_create_shipment($shipmentPayload); 
        
        if (isset($response->response_code) && $response->response_code == HTTP_RESPONSE_OK) {
            // set shipbubble order id
            update_post_meta( $orderId, 'shipbubble_order_id', $response->data->order_id );

            // set shipping status
            update_post_meta( $orderId, 'shipbubble_tracking_status', 'pending' );
        }

        echo json_encode($response); 
        
        // end processing
        wp_die();

    }

    // ajax hook for logged-in users: wp_ajax_{action}
    add_action( 'wp_ajax_initiate_order_shipment', 'shipbubble_initiate_order_shipment' );
