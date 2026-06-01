<?php
defined( 'ABSPATH' ) || exit;

class TWS_Sync_Manager {

    const AS_HOOK_SYNC  = 'tws_run_sync';
    const AS_HOOK_CHUNK = 'tws_process_chunk';
    const AS_GROUP      = 'tws';
    const LOG_OPTION    = 'tws_sync_log';
    const MAX_LOG_ROWS  = 150;
    const CHUNK_SIZE    = 15;

    public static function init(): void {
        add_action( self::AS_HOOK_SYNC,  [ __CLASS__, 'run_sync' ] );   // arg: vendor_id
        add_action( self::AS_HOOK_CHUNK, [ __CLASS__, 'process_chunk' ] ); // arg: vendor_id

        add_action( 'wp_ajax_tws_manual_sync',        [ __CLASS__, 'ajax_manual_sync' ] );
        add_action( 'wp_ajax_tws_test_connection',    [ __CLASS__, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_tws_sync_status',        [ __CLASS__, 'ajax_sync_status' ] );
        add_action( 'wp_ajax_tws_test_vendor_conn',   [ __CLASS__, 'ajax_test_vendor_connection' ] );
        add_action( 'wp_ajax_tws_sync_vendor',        [ __CLASS__, 'ajax_sync_vendor' ] );
        add_action( 'wp_ajax_tws_vendor_sync_status', [ __CLASS__, 'ajax_vendor_sync_status' ] );

        // WP-Cron fallback interval'ları
        add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_intervals' ] );
    }

    // ---------------------------------------------------------------
    // Zamanlama (per-vendor)
    // ---------------------------------------------------------------

    public static function activate(): void {
        // Her yapılandırılmış vendor için zamanlama başlat
        foreach ( TWS_Vendor_Manager::get_all_with_admin() as $v ) {
            if ( $v['configured'] && $v['config']['auto_sync'] ) {
                self::schedule_vendor( $v['id'], $v['config']['sync_interval'] );
            }
        }
    }

    public static function deactivate(): void {
        if ( self::has_action_scheduler() ) {
            as_unschedule_all_actions( self::AS_HOOK_SYNC,  [], self::AS_GROUP );
            as_unschedule_all_actions( self::AS_HOOK_CHUNK, [], self::AS_GROUP );
        }
    }

    public static function schedule_vendor( int $vendor_id, int $seconds = 3600 ): void {
        if ( self::has_action_scheduler() ) {
            // Önce mevcut zamanlamayı temizle
            as_unschedule_all_actions( self::AS_HOOK_SYNC, [ $vendor_id ], self::AS_GROUP );
            as_schedule_recurring_action( time() + 60, $seconds, self::AS_HOOK_SYNC, [ $vendor_id ], self::AS_GROUP );
        } else {
            // WP-Cron fallback
            $hook = 'tws_cron_vendor_' . $vendor_id;
            $ts   = wp_next_scheduled( $hook );
            if ( $ts ) {
                wp_unschedule_event( $ts, $hook );
            }
            $interval = self::seconds_to_wpcron_interval( $seconds );
            wp_schedule_event( time() + 60, $interval, $hook );
            add_action( $hook, function () use ( $vendor_id ) {
                self::run_sync( $vendor_id );
            } );
        }
    }

    public static function unschedule_vendor( int $vendor_id ): void {
        if ( self::has_action_scheduler() ) {
            as_unschedule_all_actions( self::AS_HOOK_SYNC,  [ $vendor_id ], self::AS_GROUP );
            as_unschedule_all_actions( self::AS_HOOK_CHUNK, [ $vendor_id ], self::AS_GROUP );
        }
    }

    // ---------------------------------------------------------------
    // Ana Sync: Trendyol'dan ürün çek, kuyruğa al
    // ---------------------------------------------------------------

    /**
     * Action Scheduler bu metodu çağırır. vendor_id AS arg olarak gelir.
     */
    public static function run_sync( int $vendor_id ): void {
        $queue_key = 'tws_sync_queue_' . $vendor_id;

        if ( get_option( $queue_key ) !== false ) {
            self::log( $vendor_id, 'warning', 'Önceki sync henüz bitmedi, atlandı.' );
            return;
        }

        $api = TWS_Vendor_Manager::get_vendor_api( $vendor_id );
        if ( ! $api ) {
            self::log( $vendor_id, 'error', 'API bilgileri eksik veya hatalı.' );
            return;
        }

        TWS_Vendor_Manager::set_sync_status( $vendor_id, 'running' );
        self::log( $vendor_id, 'info', 'Senkronizasyon başlatıldı.' );

        $products = $api->get_all_products();
        if ( is_wp_error( $products ) ) {
            TWS_Vendor_Manager::set_sync_status( $vendor_id, 'error' );
            self::log( $vendor_id, 'error', 'API hatası: ' . $products->get_error_message() );
            return;
        }

        if ( empty( $products ) ) {
            TWS_Vendor_Manager::update_last_sync( $vendor_id );
            self::log( $vendor_id, 'info', 'Aktarılacak ürün bulunamadı.' );
            return;
        }

        update_option( $queue_key, $products, false );
        update_option( 'tws_sync_offset_' . $vendor_id, 0 );
        update_option( 'tws_sync_stats_' . $vendor_id, [
            'total'     => count( $products ),
            'processed' => 0,
            'imported'  => 0,
            'updated'   => 0,
            'failed'    => 0,
            'started'   => current_time( 'mysql' ),
        ] );

        self::log( $vendor_id, 'info', sprintf( '%d ürün kuyruğa alındı.', count( $products ) ) );
        self::enqueue_next_chunk( $vendor_id );
    }

    // ---------------------------------------------------------------
    // Chunk işleme
    // ---------------------------------------------------------------

    public static function process_chunk( int $vendor_id ): void {
        $queue_key  = 'tws_sync_queue_' . $vendor_id;
        $queue      = get_option( $queue_key, [] );
        $offset     = (int) get_option( 'tws_sync_offset_' . $vendor_id, 0 );
        $chunk      = array_slice( $queue, $offset, self::CHUNK_SIZE );

        if ( empty( $chunk ) ) {
            self::finish_sync( $vendor_id );
            return;
        }

        $importer = new TWS_WC_Product_Importer( $vendor_id );
        $stats    = get_option( 'tws_sync_stats_' . $vendor_id, [] );

        foreach ( $chunk as $product ) {
            $is_new = ! (bool) get_posts( [
                'post_type'   => 'product',
                'meta_key'    => '_trendyol_product_id',
                'meta_value'  => $product['id'] ?? '',
                'numberposts' => 1,
                'fields'      => 'ids',
            ] );

            $result = $importer->import( $product );

            $stats['processed'] = ( $stats['processed'] ?? 0 ) + 1;
            if ( is_wp_error( $result ) ) {
                $stats['failed'] = ( $stats['failed'] ?? 0 ) + 1;
            } elseif ( $is_new ) {
                $stats['imported'] = ( $stats['imported'] ?? 0 ) + 1;
            } else {
                $stats['updated'] = ( $stats['updated'] ?? 0 ) + 1;
            }
        }

        update_option( 'tws_sync_offset_' . $vendor_id, $offset + self::CHUNK_SIZE );
        update_option( 'tws_sync_stats_' . $vendor_id, $stats );

        self::enqueue_next_chunk( $vendor_id );
    }

    private static function enqueue_next_chunk( int $vendor_id ): void {
        if ( self::has_action_scheduler() ) {
            as_enqueue_async_action( self::AS_HOOK_CHUNK, [ $vendor_id ], self::AS_GROUP );
        } else {
            self::process_chunk( $vendor_id );
        }
    }

    private static function finish_sync( int $vendor_id ): void {
        $stats = get_option( 'tws_sync_stats_' . $vendor_id, [] );
        $msg   = sprintf(
            'Tamamlandı — Toplam: %d, Yeni: %d, Güncellenen: %d, Hatalı: %d',
            $stats['total']    ?? 0,
            $stats['imported'] ?? 0,
            $stats['updated']  ?? 0,
            $stats['failed']   ?? 0
        );

        self::log( $vendor_id, ( ( $stats['failed'] ?? 0 ) > 0 ) ? 'warning' : 'success', $msg );
        TWS_Vendor_Manager::update_last_sync( $vendor_id );

        delete_option( 'tws_sync_queue_' . $vendor_id );
        delete_option( 'tws_sync_offset_' . $vendor_id );

        $stats['finished'] = current_time( 'mysql' );
        update_option( 'tws_sync_stats_' . $vendor_id, $stats );
    }

    // ---------------------------------------------------------------
    // AJAX — Genel (admin kendi entegrasyonu)
    // ---------------------------------------------------------------

    public static function ajax_manual_sync(): void {
        check_ajax_referer( 'tws_nonce', 'nonce' );

        $vendor_id = self::get_ajax_vendor_id();
        if ( ! $vendor_id ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ] );
        }

        if ( self::has_action_scheduler() ) {
            delete_option( 'tws_sync_queue_' . $vendor_id );
            as_enqueue_async_action( self::AS_HOOK_SYNC, [ $vendor_id ], self::AS_GROUP );
            wp_send_json_success( [ 'background' => true, 'message' => 'Arka planda başlatıldı.' ] );
        } else {
            self::run_sync( $vendor_id );
            $stats = get_option( 'tws_sync_stats_' . $vendor_id, [] );
            wp_send_json_success( [
                'background' => false,
                'message'    => sprintf( 'Tamamlandı — Yeni: %d, Güncellenen: %d, Hatalı: %d',
                    $stats['imported'] ?? 0, $stats['updated'] ?? 0, $stats['failed'] ?? 0 ),
            ] );
        }
    }

    public static function ajax_sync_status(): void {
        check_ajax_referer( 'tws_nonce', 'nonce' );

        $vendor_id = self::get_ajax_vendor_id();
        if ( ! $vendor_id ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ] );
        }

        wp_send_json_success( self::build_status_payload( $vendor_id ) );
    }

    public static function ajax_test_connection(): void {
        check_ajax_referer( 'tws_nonce', 'nonce' );

        $vendor_id = self::get_ajax_vendor_id();
        if ( ! $vendor_id ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ] );
        }

        $api    = TWS_Vendor_Manager::get_vendor_api( $vendor_id );
        $result = $api ? $api->test_connection() : new \WP_Error( 'no_api', 'API bilgileri eksik.' );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => 'Bağlantı başarılı!' ] );
    }

    // ---------------------------------------------------------------
    // AJAX — Vendor Yönetimi (admin)
    // ---------------------------------------------------------------

    public static function ajax_test_vendor_connection(): void {
        check_ajax_referer( 'tws_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ] );
        }

        $vendor_id = (int) ( $_POST['vendor_id'] ?? 0 );
        $api       = TWS_Vendor_Manager::get_vendor_api( $vendor_id );
        $result    = $api ? $api->test_connection() : new \WP_Error( 'no_api', 'API bilgileri eksik.' );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        wp_send_json_success( [ 'message' => 'Bağlantı başarılı!' ] );
    }

    public static function ajax_sync_vendor(): void {
        check_ajax_referer( 'tws_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ] );
        }

        $vendor_id = (int) ( $_POST['vendor_id'] ?? 0 );
        if ( ! $vendor_id ) {
            wp_send_json_error( [ 'message' => 'Vendor ID gerekli.' ] );
        }

        if ( self::has_action_scheduler() ) {
            delete_option( 'tws_sync_queue_' . $vendor_id );
            as_enqueue_async_action( self::AS_HOOK_SYNC, [ $vendor_id ], self::AS_GROUP );
            wp_send_json_success( [ 'background' => true, 'message' => 'Arka planda başlatıldı.' ] );
        } else {
            self::run_sync( $vendor_id );
            wp_send_json_success( [ 'background' => false, 'message' => 'Sync tamamlandı.' ] );
        }
    }

    public static function ajax_vendor_sync_status(): void {
        check_ajax_referer( 'tws_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ] );
        }

        $vendor_id = (int) ( $_POST['vendor_id'] ?? 0 );
        wp_send_json_success( self::build_status_payload( $vendor_id ) );
    }

    // ---------------------------------------------------------------
    // Yardımcılar
    // ---------------------------------------------------------------

    private static function build_status_payload( int $vendor_id ): array {
        $queue_key   = 'tws_sync_queue_' . $vendor_id;
        $in_progress = get_option( $queue_key ) !== false;
        $stats       = get_option( 'tws_sync_stats_' . $vendor_id, [] );

        $next_sync = '—';
        if ( self::has_action_scheduler() ) {
            $next_ts = as_next_scheduled_action( self::AS_HOOK_SYNC, [ $vendor_id ], self::AS_GROUP );
            if ( $next_ts ) {
                $next_sync = wp_date( 'd.m.Y H:i', $next_ts );
            }
        }

        $progress_pct = 0;
        if ( $in_progress && ! empty( $stats['total'] ) ) {
            $progress_pct = round( ( $stats['processed'] / $stats['total'] ) * 100 );
        }

        $vendor_stats = TWS_Vendor_Manager::get_vendor_stats( $vendor_id );

        return [
            'in_progress'  => $in_progress,
            'stats'        => $stats,
            'progress_pct' => $progress_pct,
            'last_sync'    => $vendor_stats['last_sync'],
            'next_sync'    => $next_sync,
            'has_as'       => self::has_action_scheduler(),
            'status'       => $vendor_stats['status'],
        ];
    }

    private static function get_ajax_vendor_id(): int {
        $current_user_id = get_current_user_id();

        // Admin kendi vendor ID'sini POST ile gönderebilir, ya da kendisi
        if ( current_user_can( 'manage_woocommerce' ) ) {
            $vendor_id = (int) ( $_POST['vendor_id'] ?? $current_user_id );
            return $vendor_id > 0 ? $vendor_id : $current_user_id;
        }

        // Vendor sadece kendi ID'sini kullanabilir
        if ( TWS_Vendor_Manager::is_vendor( $current_user_id ) ) {
            return $current_user_id;
        }

        return 0;
    }

    public static function get_api(): ?TWS_Trendyol_API {
        return TWS_Vendor_Manager::get_vendor_api( get_current_user_id() );
    }

    public static function has_action_scheduler(): bool {
        return function_exists( 'as_enqueue_async_action' );
    }

    public static function add_cron_intervals( array $schedules ): array {
        $schedules['tws_15min'] = [ 'interval' => 900,   'display' => '15 Dakika' ];
        $schedules['tws_30min'] = [ 'interval' => 1800,  'display' => '30 Dakika' ];
        $schedules['tws_2h']    = [ 'interval' => 7200,  'display' => '2 Saat' ];
        $schedules['tws_6h']    = [ 'interval' => 21600, 'display' => '6 Saat' ];
        $schedules['tws_12h']   = [ 'interval' => 43200, 'display' => '12 Saat' ];
        return $schedules;
    }

    private static function seconds_to_wpcron_interval( int $s ): string {
        return match ( true ) {
            $s <= 900   => 'tws_15min',
            $s <= 1800  => 'tws_30min',
            $s <= 3600  => 'hourly',
            $s <= 7200  => 'tws_2h',
            $s <= 21600 => 'tws_6h',
            $s <= 43200 => 'tws_12h',
            default     => 'daily',
        };
    }

    public static function log( int $vendor_id, string $level, string $message ): void {
        $option = self::LOG_OPTION . '_' . $vendor_id;
        $logs   = get_option( $option, [] );

        array_unshift( $logs, [
            'time'    => current_time( 'mysql' ),
            'level'   => $level,
            'message' => $message,
        ] );

        update_option( $option, array_slice( $logs, 0, self::MAX_LOG_ROWS ) );
    }

    public static function get_log( int $vendor_id ): array {
        return get_option( self::LOG_OPTION . '_' . $vendor_id, [] );
    }
}
