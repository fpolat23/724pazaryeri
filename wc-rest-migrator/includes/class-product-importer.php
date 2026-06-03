<?php
defined( 'ABSPATH' ) || exit;

class WC_RM_Product_Importer {

	private string $duplicate_strategy;
	private bool   $download_images;

	private static array $currency_keys = [
		'_currency', '_product_currency', '_wmc_price_currency',
		'_wc_price_currency', '_wcfm_product_currency',
	];

	public function __construct( array $options = [] ) {
		$this->duplicate_strategy = $options['duplicate_strategy'] ?? 'update';
		$this->download_images    = (bool) ( $options['download_images'] ?? true );
	}

	/**
	 * Import a single product from WC REST API data.
	 * Returns ['status'=>'created'|'updated'|'skipped'|'error', 'sku'=>..., 'name'=>..., 'message'=>...]
	 */
	public function import_product( array $data, array $variations = [] ): array {
		try {
			$sku  = trim( $data['sku']  ?? '' );
			$name = trim( $data['name'] ?? '' );
			$type = $data['type']       ?? 'simple';

			if ( ! $name ) return [ 'status' => 'skipped', 'sku' => $sku, 'name' => '(isimsiz)', 'message' => 'Ürün adı yok' ];

			$existing_id = $sku ? wc_get_product_id_by_sku( $sku ) : 0;

			if ( $existing_id && $this->duplicate_strategy === 'skip' ) {
				return [ 'status' => 'skipped', 'sku' => $sku, 'name' => $name ];
			}
			if ( $existing_id && $this->duplicate_strategy === 'create_new' ) {
				$existing_id = 0;
			}

			$product = $this->create_or_load( $type, $existing_id );
			$is_new  = ! $product->get_id();

			$this->set_base_fields( $product, $data );
			$this->set_categories( $product, $data['categories'] ?? [] );
			$this->set_tags( $product, $data['tags'] ?? [] );
			$this->set_attributes( $product, $data['attributes'] ?? [] );

			if ( $this->download_images ) {
				$this->set_images( $product, $data['images'] ?? [] );
			}

			$product->save();

			$this->set_meta( $product->get_id(), $data['meta_data'] ?? [] );
			$this->set_currency( $product->get_id(), $data );
			$this->set_related( $product, $data );

			if ( $type === 'variable' && ! empty( $variations ) ) {
				$this->set_variations( $product, $variations );
				$synced = wc_get_product( $product->get_id() );
				if ( $synced instanceof WC_Product_Variable ) {
					WC_Product_Variable::sync( $synced );
				}
			}

			return [
				'status' => $is_new ? 'created' : 'updated',
				'sku'    => $product->get_sku(),
				'name'   => $product->get_name(),
			];

		} catch ( Throwable $e ) {
			return [
				'status'  => 'error',
				'sku'     => $data['sku']  ?? '?',
				'name'    => $data['name'] ?? '?',
				'message' => $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')',
			];
		}
	}

	// ---- Private: product building ----

	private function create_or_load( string $type, int $existing_id ): WC_Product {
		if ( $existing_id ) {
			$p = wc_get_product( $existing_id );
			if ( $p ) return $p;
		}
		$map = [
			'variable' => 'WC_Product_Variable',
			'grouped'  => 'WC_Product_Grouped',
			'external' => 'WC_Product_External',
			'simple'   => 'WC_Product_Simple',
		];
		return new ( $map[ $type ] ?? 'WC_Product_Simple' )();
	}

	private function set_base_fields( WC_Product $product, array $d ): void {
		$product->set_name( $d['name'] ?? '' );
		$product->set_slug( $d['slug'] ?? '' );
		$product->set_status( $d['status'] ?? 'publish' );
		$product->set_description( $d['description'] ?? '' );
		$product->set_short_description( $d['short_description'] ?? '' );
		$product->set_catalog_visibility( $d['catalog_visibility'] ?? 'visible' );
		$product->set_featured( ! empty( $d['featured'] ) );
		$product->set_sold_individually( ! empty( $d['sold_individually'] ) );
		$product->set_tax_status( $d['tax_status'] ?? 'taxable' );
		$product->set_tax_class( $d['tax_class'] ?? '' );
		$product->set_reviews_allowed( $d['reviews_allowed'] ?? true );
		$product->set_purchase_note( $d['purchase_note'] ?? '' );
		$product->set_menu_order( (int) ( $d['menu_order'] ?? 0 ) );

		if ( ! empty( $d['sku'] ) ) {
			try { $product->set_sku( $d['sku'] ); } catch ( Throwable $e ) { /* duplicate SKU on other product */ }
		}

		$reg = $d['regular_price'] ?? '';
		if ( $reg !== '' ) $product->set_regular_price( $reg );

		$sale = $d['sale_price'] ?? '';
		if ( $sale !== '' ) $product->set_sale_price( $sale );

		$ss = $d['stock_status'] ?? '';
		if ( $ss ) $product->set_stock_status( $ss );

		if ( ! empty( $d['manage_stock'] ) ) {
			$product->set_manage_stock( true );
			$qty = $d['stock_quantity'] ?? null;
			if ( $qty !== null ) $product->set_stock_quantity( (float) $qty );
		}

		$bo = $d['backorders'] ?? '';
		if ( $bo !== '' ) $product->set_backorders( $bo );

		$dims = $d['dimensions'] ?? [];
		$product->set_weight( $d['weight'] ?? '' );
		$product->set_length( $dims['length'] ?? '' );
		$product->set_width( $dims['width']  ?? '' );
		$product->set_height( $dims['height'] ?? '' );

		$sc = $d['shipping_class'] ?? '';
		if ( $sc ) {
			$term = get_term_by( 'slug', $sc, 'product_shipping_class' );
			if ( $term ) $product->set_shipping_class_id( $term->term_id );
		}

		if ( $product instanceof WC_Product_External ) {
			$product->set_product_url( $d['external_url'] ?? '' );
			$product->set_button_text( $d['button_text']  ?? '' );
		}
	}

	private function set_categories( WC_Product $product, array $cats ): void {
		$ids = [];
		foreach ( $cats as $cat ) {
			$slug = $cat['slug'] ?? '';
			$name = $cat['name'] ?? $slug;
			if ( ! $slug ) continue;

			$term = get_term_by( 'slug', $slug, 'product_cat' );
			if ( ! $term ) {
				$ins = wp_insert_term( $name, 'product_cat', [ 'slug' => $slug ] );
				if ( ! is_wp_error( $ins ) ) $ids[] = $ins['term_id'];
			} else {
				$ids[] = $term->term_id;
			}
		}
		if ( ! empty( $ids ) ) $product->set_category_ids( $ids );
	}

	private function set_tags( WC_Product $product, array $tags ): void {
		$ids = [];
		foreach ( $tags as $tag ) {
			$slug = $tag['slug'] ?? '';
			$name = $tag['name'] ?? $slug;
			if ( ! $slug ) continue;

			$term = get_term_by( 'slug', $slug, 'product_tag' );
			if ( ! $term ) {
				$ins = wp_insert_term( $name, 'product_tag', [ 'slug' => $slug ] );
				if ( ! is_wp_error( $ins ) ) $ids[] = $ins['term_id'];
			} else {
				$ids[] = $term->term_id;
			}
		}
		if ( ! empty( $ids ) ) $product->set_tag_ids( $ids );
	}

	private function set_attributes( WC_Product $product, array $attributes ): void {
		$attrs = [];
		foreach ( $attributes as $a ) {
			$attr_id     = (int) ( $a['id'] ?? 0 );
			$name        = $a['name']     ?? '';
			$slug        = $a['slug']     ?? sanitize_title( $name );
			$is_taxonomy = $attr_id > 0 && str_starts_with( $slug, 'pa_' );
			$options     = $a['options']  ?? [];

			$attribute = new WC_Product_Attribute();
			$attribute->set_position( (int) ( $a['position'] ?? 0 ) );
			$attribute->set_visible( ! empty( $a['visible'] ) );
			$attribute->set_variation( ! empty( $a['variation'] ) );

			if ( $is_taxonomy ) {
				$wc_id = $this->ensure_global_attribute( $name, $slug );
				$attribute->set_id( $wc_id );
				$attribute->set_name( $slug );

				$term_ids = [];
				foreach ( $options as $option ) {
					$ts = sanitize_title( $option );
					$tid = $this->ensure_term( $slug, $option, $ts );
					if ( $tid ) $term_ids[] = $tid;
				}
				$attribute->set_options( $term_ids );
				$attrs[ $slug ] = $attribute;
			} else {
				$attribute->set_name( $name );
				$attribute->set_options( $options );
				$attrs[ sanitize_title( $name ) ] = $attribute;
			}
		}
		if ( ! empty( $attrs ) ) $product->set_attributes( $attrs );
	}

	private function set_images( WC_Product $product, array $images ): void {
		$main = 0;
		$gal  = [];
		foreach ( $images as $i => $img ) {
			$url = $img['src'] ?? '';
			if ( ! $url ) continue;
			$att = $this->sideload_image( $url, $product->get_id(), $img['alt'] ?? '', $img['name'] ?? '' );
			if ( ! $att ) continue;
			if ( $i === 0 ) $main  = $att;
			else            $gal[] = $att;
		}
		if ( $main ) $product->set_image_id( $main );
		if ( ! empty( $gal ) ) $product->set_gallery_image_ids( $gal );
	}

	private function set_variations( WC_Product $product, array $variations ): void {
		foreach ( $variations as $vd ) {
			$var_sku = trim( $vd['sku'] ?? '' );
			$var_id  = $var_sku ? wc_get_product_id_by_sku( $var_sku ) : 0;

			if ( $var_id ) {
				$variation = wc_get_product( $var_id );
			} else {
				$variation = new WC_Product_Variation();
				$variation->set_parent_id( $product->get_id() );
			}
			if ( ! $variation ) continue;

			if ( $var_sku ) {
				try { $variation->set_sku( $var_sku ); } catch ( Throwable $e ) { }
			}

			$variation->set_status( $vd['status'] ?? 'publish' );
			$variation->set_description( $vd['description'] ?? '' );
			$variation->set_menu_order( (int) ( $vd['menu_order'] ?? 0 ) );

			$reg = $vd['regular_price'] ?? '';
			if ( $reg !== '' ) $variation->set_regular_price( $reg );
			$sal = $vd['sale_price'] ?? '';
			if ( $sal !== '' ) $variation->set_sale_price( $sal );

			$ss = $vd['stock_status'] ?? '';
			if ( $ss ) $variation->set_stock_status( $ss );

			if ( ! empty( $vd['manage_stock'] ) ) {
				$variation->set_manage_stock( true );
				$qty = $vd['stock_quantity'] ?? null;
				if ( $qty !== null ) $variation->set_stock_quantity( (float) $qty );
			}

			$bo = $vd['backorders'] ?? '';
			if ( $bo !== '' ) $variation->set_backorders( $bo );

			$variation->set_weight( $vd['weight'] ?? '' );
			$dims = $vd['dimensions'] ?? [];
			$variation->set_length( $dims['length'] ?? '' );
			$variation->set_width( $dims['width']   ?? '' );
			$variation->set_height( $dims['height'] ?? '' );

			$vtax = $vd['tax_class'] ?? '';
			if ( $vtax !== '' ) $variation->set_tax_class( $vtax );

			$vsc = $vd['shipping_class'] ?? '';
			if ( $vsc ) {
				$t = get_term_by( 'slug', $vsc, 'product_shipping_class' );
				if ( $t ) $variation->set_shipping_class_id( $t->term_id );
			}

			// WC REST API: attributes = [{id, name, slug, option}]
			// slug is e.g. "pa_renk", option is the term name
			$var_attrs = [];
			foreach ( $vd['attributes'] ?? [] as $att ) {
				$att_slug = $att['slug'] ?? '';
				$att_val  = sanitize_title( $att['option'] ?? '' );
				if ( ! $att_slug ) continue;
				$key = str_starts_with( $att_slug, 'attribute_' ) ? $att_slug : 'attribute_' . $att_slug;
				$var_attrs[ $key ] = $att_val;
			}
			$variation->set_attributes( $var_attrs );

			if ( $this->download_images && ! empty( $vd['image']['src'] ) ) {
				$att = $this->sideload_image( $vd['image']['src'], 0, $vd['image']['alt'] ?? '', '' );
				if ( $att ) $variation->set_image_id( $att );
			}

			$variation->save();
			$this->set_currency( $variation->get_id(), $vd );
		}
	}

	private function set_meta( int $id, array $meta_data ): void {
		$skip = [
			'_thumbnail_id', '_product_image_gallery',
			'_price', '_regular_price', '_sale_price',
			'_min_variation_price', '_max_variation_price',
			'_min_variation_regular_price', '_max_variation_regular_price',
			'_min_variation_sale_price', '_max_variation_sale_price',
			'_wc_rating_count', '_wc_review_count', '_wc_average_rating',
			'_stock', '_stock_status', '_sku', '_manage_stock', '_backorders', '_low_stock_amount',
			'_weight', '_length', '_width', '_height',
			'_tax_status', '_tax_class', '_sold_individually', '_featured',
			'_virtual', '_downloadable', '_visibility', '_purchase_note',
			'_default_attributes', '_product_attributes', '_children',
			'_product_version', 'total_sales', '_edit_lock', '_edit_last',
		];
		foreach ( $meta_data as $m ) {
			$key = $m['key'] ?? '';
			$val = $m['value'] ?? '';
			if ( ! $key || in_array( $key, $skip, true ) ) continue;
			if ( str_starts_with( $key, '_edit_' ) ) continue;
			if ( is_array( $val ) || is_object( $val ) ) continue;
			update_post_meta( $id, $key, $val );
		}
	}

	private function set_currency( int $id, array $data ): void {
		// Detect currency from meta_data
		$currency = '';
		foreach ( $data['meta_data'] ?? [] as $m ) {
			if ( in_array( $m['key'] ?? '', self::$currency_keys, true ) ) {
				$v = $m['value'] ?? '';
				if ( $v && strlen( $v ) <= 5 ) { $currency = strtoupper( $v ); break; }
			}
		}
		if ( ! $currency ) return;

		$wrote = false;
		foreach ( self::$currency_keys as $key ) {
			if ( metadata_exists( 'post', $id, $key ) ) {
				update_post_meta( $id, $key, $currency );
				$wrote = true;
			}
		}
		if ( ! $wrote && $currency !== (string) get_option( 'woocommerce_currency', 'TRY' ) ) {
			update_post_meta( $id, '_currency', $currency );
		}
	}

	private function set_related( WC_Product $product, array $data ): void {
		// upsells / cross-sells come as arrays of IDs from the API
		// We resolve by SKU if possible, otherwise skip (IDs differ between sites)
		// WC REST API also provides upsell_ids / cross_sell_ids as numeric IDs — not portable.
		// We skip this here; use the XML migrator for upsell/crosssell support if needed.
	}

	// ---- Taxonomy helpers ----

	private function ensure_global_attribute( string $label, string $taxonomy ): int {
		$id = wc_attribute_taxonomy_id_by_name( $taxonomy );
		if ( $id ) return $id;

		$slug   = str_starts_with( $taxonomy, 'pa_' ) ? substr( $taxonomy, 3 ) : $taxonomy;
		$result = wc_create_attribute( [
			'name'         => $label,
			'slug'         => $slug,
			'type'         => 'select',
			'order_by'     => 'menu_order',
			'has_archives' => false,
		] );
		if ( is_wp_error( $result ) ) {
			return wc_attribute_taxonomy_id_by_name( $taxonomy ) ?: 0;
		}
		wc_register_attribute_taxonomies();
		return (int) $result;
	}

	private function ensure_term( string $taxonomy, string $name, string $slug ): int {
		if ( ! taxonomy_exists( $taxonomy ) ) return 0;
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) return $term->term_id;
		$ins = wp_insert_term( $name, $taxonomy, [ 'slug' => $slug ] );
		if ( is_wp_error( $ins ) ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			return $term ? $term->term_id : 0;
		}
		return (int) $ins['term_id'];
	}

	// ---- Image helpers ----

	private function sideload_image( string $url, int $post_id, string $alt, string $title ): int {
		$existing = $this->find_attachment_by_url( $url );
		if ( $existing ) return $existing;

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) return 0;

		$file = [
			'name'     => basename( parse_url( $url, PHP_URL_PATH ) ) ?: 'image.jpg',
			'tmp_name' => $tmp,
		];
		$att = media_handle_sideload( $file, $post_id, $title );
		if ( is_wp_error( $att ) ) { @unlink( $tmp ); return 0; }

		if ( $alt ) update_post_meta( $att, '_wp_attachment_image_alt', $alt );
		update_post_meta( $att, '_wc_rm_source_url', $url );
		return $att;
	}

	private function find_attachment_by_url( string $url ): int {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_wc_rm_source_url' AND meta_value=%s LIMIT 1",
			$url
		) );
		return $id ? (int) $id : 0;
	}
}
