<?php
defined( 'ABSPATH' ) || exit;

class WC_RM_Background_Processor {

	const HOOK_DISCOVER   = 'wc_rm_discover';
	const HOOK_IMPORT     = 'wc_rm_import_page';
	const HOOK_WATCHDOG   = 'wc_rm_watchdog';
	const GROUP           = 'wc-rest-migrator';
	const PER_PAGE        = 20;
	const WATCHDOG_INT    = 600;  // seconds between watchdog runs
	const STUCK_THRESHOLD = 900;  // seconds without heartbeat = stuck

	public static function init(): void {
		add_action( self::HOOK_DISCOVER, [ __CLASS__, 'discover' ], 10, 1 );
		add_action( self::HOOK_IMPORT,   [ __CLASS__, 'import_page' ], 10, 2 );
		add_action( self::HOOK_WATCHDOG, [ __CLASS__, 'watchdog' ], 10, 1 );
	}

	public static function start( array $options ): int {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			throw new \RuntimeException( 'Action Scheduler bulunamadı. WooCommerce güncel olmalı.' );
		}
		$job_id = WC_RM_Job_Manager::create( [
			'status'     => 'discovering',
			'source_url' => $options['source_url'] ?? '',
			'options'    => $options,
		] );
		as_enqueue_async_action( self::HOOK_DISCOVER, [ 'job_id' => $job_id ], self::GROUP );
		// Watchdog: checks every WATCHDOG_INT seconds whether the job is still progressing
		as_schedule_single_action(
			time() + self::WATCHDOG_INT,
			self::HOOK_WATCHDOG,
			[ 'job_id' => $job_id ],
			self::GROUP
		);
		return $job_id;
	}

	// ---- Phase 1: Discover ----

	public static function discover( int $job_id ): void {
		$job = WC_RM_Job_Manager::get( $job_id );
		if ( ! $job || $job->status !== 'discovering' ) return;

		try {
			$options = json_decode( $job->options, true ) ?: [];
			$client  = self::make_client( $options );
			$test    = $client->test();

			if ( ! $test['ok'] ) {
				WC_RM_Job_Manager::fail( $job_id, 'Bağlantı hatası: ' . $test['message'] );
				return;
			}
			$total = (int) $test['total'];
			if ( $total === 0 ) {
				WC_RM_Job_Manager::fail( $job_id, 'Kaynak sitede ürün bulunamadı.' );
				return;
			}

			WC_RM_Job_Manager::update( $job_id, [ 'status' => 'processing', 'total' => $total ] );
			as_enqueue_async_action( self::HOOK_IMPORT, [ 'job_id' => $job_id, 'page' => 1 ], self::GROUP );

		} catch ( \Throwable $e ) {
			WC_RM_Job_Manager::fail( $job_id, 'İstisna (discover): ' . $e->getMessage() );
		}
	}

	// ---- Phase 2: Import pages ----

	/**
	 * @param bool $enqueue_next  True when called by Action Scheduler (enqueues next page via AS).
	 *                            False when called directly from AJAX batch loop (JS drives next call).
	 */
	public static function import_page( int $job_id, int $page, bool $enqueue_next = true ): void {
		$job = WC_RM_Job_Manager::get( $job_id );
		if ( ! $job || $job->status !== 'processing' ) return;

		// Extend resource limits — image downloads and large product sets can be slow
		@set_time_limit( 300 );
		wp_raise_memory_limit( 'admin' );

		// Heartbeat: write current_page + timestamp BEFORE slow operations.
		// If this action crashes (PHP timeout/OOM), the watchdog sees a stale heartbeat
		// and re-enqueues the NEXT page automatically.
		WC_RM_Job_Manager::update( $job_id, [ 'current_page' => $page ] );

		try {
			$options  = json_decode( $job->options, true ) ?: [];
			$client   = self::make_client( $options );
			$importer = new WC_RM_Product_Importer( $options );

			$products = $client->get_products( $page, self::PER_PAGE, $options['status'] ?? 'any' );

			if ( is_wp_error( $products ) ) {
				WC_RM_Job_Manager::fail( $job_id, "Sayfa {$page} alınamadı: " . $products->get_error_message() );
				return;
			}

			if ( empty( $products ) ) {
				WC_RM_Job_Manager::update( $job_id, [ 'status' => 'completed' ] );
				return;
			}

			$results        = [ 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors_count' => 0 ];
			$errors         = [];
			$imported_items = [];

			foreach ( $products as $prod ) {
				$variations = [];
				if ( ( $prod['type'] ?? '' ) === 'variable' && ! empty( $prod['id'] ) ) {
					$var_res = $client->get_variations( (int) $prod['id'] );
					if ( ! is_wp_error( $var_res ) ) $variations = $var_res;
				}

				$result = $importer->import_product( $prod, $variations );

				if ( $result['status'] === 'created' || $result['status'] === 'updated' ) {
					$results[ $result['status'] ]++;
					$imported_items[] = [
						'sku'    => $result['sku']    ?? '',
						'name'   => $result['name']   ?? '',
						'status' => $result['status'] ?? '',
					];
				} elseif ( $result['status'] === 'skipped' ) {
					$results['skipped']++;
				} else {
					$results['errors_count']++;
					$sku = $result['sku'] ?? '';
					$errors[] = ( $sku ? "[{$sku}] " : '' ) . ( $result['message'] ?? 'Bilinmeyen hata' );
				}
			}

			$processed = ( $page - 1 ) * self::PER_PAGE + count( $products );

			WC_RM_Job_Manager::update( $job_id, [
				'processed'      => min( $processed, $job->total ),
				'results'        => $results,
				'errors'         => $errors,
				'imported_items' => $imported_items,
			] );

			if ( count( $products ) < self::PER_PAGE ) {
				WC_RM_Job_Manager::update( $job_id, [ 'status' => 'completed' ] );
			} elseif ( $enqueue_next && function_exists( 'as_enqueue_async_action' ) ) {
				// AS-driven mode: schedule next page as a background action
				as_enqueue_async_action( self::HOOK_IMPORT, [ 'job_id' => $job_id, 'page' => $page + 1 ], self::GROUP );
			}
			// AJAX-driven mode (!$enqueue_next): JS calls the next batch directly

		} catch ( \Throwable $e ) {
			WC_RM_Job_Manager::fail( $job_id, "İstisna (import, sayfa {$page}): " . $e->getMessage() . ' — ' . basename( $e->getFile() ) . ':' . $e->getLine() );
		}
	}

	// ---- Watchdog: auto-recover stuck jobs ----

	public static function watchdog( int $job_id ): void {
		$job = WC_RM_Job_Manager::get( $job_id );
		if ( ! $job ) return;

		// Terminal statuses — stop watching
		if ( in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ) ) return;

		if ( $job->status === 'processing' ) {
			$results        = json_decode( $job->results ?: '{}', true ) ?: [];
			$last_heartbeat = (int) ( $results['last_heartbeat'] ?? 0 );
			$current_page   = (int) ( $results['current_page']   ?? 0 );

			$is_stuck = $current_page > 0
				&& $last_heartbeat > 0
				&& ( time() - $last_heartbeat ) > self::STUCK_THRESHOLD;

			if ( $is_stuck ) {
				// No heartbeat for STUCK_THRESHOLD seconds — advance past the stuck page
				as_enqueue_async_action(
					self::HOOK_IMPORT,
					[ 'job_id' => $job_id, 'page' => $current_page + 1 ],
					self::GROUP
				);
				// Reset heartbeat so next watchdog cycle doesn't immediately re-trigger
				WC_RM_Job_Manager::update( $job_id, [ 'current_page' => $current_page ] );
			}
		}

		// Re-schedule watchdog for next interval
		as_schedule_single_action(
			time() + self::WATCHDOG_INT,
			self::HOOK_WATCHDOG,
			[ 'job_id' => $job_id ],
			self::GROUP
		);
	}

	// ---- Manual resume (called from admin AJAX) ----

	public static function resume( int $job_id ): bool {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) return false;

		$job = WC_RM_Job_Manager::get( $job_id );
		if ( ! $job || $job->status !== 'processing' ) return false;

		$results      = json_decode( $job->results ?: '{}', true ) ?: [];
		$current_page = max( 1, (int) ( $results['current_page'] ?? 1 ) );

		as_enqueue_async_action(
			self::HOOK_IMPORT,
			[ 'job_id' => $job_id, 'page' => $current_page + 1 ],
			self::GROUP
		);

		// Reset heartbeat so watchdog doesn't immediately re-trigger
		WC_RM_Job_Manager::update( $job_id, [ 'current_page' => $current_page ] );

		return true;
	}

	private static function make_client( array $options ): WC_RM_Api_Client {
		return new WC_RM_Api_Client(
			$options['source_url']      ?? '',
			$options['consumer_key']    ?? '',
			$options['consumer_secret'] ?? ''
		);
	}
}
