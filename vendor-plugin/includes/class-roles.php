<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PZV_Roles {
    public static function create_role() {
        add_role( PZV_ROLE, 'Satıcı', array(
            'read'                      => true,
            'upload_files'              => true,
            'edit_products'             => true,
            'edit_published_products'   => true,
            'publish_products'          => true,
            'delete_products'           => true,
            'delete_published_products' => true,
            'read_private_products'     => true,
            'edit_shop_orders'          => true,
            'read_private_shop_orders'  => true,
            'manage_product_terms'      => false,
        ) );
    }

    public static function is_vendor( $user_id = 0 ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        if ( ! $user_id ) return false;
        $user = get_userdata( $user_id );
        if ( ! $user ) return false;
        $roles = (array) $user->roles;
        if ( in_array( PZV_ROLE, $roles, true ) ) return true;
        // Admin ve editör de vendor gibi davranır (kendi mağazaları olabilir)
        if ( array_intersect( $roles, array( 'administrator', 'shop_manager', 'editor' ) ) ) return true;
        return false;
    }

    /**
     * "Saf" vendor mı? (admin/editor değil) - kısıtlamalar için
     */
    public static function is_pure_vendor( $user_id = 0 ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        if ( ! $user_id ) return false;
        $user = get_userdata( $user_id );
        if ( ! $user ) return false;
        $roles = (array) $user->roles;
        if ( ! in_array( PZV_ROLE, $roles, true ) ) return false;
        if ( array_intersect( $roles, array( 'administrator', 'shop_manager', 'editor' ) ) ) return false;
        return true;
    }

    public static function make_vendor( $user_id, $meta = array() ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return false;
        $u = new WP_User( $user_id );
        $u->set_role( PZV_ROLE );
        foreach ( $meta as $k => $v ) {
            update_user_meta( $user_id, 'pzv_' . $k, $v );
        }
        update_user_meta( $user_id, 'pzv_approved_at', current_time( 'mysql' ) );
        return true;
    }
}

add_action( 'init', function () {
    if ( ! get_role( PZV_ROLE ) ) PZV_Roles::create_role();
}, 5 );
