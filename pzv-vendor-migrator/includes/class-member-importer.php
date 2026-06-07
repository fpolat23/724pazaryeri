<?php
/**
 * Imports WooCommerce customer accounts on the DESTINATION site.
 */
defined( 'ABSPATH' ) || exit;

class PZV_Mig_Member_Importer {

	const WC_BILLING_FIELDS = [
		'billing_first_name', 'billing_last_name', 'billing_company',
		'billing_address_1', 'billing_address_2', 'billing_city',
		'billing_state', 'billing_postcode', 'billing_country',
		'billing_email', 'billing_phone',
	];

	const WC_SHIPPING_FIELDS = [
		'shipping_first_name', 'shipping_last_name', 'shipping_company',
		'shipping_address_1', 'shipping_address_2', 'shipping_city',
		'shipping_state', 'shipping_postcode', 'shipping_country', 'shipping_phone',
	];

	/**
	 * @param array $m  Member data from source API.
	 * @return array{status:string, user_id:int, message:string}
	 */
	public static function import_member( array $m ): array {
		$email    = sanitize_email( $m['email'] ?? '' );
		$username = sanitize_user( $m['username'] ?? '', true );

		if ( ! $email ) {
			return [ 'status' => 'error', 'user_id' => 0, 'message' => 'E-posta eksik.' ];
		}

		$existing = get_user_by( 'email', $email ) ?: get_user_by( 'login', $username );

		if ( $existing ) {
			$user_id = $existing->ID;
			$action  = 'updated';
		} else {
			$base_login = $username ?: sanitize_title( $email );
			$login      = $base_login;
			$suffix     = 2;
			while ( username_exists( $login ) ) {
				$login = $base_login . $suffix++;
			}

			$user_id = wp_create_user( $login, wp_generate_password( 24, true, true ), $email );
			if ( is_wp_error( $user_id ) ) {
				return [ 'status' => 'error', 'user_id' => 0, 'message' => $user_id->get_error_message() ];
			}

			$wp_user = new WP_User( $user_id );
			$wp_user->set_role( 'customer' );
			$action = 'created';
		}

		wp_update_user( [
			'ID'           => $user_id,
			'display_name' => sanitize_text_field( $m['display_name'] ?? '' ),
			'first_name'   => sanitize_text_field( $m['first_name']   ?? '' ),
			'last_name'    => sanitize_text_field( $m['last_name']    ?? '' ),
		] );

		$all_meta = array_merge( self::WC_BILLING_FIELDS, self::WC_SHIPPING_FIELDS );
		foreach ( $all_meta as $key ) {
			if ( array_key_exists( $key, $m ) ) {
				update_user_meta( $user_id, $key, sanitize_text_field( (string) $m[ $key ] ) );
			}
		}

		return [ 'status' => $action, 'user_id' => $user_id, 'message' => '' ];
	}
}
