<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PZV_Vendor {
    public static function get( $vendor_id ) {
        $user = get_userdata( $vendor_id );
        if ( ! $user || ! PZV_Roles::is_vendor( $vendor_id ) ) return null;
        return array(
            'id'           => $vendor_id,
            'username'     => $user->user_login,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'registered'   => $user->user_registered,
            'store_name'   => get_user_meta( $vendor_id, 'pzv_store_name', true ) ?: $user->display_name,
            'store_slug'   => get_user_meta( $vendor_id, 'pzv_store_slug', true ) ?: sanitize_title( $user->user_login ),
            'phone'        => get_user_meta( $vendor_id, 'pzv_phone', true ),
            'city'         => get_user_meta( $vendor_id, 'pzv_city', true ),
            'address'      => get_user_meta( $vendor_id, 'pzv_address', true ),
            'description'  => get_user_meta( $vendor_id, 'pzv_description', true ),
            'iban'         => get_user_meta( $vendor_id, 'pzv_iban', true ),
            'tc_or_tax'    => get_user_meta( $vendor_id, 'pzv_tc_or_tax', true ),
            'approved_at'  => get_user_meta( $vendor_id, 'pzv_approved_at', true ),
            'commission_override' => get_user_meta( $vendor_id, 'pzv_commission_override', true ),
            'logo'                => (int) get_user_meta( $vendor_id, 'pzv_logo', true ),
            'banner'              => (int) get_user_meta( $vendor_id, 'pzv_banner', true ),
        );
    }

    public static function get_product_ids( $vendor_id, $args = array() ) {
        $defaults = array(
            'post_type'      => 'product',
            'author'         => $vendor_id,
            'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        );
        $q = new WP_Query( wp_parse_args( $args, $defaults ) );
        return $q->posts;
    }

    public static function get_order_ids( $vendor_id ) {
        global $wpdb;
        $product_ids = self::get_product_ids( $vendor_id );
        if ( empty( $product_ids ) ) return array();
        $ids_in = implode( ',', array_map( 'intval', $product_ids ) );
        return array_map( 'intval', $wpdb->get_col( "
            SELECT DISTINCT oi.order_id
            FROM {$wpdb->prefix}woocommerce_order_items AS oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS oim ON oi.order_item_id = oim.order_item_id
            WHERE oim.meta_key = '_product_id' AND oim.meta_value IN ($ids_in)
              AND oi.order_item_type = 'line_item'
            ORDER BY oi.order_id DESC
        " ) );
    }

    public static function store_url( $vendor_id ) {
        $slug = get_user_meta( $vendor_id, 'pzv_store_slug', true );
        if ( ! $slug ) {
            $u = get_userdata( $vendor_id );
            $slug = $u ? sanitize_title( $u->user_login ) : '';
        }
        return $slug ? home_url( '/magaza/' . $slug . '/' ) : '';
    }

    public static function get_all() {
        return get_users( array( 'role' => PZV_ROLE, 'orderby' => 'registered', 'order' => 'DESC' ) );
    }
}
