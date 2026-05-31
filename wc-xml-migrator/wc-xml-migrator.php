<?php
/**
 * Plugin Name: WC XML Migrator
 * Plugin URI:  https://724pazaryeri.com
 * Description: WooCommerce ürünlerini XML formatında dışa ve içe aktarır. Tarayıcı kapatılsa bile arka planda çalışır.
 * Version:     1.1.0
 * Author:      724 Pazaryeri
 * Author URI:  https://724pazaryeri.com
 * Text Domain: wc-xml-migrator
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:      8.9
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_XML_MIGRATOR_VERSION', '1.1.0' );
define( 'WC_XML_MIGRATOR_FILE', __FILE__ );
define( 'WC_XML_MIGRATOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_XML_MIGRATOR_URL', plugin_dir_url( __FILE__ ) );

// WooCommerce HPOS uyumluluğu
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

register_activation_hook( __FILE__, function () {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-job-manager.php';
	WC_XML_Job_Manager::create_table();
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'WC XML Migrator: WooCommerce aktif değil!', 'wc-xml-migrator' ) .
				'</p></div>';
		} );
		return;
	}

	load_plugin_textdomain( 'wc-xml-migrator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	require_once WC_XML_MIGRATOR_PATH . 'includes/class-job-manager.php';
	require_once WC_XML_MIGRATOR_PATH . 'includes/class-xml-exporter.php';
	require_once WC_XML_MIGRATOR_PATH . 'includes/class-xml-importer.php';
	require_once WC_XML_MIGRATOR_PATH . 'includes/class-background-exporter.php';
	require_once WC_XML_MIGRATOR_PATH . 'includes/class-background-importer.php';
	require_once WC_XML_MIGRATOR_PATH . 'admin/class-admin-menu.php';

	// DB tablosunu yoksa oluştur (güncelleme senaryosu)
	WC_XML_Job_Manager::create_table();

	// Action Scheduler kancaları
	WC_XML_Background_Exporter::init();
	WC_XML_Background_Importer::init();

	WC_XML_Migrator_Admin::init();
} );
