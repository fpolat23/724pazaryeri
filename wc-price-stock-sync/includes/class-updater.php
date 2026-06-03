<?php
defined( 'ABSPATH' ) || exit;

class WC_PSS_Updater {

	private bool $update_prices;
	private bool $update_stock;
	private bool $match_by_name;

	public function __construct( array $options = [] ) {
		$this->update_prices = (bool) ( $options['update_prices'] ?? true );
		$this->update_stock  = (bool) ( $options['update_stock']  ?? true );
		$this->match_by_name = (bool) ( $options['match_by_name'] ?? false );
	}

	/**
	 * Process a single scraped product row.
	 * Returns ['status' => 'updated'|'not_found'|'skipped']
	 */
	public function process_row( array $row ): array {
		$sku  = trim( $row['sku']  ?? '' );
		$name = trim( $row['name'] ?? '' );

		$product_id = null;

		if ( $sku !== '' ) {
			$product_id = wc_get_product_id_by_sku( $sku ) ?: null;
		}

		if ( ! $product_id && $name !== '' && $this->match_by_name ) {
			$product_id = self::find_by_name( $name ) ?: null;
		}

		if ( ! $product_id ) return [ 'status' => 'not_found' ];

		$product = wc_get_product( $product_id );
		if ( ! $product ) return [ 'status' => 'not_found' ];

		$changed = false;

		if ( $this->update_prices ) {
			$reg = WC_PSS_Scraper::parse_price( $row['regular_price'] ?? '' );
			if ( $reg !== '' ) {
				$product->set_regular_price( wc_format_decimal( $reg ) );
				$changed = true;
			}
			if ( array_key_exists( 'sale_price', $row ) ) {
				$sale = WC_PSS_Scraper::parse_price( $row['sale_price'] ?? '' );
				$product->set_sale_price( $sale !== '' ? wc_format_decimal( $sale ) : '' );
				$changed = true;
			}
		}

		if ( $this->update_stock ) {
			if ( isset( $row['stock_status'] ) && $row['stock_status'] !== '' ) {
				$valid = [ 'instock', 'outofstock', 'onbackorder' ];
				if ( in_array( $row['stock_status'], $valid, true ) ) {
					$product->set_stock_status( $row['stock_status'] );
					$changed = true;
				}
			}
			if ( isset( $row['stock_quantity'] ) && $row['stock_quantity'] !== '' ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( (int) $row['stock_quantity'] );
				$changed = true;
			}
		}

		if ( ! $changed ) return [ 'status' => 'skipped' ];

		$product->save();
		return [ 'status' => 'updated' ];
	}

	private static function find_by_name( string $name ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_title   = %s
			   AND post_type    IN ('product','product_variation')
			   AND post_status  IN ('publish','private')
			 LIMIT 1",
			$name
		) );
	}
}
