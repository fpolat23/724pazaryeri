<?php
defined( 'ABSPATH' ) || exit;

class TWS_Sync_Manager {

    // Action Scheduler hook isimleri
    const AS_HOOK_SYNC  = 'tws_run_sync';
    const AS_HOOK_CHUNK = 'tws_process_chunk';
    const AS_GROUP      = 'tws';

    const LOG_OPTION    = 'tws_sync_log';
    const QUEUE_OPTION  = 'tws_sync_queue';   // chunk listesi (autoload=no)
    const OFFSET_OPTION = 'tws_sync_offset';
    const STATS_OPTION  = 'tws_sync_stats';
    const MAX_LOG_ROWS  = 150;
    const CHUNK_SIZE    = 15;                 // her seferinde kaç ürün işlenir

    public static function init(): void {
        // Action Scheduler hook'ları
        add_action( self::AS_HOOK_SYNC,  [ __CLASS__, 'run_sync' ] );
        add_action( self::AS_HOOK_CHUNK, [ __CLASS__, 'process_chunk' ] );

        // AJAX
        add_action( 'wp_ajax_tws_manual_sync',    [ __CLASS__, 'ajax_manual_sync' ] );
        add_action( 'wp_ajax_tws_test_connection', [ __CLASS__, 'ajax_test_connection' ] );
        add_action( 'wp_ajax_tws_sync_status',    [ __CLASS__, 'ajax_sync_status' ] );
    }

    // ---------------------------------------------------------------
    // Zamanlama
    // ---------------------------------------------------------------

    public static function activate(): void {
        self::schedule();
    }

    public static function deactivate(): void {
        self::unschedule_all();
        // WP-Cron fallback temizliği
        $ts = wp_next_scheduled( 'tws_scheduled_sync' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'tws_scheduled_sync' );
        }
    }

    /**
     * @param int|null $seconds null → option'dan oku
     */
    public static function reschedule( ?int $seconds = null ): void {
        self::unschedule_all();
        self::schedule( $seconds );
    }

    private static function schedule( ?int $seconds = null ): void {
        if ( $seconds === null ) {
            $seconds = (int) get_option( 'tws_sync_interval_seconds', 3600 );
        }

        if ( self::has_action_scheduler() ) {
            if ( ! as_has_scheduled_action( self::AS_HOOK_SYNC, [], self::AS_GROUP ) ) {
                as_schedule_recurring_action(
                    time() + 60,
                    $seconds,
                    self::AS_HOOK_SYNC,
                    [],
                    self::AS_GROUP
                );
            }
        } else {
            // WP-Cron fallback (Action Scheduler yoksa)
            $wpcron_interval = self::seconds_to_wpcron_interval( $seconds );
            if ( ! wp_next_scheduled( 'tws_scheduled_sync' ) ) {
                add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_intervals' ] );
                wp_schedule_event( time() + 60, $wpcron_interval, 'tws_scheduled_sync' );
            }
            add_action( 'tws_scheduled_sync', [ __CLASS__, 'run_sync' ] );
        }
    }

    private static function unschedule_all(): void {
        if ( self::has_action_scheduler() ) {
            as_unschedule_all_actions( self::AS_HOOK_SYNC,  [], self::AS_GROUP );
            as_unschedule_all_actions( self::AS_HOOK_CHUNK, [], self::AS_GROUP );
        }
    }

    public static function add_cron_intervals( array $schedules ): array {
        $schedules['tws_15min'] = [ 'interval' => 900,   'display' => '15 Dakika' ];
        $schedules['tws_30min'] = [ 'interval' => 1800,  'display' => '30 Dakika' ];
        $schedules['tws_2h']    = [ 'interval' => 7200,  'display' => '2 Saat' ];
        $schedules['tws_6h']    = [ 'interval' => 21600, 'display' => '6 Saat' ];
        $schedules['tws_12h']   = [ 'interval' => 43200, 'display' => '12 Saat' ];
        return $schedules;
    }

    // ---------------------------------------------------------------
    // Ana sync: Trendyol'dan ürünleri çek, kuyruğa al
    // ---------------------------------------------------------------

    public static function run_sync(): void {
        // Zaten işlem devam ediyorsa yeniden başlatma
        if ( get_option( self::QUEUE_OPTION ) !== false ) {
            self::log( 'warning', 'Önceki senkronizasyon henüz tamamlanmadı, yeni başlatma atlandı.' );
            return;
        }

        $api = self::get_api();
        if ( ! $api ) {
            self::log( 'error', 'API bilgileri eksik, senkronizasyon başlatılamadı.' );
            return;
        }

        self::log( 'info', 'Senkronizasyon başlatıldı.' );

        $products = $api->get_all_products();
        if ( is_wp_error( $products ) ) {
            self::log( 'error', 'Trendyol API hatası: ' . $products->get_error_message() );
            return;
        }

        if ( empty( $products ) ) {
            self::log( 'info', 'Aktarılacak ürün bulunamadı.' );
            update_option( 'tws_last_sync', current_time( 'mysql' ) );
            return;
        }

        update_option( self::QUEUE_OPTION, $products, false ); // autoload=no (büyük veri)
        update_option( self::OFFSET_OPTION, 0 );
        update_option( self::STATS_OPTION, [
            'total'     => count( $products ),
            'processed' => 0,
            'imported'  => 0,
            'updated'   => 0,
            'failed'    => 0,
            'started'   => current_time( 'mysql' ),
        ] );

        self::log( 'info', sprintf( '%d ürün kuyruğa alındı, chunk işleme başlıyor…', count( $products ) ) );
        self::enqueue_next_chunk();
    }

    // ---------------------------------------------------------------
    // Chunk işleme: her çalışmada 15 ürün
    // ---------------------------------------------------------------

    public static function process_chunk(): void {
        $queue  = get_option( self::QUEUE_OPTION, [] );
        $offset = (int) get_option( self::OFFSET_OPTION, 0 );
        $chunk  = array_slice( $queue, $offset, self::CHUNK_SIZE );

        if ( empty( $chunk ) ) {
            self::finish_sync();
            return;
        }

        $importer = new TWS_WC_Product_Importer();
        $stats    = get_option( self::STATS_OPTION, [] );

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

        update_option( self::OFFSET_OPTION, $offset + self::CHUNK_SIZE );
        update_option( self::STATS_OPTION, $stats );

        self::enqueue_next_chunk();
    }

    private static function enqueue_next_chunk(): void {
        if ( self::has_action_scheduler() ) {
            as_enqueue_async_action( self::AS_HOOK_CHUNK, [], self::AS_GROUP );
        } else {
            // WP-Cron fallback: senkron devam et (küçük mağazalar için yeterli)
            self::process_chunk();
        }
    }

    private static function finish_sync(): void {
        $stats = get_option( self::STATS_OPTION, [] );

        $summary = sprintf(
            'Tamamlandı — Toplam: %d, Yeni: %d, Güncellenen: %d, Hatalı: %d',
            $stats['total']    ?? 0,
            $stats['imported'] ?? 0,
            $stats['updated']  ?? 0,
            $stats['failed']   ?? 0
        );

        self::log( ( ( $stats['failed'] ?? 0 ) > 0 ) ? 'warning' : 'success', $summary );
        update_option( 'tws_last_sync', current_time( 'mysql' ) );

        // Kuyruğu ve istatistikleri sil
        delete_option( self::QUEUE_OPTION );
        delete_option( self::OFFSET_OPTION );
        // Stats'ı koru (son sync özeti için)
        $stats['finished'] = current_time( 'mysql' );
        update_option( self::STATS_OPTION, $stats );
    }

    // ---------------------------------------------------------------
    // AJAX
    // ---------------------------------------------------------------

    public static function ajax_manual_sync(): void {
        check_ajax_referer( 'tws_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [ 'message' => 'Yetersiz yetki.' ] );
        }

        if ( self::has_action_scheduler() ) {
            // Önceki tamamlanmamış kuyruğu temizle
            delete_option( self::QUEUE_OPTION );
            as_enqueue_async_action( self::AS_HOOK_SYNC, [], self::AS_GROUP );
            wp_send_json_success( [
                'background' => true,
                'message'    => 'Senkronizasyon arka planda başlatıldı.',
            ] );
        } else {
            // Fallback: senkron çalıştır
            self::run_sync();
            $stats = get_option( self::STATS_OPTION, [] );
            wp_send_json_success( [
                'background' => false,
                'message'    => sprintf(
                    'Tamamlandı — Yeni: %d, Güncellenen: %d, Hatalı: %d',
                    $stats['imported'] ?? 0,
                    $stats['updated']  ?? 0,
                    $stats['failed']   ?? 0
                ),
            ] );
        }
    }

    public static function ajax_sync_status(): void {
        check_ajax_referer( 'tws_nonce', 'nonce' );

        $queue      = get_option( self::QUEUE_OPTION, false );
        $in_progress = $queue !== false;
        $stats       = get_option( self::STATS_OPTION, [] );

        $next_sync = '—';
        if ( self::has_action_scheduler() ) {
            $next_ts = as_next_scheduled_action( self::AS_HOOK_SYNC, [], self::AS_GROUP );
            if ( $next_ts ) {
                $next_sync = wp_date( 'd.m.Y H:i', $next_ts );
            }
        } else {
            $next_ts = wp_next_scheduled( 'tws_scheduled_sync' );
            if ( $next_ts ) {
                $next_sync = wp_date( 'd.m.Y H:i', $next_ts );
            }
        }

        $progress_pct = 0;
        if ( $in_progress && ! empty( $stats['total'] ) ) {
            $progress_pct = round( ( $stats['processed'] / $stats['total'] ) * 100 );
        }

        wp_send_json_success( [
            'in_progress'  => $in_progress,
            'stats'        => $stats,
            'progress_pct' => $progress_pct,
            'last_sync'    => get_option( 'tws_last_sync', '—' ),
            'next_sync'    => $next_sync,
            'has_as'       => self::has_action_scheduler(),
        ] );
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

    // ---------------------------------------------------------------
    // Yardımcılar
    // ---------------------------------------------------------------

    public static function get_api(): ?TWS_Trendyol_API {
        $api_key     = get_option( 'tws_api_key', '' );
        $api_secret  = get_option( 'tws_api_secret', '' );
        $supplier_id = get_option( 'tws_supplier_id', '' );

        if ( ! $api_key || ! $api_secret || ! $supplier_id ) {
            return null;
        }

        return new TWS_Trendyol_API( $api_key, $api_secret, $supplier_id );
    }

    private static function has_action_scheduler(): bool {
        return function_exists( 'as_enqueue_async_action' );
    }

    private static function seconds_to_wpcron_interval( int $seconds ): string {
        return match ( true ) {
            $seconds <= 900  => 'tws_15min',
            $seconds <= 1800 => 'tws_30min',
            $seconds <= 3600 => 'hourly',
            $seconds <= 7200 => 'tws_2h',
            $seconds <= 21600 => 'tws_6h',
            $seconds <= 43200 => 'tws_12h',
            default          => 'daily',
        };
    }

    public static function log( string $level, string $message ): void {
        $logs = get_option( self::LOG_OPTION, [] );
        array_unshift( $logs, [
            'time'    => current_time( 'mysql' ),
            'level'   => $level,
            'message' => $message,
        ] );
        update_option( self::LOG_OPTION, array_slice( $logs, 0, self::MAX_LOG_ROWS ) );
    }
}
