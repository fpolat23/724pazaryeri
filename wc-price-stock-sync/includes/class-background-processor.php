<?php
defined( 'ABSPATH' ) || exit;

class WC_PSS_Background_Processor {

	const HOOK       = 'wc_pss_process_batch';
	const GROUP      = 'wc-pss';
	const BATCH_SIZE = 50;

	public static function init(): void {
		add_action( self::HOOK, [ __CLASS__, 'process_batch' ], 10, 2 );
	}

	public static function start( string $file_path, array $options ): int {
		$total = self::count_rows( $file_path );

		$job_id = WC_PSS_Job_Manager::create( [
			'status'    => 'processing',
			'total'     => $total,
			'file_path' => $file_path,
			'options'   => $options,
		] );

		if ( $total === 0 ) {
			WC_PSS_Job_Manager::update( $job_id, [ 'status' => 'completed' ] );
		} else {
			if ( ! function_exists( 'as_enqueue_async_action' ) ) {
				WC_PSS_Job_Manager::fail( $job_id, 'Action Scheduler mevcut değil. WooCommerce\'in güncel olduğundan emin olun.' );
				throw new \RuntimeException( 'Action Scheduler (as_enqueue_async_action) bu sitede mevcut değil.' );
			}
			as_enqueue_async_action( self::HOOK, [ 'job_id' => $job_id, 'offset' => 0 ], self::GROUP );
		}

		return $job_id;
	}

	public static function process_batch( int $job_id, int $offset ): void {
		$job = WC_PSS_Job_Manager::get( $job_id );
		if ( ! $job || $job->status !== 'processing' ) return;

		if ( ! file_exists( $job->file_path ) ) {
			WC_PSS_Job_Manager::fail( $job_id, 'CSV dosyası bulunamadı.' );
			return;
		}

		$options  = json_decode( $job->options, true ) ?: [];
		$rows     = self::read_rows( $job->file_path, $offset, self::BATCH_SIZE );
		$updater  = new WC_PSS_Updater( $options );
		$errors   = [];
		$updated  = 0;
		$not_found = 0;
		$skipped  = 0;

		foreach ( $rows as $i => $row ) {
			try {
				$result = $updater->process_row( $row );
				switch ( $result['status'] ) {
					case 'updated':   $updated++;    break;
					case 'not_found': $not_found++;  break;
					default:          $skipped++;    break;
				}
			} catch ( Throwable $e ) {
				$errors[] = 'Satır ' . ( $offset + $i + 1 ) . ': ' . $e->getMessage();
			}
		}

		$new_processed = $offset + count( $rows );

		WC_PSS_Job_Manager::update( $job_id, [
			'processed' => $new_processed,
			'results'   => compact( 'updated', 'not_found', 'skipped' ),
			'errors'    => $errors,
		] );

		if ( count( $rows ) < self::BATCH_SIZE ) {
			WC_PSS_Job_Manager::update( $job_id, [ 'status' => 'completed' ] );
		} else {
			as_enqueue_async_action( self::HOOK, [ 'job_id' => $job_id, 'offset' => $new_processed ], self::GROUP );
		}
	}

	private static function count_rows( string $file_path ): int {
		$fp = @fopen( $file_path, 'r' ); // phpcs:ignore
		if ( ! $fp ) return 0;
		fgetcsv( $fp ); // başlık satırını atla
		$count = 0;
		while ( fgetcsv( $fp ) !== false ) $count++;
		fclose( $fp );
		return $count;
	}

	private static function read_rows( string $file_path, int $offset, int $limit ): array {
		$fp = @fopen( $file_path, 'r' ); // phpcs:ignore
		if ( ! $fp ) return [];

		$header = fgetcsv( $fp );
		if ( ! $header ) { fclose( $fp ); return []; }

		// Başlık adlarını normalize et: küçük harf, boşluk temizle
		$header = array_map( function ( $h ) { return strtolower( trim( $h ) ); }, $header );
		$col_count = count( $header );

		$rows    = [];
		$current = 0;

		while ( ( $row = fgetcsv( $fp ) ) !== false ) {
			if ( $current < $offset ) { $current++; continue; }
			if ( count( $rows ) >= $limit ) break;

			if ( count( $row ) >= $col_count ) {
				$rows[] = array_combine( $header, array_slice( $row, 0, $col_count ) );
			}
			$current++;
		}

		fclose( $fp );
		return $rows;
	}
}
