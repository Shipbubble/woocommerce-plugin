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

        $body = shipbubble_base_response(); // default response
        
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
     * @return object
     */
    function shipbubble_get_couriers(): object
    {

        $url = SHIPBUBBLE_BASE_URL . '/couriers';

        $url = esc_url_raw( $url );

        $body = shipbubble_base_response(); // default response

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

        $body = shipbubble_base_response(); // default response
        
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

    
    function shipbubble_get_shipping_rates(string $addressCode, array $products)
    {
        $options = get_option( WC_SHIPBUBBLE_ID, shipbubble_wc_options_default() );

        $courier_list = isset( $options['courier_list'] ) ? $options['courier_list'] : array('all');

        $url = SHIPBUBBLE_BASE_URL . '/fetch_rates';

        // if (!array_search('all', $courier_list)) {
        //     $service_codes = implode(',', $courier_list);
        //     $url = SHIPBUBBLE_BASE_URL . '/fetch_rates/' . $service_codes;
        // }

        $url = esc_url_raw( $url );

        // get API key from options
        $token = shipbubble_get_token();

        // $body = shipbubble_base_response(); // default response

        $args = array( 
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
        );

        $packages = array();
        $netWeight = 0;
        foreach ($products['data'] as $item) {
            $packages[] = array(
                'name' => $item['name'],
                'description' => $item['description'],
                'unit_weight' => $item['weight'],
                'unit_amount' => $item['price'],
                'quantity' => (string) $item['quantity'],
            );
            $netWeight += ($item['weight'] * $item['quantity']);
        }

        $setDimensions = shipbubble_set_package_dimensions($netWeight);

        $senderAddressCode = get_option(WC_SHIPBUBBLE_ID)['address_code'];

        $payload = [
            'sender_address_code' => $senderAddressCode,
            'reciever_address_code' => $addressCode,
            'pickup_date' => date('Y-m-d'),
            'category_id' => '58823517',
            'package_items' => $packages,
            'package_dimension' => [
                'length' => $setDimensions['length'],
                'width' => $setDimensions['width'],
                'height' => $setDimensions['height']
            ],
            'service_type' => 'pickup',
            'delivery_instructions' => 'n/a'
        ];

        // return json_decode(json_encode($payload));

        // pass payload
        $args['body'] = $payload;

        // call endpoint
        $response = wp_safe_remote_post( $url, $args );

        // response data
        $data = wp_remote_retrieve_body( $response );

        if (isset($data)) {
            $body = $data;
        }

        // output data
        return json_decode($body);
    }
