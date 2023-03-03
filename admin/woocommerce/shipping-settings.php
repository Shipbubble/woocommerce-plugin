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
                        $categories_options = shipbubble_get_order_categories();
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
                            'store_category' => array(
                                'title'         => __( 'Store Categories', 'woocommerce' ),
                                'type'             => 'select',
                                'description'     => __( 'Store Categories.', 'woocommerce' ),
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