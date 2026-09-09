<div id="shipbubble-settings-local-pickup" class="shipbubble-tab-content" style="display:none;">
    <form id="shipbubble-local-pickup-form">
		<?php
		settings_fields('shipbubble_local_pickup');
		do_settings_sections('shipbubble_local_pickup');
        ?>
        <div class="shipbubble-settings">
            <table class="form-table">
            <tr>
                <th scope="row"><label for="shipbubble_local_pickup">Activate Local Pickup</label></th>
                <td>
                    <input type="checkbox" name="shipbubble_local_pickup" id="shipbubble_local_pickup" value="1" <?php checked(shipbubble_is_local_pickup_active()); ?>>
                    <label for="shipbubble_local_pickup">Activate to use</label>
                    <p class="description">Activate Local pickup on checkout.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="shipbubble_local_pickup_text">Local Pickup Text</label></th>
                <td>
                    <?php
                    wp_editor(
                        $options['local_pickup_text'] ?? '',
                        'shipbubble_local_pickup_text',
                        array(
                            'textarea_name'    => 'shipbubble_local_pickup_text',
                            'textarea_rows'    => 3,
                            'teeny'            => true,
                            'media_buttons'    => false,
                            'drag_drop_upload' => false,
                            'wpautop'          => false,
                            'tinymce'          => array(
                                'toolbar1'          => 'bold,italic,link,unlink',
                                'toolbar2'          => '',
                                'menubar'           => false,
                                'statusbar'         => false,
                                'branding'          => false,
                                'resize'            => false,
                                'forced_root_block' => false,
                                'force_br_newlines' => true,
                                'force_p_newlines'  => false,
                            ),
                            'quicktags' => array( 'buttons' => 'strong,em,link,close' ),
                        )
                    );
                    ?>
                    <p class="description">Customize the text displayed for in-store pickup option. Basic formatting (bold, italic, links) is supported.</p>
                </td>
            </tr>
        </table>
        </div>
        <div class="shipbubble-actions">
            <?php submit_button(); ?>
        </div>
    </form>
</div>