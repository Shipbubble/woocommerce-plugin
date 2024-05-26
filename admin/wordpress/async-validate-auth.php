<?php
    // process ajax request
    function shipbubble_validate_api_key() {

        // check nonce
	    check_ajax_referer( 'ajax_wc_admin', 'nonce' );

        // check user
        if ( ! current_user_can( 'manage_options' ) ) return;

        $apiKey = sanitize_text_field($_POST['data']['api_key']);

        echo json_encode(shipbubble_get_wallet_balance($apiKey)); 

        // end processing
        wp_die();

    }

    // ajax hook for logged-in users: wp_ajax_{action}
    add_action( 'wp_ajax_validate_api_key', 'shipbubble_validate_api_key' );
