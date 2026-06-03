<?php
defined( 'ABSPATH' ) || exit;

class WC_RM_Job_Manager {

	const TABLE = 'wc_rm_jobs';

	public static function install(): void {
		global $wpdb;
		$table   = $wpdb->prefix . self::TABLE;
		$charset = $wpdb->get_charset_collate();

		// dbDelta() silently aborts when TEXT columns have non-NULL defaults (invalid MySQL).
		// Direct CREATE TABLE IF NOT EXISTS is more reliable here.
		$wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} (
			id          bigint(20)   NOT NULL AUTO_INCREMENT,
			status      varchar(20)  NOT NULL DEFAULT 'pending',
			source_url  varchar(500) NOT NULL DEFAULT '',
			options     longtext,
			total       int(11)      NOT NULL DEFAULT 0,
			processed   int(11)      NOT NULL DEFAULT 0,
			results     longtext,
			errors      longtext,
			created_at  datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) {$charset}" );
	}

	public static function create( array $data ): int {
		global $wpdb;

		$row = [
			'status'    => $data['status']     ?? 'pending',
			'source_url'=> $data['source_url'] ?? '',
			'options'   => wp_json_encode( $data['options'] ?? [] ),
			'total'     => $data['total']      ?? 0,
		];

		$result = $wpdb->insert( $wpdb->prefix . self::TABLE, $row );

		// Tablo yoksa oluştur ve tekrar dene
		if ( $result === false ) {
			self::install();
			$result = $wpdb->insert( $wpdb->prefix . self::TABLE, $row );
		}

		if ( $result === false || ! $wpdb->insert_id ) {
			throw new \RuntimeException( 'İş kaydı oluşturulamadı. DB hatası: ' . $wpdb->last_error );
		}

		return (int) $wpdb->insert_id;
	}

	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}" . self::TABLE . " WHERE id = %d", $id
		) ) ?: null;
	}

	public static function update( int $id, array $data ): void {
		global $wpdb;
		$set = array_intersect_key( $data, array_flip( [ 'status', 'total', 'processed', 'source_url' ] ) );

		// Accumulate results + imported_items
		if ( isset( $data['results'] ) || isset( $data['imported_items'] ) ) {
			$job      = self::get( $id );
			$existing = json_decode( $job ? $job->results : '{}', true ) ?: [];
			$new      = (array) ( $data['results'] ?? [] );

			$ex_items  = $existing['imported_items'] ?? [];
			$new_items = (array) ( $data['imported_items'] ?? [] );
			$merged    = array_merge( $ex_items, $new_items );
			if ( count( $merged ) > 500 ) $merged = array_slice( $merged, 0, 500 );

			$set['results'] = wp_json_encode( [
				'created'        => ( $existing['created']      ?? 0 ) + (int) ( $new['created']      ?? 0 ),
				'updated'        => ( $existing['updated']      ?? 0 ) + (int) ( $new['updated']      ?? 0 ),
				'skipped'        => ( $existing['skipped']      ?? 0 ) + (int) ( $new['skipped']      ?? 0 ),
				'errors_count'   => ( $existing['errors_count'] ?? 0 ) + (int) ( $new['errors_count'] ?? 0 ),
				'imported_items' => $merged,
			] );
		}

		// Accumulate errors
		if ( isset( $data['errors'] ) ) {
			$job   = self::get( $id );
			$errs  = json_decode( $job ? $job->errors : '[]', true ) ?: [];
			$errs  = array_merge( $errs, (array) $data['errors'] );
			if ( count( $errs ) > 200 ) $errs = array_slice( $errs, 0, 200 );
			$set['errors'] = wp_json_encode( $errs );
		}

		if ( ! empty( $set ) ) {
			$wpdb->update( $wpdb->prefix . self::TABLE, $set, [ 'id' => $id ] );
		}
	}

	public static function fail( int $id, string $message ): void {
		global $wpdb;
		$job  = self::get( $id );
		$errs = json_decode( $job ? $job->errors : '[]', true ) ?: [];
		$errs[] = $message;
		$wpdb->update( $wpdb->prefix . self::TABLE, [
			'status' => 'failed',
			'errors' => wp_json_encode( $errs ),
		], [ 'id' => $id ] );
	}

	public static function get_recent( int $limit = 30 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}" . self::TABLE . " ORDER BY id DESC LIMIT %d", $limit
		) ) ?: [];
	}

	public static function delete( int $id ): void {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . self::TABLE, [ 'id' => $id ] );
	}
}
