<?php
defined( 'ABSPATH' ) || exit;

class TWS_Sync_Manager {

    const CRON_HOOK    = 'tws_scheduled_sync';
    const LOG_OPTION   = 'tws_sync_log';
    const MAX_LOG_ROWS = 100;

    public static function init(): void {
        add_action( self::CRON_HOOK, [ __CLASS__, 'run_sync' ] );
        add_action( 'wp_ajax_tws_manual_sync', [ __CLASS__, 'ajax_manual_sync' ] );
        add_action( 'wp_ajax_tws_test_connection', [ __CLASS__, 'ajax_test_connection' ] );
    }

    public static function activate(): void {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            $interval = get_option( 'tws_sync_interval', 'hourly' );
            wp_schedule_event( time(), $interval, self::CRON_HOOK );
        }
    }

    public static function deactivate(): void {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    public static function reschedule( string $interval ): void {
        self::deactivate();
        wp_schedule_event( time(), $interval, self::CRON_HOOK );
    }

    public static function run_sync(): array {
        $api = self::get_api();
        if ( ! $api ) {
            self::log( 'error', 'API bilgileri eksik, senkronizasyon başlatılamadı.' );
            return [ 'success' => false, 'message' => 'API bilgileri eksik.' ];
        }

        self::log( 'info', 'Senkronizasyon başlatıldı.' );

        $products = $api->get_all_products();
        if ( is_wp_error( $products ) ) {
            $msg = 'Trendyol API hatası: ' . $products->get_error_message();
            self::log( 'error', $msg );
            return [ 'success' => false, 'message' => $msg ];
        }

        $importer = new TWS_WC_Product_Importer();
        $imported = 0;
        $updated  = 0;
        $failed   = 0;
        $errors   = [];

        foreach ( $products as $product ) {
            $is_new = empty( get_posts( [
                'post_type'   => 'product',
                'meta_key'    => '_trendyol_product_id',
                'meta_value'  => $product['id'] ?? '',
                'numberposts' => 1,
                'fields'      => 'ids',
            ] ) );

            $result = $importer->import( $product );

            if ( is_wp_error( $result ) ) {
                $failed++;
                $errors[] = sprintf( '%s: %s', $product['title'] ?? '?', $result->get_error_message() );
            } elseif ( $is_new ) {
                $imported++;
            } else {
                $updated++;
            }
        }

        $summary = sprintf(
            'Tamamlandı — Yeni: %d, Güncellenen: %d, Hatalı: %d',
            $imported,
            $updated,
            $failed
        );

        self::log( $failed > 0 ? 'warning' : 'success', $summary );

        if ( ! empty( $errors ) ) {
            foreach ( array_slice( $errors, 0, 10 ) as $err ) {
                self::log( 'error', $err );
            }
        }

        update_option( 'tws_last_sync', current_time( 'mysql' ) );

        return [
            'success'  => true,
            'imported' => $imported,
            'updated'  => $updated,
            'failed'   => $failed,
            'message'  => $summary,
        ];
    }

    public static function ajax_manual_sync(): void {
        check_ajax_referer( 'tws_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ] );
        }

        $result = self::run_sync();
        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    public static function ajax_test_connection(): void {
        check_ajax_referer( 'tws_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ] );
        }

        $api    = self::get_api();
        $result = $api ? $api->test_connection() : new \WP_Error( 'no_credentials', 'API bilgileri eksik.' );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => 'Bağlantı başarılı!' ] );
    }

    public static function get_api(): ?TWS_Trendyol_API {
        $api_key     = get_option( 'tws_api_key', '' );
        $api_secret  = get_option( 'tws_api_secret', '' );
        $supplier_id = get_option( 'tws_supplier_id', '' );

        if ( ! $api_key || ! $api_secret || ! $supplier_id ) {
            return null;
        }

        return new TWS_Trendyol_API( $api_key, $api_secret, $supplier_id );
    }

    private static function log( string $level, string $message ): void {
        $logs = get_option( self::LOG_OPTION, [] );

        array_unshift( $logs, [
            'time'    => current_time( 'mysql' ),
            'level'   => $level,
            'message' => $message,
        ] );

        // Maksimum satır sayısını koru
        $logs = array_slice( $logs, 0, self::MAX_LOG_ROWS );

        update_option( self::LOG_OPTION, $logs );
    }
}
