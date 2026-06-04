<?php
defined( 'ABSPATH' ) || exit;

class WC_RM_Api_Client {

	private string $base_url;
	private string $consumer_key;
	private string $consumer_secret;
	private int    $timeout = 60;

	public function __construct( string $base_url, string $consumer_key, string $consumer_secret ) {
		$this->base_url        = rtrim( $base_url, '/' );
		$this->consumer_key    = $consumer_key;
		$this->consumer_secret = $consumer_secret;
	}

	// ---- Public methods ----

	public function test(): array {
		$raw = $this->get_raw( 'products', [ 'per_page' => 1, 'status' => 'any' ] );
		if ( is_wp_error( $raw ) ) {
			return [ 'ok' => false, 'message' => $raw->get_error_message(), 'total' => 0 ];
		}
		$code = wp_remote_retrieve_response_code( $raw );
		if ( $code === 401 || $code === 403 ) {
			return [ 'ok' => false, 'message' => "Yetkilendirme hatası (HTTP {$code}). Consumer Key/Secret'i kontrol edin.", 'total' => 0 ];
		}
		if ( $code < 200 || $code >= 300 ) {
			$body = json_decode( wp_remote_retrieve_body( $raw ), true );
			return [ 'ok' => false, 'message' => ( $body['message'] ?? "HTTP {$code}" ), 'total' => 0 ];
		}
		$total = (int) wp_remote_retrieve_header( $raw, 'x-wp-total' );
		return [ 'ok' => true, 'total' => $total, 'message' => "Bağlantı başarılı. Toplam {$total} ürün." ];
	}

	/** Returns decoded JSON array or WP_Error. Also provides total via X-WP-Total header. */
	public function get_products( int $page, int $per_page = 20, string $status = 'any' ): array|WP_Error {
		return $this->get( 'products', [
			'page'     => $page,
			'per_page' => $per_page,
			'status'   => $status,
			'orderby'  => 'id',
			'order'    => 'asc',
		] );
	}

	/** Returns total product count from header (cheap call). */
	public function get_product_count( string $status = 'any' ): int {
		$raw = $this->get_raw( 'products', [ 'per_page' => 1, 'status' => $status ] );
		if ( is_wp_error( $raw ) ) return 0;
		return (int) wp_remote_retrieve_header( $raw, 'x-wp-total' );
	}

	/** Fetch up to 100 variations in one call; returns array or WP_Error. */
	public function get_variations( int $product_id ): array|WP_Error {
		$all  = [];
		$page = 1;
		do {
			$batch = $this->get( "products/{$product_id}/variations", [
				'page'     => $page,
				'per_page' => 100,
				'orderby'  => 'id',
				'order'    => 'asc',
			] );
			if ( is_wp_error( $batch ) ) return $batch;
			$all  = array_merge( $all, $batch );
			$page++;
		} while ( count( $batch ) === 100 );
		return $all;
	}

	// ---- Private helpers ----

	private function get( string $endpoint, array $params = [] ): array|WP_Error {
		$raw = $this->get_raw( $endpoint, $params );
		if ( is_wp_error( $raw ) ) return $raw;

		$code = wp_remote_retrieve_response_code( $raw );
		$body = json_decode( wp_remote_retrieve_body( $raw ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'api_error', $body['message'] ?? "HTTP {$code}" );
		}
		return is_array( $body ) ? $body : [];
	}

	private function get_raw( string $endpoint, array $params = [] ) {
		$url = $this->base_url . '/wp-json/wc/v3/' . ltrim( $endpoint, '/' );
		if ( $params ) $url = add_query_arg( $params, $url );

		return wp_remote_get( $url, [
			'timeout'   => $this->timeout,
			'sslverify' => false,
			'headers'   => [
				'Authorization' => 'Basic ' . base64_encode( $this->consumer_key . ':' . $this->consumer_secret ),
			],
		] );
	}
}
