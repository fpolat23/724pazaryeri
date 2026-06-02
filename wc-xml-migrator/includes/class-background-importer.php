<?php
defined( 'ABSPATH' ) || exit;

class WC_XML_Background_Importer {

	const HOOK_BATCH        = 'wc_xml_migrator_import_batch';
	const HOOK_TERM_IMAGES  = 'wc_xml_migrator_import_term_images';
	const GROUP             = 'wc-xml-migrator';
	const BATCH_SIZE        = 10;

	public static function init(): void {
		add_action( self::HOOK_BATCH,       [ __CLASS__, 'process_batch' ],       10, 2 );
		add_action( self::HOOK_TERM_IMAGES, [ __CLASS__, 'process_term_images' ], 10, 1 );
	}

	public static function start( string $file_path, array $options ): int {
		$total = self::count_products_in_xml( $file_path );

		$job_id = WC_XML_Job_Manager::create( [
			'job_type'    => 'import',
			'status'      => 'processing',
			'total_items' => $total,
			'file_path'   => $file_path,
			'options'     => $options,
		] );

		// İlk adım: ürünler → son adım: term görselleri
		if ( $total > 0 ) {
			as_enqueue_async_action( self::HOOK_BATCH, [ 'job_id' => $job_id, 'offset' => 0 ], self::GROUP );
		} else {
			// Ürün yoksa direkt term görsellerine geç
			as_enqueue_async_action( self::HOOK_TERM_IMAGES, [ 'job_id' => $job_id ], self::GROUP );
		}

		return $job_id;
	}

	/**
	 * Duraklatılan import için kaldığı offsetten devam eder.
	 */
	public static function resume( int $job_id ): void {
		$job = WC_XML_Job_Manager::get( $job_id );
		if ( ! $job ) return;

		if ( (int) $job->processed < (int) $job->total_items ) {
			as_enqueue_async_action( self::HOOK_BATCH, [ 'job_id' => $job_id, 'offset' => (int) $job->processed ], self::GROUP );
		} else {
			as_enqueue_async_action( self::HOOK_TERM_IMAGES, [ 'job_id' => $job_id ], self::GROUP );
		}
	}

	public static function process_batch( int $job_id, int $offset ): void {
		$job = WC_XML_Job_Manager::get( $job_id );
		if ( ! $job || $job->status !== 'processing' ) return;

		if ( ! file_exists( $job->file_path ) ) {
			WC_XML_Job_Manager::fail( $job_id, 'XML dosyası bulunamadı: ' . $job->file_path );
			return;
		}

		$options    = json_decode( $job->options, true ) ?: [];
		$nodes      = self::read_product_nodes( $job->file_path, $offset, self::BATCH_SIZE );
		$importer   = new WC_XML_Importer( $options );
		$errors     = [];

		foreach ( $nodes as $index => $node_xml ) {
			try {
				$importer->import_node_xml( $node_xml );
			} catch ( Throwable $e ) {
				$errors[] = sprintf( 'Ürün %d: %s', $offset + $index + 1, $e->getMessage() );
			}
		}

		$new_processed = $offset + count( $nodes );

		WC_XML_Job_Manager::update( $job_id, [
			'processed' => $new_processed,
			'errors'    => $errors,
		] );

		if ( count( $nodes ) < self::BATCH_SIZE ) {
			// Ürünler bitti → term görsellerini içe aktar
			as_enqueue_async_action( self::HOOK_TERM_IMAGES, [ 'job_id' => $job_id ], self::GROUP );
		} else {
			as_enqueue_async_action(
				self::HOOK_BATCH,
				[ 'job_id' => $job_id, 'offset' => $new_processed ],
				self::GROUP
			);
		}
	}

	/**
	 * Son adım: kategori ve marka görsellerini içe aktarır.
	 */
	public static function process_term_images( int $job_id ): void {
		$job = WC_XML_Job_Manager::get( $job_id );
		if ( ! $job || $job->status !== 'processing' ) return;

		$options  = json_decode( $job->options, true ) ?: [];
		$importer = new WC_XML_Importer( $options );

		$term_results = $importer->import_term_images_from_file( $job->file_path );

		if ( ! empty( $term_results['errors'] ) ) {
			WC_XML_Job_Manager::update( $job_id, [ 'errors' => $term_results['errors'] ] );
		}

		WC_XML_Job_Manager::update( $job_id, [ 'status' => 'completed' ] );
	}

	private static function count_products_in_xml( string $file_path ): int {
		$reader = new XMLReader();
		if ( ! $reader->open( $file_path ) ) return 0;

		$count = 0;
		while ( $reader->read() ) {
			if ( $reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'product' ) {
				$count++;
				$reader->next();
			}
		}
		$reader->close();
		return $count;
	}

	private static function read_product_nodes( string $file_path, int $offset, int $limit ): array {
		$reader = new XMLReader();
		if ( ! @$reader->open( $file_path ) ) return []; // phpcs:ignore

		$result  = [];
		$current = 0;

		libxml_use_internal_errors( true );

		while ( $reader->read() ) {
			if ( $reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'product' ) {
				continue;
			}

			if ( $current < $offset ) {
				$reader->next(); // Alt ağacı atla; döngünün read() çağrısı end tag'i geçer
				$current++;
				continue;
			}

			if ( count( $result ) >= $limit ) break;

			$xml = $reader->readOuterXML(); // Elementi oku ve okuyucuyu ilerlet
			if ( $xml ) $result[] = $xml;
			$current++;
		}

		libxml_clear_errors();
		$reader->close();
		return $result;
	}
}
