<?php
defined( 'ABSPATH' ) || exit;

class WC_Cat_Background_Importer {

	const HOOK       = 'wc_cat_migrator_import_batch';
	const GROUP      = 'wc-cat-migrator';
	const BATCH_SIZE = 5; // Her turda 5 kategori; görsel indirme yavaş olabilir

	public static function init(): void {
		add_action( self::HOOK, [ __CLASS__, 'process_batch' ], 10, 2 );
	}

	/**
	 * İşi başlatır. XML dosyasını kaydeder, job oluşturur, ilk batch'i kuyruğa alır.
	 * Job ID'sini döner.
	 */
	public static function start( string $file_path, array $options ): int {
		// Kategori sayısını say
		$total = self::count_categories( $file_path );

		$job_id = WC_Cat_Job_Manager::create( [
			'status'    => 'processing',
			'total'     => $total,
			'file_path' => $file_path,
			'options'   => $options,
		] );

		if ( $total === 0 ) {
			WC_Cat_Job_Manager::update( $job_id, [ 'status' => 'completed' ] );
		} else {
			as_enqueue_async_action( self::HOOK, [ 'job_id' => $job_id, 'offset' => 0 ], self::GROUP );
		}

		return $job_id;
	}

	/**
	 * Action Scheduler tarafından çağrılır.
	 */
	public static function process_batch( int $job_id, int $offset ): void {
		$job = WC_Cat_Job_Manager::get( $job_id );
		if ( ! $job || $job->status !== 'processing' ) return;

		if ( ! file_exists( $job->file_path ) ) {
			WC_Cat_Job_Manager::fail( $job_id, 'XML dosyası bulunamadı.' );
			return;
		}

		$options = json_decode( $job->options, true ) ?: [];

		// XML'den bu batch'in kategorilerini oku
		$nodes = self::read_nodes( $job->file_path, $offset, self::BATCH_SIZE );

		if ( empty( $nodes ) ) {
			WC_Cat_Job_Manager::update( $job_id, [ 'status' => 'completed' ] );
			return;
		}

		// Importer her batch başında mevcut kategorileri yeniden yükler (önceki batch'ler ekledi)
		$importer = new WC_Cat_Migrator_Importer( $options );
		$errors   = [];

		foreach ( $nodes as $i => $node_xml ) {
			try {
				$result = $importer->import_single_xml( $node_xml );
				if ( ! empty( $result['errors'] ) ) {
					$errors = array_merge( $errors, $result['errors'] );
				}
			} catch ( Throwable $e ) {
				$errors[] = 'Kategori ' . ( $offset + $i + 1 ) . ': ' . $e->getMessage();
			}
		}

		$new_processed = $offset + count( $nodes );

		WC_Cat_Job_Manager::update( $job_id, [
			'processed' => $new_processed,
			'errors'    => $errors,
		] );

		if ( count( $nodes ) < self::BATCH_SIZE ) {
			// Son batch tamamlandı
			WC_Cat_Job_Manager::update( $job_id, [ 'status' => 'completed' ] );
		} else {
			// Sonraki batch'i kuyruğa al
			as_enqueue_async_action( self::HOOK, [ 'job_id' => $job_id, 'offset' => $new_processed ], self::GROUP );
		}
	}

	private static function count_categories( string $file_path ): int {
		$reader = new XMLReader();
		if ( ! $reader->open( $file_path ) ) return 0;
		$count = 0;
		while ( $reader->read() ) {
			if ( $reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'category' ) {
				$count++;
				$reader->next();
			}
		}
		$reader->close();
		return $count;
	}

	private static function read_nodes( string $file_path, int $offset, int $limit ): array {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		if ( ! $dom->load( $file_path ) ) {
			libxml_clear_errors();
			return [];
		}
		libxml_clear_errors();

		$all    = $dom->getElementsByTagName( 'category' );
		$result = [];
		$end    = min( $offset + $limit, $all->length );

		for ( $i = $offset; $i < $end; $i++ ) {
			$node = $all->item( $i );
			if ( $node ) $result[] = $dom->saveXML( $node );
		}

		return $result;
	}
}
