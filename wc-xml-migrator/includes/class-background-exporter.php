<?php
defined( 'ABSPATH' ) || exit;

class WC_XML_Background_Exporter {

	const HOOK_BATCH    = 'wc_xml_migrator_export_batch';
	const HOOK_FINALIZE = 'wc_xml_migrator_export_finalize';
	const GROUP         = 'wc-xml-migrator';
	const BATCH_SIZE    = 20;

	public static function init(): void {
		add_action( self::HOOK_BATCH,    [ __CLASS__, 'process_batch' ],    10, 2 );
		add_action( self::HOOK_FINALIZE, [ __CLASS__, 'finalize' ],         10, 1 );
	}

	/**
	 * Dışa aktarma işi başlatır; job ID'sini döner.
	 */
	public static function start( array $options ): int {
		$exporter = new WC_XML_Exporter( $options );
		$total    = $exporter->count_products();

		// Temp dosya oluştur
		$dir  = self::uploads_dir();
		$slug = 'wc-export-' . wp_generate_password( 8, false );
		$path = $dir . '/' . $slug . '.tmp';

		file_put_contents( $path, WC_XML_Exporter::get_xml_header( $total ) );

		$job_id = WC_XML_Job_Manager::create( [
			'job_type'    => 'export',
			'status'      => 'processing',
			'total_items' => $total,
			'file_path'   => $path,
			'options'     => $options,
		] );

		if ( $total === 0 ) {
			as_enqueue_async_action( self::HOOK_FINALIZE, [ 'job_id' => $job_id ], self::GROUP );
		} else {
			as_enqueue_async_action( self::HOOK_BATCH, [ 'job_id' => $job_id, 'page' => 1 ], self::GROUP );
		}

		return $job_id;
	}

	public static function process_batch( int $job_id, int $page ): void {
		$job = WC_XML_Job_Manager::get( $job_id );
		if ( ! $job || $job->status !== 'processing' ) return;

		$options     = json_decode( $job->options, true ) ?: [];
		$batch_size  = min( (int) ( $options['batch_size'] ?? self::BATCH_SIZE ), 100 );
		$exporter    = new WC_XML_Exporter( $options );
		$products    = $exporter->get_batch( $page, $batch_size );

		if ( ! empty( $products ) ) {
			$xml = '';
			foreach ( $products as $product ) {
				$xml .= $exporter->product_to_xml_string( $product );
			}
			file_put_contents( $job->file_path, $xml, FILE_APPEND | LOCK_EX );

			WC_XML_Job_Manager::update( $job_id, [
				'processed' => (int) $job->processed + count( $products ),
			] );
		}

		if ( count( $products ) < $batch_size ) {
			as_enqueue_async_action( self::HOOK_FINALIZE, [ 'job_id' => $job_id ], self::GROUP );
		} else {
			as_enqueue_async_action( self::HOOK_BATCH, [ 'job_id' => $job_id, 'page' => $page + 1 ], self::GROUP );
		}
	}

	public static function finalize( int $job_id ): void {
		$job = WC_XML_Job_Manager::get( $job_id );
		if ( ! $job ) return;

		if ( ! file_exists( $job->file_path ) ) {
			WC_XML_Job_Manager::fail( $job_id, 'Geçici dosya bulunamadı.' );
			return;
		}

		// Kategori ve marka görsellerini ekle
		$options  = json_decode( $job->options, true ) ?: [];
		$exporter = new WC_XML_Exporter( $options );
		$term_xml = $exporter->export_term_images_xml();
		if ( $term_xml ) {
			file_put_contents( $job->file_path, $term_xml, FILE_APPEND | LOCK_EX );
		}

		file_put_contents( $job->file_path, WC_XML_Exporter::get_xml_footer(), FILE_APPEND | LOCK_EX );

		$xml_path = preg_replace( '/\.tmp$/', '.xml', $job->file_path );
		rename( $job->file_path, $xml_path );

		$upload_dir = wp_upload_dir();
		$file_url   = str_replace(
			trailingslashit( $upload_dir['basedir'] ),
			trailingslashit( $upload_dir['baseurl'] ),
			$xml_path
		);

		WC_XML_Job_Manager::update( $job_id, [
			'status'    => 'completed',
			'file_path' => $xml_path,
			'file_url'  => $file_url,
		] );
	}

	private static function uploads_dir(): string {
		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/wc-xml-migrator';
		wp_mkdir_p( $dir );

		// Dizini listelemeden koru
		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents( $htaccess, "Options -Indexes\n" );
		}

		return $dir;
	}
}
