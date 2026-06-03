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
	public function login( array $source, ?string $prefetched_page = null ): bool {
		$login_url  = rtrim( $source['login_url'] ?: $source['base_url'], '/' );
		$user_field = $source['user_field'] ?: 'username';
		$pass_field = $source['pass_field'] ?: 'password';

		// Fetch login page to capture hidden fields and form action (use pre-fetched if available)
		$page      = $prefetched_page ?? $this->get( $login_url );
		$post_url  = $login_url;
		$fields    = [];

		if ( $page ) {
			// Find the login form (prefer the one containing user_field or pass_field)
			$form_html = $page;
			if ( preg_match_all( '/<form[^>]*>.*?<\/form>/is', $page, $form_m ) ) {
				foreach ( $form_m[0] as $candidate ) {
					if ( stripos( $candidate, 'name="' . $user_field . '"' ) !== false ||
					     stripos( $candidate, "name='" . $user_field . "'" ) !== false ) {
						$form_html = $candidate;
						break;
					}
				}
			}

			// Find form action
			if ( preg_match( '/<form[^>]+action=["\']([^"\']+)["\']/is', $form_html, $m ) ) {
				$action = html_entity_decode( $m[1] );
				if ( filter_var( $action, FILTER_VALIDATE_URL ) ) {
					$post_url = $action;
				} elseif ( str_starts_with( $action, '/' ) ) {
					$p        = wp_parse_url( $login_url );
					$post_url = $p['scheme'] . '://' . $p['host'] . $action;
				}
			}

			// Extract ALL input fields with a value (not just hidden), excluding submit/button/file/image
			preg_match_all( '/<input([^>]*)\/?>/is', $form_html, $inputs );
			foreach ( $inputs[1] as $attrs ) {
				$type = '';
				if ( preg_match( '/\btype=["\']([^"\']+)["\']/i', $attrs, $t ) ) $type = strtolower( $t[1] );
				if ( in_array( $type, [ 'submit', 'button', 'file', 'image', 'reset' ], true ) ) continue;
				$name = $value = '';
				if ( preg_match( '/\bname=["\']([^"\']+)["\']/i', $attrs, $n ) ) $name  = $n[1];
				if ( preg_match( '/\bvalue=["\']([^"\']*)["\']/', $attrs, $v ) )  $value = html_entity_decode( $v[1] );
				// For checkboxes/radios, only include if checked
				if ( in_array( $type, [ 'checkbox', 'radio' ], true ) ) {
					if ( ! preg_match( '/\bchecked\b/i', $attrs ) ) continue;
				}
				if ( $name ) $fields[ $name ] = $value;
			}

			// Also capture <select> default (first <option> or selected one)
			preg_match_all( '/<select[^>]*name=["\']([^"\']+)["\'][^>]*>(.*?)<\/select>/is', $form_html, $selects );
			foreach ( $selects[1] as $si => $sel_name ) {
				$sel_html = $selects[2][ $si ];
				$sel_val  = '';
				if ( preg_match( '/<option[^>]+selected[^>]*value=["\']([^"\']*)["\']|<option[^>]+value=["\']([^"\']*)["\'][^>]+selected/i', $sel_html, $sv ) ) {
					$sel_val = $sv[1] ?: $sv[2];
				} elseif ( preg_match( '/<option[^>]+value=["\']([^"\']*)["\']>/i', $sel_html, $sv ) ) {
					$sel_val = $sv[1]; // first option as default
				}
				$fields[ $sel_name ] = $sel_val;
			}
		}

		$fields[ $user_field ] = $source['username'];
		$fields[ $pass_field ] = $source['password'];

		// Apply extra_fields overrides (key=value lines, e.g. "kriter=email")
		if ( ! empty( $source['extra_fields'] ) ) {
			foreach ( explode( "\n", $source['extra_fields'] ) as $line ) {
				$line = trim( $line );
				if ( $line === '' || ! str_contains( $line, '=' ) ) continue;
				[ $k, $v ] = explode( '=', $line, 2 );
				$k = trim( $k );
				$v = trim( $v );
				if ( $k !== '' ) $fields[ $k ] = $v;
			}
		}

		$result = $this->post( $post_url, $fields );
		return $result !== null;
	}

	public function get( string $url ): ?string {
		return $this->request( 'GET', $url )['body'];
	}

	public function post( string $url, array $body ): ?string {
		return $this->request( 'POST', $url, $body )['body'];
	}

	/** Returns ['code' => int, 'url' => string, 'body' => ?string] */
	public function get_info( string $url ): array {
		return $this->request( 'GET', $url );
	}

	private function request( string $method, string $url, array $post_body = [] ): array {
		if ( ! function_exists( 'curl_init' ) ) {
			$body = $this->wp_request( $method, $url, $post_body );
			return [ 'code' => $body !== null ? 200 : 0, 'url' => $url, 'body' => $body ];
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

		$body     = curl_exec( $ch );
		$code     = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$eff_url  = (string) curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL );
		curl_close( $ch );

		if ( $body === false || $code < 200 || $code >= 400 ) {
			return [ 'code' => $code, 'url' => $eff_url, 'body' => null ];
		}
		return [ 'code' => $code, 'url' => $eff_url, 'body' => $body ];
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
