<?php
defined( 'ABSPATH' ) || exit;

class WC_PSS_Job_Manager {

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wc_pss_jobs';
	}

	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE IF NOT EXISTS " . self::table() . " (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			status     VARCHAR(20)  NOT NULL DEFAULT 'pending',
			total      INT(11)      NOT NULL DEFAULT 0,
			processed  INT(11)      NOT NULL DEFAULT 0,
			file_path  TEXT         DEFAULT NULL,
			options    LONGTEXT     DEFAULT NULL,
			results    LONGTEXT     DEFAULT NULL,
			errors     LONGTEXT     DEFAULT NULL,
			created_at DATETIME     NOT NULL,
			updated_at DATETIME     NOT NULL,
			PRIMARY KEY (id)
		) $charset;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function create( array $data ): int {
		global $wpdb;
		$now = current_time( 'mysql' );
		$wpdb->insert( self::table(), [
			'status'    => $data['status']    ?? 'pending',
			'total'     => $data['total']     ?? 0,
			'processed' => 0,
			'file_path' => $data['file_path'] ?? null,
			'options'   => isset( $data['options'] ) ? wp_json_encode( $data['options'] ) : null,
			'results'   => wp_json_encode( [ 'updated' => 0, 'not_found' => 0, 'skipped' => 0 ] ),
			'errors'    => wp_json_encode( [] ),
			'created_at' => $now,
			'updated_at' => $now,
		] );
		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $data ): void {
		global $wpdb;
		$set = [ 'updated_at' => current_time( 'mysql' ) ];

		if ( isset( $data['status'] ) )    $set['status']    = $data['status'];
		if ( isset( $data['processed'] ) ) $set['processed'] = (int) $data['processed'];
		if ( isset( $data['total'] ) )     $set['total']     = (int) $data['total'];
		if ( isset( $data['file_path'] ) ) $set['file_path'] = $data['file_path'];

		if ( isset( $data['results'] ) ) {
			$job      = self::get( $id );
			$existing = json_decode( $job ? $job->results : '{}', true ) ?: [];
			$new      = (array) $data['results'];
			$set['results'] = wp_json_encode( [
				'updated'   => ( $existing['updated']   ?? 0 ) + (int) ( $new['updated']   ?? 0 ),
				'not_found' => ( $existing['not_found'] ?? 0 ) + (int) ( $new['not_found'] ?? 0 ),
				'skipped'   => ( $existing['skipped']   ?? 0 ) + (int) ( $new['skipped']   ?? 0 ),
			] );
		}

		if ( isset( $data['errors'] ) ) {
			$job      = self::get( $id );
			$existing = json_decode( $job ? $job->errors : '[]', true ) ?: [];
			$merged   = array_merge( $existing, (array) $data['errors'] );
			// Hata listesini en fazla 200 girişle sınırla
			$set['errors'] = wp_json_encode( array_values( array_slice( $merged, 0, 200 ) ) );
		}

		$wpdb->update( self::table(), $set, [ 'id' => $id ] );
	}

	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );
	}

	public static function get_recent( int $limit = 20 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC LIMIT %d', $limit ) );
	}

	public static function fail( int $id, string $msg ): void {
		self::update( $id, [ 'status' => 'failed', 'errors' => [ $msg ] ] );
	}

	public static function delete( int $id ): void {
		global $wpdb;
		$job = self::get( $id );
		if ( $job && $job->file_path && file_exists( $job->file_path ) ) {
			@unlink( $job->file_path ); // phpcs:ignore
		}
		$wpdb->delete( self::table(), [ 'id' => $id ] );
	}
}
