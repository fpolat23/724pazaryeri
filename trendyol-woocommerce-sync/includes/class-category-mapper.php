<?php
defined( 'ABSPATH' ) || exit;

class TWS_Category_Mapper {

    /**
     * Trendyol kategori adını WooCommerce kategori ID'sine çevirir.
     * Yoksa yeni oluşturur.
     */
    public static function get_or_create( string $category_name ): int {
        if ( empty( $category_name ) ) {
            return 0;
        }

        $parts     = array_map( 'trim', explode( '>', $category_name ) );
        $parent_id = 0;

        foreach ( $parts as $part ) {
            $parent_id = self::find_or_create_term( $part, $parent_id );
        }

        return $parent_id;
    }

    private static function find_or_create_term( string $name, int $parent ): int {
        $existing = get_term_by( 'name', $name, 'product_cat', ARRAY_A );

        if ( $existing && (int) $existing['parent'] === $parent ) {
            return (int) $existing['term_id'];
        }

        $result = wp_insert_term( $name, 'product_cat', [ 'parent' => $parent ] );

        if ( is_wp_error( $result ) ) {
            // Terim zaten varsa hata verir, var olan ID'yi döndür
            if ( isset( $result->error_data['term_exists'] ) ) {
                return (int) $result->error_data['term_exists'];
            }
            return 0;
        }

        return (int) $result['term_id'];
    }

    /**
     * Trendyol marka adını WooCommerce PA_BRAND attribute'una kaydeder.
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
