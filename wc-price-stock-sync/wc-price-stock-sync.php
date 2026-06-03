<?php
/**
 * Plugin Name: WC Price & Stock Sync
 * Plugin URI:  https://724pazaryeri.com
 * Description: Bayi paneli girişiyle kaynak sitelerin ürün sayfalarını parse ederek SKU/ad eşleşmesiyle fiyat ve stok günceller.
 * Version:     2.1.0
 * Author:      724 Pazaryeri
 * Author URI:  https://724pazaryeri.com
 * Requires at least: 5.9
 * Requires PHP:      8.0
 * WC requires at least: 6.0
 * WC tested up to:      9.9
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_PSS_VERSION', '2.1.0' );
define( 'WC_PSS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_PSS_URL', plugin_dir_url( __FILE__ ) );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

register_activation_hook( __FILE__, function () {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-job-manager.php';
	WC_PSS_Job_Manager::create_table();
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) && ! function_exists( 'WC' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>WC Price &amp; Stock Sync: WooCommerce aktif değil!</p></div>';
		} );
		return;
	}

	require_once WC_PSS_PATH . 'includes/class-job-manager.php';
	require_once WC_PSS_PATH . 'includes/class-source-manager.php';
	require_once WC_PSS_PATH . 'includes/class-http-client.php';
	require_once WC_PSS_PATH . 'includes/class-scraper.php';
	require_once WC_PSS_PATH . 'includes/class-updater.php';
	require_once WC_PSS_PATH . 'includes/class-background-processor.php';
	require_once WC_PSS_PATH . 'admin/class-admin.php';

	WC_PSS_Job_Manager::create_table();
	WC_PSS_Background_Processor::init();
	WC_PSS_Admin::init();
} );
