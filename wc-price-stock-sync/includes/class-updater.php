<?php
defined( 'ABSPATH' ) || exit;

class WC_PSS_Updater {

	private bool   $update_prices;
	private bool   $update_stock;
	private bool   $match_by_name;
	private array  $price_rules;
	private string $sku_prefix;

	public function __construct( array $options = [] ) {
		$this->update_prices = (bool)   ( $options['update_prices'] ?? true );
		$this->update_stock  = (bool)   ( $options['update_stock']  ?? true );
		$this->match_by_name = (bool)   ( $options['match_by_name'] ?? false );
		$this->price_rules   = (array)  ( $options['price_rules']   ?? [] );
		$this->sku_prefix    = (string) ( $options['sku_prefix']    ?? '' );
	}

	/**
	 * Apply percentage markup based on configured price ranges.
	 * Range check: min <= price < max (max empty = no upper limit).
	 * Same ratio applied to both regular and sale prices so discount is preserved.
	 */
	private function apply_markup( float $price ): float {
		if ( empty( $this->price_rules ) || $price <= 0 ) return $price;
		foreach ( $this->price_rules as $rule ) {
			$min = (float) ( $rule['min'] ?? 0 );
			$max = ( isset( $rule['max'] ) && $rule['max'] !== '' ) ? (float) $rule['max'] : PHP_FLOAT_MAX;
			if ( $price >= $min && $price < $max ) {
				$pct = (float) ( $rule['pct'] ?? 0 );
				return round( $price * ( 1 + $pct / 100 ), 2 );
			}
		}
		return $price;
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
			// Fallback: try with configured prefix (e.g. source has "076.87136330", WC has "BÇ-076.87136330")
			if ( ! $product_id && $this->sku_prefix !== '' ) {
				$product_id = wc_get_product_id_by_sku( $this->sku_prefix . $sku ) ?: null;
			}
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
				$product->set_regular_price( wc_format_decimal( $this->apply_markup( (float) $reg ) ) );
				$changed = true;
			}
			if ( array_key_exists( 'sale_price', $row ) ) {
				$sale = WC_PSS_Scraper::parse_price( $row['sale_price'] ?? '' );
				$product->set_sale_price( $sale !== '' ? wc_format_decimal( $this->apply_markup( (float) $sale ) ) : '' );
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

		$old_reg  = $product->get_regular_price();
		$old_sale = $product->get_sale_price();
		$product->save();
		return [
			'status'    => 'updated',
			'sku'       => $product->get_sku(),
			'name'      => $product->get_name(),
			'old_price' => $old_reg,
			'new_price' => $product->get_regular_price(),
		];
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
