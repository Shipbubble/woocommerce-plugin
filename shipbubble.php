<?php 

	/**
	 * Plugin Name:  ShipBubble
	 * Description:  Plugin for enabling & managing logistics & deliveries for orders on eCommerce platforms
	 * Plugin URI:   https://profiles.wordpress.org/oghenemavo
	 * Contributors: oghenemavo
	 * Author:       Mavi Onogomuho
	 * Author URI:   https://linkedin.com/in/mavi-onogomuho
	 * Tags:         delivery, logistics, eCommerce
	 * Version:      1.0
	 * Stable tag:   1.0
	 * Requires at least: 5.6
	 * Tested up to:      8.1
	 * Text Domain:  shipbubble
	 * Domain Path:  /languages
	 * License:      GPL v3 or later
	 * License URI:  https://www.gnu.org/licenses/gpl-3.0.txt
	 */

	// exit if file is called directly
	if ( ! defined( 'ABSPATH' ) ) 
	{
		exit;
	}

	// if  admin area
	if ( is_admin() ) 
	{
		// include dependencies
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/settings-menu.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/settings-page.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/settings-register.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/settings-callback.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/wordpress/async-validate-auth.php';
		
		// Woocommerce
		// require_once plugin_dir_path( __FILE__ ) . 'admin/woocommerce/shipping-settings.php';
		require_once plugin_dir_path( __FILE__ ) . 'admin/woocommerce/async-create-shipment.php';
	}

	// includes
	require_once plugin_dir_path( __FILE__ ) . 'includes/constants.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/endpoints.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/core-methods.php';

	// public
	require_once plugin_dir_path( __FILE__ ) . 'public/async-checkout-couriers.php';


	// action on activation
	function shipbubble_on_activation() 
	{
		if ( ! current_user_can( 'activate_plugins' ) ) return;

		if (get_option('shipbubble_init')) {
			$data = array('initialized' => true, 'account_status' => false);
			update_option( 'shipbubble_init', $data );
		} else {
			$data = array('initialized' => true, 'account_status' => false);
			add_option( 'shipbubble_init', $data );
		}

	}

	register_activation_hook( __FILE__, 'shipbubble_on_activation' );


	// action on deactivation
	function shipbubble_on_deactivation() 
	{
		if ( ! current_user_can( 'activate_plugins' ) ) return;
		
		$data = array('initialized' => false, 'account_status' => false);
		update_option( 'shipbubble_init', $data );
	}

	register_deactivation_hook( __FILE__, 'shipbubble_on_deactivation' );

	// default plugin options
	function shipbubble_options_default(): array 
	{
		return array(
			'shipbubble_api_key'     	=> '',
		);
	}

	function shipbubble_wc_options_default(): array 
	{
		return array(
			'extra_charges' => '0',
			'courier_list' =>  array('all'),
			'shipping_price' => 'default',
		);
	}
	
    /**
     * Check if WooCommerce is active
     */
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) 
    {

        function shipbubble_shipping_service_init() 
        {
            if ( ! class_exists( 'WC_SHIPBUBBLE_SHIPPING_METHOD' ) )  {
                class WC_SHIPBUBBLE_SHIPPING_METHOD extends WC_Shipping_Method 
                {
                    /**
                     * Constructor for your shipping class
                     *
                     * @access public
                     * @return void
                     */
                    public function __construct() 
                    {
                        $this->id                 = SHIPBUBBLE_ID; // Id for your shipping method. Should be uunique.
                        $this->method_title       = __( 'Shipbubble' );  // Title shown in admin
                        $this->method_description = __( 'Ship without limits ! We make e-commerce shipping quicker, easier, and more affordable.' ); // Description shown in admin

                        // Define user set variables
                        $this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled
                        $this->title              = "Shipbubble"; // This can be added as an setting but for this example its forced.

                        $this->init();
                    }

                    /**
                     * Init your settings
                     *
                     * @access public
                     * @return void
                     */
                    public function init() 
                    {
                        // Load the settings API
                        $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
                        $this->init_settings(); // This is part of the settings API. Loads settings you previously init.

                        // Save settings in admin if you have any defined
                        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                    }

                    public function init_form_fields()
                    {
                        $countryObject = WC()->countries;
                        $country_state = explode(':', get_option( 'woocommerce_default_country' ));
                        $streetAddress = trim(get_option( 'woocommerce_store_address' ));
                        
                        if ($streetAddress != $this->get_option('pickup_address')) 
                        {
                            $address = $streetAddress . ' ' . get_option('woocommerce_store_city') . ' ' . $countryObject->states[ $country_state[0] ][ $country_state[1] ] . ' ' . $countryObject->countries[$country_state[0]];

                            $name = $this->get_option( 'store_name' );
                            $phone = $this->get_option( 'store_phone' );
                            $email = get_option('admin_email');
                            
                            $response = shipbubble_validate_address($name, $email, $phone, $address);
                            
                            if ($response->status == 'success') 
                            {
                                $this->update_option('pickup_address', $streetAddress);
                                $this->update_option('address_code', $response->data->address_code);
                            } else {
                                $output = '<div id="message" class="updated woocommerce-message">
                                    <a class="woocommerce-message-close notice-dismiss" href="/flagcommerce/wp-admin/admin.php?page=wc-settings&amp;tab=shipping&amp;section=shipbubble_shipping_services&amp;wc-hide-notice=no_secure_connection&amp;_wc_notice_nonce=0fda8979b4">Dismiss</a>
                                
                                    <p>' . $response->message . 'to generate address code. <br>
                                    </p>
                                </div>';
                                echo $output;
                            }
                        }
                        
                        $courier_options = shipbubble_courier_options();
                        $this->form_fields = array(
                            'store_name' => array(
                                'title'         => __( 'Store Sender Name', 'woocommerce' ),
                                'type'             => 'text',
                                'description'     => __( 'This is the first and last name of the store sender.', 'woocommerce' ),
                                'placeholder'        => __( 'Store Sender Name', 'woocommerce' ),
                            ),
                            'store_phone' => array(
                                'title'         => __( 'Store Phone', 'woocommerce' ),
                                'type'             => 'text',
                                'description'     => __( 'This is the phone number of the store.', 'woocommerce' ),
                            ),
                            'pickup_address' => array(
                                'title'         => __( 'Pickup Address', 'woocommerce' ),
                                'type'             => 'text',
                                'description'     => __( 'This is the address setup for pickup.', 'woocommerce' ),
                                'default'        => __( '', 'woocommerce' ),
                                'custom_attributes' => array('readonly' => 'readonly')
                            ),
                            'address_code' => array(
                                'title'         => __( 'Address Code', 'woocommerce' ),
                                'type'             => 'text',
                                'description'     => __( 'This is the address code setup for pickup (66502255).', 'woocommerce' ),
                                'default'        => __( '0', 'woocommerce' ),
                                'custom_attributes' => array('readonly' => 'readonly')
                            ),
                            'extra_charges' => array(
                                'title'         => __( 'Custom Shipping Extra Charges', 'woocommerce' ),
                                'type'             => 'number',
                                'description'     => __( 'This controls adds a fee to any logistics selected.', 'woocommerce' ),
                                'default'        => __( '0', 'woocommerce' ),
                                'custom_attributes' => array('step' => '0.01', 'min' => '0')
                            ),
                            'shipping_price' => array(
                                'title'         => __( 'Shipping Price', 'woocommerce' ),
                                'type'             => 'select',
                                'description'     => __( 'Shipbubble Courier Price Types.', 'woocommerce' ),
                                'options' => array('default' => 'Default', 'fastest' => 'Fastest', 'cheapest' => 'Cheapest'),
                                'default'        => __( 'default', 'woocommerce' ),
                            ),
                            'courier_list' => array(
                                'title'         => __( 'Courier List', 'woocommerce' ),
                                'type'             => 'multiselect',
                                'description'     => __( 'Onboarded Courier List.', 'woocommerce' ),
                                'options' => $courier_options,
                                'default'        => __( 'all', 'woocommerce' ),
                            ),
                        );
                        
                    }

                    /**
                     * calculate_shipping function.
                     *
                     * @access public
                     * @param array $package optional – multi-dimensional array of cart items to calc shipping for.
                     * @return void
                     */
                    public function calculate_shipping( $package = array() ) 
                    {
                        // This is where you'll add your rates
                        $rate = array(
                            'id'     => $this->id,
                            'label' => $this->title,
                            'cost' => '5000',
                            // 'calc_tax' => 'per_item'
                        );
                        // This will add custom cost to shipping method 

                        // Register the rate
                        $this->add_rate( $rate );
                    }
                }
            }
        }

        add_action( 'woocommerce_shipping_init', 'shipbubble_shipping_service_init' );

        function shipbubble_couriers_methods( $methods ) 
        {
            $methods['shipbubble_shipping_services'] = 'WC_SHIPBUBBLE_SHIPPING_METHOD';
            return $methods;
        }

        add_filter( 'woocommerce_shipping_methods', 'shipbubble_couriers_methods' );
    }

	add_action( 'woocommerce_after_checkout_billing_form', 'shipbubble_courier_list_container' );
	function shipbubble_courier_list_container()
	{
		$container = '
			<div id="courier-section">
				<div id="courier-list"></div>
				<input type="hidden" id="shipbubble_shipment_details" name="shipbubble_shipment_details" value="">
				<input type="hidden" id="shipbubble_selected_courier" name="shipbubble_selected_courier" value="">
				<input type="hidden" id="shipbubble_cost" name="shipbubble_cost" value="">
			</div>
		';

		echo $container;
	}

	add_action( 'wp_footer', 'shipbubble_courier_setup_on_change' );
	function shipbubble_courier_setup_on_change() 
	{
		if ( is_checkout() ) {
			?>

			<script type="text/javascript">
				jQuery( document ).ready(
					function($) {

						$('#courier-section').click(function() {

							const courier_radio_btn = $('input[name="delivery_option"]');
							courier_radio_btn.change(function() {
								if (courier_radio_btn.is(':checked')) {
									const checked_courier = $('input[type="radio"][name="delivery_option"]:checked');
									const courier_name = checked_courier.attr('data-courier_name');
									const total = checked_courier.attr('data-cost');
									const courier_id = checked_courier.attr('data-courier_id');
									const service_code = checked_courier.attr('data-service_code');

									console.log('value is s ', checked_courier.attr('data-request_token'));

									const shipment = {
										request_token: checked_courier.attr('data-request_token'),
										shipment_cost: total,
										courier_id,
										courier_name,
										service_code,
									};
		
									$('#shipbubble_shipment_details').val( JSON.stringify(shipment) );
									$('#shipbubble_selected_courier').val( courier_name );
									$('#shipbubble_cost').val( total );
									// $('#shipbubble_service_code').val( service_code );
									// $('#shipbubble_courier_id').val( courier_id );
		
									jQuery('body').trigger('update_checkout');
		
								}
							});
						});

					}
				);
			</script>

			<?php
		}
	}

	add_filter( 'woocommerce_package_rates', 'shipbubble_change_rates', 100, 2 );
	function shipbubble_change_rates( $rates, $packages ) 
	{
		if ( isset( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $post_data );
		} else {
			$post_data = $_POST;
		}

		// $customer = WC()->customer;
		
		// error_log(print_r($packages, true));

		// if (empty($packages['destination']['address']) ) {
		// 	foreach( $rates as $rate_key => $rate ) {
		// 		if ( 'shipbubble_shipping_services' === $rate->method_id ) {
		// 			unset( $rates[$rate_key] );
		// 		}
		// 	}
		// }

		if ( isset( $post_data['delivery_option'] ) ) {
			foreach( $rates as $rate_key => $rate ) {
				if ( 'shipbubble_shipping_services' === $rate->method_id ) {
					// set rate cost
					if (!empty($post_data['shipbubble_selected_courier']) && strlen($post_data['shipbubble_selected_courier'])) {
						$rates[$rate_key]->label = $post_data['shipbubble_selected_courier'];
					}
					$rates[$rate_key]->cost = $post_data['shipbubble_cost'];
				}
			}
		} else {
			foreach( $rates as $rate_key => $rate ){
				unset($rates[$rate_key]); // Remove
			}
		}
		return $rates;
	}

	function lab_pacakge_cost() 
	{
		
		global $woocommerce;
		
		// $flat_fee    = get_option( 'techiepress_vat_pricing_flat_fee' );
		// $dynamic_fee = get_option( 'techiepress_vat_pricing_dynamic_fee' );
		
		if ( ! $_POST || ( is_admin() && ! is_ajax() ) ) {
			return;
		}
		
		if ( isset( $_POST['post_data'] ) ) {
			parse_str( $_POST['post_data'], $post_data );
		} else {
			$post_data = $_POST;
		}
		
		if ( isset( $post_data['techiepress_vat_cancel'] ) ) {

			// WC()->cart->calculate_shipping();
			$taxable = 250 + ( $woocommerce->cart->cart_contents_total * 1 );
			
			$woocommerce->cart->add_fee( __( 'VAT', 'om-service-widget' ), $taxable );
		}
		
		return;
		
	}
	add_action( 'woocommerce_cart_calculate_fees', 'lab_pacakge_cost');


	// new 
	add_action( 'woocommerce_checkout_update_order_review', 'shipbubble_checkout_update_order_review');
	function shipbubble_checkout_update_order_review($posted_data)
	{
		global $woocommerce;

		$packages = $woocommerce->cart->get_shipping_packages();
		foreach ($packages as $package_key => $package) {
			$session_key = 'shipping_for_package_' . $package_key;
			
			// Clears the session
			// Woocommerce would recalculate and recall your calculate_shipping() function
			$stored_rates = WC()->session->__unset($session_key);
		}
		
	}


	/**
	 * Trial & Error
	 */
	add_action( 'woocommerce_before_checkout_billing_form', 'shipbubble_echo_notice_shipping' );
	
	function shipbubble_echo_notice_shipping() {
	echo '<div class="shipping-notice woocommerce-error" style="display:none">Ensure that you have filled your First & Last Name, Address, City, State and Country.</div>';
	}

	// Disable Shipping methods if not in checkout page
	add_filter( 'woocommerce_package_rates', 'keep_shipping_methods_on_checkout', 100, 2 );
	function keep_shipping_methods_on_checkout( $rates, $package ) {
		if ( ! is_checkout() ) {
			// Loop through shipping methods rates
			foreach( $rates as $rate_key => $rate ){
				unset($rates[$rate_key]); // Remove
			}
		}
		return $rates;
	}

	// Shipping packages
	add_filter( 'woocommerce_shipping_packages', 'keep_shipping_packages_on_checkout', 20, 1 );
	add_filter( 'woocommerce_cart_shipping_packages', 'keep_shipping_packages_on_checkout', 20, 1 );
	function keep_shipping_packages_on_checkout( $packages ) {
		if ( ! is_checkout() ) {
			foreach( $packages as $key => $package ) {
				WC()->session->__unset('shipping_for_package_'. $key); // Remove
				unset($packages[$key]); // Remove
			}
		}
		return $packages;
	}

	// prevent proceed to order if shipping method has not been selected
	add_filter('woocommerce_order_button_html', 'disable_place_order_button_html' );
	function disable_place_order_button_html( $button ) {
		// HERE define your targeted shipping method id
		$targeted_shipping_method = "flat_rate:14";

		// Get the chosen shipping method (if it exist)
		$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
		
		// If the targeted shipping method is selected, we disable the button
		if( in_array( $targeted_shipping_method, $chosen_shipping_methods ) ) {
			$style  = 'style="background:Silver !important; color:white !important; cursor: not-allowed !important; text-align:center;"';
			$text   = apply_filters( 'woocommerce_order_button_text', __( 'Place order', 'woocommerce' ) );
			$button = '<a class="button" '.$style.'>' . $text . '</a>';
		}
		return $button;
	}

	/**
	 * for orders
	 */
		
	//  add_action('woocommerce_admin_order_data_after_order_details', 'my_custom_order_manipulation_function');
	 
	//  function my_custom_order_manipulation_function( $orderID ) {

		// var_dump($orderID);
		// die;
		
	// 	echo '<p>bacon</p>';
	// }
	
	// add_action( 'woocommerce_before_save_order_items', 'so42270384_woocommerce_before_save_order_items', 10, 2 );
	// function so42270384_woocommerce_before_save_order_items( $order_id, $items ) {
	// 	echo $order_id;
	// 	var_dump( $items );
		// die;
	// }

	// add_action( 'woocommerce_after_order_itemmeta', 'so_32457241_before_order_itemmeta', 10, 3 );
	// function so_32457241_before_order_itemmeta( $item_id, $item, $_product ){
		// var_dump($item);
		// var_dump($_product);
	// 	echo '<p>lambast</p>';
	// }

	add_action( 'woocommerce_checkout_update_order_meta', 'shipbubble_checkout_update_order_meta', 10, 1 );
	function shipbubble_checkout_update_order_meta( $order_id ) 
	{

		if( isset( $_POST['shipbubble_shipment_details'] ) || ! empty( $_POST['shipbubble_shipment_details'] ) ) {
		   update_post_meta( $order_id, 'shipbubble_shipment_details', $_POST['shipbubble_shipment_details'] );

		   update_post_meta( $order_id, 'shipbubble_order_id', '' );
		}

	}

	add_action( 'woocommerce_admin_order_data_after_billing_address', 'shibubble_order_data_after_billing_address', 10, 1 );
	function shibubble_order_data_after_billing_address( $order ) 
	{
		// echo '<pre> ' . var_export($order->data['shipping'], true) . '</pre>';
		// die;
		// echo '<pre>' . var_export(wc_get_product( $order->get_items()[9]['product_id'] ), true) . '</pre>';
		// echo '<pre> ' . var_export(json_decode($shipment)->service_code, true) . '</pre>';
		// die;

		$shipbubbleOrderId = get_post_meta( $order->get_id(), 'shipbubble_order_id', true );
		$shipment = get_post_meta( $order->get_id(), 'shipbubble_shipment_details' )[0];

		// $serviceCode = array( 'speedaf-express' ); // test
		$serviceCode = array( json_decode($shipment)->service_code ); // prod
		
		// Check date meets 48hr mark
		$today = new DateTime('now');
		$orderDate = new DateTime( $order->date_created );
		$interval = $orderDate->diff($today);

		if( strlen($shipbubbleOrderId) < 1 && $interval->h > SHIPBUBBLE_REQUEST_TOKEN_EXPIRY) {
			$rates = shipbubble_regenerate_rate_token($order, $serviceCode);
			if (count($rates)) {
				$shipment = json_encode($rates);
				update_post_meta( $order->get_id(), 'shipbubble_shipment_details', $shipment );
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
