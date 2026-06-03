<?php
defined( 'ABSPATH' ) || exit;

class WC_PSS_Background_Processor {

	const HOOK_DISCOVER = 'wc_pss_discover';
	const HOOK_SCRAPE   = 'wc_pss_scrape_batch';
	const GROUP         = 'wc-pss';
	const BATCH_SIZE    = 5; // HTTP requests per batch

	public static function init(): void {
		add_action( self::HOOK_DISCOVER, [ __CLASS__, 'discover' ], 10, 1 );
		add_action( self::HOOK_SCRAPE,   [ __CLASS__, 'scrape_batch' ], 10, 2 );
	}

	public static function start( string $source_id, array $options ): int {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			throw new \RuntimeException( 'Action Scheduler (as_enqueue_async_action) bu sitede mevcut değil. WooCommerce güncel olmalı.' );
		}

		$job_id = WC_PSS_Job_Manager::create( [
			'status'  => 'discovering',
			'total'   => 0,
			'options' => array_merge( $options, [ 'source_id' => $source_id ] ),
		] );

		as_enqueue_async_action( self::HOOK_DISCOVER, [ 'job_id' => $job_id ], self::GROUP );
		return $job_id;
	}

	// ----------------------------------------------------------------
	// Phase 1: Discover product URLs
	// ----------------------------------------------------------------

	public static function discover( int $job_id ): void {
		$job = WC_PSS_Job_Manager::get( $job_id );
		if ( ! $job || $job->status !== 'discovering' ) return;

		try {
			$options   = json_decode( $job->options, true ) ?: [];
			$source_id = $options['source_id'] ?? '';
			$source    = WC_PSS_Source_Manager::get_with_pass( $source_id );

			if ( ! $source ) {
				WC_PSS_Job_Manager::fail( $job_id, 'Kaynak site bulunamadı: ' . $source_id );
				return;
			}

			$client = new WC_PSS_Http_Client();

			if ( ! empty( $source['username'] ) ) {
				$logged_in = $client->login( $source );
				if ( ! $logged_in ) {
					WC_PSS_Job_Manager::fail( $job_id, 'Giriş başarısız — ' . $source['name'] . '. Login URL\'i ve form alan adlarını kontrol edin.' );
					return;
				}
			}

			$urls = WC_PSS_Scraper::discover_urls( $source, $client );

			if ( empty( $urls ) ) {
				WC_PSS_Job_Manager::fail( $job_id,
					"Kaynak sitede ürün URL'i bulunamadı.\n"
					. "• Sitemap yöntemi: /product-sitemap.xml, /wp-sitemap.xml, /sitemap_index.xml denendi — bulunamadı.\n"
					. "• Çözüm: Kaynak ayarlarında keşif yöntemini 'Sayfa Crawl' seçin, Crawl URL'i ve ürün URL desenini girin (ör. /urun/)."
				);
				return;
			}

			$upload    = wp_upload_dir();
			$dir       = $upload['basedir'] . '/wc-pss';
			wp_mkdir_p( $dir );
			if ( ! file_exists( $dir . '/.htaccess' ) ) {
				file_put_contents( $dir . '/.htaccess', "Options -Indexes\n" );
			}
			$urls_file = $dir . '/urls-' . $job_id . '.json';
			file_put_contents( $urls_file, wp_json_encode( $urls ) );

			WC_PSS_Job_Manager::update( $job_id, [
				'status'    => 'processing',
				'total'     => count( $urls ),
				'file_path' => $urls_file,
			] );

			as_enqueue_async_action( self::HOOK_SCRAPE, [ 'job_id' => $job_id, 'offset' => 0 ], self::GROUP );

		} catch ( \Throwable $e ) {
			WC_PSS_Job_Manager::fail( $job_id, 'İstisna (discover): ' . $e->getMessage() . ' — ' . basename( $e->getFile() ) . ':' . $e->getLine() );
		}
	}

	// ----------------------------------------------------------------
	// Phase 2: Scrape batches
	// ----------------------------------------------------------------

	public static function scrape_batch( int $job_id, int $offset ): void {
		$job = WC_PSS_Job_Manager::get( $job_id );
		if ( ! $job || $job->status !== 'processing' ) return;

		$urls_file = $job->file_path;
		if ( ! $urls_file || ! file_exists( $urls_file ) ) {
			WC_PSS_Job_Manager::fail( $job_id, 'URL listesi dosyası bulunamadı.' );
			return;
		}

		$options   = json_decode( $job->options, true ) ?: [];
		$source_id = $options['source_id'] ?? '';
		$source    = WC_PSS_Source_Manager::get_with_pass( $source_id );

		if ( ! $source ) {
			WC_PSS_Job_Manager::fail( $job_id, 'Kaynak site silindi — iş iptal edildi.' );
			return;
		}

		$all_urls = json_decode( file_get_contents( $urls_file ), true ) ?: [];
		$batch    = array_slice( $all_urls, $offset, self::BATCH_SIZE );

		if ( empty( $batch ) ) {
			WC_PSS_Job_Manager::update( $job_id, [ 'status' => 'completed' ] );
			return;
		}

		$client = new WC_PSS_Http_Client();
		if ( ! empty( $source['username'] ) ) {
			$client->login( $source );
		}

		$updater       = new WC_PSS_Updater( $options );
		$updated       = 0;
		$not_found     = 0;
		$skipped       = 0;
		$errors        = [];
		$updated_items = [];

		foreach ( $batch as $i => $url ) {
			try {
				$data = WC_PSS_Scraper::scrape_product( $url, $source, $client );
				if ( ! $data ) {
					$errors[] = ( $offset + $i + 1 ) . '. URL parse edilemedi: ' . $url;
					continue;
				}
				$result = $updater->process_row( $data );
				switch ( $result['status'] ) {
					case 'updated':
						$updated++;
						$updated_items[] = [
							'sku'       => $result['sku']       ?? '',
							'name'      => $result['name']      ?? '',
							'old_price' => $result['old_price'] ?? '',
							'new_price' => $result['new_price'] ?? '',
						];
						break;
					case 'not_found': $not_found++; break;
					default:          $skipped++;   break;
				}
			} catch ( \Throwable $e ) {
				$errors[] = ( $offset + $i + 1 ) . '. URL: ' . $e->getMessage();
			}
		}

		$new_offset = $offset + count( $batch );

		WC_PSS_Job_Manager::update( $job_id, [
			'processed'     => $new_offset,
			'results'       => compact( 'updated', 'not_found', 'skipped' ),
			'errors'        => $errors,
			'updated_items' => $updated_items,
		] );

		if ( count( $batch ) < self::BATCH_SIZE ) {
			WC_PSS_Job_Manager::update( $job_id, [ 'status' => 'completed' ] );
		} else {
			as_enqueue_async_action( self::HOOK_SCRAPE, [ 'job_id' => $job_id, 'offset' => $new_offset ], self::GROUP );
		}
	}
}
