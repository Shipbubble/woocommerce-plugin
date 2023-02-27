<?php // Core Methods


    function shipbubble_get_token(): string
    {
        $options = get_option( 'shipbubble_options', shipbubble_options_default() );

        return isset( $options['shipbubble_api_key'] ) ? sanitize_text_field( $options['shipbubble_api_key'] ) : '';
    }

    function shipbubble_base_response($status = null, $message = null, $data = null): string
    {
        return json_encode(
            array(
                'status' => $status ?? 'failed',
                'message' => $message ?? 'Unable to complete request, try again later',
                'data' => $data
            )
        );
    }