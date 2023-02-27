<?php // Core Methods


    function shipbubble_get_token(): string
    {
        $options = get_option( 'shipbubble_options', shipbubble_options_default() );

        return isset( $options['shipbubble_api_key'] ) ? sanitize_text_field( $options['shipbubble_api_key'] ) : '';
    }

    function shipbubble_base_response($status = null, $message = null, $data = null): string
    {
        return json_encode(
            array(
                'status' => $status ?? 'failed',
                'message' => $message ?? 'Unable to complete request, try again later',
                'data' => $data
            )
        );
    }

    function shipbubble_courier_options() 
    {
        $body = array(
            'all' => 'All'
        );

        $response = shipbubble_get_couriers();
        if ($response->status == 'success') 
        {
            foreach ($response->data as $courier) {
                $body[$courier->service_code] = $courier->name;
            }
        }
        return $body;
        
    }

    
    function shipbubble_get_checkout_orders(): array
    {
        $products = array();
        $cart = WC()->cart->get_cart();

        $products['dimensions']['length'] = 2; // default
        $products['dimensions']['width'] = 5; // default
        $products['dimensions']['height'] = 0; // start
        $products['total'] = 0;

        foreach($cart as $cart_item_key => $cart_item ) {
            $products['total'] += $cart_item['line_total'];

            $data = $cart_item['data'];

            $weight = empty($data->get_weight()) ? 0 : $data->get_weight();

            $products['data'][$cart_item_key]['quantity'] = $cart_item['quantity'];
            $products['data'][$cart_item_key]['price'] = $data->get_price();
            $products['data'][$cart_item_key]['type'] = $data->get_type();
            $products['data'][$cart_item_key]['name'] = $data->get_name();
            $products['data'][$cart_item_key]['weight'] = $weight;
            $products['data'][$cart_item_key]['length'] = $data->get_length();
            $products['data'][$cart_item_key]['width'] = $data->get_width();
            $products['data'][$cart_item_key]['height'] = $data->get_height();
            $products['data'][$cart_item_key]['description'] = empty($data->get_short_description()) ? 'n/a' : $data->get_short_description();

        }

        return $products;
    }

    function shipbubble_set_package_dimensions($weight)
    {
        $shipbubble_dimensions = shipbubble_package_dimensions();
        $dimensions = array();

        if ($weight <= 5) {
            $dimensions = $shipbubble_dimensions[0];
        } elseif ($weight > 5 && $weight <= 20) {
            $dimensions = $shipbubble_dimensions[1];
        } else {
            $dimensions = $shipbubble_dimensions[2];
        }
        return $dimensions;
    }

    function shipbubble_package_dimensions(): array
    {
        return array(
            array(
                'box_size_id' => 27459899,
                'name' => 'tiny box',
                'description_image_url' => 'https://res.cloudinary.com/delivry/image/upload/v1635776054/package_boxes/tiny_box_ie9dob.jpg',
                'height' => 2,
                'width' => 5,
                'length' => 5,
                'max_weight' => 5
            ),
            array(
                'box_size_id' => 44174253,
                'name' => 'medium box',
                'description_image_url' => 'https://res.cloudinary.com/delivry/image/upload/v1635776059/package_boxes/medium_box_v1oisg.png',
                'height' => 10,
                'width' => 20,
                'length' => 20,
                'max_weight' => 20
            ),
            array(
                'box_size_id' => 42172412,
                'name' => 'big box',
                'description_image_url' => 'https://res.cloudinary.com/delivry/image/upload/v1635776049/package_boxes/big_box_wrcubo.png',
                'height' => 2,
                'width' => 40,
                'length' => 40,
                'max_weight' => 40
            ),
        );
    }

    function shipbubble_process_shipping_rates($addressCode, $products)
    {
        $options = get_option( WC_SHIPBUBBLE_ID, shipbubble_wc_options_default() );

        $courier_price_type = isset( $options['shipping_price'] ) ? sanitize_text_field( $options['shipping_price'] ) : 'default';

        $extra_charges = isset( $options['extra_charges'] ) ? sanitize_text_field( $options['extra_charges'] ) : '0';

        $rates = array();

        $response = shipbubble_get_shipping_rates($addressCode, $products);

        // return $response;

        if (isset($response->status) && strtolower($response->status) == 'success') 
        {
            $data = $response->data;
            $rates['request_token'] = $data->request_token;
            $rates['extra_charges'] = $extra_charges;
            
            switch(strtolower($courier_price_type))
            {
                case 'default':
                    $rates['rate'] = 'default';
                    $rates['couriers'] = $data->couriers;
                    break;

                case 'fastest':
                    $rates['rate'] = 'fastest';
                    $rates['couriers'][] = $data->fastest_courier;
                    break;

                case 'cheapest':
                    $rates['rate'] = 'cheapest';
                    $rates['couriers'][] = $data->cheapest_courier;
                    break;

                default:
                    $rates['rate'] = 'default';
                    $rates['couriers'] = $data->couriers;
                    break;
            }
        }

        return $rates;
    }