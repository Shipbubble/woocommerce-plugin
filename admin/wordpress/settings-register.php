<?php // ShipBubble - Register Settings

    if ( ! defined( 'ABSPATH' ) ) 
    {
        exit;
    }


    // register plugin settings
    function shipbubble_register_settings() {
        
        /*
        
        register_setting( 
            string   $option_group, 
            string   $option_name, 
            callable $sanitize_callback
        );
        
        */
        
        register_setting( 
            'shipbubble_field_options', 
            'shipbubble_options', 
            'shipbubble_callback_validate_options' 
        ); 


        /*
            Add a Section

            add_settings_section( 
                string   $id, 
                string   $title, 
                callable $callback, 
                string   $page
            );
        
        */
        
        add_settings_section( 
            'shipbubble_section_login', 
            '', 
            'shipbubble_callback_section_login', 
            'shipbubble'
        );


        /*
            Add a field

            add_settings_field(
                string   $id,
                string   $title,
                callable $callback,
                string   $page,
                string   $section = 'default',
                array    $args = []
            );

        */

        add_settings_field(
            'shipbubble_api_key',
            'Shipbubble API Key',
            'shipbubble_callback_field_text',
            'shipbubble',
            'shipbubble_section_login',
            [ 'id' => 'shipbubble_api_key', 'label' => '', 'placeholder' => 'sb_prod_xxxxxxxxxxxxxxxxxxxxx' ]
        );

    }
    add_action( 'admin_init', 'shipbubble_register_settings' );
