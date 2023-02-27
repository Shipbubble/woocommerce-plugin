<?php // ShipBubble - Settings Callback

    if ( ! defined( 'ABSPATH' ) ) 
    {
        exit;
    }


    // callback: login section
    function shipbubble_callback_section_login() 
    {
        
        echo '<p>These settings enable you to authenticate the plugin for use.</p>';
        
    }

    // callback: text field
    function shipbubble_callback_field_text( $args ) 
    {

        $options = get_option( 'shipbubble_options', shipbubble_options_default() );
        
        $id    = isset( $args['id'] )    ? $args['id']    : '';
        $label = isset( $args['label'] ) ? $args['label'] : '';
        $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
        
        $value = isset( $options[$id] ) ? sanitize_text_field( $options[$id] ) : '';
        
        echo '<input id="shipbubble_options_'. $id .'" name="shipbubble_options['. $id .']" type="text" size="40" value="'. $value .'" placeholder="' . $placeholder . '"><br />';
        echo '<label for="shipbubble_options_'. $id .'">'. $label .'</label><br />';
        echo '<span class="form_note_' . $id .'"></span>';

    }