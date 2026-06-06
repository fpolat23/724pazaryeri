<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PZV_Commission {
    public function __construct() {
        add_action( 'woocommerce_order_status_completed', array( $this, 'record_on_complete' ) );
        add_action( 'woocommerce_order_status_refunded',  array( $this, 'cancel_on_refund' ) );
    }

    /** Komisyon oranı hesapla:
     *  1. İlk 3 ay (90 gün) → %0 (yeni satıcı teşviki)
     *  2. Vendor override (admin tarafından atanmış sabit oran)
     *  3. Kategori bazlı oran (en yüksek kategori oranı)
     *  4. Global varsayılan oran
     */
    public static function calculate_rate( $product_id, $vendor_id ) {
        // ── 1. İlk 3 ay sıfır komisyon ──────────────────────────────
        $approved_at = get_user_meta( $vendor_id, 'pzv_approved_at', true );
        if ( ! $approved_at ) {
            $user = get_userdata( $vendor_id );
            $approved_at = $user ? $user->user_registered : null;
        }
        if ( $approved_at ) {
            $diff_days = ( time() - strtotime( $approved_at ) ) / DAY_IN_SECONDS;
            if ( $diff_days < 90 ) return 0.0;
        }

        // ── 2. Vendor override (sadece admin atayabilir) ─────────────
        $vendor_rate = get_user_meta( $vendor_id, 'pzv_commission_override', true );
        if ( $vendor_rate !== '' && $vendor_rate !== false && is_numeric( $vendor_rate ) ) {
            return (float) $vendor_rate;
        }

        // ── 3. Kategori bazlı oran (en yüksek) ──────────────────────
        $cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
        $cat_rates = array();
        if ( ! is_wp_error( $cats ) ) {
            foreach ( $cats as $cid ) {
                $r = get_term_meta( $cid, 'pzv_commission', true );
                if ( $r !== '' && is_numeric( $r ) ) $cat_rates[] = (float) $r;
            }
        }
        if ( ! empty( $cat_rates ) ) return max( $cat_rates );

        // ── 4. Global varsayılan ────────────────────────────────────
        return (float) get_option( 'pzv_default_commission', 10 );
    }

    public function record_on_complete( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        if ( $order->get_meta( '_pzv_commissions_recorded' ) === 'yes' ) return;
        global $wpdb;
        $table = self::table_name();
        self::create_table();
        foreach ( $order->get_items() as $item ) {
            $pid = $item->get_product_id();
            $vendor_id = (int) get_post_field( 'post_author', $pid );
            if ( ! PZV_Roles::is_vendor( $vendor_id ) ) continue;
            $line_total = (float) $item->get_total();
            $qty = (int) $item->get_quantity();
            $rate = self::calculate_rate( $pid, $vendor_id );
            $commission = round( $line_total * ( $rate / 100 ), 2 );
            $vendor_amt = round( $line_total - $commission, 2 );
            $wpdb->insert( $table, array(
                'order_id' => $order_id, 'order_item_id' => $item->get_id(),
                'product_id' => $pid, 'vendor_id' => $vendor_id,
                'qty' => $qty, 'line_total' => $line_total,
                'commission_rate' => $rate, 'commission_amt' => $commission,
                'vendor_amt' => $vendor_amt, 'status' => 'pending',
                'created_at' => current_time( 'mysql' ),
            ), array('%d','%d','%d','%d','%d','%f','%f','%f','%f','%s','%s') );
        }
        $order->update_meta_data( '_pzv_commissions_recorded', 'yes' );
        $order->save();
    }

    public function cancel_on_refund( $order_id ) {
        global $wpdb;
        $wpdb->update( self::table_name(),
            array( 'status' => 'cancelled' ),
            array( 'order_id' => $order_id ),
            array( '%s' ), array( '%d' )
        );
    }

    public static function vendor_pending( $vendor_id ) {
        global $wpdb;
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(vendor_amt) FROM " . self::table_name() . " WHERE vendor_id = %d AND status = 'pending'",
            $vendor_id
        ) );
    }

    public static function vendor_paid( $vendor_id ) {
        global $wpdb;
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(vendor_amt) FROM " . self::table_name() . " WHERE vendor_id = %d AND status = 'paid'",
            $vendor_id
        ) );
    }

    public static function vendor_total_sales( $vendor_id ) {
        global $wpdb;
        return (float) $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(line_total) FROM " . self::table_name() . " WHERE vendor_id = %d AND status IN ('pending','paid')",
            $vendor_id
        ) );
    }

    public static function vendor_records( $vendor_id, $limit = 100 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table_name() . " WHERE vendor_id = %d ORDER BY id DESC LIMIT %d",
            $vendor_id, $limit
        ) );
    }

    public static function pending_payouts() {
        global $wpdb;
        return $wpdb->get_results( "
            SELECT vendor_id, SUM(vendor_amt) AS total, COUNT(*) AS items
            FROM " . self::table_name() . "
            WHERE status = 'pending'
            GROUP BY vendor_id ORDER BY total DESC
        " );
    }

    public static function mark_vendor_paid( $vendor_id, $note = '' ) {
        global $wpdb;
        return $wpdb->update( self::table_name(),
            array( 'status' => 'paid', 'paid_at' => current_time( 'mysql' ), 'paid_note' => $note ),
            array( 'vendor_id' => $vendor_id, 'status' => 'pending' ),
            array( '%s', '%s', '%s' ), array( '%d', '%s' )
        );
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'pzv_commissions';
    }

    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
        $exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
        if ( $exists === $table ) return;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            order_item_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED NOT NULL,
            vendor_id BIGINT(20) UNSIGNED NOT NULL,
            qty INT(11) NOT NULL DEFAULT 1,
            line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
            commission_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
            commission_amt DECIMAL(12,2) NOT NULL DEFAULT 0,
            vendor_amt DECIMAL(12,2) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NULL,
            paid_at DATETIME NULL,
            paid_note TEXT NULL,
            PRIMARY KEY (id),
            KEY vendor_id (vendor_id),
            KEY order_id (order_id),
            KEY status (status)
        ) $charset;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
