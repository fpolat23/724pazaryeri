<?php
defined( 'ABSPATH' ) || exit;

class WC_Cat_Job_Manager {

	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wc_cat_migrator_jobs';
	}

	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE IF NOT EXISTS " . self::table() . " (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			status      VARCHAR(20)  NOT NULL DEFAULT 'pending',
			total       INT(11)      NOT NULL DEFAULT 0,
			processed   INT(11)      NOT NULL DEFAULT 0,
			file_path   TEXT         DEFAULT NULL,
			options     LONGTEXT     DEFAULT NULL,
			errors      LONGTEXT     DEFAULT NULL,
			created_at  DATETIME     NOT NULL,
			updated_at  DATETIME     NOT NULL,
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

		if ( isset( $data['errors'] ) ) {
			$existing        = self::get_errors( $id );
			$set['errors']   = wp_json_encode( array_values( array_merge( $existing, (array) $data['errors'] ) ) );
		}

		$wpdb->update( self::table(), $set, [ 'id' => $id ] );
	}

	public static function get( int $id ): ?object {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id=%d', $id ) );
	}

	public static function get_recent( int $limit = 10 ): array {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC LIMIT %d', $limit
		) );
	}

	public static function get_errors( int $id ): array {
		$job = self::get( $id );
		return $job ? ( json_decode( $job->errors ?: '[]', true ) ?: [] ) : [];
	}

	public static function fail( int $id, string $msg ): void {
		self::update( $id, [ 'status' => 'failed', 'errors' => [ $msg ] ] );
	}
}
