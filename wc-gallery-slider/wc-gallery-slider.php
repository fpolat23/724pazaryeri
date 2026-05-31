<?php
/**
 * Plugin Name: WC Gallery Slider
 * Plugin URI:  https://724pazaryeri.com
 * Description: WooCommerce ürün detay sayfasında otomatik kayan, dokunmatik destekli galeri ve lightbox.
 * Version:     1.0.0
 * Author:      724 Pazaryeri
 * Author URI:  https://724pazaryeri.com
 * Text Domain: wc-gallery-slider
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:      8.9
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_GALLERY_VERSION', '1.0.0' );
define( 'WC_GALLERY_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_GALLERY_URL', plugin_dir_url( __FILE__ ) );

// HPOS uyumluluğu
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) return;

	// WooCommerce ürün görseli şablonunu değiştir
	add_filter( 'woocommerce_locate_template', function ( $template, $template_name ) {
		if ( $template_name === 'single-product/product-image.php' ) {
			$override = WC_GALLERY_PATH . 'templates/product-image.php';
			if ( file_exists( $override ) ) return $override;
		}
		return $template;
	}, 10, 2 );

	add_action( 'wp_enqueue_scripts', function () {
		if ( ! is_product() && ! is_singular( 'product' ) ) return;

		// WooCommerce'in varsayılan zoom/photoswipe scriptlerini kaldır
		add_action( 'wp_enqueue_scripts', function () {
			wp_dequeue_script( 'zoom' );
			wp_dequeue_script( 'photoswipe' );
			wp_dequeue_script( 'photoswipe-ui-default' );
			wp_dequeue_script( 'wc-photoswipe' );
			wp_dequeue_style( 'photoswipe' );
			wp_dequeue_style( 'photoswipe-default-skin' );
		}, 99 );

		// Swiper.js (CDN)
		wp_enqueue_style(
			'swiper',
			'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
			[],
			'11'
		);
		wp_enqueue_script(
			'swiper',
			'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
			[],
			'11',
			true
		);

		// Eklenti stilleri ve scriptleri
		wp_enqueue_style(
			'wc-gallery-slider',
			WC_GALLERY_URL . 'assets/css/gallery.css',
			[ 'swiper' ],
			WC_GALLERY_VERSION
		);
		wp_enqueue_script(
			'wc-gallery-slider',
			WC_GALLERY_URL . 'assets/js/gallery.js',
			[ 'swiper' ],
			WC_GALLERY_VERSION,
			true
		);
	} );
} );
