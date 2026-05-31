<?php
defined( 'ABSPATH' ) || exit;

class WC_XML_Background_Importer {

	const HOOK_BATCH = 'wc_xml_migrator_import_batch';
	const GROUP      = 'wc-xml-migrator';
	const BATCH_SIZE = 10;

	public static function init(): void {
		add_action( self::HOOK_BATCH, [ __CLASS__, 'process_batch' ], 10, 2 );
	}

	/**
	 * İçe aktarma işi başlatır; job ID'sini döner.
	 */
	public static function start( string $file_path, array $options ): int {
		$total = self::count_products_in_xml( $file_path );

		$job_id = WC_XML_Job_Manager::create( [
			'job_type'    => 'import',
			'status'      => 'processing',
			'total_items' => $total,
			'file_path'   => $file_path,
			'options'     => $options,
		] );

		if ( $total === 0 ) {
			WC_XML_Job_Manager::update( $job_id, [ 'status' => 'completed' ] );
		} else {
			as_enqueue_async_action( self::HOOK_BATCH, [ 'job_id' => $job_id, 'offset' => 0 ], self::GROUP );
		}

		return $job_id;
	}

	public static function process_batch( int $job_id, int $offset ): void {
		$job = WC_XML_Job_Manager::get( $job_id );
		if ( ! $job || $job->status !== 'processing' ) return;

		if ( ! file_exists( $job->file_path ) ) {
			WC_XML_Job_Manager::fail( $job_id, 'XML dosyası bulunamadı: ' . $job->file_path );
			return;
		}

		$options     = json_decode( $job->options, true ) ?: [];
		$batch_size  = self::BATCH_SIZE;
		$nodes       = self::read_product_nodes( $job->file_path, $offset, $batch_size );

		if ( empty( $nodes ) ) {
			WC_XML_Job_Manager::update( $job_id, [ 'status' => 'completed' ] );
			return;
		}

		$importer = new WC_XML_Importer( $options );
		$errors   = [];

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

		if ( count( $nodes ) < $batch_size ) {
			WC_XML_Job_Manager::update( $job_id, [ 'status' => 'completed' ] );
		} else {
			as_enqueue_async_action(
				self::HOOK_BATCH,
				[ 'job_id' => $job_id, 'offset' => $new_processed ],
				self::GROUP
			);
		}
	}

	/**
	 * XMLReader ile yalnızca sayım yapar – belleği düşük tutar.
	 */
	private static function count_products_in_xml( string $file_path ): int {
		$reader = new XMLReader();
		if ( ! $reader->open( $file_path ) ) return 0;

		$count = 0;
		while ( $reader->read() ) {
			if ( $reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'product' ) {
				$count++;
				$reader->next(); // alt düğümleri atla
			}
		}
		$reader->close();
		return $count;
	}

	/**
	 * Belirtilen offset ve limit kadar ürün XML'ini döner.
	 * DOMDocument kullanır – güvenilir ve basit.
	 */
	private static function read_product_nodes( string $file_path, int $offset, int $limit ): array {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );

		if ( ! $dom->load( $file_path ) ) {
			libxml_clear_errors();
			return [];
		}
		libxml_clear_errors();

		$nodes  = $dom->getElementsByTagName( 'product' );
		$result = [];
		$end    = min( $offset + $limit, $nodes->length );

		for ( $i = $offset; $i < $end; $i++ ) {
			$node = $nodes->item( $i );
			if ( $node ) {
				$result[] = $dom->saveXML( $node );
			}
		}

		return $result;
	}
}
