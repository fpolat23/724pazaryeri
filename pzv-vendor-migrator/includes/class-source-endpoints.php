<?php
/**
 * REST API endpoints exposed on the SOURCE site.
 * Requires PazarYeri Vendor System (pazaryeri-vendor.php) to be active.
 * Auth: WordPress Application Password (HTTP Basic).
 */
defined( 'ABSPATH' ) || exit;

class PZV_Mig_Source_Endpoints {

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register' ] );
	}

	public static function register(): void {
		$ns = PZV_MIG_API_NS;

		// GET /wp-json/pzv-mig/v1/vendors   — paginated vendor list
		register_rest_route( $ns, '/vendors', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'list_vendors' ],
			'permission_callback' => [ __CLASS__, 'auth' ],
			'args'                => [
				'page'     => [ 'default' => 1,  'sanitize_callback' => 'absint' ],
				'per_page' => [ 'default' => 20, 'sanitize_callback' => 'absint' ],
			],
		] );

		// GET /wp-json/pzv-mig/v1/vendors/{id}/skus   — product SKUs of a vendor (paginated)
		register_rest_route( $ns, '/vendors/(?P<id>\d+)/skus', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'vendor_skus' ],
			'permission_callback' => [ __CLASS__, 'auth' ],
			'args'                => [
				'page'     => [ 'default' => 1,   'sanitize_callback' => 'absint' ],
				'per_page' => [ 'default' => 200, 'sanitize_callback' => 'absint' ],
			],
		] );

		// GET /wp-json/pzv-mig/v1/members   — paginated customer list
		register_rest_route( $ns, '/members', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'list_members' ],
			'permission_callback' => [ __CLASS__, 'auth' ],
			'args'                => [
				'page'     => [ 'default' => 1,  'sanitize_callback' => 'absint' ],
				'per_page' => [ 'default' => 50, 'sanitize_callback' => 'absint' ],
			],
		] );

		// GET /wp-json/pzv-mig/v1/commissions   — paginated commission records
		register_rest_route( $ns, '/commissions', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'list_commissions' ],
			'permission_callback' => [ __CLASS__, 'auth' ],
			'args'                => [
				'page'     => [ 'default' => 1,   'sanitize_callback' => 'absint' ],
				'per_page' => [ 'default' => 200, 'sanitize_callback' => 'absint' ],
			],
		] );
	}

	public static function auth(): bool {
		return current_user_can( 'manage_options' );
	}

	// ---- Vendors ----

	public static function list_vendors( WP_REST_Request $r ): WP_REST_Response {
		if ( ! class_exists( 'PZV_Vendor' ) ) {
			return new WP_REST_Response(
				[ 'code' => 'pzv_not_active', 'message' => 'PazarYeri Vendor System eklentisi aktif değil.' ],
				404
			);
		}

		$page     = max( 1, (int) $r['page'] );
		$per_page = min( 50, max( 1, (int) $r['per_page'] ) );
		$all      = PZV_Vendor::get_all();   // all WP_User objects with pzv_vendor role
		$total    = count( $all );
		$sliced   = array_slice( $all, ( $page - 1 ) * $per_page, $per_page );

		$data = [];
		foreach ( $sliced as $u ) {
			$v = PZV_Vendor::get( $u->ID );
			if ( ! $v ) continue;

			$v['logo_url']   = ( $v['logo']   > 0 ) ? ( wp_get_attachment_url( (int) $v['logo'] )   ?: '' ) : '';
			$v['banner_url'] = ( $v['banner'] > 0 ) ? ( wp_get_attachment_url( (int) $v['banner'] ) ?: '' ) : '';
			$v['status']     = get_user_meta( $u->ID, 'pzv_status', true ) ?: 'active';
			$v['email']      = $u->user_email;
			$v['username']   = $u->user_login;

			$data[] = $v;
		}

		$resp = new WP_REST_Response( $data );
		$resp->header( 'X-PZV-Total', (string) $total );
		$resp->header( 'X-PZV-Pages', (string) max( 1, (int) ceil( $total / $per_page ) ) );
		return $resp;
	}

	// ---- Vendor SKUs ----

	public static function vendor_skus( WP_REST_Request $r ): WP_REST_Response {
		if ( ! class_exists( 'PZV_Vendor' ) ) {
			$resp = new WP_REST_Response( [] );
			$resp->header( 'X-PZV-Total', '0' );
			$resp->header( 'X-PZV-Pages', '1' );
			return $resp;
		}

		$vid      = (int) $r['id'];
		$page     = max( 1, (int) $r['page'] );
		$per_page = min( 200, max( 1, (int) $r['per_page'] ) );

		global $wpdb;
		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'product'
			 AND post_status IN ('publish','draft','pending','private')
			 AND post_author = %d",
			$vid
		) );

		$paged_ids = PZV_Vendor::get_product_ids( $vid, [
			'posts_per_page' => $per_page,
			'paged'          => $page,
		] );

		$skus = [];
		foreach ( (array) $paged_ids as $pid ) {
			$sku = get_post_meta( (int) $pid, '_sku', true );
			if ( $sku !== '' && $sku !== false ) {
				$skus[] = $sku;
			}
		}

		$resp = new WP_REST_Response( $skus );
		$resp->header( 'X-PZV-Total', (string) $total );
		$resp->header( 'X-PZV-Pages', (string) max( 1, (int) ceil( $total / $per_page ) ) );
		return $resp;
	}

	// ---- Members ----

	public static function list_members( WP_REST_Request $r ): WP_REST_Response {
		$page     = max( 1, (int) $r['page'] );
		$per_page = min( 100, max( 1, (int) $r['per_page'] ) );

		$query = new WP_User_Query( [
			'role'        => 'customer',
			'number'      => $per_page,
			'offset'      => ( $page - 1 ) * $per_page,
			'orderby'     => 'ID',
			'order'       => 'ASC',
			'count_total' => true,
		] );

		$total = $query->get_total();
		$users = $query->get_results();

		$wc_meta_keys = [
			'billing_first_name', 'billing_last_name', 'billing_company',
			'billing_address_1', 'billing_address_2', 'billing_city',
			'billing_state', 'billing_postcode', 'billing_country',
			'billing_email', 'billing_phone',
			'shipping_first_name', 'shipping_last_name', 'shipping_company',
			'shipping_address_1', 'shipping_address_2', 'shipping_city',
			'shipping_state', 'shipping_postcode', 'shipping_country', 'shipping_phone',
		];

		$data = [];
		foreach ( $users as $u ) {
			$row = [
				'id'           => $u->ID,
				'email'        => $u->user_email,
				'username'     => $u->user_login,
				'display_name' => $u->display_name,
				'first_name'   => $u->first_name,
				'last_name'    => $u->last_name,
				'registered'   => $u->user_registered,
			];
			foreach ( $wc_meta_keys as $key ) {
				$row[ $key ] = get_user_meta( $u->ID, $key, true );
			}
			$data[] = $row;
		}

		$resp = new WP_REST_Response( $data );
		$resp->header( 'X-PZV-Total', (string) $total );
		$resp->header( 'X-PZV-Pages', (string) max( 1, (int) ceil( $total / $per_page ) ) );
		return $resp;
	}

	// ---- Commissions ----

	public static function list_commissions( WP_REST_Request $r ): WP_REST_Response {
		if ( ! class_exists( 'PZV_Commission' ) ) {
			$resp = new WP_REST_Response( [] );
			$resp->header( 'X-PZV-Total', '0' );
			$resp->header( 'X-PZV-Pages', '1' );
			return $resp;
		}

		global $wpdb;
		$page     = max( 1, (int) $r['page'] );
		$per_page = min( 200, max( 1, (int) $r['per_page'] ) );
		$offset   = ( $page - 1 ) * $per_page;
		$table    = $wpdb->prefix . 'pzv_commissions';

		$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$records = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
			$per_page, $offset
		) );

		// Annotate with vendor email + product SKU for destination mapping
		foreach ( $records as &$rec ) {
			$uid             = (int) $rec->vendor_id;
			$u               = get_userdata( $uid );
			$rec->vendor_email = $u ? $u->user_email : '';
			$rec->product_sku  = get_post_meta( (int) $rec->product_id, '_sku', true ) ?: '';
		}
		unset( $rec );

		$resp = new WP_REST_Response( $records );
		$resp->header( 'X-PZV-Total', (string) $total );
		$resp->header( 'X-PZV-Pages', (string) max( 1, (int) ceil( $total / $per_page ) ) );
		return $resp;
	}
}
