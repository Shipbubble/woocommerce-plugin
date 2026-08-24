<?php

/**
 * Enqueue Shipbubble admin styles with file-based cache busting.
 *
 * @return void
 */
function shipbubble_enqueue_style_admin() {
	
	/*
		wp_enqueue_style(
			string           $handle,
			string           $src = '',
			array            $deps = array(),
			string|bool|null $ver = false,
			string           $media = 'all'
		)
	*/
	
	$style_path = dirname(__DIR__) . '/css/styles-wc.css';
	$src = plugins_url( 'admin/css/styles-wc.css', dirname( __DIR__, 2 ) . '/shipbubble.php' );
	$version = file_exists($style_path) ? filemtime($style_path) : null;

	wp_enqueue_style( 'shipbubble-admin', $src, array(), $version, 'all' );

}
add_action( 'admin_enqueue_scripts', 'shipbubble_enqueue_style_admin' );
