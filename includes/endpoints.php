<?php // Shipbubble Endpoints

    // disable direct file access
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    /**
     * Authenticate & Fetch User Wallet Balance
     *
     * @param string $apiKey
     * @return void
     */
    function shipbubble_get_wallet_balance(string $apiKey = '') 
    {

        $url = SHIPBUBBLE_BASE_URL . '/wallet/balance';

        $url = esc_url_raw( $url );
        $token = '';
        $body = shipbubble_base_response();
        
        if (isset($apiKey)) {
            $token = $apiKey;
        } elseif (strlen(shipbubble_get_token()) > 0 ) {
            // get API key from options
            $token = shipbubble_get_token();
        }

        
        if (strlen($token) > 0) {
            $args = array( 
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                ),
            );
        
            $response = wp_safe_remote_get( $url, $args );
        
            // response data
            $data = wp_remote_retrieve_body( $response );

            if (isset($data)) {
                $body = $data;
            }
        }
        
        // output data
        return json_decode($body);
    }

    /**
     * Fetch all available couriers
     *
     * @return void
     */
    function shipbubble_get_couriers() 
    {

        $url = SHIPBUBBLE_BASE_URL . '/couriers';

        $url = esc_url_raw( $url );

        // get API key from options
        $token = shipbubble_get_token();

        $args = array( 
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
        );

        $response = wp_safe_remote_get( $url, $args );

        // response data
        $data = wp_remote_retrieve_body( $response );

        if (isset($data)) {
            $body = $data;
        }

        // output data
        return json_decode($body);
    }

    /**
     * Validate an address
     *
     * @param string $name
     * @param string $email
     * @param string $phone
     * @param string $address
     * @return object addressCode
     */
    function shipbubble_validate_address(string $name, string $email, string $phone, string $address):object
    {
        $url = SHIPBUBBLE_BASE_URL . '/address/validate';

        $url = esc_url_raw( $url );
        // get API key from options
        $token = shipbubble_get_token();
        $body = shipbubble_base_response();
        
        $args = array( 
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
        );

        $payload = array(
            'name' => $name, 
            'email' => $email, 
            'phone' => $phone, 
            'address' => $address
        );

        $args['body'] = $payload;

        $response = wp_safe_remote_post( $url, $args );

        // response data
        $data = wp_remote_retrieve_body( $response );

        if (isset($data)) {
            $body = $data;
        }

        // output data
        return json_decode($body);
    }
