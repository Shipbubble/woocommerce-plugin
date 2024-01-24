<?php // Shipbubble Endpoints

// disable direct file access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Authenticate & Fetch User Wallet Balance
 *
 * @param string $apiKey
 * @return mixed
 */
function shipbubble_get_wallet_balance(string $apiKey = '')
{

    $url = SHIPBUBBLE_BASE_URL . '/wallet/balance';

    $url = esc_url_raw($url);
    $token = '';

    if (isset($apiKey)) {
        $token = $apiKey;
    } elseif (strlen(shipbubble_get_token()) > 0) {
        // get API key from options
        $token = shipbubble_get_token();
    }

    $body = shipbubble_base_response(); // default response

    if (strlen($token) > 0) {
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'x-shipbubble-platform' => 'wordpress'
            ),
            'timeout'     => SHIPBUBBLE_EP_REQUEST_TIMEOUT,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking'    => true,
            'cookies'     => array(),
            'compress'    => false,
            'decompress'  => true,
            'sslverify'   => true,
            'stream'      => false,
            'filename'    => null
        );

        $response = wp_safe_remote_get($url, $args);

        if (!is_wp_error($response)) {
            // response data
            $data = wp_remote_retrieve_body($response);
            // response code
            $response_code = wp_remote_retrieve_response_code($response);
            $data = json_decode($data, true);

            // append response code
            $data['response_code'] = $response_code;
            $body = json_encode($data);
        } else {
            $error_message = $response->get_error_message();
            error_log(print_r($error_message, true));
            // throw new Exception( $error_message );
        }
    }

    // output data
    return json_decode($body);
}

/**
 * Fetch all available couriers
 *
 * @return mixed
 */
function shipbubble_get_color_code()
{

    $url = SHIPBUBBLE_BASE_URL . '/brand_assets';

    $url = esc_url_raw($url);

    $body = shipbubble_base_response(); // default response

    // get API key from options
    $token = shipbubble_get_token();

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'x-shipbubble-platform' => 'wordpress'
        ),
        'timeout'     => SHIPBUBBLE_EP_REQUEST_TIMEOUT,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'cookies'     => array(),
        'compress'    => false,
        'decompress'  => true,
        'sslverify'   => true,
        'stream'      => false,
        'filename'    => null
    );

    $response = wp_safe_remote_get($url, $args);

    if (!is_wp_error($response)) {
        // response data
        $data = wp_remote_retrieve_body($response);
        // response code
        $response_code = wp_remote_retrieve_response_code($response);
        $data = json_decode($data, true);

        // append response code
        $data['response_code'] = $response_code;
        $body = json_encode($data);
    } else {
        $error_message = $response->get_error_message();
        error_log(print_r($error_message, true));
        // throw new Exception( $error_message );
    }

    // output data
    return json_decode($body);
}

/**
 * Fetch all available couriers
 *
 * @return mixed
 */
function shipbubble_get_couriers()
{

    $url = SHIPBUBBLE_BASE_URL . '/couriers';

    $url = esc_url_raw($url);

    $body = shipbubble_base_response(); // default response

    // get API key from options
    $token = shipbubble_get_token();

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'x-shipbubble-platform' => 'wordpress'
        ),
        'timeout'     => SHIPBUBBLE_EP_REQUEST_TIMEOUT,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'cookies'     => array(),
        'compress'    => false,
        'decompress'  => true,
        'sslverify'   => true,
        'stream'      => false,
        'filename'    => null
    );

    $response = wp_safe_remote_get($url, $args);

    if (!is_wp_error($response)) {
        // response data
        $data = wp_remote_retrieve_body($response);
        // response code
        $response_code = wp_remote_retrieve_response_code($response);
        $data = json_decode($data, true);

        // append response code
        $data['response_code'] = $response_code;
        $body = json_encode($data);
    } else {
        $error_message = $response->get_error_message();
        error_log(print_r($error_message, true));
        // throw new Exception( $error_message );
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
 * @return mixed addressCode
 */
function shipbubble_validate_address(string $name, string $email, string $phone, string $address)
{
    $url = SHIPBUBBLE_BASE_URL . '/address/validate';

    $url = esc_url_raw($url);

    // get API key from options
    $token = shipbubble_get_token();

    $body = shipbubble_base_response(); // default response

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'x-shipbubble-platform' => 'wordpress'
        ),
        'timeout'     => SHIPBUBBLE_EP_REQUEST_TIMEOUT,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'cookies'     => array(),
        'compress'    => false,
        'decompress'  => true,
        'sslverify'   => true,
        'stream'      => false,
        'filename'    => null
    );

    $payload = array(
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address
    );

    $args['body'] = $payload;

    $response = wp_safe_remote_post($url, $args);

    if (!is_wp_error($response)) {
        // response data
        $data = wp_remote_retrieve_body($response);
        // response code
        $response_code = wp_remote_retrieve_response_code($response);
        $data = json_decode($data, true);

        // append response code
        $data['response_code'] = $response_code;
        $body = json_encode($data);
    } else {
        $error_message = $response->get_error_message();
        error_log(print_r($error_message, true));
        // throw new Exception( $error_message );
    }

    // output data
    return json_decode($body);
}

/**
 * Fetch Shipping rates from different Couriers
 *
 * @param string $addressCode
 * @param array $products
 * @param array $serviceCodes
 * @return mixed
 */
function shipbubble_get_shipping_rates(string $addressCode, array $products, $serviceCodes = array())
{
    $options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

    // $courier_list = isset($options['courier_list']) ? $options['courier_list'] : array('all');

    $serviceCodesFormat = '';

    $url = SHIPBUBBLE_BASE_URL . '/fetch_rates';

    if (count($serviceCodes)) {
        $serviceCodesFormat = implode(',', $serviceCodes);
        $url = SHIPBUBBLE_BASE_URL . '/fetch_rates/' . $serviceCodesFormat;
    }

    $url = esc_url_raw($url);

    // echo '<pre>' . var_export($url, true) . '</pre>';
    // die;

    // get API key from options
    $token = shipbubble_get_token();


    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'x-shipbubble-platform' => 'wordpress'
        ),
        'timeout'     => SHIPBUBBLE_EP_REQUEST_TIMEOUT,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'cookies'     => array(),
        'compress'    => false,
        'decompress'  => true,
        'sslverify'   => true,
        'stream'      => false,
        'filename'    => null
    );

    $packages = array();
    $netWeight = 0;
    foreach ($products['data'] as $item) {
        $packages[] = array(
            'name' => $item['name'],
            'description' => strip_tags($item['description']),
            'unit_weight' => $item['weight'],
            'unit_amount' => $item['price'],
            'quantity' => (string) $item['quantity'],
        );
        $netWeight += ($item['weight'] * $item['quantity']);
    }

    $setDimensions = shipbubble_set_package_dimensions($netWeight);

    $senderAddressCode = get_option(WC_SHIPBUBBLE_ID)['address_code'];
    $categoryCode = get_option(WC_SHIPBUBBLE_ID)['store_category'];

    // Get the currency code used in the cart
    $currency_code = get_woocommerce_currency();

    $payload = [
        'sender_address_code' => $senderAddressCode,
        'reciever_address_code' => $addressCode,
        'pickup_date' => date('Y-m-d'),
        'category_id' => $categoryCode ?? '',
        'package_items' => $packages,
        'package_dimension' => [
            'length' => $setDimensions['length'],
            'width' => $setDimensions['width'],
            'height' => $setDimensions['height']
        ],
        'service_type' => 'pickup',
        'delivery_instructions' => $products['comments'] ?? 'please handle carefully',
    ];

    if (!empty($currency_code)) {
        $payload['store_checkout_currency'] = $currency_code;
    }

    // error_log(print_r($payload, true));

    // return json_decode(json_encode($payload));

    // pass payload
    $args['body'] = $payload;

    $body = shipbubble_base_response(); // default response

    // call endpoint
    $response = wp_safe_remote_post($url, $args);

    // return json_decode(json_encode($response));
    if (!is_wp_error($response)) {
        // response data
        $data = wp_remote_retrieve_body($response);
        // response code
        $response_code = wp_remote_retrieve_response_code($response);
        $data = json_decode($data, true);

        // append response code
        $data['response_code'] = $response_code;
        $body = json_encode($data);
    } else {
        $error_message = $response->get_error_message();
        error_log(print_r($error_message, true));
        // throw new Exception( $error_message );
    }

    // error_log(print_r($body, true));

    // output data
    return json_decode($body);
}

/**
 * Create shipment for a given set of orders
 *
 * @param array $shipmentPayload
 * @return mixed
 */
function shipbubble_create_shipment(array $shipmentPayload)
{
    $url = SHIPBUBBLE_BASE_URL . '/labels';

    $url = esc_url_raw($url);

    // get API key from options
    $token = shipbubble_get_token();

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'x-shipbubble-platform' => 'wordpress'
        ),
        'timeout'     => SHIPBUBBLE_EP_REQUEST_TIMEOUT,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'cookies'     => array(),
        'compress'    => false,
        'decompress'  => true,
        'sslverify'   => true,
        'stream'      => false,
        'filename'    => null
    );

    $payload = array(
        'request_token' => $shipmentPayload['request_token'],
        'service_code' => $shipmentPayload['service_code'],
        'courier_id' => $shipmentPayload['courier_id'],
    );

    // return json_decode(json_encode($payload));

    // pass payload
    $args['body'] = $payload;

    $body = shipbubble_base_response(); // default response

    // call endpoint
    $response = wp_safe_remote_post($url, $args);

    if (!is_wp_error($response)) {
        // response data
        $data = wp_remote_retrieve_body($response);
        // response code
        $response_code = wp_remote_retrieve_response_code($response);
        $data = json_decode($data, true);

        // append response code
        $data['response_code'] = $response_code;
        $body = json_encode($data);
    } else {
        $error_message = $response->get_error_message();
        error_log(print_r($error_message, true));
        // throw new Exception( $error_message );
    }

    // output data
    return json_decode($body);
}

/**
 * Track Shipment
 *
 * @param string $shipbubbleOrderId
 * @return mixed
 */
function shipbubble_track_shipment(string $shipbubbleOrderId)
{

    $url = SHIPBUBBLE_BASE_URL . '/labels/list/' . $shipbubbleOrderId;

    $url = esc_url_raw($url);

    // get API key from options
    $token = shipbubble_get_token();

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'x-shipbubble-platform' => 'wordpress'
        ),
        'timeout'     => SHIPBUBBLE_EP_REQUEST_TIMEOUT,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'cookies'     => array(),
        'compress'    => false,
        'decompress'  => true,
        'sslverify'   => true,
        'stream'      => false,
        'filename'    => null
    );

    $body = shipbubble_base_response(); // default response

    $response = wp_safe_remote_get($url, $args);

    if (!is_wp_error($response)) {
        // response data
        $data = wp_remote_retrieve_body($response);
        // response code
        $response_code = wp_remote_retrieve_response_code($response);
        $data = json_decode($data, true);

        // append response code
        $data['response_code'] = $response_code;
        $body = json_encode($data);
    } else {
        $error_message = $response->get_error_message();
        error_log(print_r($error_message, true));
        // throw new Exception( $error_message );
    }

    // output data
    return json_decode($body);
}

/**
 * Fetch Shipbubble Package Categories
 *
 * @return mixed
 */
function shipbubble_order_categories()
{

    $url = SHIPBUBBLE_BASE_URL . '/labels/categories';

    $url = esc_url_raw($url);

    // get API key from options
    $token = shipbubble_get_token();

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'x-shipbubble-platform' => 'wordpress'
        ),
        'timeout'     => SHIPBUBBLE_EP_REQUEST_TIMEOUT,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking'    => true,
        'cookies'     => array(),
        'compress'    => false,
        'decompress'  => true,
        'sslverify'   => true,
        'stream'      => false,
        'filename'    => null
    );

    $body = shipbubble_base_response(); // default response

    $response = wp_safe_remote_get($url, $args);

    if (!is_wp_error($response)) {
        // response data
        $data = wp_remote_retrieve_body($response);
        // response code
        $response_code = wp_remote_retrieve_response_code($response);
        $data = json_decode($data, true);

        // append response code
        $data['response_code'] = $response_code;
        $body = json_encode($data);
    } else {
        $error_message = $response->get_error_message();
        error_log(print_r($error_message, true));
        // throw new Exception( $error_message );
    }

    // output data
    return json_decode($body);
}
