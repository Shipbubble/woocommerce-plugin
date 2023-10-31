<?php

add_action('woocommerce_admin_order_data_after_billing_address', 'shibubble_order_data_after_billing_address', 10, 1);
function shibubble_order_data_after_billing_address($order)
{
    if (in_array($order->get_status(), SHIPBUBBLE_WC_BAD_ORDER_STATUS_ARR)) {
        return;
    }

    // Verify Shipbubble Order
    if (!$order->has_shipping_method(SHIPBUBBLE_ID)) {
        return;
    }

    $shipmentDetailsArray = get_post_meta($order->get_id(), 'shipbubble_shipment_details');
    // error_log(print_r($shipmentDetailsArray, true));
    if (!count($shipmentDetailsArray)) {
        return;
    }

    // Get Shipping Data
    $shippingData = $order->data['shipping'];
    $orderAddress = sb_create_address($shippingData['address_1'], $shippingData['city'], $shippingData['state'], $shippingData['country']);


    // Get Delivery Address
    $shipbubbleDeliveryAddress = get_post_meta($order->get_id(), 'shipbubble_delivery_address', true);

    // Get Shipbubble Order ID
    $shipbubbleOrderId = get_post_meta($order->get_id(), 'shipbubble_order_id', true);

    // $shipment = $shipmentDetailsArray[0]; // [0 => 'request token ...']

    // var_dump($shipmentDetailsArray[0]);
    // die;
    
    $convertedArray = shipbubble_convert_special_strings_to_array($shipmentDetailsArray[0]); // [0 => 'request token ...']
    $shipment = json_decode(json_encode($convertedArray));
    $serviceCode = ''; // prod
    // $serviceCode = array( 'speedaf-express' ); // test

    if (!empty($shipment)) 
    {
        $serviceCode = $shipment->service_code; // prod
    }

    // Check date meets 48hr mark
    $today = new DateTime('now');
    $orderDate = new DateTime($order->date_created);
    $interval = $orderDate->diff($today);
    $hrsInterval = $interval->h + ($interval->days * 24);

    //if (strlen($shipbubbleOrderId) < 1 && !sb_compare_addresses($shipbubbleDeliveryAddress, $orderAddress) && $hrsInterval < SHIPBUBBLE_REQUEST_TOKEN_EXPIRY && !is_null($shipment)) {

    // set flag to regenerate token
    $regenerateToken = false;

    // Check token has expired and shipbubble id doesn't exist to enable flag
    if (strlen($shipbubbleOrderId) < 1 && $hrsInterval > SHIPBUBBLE_REQUEST_TOKEN_EXPIRY) 
    {
        $regenerateToken = true;
    }

    // Check address has changed under 48hrs to enable flag
    if (!sb_compare_addresses($shipbubbleDeliveryAddress, $orderAddress)) 
    {
        $regenerateToken = true;
    }

    if ($regenerateToken && !is_null($shipment)) 
    {
        // regenerate token
        $regeneratedMeta = shipbubble_regenerate_rate_token($order, $shipment);

        // update db
        if (count($regeneratedMeta)) {
            $shipment = json_encode($regeneratedMeta);
            update_post_meta($order->get_id(), 'shipbubble_shipment_details', $shipment);
            update_post_meta($order->get_id(), 'shipbubble_delivery_address', $orderAddress);
        }
    }

?>
    <?php if (strlen($shipbubbleOrderId) < 1 && !is_null($shipment)): ?>
        <input type="hidden" id="wc_order_id" name="wc_order_id" value='<?php echo esc_html($order->get_id()); ?>' />

        <input type="hidden" id="shipment_details" name="shipment_details" value='<?php echo esc_html(json_encode($shipment)); ?>' />

        <button id="create-shipment" style="background-color: #FF5170; color: #FFF; padding: 4px 16px; border: 1px solid #FF5170; border-radius: 3px; cursor: pointer;">
            Create Shipment via Shipbubble
        </button>
    <?php endif; ?>

    <?php
}


add_filter('manage_edit-shop_order_columns', 'shipbubble_custom_order_column', 10);
function shipbubble_custom_order_column($columns)
{
    $reorderedColumns = array();

    foreach ($columns as $key => $col) {
        $reorderedColumns[$key] = $col;
        if ($key == 'order_status') {
            // Inserting after STATUS Column
            $reorderedColumns['sb_shipping_status'] = esc_html('Shipping Status');
        }
    }

    return $reorderedColumns;
}

add_action('manage_shop_order_posts_custom_column', 'shipbubble_shipping_status_column_content');
function shipbubble_shipping_status_column_content($column)
{
    global $post;

    // Verify Column ID
    if ('sb_shipping_status' === $column) {
        // Get Order
        $order = new WC_Order($post->ID);

        // Conditional function based on the Order shipping method 
        if ($order->has_shipping_method(SHIPBUBBLE_ID)) {
            // Check Shipping Status
            $status = get_post_meta($post->ID, 'shipbubble_tracking_status', true);
            if (!empty($status)) {
                echo shipbubble_shipment_status_label($status);
            } elseif (in_array($order->get_status(), SHIPBUBBLE_WC_BAD_ORDER_STATUS_ARR)) {
                echo '<mark class="order-status status-on-hold">
                        <span>No shipment initiated</span>
                    </mark>';
            } else {
                echo '<mark class="order-status status-on-hold">
                        <span>No shipment yet</span>
                    </mark>';
            }
        } elseif (!$order->has_shipping_method(SHIPBUBBLE_ID)) {
            echo '<span class="dashicons dashicons-minus" title="not processed via shipbubble"></span>';
        } else {
            echo esc_html('Not specified');
        }
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

add_filter('is_protected_meta', 'hide_meta_sb_shipment_meta', 10, 2);

function hide_meta_sb_shipment_meta($protected, $meta_key)
{
    return $meta_key == 'sb_shipment_meta' ? true : $protected;
}

// end


// Adding Meta container admin shop_order pages
add_action('add_meta_boxes', 'mv_add_meta_boxes');
if (!function_exists('mv_add_meta_boxes')) {
    function mv_add_meta_boxes()
    {
        add_meta_box('sb_track_shipment', __('Track Shipment', 'woocommerce'), 'shipbubble_track_order_shipment', 'shop_order', 'side', 'core');
    }
}

// Adding Meta field in the meta container admin shop_order pages
if (!function_exists('shipbubble_track_order_shipment')) {
    function shipbubble_track_order_shipment()
    {
        global $post;

        $shipbubbleOrderId = get_post_meta($post->ID, 'shipbubble_order_id', true) ?? '';

        $response = null;
        if (strlen($shipbubbleOrderId) > 0) {
            $response = shipbubble_track_shipment($shipbubbleOrderId);
        }

    ?>

        <?php if (isset($response->response_code) && $response->response_code == SHIPBUBBLE_RESPONSE_IS_OK) : ?>

            <a href="<?php echo esc_html($response->data[0]->tracking_url); ?>" target="_blank">
                Tracking Link
            </a><br>

            <?php
            $latestPackageStatus = end($response->data[0]->package_status);

            // set shipping status
            update_post_meta($post->ID, 'shipbubble_tracking_status', strtolower($latestPackageStatus->status));

            ?>

            <?php foreach ($response->data[0]->package_status as $key => $data) : ?>

                <div class="sb-flex-container">
                    <span>
                        <?php echo esc_html(date('F j, Y', strtotime($data->datetime))); ?>
                        <br>
                        <?php echo esc_html(date('H:i A', strtotime($data->datetime))); ?>
                    </span>
                    <span>
                        <?php echo esc_html($data->status); ?>
                    </span>
                </div>

            <?php endforeach; ?>
        <?php endif; ?>

<?php
    }
}



add_action('woocommerce_admin_order_data_after_billing_address', 'shipbubble_display_wallet_balance', 10, 1);

function shipbubble_display_wallet_balance($order)
{
    $balance = '0';
    $currency = '₦';

    if (in_array($order->get_status(), SHIPBUBBLE_WC_BAD_ORDER_STATUS_ARR)) {
        return;
    }

    // Verify Shipbubble Order
    if (!$order->has_shipping_method(SHIPBUBBLE_ID)) {
        return;
    }

    if (!count(get_post_meta($order->get_id(), 'shipbubble_shipment_details'))) {
        return;
    }

    $shipbubbleOrderId = get_post_meta($order->get_id(), 'shipbubble_order_id', true);
    if (strlen($shipbubbleOrderId) < 1) {
        $response = shipbubble_get_wallet_balance(shipbubble_get_token());

        if (isset($response->response_code) && $response->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
            $balance = $response->data->balance;
        }

        echo '<input type="hidden" id="shipbubble_shipping_cost" name="shipbubble_shipping_cost" value="' . esc_html((float) $order->shipping_total) . '"/>';

        echo '<input type="hidden" id="shipbubble_wallet_balance" name="shipbubble_wallet_balance" value="' . esc_html((float) $balance) . '"/>';

        echo '<p><strong>' . __('Shipbubble Wallet Balance:') . '</strong><br> <strong>' . esc_html($currency) . esc_html(number_format($balance, 2)) . '</strong></p>';
    } else {
        echo '<p><strong>' . __('Shipbubble Order ID:') . '</strong><br> ' . esc_html($shipbubbleOrderId) . '</p>';
    }
}
