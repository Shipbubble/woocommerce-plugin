<?php // Core Methods
use \Yay_Currency\Helpers\YayCurrencyHelper;
use Automattic\WooCommerce\Utilities\OrderUtil;

function shipbubble_get_token(): string
{
    $options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

	if ('yes' === $options['live_mode']) {
		return isset($options['live_api_key']) ? sanitize_text_field($options['live_api_key']) : '';
	}

	return isset($options['sandbox_api_key']) ? sanitize_text_field($options['sandbox_api_key']) : '';
}

function shipbubble_get_keys() {
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
	return array(
		'live_api_key' => isset($options['live_api_key']) ? sanitize_text_field($options['live_api_key']) : '',
		'sandbox_api_key' => isset($options['sandbox_api_key']) ? sanitize_text_field($options['sandbox_api_key']) : ''
	);
}

function shipbubble_is_live_mode() {
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

	if (!isset($options['live_mode'])) {
		$options['live_mode'] = 'yes';
		update_option(WC_SHIPBUBBLE_ID, $options);
	}

	return 'yes' === $options['live_mode'];
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

    foreach ($cart as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $productItem = wc_get_product($product_id);

        if ($productItem && $productItem->is_virtual()) {
			// Your custom actions for virtual products in the cart
            // skip
            continue;
        }

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

function shipbubble_set_package_dimensions($package_weight)
{
    $shipbubble_dimensions = shipbubble_package_dimensions();
    $dimensions = array();
    $default = array(
        'length'                => 56,
        'width'                 => 50,
        'height'                => 45,
        'max_weight'            => 40
    );

    $filtered_sizes = array_filter($shipbubble_dimensions, function ($sizeArray) use ($package_weight) {
        return $package_weight <= $sizeArray['max_weight'];
    });

    if (count($filtered_sizes)) {
        $dimensions = array_shift($filtered_sizes);
    } else {
        $dimensions = $default;
    }

    return $dimensions;
}

function shipbubble_package_dimensions(): array
{
    return array(
        array(
            'length'                => 25,
            'width'                 => 35,
            'height'                => 2,
            'max_weight'            => 0.5
        ),
        array(
            'length'                => 35,
            'width'                 => 18,
            'height'                => 10,
            'max_weight'            => 1.5
        ),
        array(
            'length'                => 34,
            'width'                 => 32,
            'height'                => 10,
            'max_weight'            => 3
        ),
        array(
            'length'                => 34,
            'width'                 => 32,
            'height'                => 18,
            'max_weight'            => 7
        ),
        array(
            'length'                => 34,
            'width'                 => 32,
            'height'                => 34,
            'max_weight'            => 12
        ),
        array(
            'length'                => 42,
            'width'                 => 36,
            'height'                => 37,
            'max_weight'            => 18
        ),
        array(
            'length'                => 48,
            'width'                 => 40,
            'height'                => 39,
            'max_weight'            => 25
        ),
    );
}

/**
 * Processes shipping rates based on the address code, products, and service codes.
 *
 * @param string $addressCode  The address code used to fetch the shipping rates.
 * @param array  $products     The list of products for which shipping rates are to be calculated.
 * @param array  $serviceCodes Optional. An array of specific service codes for which rates should be calculated. Defaults to an empty array.
 *
 * @return array An array containing the shipping rates, request token, extra charges, couriers, and the currency symbol. In case of errors, it will return an error message.
 */
function shipbubble_process_shipping_rates($addressCode, $products, $serviceCodes = array())
{
    $options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

    // $courier_price_type = isset( $options['shipping_price'] ) ? sanitize_text_field( $options['shipping_price'] ) : 'default';

    $extra_charges = isset($options['extra_charges']) ? sanitize_text_field($options['extra_charges']) : '0';

    $rates = array();

    $response = shipbubble_get_shipping_rates($addressCode, $products, $serviceCodes);

    if (isset($response->response_code) && $response->response_code == SHIPBUBBLE_RESPONSE_IS_OK) {
        $data = $response->data;
        $rates['request_token'] = $data->request_token;
        $rates['extra_charges'] = $extra_charges;

        $rates['rate'] = 'default';
        $rates['couriers'] = $data->couriers;

        // currency code
        $currency_code = $data->couriers[0]->rate_card_currency;

        // Get the currency symbol for the specified currency code
         $currency_symbol = get_woocommerce_currency_symbol($currency_code);
        // $rates['currency_symbol'] = $currency_symbol;
        
        $rates['currency_symbol'] = $currency_symbol;
    } else {
        if (isset($response->error)) {
            $rates['error'] = $response->error[0];
        } elseif (isset($response->message)) {
            $rates['error'] = $response->message;
        } else {
            $rates['error'] = 'unable to fetch rates';
        }
    }

    return $rates;
}

function shipbubble_regenerate_rate_token($order, $shipment, $reason = '')
{
    $countryObject = WC()->countries;

    $name = $order->data['shipping']['first_name'] . ' ' . $order->data['shipping']['last_name'];
    $address = $order->data['shipping']['address_1'] . ', ' . $order->data['shipping']['city'] . ', ' . $countryObject->states[$order->data['shipping']['country']][$order->data['shipping']['state']] . ', ' . $countryObject->countries[$order->data['shipping']['country']];

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
            $product = wc_get_product($value['product_id']);

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
        $response = shipbubble_process_shipping_rates($addressCode, $items, [$shipment->service_code]);

        if (count($response) && isset($response['couriers']) && count($response['couriers'])) 
        {
            $rates['request_token'] = $response['request_token'];

            // filter request
            $filtered_courier = array_filter($response['couriers'], function($courier) use($shipment)
            {
                return $shipment->service_code == $courier->service_code && $shipment->courier_id == $courier->courier_id;
            });

            if (count($filtered_courier)) 
            {
                $rates['service_code'] = $filtered_courier[0]->service_code;
                $rates['courier_id'] = $filtered_courier[0]->courier_id;
                $rates['courier_name'] = $filtered_courier[0]->courier_name;
                $rates['shipment_cost'] = (string) $filtered_courier[0]->total;
            }
            else
            {
                $rates['service_code'] = $response['couriers'][0]->service_code;
                $rates['courier_id'] = $response['couriers'][0]->courier_id;
                $rates['courier_name'] = $response['couriers'][0]->courier_name;
                $rates['shipment_cost'] = (string) $response['couriers'][0]->total;
            }

            // first data
            $rates['request_datetime'] = $shipment->request_datetime;
            $rates['order_request_time'] = $shipment->order_request_time;

            // new data
            $rates['regenerated_token_time'] = date('Y-m-d H:i:s');
            $rates['token_regenerate_reason'] = $reason;
        }
        else 
        {
            if (isset($response['error']))
            {
                $rates['errors'] = $response['error'];
            }
        }
    }
    else 
    {
        if (isset($addressResponse->status) && $addressResponse->status == 'failed')
        {
            $rates['errors'] = isset($addressResponse->message) ? $addressResponse->message : '';
        }
    }
    return $rates;
}


function shipbubble_shipment_status_label($status)
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
    $state = $countryObject->states[$countryLabel][$stateLabel];
    $country = $countryObject->countries[$countryLabel];

    return $address . ' ' . $city . ' ' . $state . ' ' . $country;
}

function sb_compare_addresses(string $address1, string $address2)
{
    return trim(strtolower($address1)) == trim(strtolower($address2));
}

function shipbubble_data_is_serialized($str) {
    return is_string($str) && ($str == serialize(false) || @unserialize($str) !== false);
}

function shipbubble_get_currency_code() {

	$currency_code = '';

	$plugins = array(
		array(
			'check' => function() { return class_exists('YITH_WCMCS_Currency_Handler'); },
			'get_currency' => function() { return yith_wcmcs_get_current_currency_id(); }
		),
		array(
			'check' => function() { return is_plugin_active('yaycurrency/yay-currency.php') && class_exists('Yay_Currency\Helpers\YayCurrencyHelper'); },
			'get_currency' => function() {
				$currency_data = YayCurrencyHelper::get_current_currency();
				return is_array($currency_data) ? isset($currency_data['currency']) ? $currency_data['currency'] : '' : '';
			}
		),
	);

	foreach ($plugins as $plugin) {
		if ($plugin['check']()) {
			$currency_code = $plugin['get_currency']();
			break;
		}
	}
	if (empty($currency_code)) {
		$currency_code = get_woocommerce_currency();
	}


	return empty($currency_code) ? 'NGN' : $currency_code;
}

function shipbubble_live_address_validated() {
	$shipbubble_init = get_option(SHIPBUBBLE_INIT);
	return true === ($shipbubble_init[SHIPBUBBLE_ADDRESS_VALIDATED] ?? false);
}

function shipbubble_sandbox_address_validated() {
	$shipbubble_init = get_option(SHIPBUBBLE_INIT);
	return true === ($shipbubble_init[SHIPBUBBLE_SANDBOX_ADDRESS_VALIDATED] ?? false);
}

function shipbubble_get_address_code() {
	if (shipbubble_is_live_mode()) {
		return get_option(WC_SHIPBUBBLE_ID)['address_code'] ?? '';
	} else {
		return get_option(WC_SHIPBUBBLE_ID)['sandbox_address_code'] ?? '';
	}
}

function shipbubble_switch_mode($mode) {
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());
	$options['live_mode'] = $mode;
	update_option(WC_SHIPBUBBLE_ID, $options);
}

function generate_shipbubble_notice() {
	$shipbubble_init = get_option(SHIPBUBBLE_INIT);
	$message = '';
	$notice_type = 'notice-error';

	$link = '<a href="admin.php?page=wc-settings&tab=shipping&section=shipbubble_shipping_services" style="text-decoration: underline; font-weight: bold;">%s</a>';

	if (false == $shipbubble_init['account_status']) {
		$message = sprintf(
			__('Please %s your Shipbubble API Keys to start shipping.', 'shipbubble'),
			sprintf($link, __('setup', 'shipbubble'))
		);
	}
	elseif (!shipbubble_is_live_mode()) {
		if (shipbubble_sandbox_address_validated()) {
			$message = __('Shipbubble test mode is active, please do not use for a live site', 'shipbubble');
			$notice_type = 'notice-info';
		} else {
			$message = sprintf(
					__('Please complete your Shipbubble %s.', 'shipbubble'),
					sprintf($link, __('setup', 'shipbubble'))
				) . ' '. __('Validate your address and start shipping with ease.');
		}
	} elseif (shipbubble_is_live_mode() && !shipbubble_live_address_validated()) {
		$message = sprintf(
				__('Please complete your Shipbubble %s.', 'shipbubble'),
				sprintf($link, __('setup', 'shipbubble'))
			) . ' '. __('Validate your address and start shipping with ease.');
	}

	if (!empty($message)) {
		$logo_url = SHIPBUBBLE_LOGO_URL;
		ob_start();
		?>
		<div id="shipbubble_notice_div" class="notice <?php echo $notice_type; ?> is-dismissible" style="padding: 15px; background-color: #f1f1f1;">
			<p>
				<img src="<?php echo esc_url($logo_url); ?>" alt="<?php esc_attr_e('Shipbubble Logo', 'shipbubble'); ?>" style="max-width: 100px; height: auto;">
			</p>
			<p style="font-size: 14px; color: #333;">
				<?php echo $message; ?>
			</p>
		</div>
		<?php
		return ob_get_clean();
	} else {
		return '';
	}
}


function shipbubble_is_local_pickup_active() {
	return shipbubble_is_option_active('local_pickup');
}

/**
 * Update order meta data for a given order.
 *
 * This function retrieves an order by its ID, updates its meta data, and saves the order.
 *
 * @param int    $order_id  The ID of the order to update.
 * @param string $meta_key  The meta key to update or add.
 * @param mixed  $meta_value The meta value to set for the given meta key.
 *
 * @return bool Returns true if the order was updated successfully, false otherwise.
 */
function shipbubble_update_order_meta($order_id, $meta_key, $meta_value) {
	$order = wc_get_order($order_id);

    if (!$order) return false;

    $order->update_meta_data($meta_key, $meta_value);
    $order->save();

    return true;
}

/**
 * Retrieve order meta data for a given order.
 *
 * This function fetches meta data for an order using WooCommerce's order meta system.
 * If the order is not migrated, it falls back to using the post meta system.
 *
 * @param int    $order_id The ID of the order to retrieve meta data from.
 * @param string $meta_key The meta key to retrieve the value for.
 *
 * @return mixed The meta value if found, or an empty string if the order does not exist or the meta data is not available.
 */
function shipbubble_get_order_meta($order_id, $meta_key) {
	$order = wc_get_order($order_id);

    if (!$order) return '';
    if (shipbubble_is_order_migrated($order)) {
        $metadata = $order->get_meta($meta_key);
    } else {
        $metadata = get_post_meta($order_id, $meta_key, true);
    }

    return $metadata;
}

/**
 * Check if a WooCommerce order has been migrated based on the database update time.
 *
 * This function compares the order's creation date with the `shipbubble_db_update_time` option
 * to determine if the order was created after the migration date, thus marking it as migrated.
 *
 * @param WC_Order $order The WooCommerce order object to check.
 *
 * @return bool Returns true if the order is considered migrated, false otherwise.
 */
function shipbubble_is_order_migrated($order) {
	$updated_time = get_option('shipbubble_db_update_time');

    if (!$updated_time) return false;

	// Get the order creation date (WooCommerce stores this in the order object)
	$order_date = $order->get_date_created(); // Returns a DateTime object

	// Convert the order date to a timestamp for comparison
	$order_timestamp = $order_date ? $order_date->getTimestamp() : 0;

	// Compare the order timestamp with the updated time
	if ($order_timestamp >= $updated_time) {
		return true; // Order is considered migrated
	} else {
		return false; // Order is not migrated
	}
}

/**
 * Checks if a specific Shipbubble option is active.
 *
 * @param string $option The key of the option to check.
 * @return bool Returns true if the option is set to 'yes', false otherwise.
 */
function shipbubble_is_option_active($option) {
	// Retrieve the Shipbubble options or the default options if not set.
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

	// If the specified option is not set, default it to 'no' and update the option.
	if (!isset($options[$option])) {
		$options[$option] = 'no';
		update_option(WC_SHIPBUBBLE_ID, $options);
	}

	// Return true if the option is set to 'yes', false otherwise.
	return 'yes' === $options[$option];
}

function shipbubble_get_option($key) {
	$options = get_option(WC_SHIPBUBBLE_ID, shipbubble_wc_options_default());

    return $options[$key] ?? '';
}