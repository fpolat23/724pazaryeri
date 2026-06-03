<?php
defined( 'ABSPATH' ) || exit;

class WC_PSS_Source_Manager {

	private const OPTION = 'wc_pss_sources';

	public static function all(): array {
		return get_option( self::OPTION, [] ) ?: [];
	}

	public static function get( string $id ): ?array {
		$id = strtolower( $id );
		foreach ( self::all() as $src ) {
			if ( strtolower( $src['id'] ?? '' ) === $id ) return $src;
		}
		return null;
	}

	public static function save( array $data ): string {
		$sources = self::all();
		$id      = strtolower( $data['id'] ?? '' );

		if ( $id ) {
			foreach ( $sources as &$src ) {
				if ( strtolower( $src['id'] ?? '' ) === $id ) { $src = array_merge( $src, $data ); break; }
			}
			unset( $src );
		} else {
			$id         = 'src_' . strtolower( wp_generate_password( 8, false ) );
			$data['id'] = $id;
			$sources[]  = $data;
		}

		update_option( self::OPTION, $sources, false );
		return $id;
	}

	public static function delete( string $id ): void {
		$sources = array_values( array_filter( self::all(), fn( $s ) => ( $s['id'] ?? '' ) !== $id ) );
		update_option( self::OPTION, $sources, false );
	}

	/** Return source with decrypted password. */
	public static function get_with_pass( string $id ): ?array {
		$src = self::get( $id );
		if ( ! $src ) return null;
		if ( ! empty( $src['password_enc'] ) ) {
			$src['password'] = self::decrypt( $src['password_enc'] );
		}
		return $src;
	}

	public static function encrypt( string $val ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) return base64_encode( $val );
		$key = substr( wp_salt( 'auth' ), 0, 32 );
		$iv  = substr( wp_salt( 'secure_auth' ), 0, 16 );
		return base64_encode( openssl_encrypt( $val, 'AES-256-CBC', $key, 0, $iv ) );
	}

	public static function decrypt( string $val ): string {
		if ( ! function_exists( 'openssl_decrypt' ) ) return base64_decode( $val );
		$key = substr( wp_salt( 'auth' ), 0, 32 );
		$iv  = substr( wp_salt( 'secure_auth' ), 0, 16 );
		return openssl_decrypt( base64_decode( $val ), 'AES-256-CBC', $key, 0, $iv ) ?: '';
	}
}
