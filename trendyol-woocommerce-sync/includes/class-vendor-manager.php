<?php
defined( 'ABSPATH' ) || exit;

class TWS_Vendor_Manager {

    const VENDOR_ROLES = [ 'seller', 'vendor', 'wcfm_vendor', 'wc_product_vendors_admin_vendor' ];
    const META_PREFIX  = '_tws_';

    // ---------------------------------------------------------------
    // Kullanıcı sorgulama
    // ---------------------------------------------------------------

    /**
     * Tüm vendor kullanıcılarını döndürür.
     * Admin'i içermez; ayrı yönetilir.
     */
    public static function get_all_vendors(): array {
        $users = get_users( [
            'role__in' => self::VENDOR_ROLES,
            'orderby'  => 'display_name',
            'number'   => 500,
        ] );

        return array_map( [ __CLASS__, 'format_vendor' ], $users );
    }

    /**
     * Vendor rolü olan veya admin olan tüm kullanıcılar
     * (admin kendi entegrasyonunu da yönetebilir).
     */
    public static function get_all_with_admin(): array {
        $users = get_users( [
            'role__in' => array_merge( self::VENDOR_ROLES, [ 'administrator' ] ),
            'orderby'  => 'display_name',
            'number'   => 500,
        ] );

        return array_map( [ __CLASS__, 'format_vendor' ], $users );
    }

    private static function format_vendor( \WP_User $user ): array {
        $config = self::get_vendor_config( $user->ID );
        $stats  = self::get_vendor_stats( $user->ID );
        return [
            'id'         => $user->ID,
            'name'       => $user->display_name,
            'email'      => $user->user_email,
            'roles'      => $user->roles,
            'configured' => ! empty( $config['api_key'] ) && ! empty( $config['supplier_id'] ),
            'config'     => $config,
            'stats'      => $stats,
        ];
    }

    // ---------------------------------------------------------------
    // Yapılandırma okuma / yazma
    // ---------------------------------------------------------------

    public static function get_vendor_config( int $user_id ): array {
        $get = function ( $key, $default = '' ) use ( $user_id ) {
            $v = get_user_meta( $user_id, self::META_PREFIX . $key, true );
            return $v !== '' && $v !== false ? $v : $default;
        };

        return [
            'api_key'         => (string) $get( 'api_key' ),
            'api_secret'      => (string) $get( 'api_secret' ),
            'supplier_id'     => (string) $get( 'supplier_id' ),
            'price_markup'    => (float)  $get( 'price_markup', 0 ),
            'sync_interval'   => (int)    $get( 'sync_interval', 3600 ),
            'auto_sync'       => (bool)   $get( 'auto_sync', 1 ),
        ];
    }

    public static function save_vendor_config( int $user_id, array $data ): void {
        $allowed = [
            'api_key'       => 'sanitize_text_field',
            'api_secret'    => 'sanitize_text_field',
            'supplier_id'   => 'sanitize_text_field',
            'price_markup'  => 'floatval',
            'sync_interval' => 'absint',
            'auto_sync'     => 'boolval',
        ];

        foreach ( $allowed as $key => $sanitizer ) {
            if ( array_key_exists( $key, $data ) ) {
                update_user_meta( $user_id, self::META_PREFIX . $key, $sanitizer( $data[ $key ] ) );
            }
        }
    }

    // ---------------------------------------------------------------
    // API bağlantısı
    // ---------------------------------------------------------------

    public static function get_vendor_api( int $user_id ): ?TWS_Trendyol_API {
        $c = self::get_vendor_config( $user_id );
        if ( ! $c['api_key'] || ! $c['api_secret'] || ! $c['supplier_id'] ) {
            return null;
        }
        return new TWS_Trendyol_API( $c['api_key'], $c['api_secret'], $c['supplier_id'] );
    }

    // ---------------------------------------------------------------
    // İstatistik
    // ---------------------------------------------------------------

    public static function get_vendor_stats( int $user_id ): array {
        global $wpdb;

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT pm.post_id)
             FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = '_trendyol_product_id'
               AND p.post_author = %d
               AND p.post_status != 'trash'",
            $user_id
        ) );

        $last_sync = get_user_meta( $user_id, '_tws_last_sync', true );
        $status    = get_user_meta( $user_id, '_tws_sync_status', true ) ?: 'idle';

        return [
            'product_count' => $count,
            'last_sync'     => $last_sync ?: '—',
            'status'        => $status,
        ];
    }

    public static function update_last_sync( int $user_id ): void {
        update_user_meta( $user_id, '_tws_last_sync', current_time( 'mysql' ) );
        update_user_meta( $user_id, '_tws_sync_status', 'idle' );
    }

    public static function set_sync_status( int $user_id, string $status ): void {
        update_user_meta( $user_id, '_tws_sync_status', $status );
    }

    // ---------------------------------------------------------------
    // Erişim kontrol
    // ---------------------------------------------------------------

    public static function is_vendor( int $user_id ): bool {
        $user = get_userdata( $user_id );
        return $user && ! empty( array_intersect( $user->roles, self::VENDOR_ROLES ) );
    }

    /**
     * Kullanıcı bu eklentiye erişebilir mi?
     * Admin veya vendor rolüne sahip kullanıcılar erişebilir.
     */
    public static function can_access( int $user_id ): bool {
        return user_can( $user_id, 'manage_woocommerce' ) || self::is_vendor( $user_id );
    }

    /**
     * Kullanıcı başka bir vendor'ın ayarlarını yönetebilir mi?
     */
    public static function can_manage_others( int $user_id ): bool {
        return user_can( $user_id, 'manage_woocommerce' );
    }
}
