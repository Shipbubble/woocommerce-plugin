<?php

// enqueue admin style
function shipbubble_enqueue_style_public() {
	
	/*
		wp_enqueue_style(
			string           $handle,
			string           $src = '',
			array            $deps = array(),
			string|bool|null $ver = false,
			string           $media = 'all'
		)
	*/
	
	$src = plugins_url( '/css/styles-wc.css', plugin_dir_path( __FILE__ ) );

	wp_enqueue_style( 'shipbubble-public', $src, array(), null, 'all' );

}
add_action( 'wp_enqueue_scripts', 'shipbubble_enqueue_style_public' );