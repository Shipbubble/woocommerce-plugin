<?php // Shipbubble Endpoints

    // disable direct file access
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    function shipbubble_get_wallet_balance(string $apiKey = '') 
    {

        $url = SHIPBUBBLE_BASE_URL . '/wallet/balance';

        $url = esc_url_raw( $url );

        $token = '';

        if (isset($apiKey)) {
            $token = $apiKey;
        } elseif (strlen(shipbubble_get_token()) > 0 ) {
            // get API key from options
            $token = shipbubble_get_token();
        }

        $body = shipbubble_base_response();
        
        if (strlen($token) > 0) {
            $args = array( 
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                ),
            );
        
            $response = wp_safe_remote_get( $url, $args );
        
            // response data
            $body = wp_remote_retrieve_body( $response );
        }
        
        // output data
        return json_decode($body);
    }
