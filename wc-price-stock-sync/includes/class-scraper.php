<?php
defined( 'ABSPATH' ) || exit;

class WC_PSS_Scraper {

	// ----------------------------------------------------------------
	// URL Discovery
	// ----------------------------------------------------------------

	/**
	 * Discover all product URLs from source site.
	 * Tries sitemap first, falls back to page crawling if configured.
	 */
	public static function discover_urls( array $source, WC_PSS_Http_Client $client ): array {
		if ( ( $source['discovery'] ?? 'sitemap' ) === 'crawl' ) {
			return self::crawl_urls( $source, $client );
		}
		return self::sitemap_urls( $source['base_url'], $client );
	}

	private static function sitemap_urls( string $base_url, WC_PSS_Http_Client $client ): array {
		$base = rtrim( $base_url, '/' );
		$urls = [];

		// 1. Yoast / RankMath product sitemap
		$r = self::parse_sitemap( $base . '/product-sitemap.xml', $client );
		if ( ! empty( $r ) ) return $r;

		// 2. WordPress native sitemap index → find product sub-sitemaps
		$index_body = $client->get( $base . '/wp-sitemap.xml' );
		if ( $index_body ) {
			$subs = self::extract_locs( $index_body );
			foreach ( $subs as $sub ) {
				if ( str_contains( $sub, 'product' ) ) {
					$sub_urls = self::parse_sitemap( $sub, $client );
					$urls     = array_merge( $urls, $sub_urls );
				}
			}
			if ( ! empty( $urls ) ) return array_values( array_unique( $urls ) );
		}

		// 3. WP native paged directly
		for ( $p = 1; $p <= 200; $p++ ) {
			$batch = self::parse_sitemap( $base . '/wp-sitemap-posts-product-' . $p . '.xml', $client );
			if ( empty( $batch ) ) break;
			$urls = array_merge( $urls, $batch );
		}
		if ( ! empty( $urls ) ) return array_values( array_unique( $urls ) );

		// 4. Generic sitemap index
		$index_body = $client->get( $base . '/sitemap_index.xml' )
					?: $client->get( $base . '/sitemap.xml' );
		if ( $index_body ) {
			$subs = self::extract_locs( $index_body );
			foreach ( $subs as $sub ) {
				if ( str_contains( strtolower( $sub ), 'product' ) ) {
					$urls = array_merge( $urls, self::parse_sitemap( $sub, $client ) );
				}
			}
		}

		return array_values( array_unique( $urls ) );
	}

	private static function parse_sitemap( string $url, WC_PSS_Http_Client $client ): array {
		$body = $client->get( $url );
		if ( ! $body ) return [];
		$locs = self::extract_locs( $body );
		return array_values( array_filter( $locs, fn( $u ) => ! str_ends_with( $u, '.xml' ) ) );
	}

	private static function extract_locs( string $xml ): array {
		preg_match_all( '/<loc>\s*(https?:\/\/[^\s<]+)\s*<\/loc>/si', $xml, $m );
		return array_map( 'trim', $m[1] ?? [] );
	}

	/**
	 * Crawl a starting page following pagination and collecting product URLs.
	 */
	private static function crawl_urls( array $source, WC_PSS_Http_Client $client ): array {
		$base    = rtrim( $source['base_url'], '/' );
		$start   = $source['crawl_url'] ?: $base;
		$pattern = $source['url_pattern'] ?: '/urun/';
		$urls    = [];
		$visited = [];
		$queue   = [ $start ];

		while ( ! empty( $queue ) && count( $urls ) < 60000 ) {
			$page_url = array_shift( $queue );
			if ( in_array( $page_url, $visited, true ) ) continue;
			$visited[] = $page_url;

			$html = $client->get( $page_url );
			if ( ! $html ) continue;

			// Collect product URLs and all same-domain links
			preg_match_all( '/href=["\']([^"\'#]+)["\']/i', $html, $hrefs );
			foreach ( $hrefs[1] as $href ) {
				$abs = self::to_absolute( html_entity_decode( trim( $href ) ), $base );
				if ( ! $abs || ! str_starts_with( $abs, $base ) ) continue;

				if ( str_contains( $abs, $pattern ) ) {
					// Strip query strings from product URLs to avoid duplicates
					$clean = strtok( $abs, '?' );
					if ( $clean && ! in_array( $clean, $urls, true ) ) $urls[] = $clean;
				}
			}

			// ---- Pagination detection (multiple strategies) ----

			// Strategy 1: rel="next" (standard HTML)
			if ( preg_match( '/(?:rel=["\']next["\']\s[^>]*href=["\']([^"\']+)["\']|href=["\']([^"\']+)["\']\s[^>]*rel=["\']next["\'])/i', $html, $m ) ) {
				$next_url = self::to_absolute( html_entity_decode( $m[1] ?: $m[2] ), $base );
				if ( $next_url && ! in_array( $next_url, $visited, true ) ) {
					array_unshift( $queue, $next_url );
					continue;
				}
			}

			// Strategy 2: explicit next-button text (Turkish + universal)
			if ( preg_match(
				'/href=["\']([^"\']+)["\'][^>]*>\s*(?:&rsaquo;|›|&raquo;|»|>>|Sonraki|İleri|Next)\s*</i',
				$html, $m
			) ) {
				$next_url = self::to_absolute( html_entity_decode( $m[1] ), $base );
				if ( $next_url && ! in_array( $next_url, $visited, true ) ) {
					array_unshift( $queue, $next_url );
					continue;
				}
			}

			// Strategy 3: queue ALL numbered pagination links on this page
			// Matches: ?page=N  ?p=N  /page/N/  /sayfa/N  /sayfa-N
			preg_match_all(
				'/href=["\']([^"\']*(?:[?&](?:page|sayfa|p)=\d+|\/(?:page|sayfa)\/\d+|\/sayfa-\d+)[^"\']*)["\']/',
				$html, $pg
			);
			foreach ( $pg[1] as $plink ) {
				$abs = self::to_absolute( html_entity_decode( trim( $plink ) ), $base );
				if ( $abs && str_starts_with( $abs, $base ) && ! in_array( $abs, $visited, true ) && ! in_array( $abs, $queue, true ) ) {
					$queue[] = $abs;
				}
			}

			// Strategy 4: URL-increment fallback — if current URL already has ?page=N,
			// check whether page N+1 exists in the HTML links
			$qs = wp_parse_url( $page_url, PHP_URL_QUERY ) ?? '';
			parse_str( $qs, $qp );
			$param = isset( $qp['page'] ) ? 'page' : ( isset( $qp['sayfa'] ) ? 'sayfa' : ( isset( $qp['p'] ) ? 'p' : '' ) );
			if ( $param !== '' ) {
				$next_n    = (int) $qp[ $param ] + 1;
				$candidate = str_replace( "{$param}=" . $qp[ $param ], "{$param}={$next_n}", $page_url );
				if ( str_contains( $html, "{$param}={$next_n}" ) && ! in_array( $candidate, $visited, true ) ) {
					$queue[] = $candidate;
				}
			}
		}

		return array_values( array_unique( $urls ) );
	}

	private static function to_absolute( string $href, string $base ): ?string {
		if ( str_starts_with( $href, 'http' ) ) return $href;
		if ( str_starts_with( $href, '//' ) ) return 'https:' . $href;
		if ( str_starts_with( $href, '/' ) ) return $base . $href;
		return null;
	}

	// ----------------------------------------------------------------
	// Product Page Scraping
	// ----------------------------------------------------------------

	/**
	 * Scrape a single product page and return structured data.
	 * Returns ['sku', 'name', 'regular_price', 'sale_price', 'stock_status'] or null on failure.
	 */
	public static function scrape_product( string $url, array $source, WC_PSS_Http_Client $client ): ?array {
		$html = $client->get( $url );
		if ( ! $html ) return null;

		$data = self::extract_json_ld( $html )
			?? self::extract_microdata( $html )
			?? self::extract_script_json( $html )
			?? [];

		// Override with configured CSS selectors
		if ( ! empty( $source['name_sel'] ) ) {
			$v = self::sel_text( $html, $source['name_sel'] );
			if ( $v !== '' ) $data['name'] = trim( $v );
		}
		if ( ! empty( $source['sku_sel'] ) ) {
			$v = self::sel_text( $html, $source['sku_sel'] );
			if ( $v !== '' ) $data['sku'] = $v;
		}
		if ( ! empty( $source['price_sel'] ) ) {
			$v = self::sel_text( $html, $source['price_sel'] );
			if ( $v !== '' ) $data['regular_price'] = self::parse_price( $v );
		}
		if ( ! empty( $source['reg_price_sel'] ) ) {
			// When both selectors are set: reg_price_sel → original, price_sel → sale
			$v = self::sel_text( $html, $source['reg_price_sel'] );
			if ( $v !== '' ) {
				$data['sale_price']    = $data['regular_price'] ?? '';
				$data['regular_price'] = self::parse_price( $v );
			}
		}
		if ( ! empty( $source['stock_sel'] ) ) {
			$v = self::sel_text( $html, $source['stock_sel'] );
			if ( $v !== '' ) $data['stock_status'] = self::parse_stock_text( $v );
		}

		// Fallback price from WooCommerce HTML
		if ( empty( $data['regular_price'] ) ) {
			$prices = self::extract_prices_html( $html );
			if ( $prices ) {
				$data['regular_price'] = $prices['regular'];
				$data['sale_price']    = $prices['sale'];
			}
		}

		// Fallback SKU
		if ( empty( $data['sku'] ) ) {
			$data['sku'] = self::extract_sku_html( $html );
		}

		// Fallback name (needed for name-based matching when SKU is absent/unmatched)
		if ( empty( $data['name'] ) ) {
			$data['name'] = self::extract_name_html( $html );
		}

		if ( empty( $data['sku'] ) && empty( $data['name'] ) ) return null;

		return $data;
	}

	// ----------------------------------------------------------------
	// JSON-LD Extraction
	// ----------------------------------------------------------------

	private static function extract_json_ld( string $html ): ?array {
		preg_match_all(
			'/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si',
			$html, $matches
		);
		foreach ( $matches[1] as $json_str ) {
			$decoded = json_decode( trim( $json_str ), true );
			if ( ! $decoded ) continue;
			$items = isset( $decoded[0] ) ? $decoded : [ $decoded ];
			foreach ( $items as $item ) {
				$r = self::parse_product_schema( $item );
				if ( $r ) return $r;
			}
		}
		return null;
	}

	private static function parse_product_schema( array $d ): ?array {
		$type = is_array( $d['@type'] ?? '' ) ? ( $d['@type'][0] ?? '' ) : ( $d['@type'] ?? '' );
		if ( $type !== 'Product' ) return null;

		$result = [
			'name'          => strip_tags( $d['name'] ?? '' ),
			'sku'           => trim( $d['sku'] ?? '' ),
			'regular_price' => '',
			'sale_price'    => '',
			'stock_status'  => 'instock',
		];

		$offer = $d['offers'] ?? null;
		if ( $offer ) {
			if ( isset( $offer[0] ) ) $offer = $offer[0];
			$result['regular_price'] = self::parse_price( (string) ( $offer['price'] ?? '' ) );
			$result['stock_status']  = self::parse_stock_avail( strtolower( $offer['availability'] ?? '' ) );
		}

		return $result;
	}

	// ----------------------------------------------------------------
	// Microdata (itemprop) Extraction
	// ----------------------------------------------------------------

	private static function extract_microdata( string $html ): ?array {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();
		$xpath = new DOMXPath( $doc );

		$get = function( string $prop ) use ( $xpath ): string {
			// Try content attribute first (meta tags), then text content
			$nodes = $xpath->query( '//*[@itemprop="' . $prop . '"]' );
			if ( ! $nodes || ! $nodes->length ) return '';
			$node = $nodes->item(0);
			$val  = $node->getAttribute( 'content' );
			return trim( $val !== '' ? $val : $node->textContent );
		};

		$price = $get( 'price' );
		$sku   = self::sanitize_sku( $get( 'sku' ) );
		$name  = mb_substr( trim( $get( 'name' ) ), 0, 300 );
		$avail = $get( 'availability' );

		if ( ! $price && ! $sku ) return null;
		return [
			'name'          => $name,
			'sku'           => $sku,
			'regular_price' => self::parse_price( $price ),
			'sale_price'    => '',
			'stock_status'  => $avail ? self::parse_stock_avail( strtolower( $avail ) ) : 'instock',
		];
	}

	// ----------------------------------------------------------------
	// Script-embedded JSON Extraction
	// ----------------------------------------------------------------

	private static function extract_script_json( string $html ): ?array {
		// Extract all <script> tag contents (not JSON-LD)
		preg_match_all(
			'/<script(?![^>]+type=["\']application\/ld\+json["\'])[^>]*>(.*?)<\/script>/si',
			$html, $scripts
		);

		$price_keys = [ 'price', 'fiyat', 'satisFiyati', 'satis_fiyati', 'urunFiyati', 'urun_fiyati' ];
		$sku_keys   = [ 'sku', 'stokKodu', 'stok_kodu', 'urunKodu', 'urun_kodu', 'productCode', 'modelNo' ];
		$name_keys  = [ 'name', 'urunAdi', 'urun_adi', 'title', 'baslik' ];

		foreach ( $scripts[1] as $js ) {
			// Find JSON blobs embedded as JS variable assignments: var x = {...}; or window.x = {...};
			preg_match_all( '/(?:var\s+\w+\s*=\s*|window\.\w+\s*=\s*|[a-zA-Z_$][\w$]*\s*[=:]\s*)(\{[^;]{50,}\})\s*;?/s', $js, $blobs );
			foreach ( $blobs[1] as $blob ) {
				// Quick check before parsing
				if ( ! preg_match( '/price|fiyat|sku|stokKodu/i', $blob ) ) continue;
				$d = json_decode( $blob, true );
				if ( ! is_array( $d ) ) continue;

				$price = '';
				foreach ( $price_keys as $k ) {
					if ( isset( $d[ $k ] ) && $d[ $k ] !== '' ) { $price = (string) $d[ $k ]; break; }
				}
				$sku = '';
				foreach ( $sku_keys as $k ) {
					if ( isset( $d[ $k ] ) && $d[ $k ] !== '' ) { $sku = (string) $d[ $k ]; break; }
				}
				$name = '';
				foreach ( $name_keys as $k ) {
					if ( isset( $d[ $k ] ) && $d[ $k ] !== '' ) { $name = (string) $d[ $k ]; break; }
				}

				$sku_clean = self::sanitize_sku( $sku );
				if ( $price || $sku_clean ) {
					return [
						'name'          => mb_substr( trim( $name ), 0, 300 ),
						'sku'           => $sku_clean,
						'regular_price' => self::parse_price( $price ),
						'sale_price'    => '',
						'stock_status'  => 'instock',
					];
				}
			}
		}
		return null;
	}

	// ----------------------------------------------------------------
	// HTML Fallback Patterns
	// ----------------------------------------------------------------

	private static function extract_prices_html( string $html ): ?array {
		// WooCommerce: <del>...<bdi>199,00</bdi>...</del><ins>...<bdi>149,00</bdi>...</ins>
		if ( preg_match(
			'/<del[^>]*>.*?<bdi[^>]*>([\d\s.,]+)<\/bdi>.*?<\/del>.*?<ins[^>]*>.*?<bdi[^>]*>([\d\s.,]+)<\/bdi>/si',
			$html, $m
		) ) {
			return [ 'regular' => self::parse_price( $m[1] ), 'sale' => self::parse_price( $m[2] ) ];
		}
		// Single price
		if ( preg_match(
			'/<(?:span|p)[^>]+class=["\'][^"\']*(?:price|fiyat)[^"\']*["\'][^>]*>.*?<bdi[^>]*>([\d\s.,]+)<\/bdi>/si',
			$html, $m
		) ) {
			return [ 'regular' => self::parse_price( $m[1] ), 'sale' => '' ];
		}
		// Turkish B2B: "480.00 ₺ - 10 % 432.00 ₺" — values may be in separate tags, so strip HTML first
		$text = html_entity_decode( preg_replace( '/<[^>]+>/', ' ', $html ) );
		$text = preg_replace( '/\s+/', ' ', $text );
		if ( preg_match( '/([\d.,]+)\s*₺\s*[-–]\s*[\d.,]+\s*%\s*([\d.,]+)\s*₺/u', $text, $m ) ) {
			return [ 'regular' => self::parse_price( $m[1] ), 'sale' => self::parse_price( $m[2] ) ];
		}
		// Same but TL suffix variant
		if ( preg_match( '/([\d.,]+)\s*TL\s*[-–]\s*[\d.,]+\s*%\s*([\d.,]+)\s*TL/i', $text, $m ) ) {
			return [ 'regular' => self::parse_price( $m[1] ), 'sale' => self::parse_price( $m[2] ) ];
		}
		return null;
	}

	private static function extract_sku_html( string $html ): string {
		// WooCommerce .sku span
		if ( preg_match( '/<span class=["\']sku["\'][^>]*>(.*?)<\/span>/si', $html, $m ) ) {
			return self::sanitize_sku( $m[1] );
		}
		// itemprop=sku
		if ( preg_match( '/<[^>]+itemprop=["\']sku["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m ) ) {
			return self::sanitize_sku( $m[1] );
		}
		// Common class names: sku, urun-kodu, product-code, stok-kodu, model-no
		if ( preg_match(
			'/<[^>]+class=["\'][^"\']*(?:sku|urun-kodu|product-code|stok-kodu|model-no)[^"\']*["\'][^>]*>(.*?)<\/(?:span|div|td|p)>/si',
			$html, $m
		) ) {
			return self::sanitize_sku( $m[1] );
		}
		// Turkish B2B pattern: "Kod : 075.93386" or "Kod Код Code : 075.93386"
		// Look for "Kod" label (possibly multilingual) followed by a value
		$text = preg_replace( '/<[^>]+>/', ' ', $html );
		$text = html_entity_decode( $text );
		if ( preg_match( '/\bKod\b[^:]*:\s*([\w.#-]{3,30})/u', $text, $m ) ) {
			$candidate = self::sanitize_sku( $m[1] );
			if ( $candidate !== '' ) return $candidate;
		}
		return '';
	}

	private static function extract_name_html( string $html ): string {
		// 1. og:title — reliable, short, site-defined; strip SEO suffixes after
		$og = '';
		if ( preg_match( '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m ) ) {
			$og = $m[1];
		} elseif ( preg_match( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:title["\']/i', $html, $m ) ) {
			$og = $m[1];
		}
		if ( $og ) {
			$v = self::strip_title_suffix( self::clean_name( $og ) );
			if ( strlen( $v ) >= 3 && strlen( $v ) <= 300 ) return $v;
		}
		// 2. itemprop="name" content attribute
		if ( preg_match( '/<[^>]+itemprop=["\']name["\'][^>]*content=["\']([^"\']{3,200})["\']/i', $html, $m ) ) {
			$v = self::strip_title_suffix( self::clean_name( $m[1] ) );
			if ( strlen( $v ) >= 3 ) return $v;
		}
		// 3. <h1> — only if short and single-language (skip multilingual section headings)
		if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/si', $html, $m ) ) {
			$v = self::strip_title_suffix( self::clean_name( $m[1] ) );
			// Skip if too long (section headings in multiple languages are typically very long)
			// or if it contains Arabic/Cyrillic characters mixed with Latin (multilingual heading)
			$has_arabic   = preg_match( '/\p{Arabic}/u', $v );
			$has_cyrillic = preg_match( '/\p{Cyrillic}/u', $v );
			if ( strlen( $v ) >= 3 && strlen( $v ) <= 120 && ! $has_arabic && ! $has_cyrillic ) {
				return $v;
			}
		}
		// 4. <title> minus " | site" suffix
		if ( preg_match( '/<title[^>]*>([^<]+)<\/title>/i', $html, $m ) ) {
			$v = self::strip_title_suffix( self::clean_name( $m[1] ) );
			if ( strlen( $v ) >= 3 ) return $v;
		}
		return '';
	}

	/** Remove " | Site Name", " - Site Name", " toptan …" SEO suffixes from titles. */
	private static function strip_title_suffix( string $name ): string {
		// Strip " | anything" and " – anything" and " - anything"
		$name = preg_replace( '/\s*[|–]\s*.+$/u', '', $name );
		// Strip trailing SEO words common on Turkish B2B sites
		$name = preg_replace( '/\s+(?:toptan\s+çeşitleri|toptan\s+fiyat|toptan|çeşitleri|fiyatları)\s*$/iu', '', $name );
		return trim( $name );
	}

	private static function clean_name( string $raw ): string {
		$raw = trim( strip_tags( html_entity_decode( $raw ) ) );
		$raw = preg_replace( '/\s+/', ' ', $raw );
		return mb_substr( $raw, 0, 300 );
	}

	/** Strip, trim, max 80 chars. Returns '' if result looks like HTML/JS junk. */
	private static function sanitize_sku( string $raw ): string {
		$raw = trim( strip_tags( $raw ) );
		$raw = preg_replace( '/\s+/', ' ', $raw );
		if ( strlen( $raw ) > 80 ) return '';           // too long = junk
		if ( preg_match( '/[<>{}\[\]]/', $raw ) ) return ''; // HTML/JS fragment
		return $raw;
	}

	// ----------------------------------------------------------------
	// CSS Selector → Text (simple subset)
	// ----------------------------------------------------------------

	private static function sel_text( string $html, string $selector ): string {
		if ( ! $html || ! $selector ) return '';
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();
		$xpath = new DOMXPath( $doc );
		$xp    = self::css_to_xpath( trim( $selector ) );
		if ( ! $xp ) return '';
		$nodes = @$xpath->query( $xp );
		return ( $nodes && $nodes->length > 0 ) ? trim( $nodes->item( 0 )->textContent ) : '';
	}

	private static function css_to_xpath( string $css ): string {
		// Take only the first comma-separated selector
		if ( str_contains( $css, ',' ) ) {
			$css = trim( explode( ',', $css )[0] );
		}
		$parts = preg_split( '/\s+/', $css );
		$xp    = '//' . implode( '//', array_map( function ( $p ) {
			// tag#id
			if ( preg_match( '/^([a-z][a-z0-9]*)#(.+)$/i', $p, $m ) ) {
				return $m[1] . '[@id="' . $m[2] . '"]';
			}
			// tag.class1.class2…
			if ( preg_match( '/^([a-z][a-z0-9]*)\.(.+)$/i', $p, $m ) ) {
				$cls  = array_map( fn( $c ) => 'contains(@class,"' . $c . '")', explode( '.', $m[2] ) );
				return $m[1] . '[' . implode( ' and ', $cls ) . ']';
			}
			// #id
			if ( preg_match( '/^#(.+)$/', $p, $m ) ) {
				return '*[@id="' . $m[1] . '"]';
			}
			// .class1.class2…
			if ( preg_match( '/^\.(.+)$/', $p, $m ) ) {
				$cls = array_map( fn( $c ) => 'contains(@class,"' . $c . '")', explode( '.', $m[1] ) );
				return '*[' . implode( ' and ', $cls ) . ']';
			}
			// plain tag
			if ( preg_match( '/^[a-z][a-z0-9]*$/i', $p ) ) return $p;
			return '*';
		}, $parts ) );
		return $xp;
	}

	// ----------------------------------------------------------------
	// Price / Stock Helpers
	// ----------------------------------------------------------------

	public static function parse_price( string $price ): string {
		$price = trim( strip_tags( $price ) );
		$price = preg_replace( '/[^\d.,]/', '', $price );  // strip TL ₺ etc.
		if ( $price === '' ) return '';

		if ( str_contains( $price, '.' ) && str_contains( $price, ',' ) ) {
			// Determine decimal separator by position of last occurrence
			if ( strrpos( $price, ',' ) > strrpos( $price, '.' ) ) {
				// Turkish: 1.234,56
				$price = str_replace( '.', '', $price );
				$price = str_replace( ',', '.', $price );
			} else {
				// English: 1,234.56
				$price = str_replace( ',', '', $price );
			}
		} elseif ( str_contains( $price, ',' ) ) {
			$parts = explode( ',', $price );
			$price = ( count( $parts ) === 2 && strlen( $parts[1] ) <= 2 )
				? str_replace( ',', '.', $price )
				: str_replace( ',', '', $price );
		}

		return $price;
	}

	private static function parse_stock_avail( string $avail ): string {
		if ( str_contains( $avail, 'outofstock' ) || str_contains( $avail, 'soldout' ) ) return 'outofstock';
		if ( str_contains( $avail, 'backorder' ) ) return 'onbackorder';
		return 'instock';
	}

	private static function parse_stock_text( string $text ): string {
		$t = strtolower( trim( $text ) );
		$out  = [ 'stokta yok', 'tükendi', 'stok yok', 'out of stock', 'sold out' ];
		$back = [ 'ön sipariş', 'backorder', 'sipariş üzerine' ];
		foreach ( $out  as $kw ) { if ( str_contains( $t, $kw ) ) return 'outofstock'; }
		foreach ( $back as $kw ) { if ( str_contains( $t, $kw ) ) return 'onbackorder'; }
		return 'instock';
	}
}
