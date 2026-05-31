<?php
defined( 'ABSPATH' ) || exit;

class WC_XML_Exporter {

	private $batch_size;
	private $include_images;
	private $include_variations;
	private $include_meta;
	private $category_filter;
	private $status_filter;

	public function __construct( array $options = [] ) {
		$this->batch_size        = (int) ( $options['batch_size'] ?? 50 );
		$this->include_images    = (bool) ( $options['include_images'] ?? true );
		$this->include_variations = (bool) ( $options['include_variations'] ?? true );
		$this->include_meta      = (bool) ( $options['include_meta'] ?? false );
		$this->category_filter   = array_filter( (array) ( $options['categories'] ?? [] ) );
		$this->status_filter     = $options['status'] ?? 'publish';
	}

	// ---- Arka plan işlem için public yardımcılar ----

	public function count_products(): int {
		$args = [ 'status' => $this->status_filter, 'return' => 'ids', 'limit' => -1 ];
		if ( ! empty( $this->category_filter ) ) $args['category'] = $this->category_filter;
		return count( wc_get_products( $args ) );
	}

	public function get_batch( int $page, int $size = 0 ): array {
		if ( $size > 0 ) $this->batch_size = $size;
		return $this->query_products( $page );
	}

	public function product_to_xml_string( WC_Product $product ): string {
		$dom  = new DOMDocument( '1.0', 'UTF-8' );
		$dom->formatOutput = true;
		$root = $dom->createElement( 'root' );
		$dom->appendChild( $root );
		$this->append_product( $dom, $root, $product );
		$el = $root->firstChild;
		return $el ? $dom->saveXML( $el ) . "\n" : '';
	}

	public static function get_xml_header( int $total = 0 ): string {
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
		       '<wc_products' .
		       ' version="' . esc_attr( WC_XML_MIGRATOR_VERSION ) . '"' .
		       ' exported_at="' . esc_attr( current_time( 'c' ) ) . '"' .
		       ' site_url="' . esc_attr( get_site_url() ) . '"' .
		       ' total_products="' . (int) $total . '">' . "\n";
	}

	public static function get_xml_footer(): string {
		return '</wc_products>' . "\n";
	}

	// ---- Tek seferlik tam dışa aktarma (eski davranış) ----

	/**
	 * Tüm ürünleri XML olarak dışa aktarır; XML dizesini döner.
	 */
	public function export(): string {
		$dom = new DOMDocument( '1.0', 'UTF-8' );
		$dom->formatOutput = true;

		$root = $dom->createElement( 'wc_products' );
		$root->setAttribute( 'version', WC_XML_MIGRATOR_VERSION );
		$root->setAttribute( 'exported_at', current_time( 'c' ) );
		$root->setAttribute( 'site_url', get_site_url() );
		$dom->appendChild( $root );

		$page   = 1;
		$total  = 0;

		do {
			$products = $this->query_products( $page );
			foreach ( $products as $product ) {
				$this->append_product( $dom, $root, $product );
				$total++;
			}
			$page++;
		} while ( count( $products ) === $this->batch_size );

		$root->setAttribute( 'total_products', (string) $total );

		return $dom->saveXML();
	}

	private function query_products( int $page ): array {
		$args = [
			'status'   => $this->status_filter,
			'limit'    => $this->batch_size,
			'page'     => $page,
			'orderby'  => 'ID',
			'order'    => 'ASC',
			'type'     => [ 'simple', 'variable', 'grouped', 'external' ],
		];

		if ( ! empty( $this->category_filter ) ) {
			$args['category'] = $this->category_filter;
		}

		return wc_get_products( $args );
	}

	private function append_product( DOMDocument $dom, DOMElement $root, WC_Product $product ): void {
		$el = $dom->createElement( 'product' );
		$root->appendChild( $el );

		$this->add_text( $dom, $el, 'id', $product->get_id() );
		$this->add_text( $dom, $el, 'type', $product->get_type() );
		$this->add_text( $dom, $el, 'sku', $product->get_sku() );
		$this->add_text( $dom, $el, 'name', $product->get_name() );
		$this->add_text( $dom, $el, 'slug', $product->get_slug() );
		$this->add_text( $dom, $el, 'status', $product->get_status() );
		$this->add_text( $dom, $el, 'description', $product->get_description() );
		$this->add_text( $dom, $el, 'short_description', $product->get_short_description() );
		$this->add_text( $dom, $el, 'regular_price', $product->get_regular_price() );
		$this->add_text( $dom, $el, 'sale_price', $product->get_sale_price() );
		$this->add_text( $dom, $el, 'stock_status', $product->get_stock_status() );
		$this->add_text( $dom, $el, 'stock_quantity', $product->get_stock_quantity() );
		$this->add_text( $dom, $el, 'manage_stock', $product->get_manage_stock() ? '1' : '0' );
		$this->add_text( $dom, $el, 'weight', $product->get_weight() );
		$this->add_text( $dom, $el, 'length', $product->get_length() );
		$this->add_text( $dom, $el, 'width', $product->get_width() );
		$this->add_text( $dom, $el, 'height', $product->get_height() );
		$this->add_text( $dom, $el, 'tax_status', $product->get_tax_status() );
		$this->add_text( $dom, $el, 'tax_class', $product->get_tax_class() );
		$this->add_text( $dom, $el, 'visibility', $product->get_catalog_visibility() );
		$this->add_text( $dom, $el, 'featured', $product->get_featured() ? '1' : '0' );
		$this->add_text( $dom, $el, 'sold_individually', $product->get_sold_individually() ? '1' : '0' );

		if ( $product->get_type() === 'external' ) {
			/** @var WC_Product_External $product */
			$this->add_text( $dom, $el, 'external_url', $product->get_product_url() );
			$this->add_text( $dom, $el, 'button_text', $product->get_button_text() );
		}

		$this->append_categories( $dom, $el, $product );
		$this->append_tags( $dom, $el, $product );
		$this->append_attributes( $dom, $el, $product );

		if ( $this->include_images ) {
			$this->append_images( $dom, $el, $product );
		}

		if ( $this->include_variations && $product->get_type() === 'variable' ) {
			$this->append_variations( $dom, $el, $product );
		}

		if ( $this->include_meta ) {
			$this->append_meta( $dom, $el, $product );
		}
	}

	private function append_categories( DOMDocument $dom, DOMElement $el, WC_Product $product ): void {
		$cats_el = $dom->createElement( 'categories' );
		$el->appendChild( $cats_el );

		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		if ( ! is_array( $terms ) ) return;

		foreach ( $terms as $term ) {
			$cat = $dom->createElement( 'category' );
			$this->add_text( $dom, $cat, 'id', $term->term_id );
			$this->add_text( $dom, $cat, 'name', $term->name );
			$this->add_text( $dom, $cat, 'slug', $term->slug );

			$parent = get_term( $term->parent, 'product_cat' );
			if ( $parent && ! is_wp_error( $parent ) && $parent->term_id ) {
				$this->add_text( $dom, $cat, 'parent_slug', $parent->slug );
			}
			$cats_el->appendChild( $cat );
		}
	}

	private function append_tags( DOMDocument $dom, DOMElement $el, WC_Product $product ): void {
		$tags_el = $dom->createElement( 'tags' );
		$el->appendChild( $tags_el );

		$terms = get_the_terms( $product->get_id(), 'product_tag' );
		if ( ! is_array( $terms ) ) return;

		foreach ( $terms as $term ) {
			$tag = $dom->createElement( 'tag' );
			$this->add_text( $dom, $tag, 'name', $term->name );
			$this->add_text( $dom, $tag, 'slug', $term->slug );
			$tags_el->appendChild( $tag );
		}
	}

	private function append_attributes( DOMDocument $dom, DOMElement $el, WC_Product $product ): void {
		$attrs_el = $dom->createElement( 'attributes' );
		$el->appendChild( $attrs_el );

		foreach ( $product->get_attributes() as $key => $attribute ) {
			$attr = $dom->createElement( 'attribute' );
			$this->add_text( $dom, $attr, 'name', $attribute->get_name() );
			$this->add_text( $dom, $attr, 'slug', $key );
			$this->add_text( $dom, $attr, 'position', $attribute->get_position() );
			$this->add_text( $dom, $attr, 'visible', $attribute->get_visible() ? '1' : '0' );
			$this->add_text( $dom, $attr, 'variation', $attribute->get_variation() ? '1' : '0' );

			$vals_el = $dom->createElement( 'values' );
			foreach ( $attribute->get_options() as $option ) {
				$term = is_numeric( $option ) ? get_term( (int) $option ) : null;
				$value_text = $term && ! is_wp_error( $term ) ? $term->name : $option;
				$v = $dom->createElement( 'value' );
				$v->appendChild( $dom->createTextNode( (string) $value_text ) );
				$vals_el->appendChild( $v );
			}
			$attr->appendChild( $vals_el );
			$attrs_el->appendChild( $attr );
		}
	}

	private function append_images( DOMDocument $dom, DOMElement $el, WC_Product $product ): void {
		$imgs_el = $dom->createElement( 'images' );
		$el->appendChild( $imgs_el );

		$main_id = $product->get_image_id();
		if ( $main_id ) {
			$this->append_image_node( $dom, $imgs_el, $main_id, true );
		}

		foreach ( $product->get_gallery_image_ids() as $gallery_id ) {
			$this->append_image_node( $dom, $imgs_el, $gallery_id, false );
		}
	}

	private function append_image_node( DOMDocument $dom, DOMElement $parent, int $attachment_id, bool $is_main ): void {
		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) return;

		$img = $dom->createElement( 'image' );
		$img->setAttribute( 'main', $is_main ? '1' : '0' );
		$this->add_text( $dom, $img, 'url', $url );
		$this->add_text( $dom, $img, 'alt', get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		$this->add_text( $dom, $img, 'title', get_the_title( $attachment_id ) );
		$parent->appendChild( $img );
	}

	private function append_variations( DOMDocument $dom, DOMElement $el, WC_Product $product ): void {
		$vars_el = $dom->createElement( 'variations' );
		$el->appendChild( $vars_el );

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( ! $variation ) continue;

			$var = $dom->createElement( 'variation' );
			$this->add_text( $dom, $var, 'id', $variation->get_id() );
			$this->add_text( $dom, $var, 'sku', $variation->get_sku() );
			$this->add_text( $dom, $var, 'regular_price', $variation->get_regular_price() );
			$this->add_text( $dom, $var, 'sale_price', $variation->get_sale_price() );
			$this->add_text( $dom, $var, 'stock_status', $variation->get_stock_status() );
			$this->add_text( $dom, $var, 'stock_quantity', $variation->get_stock_quantity() );
			$this->add_text( $dom, $var, 'manage_stock', $variation->get_manage_stock() ? '1' : '0' );
			$this->add_text( $dom, $var, 'weight', $variation->get_weight() );
			$this->add_text( $dom, $var, 'description', $variation->get_description() );
			$this->add_text( $dom, $var, 'status', $variation->get_status() );

			if ( $this->include_images ) {
				$img_id = $variation->get_image_id();
				if ( $img_id ) {
					$imgs_el = $dom->createElement( 'images' );
					$var->appendChild( $imgs_el );
					$this->append_image_node( $dom, $imgs_el, $img_id, true );
				}
			}

			$vatts_el = $dom->createElement( 'attributes' );
			foreach ( $variation->get_variation_attributes() as $attr_key => $attr_val ) {
				$att = $dom->createElement( 'attribute' );
				$this->add_text( $dom, $att, 'name', wc_attribute_label( str_replace( 'attribute_', '', $attr_key ) ) );
				$this->add_text( $dom, $att, 'slug', $attr_key );
				$this->add_text( $dom, $att, 'value', $attr_val );
				$vatts_el->appendChild( $att );
			}
			$var->appendChild( $vatts_el );
			$vars_el->appendChild( $var );
		}
	}

	private function append_meta( DOMDocument $dom, DOMElement $el, WC_Product $product ): void {
		$meta_el = $dom->createElement( 'meta_data' );
		$el->appendChild( $meta_el );

		$skip_keys = [
			'_thumbnail_id', '_product_image_gallery', '_price', '_regular_price',
			'_sale_price', '_wc_rating_count', '_wc_review_count', '_wc_average_rating',
		];

		foreach ( $product->get_meta_data() as $meta ) {
			$data = $meta->get_data();
			if ( in_array( $data['key'], $skip_keys, true ) ) continue;
			if ( strpos( $data['key'], '_edit_' ) === 0 ) continue;
			if ( is_array( $data['value'] ) || is_object( $data['value'] ) ) continue;

			$m = $dom->createElement( 'meta' );
			$this->add_text( $dom, $m, 'key', $data['key'] );
			$this->add_text( $dom, $m, 'value', (string) $data['value'] );
			$meta_el->appendChild( $m );
		}
	}

	private function add_text( DOMDocument $dom, DOMElement $parent, string $tag, $value ): void {
		$node = $dom->createElement( $tag );
		$node->appendChild( $dom->createTextNode( (string) ( $value ?? '' ) ) );
		$parent->appendChild( $node );
	}
}
