<?php
/**
 * Plugin Name: Trendyol WooCommerce Sync
 * Plugin URI:  https://724pazaryeri.com
 * Description: Trendyol mağazasındaki ürünleri WooCommerce'e otomatik olarak aktarır ve senkronize eder.
 * Version:     1.0.0
 * Author:      724 Pazaryeri
 * Text Domain: trendyol-wc-sync
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'TWS_VERSION', '1.0.0' );
define( 'TWS_PLUGIN_FILE', __FILE__ );
define( 'TWS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TWS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TWS_PLUGIN_DIR . 'includes/class-trendyol-api.php';
require_once TWS_PLUGIN_DIR . 'includes/class-category-mapper.php';
require_once TWS_PLUGIN_DIR . 'includes/class-wc-product-importer.php';
require_once TWS_PLUGIN_DIR . 'includes/class-sync-manager.php';
require_once TWS_PLUGIN_DIR . 'admin/class-admin-page.php';

register_activation_hook( __FILE__, [ 'TWS_Sync_Manager', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'TWS_Sync_Manager', 'deactivate' ] );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="error"><p><strong>Trendyol WooCommerce Sync:</strong> WooCommerce eklentisi aktif değil.</p></div>';
        } );
        return;
    }

    TWS_Admin_Page::init();
    TWS_Sync_Manager::init();
} );
