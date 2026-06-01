<?php
defined( 'ABSPATH' ) || exit;

class TWS_Admin_Page {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
    }

    public static function register_menu(): void {
        add_submenu_page(
            'woocommerce',
            'Trendyol Sync',
            'Trendyol Sync',
            'manage_woocommerce',
            'trendyol-wc-sync',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function register_settings(): void {
        $fields = [
            'tws_api_key'       => 'sanitize_text_field',
            'tws_api_secret'    => 'sanitize_text_field',
            'tws_supplier_id'   => 'sanitize_text_field',
            'tws_sync_interval' => 'sanitize_text_field',
        ];

        foreach ( $fields as $option => $sanitize ) {
            register_setting( 'tws_settings', $option, [ 'sanitize_callback' => $sanitize ] );
        }

        add_action( 'update_option_tws_sync_interval', function ( $old, $new ) {
            TWS_Sync_Manager::reschedule( $new );
        }, 10, 2 );
    }

    public static function enqueue_scripts( string $hook ): void {
        if ( strpos( $hook, 'trendyol-wc-sync' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'tws-admin',
            TWS_PLUGIN_URL . 'assets/admin.css',
            [],
            TWS_VERSION
        );

        wp_enqueue_script(
            'tws-admin',
            TWS_PLUGIN_URL . 'assets/admin.js',
            [ 'jquery' ],
            TWS_VERSION,
            true
        );

        wp_localize_script( 'tws-admin', 'twsData', [
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'tws_nonce' ),
            'lastSync' => get_option( 'tws_last_sync', '—' ),
        ] );
    }

    public static function render_page(): void {
        require_once TWS_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
