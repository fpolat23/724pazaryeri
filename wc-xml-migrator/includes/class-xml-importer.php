<?php
defined( 'ABSPATH' ) || exit;

class WC_XML_Importer {

	private array $results = [
		'created'  => 0,
		'updated'  => 0,
		'skipped'  => 0,
		'errors'   => [],
	];

	private bool $update_existing;
	private bool $download_images;
	private string $duplicate_strategy; // 'skip' | 'update' | 'create_new'

	public function __construct( array $options = [] ) {
		$this->update_existing    = (bool) ( $options['update_existing'] ?? true );
		$this->download_images    = (bool) ( $options['download_images'] ?? true );
		$this->duplicate_strategy = $options['duplicate_strategy'] ?? 'update';
	}

	/**
	 * Tek bir ürün XML string'ini içe aktarır (arka plan işlemci için).
	 */
	public function import_node_xml( string $product_xml ): void {
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadXML( $product_xml );
		libxml_clear_errors();

		$nodes = $dom->getElementsByTagName( 'product' );
		if ( $nodes->length ) {
			$this->process_product( $nodes->item( 0 ) );
		}
	}

	public function get_results(): array {
		return $this->results;
	}

	/**
	 * XML string'ten ürünleri içe aktarır; sonuç dizisini döner.
	 */
	public function import( string $xml_content ): array {
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadXML( $xml_content );

		$errors = libxml_get_errors();
		libxml_clear_errors();

		if ( ! empty( $errors ) ) {
			$messages = array_map( fn( $e ) => trim( $e->message ), $errors );
			$this->results['errors'][] = 'XML ayrıştırma hatası: ' . implode( '; ', $messages );
			return $this->results;
		}

		$products = $dom->getElementsByTagName( 'product' );

		foreach ( $products as $product_node ) {
			$this->process_product( $product_node );
		}

		return $this->results;
	}

	private function process_product( DOMElement $node ): void {
		try {
			$sku  = $this->get_text( $node, 'sku' );
			$type = $this->get_text( $node, 'type' ) ?: 'simple';
			$name = $this->get_text( $node, 'name' );

			if ( empty( $name ) ) {
				$this->results['skipped']++;
				return;
			}

			$existing_id = $sku ? wc_get_product_id_by_sku( $sku ) : 0;

			if ( $existing_id && $this->duplicate_strategy === 'skip' ) {
				$this->results['skipped']++;
				return;
			}

			if ( $existing_id && $this->duplicate_strategy === 'create_new' ) {
				$existing_id = 0;
			}

			$product = $this->create_or_load_product( $type, $existing_id );

			$this->set_base_fields( $product, $node );
			$this->set_categories( $product, $node );
			$this->set_tags( $product, $node );
			$this->set_attributes( $product, $node );

			if ( $this->download_images ) {
				$this->set_images( $product, $node );
			}

			$product->save();

			if ( $type === 'variable' ) {
				$this->set_variations( $product, $node );
			}

			if ( $existing_id ) {
				$this->results['updated']++;
			} else {
				$this->results['created']++;
			}
		} catch ( Throwable $e ) {
			$this->results['errors'][] = sprintf(
				'Ürün aktarım hatası (SKU: %s): %s',
				$this->get_text( $node, 'sku' ) ?: '?',
				$e->getMessage()
			);
		}
	}

	private function create_or_load_product( string $type, int $existing_id ): WC_Product {
		if ( $existing_id ) {
			$product = wc_get_product( $existing_id );
			if ( $product ) return $product;
		}

		$class_map = [
			'variable' => 'WC_Product_Variable',
			'grouped'  => 'WC_Product_Grouped',
			'external' => 'WC_Product_External',
			'simple'   => 'WC_Product_Simple',
		];

		$class = $class_map[ $type ] ?? 'WC_Product_Simple';
		return new $class();
	}

	private function set_base_fields( WC_Product $product, DOMElement $node ): void {
		$product->set_name( $this->get_text( $node, 'name' ) );
		$product->set_slug( $this->get_text( $node, 'slug' ) );
		$product->set_status( $this->get_text( $node, 'status' ) ?: 'publish' );
		$product->set_description( $this->get_text( $node, 'description' ) );
		$product->set_short_description( $this->get_text( $node, 'short_description' ) );
		$product->set_catalog_visibility( $this->get_text( $node, 'visibility' ) ?: 'visible' );
		$product->set_featured( $this->get_text( $node, 'featured' ) === '1' );
		$product->set_sold_individually( $this->get_text( $node, 'sold_individually' ) === '1' );
		$product->set_tax_status( $this->get_text( $node, 'tax_status' ) ?: 'taxable' );
		$product->set_tax_class( $this->get_text( $node, 'tax_class' ) );

		$sku = $this->get_text( $node, 'sku' );
		if ( $sku ) $product->set_sku( $sku );

		$regular = $this->get_text( $node, 'regular_price' );
		if ( $regular !== '' ) $product->set_regular_price( $regular );

		$sale = $this->get_text( $node, 'sale_price' );
		if ( $sale !== '' ) $product->set_sale_price( $sale );

		$stock_status = $this->get_text( $node, 'stock_status' );
		if ( $stock_status ) $product->set_stock_status( $stock_status );

		if ( $this->get_text( $node, 'manage_stock' ) === '1' ) {
			$product->set_manage_stock( true );
			$qty = $this->get_text( $node, 'stock_quantity' );
			if ( $qty !== '' ) $product->set_stock_quantity( (float) $qty );
		}

		$weight = $this->get_text( $node, 'weight' );
		if ( $weight !== '' ) $product->set_weight( $weight );

		$product->set_length( $this->get_text( $node, 'length' ) );
		$product->set_width( $this->get_text( $node, 'width' ) );
		$product->set_height( $this->get_text( $node, 'height' ) );

		if ( $product instanceof WC_Product_External ) {
			$product->set_product_url( $this->get_text( $node, 'external_url' ) );
			$product->set_button_text( $this->get_text( $node, 'button_text' ) );
		}
	}

	private function set_categories( WC_Product $product, DOMElement $node ): void {
		$cat_ids = [];
		foreach ( $node->getElementsByTagName( 'categories' ) as $cats_el ) {
			foreach ( $cats_el->getElementsByTagName( 'category' ) as $cat_node ) {
				$name        = $this->get_text( $cat_node, 'name' );
				$slug        = $this->get_text( $cat_node, 'slug' );
				$parent_slug = $this->get_text( $cat_node, 'parent_slug' );

				$parent_id = 0;
				if ( $parent_slug ) {
					$parent_term = get_term_by( 'slug', $parent_slug, 'product_cat' );
					if ( $parent_term ) $parent_id = $parent_term->term_id;
				}

				$term = get_term_by( 'slug', $slug, 'product_cat' );
				if ( ! $term ) {
					$inserted = wp_insert_term( $name, 'product_cat', [
						'slug'   => $slug,
						'parent' => $parent_id,
					] );
					if ( ! is_wp_error( $inserted ) ) {
						$cat_ids[] = $inserted['term_id'];
					}
				} else {
					$cat_ids[] = $term->term_id;
				}
			}
		}

		if ( ! empty( $cat_ids ) ) {
			$product->set_category_ids( $cat_ids );
		}
	}

	private function set_tags( WC_Product $product, DOMElement $node ): void {
		$tag_ids = [];
		foreach ( $node->getElementsByTagName( 'tags' ) as $tags_el ) {
			foreach ( $tags_el->getElementsByTagName( 'tag' ) as $tag_node ) {
				$name = $this->get_text( $tag_node, 'name' );
				$slug = $this->get_text( $tag_node, 'slug' );

				$term = get_term_by( 'slug', $slug, 'product_tag' );
				if ( ! $term ) {
					$inserted = wp_insert_term( $name, 'product_tag', [ 'slug' => $slug ] );
					if ( ! is_wp_error( $inserted ) ) {
						$tag_ids[] = $inserted['term_id'];
					}
				} else {
					$tag_ids[] = $term->term_id;
				}
			}
		}

		if ( ! empty( $tag_ids ) ) {
			$product->set_tag_ids( $tag_ids );
		}
	}

	private function set_attributes( WC_Product $product, DOMElement $node ): void {
		$attrs = [];

		// Sadece direkt <attributes> çocuklarını al (variation içindeki değil)
		foreach ( $node->childNodes as $child ) {
			if ( ! ( $child instanceof DOMElement ) || $child->tagName !== 'attributes' ) continue;

			foreach ( $child->getElementsByTagName( 'attribute' ) as $attr_node ) {
				$name      = $this->get_text( $attr_node, 'name' );
				$visible   = $this->get_text( $attr_node, 'visible' ) === '1';
				$variation = $this->get_text( $attr_node, 'variation' ) === '1';
				$position  = (int) $this->get_text( $attr_node, 'position' );

				$values = [];
				foreach ( $attr_node->getElementsByTagName( 'value' ) as $val_node ) {
					$values[] = $val_node->nodeValue;
				}

				$attribute = new WC_Product_Attribute();
				$attribute->set_name( $name );
				$attribute->set_options( $values );
				$attribute->set_visible( $visible );
				$attribute->set_variation( $variation );
				$attribute->set_position( $position );
				$attrs[] = $attribute;
			}
			break;
		}

		if ( ! empty( $attrs ) ) {
			$product->set_attributes( $attrs );
		}
	}

	private function set_images( WC_Product $product, DOMElement $node ): void {
		$main_id    = 0;
		$gallery_ids = [];

		foreach ( $node->childNodes as $child ) {
			if ( ! ( $child instanceof DOMElement ) || $child->tagName !== 'images' ) continue;

			foreach ( $child->getElementsByTagName( 'image' ) as $img_node ) {
				$url     = $this->get_text( $img_node, 'url' );
				$alt     = $this->get_text( $img_node, 'alt' );
				$title   = $this->get_text( $img_node, 'title' );
				$is_main = $img_node->getAttribute( 'main' ) === '1';

				if ( ! $url ) continue;

				$attachment_id = $this->sideload_image( $url, $product->get_id(), $alt, $title );
				if ( ! $attachment_id ) continue;

				if ( $is_main && ! $main_id ) {
					$main_id = $attachment_id;
				} else {
					$gallery_ids[] = $attachment_id;
				}
			}
			break;
		}

		if ( $main_id ) $product->set_image_id( $main_id );
		if ( ! empty( $gallery_ids ) ) $product->set_gallery_image_ids( $gallery_ids );
	}

	private function set_variations( WC_Product $product, DOMElement $node ): void {
		foreach ( $node->getElementsByTagName( 'variations' ) as $vars_el ) {
			foreach ( $vars_el->getElementsByTagName( 'variation' ) as $var_node ) {
				$var_sku = $this->get_text( $var_node, 'sku' );
				$var_id  = $var_sku ? wc_get_product_id_by_sku( $var_sku ) : 0;

				if ( $var_id ) {
					$variation = wc_get_product( $var_id );
				} else {
					$variation = new WC_Product_Variation();
					$variation->set_parent_id( $product->get_id() );
				}

				if ( ! $variation ) continue;

				if ( $var_sku ) $variation->set_sku( $var_sku );
				$variation->set_status( $this->get_text( $var_node, 'status' ) ?: 'publish' );
				$variation->set_description( $this->get_text( $var_node, 'description' ) );

				$reg = $this->get_text( $var_node, 'regular_price' );
				if ( $reg !== '' ) $variation->set_regular_price( $reg );

				$sal = $this->get_text( $var_node, 'sale_price' );
				if ( $sal !== '' ) $variation->set_sale_price( $sal );

				$ss = $this->get_text( $var_node, 'stock_status' );
				if ( $ss ) $variation->set_stock_status( $ss );

				if ( $this->get_text( $var_node, 'manage_stock' ) === '1' ) {
					$variation->set_manage_stock( true );
					$qty = $this->get_text( $var_node, 'stock_quantity' );
					if ( $qty !== '' ) $variation->set_stock_quantity( (float) $qty );
				}

				$weight = $this->get_text( $var_node, 'weight' );
				if ( $weight !== '' ) $variation->set_weight( $weight );

				// Varyasyon öznitelikleri
				$var_attrs = [];
				foreach ( $var_node->getElementsByTagName( 'attributes' ) as $vatts_el ) {
					foreach ( $vatts_el->getElementsByTagName( 'attribute' ) as $att_node ) {
						$slug  = $this->get_text( $att_node, 'slug' );
						$value = $this->get_text( $att_node, 'value' );
						if ( $slug ) $var_attrs[ $slug ] = $value;
					}
					break;
				}
				$variation->set_attributes( $var_attrs );

				if ( $this->download_images ) {
					foreach ( $var_node->childNodes as $c ) {
						if ( ! ( $c instanceof DOMElement ) || $c->tagName !== 'images' ) continue;
						foreach ( $c->getElementsByTagName( 'image' ) as $img_node ) {
							$url = $this->get_text( $img_node, 'url' );
							if ( ! $url ) continue;
							$att_id = $this->sideload_image( $url, 0, '', '' );
							if ( $att_id ) $variation->set_image_id( $att_id );
						}
						break;
					}
				}

				$variation->save();
			}
		}
	}

	/**
	 * Uzak görseli medya kütüphanesine indirir; mevcut ise attachment ID'sini döner.
	 */
	private function sideload_image( string $url, int $post_id, string $alt, string $title ): int {
		// Daha önce aynı URL indirildi mi?
		$existing = $this->find_attachment_by_url( $url );
		if ( $existing ) return $existing;

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) return 0;

		$file_array = [
			'name'     => basename( parse_url( $url, PHP_URL_PATH ) ) ?: 'image.jpg',
			'tmp_name' => $tmp,
		];

		$attachment_id = media_handle_sideload( $file_array, $post_id, $title );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return 0;
		}

		if ( $alt ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}

		// URL'yi meta olarak sakla (sonraki importlarda tekrar indirmeyi önler)
		update_post_meta( $attachment_id, '_wc_xml_source_url', $url );

		return $attachment_id;
	}

	private function find_attachment_by_url( string $url ): int {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wc_xml_source_url' AND meta_value = %s LIMIT 1",
			$url
		) );
		return $id ? (int) $id : 0;
	}

	private function get_text( DOMElement $parent, string $tag ): string {
		$nodes = $parent->getElementsByTagName( $tag );
		if ( ! $nodes->length ) return '';
		// Sadece doğrudan alt düğüm
		foreach ( $nodes as $node ) {
			if ( $node->parentNode === $parent ) {
				return trim( $node->nodeValue );
			}
		}
		return '';
	}
}
