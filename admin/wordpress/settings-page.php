<?php  // ShipBubble - Wordpress Settings Page

    // exit if file is called directly
    if ( ! defined( 'ABSPATH' ) ) 
    {
        exit;
    }

    // display the plugin settings page
    function shipbubble_display_settings_page() {
    
        // check if user is allowed access
        if ( ! current_user_can( 'manage_options' ) ) return;
        
        ?>
        
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form id="shipbubble_options_form" action="options.php" method="post">
                
                <?php
                
                    // output security fields
                    settings_fields( 'shipbubble_field_options' );
                    
                    // output setting sections
                    do_settings_sections( 'shipbubble' );
                    
                    // submit button
                    submit_button('Connect Account');
                
                ?>
                
            </form>
        </div>
    
    <?php
    
    }
