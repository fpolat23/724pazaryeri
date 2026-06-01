<?php
/**
 * Plugin Name: WC Category Migrator
 * Plugin URI:  https://724pazaryeri.com
 * Description: WooCommerce kategori hiyerarşisini ve kategori resimlerini XML ile aktarır/alır. Arka planda çalışır.
 * Version:     1.1.0
 * Author:      724 Pazaryeri
 * Author URI:  https://724pazaryeri.com
 * Text Domain: wc-category-migrator
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:      8.9
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_CAT_MIGRATOR_VERSION', '1.1.0' );
define( 'WC_CAT_MIGRATOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_CAT_MIGRATOR_URL', plugin_dir_url( __FILE__ ) );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

register_activation_hook( __FILE__, function () {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-job-manager.php';
	WC_Cat_Job_Manager::create_table();
} );

add_action( 'plugins_loaded', function () {
	if ( ! ( class_exists( 'WooCommerce' ) || function_exists( 'WC' ) ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>WC Category Migrator: WooCommerce aktif değil!</p></div>';
		} );
		return;
	}

	require_once WC_CAT_MIGRATOR_PATH . 'includes/class-job-manager.php';
	require_once WC_CAT_MIGRATOR_PATH . 'includes/class-exporter.php';
	require_once WC_CAT_MIGRATOR_PATH . 'includes/class-importer.php';
	require_once WC_CAT_MIGRATOR_PATH . 'includes/class-background-importer.php';
	require_once WC_CAT_MIGRATOR_PATH . 'admin/class-admin.php';

	WC_Cat_Job_Manager::create_table();
	WC_Cat_Background_Importer::init();
	WC_Cat_Migrator_Admin::init();
} );
