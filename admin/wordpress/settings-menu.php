<?php // ShipBubble - Wordpress Admin Menu


    // exit if file is called directly
    if ( ! defined( 'ABSPATH' ) ) 
    {
        exit;
    }


    // add top-level administrative menu
    function shipbubble_add_toplevel_menu() {
        
        /* 
        
        add_menu_page(
            string   $page_title, 
            string   $menu_title, 
            string   $capability, 
            string   $menu_slug, 
            callable $function = '', 
            string   $icon_url = '', 
            int      $position = null 
        )
        
        */
        
        add_menu_page(
            'Connect your Shipbubble Account',
            'Shipbubble',
            'manage_options',
            'shipbubble',
            'shipbubble_display_settings_page',
            'dashicons-admin-generic',
            null
        );
        
    }
    
    add_action( 'admin_menu', 'shipbubble_add_toplevel_menu' );