<?php
/**
 * HTTP client used on the DESTINATION site to call source endpoints.
 * Auth: WordPress Application Password (HTTP Basic).
 */
defined( 'ABSPATH' ) || exit;

class PZV_Mig_Api_Client {

	private string $base;
	private string $auth_header;

	public function __construct( string $source_url, string $username, string $app_password ) {
		$this->base        = rtrim( $source_url, '/' );
		// App passwords may have spaces — strip them when encoding
		$this->auth_header = 'Basic ' . base64_encode( $username . ':' . str_replace( ' ', '', $app_password ) );
	}

	// ---- Test ----

	public function test(): array {
		$raw = $this->raw( '/wp-json/pzv-mig/v1/vendors', [ 'per_page' => 1 ] );
		if ( is_wp_error( $raw ) ) {
			return [ 'ok' => false, 'message' => $raw->get_error_message(), 'total' => 0 ];
		}
		$code = wp_remote_retrieve_response_code( $raw );
		if ( $code === 401 || $code === 403 ) {
			return [ 'ok' => false, 'message' => "Yetkilendirme hatası (HTTP {$code}). Kullanıcı adı / Uygulama Şifresi kontrol edin.", 'total' => 0 ];
		}
		if ( $code === 404 ) {
			$body = json_decode( wp_remote_retrieve_body( $raw ), true );
			if ( isset( $body['code'] ) && $body['code'] === 'pzv_not_active' ) {
				return [ 'ok' => false, 'message' => 'Kaynak sitede PazarYeri Vendor System eklentisi aktif değil.', 'total' => 0 ];
			}
			return [ 'ok' => false, 'message' => 'PZV Vendor Migrator kaynak eklentisi bulunamadı (HTTP 404). Kaynak siteye de yükleyin.', 'total' => 0 ];
		}
		if ( $code < 200 || $code >= 300 ) {
			$body = json_decode( wp_remote_retrieve_body( $raw ), true );
			return [ 'ok' => false, 'message' => $body['message'] ?? "HTTP {$code}", 'total' => 0 ];
		}
		$total = (int) wp_remote_retrieve_header( $raw, 'x-pzv-total' );
		return [ 'ok' => true, 'total' => $total, 'message' => "Bağlantı başarılı. Toplam {$total} satıcı." ];
	}

	// ---- Members ----

	public function get_member_count(): int {
		$raw = $this->raw( '/wp-json/pzv-mig/v1/members', [ 'per_page' => 1 ] );
		if ( is_wp_error( $raw ) ) return 0;
		return (int) wp_remote_retrieve_header( $raw, 'x-pzv-total' );
	}

	public function get_members( int $page, int $per_page = 50 ): array|WP_Error {
		return $this->get( '/wp-json/pzv-mig/v1/members', [
			'page'     => $page,
			'per_page' => $per_page,
		] );
	}

	// ---- Vendors ----

	public function get_vendor_count(): int {
		$raw = $this->raw( '/wp-json/pzv-mig/v1/vendors', [ 'per_page' => 1 ] );
		if ( is_wp_error( $raw ) ) return 0;
		return (int) wp_remote_retrieve_header( $raw, 'x-pzv-total' );
	}

	public function get_vendors( int $page, int $per_page = 20 ): array|WP_Error {
		return $this->get( '/wp-json/pzv-mig/v1/vendors', [
			'page'     => $page,
			'per_page' => $per_page,
		] );
	}

	// ---- Vendor SKUs ----

	public function get_vendor_sku_count( int $source_vendor_id ): int {
		$raw = $this->raw( "/wp-json/pzv-mig/v1/vendors/{$source_vendor_id}/skus", [ 'per_page' => 1 ] );
		if ( is_wp_error( $raw ) ) return 0;
		return (int) wp_remote_retrieve_header( $raw, 'x-pzv-total' );
	}

	public function get_vendor_skus( int $source_vendor_id, int $page = 1, int $per_page = 200 ): array|WP_Error {
		return $this->get( "/wp-json/pzv-mig/v1/vendors/{$source_vendor_id}/skus", [
			'page'     => $page,
			'per_page' => $per_page,
		] );
	}

	// ---- Commissions ----

	public function get_commission_count(): int {
		$raw = $this->raw( '/wp-json/pzv-mig/v1/commissions', [ 'per_page' => 1 ] );
		if ( is_wp_error( $raw ) ) return 0;
		return (int) wp_remote_retrieve_header( $raw, 'x-pzv-total' );
	}

	public function get_commissions( int $page, int $per_page = 200 ): array|WP_Error {
		return $this->get( '/wp-json/pzv-mig/v1/commissions', [
			'page'     => $page,
			'per_page' => $per_page,
		] );
	}

	// ---- Internals ----

	private function get( string $endpoint, array $params = [] ): array|WP_Error {
		$raw = $this->raw( $endpoint, $params );
		if ( is_wp_error( $raw ) ) return $raw;
		$code = wp_remote_retrieve_response_code( $raw );
		$body = json_decode( wp_remote_retrieve_body( $raw ), true );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'api_err', $body['message'] ?? "HTTP {$code}" );
		}
		return is_array( $body ) ? $body : [];
	}

	private function raw( string $endpoint, array $params = [] ) {
		$url = $this->base . $endpoint;
		if ( $params ) $url = add_query_arg( $params, $url );
		return wp_remote_get( $url, [
			'timeout'   => 45,
			'sslverify' => false,
			'headers'   => [ 'Authorization' => $this->auth_header ],
		] );
	}
}
