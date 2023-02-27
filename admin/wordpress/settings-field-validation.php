<?php // ShipBubble - Validate Options Input

    if ( ! defined( 'ABSPATH' ) ) 
    {
        exit;
    }


    // validate plugin settings
    function shipbubble_callback_validate_options($input) 
    {
        // sandbox key
        if ( isset( $input['shipbubble_api_key'] ) ) 
        {	
            $input['shipbubble_api_key'] = sanitize_text_field( $input['shipbubble_api_key'] );
        }

        return $input;
        
    }