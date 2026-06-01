<?php
defined( 'ABSPATH' ) || exit;

class TWS_Category_Mapper {

    const DISCOVERED_OPTION = 'tws_discovered_categories';
    const MAP_OPTION        = 'tws_category_map';

    /**
     * Trendyol kategori adını WooCommerce kategori ID'sine çevirir.
     * Önce manuel eşleştirmeye, sonra otomatik oluşturmaya bakar.
     */
    public static function get_or_create( string $category_name ): int {
        if ( empty( $category_name ) ) {
            return 0;
        }

        self::record_discovered( $category_name );

        // Manuel eşleştirme var mı?
        $map = self::get_mapping();
        if ( isset( $map[ $category_name ] ) && (int) $map[ $category_name ] > 0 ) {
            return (int) $map[ $category_name ];
        }

        // Otomatik oluştur (hiyerarşik)
        $parts     = array_map( 'trim', explode( '>', $category_name ) );
        $parent_id = 0;

        foreach ( $parts as $part ) {
            $parent_id = self::find_or_create_term( $part, $parent_id );
        }

        return $parent_id;
    }

    /**
     * Kaydedilmiş manuel kategori eşleştirmesini döndürür.
     * Format: [ 'Trendyol Kategori Adı' => wc_term_id ]
     */
    public static function get_mapping(): array {
        $raw = get_option( self::MAP_OPTION, [] );
        return is_array( $raw ) ? $raw : [];
    }

    /**
     * Manuel kategori eşleştirmesini kaydeder.
     */
    public static function save_mapping( array $map ): void {
        $clean = [];
        foreach ( $map as $trendyol_cat => $wc_id ) {
            $trendyol_cat = sanitize_text_field( $trendyol_cat );
            $wc_id        = (int) $wc_id;
            if ( $trendyol_cat && $wc_id > 0 ) {
                $clean[ $trendyol_cat ] = $wc_id;
            }
        }
        update_option( self::MAP_OPTION, $clean );
    }

    /**
     * Senkronizasyon sırasında keşfedilen Trendyol kategorilerini kaydeder.
     */
    public static function record_discovered( string $category_name ): void {
        if ( empty( $category_name ) ) {
            return;
        }
        $list = get_option( self::DISCOVERED_OPTION, [] );
        if ( ! in_array( $category_name, $list, true ) ) {
            $list[] = $category_name;
            sort( $list );
            update_option( self::DISCOVERED_OPTION, $list );
        }
    }

    /**
     * Keşfedilen Trendyol kategorilerini döndürür.
     */
    public static function get_discovered(): array {
        return (array) get_option( self::DISCOVERED_OPTION, [] );
    }

    private static function find_or_create_term( string $name, int $parent ): int {
        $existing = get_term_by( 'name', $name, 'product_cat', ARRAY_A );

        if ( $existing && (int) $existing['parent'] === $parent ) {
            return (int) $existing['term_id'];
        }

        $result = wp_insert_term( $name, 'product_cat', [ 'parent' => $parent ] );

        if ( is_wp_error( $result ) ) {
            if ( isset( $result->error_data['term_exists'] ) ) {
                return (int) $result->error_data['term_exists'];
            }
            return 0;
        }

        return (int) $result['term_id'];
    }

    /**
     * Trendyol marka adını WooCommerce PA_MARKA attribute'una kaydeder.
     */
    public static function get_or_create_brand( string $brand ): int {
        if ( empty( $brand ) ) {
            return 0;
        }

        $existing = get_term_by( 'name', $brand, 'pa_marka' );
        if ( $existing ) {
            return (int) $existing->term_id;
        }

        $result = wp_insert_term( $brand, 'pa_marka' );
        if ( is_wp_error( $result ) ) {
            return isset( $result->error_data['term_exists'] ) ? (int) $result->error_data['term_exists'] : 0;
        }

        return (int) $result['term_id'];
    }
}
