<?php

add_action('woocommerce_admin_order_data_after_billing_address', 'shipbubble_order_data_after_billing_address', 10, 1);
/**
 * Adds the "Create Shipment via Shipbubble" button after the billing address on the order page.
 *
 * This function checks if the order is eligible for shipment creation via Shipbubble based on shipping method and status.
 * If eligible, it displays the button to create the shipment. It also handles displaying warnings if shipment cannot be processed.
 *
 * @param WC_Order $order The WooCommerce order object.
 *
 * @return void
 */
function shipbubble_order_data_after_billing_address($order)
{
    if (in_array($order->get_status(), SHIPBUBBLE_WC_BAD_ORDER_STATUS_ARR)) {
        return;
    }

    // Verify Shipbubble Order
    if (!$order->has_shipping_method(SHIPBUBBLE_ID)) {
        return;
    }

    $order_id = $order->get_id();
	$order_data = $order->get_data();

    if (shipbubble_get_order_meta($order_id, 'shipbubble_local_pickup')) return;

    // Get Shipbubble Order ID
    $shipbubbleOrderId = shipbubble_get_order_meta($order_id, 'shipbubble_order_id', true);

    $serializedShipment = shipbubble_get_order_meta($order_id, 'shipbubble_shipment_details');
	$style = "background-color: #000; color: #FFF; padding: 4px 16px; border: 1px solid #000; border-radius: 3px; cursor: not-allowed;";

	if (strlen($shipbubbleOrderId) < 1 && !shipbubble_data_is_serialized($serializedShipment))
    {
        $msg = "Unable to process this order for shipment";
        $output = '<div id="message" class="notice notice-warning is-dismissible">
            <p>' . $msg . '</p>
            
            <button type="button" class="notice-dismiss">
                <span class="screen-reader-text">Dismiss this notice.</span>
            </button>
        </div>';
        echo $output;

        echo '<button type="button" onclick="alert(\''. $msg . '\')" title="' . $msg . '" style="'.$style.'">
            Create Shipment via Shipbubble
        </button>';
        return;
    }

    $shipmentDetailsArray = unserialize($serializedShipment);

    if ($shipmentDetailsArray === false)
    {
        return;
    }
    
    // error_log(print_r($shipmentDetailsArray, true));
    if (!count($shipmentDetailsArray)) {
        return;
    }

    // set order id to meta
    $shipmentDetailsArray['order_id'] = $order_id;

    // Get Shipping Data
    $shippingData = $order_data['shipping'];
    $shippingPhone = $order_data['billing']['phone'];
    $orderAddress = sb_create_address($shippingData['address_1'], $shippingData['city'], $shippingData['state'], $shippingData['country']);
    $orderPhone = shipbubble_get_order_meta($order_id, 'shipbubble_delivery_phone', true);

    if (empty($orderPhone)) {
        $orderPhone = $shippingPhone;
        shipbubble_update_order_meta($order_id, 'shipbubble_delivery_phone', $orderPhone);
    }

    // Get Delivery Address
    $shipbubbleDeliveryAddress = shipbubble_get_order_meta($order_id, 'shipbubble_delivery_address', true);
    
    // serialize shipment
    $serializedShipment = serialize($shipmentDetailsArray);

    // make an object
    $shipment = json_decode(json_encode($shipmentDetailsArray), false);

    // Check date meets 48hr mark
    $today = new DateTime();
    $interval = $order->get_date_created()->diff($today);
    $hrsInterval = $interval->h + ($interval->days * 24);

    //if (strlen($shipbubbleOrderId) < 1 && !sb_compare_addresses($shipbubbleDeliveryAddress, $orderAddress) && $hrsInterval < SHIPBUBBLE_REQUEST_TOKEN_EXPIRY && !is_null($shipment)) {

    $disable = false;
    $disabled = "";


    // Check address has changed or token has expired
    if (!sb_compare_addresses($shipbubbleDeliveryAddress, $orderAddress) || $hrsInterval > SHIPBUBBLE_REQUEST_TOKEN_EXPIRY || $orderPhone != $shippingPhone)
    {
        $disable = true;
	    $disabled = "disabled='disabled'";
    }


    if (!$disable) {
	    $style = "background-color: #FF5170; color: #FFF; padding: 4px 16px; border: 1px solid #FF5170; border-radius: 3px; cursor: pointer;";
    }

?>
    <?php if (strlen($shipbubbleOrderId) < 1 && !is_null($shipment)): ?>
        <input type="hidden" id="wc_order_id" name="wc_order_id" value='<?php echo esc_html($order->get_id()); ?>' />

        <button id="create-shipment" style="<?php echo $style?>" <?php echo $disabled ?>>
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

/**
 * Adds content to the custom "Shipping Status" column in the shop orders admin screen.
 *
 * @param string $column The ID of the current column being processed.
 */
add_action('manage_shop_order_posts_custom_column', 'shipbubble_shipping_status_column_content');
function shipbubble_shipping_status_column_content($column)
{
	global $post;

	// Verify that the current column is the 'sb_shipping_status' column.
	if ('sb_shipping_status' === $column) {
		// Get the WooCommerce Order object for the current post ID.
		$order = new WC_Order($post->ID);

		// Check if the order uses the Shipbubble shipping method.
		if ($order->has_shipping_method(SHIPBUBBLE_ID)) {
			// Check if this order is marked as local pickup.
			if (shipbubble_get_order_meta($post->ID, 'shipbubble_local_pickup')) {
				echo '<span class="dashicons dashicons-minus" title="Not processed via Shipbubble"></span>';
			} else {
				// Retrieve the Shipbubble tracking status for the order.
				$status = shipbubble_get_order_meta($post->ID, 'shipbubble_tracking_status');

				// If there is a shipping status, display it.
				if (!empty($status)) {
					echo shipbubble_shipment_status_label($status);
				}
				// If the order is in a bad status (e.g., canceled, failed), show 'No shipment initiated'.
                elseif (in_array($order->get_status(), SHIPBUBBLE_WC_BAD_ORDER_STATUS_ARR)) {
					echo '<mark class="order-status status-on-hold">
                            <span>No shipment initiated</span>
                          </mark>';
				}
				// Otherwise, display 'No shipment yet'.
				else {
					echo '<mark class="order-status status-on-hold">
                            <span>No shipment yet</span>
                          </mark>';
				}
			}
		}
		// If the order does not use the Shipbubble shipping method, display a dashicon with a note.
		else {
			echo '<span class="dashicons dashicons-minus" title="Not processed via Shipbubble"></span>';
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

        $shipbubbleOrderId = shipbubble_get_order_meta($post->ID, 'shipbubble_order_id', true) ?? '';

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
            shipbubble_update_order_meta($post->ID, 'shipbubble_tracking_status', strtolower($latestPackageStatus->status));

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

/**
 * Displays the Shipbubble wallet balance for a given order.
 *
 * This function checks the order status and shipping method, retrieves the wallet balance from Shipbubble,
 * and outputs the balance or the Shipbubble Order ID if applicable.
 *
 * @param WC_Order $order The WooCommerce order object.
 *
 * @return void
 */
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

	if (shipbubble_get_order_meta($order->get_id(), 'shipbubble_local_pickup')) return;


    $shipment_details = maybe_unserialize(shipbubble_get_order_meta($order->get_id(), 'shipbubble_shipment_details'));

    if (empty($shipment_details) || !count($shipment_details)) {
        return;
    }

    $shipbubbleOrderId = shipbubble_get_order_meta($order->get_id(), 'shipbubble_order_id', true);
    if (strlen($shipbubbleOrderId) < 1) {
        $response = shipbubble_get_wallet_balance(shipbubble_get_token());

        if (isset($response->response_code) && $response->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
            $balance = $response->data->balance;
        }

        echo '<input type="hidden" id="shipbubble_shipping_cost" name="shipbubble_shipping_cost" value="' . esc_html((float) $order->get_shipping_total()) . '"/>';

        echo '<input type="hidden" id="shipbubble_wallet_balance" name="shipbubble_wallet_balance" value="' . esc_html((float) $balance) . '"/>';

        echo '<p><strong>' . __('Shipbubble Wallet Balance:') . '</strong><br> <strong>' . esc_html($currency) . esc_html(number_format($balance, 2)) . '</strong></p>';
    } else {
        echo '<p><strong>' . __('Shipbubble Order ID:') . '</strong><br> ' . esc_html($shipbubbleOrderId) . '</p>';
    }
}
