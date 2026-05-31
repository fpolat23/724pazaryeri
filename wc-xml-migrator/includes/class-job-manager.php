<?php
defined( 'ABSPATH' ) || exit;

class WC_XML_Job_Manager {

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wc_xml_migrator_jobs';
	}

	public static function create_table(): void {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			job_type    VARCHAR(20)  NOT NULL,
			status      VARCHAR(20)  NOT NULL DEFAULT 'pending',
			total_items INT(11)      NOT NULL DEFAULT 0,
			processed   INT(11)      NOT NULL DEFAULT 0,
			file_path   TEXT         DEFAULT NULL,
			file_url    TEXT         DEFAULT NULL,
			options     LONGTEXT     DEFAULT NULL,
			errors      LONGTEXT     DEFAULT NULL,
			created_at  DATETIME     NOT NULL,
			updated_at  DATETIME     NOT NULL,
			PRIMARY KEY (id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function create( array $data ): int {
		global $wpdb;

		$now = current_time( 'mysql' );
		$wpdb->insert( self::table(), [
			'job_type'   => $data['job_type'],
			'status'     => $data['status'] ?? 'pending',
			'total_items' => $data['total_items'] ?? 0,
			'processed'  => 0,
			'file_path'  => $data['file_path'] ?? null,
			'file_url'   => $data['file_url'] ?? null,
			'options'    => isset( $data['options'] ) ? wp_json_encode( $data['options'] ) : null,
			'errors'     => wp_json_encode( [] ),
			'created_at' => $now,
			'updated_at' => $now,
		] );

		return (int) $wpdb->insert_id;
	}

	public static function update( int $id, array $data ): void {
		global $wpdb;

		$set = [ 'updated_at' => current_time( 'mysql' ) ];

		foreach ( [ 'status', 'file_path', 'file_url' ] as $col ) {
			if ( isset( $data[ $col ] ) ) $set[ $col ] = $data[ $col ];
		}

		foreach ( [ 'total_items', 'processed' ] as $col ) {
			if ( isset( $data[ $col ] ) ) $set[ $col ] = (int) $data[ $col ];
		}

		if ( isset( $data['errors'] ) ) {
			$existing = self::get_errors( $id );
			$merged   = array_merge( $existing, (array) $data['errors'] );
			$set['errors'] = wp_json_encode( array_values( $merged ) );
		}

		$wpdb->update( self::table(), $set, [ 'id' => $id ] );
	}

	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . self::table() . " WHERE id = %d",
			$id
		) );
	}

	public static function get_recent( int $limit = 10 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . self::table() . " ORDER BY created_at DESC LIMIT %d",
			$limit
		) );
	}

	public static function get_errors( int $id ): array {
		$job = self::get( $id );
		if ( ! $job ) return [];
		return json_decode( $job->errors ?: '[]', true ) ?: [];
	}

	public static function fail( int $id, string $message ): void {
		self::update( $id, [
			'status' => 'failed',
			'errors' => [ $message ],
		] );
	}
}
