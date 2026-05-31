<?php
/**
 * Plugin Name: PazarYeri Vendor System
 * Description: Hafif WooCommerce çoklu satıcı sistemi. Dokan alternatifi. Kategori bazlı komisyon, frontend dashboard, sipariş yönetimi.
 * Version:     1.1.3
 * Author:      724PazarYeri
 * Requires PHP: 7.2
 * Requires at least: 5.6
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PZV_VERSION', '1.1.3' );
define( 'PZV_FILE', __FILE__ );
define( 'PZV_DIR', plugin_dir_path( __FILE__ ) );
define( 'PZV_URL', plugin_dir_url( __FILE__ ) );
define( 'PZV_ROLE', 'pzv_vendor' );

require_once PZV_DIR . 'includes/class-roles.php';
require_once PZV_DIR . 'includes/class-vendor.php';
require_once PZV_DIR . 'includes/class-commission.php';
require_once PZV_DIR . 'includes/class-admin.php';
require_once PZV_DIR . 'includes/class-dashboard.php';

register_activation_hook( __FILE__, function () {
    PZV_Roles::create_role();
    PZV_Commission::create_table();
    if ( ! get_page_by_path( 'saticim' ) ) {
        wp_insert_post( array(
            'post_title'   => 'Satıcı Paneli',
            'post_name'    => 'saticim',
            'post_content' => '[pazaryeri_vendor_dashboard]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
    }
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );

add_action( 'admin_init', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>PazarYeri Vendor:</strong> WooCommerce gerekli.</p></div>';
        } );
    }
} );


// HPOS (High-Performance Order Storage) uyumluluk deklarasyonu
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) return;
    new PZV_Admin();
    new PZV_Dashboard();
    new PZV_Commission();

    // Vendor ürünlerini otomatik "onay bekliyor" statüsüne düşür
    add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
        if ( $data['post_type'] !== 'product' ) return $data;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $data;
        if ( defined( 'PZV_DOING_QUICK_SAVE' ) ) return $data;
        if ( ! is_user_logged_in() ) return $data;
        if ( current_user_can( 'manage_woocommerce' ) ) return $data;
        $uid = get_current_user_id();
        if ( ! PZV_Roles::is_vendor( $uid ) ) return $data;
        if ( $data['post_status'] === 'publish' ) {
            $data['post_status'] = 'pending';
        }
        return $data;
    }, 99, 2 );

    // Ürün onay beklemeye girdiğinde yöneticiye e-posta gönder (tek seferlik)
    add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
        if ( $post->post_type !== 'product' ) return;
        if ( $new_status !== 'pending' || $old_status === 'pending' ) return;
        $author_id = (int) $post->post_author;
        if ( ! PZV_Roles::is_vendor( $author_id ) ) return;
        $vendor      = PZV_Vendor::get( $author_id );
        $store_name  = $vendor ? $vendor['store_name'] : 'bilinmiyor';
        $admin_email = get_option( 'admin_email' );
        $subject     = '724PazarYeri - Onay Bekleyen Ürün: ' . $post->post_title;
        $body        = "Satıcı \"{$store_name}\" bir ürünü onayınızı bekliyor.\n\n"
                     . "Ürün: {$post->post_title}\n"
                     . "Düzenle: " . admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) . "\n\n"
                     . "Bekleyen ürünler: " . admin_url( 'admin.php?page=pzv-pending' );
        wp_mail( $admin_email, $subject, $body );
    }, 10, 3 );
} );

add_shortcode( 'pazaryeri_vendor_dashboard', array( 'PZV_Dashboard', 'render_shortcode' ) );

// AJAX endpoints
add_action( 'wp_ajax_pzv_approve_vendor',  array( 'PZV_Admin', 'ajax_approve_vendor' ) );
add_action( 'wp_ajax_pzv_save_commission', array( 'PZV_Admin', 'ajax_save_commission' ) );
add_action( 'wp_ajax_pzv_mark_paid',       array( 'PZV_Admin', 'ajax_mark_paid' ) );
add_action( 'wp_ajax_pzv_search_products', array( 'PZV_Dashboard', 'ajax_search_products' ) );
add_action( 'wp_ajax_pzv_clone_product',   array( 'PZV_Dashboard', 'ajax_clone_product' ) );
add_action( 'wp_ajax_pzv_update_my_product', array( 'PZV_Dashboard', 'ajax_update_my_product' ) );
add_action( 'wp_ajax_pzv_update_order',    array( 'PZV_Dashboard', 'ajax_update_order' ) );
add_action( 'wp_ajax_pzv_approve_product', array( 'PZV_Admin', 'ajax_approve_product' ) );
