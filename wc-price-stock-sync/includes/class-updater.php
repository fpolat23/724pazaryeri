<?php
defined( 'ABSPATH' ) || exit;

class WC_PSS_Updater {

	private bool $update_prices;
	private bool $update_stock;

	public function __construct( array $options = [] ) {
		$this->update_prices = (bool) ( $options['update_prices'] ?? true );
		$this->update_stock  = (bool) ( $options['update_stock']  ?? true );
	}

	/**
	 * Tek bir CSV satırını işler.
	 * Döner: ['status' => 'updated'|'not_found'|'skipped']
	 */
	public function process_row( array $row ): array {
		$sku = trim( $row['sku'] ?? '' );
		if ( $sku === '' ) return [ 'status' => 'skipped' ];

		$product_id = wc_get_product_id_by_sku( $sku );
		if ( ! $product_id ) return [ 'status' => 'not_found' ];

		$product = wc_get_product( $product_id );
		if ( ! $product ) return [ 'status' => 'not_found' ];

		$changed = false;

		if ( $this->update_prices ) {
			if ( isset( $row['regular_price'] ) && $row['regular_price'] !== '' ) {
				$product->set_regular_price( wc_format_decimal( $row['regular_price'] ) );
				$changed = true;
			}
			// sale_price anahtarı varsa (boş string bile olsa) güncelle
			if ( array_key_exists( 'sale_price', $row ) ) {
				$sale = trim( $row['sale_price'] );
				$product->set_sale_price( $sale !== '' ? wc_format_decimal( $sale ) : '' );
				$changed = true;
			}
		}

		if ( $this->update_stock ) {
			if ( isset( $row['manage_stock'] ) && $row['manage_stock'] !== '' ) {
				$manage = in_array( strtolower( trim( $row['manage_stock'] ) ), [ 'yes', '1', 'true', 'evet' ], true );
				$product->set_manage_stock( $manage );
				$changed = true;
			}
			if ( isset( $row['stock_quantity'] ) && $row['stock_quantity'] !== '' ) {
				$product->set_manage_stock( true );
				$product->set_stock_quantity( (int) $row['stock_quantity'] );
				$changed = true;
			}
			if ( isset( $row['stock_status'] ) && $row['stock_status'] !== '' ) {
				$valid = [ 'instock', 'outofstock', 'onbackorder' ];
				$status = strtolower( trim( $row['stock_status'] ) );
				if ( in_array( $status, $valid, true ) ) {
					$product->set_stock_status( $status );
					$changed = true;
				}
			}
		}

		if ( ! $changed ) return [ 'status' => 'skipped' ];

		$product->save();
		return [ 'status' => 'updated' ];
	}
}
