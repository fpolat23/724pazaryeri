<?php
defined( 'ABSPATH' ) || exit;

class WC_Cat_Migrator_Exporter {

	private bool $include_images;

	public function __construct( bool $include_images = true ) {
		$this->include_images = $include_images;
	}

	/**
	 * Tüm kategorileri hiyerarşiyi koruyarak XML string olarak döner.
	 */
	public function export(): string {
		$terms = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'orderby'    => 'term_id',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) ) {
			return $this->empty_xml( $terms->get_error_message() );
		}

		// BFS ile üst → alt sırala (import sırasında parent her zaman önce gelsin)
		$sorted = $this->sort_hierarchy( (array) $terms );

		$dom = new DOMDocument( '1.0', 'UTF-8' );
		$dom->formatOutput = true;

		$root = $dom->createElement( 'wc_categories' );
		$root->setAttribute( 'version', WC_CAT_MIGRATOR_VERSION );
		$root->setAttribute( 'exported_at', $this->local_time() );
		$root->setAttribute( 'site_url', get_site_url() );
		$root->setAttribute( 'total', (string) count( $sorted ) );
		$dom->appendChild( $root );

		foreach ( $sorted as $term ) {
			$this->append_term( $dom, $root, $term );
		}

		return $dom->saveXML();
	}

	/**
	 * Genişlik-öncelikli arama (BFS) ile üst kategoriler daima alt kategorilerden önce gelir.
	 */
	private function sort_hierarchy( array $terms ): array {
		// parent_id => [term, term, ...] gruplama
		$by_parent = [];
		foreach ( $terms as $term ) {
			$by_parent[ (int) $term->parent ][] = $term;
		}

		$sorted = [];
		$queue  = $by_parent[0] ?? [];

		while ( ! empty( $queue ) ) {
			/** @var WP_Term $term */
			$term     = array_shift( $queue );
			$sorted[] = $term;
			$children = $by_parent[ $term->term_id ] ?? [];
			foreach ( $children as $child ) {
				$queue[] = $child;
			}
		}

		// parent'ı olmayan yetim term'ler (bütünlük için ekle)
		$sorted_ids = array_column( $sorted, 'term_id' );
		foreach ( $terms as $term ) {
			if ( ! in_array( $term->term_id, $sorted_ids, true ) ) {
				$sorted[] = $term;
			}
		}

		return $sorted;
	}

	private function append_term( DOMDocument $dom, DOMElement $root, WP_Term $term ): void {
		$el = $dom->createElement( 'category' );
		$root->appendChild( $el );

		$this->add_text( $dom, $el, 'slug', $term->slug );
		$this->add_text( $dom, $el, 'name', $term->name );
		$this->add_text( $dom, $el, 'description', $term->description );
		$this->add_text( $dom, $el, 'display', get_term_meta( $term->term_id, 'display_type', true ) ?: 'default' );

		// Üst kategori: ID değil slug sakla (hedef sitede ID farklı olabilir)
		$parent_slug = '';
		if ( $term->parent ) {
			$parent = get_term( $term->parent, 'product_cat' );
			if ( $parent && ! is_wp_error( $parent ) ) {
				$parent_slug = $parent->slug;
			}
		}
		$this->add_text( $dom, $el, 'parent_slug', $parent_slug );

		if ( $this->include_images ) {
			$this->append_image( $dom, $el, $term );
		}
	}

	private function append_image( DOMDocument $dom, DOMElement $el, WP_Term $term ): void {
		$thumb_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		if ( ! $thumb_id ) return;

		$url = wp_get_attachment_url( $thumb_id );
		if ( ! $url ) return;

		$img = $dom->createElement( 'image' );
		$this->add_text( $dom, $img, 'url', $url );
		$this->add_text( $dom, $img, 'alt', get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) );
		$el->appendChild( $img );
	}

	private function add_text( DOMDocument $dom, DOMElement $parent, string $tag, $value ): void {
		$node = $dom->createElement( $tag );
		$node->appendChild( $dom->createTextNode( (string) ( $value ?? '' ) ) );
		$parent->appendChild( $node );
	}

	private function local_time(): string {
		try {
			$tz = new DateTimeZone( wp_timezone_string() );
			$dt = new DateTime( 'now', $tz );
			return $dt->format( 'd.m.Y H:i:s' ) . ' (' . $tz->getName() . ')';
		} catch ( Exception $e ) {
			return current_time( 'c' );
		}
	}

	private function empty_xml( string $error = '' ): string {
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
		       '<wc_categories total="0" error="' . esc_attr( $error ) . '"/>';
	}
}
