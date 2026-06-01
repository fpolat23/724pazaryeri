<?php
defined( 'ABSPATH' ) || exit;

class TWS_Admin_Page {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
        add_action( 'wp_ajax_tws_save_category_map', [ __CLASS__, 'ajax_save_category_map' ] );
        add_action( 'wp_ajax_tws_get_wc_categories', [ __CLASS__, 'ajax_get_wc_categories' ] );
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
            'tws_price_markup'  => [ __CLASS__, 'sanitize_markup' ],
            'tws_vendor_id'     => 'absint',
        ];

        foreach ( $fields as $option => $sanitize ) {
            register_setting( 'tws_settings', $option, [ 'sanitize_callback' => $sanitize ] );
        }

        add_action( 'update_option_tws_sync_interval', function ( $old, $new ) {
            TWS_Sync_Manager::reschedule( $new );
        }, 10, 2 );
    }

    public static function sanitize_markup( $value ): float {
        $v = (float) $value;
        // -90 ile +500 aralığında sınırla
        return max( -90.0, min( 500.0, $v ) );
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
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'tws_nonce' ),
            'lastSync'   => get_option( 'tws_last_sync', '—' ),
            'categoryMap' => TWS_Category_Mapper::get_mapping(),
            'discovered' => TWS_Category_Mapper::get_discovered(),
            'wcCategories' => self::get_wc_category_list(),
        ] );
    }

    public static function render_page(): void {
        require_once TWS_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * WC kategorilerini id => name formatında döndürür (hiyerarşik).
     */
    public static function get_wc_category_list(): array {
        $terms = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
        ] );

        if ( is_wp_error( $terms ) ) {
            return [];
        }

        // Hiyerarşik isim oluştur
        $term_map = [];
        foreach ( $terms as $term ) {
            $term_map[ $term->term_id ] = $term;
        }

        $result = [];
        foreach ( $terms as $term ) {
            $result[] = [
                'id'   => $term->term_id,
                'name' => self::build_term_path( $term, $term_map ),
            ];
        }

        usort( $result, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );

        return $result;
    }

    private static function build_term_path( \WP_Term $term, array $all ): string {
        $parts = [ $term->name ];
        $parent = $term->parent;

        while ( $parent && isset( $all[ $parent ] ) ) {
            array_unshift( $parts, $all[ $parent ]->name );
            $parent = $all[ $parent ]->parent;
        }

        return implode( ' > ', $parts );
    }

    public static function ajax_save_category_map(): void {
        check_ajax_referer( 'tws_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ] );
        }

        $raw = isset( $_POST['map'] ) ? wp_unslash( $_POST['map'] ) : '[]';
        $map = json_decode( $raw, true );

        if ( ! is_array( $map ) ) {
            wp_send_json_error( [ 'message' => 'Geçersiz veri formatı.' ] );
        }

        TWS_Category_Mapper::save_mapping( $map );
        wp_send_json_success( [ 'message' => 'Kategori eşleştirmesi kaydedildi.' ] );
    }

    public static function ajax_get_wc_categories(): void {
        check_ajax_referer( 'tws_nonce', 'nonce' );
        wp_send_json_success( self::get_wc_category_list() );
    }
}
