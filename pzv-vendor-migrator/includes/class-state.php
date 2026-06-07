<?php
/**
 * wp_options-based job state for all migration phases.
 */
defined( 'ABSPATH' ) || exit;

class PZV_Mig_State {

	const KEY_SETTINGS    = 'pzv_mig_settings';
	const KEY_MEMBER_JOB  = 'pzv_mig_member_job';
	const KEY_VENDOR_JOB  = 'pzv_mig_vendor_job';
	const KEY_LINK_JOB    = 'pzv_mig_link_job';
	const KEY_COMM_JOB    = 'pzv_mig_comm_job';

	// ---- Settings ----

	public static function get_settings(): array {
		return (array) get_option( self::KEY_SETTINGS, [] );
	}

	public static function save_settings( array $s ): void {
		update_option( self::KEY_SETTINGS, $s, false );
	}

	public static function make_client(): ?PZV_Mig_Api_Client {
		$s = self::get_settings();
		if ( empty( $s['source_url'] ) || empty( $s['username'] ) || empty( $s['app_password'] ) ) return null;
		return new PZV_Mig_Api_Client( $s['source_url'], $s['username'], $s['app_password'] );
	}

	// ---- Member import job ----

	public static function member_job(): array {
		return (array) get_option( self::KEY_MEMBER_JOB, [ 'status' => 'idle' ] );
	}

	public static function member_job_start( int $total ): void {
		update_option( self::KEY_MEMBER_JOB, [
			'status'       => 'running',
			'total'        => $total,
			'processed'    => 0,
			'current_page' => 0,
			'created'      => 0,
			'updated'      => 0,
			'errors'       => [],
			'started_at'   => time(),
		], false );
	}

	public static function member_job_update( array $delta ): void {
		$job = self::member_job();
		if ( isset( $delta['errors'] ) ) {
			$delta['errors'] = array_merge( $job['errors'] ?? [], $delta['errors'] );
			if ( count( $delta['errors'] ) > 200 ) {
				$delta['errors'] = array_slice( $delta['errors'], -200 );
			}
		}
		update_option( self::KEY_MEMBER_JOB, array_merge( $job, $delta ), false );
	}

	public static function member_job_done(): void {
		self::member_job_update( [ 'status' => 'completed' ] );
	}

	public static function member_job_reset(): void {
		delete_option( self::KEY_MEMBER_JOB );
	}

	// ---- Vendor import job ----

	public static function vendor_job(): array {
		return (array) get_option( self::KEY_VENDOR_JOB, [ 'status' => 'idle' ] );
	}

	public static function vendor_job_start( int $total ): void {
		update_option( self::KEY_VENDOR_JOB, [
			'status'       => 'running',
			'total'        => $total,
			'processed'    => 0,
			'current_page' => 0,
			'created'      => 0,
			'updated'      => 0,
			'errors'       => [],
			'vendor_list'  => [], // [{source_id, dest_id, email, store_name}]
			'started_at'   => time(),
		], false );
	}

	public static function vendor_job_update( array $delta ): void {
		$job = self::vendor_job();
		// Merge arrays (errors + vendor_list are appended)
		if ( isset( $delta['errors'] ) ) {
			$delta['errors'] = array_merge( $job['errors'] ?? [], $delta['errors'] );
			if ( count( $delta['errors'] ) > 200 ) {
				$delta['errors'] = array_slice( $delta['errors'], -200 );
			}
		}
		if ( isset( $delta['vendor_list_append'] ) ) {
			$delta['vendor_list'] = array_merge( $job['vendor_list'] ?? [], $delta['vendor_list_append'] );
			unset( $delta['vendor_list_append'] );
		}
		update_option( self::KEY_VENDOR_JOB, array_merge( $job, $delta ), false );
	}

	public static function vendor_job_done(): void {
		self::vendor_job_update( [ 'status' => 'completed' ] );
	}

	public static function vendor_job_reset(): void {
		delete_option( self::KEY_VENDOR_JOB );
	}

	// ---- Product link job ----

	public static function link_job(): array {
		return (array) get_option( self::KEY_LINK_JOB, [ 'status' => 'idle' ] );
	}

	public static function link_job_start( int $vendor_count ): void {
		$vendor_list = self::vendor_job()['vendor_list'] ?? [];
		update_option( self::KEY_LINK_JOB, [
			'status'            => 'running',
			'vendor_list'       => $vendor_list,
			'vendor_total'      => $vendor_count,
			'vendor_index'      => 0,    // which vendor we're on
			'vendor_sku_page'   => 1,    // which SKU page for current vendor
			'linked'            => 0,
			'missing'           => 0,
			'errors'            => [],
			'started_at'        => time(),
		], false );
	}

	public static function link_job_update( array $delta ): void {
		$job = self::link_job();
		if ( isset( $delta['errors'] ) ) {
			$delta['errors'] = array_merge( $job['errors'] ?? [], $delta['errors'] );
		}
		update_option( self::KEY_LINK_JOB, array_merge( $job, $delta ), false );
	}

	public static function link_job_done(): void {
		self::link_job_update( [ 'status' => 'completed' ] );
	}

	public static function link_job_reset(): void {
		delete_option( self::KEY_LINK_JOB );
	}

	// ---- Commission job ----

	public static function comm_job(): array {
		return (array) get_option( self::KEY_COMM_JOB, [ 'status' => 'idle' ] );
	}

	public static function comm_job_start( int $total ): void {
		update_option( self::KEY_COMM_JOB, [
			'status'       => 'running',
			'total'        => $total,
			'processed'    => 0,
			'current_page' => 0,
			'imported'     => 0,
			'errors'       => [],
			'started_at'   => time(),
		], false );
	}

	public static function comm_job_update( array $delta ): void {
		$job = self::comm_job();
		if ( isset( $delta['errors'] ) ) {
			$delta['errors'] = array_merge( $job['errors'] ?? [], $delta['errors'] );
		}
		update_option( self::KEY_COMM_JOB, array_merge( $job, $delta ), false );
	}

	public static function comm_job_done(): void {
		self::comm_job_update( [ 'status' => 'completed' ] );
	}

	public static function comm_job_reset(): void {
		delete_option( self::KEY_COMM_JOB );
	}
}
