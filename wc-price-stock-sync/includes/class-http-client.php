<?php
defined( 'ABSPATH' ) || exit;

/**
 * cURL-based HTTP client with persistent cookie jar for authenticated scraping.
 */
class WC_PSS_Http_Client {

	private string $cookie_file;

	public function __construct() {
		$upload            = wp_upload_dir();
		$dir               = $upload['basedir'] . '/wc-pss';
		wp_mkdir_p( $dir );
		$this->cookie_file = $dir . '/cookies-' . wp_generate_password( 8, false ) . '.txt';
	}

	public function __destruct() {
		if ( file_exists( $this->cookie_file ) ) {
			@unlink( $this->cookie_file ); // phpcs:ignore
		}
	}

	/**
	 * Log in to the source site.
	 * Fetches the login page, extracts hidden form fields (nonces/CSRF), then POSTs credentials.
	 */
	public function login( array $source ): bool {
		$login_url  = rtrim( $source['login_url'] ?: $source['base_url'], '/' );
		$user_field = $source['user_field'] ?: 'username';
		$pass_field = $source['pass_field'] ?: 'password';

		// Fetch login page to capture hidden fields and form action
		$page      = $this->get( $login_url );
		$post_url  = $login_url;
		$fields    = [];

		if ( $page ) {
			// Find form action
			if ( preg_match( '/<form[^>]+action=["\']([^"\']+)["\']/is', $page, $m ) ) {
				$action = html_entity_decode( $m[1] );
				if ( filter_var( $action, FILTER_VALIDATE_URL ) ) {
					$post_url = $action;
				} elseif ( str_starts_with( $action, '/' ) ) {
					$p        = wp_parse_url( $login_url );
					$post_url = $p['scheme'] . '://' . $p['host'] . $action;
				}
			}
			// Extract all hidden fields
			preg_match_all( '/<input[^>]+type=["\']hidden["\'][^>]*\/?>/is', $page, $inputs );
			foreach ( $inputs[0] as $tag ) {
				$name = $value = '';
				if ( preg_match( '/\bname=["\']([^"\']+)["\']/i', $tag, $n ) ) $name  = $n[1];
				if ( preg_match( '/\bvalue=["\']([^"\']*)["\']/', $tag, $v ) )  $value = html_entity_decode( $v[1] );
				if ( $name ) $fields[ $name ] = $value;
			}
		}

		$fields[ $user_field ] = $source['username'];
		$fields[ $pass_field ] = $source['password'];

		$result = $this->post( $post_url, $fields );
		return $result !== null;
	}

	public function get( string $url ): ?string {
		return $this->request( 'GET', $url );
	}

	public function post( string $url, array $body ): ?string {
		return $this->request( 'POST', $url, $body );
	}

	private function request( string $method, string $url, array $post_body = [] ): ?string {
		if ( ! function_exists( 'curl_init' ) ) {
			return $this->wp_request( $method, $url, $post_body );
		}

		$ch = curl_init();
		curl_setopt_array( $ch, [
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS      => 6,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_COOKIEFILE     => $this->cookie_file,
			CURLOPT_COOKIEJAR      => $this->cookie_file,
			CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124',
			CURLOPT_ENCODING       => '',
			CURLOPT_HTTPHEADER     => [ 'Accept-Language: tr-TR,tr;q=0.9,en;q=0.8' ],
		] );

		if ( $method === 'POST' ) {
			curl_setopt( $ch, CURLOPT_POST, true );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, http_build_query( $post_body ) );
		} else {
			curl_setopt( $ch, CURLOPT_HTTPGET, true );
		}

		$body = curl_exec( $ch );
		$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( $body === false || $code < 200 || $code >= 400 ) return null;
		return $body;
	}

	private function wp_request( string $method, string $url, array $post_body = [] ): ?string {
		$args = [ 'timeout' => 30, 'sslverify' => false ];
		if ( $method === 'POST' ) {
			$args['method'] = 'POST';
			$args['body']   = $post_body;
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) return null;
		$code = wp_remote_retrieve_response_code( $response );
		return ( $code >= 200 && $code < 400 ) ? wp_remote_retrieve_body( $response ) : null;
	}
}
