<?php

    add_action( 'woocommerce_admin_order_data_after_billing_address', 'shibubble_order_data_after_billing_address', 10, 1 );
    function shibubble_order_data_after_billing_address( $order ) 
    {
        // array (
        //     'first_name' => 'mavi',
        //     'last_name' => 'prince',
        //     'company' => '',
        //     'address_1' => '21 Ilamoye street, Ijeshatedo',
        //     'address_2' => '',
        //     'city' => 'Surulere',
        //     'state' => 'LA',
        //     'postcode' => '',
        //     'country' => 'NG',
        //     'phone' => '',
        //   )

        $shippingData = $order->data['shipping'];
        $orderAddress = sb_create_address($shippingData['address_1'], $shippingData['city'], $shippingData['state'],$shippingData['country']);

        // echo '<pre> ' . var_export($orderAddress, true) . '</pre>';
        // echo '<pre> ' . var_export($order->data['shipping'], true) . '</pre>';
        // die;
        // echo '<pre>' . var_export(wc_get_product( $order->get_items()[9]['product_id'] ), true) . '</pre>';
        // echo '<pre> ' . var_export(json_decode($shipment)->service_code, true) . '</pre>';
        // die;

        $shipbubbleDeliveryAddress = get_post_meta( $order->get_id(), 'shipbubble_delivery_address', true );

        $shipbubbleOrderId = get_post_meta( $order->get_id(), 'shipbubble_order_id', true );
        $shipment = get_post_meta( $order->get_id(), 'shipbubble_shipment_details' )[0];

        // $serviceCode = array( 'speedaf-express' ); // test
        $serviceCode = array( json_decode($shipment)->service_code ); // prod
        
        // Check date meets 48hr mark
        $today = new DateTime('now');
        $orderDate = new DateTime( $order->date_created );
        $interval = $orderDate->diff($today);

        // check token has lasted longer than 48hrs before regenerating new request token
        if( strlen($shipbubbleOrderId) < 1 && $interval->h > SHIPBUBBLE_REQUEST_TOKEN_EXPIRY) {
            $rates = shipbubble_regenerate_rate_token($order, $serviceCode);
            if (count($rates)) {
                $shipment = json_encode($rates);
                update_post_meta( $order->get_id(), 'shipbubble_shipment_details', $shipment );
                update_post_meta( $order->get_id(), 'shipbubble_delivery_address', $orderAddress );
            }
        }

        // Check address has changed under 48hrs before before regenerating new request token
        if ( strlen($shipbubbleOrderId) < 1 && !sb_compare_addresses($shipbubbleDeliveryAddress, $orderAddress) && $interval->h < SHIPBUBBLE_REQUEST_TOKEN_EXPIRY ) {
            // regenerate
            $rates = shipbubble_regenerate_rate_token($order, $serviceCode);
            if (count($rates)) {
                $shipment = json_encode($rates);
                update_post_meta( $order->get_id(), 'shipbubble_shipment_details', $shipment );
                update_post_meta( $order->get_id(), 'shipbubble_delivery_address', $orderAddress );
            }
        }

        // update_post_meta( $order->get_id(), 'shipbubble_order_id', 'helloworld' );
        // die;
        // echo '<p><strong>' . __( 'Shipment JSON:', SHIPBUBBLE_ID ) . '</strong><br>' . get_post_meta( $order->get_id(), 'shipbubble_shipment_details', true ) . '</p>';
        ?>
            <?php if (strlen($shipbubbleOrderId) < 1): ?>
                <input type="hidden" id="wc_order_id" name="wc_order_id" value='<?= $order->get_id(); ?>' />

                <input type="hidden" id="shipment_details" name="shipment_details" value='<?= $shipment; ?>' />

                <button id="create-shipment" style="background-color: #FF5170; color: #FFF; padding: 4px 16px; border: 1px solid #FF5170; border-radius: 3px; cursor: pointer;">
                    Create Shipment
                </button>
            <?php endif; ?>

        <?php
    }


    add_filter('manage_edit-shop_order_columns', 'shipbubble_custom_order_column', 20);
    function shipbubble_custom_order_column($columns)
    {
        $reorderedColumns = array();

        foreach ($columns as $key => $col) {
            $reorderedColumns[$key] = $col;
            if ($key == 'order_status') {
                // Inserting after STATUS Column
                $reorderedColumns['shipping_status'] = __( 'Shipping Status', 'theme_domain');
            }
        }

        return $reorderedColumns;
    }

    // Adding custom fields meta data for each new Column
    add_action('manage_shop_order_posts_custom_column', 'custom_orders_list_column_content', 20, 2);
    function custom_orders_list_column_content( $column, $post_id)
    {
        switch ($column) {
            case 'shipping_status':

                $status = get_post_meta( $post_id, 'shipbubble_tracking_status', true );
                if (!empty($status)) {
                    echo shipbubble_shipment_status_label($status);
                } else {
                    echo '<mark class="order-status status-on-hold">
                        <span>No Shipment Yet</span>
                    </mark>';
                }

                break;
        }
    }


    /**
     * Hide Custom Order Fields
     */
    add_filter('is_protected_meta', 'hide_meta_shipbubble_tracking_status', 10, 2);
    function hide_meta_shipbubble_tracking_status($protected, $meta_key)
    {
        return $meta_key == 'shipbubble_tracking_status' ? true : $protected;
    }

    add_filter('is_protected_meta', 'hide_meta_shipbubble_shipment_details', 10, 2);
    function hide_meta_shipbubble_shipment_details($protected, $meta_key)
    {
        return $meta_key == 'shipbubble_shipment_details' ? true : $protected;
    }

    add_filter('is_protected_meta', 'hide_meta_shipbubble_order_id', 10, 2);
    function hide_meta_shipbubble_order_id($protected, $meta_key)
    {
        return $meta_key == 'shipbubble_order_id' ? true : $protected;
    }

    add_filter('is_protected_meta', 'hide_meta_shipbubble_delivery_address', 10, 2);
    function hide_meta_shipbubble_delivery_address($protected, $meta_key)
    {
        return $meta_key == 'shipbubble_delivery_address' ? true : $protected;
    }

    // end


    // Adding Meta container admin shop_order pages
    add_action( 'add_meta_boxes', 'mv_add_meta_boxes' );
    if ( ! function_exists( 'mv_add_meta_boxes' ) )
    {
        function mv_add_meta_boxes()
        {
            add_meta_box( 'sb_track_shipment', __('Track Shipment','woocommerce'), 'shipbubble_track_order_shipment', 'shop_order', 'side', 'core' );
        }
    }

    // Adding Meta field in the meta container admin shop_order pages
    if ( ! function_exists( 'shipbubble_track_order_shipment' ) )
    {
        function shipbubble_track_order_shipment()
        {
            global $post;

            $shipbubbleOrderId = get_post_meta( $post->ID, 'shipbubble_order_id', true ) ?? '';

            $response = null;
            if (strlen($shipbubbleOrderId) > 0) {
                $response = shipbubble_track_shipment($shipbubbleOrderId);
            }

            ?>

                <?php if (isset($response->response_code) && $response->response_code == HTTP_RESPONSE_OK): ?>

                    <a href="<?= $response->data[0]->tracking_url; ?>" target="_blank">
                        Tracking Link
                    </a><br>


                    <?php foreach($response->data[0]->package_status as $key => $data): ?>

                        <?php if ($key > 0): ?>
                            <div class="sb-status-indicator"></div>
                        <?php endif; ?>

                        <div class="sb-flex-container">
                            <span>
                                <?= date('F j, Y', strtotime($data->datetime)); ?>
                                <br>
                                <?= date('H:i A', strtotime($data->datetime)); ?>
                            </span>
                            <span><?= $data->status; ?></span>
                        </div>
                        
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php
        }
    }


    
    add_action( 'woocommerce_admin_order_data_after_billing_address', 'shipbubble_display_wallet_balance', 10, 1 );

    function shipbubble_display_wallet_balance( $order ) {
        $balance = '0';
        $currency = '₦';

        // echo '<pre>' .  var_export($order->shipping_total, true) . '</pre>';
        // die;

        // $shipbubbleOrderId = get_post_meta( $order->get_id(), 'shipping_total', true );

        $shipbubbleOrderId = get_post_meta( $order->get_id(), 'shipbubble_order_id', true );

        if( strlen($shipbubbleOrderId) < 1 ) {
            $response = shipbubble_get_wallet_balance(shipbubble_get_token());
    
            if (isset($response->response_code) && $response->response_code == HTTP_RESPONSE_OK) {
                $balance = $response->data->balance;
            }

            echo '<input type="hidden" id="shipbubble_shipping_cost" name="shipbubble_shipping_cost" value="' . $order->shipping_total . '"/>';

            echo '<input type="hidden" id="shipbubble_wallet_balance" name="shipbubble_wallet_balance" value="' . $balance . '"/>';
    
            echo '<p><strong>' . __( 'Shipbubble Wallet Balance:' ) . '</strong><br> <strong>' . $currency . number_format( $balance, 2 ) . '</strong></p>';
            
        } else {
            echo '<p><strong>' . __( 'Shipbubble Order ID:' ) . '</strong><br> ' . $shipbubbleOrderId . '</p>';
        }

    }
    