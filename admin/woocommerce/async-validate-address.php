<?php

    // enqueue scripts
    function ajax_enqueue_scripts_validate_address( $hook ) 
    {
        // check if our page
        // if ( 'post.php' !== $hook ) return;
        
        // define script url
        $script_url = plugins_url( '/js/ajax-validate-address.js', plugin_dir_path( __FILE__ ) );

        // enqueue script
        wp_enqueue_script( 'ajax-wc-admin', $script_url, array( 'jquery' ) );

        // create nonce
        $nonce = wp_create_nonce( 'ajax_wc_admin' );

        // define script
        $script = array( 'nonce' => $nonce );

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

        $data = $_POST['data']['payload'];

        if ( empty($data) || empty($data['name']) || empty($data['email']) || empty($data['phone']) || 
            empty($data['address']) ) {

            $output = array('status' => 'failed', 'data' => 'some items are missing, please fill');

            echo json_encode('near');
            // echo json_encode($output);
        } else {
            // validate address
            $response = shipbubble_validate_address(
                $data['name'], 
                $data['email'], 
                $data['phone'], 
                $data['address']
            );

            // echo json_encode('heere');
            echo json_encode($response);
        }
        
        // end processing
        wp_die();

    }

    // ajax hook for logged-in users: wp_ajax_{action}
    add_action( 'wp_ajax_initiate_validate_sender_address', 'shipbubble_initiate_validate_sender_address' );