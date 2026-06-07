<?php
/**
 * Business logic for importing vendors and linking products on the DESTINATION site.
 */
defined( 'ABSPATH' ) || exit;

class PZV_Mig_Vendor_Importer {

	const META_FIELDS = [
		'pzv_store_name', 'pzv_store_slug', 'pzv_phone', 'pzv_city',
		'pzv_address', 'pzv_description', 'pzv_iban', 'pzv_tc_or_tax',
		'pzv_approved_at', 'pzv_commission_override', 'pzv_dispatch_days',
		'pzv_instagram_url', 'pzv_twitter_url', 'pzv_return_policy',
		'pzv_working_hours', 'pzv_status',
	];

	// ---- Import a single vendor ----

	/**
	 * @param array $v   Vendor data from source API.
	 * @return array{status:string, user_id:int, store_name:string, message:string}
	 */
	public static function import_vendor( array $v ): array {
		$email    = sanitize_email( $v['email'] ?? '' );
		$username = sanitize_user( $v['username'] ?? '', true );

		if ( ! $email ) {
			return [ 'status' => 'error', 'user_id' => 0, 'store_name' => $v['store_name'] ?? '', 'message' => 'E-posta eksik.' ];
		}

		// Find existing by email first, then by username
		$existing = get_user_by( 'email', $email ) ?: get_user_by( 'login', $username );

		if ( $existing ) {
			$user_id = $existing->ID;
			$wp_user = new WP_User( $user_id );
			if ( ! in_array( 'pzv_vendor', (array) $wp_user->roles, true ) ) {
				$wp_user->add_role( 'pzv_vendor' );
			}
			$action = 'updated';
		} else {
			// Ensure unique username
			$base_login = $username ?: sanitize_title( $v['store_name'] ?? $email );
			$login      = $base_login;
			$suffix     = 2;
			while ( username_exists( $login ) ) {
				$login = $base_login . $suffix++;
			}

			$user_id = wp_create_user( $login, wp_generate_password( 24, true, true ), $email );
			if ( is_wp_error( $user_id ) ) {
				return [ 'status' => 'error', 'user_id' => 0, 'store_name' => $v['store_name'] ?? '', 'message' => $user_id->get_error_message() ];
			}

			$wp_user = new WP_User( $user_id );
			$wp_user->set_role( 'pzv_vendor' );
			$action = 'created';
		}

		// Display name
		wp_update_user( [
			'ID'           => $user_id,
			'display_name' => sanitize_text_field( $v['display_name'] ?? $v['store_name'] ?? '' ),
		] );

		// PZV meta fields
		foreach ( self::META_FIELDS as $key ) {
			if ( array_key_exists( $key, $v ) ) {
				update_user_meta( $user_id, $key, $v[ $key ] );
			}
		}

		// Source ID mapping (used by product-linking phase)
		update_user_meta( $user_id, '_pzv_source_id', (int) ( $v['id'] ?? 0 ) );

		// Images
		if ( ! empty( $v['logo_url'] ) ) {
			$lid = self::sideload_image( $v['logo_url'] );
			if ( $lid > 0 ) update_user_meta( $user_id, 'pzv_logo', $lid );
		}
		if ( ! empty( $v['banner_url'] ) ) {
			$bid = self::sideload_image( $v['banner_url'] );
			if ( $bid > 0 ) update_user_meta( $user_id, 'pzv_banner', $bid );
		}

		return [
			'status'     => $action,
			'user_id'    => $user_id,
			'store_name' => sanitize_text_field( $v['store_name'] ?? '' ),
			'message'    => '',
		];
	}

	// ---- Link products to a vendor by SKU array ----

	/**
	 * @param  string[] $skus
	 * @return array{linked:int, missing:int}
	 */
	public static function link_products( int $dest_vendor_id, array $skus ): array {
		global $wpdb;
		$linked  = 0;
		$missing = 0;

		foreach ( $skus as $sku ) {
			$sku = sanitize_text_field( $sku );
			if ( $sku === '' ) continue;

			$product_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = '_sku' AND meta_value = %s
				 LIMIT 1",
				$sku
			) );

			if ( ! $product_id ) { $missing++; continue; }

			$wpdb->update(
				$wpdb->posts,
				[ 'post_author' => $dest_vendor_id ],
				[ 'ID' => $product_id ],
				[ '%d' ], [ '%d' ]
			);
			clean_post_cache( $product_id );
			$linked++;
		}

		return [ 'linked' => $linked, 'missing' => $missing ];
	}

	// ---- Import a commission record ----

	public static function import_commission( array $rec ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'pzv_commissions';

		// Ensure table exists
		if ( class_exists( 'PZV_Commission' ) ) {
			PZV_Commission::create_table();
		} else {
			// Minimal table creation if PZV_Commission not available
			$charset = $wpdb->get_charset_collate();
			$wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				order_item_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				vendor_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
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
				KEY vendor_id (vendor_id)
			) {$charset}" );
		}

		// Map vendor by email
		$dest_vendor_id = 0;
		if ( ! empty( $rec['vendor_email'] ) ) {
			$u = get_user_by( 'email', sanitize_email( $rec['vendor_email'] ) );
			if ( $u ) $dest_vendor_id = $u->ID;
		}
		if ( ! $dest_vendor_id ) return false;

		// Map product by SKU
		$dest_product_id = 0;
		if ( ! empty( $rec['product_sku'] ) ) {
			$dest_product_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1",
				$rec['product_sku']
			) );
		}

		$result = $wpdb->insert( $table, [
			'order_id'        => 0,                               // orders not migrated
			'order_item_id'   => 0,
			'product_id'      => $dest_product_id,
			'vendor_id'       => $dest_vendor_id,
			'qty'             => max( 1, (int) ( $rec['qty'] ?? 1 ) ),
			'line_total'      => (float) ( $rec['line_total']     ?? 0 ),
			'commission_rate' => (float) ( $rec['commission_rate'] ?? 0 ),
			'commission_amt'  => (float) ( $rec['commission_amt']  ?? 0 ),
			'vendor_amt'      => (float) ( $rec['vendor_amt']      ?? 0 ),
			'status'          => sanitize_key( $rec['status'] ?? 'pending' ),
			'created_at'      => $rec['created_at'] ?? current_time( 'mysql' ),
			'paid_at'         => $rec['paid_at'] ?: null,
			'paid_note'       => $rec['paid_note'] ?? '',
		], [ '%d','%d','%d','%d','%d','%f','%f','%f','%f','%s','%s','%s','%s' ] );

		return $result !== false;
	}

	// ---- Image sideload helper ----

	private static function sideload_image( string $url ): int {
		if ( ! $url ) return 0;
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) return 0;

		$path     = parse_url( $url, PHP_URL_PATH );
		$ext      = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ?: 'jpg';
		$filename = 'vendor-img-' . substr( md5( $url ), 0, 8 ) . '.' . $ext;
		$file     = [ 'name' => sanitize_file_name( $filename ), 'tmp_name' => $tmp ];

		$attach_id = media_handle_sideload( $file, 0 );
		if ( is_wp_error( $attach_id ) ) { @unlink( $tmp ); return 0; }
		return $attach_id;
	}
}
