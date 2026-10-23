<?php
/**
 * Tema Piedinifini.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'piedinifini',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);
} );
