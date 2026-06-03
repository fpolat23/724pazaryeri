<?php
/**
 * Plugin Name: WC REST Migrator
 * Plugin URI:  https://724pazaryeri.com
 * Description: Kaynak WooCommerce sitesinden ürünleri REST API aracılığıyla doğrudan içe aktarır. XML dosyası gerekmez.
 * Version:     1.0.0
 * Author:      724 Pazaryeri
 * Author URI:  https://724pazaryeri.com
 * Text Domain: wc-rest-migrator
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:      9.9
 */

defined( 'ABSPATH' ) || exit;

define( 'WC_REST_MIGRATOR_VERSION', '1.0.0' );
define( 'WC_REST_MIGRATOR_FILE',    __FILE__ );
define( 'WC_REST_MIGRATOR_PATH',    plugin_dir_path( __FILE__ ) );
define( 'WC_REST_MIGRATOR_URL',     plugin_dir_url( __FILE__ ) );

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) return;

	require_once WC_REST_MIGRATOR_PATH . 'includes/class-job-manager.php';
	require_once WC_REST_MIGRATOR_PATH . 'includes/class-api-client.php';
	require_once WC_REST_MIGRATOR_PATH . 'includes/class-product-importer.php';
	require_once WC_REST_MIGRATOR_PATH . 'includes/class-background-processor.php';
	require_once WC_REST_MIGRATOR_PATH . 'admin/class-admin-menu.php';

	WC_RM_Job_Manager::install();
	WC_RM_Background_Processor::init();
	WC_RM_Admin_Menu::init();
} );

register_activation_hook( __FILE__, function () {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-job-manager.php';
	WC_RM_Job_Manager::install();
} );
