<?php
defined( 'ABSPATH' ) || exit;

class TWS_Admin_Page {

    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );

        // Import AJAX
        add_action( 'wp_ajax_tws_save_category_map',  [ __CLASS__, 'ajax_save_category_map' ] );
        add_action( 'wp_ajax_tws_get_wc_categories',  [ __CLASS__, 'ajax_get_wc_categories' ] );

        // Vendor yönetimi AJAX (admin)
        add_action( 'wp_ajax_tws_get_vendors',         [ __CLASS__, 'ajax_get_vendors' ] );
        add_action( 'wp_ajax_tws_save_vendor_config',  [ __CLASS__, 'ajax_save_vendor_config' ] );

        // Export AJAX
        add_action( 'wp_ajax_tws_get_wc_products',            [ __CLASS__, 'ajax_get_wc_products' ] );
        add_action( 'wp_ajax_tws_search_trendyol_categories', [ __CLASS__, 'ajax_search_trendyol_categories' ] );
        add_action( 'wp_ajax_tws_get_category_attributes',    [ __CLASS__, 'ajax_get_category_attributes' ] );
        add_action( 'wp_ajax_tws_search_brands',              [ __CLASS__, 'ajax_search_brands' ] );
        add_action( 'wp_ajax_tws_get_cargo_companies',        [ __CLASS__, 'ajax_get_cargo_companies' ] );
        add_action( 'wp_ajax_tws_export_product',             [ __CLASS__, 'ajax_export_product' ] );
        add_action( 'wp_ajax_tws_check_export_status',        [ __CLASS__, 'ajax_check_export_status' ] );
    }

    public static function register_menu(): void {
        add_submenu_page(
            'woocommerce',
            'Trendyol Sync',
            'Trendyol Sync',
            'edit_products',   // vendor'lar da erişebilsin
            'trendyol-wc-sync',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function register_settings(): void {
        // Global ayarlar artık yok — her şey user meta'ya taşındı.
        // Geriye dönük uyumluluk için import AJAX hanndler'da category map kaydedilir.
        register_setting( 'tws_settings', 'tws_cat_map_placeholder', [ 'sanitize_callback' => '__return_empty_string' ] );
    }

    public static function enqueue_scripts( string $hook ): void {
        if ( strpos( $hook, 'trendyol-wc-sync' ) === false ) {
            return;
        }

        wp_enqueue_style( 'tws-admin', TWS_PLUGIN_URL . 'assets/admin.css', [], TWS_VERSION );
        wp_enqueue_script( 'tws-admin', TWS_PLUGIN_URL . 'assets/admin.js', [ 'jquery' ], TWS_VERSION, true );

        $current_user_id = get_current_user_id();
        $is_admin        = current_user_can( 'manage_woocommerce' );

        // Aktif vendor: admin ise kendi ID'si, vendor ise kendi ID'si
        $active_vendor_id = $current_user_id;

        wp_localize_script( 'tws-admin', 'twsData', [
            'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'tws_nonce' ),
            'isAdmin'        => $is_admin,
            'currentUserId'  => $current_user_id,
            'activeVendorId' => $active_vendor_id,
            'categoryMap'    => TWS_Category_Mapper::get_mapping(),
            'discovered'     => TWS_Category_Mapper::get_discovered(),
            'wcCategories'   => self::get_wc_category_list(),
            'intervals'      => [
                900   => 'Her 15 dakika',
                1800  => 'Her 30 dakika',
                3600  => 'Her saat',
                7200  => 'Her 2 saat',
                21600 => 'Her 6 saat',
                43200 => 'Her 12 saat',
                86400 => 'Günde 1 kez',
            ],
        ] );
    }

    public static function render_page(): void {
        $current_user_id = get_current_user_id();

        if ( ! TWS_Vendor_Manager::can_access( $current_user_id ) ) {
            wp_die( 'Bu sayfaya erişim yetkiniz yok.' );
        }

        require_once TWS_PLUGIN_DIR . 'admin/views/settings.php';
    }

    // ---------------------------------------------------------------
    // Import AJAX
    // ---------------------------------------------------------------

    public static function ajax_save_category_map(): void {
        self::verify_nonce();
        $raw = isset( $_POST['map'] ) ? wp_unslash( $_POST['map'] ) : '[]';
        $map = json_decode( $raw, true );
        if ( ! is_array( $map ) ) {
            wp_send_json_error( [ 'message' => 'Geçersiz veri.' ] );
        }
        TWS_Category_Mapper::save_mapping( $map );
        wp_send_json_success( [ 'message' => 'Kategori eşleştirmesi kaydedildi.' ] );
    }

    public static function ajax_get_wc_categories(): void {
        self::verify_nonce();
        wp_send_json_success( self::get_wc_category_list() );
    }

    // ---------------------------------------------------------------
    // Vendor Yönetimi AJAX
    // ---------------------------------------------------------------

    public static function ajax_get_vendors(): void {
        self::verify_nonce( true );
        wp_send_json_success( TWS_Vendor_Manager::get_all_with_admin() );
    }

    public static function ajax_save_vendor_config(): void {
        self::verify_nonce();

        $current_user_id = get_current_user_id();
        $target_id       = (int) ( $_POST['vendor_id'] ?? $current_user_id );

        // Vendor sadece kendi config'ini kaydedebilir; admin herkesi kaydedebilir
        if ( $target_id !== $current_user_id && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ] );
        }

        $config = [
            'api_key'       => sanitize_text_field( $_POST['api_key'] ?? '' ),
            'api_secret'    => sanitize_text_field( $_POST['api_secret'] ?? '' ),
            'supplier_id'   => sanitize_text_field( $_POST['supplier_id'] ?? '' ),
            'price_markup'  => (float) ( $_POST['price_markup'] ?? 0 ),
            'sync_interval' => (int) ( $_POST['sync_interval'] ?? 3600 ),
            'auto_sync'     => ! empty( $_POST['auto_sync'] ),
        ];

        TWS_Vendor_Manager::save_vendor_config( $target_id, $config );

        // Zamanlamayı güncelle
        if ( $config['auto_sync'] && $config['api_key'] && $config['supplier_id'] ) {
            TWS_Sync_Manager::schedule_vendor( $target_id, $config['sync_interval'] );
        } else {
            TWS_Sync_Manager::unschedule_vendor( $target_id );
        }

        wp_send_json_success( [ 'message' => 'Ayarlar kaydedildi.' ] );
    }

    // ---------------------------------------------------------------
    // Export AJAX
    // ---------------------------------------------------------------

    public static function ajax_get_wc_products(): void {
        self::verify_nonce();

        $page     = max( 1, (int) ( $_POST['page'] ?? 1 ) );
        $per_page = 20;
        $search   = sanitize_text_field( $_POST['search'] ?? '' );
        $author   = current_user_can( 'manage_woocommerce' ) ? 0 : get_current_user_id();

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        if ( $search ) {
            $args['s'] = $search;
        }

        if ( $author ) {
            $args['author'] = $author;
        }

        $query    = new \WP_Query( $args );
        $products = [];

        foreach ( $query->posts as $post ) {
            $product = wc_get_product( $post->ID );
            if ( ! $product ) {
                continue;
            }

            $status    = get_post_meta( $post->ID, '_trendyol_export_status', true );
            $date      = get_post_meta( $post->ID, '_trendyol_export_date', true );
            $config    = get_post_meta( $post->ID, '_trendyol_export_config', true ) ?: [];
            $image_id  = $product->get_image_id();
            $thumb_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

            $products[] = [
                'id'     => $post->ID,   'name'  => $product->get_name(),
                'sku'    => $product->get_sku(), 'price' => wc_price( $product->get_price() ),
                'stock'  => $product->get_stock_quantity(), 'type'  => $product->get_type(),
                'thumb'  => $thumb_url ?: '', 'status' => $status ?: 'none',
                'export_date' => $date ?: '', 'config' => $config,
            ];
        }

        wp_send_json_success( [
            'products'   => $products,
            'total'      => (int) $query->found_posts,
            'totalPages' => (int) $query->max_num_pages,
            'page'       => $page,
        ] );
    }

    public static function ajax_search_trendyol_categories(): void {
        self::verify_nonce();
        $api    = self::current_vendor_api();
        $name   = sanitize_text_field( $_POST['name'] ?? '' );
        $result = $api ? $api->search_categories( $name ) : new \WP_Error( 'no_api', 'API bilgileri eksik.' );
        if ( is_wp_error( $result ) ) { wp_send_json_error( [ 'message' => $result->get_error_message() ] ); }
        wp_send_json_success( $result );
    }

    public static function ajax_get_category_attributes(): void {
        self::verify_nonce();
        $api         = self::current_vendor_api();
        $category_id = (int) ( $_POST['category_id'] ?? 0 );
        if ( ! $category_id ) { wp_send_json_error( [ 'message' => 'Kategori ID gerekli.' ] ); }
        $result = $api ? $api->get_category_attributes( $category_id ) : new \WP_Error( 'no_api', 'API bilgileri eksik.' );
        if ( is_wp_error( $result ) ) { wp_send_json_error( [ 'message' => $result->get_error_message() ] ); }
        wp_send_json_success( $result );
    }

    public static function ajax_search_brands(): void {
        self::verify_nonce();
        $api    = self::current_vendor_api();
        $name   = sanitize_text_field( $_POST['name'] ?? '' );
        $result = $api ? $api->search_brands( $name ) : new \WP_Error( 'no_api', 'API bilgileri eksik.' );
        if ( is_wp_error( $result ) ) { wp_send_json_error( [ 'message' => $result->get_error_message() ] ); }
        wp_send_json_success( $result );
    }

    public static function ajax_get_cargo_companies(): void {
        self::verify_nonce();
        $api    = self::current_vendor_api();
        $result = $api ? $api->get_cargo_companies() : new \WP_Error( 'no_api', 'API bilgileri eksik.' );
        if ( is_wp_error( $result ) ) { wp_send_json_error( [ 'message' => $result->get_error_message() ] ); }
        wp_send_json_success( $result );
    }

    public static function ajax_export_product(): void {
        self::verify_nonce();
        $product_id = (int) ( $_POST['product_id'] ?? 0 );
        $config_raw = isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : '{}';
        $config     = json_decode( $config_raw, true );
        if ( ! $product_id || ! is_array( $config ) ) { wp_send_json_error( [ 'message' => 'Geçersiz istek.' ] ); }

        $api = self::current_vendor_api();
        if ( ! $api ) { wp_send_json_error( [ 'message' => 'API bilgileri eksik.' ] ); }

        $exporter = new TWS_Trendyol_Exporter( $api );
        $result   = $exporter->export( $product_id, $config );
        if ( is_wp_error( $result ) ) { wp_send_json_error( [ 'message' => $result->get_error_message() ] ); }
        wp_send_json_success( $result );
    }

    public static function ajax_check_export_status(): void {
        self::verify_nonce();
        $product_id = (int) ( $_POST['product_id'] ?? 0 );
        if ( ! $product_id ) { wp_send_json_error( [ 'message' => 'Ürün ID gerekli.' ] ); }

        $api = self::current_vendor_api();
        if ( ! $api ) { wp_send_json_error( [ 'message' => 'API bilgileri eksik.' ] ); }

        $exporter = new TWS_Trendyol_Exporter( $api );
        $result   = $exporter->check_status( $product_id );
        if ( is_wp_error( $result ) ) { wp_send_json_error( [ 'message' => $result->get_error_message() ] ); }
        wp_send_json_success( $result );
    }

    // ---------------------------------------------------------------
    // Yardımcılar
    // ---------------------------------------------------------------

    private static function current_vendor_api(): ?TWS_Trendyol_API {
        return TWS_Vendor_Manager::get_vendor_api( get_current_user_id() );
    }

    public static function get_wc_category_list(): array {
        $terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) ) {
            return [];
        }

        $term_map = [];
        foreach ( $terms as $t ) {
            $term_map[ $t->term_id ] = $t;
        }

        $result = [];
        foreach ( $terms as $t ) {
            $result[] = [ 'id' => $t->term_id, 'name' => self::build_term_path( $t, $term_map ) ];
        }

        usort( $result, fn( $a, $b ) => strcmp( $a['name'], $b['name'] ) );
        return $result;
    }

    private static function build_term_path( \WP_Term $term, array $all ): string {
        $parts  = [ $term->name ];
        $parent = $term->parent;
        while ( $parent && isset( $all[ $parent ] ) ) {
            array_unshift( $parts, $all[ $parent ]->name );
            $parent = $all[ $parent ]->parent;
        }
        return implode( ' > ', $parts );
    }

    private static function verify_nonce( bool $admin_only = false ): void {
        check_ajax_referer( 'tws_nonce', 'nonce' );
        $uid = get_current_user_id();
        if ( $admin_only && ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ] );
        }
        if ( ! $admin_only && ! TWS_Vendor_Manager::can_access( $uid ) ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ] );
        }
    }
}
