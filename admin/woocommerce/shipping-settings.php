<?php // Shipbubble woocommerce shipping settings

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
                        $this->enabled            = $this->get_option('activate_shipbubble', 'no'); // This can be added as an setting but for this example its forced enabled
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

                        $this->display_errors();

                        $response = shipbubble_get_wallet_balance(shipbubble_get_token());
    
                        // Load the settings API
                        if (isset($response->response_code) && $response->response_code == HTTP_RESPONSE_OK) {
                            $this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
                        }

                        $this->init_settings(); // This is part of the settings API. Loads settings you previously init.


                        // Save settings in admin if you have any defined
                        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                    }

                    public function init_form_fields()
                    {
                        $countries_obj   = new WC_Countries();
                        $countries   = $countries_obj->__get('countries');
                        $default_country = $countries_obj->get_base_country();
                        
                        $courier_options = shipbubble_courier_options();
                        $categories_options = shipbubble_get_order_categories();

                        $isEnabled = '<br><div class="sb-activated-not">Not Activated for use</div>';
                        if ($this->get_option('activate_shipbubble', 'no') == 'yes') {
                            $isEnabled = '<br><div class="sb-activated-success">Activated for use</div>';
                        }

                        $this->method_description .= $isEnabled;

                        $this->form_fields = array(
                            'activate_shipbubble' => array(
                                'title'         => __( 'Activate to use', 'woocommerce' ),
                                'type'             => 'checkbox',
                                'description'     => __( 'Activate Shipubble on Checkout.', 'woocommerce' ),
                                'default'        => __( 'no', 'woocommerce' ),
                            ),
                            'sender_name' => array(
                                'title'         => __( 'Sender\'s Name', 'woocommerce' ),
                                'type'             => 'text',
                                'class' => 'address_form_field',
                                'description'     => __( 'This is the first and last name of the sender sender.', 'woocommerce' ),
                                'placeholder'        => __( 'Store Sender Name', 'woocommerce' ),
                            ),
                            'sender_phone' => array(
                                'title'         => __( 'Sender\'s Phone Number', 'woocommerce' ),
                                'type'             => 'text',
                                'class' => 'address_form_field',
                                'description'     => __( 'This is the phone number of the sender.', 'woocommerce' ),
                            ),
                            'sender_email' => array(
                                'title'         => __( 'Sender\'s Email Address', 'woocommerce' ),
                                'type'             => 'text',
                                'class' => 'address_form_field',
                                'description'     => __( 'This is the email of the sender.', 'woocommerce' ),
                            ),
                            'pickup_country' => array(
                                'title'         => __( 'Pickup Country', 'woocommerce' ),
                                'type'             => 'select',
                                'class' => 'address_form_field',
                                'description'     => __( 'Pickup Country.', 'woocommerce' ),
                                'options' => $countries,
                                'default'        => __( $default_country, 'woocommerce' ),
                            ),
                            'pickup_state' => array(
                                'title'         => __( 'Pickup State', 'woocommerce' ),
                                'type'             => 'text',
                                'class' => 'address_form_field',
                                'description'     => __( 'Pickup State.', 'woocommerce' ),
                            ),
                            'pickup_address' => array(
                                'title'         => __( 'Pickup Address', 'woocommerce' ),
                                'type'             => 'text',
                                'class' => 'address_form_field',
                                'description'     => __( 'This is the address setup for pickup.', 'woocommerce' ),
                                'default'        => __( '', 'woocommerce' ),
                                // 'custom_attributes' => array('readonly' => 'readonly')
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
                            'store_category' => array(
                                'title'         => __( 'Store Category', 'woocommerce' ),
                                'type'             => 'select',
                                'description'     => __( 'Store Category.', 'woocommerce' ),
                                'options' => $categories_options,
                                // 'default'        => __( '', 'woocommerce' ),
                            ),
                            'courier_list' => array(
                                'title'         => __( 'Courier List', 'woocommerce' ),
                                'type'             => 'multiselect',
                                'description'     => __( 'Onboarded Courier List.', 'woocommerce' ),
                                'options' => $courier_options,
                                'default'        => __( 'all', 'woocommerce' ),
                            ),
                            'user_can_ship' => array(
                                'title'         => __( 'Customer Can Ship', 'woocommerce' ),
                                'type'             => 'checkbox',
                                'description'     => __( 'Customer Can Ship Orders on Checkout.', 'woocommerce' ),
                                'default'        => __( 'no', 'woocommerce' ),
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