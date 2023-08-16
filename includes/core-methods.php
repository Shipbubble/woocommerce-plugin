<?php // Core Methods


    function shipbubble_get_token(): string
    {
        $options = get_option( 'shipbubble_options', shipbubble_options_default() );

        return isset( $options['shipbubble_api_key'] ) ? sanitize_text_field( $options['shipbubble_api_key'] ) : '';
    }

    function shipbubble_base_response($status = null, $message = null, $data = null)
    {
        return json_encode(
            array(
                'response_code' => '500',
                'status' => $status ?? 'failed',
                'message' => $message ?? 'Unable to complete request, try again later',
                'data' => $data ?? [],
            )
        );
    }

    function shipbubble_courier_options() 
    {
        $body = array(
            'all' => 'All'
        );

        $response = shipbubble_get_couriers();
        if (isset($response->response_code) && $response->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
            foreach ($response->data as $courier) {
                $body[$courier->service_code] = $courier->name;
            }
        }
        return $body;
        
    }

    function shipbubble_get_order_categories() 
    {
        $body = array();

        $response = shipbubble_order_categories();
        if (isset($response->response_code) && $response->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
            foreach ($response->data as $data) {
                $body[$data->category_id] = $data->category;
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

    function shipbubble_process_shipping_rates($addressCode, $products, $serviceCodes = array())
    {
        $options = get_option( WC_SHIPBUBBLE_ID, shipbubble_wc_options_default() );

        // $courier_price_type = isset( $options['shipping_price'] ) ? sanitize_text_field( $options['shipping_price'] ) : 'default';

        $extra_charges = isset( $options['extra_charges'] ) ? sanitize_text_field( $options['extra_charges'] ) : '0';

        $rates = array();

        $response = shipbubble_get_shipping_rates($addressCode, $products, $serviceCodes);

        if (isset($response->response_code) && $response->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
            $data = $response->data;
            $rates['request_token'] = $data->request_token;
            $rates['extra_charges'] = $extra_charges;

            $rates['rate'] = 'default';
            $rates['couriers'] = $data->couriers;
        } else {
            if (isset($response['error'][0])) {
                $rates['error'] = $response['error'][0];
            } elseif (isset($response['message'])) {
                $rates['error'] = $response['message'];
            } else {
                $rates['error'] = 'unable to fetch rates';
            }
        }

        return $rates;
    }

    function shipbubble_regenerate_rate_token($order, $serviceCodes)
    {
        $countryObject = WC()->countries;

		$name = $order->data['shipping']['first_name'] . ' ' . $order->data['shipping']['last_name'];
		$address = $order->data['shipping']['address_1'] . ' ' . $order->data['shipping']['city'] . ' ' . $countryObject->states[ $order->data['shipping']['country'] ][ $order->data['shipping']['state'] ] . ' ' . $countryObject->countries[ $order->data['shipping']['country'] ];

        // Initialize Shipping Address Array
		$shipping = array(
			'name' => $name,
			'address' => $address,
			'phone' => $order->data['billing']['phone'],
			'email' => $order->data['billing']['email'],
        );
        
        // Generate Address Code
        $addressResponse = shipbubble_validate_address(
            $shipping['name'], 
			$shipping['email'], 
			$shipping['phone'], 
			$shipping['address']
		);
        
        $rates = array();

        // if successful
		if (isset($addressResponse->response_code) && $addressResponse->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
			// $products = shipbubble_get_checkout_orders();
			$addressCode = $addressResponse->data->address_code;

            $items = array();
            $items['total'] = 0;
            $i = 0;

            // Set up Orders
            foreach ($order->get_items() as $key => $value) {
                $product = wc_get_product( $value['product_id'] );

                $items['total'] += $product->get_price();

                $weight = empty($product->get_weight()) ? 0 : $product->get_weight();

                $items['data'][$i]['quantity'] = $value['quantity'];
                $items['data'][$i]['price'] = $product->get_price();
                $items['data'][$i]['type'] = $product->get_type();
                $items['data'][$i]['name'] = $product->get_name();
                $items['data'][$i]['weight'] = $weight;
                $items['data'][$i]['length'] = $product->get_length();
                $items['data'][$i]['width'] = $product->get_width();
                $items['data'][$i]['height'] = $product->get_height();
                $items['data'][$i]['description'] = empty($product->get_short_description()) ? 'n/a' : $product->get_short_description();

                $i++;
            }
			
            // Fetch Shipping rate for service code
			$response = shipbubble_process_shipping_rates($addressCode, $items, $serviceCodes);
            
			if (count($response) > 0) {
                $rates['request_token'] = $response['request_token'];
                $rates['service_code'] = $response['couriers'][0]->service_code;
                $rates['courier_id'] = $response['couriers'][0]->courier_id;
                $rates['courier_name'] = $response['couriers'][0]->courier_name;
                $rates['shipment_cost'] = $response['couriers'][0]->total;
			}
		}
        
        // echo '<pre>' . var_export($rates, true) . '</pre>';
        // die;
        
        return $rates;
    }


    function shipbubble_shipment_status_label( $status )
    {
        $label = '';

        switch ($status) {
            case 'confirmed':
                $label .= '<mark class="order-status status-on-hold">
                    <span>Confirmed</span>
                </mark>';
                break;

            case 'picked_up':
                $label .= '<mark class="order-status status-on-hold">
                    <span>Picked up</span>
                </mark>';
                break;

            case 'in_transit':
                $label .= '<mark class="order-status status-trash">
                    <span>In Transit</span>
                </mark>';
                break;

            case 'completed':
                $label .= '<mark class="order-status status-completed">
                    <span>Completed</span>
                </mark>';
                break;

            case 'cancelled':
                $label .= '<mark class="order-status status-failed">
                    <span>Cancelled</span>
                </mark>';
                break;
                
            default:
                $label .= '<mark class="order-status status-processing">
                    <span>Pending</span>
                </mark>';
                break;
        }

        return $label;
    }

    function sb_create_address(string $address, string $city, string $stateLabel, string $countryLabel)
    {
        $countryObject = WC()->countries;
        $state = $countryObject->states[ $countryLabel ][ $stateLabel ];
        $country = $countryObject->countries[ $countryLabel ];
        
        return $address . ' ' . $city . ' ' . $state . ' ' . $country;
    }

    function sb_compare_addresses(string $address1, string $address2)
    {
        return trim(strtolower($address1)) == trim(strtolower($address2));
    }