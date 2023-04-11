<?php

    // enqueue scripts
    function ajax_admin_enqueue_scripts( $hook ) 
    {
        // check if our page
        if ( 'toplevel_page_shipbubble' !== $hook ) return;
        
        // define script url
        $script_url = plugins_url( '/js/ajax-validate-auth.js', plugin_dir_path( __FILE__ ) );

        // var_dump($script_url);
        // die();

        // enqueue script
        wp_enqueue_script( 'ajax-admin', $script_url, array( 'jquery' ) );

        // create nonce
        $nonce = wp_create_nonce( 'ajax_admin' );

        // define script
        $script = array( 'nonce' => $nonce );

        // localize script
        wp_localize_script( 'ajax-admin', 'ajax_admin', $script );

    }

    add_action( 'admin_enqueue_scripts', 'ajax_admin_enqueue_scripts' );


    // process ajax request
    function shipbubble_validate_api_key() {

        // check nonce
        check_ajax_referer( 'ajax_admin', 'nonce' );

        // check user
        if ( ! current_user_can( 'manage_options' ) ) return;

        $apiKey = sanitize_text_field($_POST['data']['api_key']);

        echo json_encode(shipbubble_get_wallet_balance($apiKey)); 

        // end processing
        wp_die();

    }

    // ajax hook for logged-in users: wp_ajax_{action}
    add_action( 'wp_ajax_validate_api_key', 'shipbubble_validate_api_key' );
