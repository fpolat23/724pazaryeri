<?php
/**
 * Plugin Name: PZV Vendor Migrator
 * Plugin URI:  https://724pazaryeri.com
 * Description: PazarYeri Vendor System üye, satıcı, ürün ataması ve komisyon verilerini kaynak WooCommerce sitesinden aktarır.
 * Version:     1.1.0
 * Author:      724 PazarYeri
 * Author URI:  https://724pazaryeri.com
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

defined( 'ABSPATH' ) || exit;

define( 'PZV_MIG_VERSION', '1.1.0' );
define( 'PZV_MIG_FILE',    __FILE__ );
define( 'PZV_MIG_PATH',    plugin_dir_path( __FILE__ ) );
define( 'PZV_MIG_URL',     plugin_dir_url( __FILE__ ) );
define( 'PZV_MIG_API_NS',  'pzv-mig/v1' );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) return;

	require_once PZV_MIG_PATH . 'includes/class-state.php';
	require_once PZV_MIG_PATH . 'includes/class-source-endpoints.php';
	require_once PZV_MIG_PATH . 'includes/class-api-client.php';
	require_once PZV_MIG_PATH . 'includes/class-vendor-importer.php';
	require_once PZV_MIG_PATH . 'includes/class-member-importer.php';
	require_once PZV_MIG_PATH . 'admin/class-admin-ui.php';

	// Source endpoints — active on ANY site that has this plugin + PZV
	PZV_Mig_Source_Endpoints::init();

	if ( is_admin() ) {
		PZV_Mig_Admin_UI::init();
	}
} );
