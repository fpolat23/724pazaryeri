<?php
defined( 'ABSPATH' ) || exit;

class WC_Cat_Migrator_Importer {

	private bool   $download_images;
	private bool   $update_existing;
	private array  $slug_to_id = []; // slug → term_id haritası
	private array  $results = [
		'created' => 0,
		'updated' => 0,
		'skipped' => 0,
		'images'  => 0,
		'errors'  => [],
	];

	public function __construct( array $options = [] ) {
		$this->download_images = (bool) ( $options['download_images'] ?? true );
		$this->update_existing = (bool) ( $options['update_existing'] ?? true );
	}

	public function import( string $xml_content ): array {
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadXML( $xml_content );
		$parse_errors = libxml_get_errors();
		libxml_clear_errors();

		if ( ! empty( $parse_errors ) ) {
			$this->results['errors'][] = 'XML ayrıştırma hatası: ' . trim( $parse_errors[0]->message );
			return $this->results;
		}

		// Mevcut kategorilerin slug→ID haritasını hazırla
		$this->build_slug_map();

		// XML parent-first sıralı olduğu için tek geçişte işle
		$nodes = $dom->getElementsByTagName( 'category' );
		foreach ( $nodes as $node ) {
			$this->process_node( $node );
		}

		return $this->results;
	}

	private function build_slug_map(): void {
		$existing = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'fields'     => 'all',
		] );
		if ( is_wp_error( $existing ) ) return;
		foreach ( $existing as $term ) {
			$this->slug_to_id[ $term->slug ] = $term->term_id;
		}
	}

	private function process_node( DOMElement $node ): void {
		$slug        = $this->get_text( $node, 'slug' );
		$name        = $this->get_text( $node, 'name' );
		$description = $this->get_text( $node, 'description' );
		$parent_slug = $this->get_text( $node, 'parent_slug' );
		$display     = $this->get_text( $node, 'display' );

		if ( ! $slug || ! $name ) {
			$this->results['skipped']++;
			return;
		}

		// Parent'ı slug → ID ile çöz
		$parent_id = 0;
		if ( $parent_slug !== '' ) {
			if ( isset( $this->slug_to_id[ $parent_slug ] ) ) {
				$parent_id = $this->slug_to_id[ $parent_slug ];
			} else {
				// Parent henüz oluşturulmamışsa hata kaydet ama devam et
				$this->results['errors'][] = "Üst kategori bulunamadı: \"$parent_slug\" (slug: $slug)";
			}
		}

		// Kategoriyi oluştur veya güncelle
		$term_id = $this->upsert_term( $slug, $name, $description, $parent_id );

		if ( ! $term_id ) return;

		// display_type meta
		if ( $display && $display !== 'default' ) {
			update_term_meta( $term_id, 'display_type', $display );
		}

		// Görsel
		if ( $this->download_images ) {
			$img_url = $this->extract_image_url( $node );
			if ( $img_url ) {
				$att_id = $this->sideload_image( $img_url );
				if ( $att_id ) {
					update_term_meta( $term_id, 'thumbnail_id', $att_id );
					$this->results['images']++;
				}
			}
		}
	}

	private function upsert_term( string $slug, string $name, string $description, int $parent_id ): int {
		if ( isset( $this->slug_to_id[ $slug ] ) ) {
			$term_id = $this->slug_to_id[ $slug ];

			if ( ! $this->update_existing ) {
				$this->results['skipped']++;
				return $term_id; // Görseli yine de güncellemek için ID döndür
			}

			$result = wp_update_term( $term_id, 'product_cat', [
				'name'        => $name,
				'description' => $description,
				'parent'      => $parent_id,
			] );

			if ( is_wp_error( $result ) ) {
				$this->results['errors'][] = "Güncelleme hatası ($slug): " . $result->get_error_message();
				return 0;
			}

			$this->results['updated']++;
			return $term_id;
		}

		// Yeni kategori
		$result = wp_insert_term( $name, 'product_cat', [
			'slug'        => $slug,
			'description' => $description,
			'parent'      => $parent_id,
		] );

		if ( is_wp_error( $result ) ) {
			// Slug çakışması — WordPress bazen "-2" ekler, onu dene
			if ( $result->get_error_code() === 'term_exists' ) {
				$term_id = (int) $result->get_error_data();
				$this->slug_to_id[ $slug ] = $term_id;
				$this->results['updated']++;
				return $term_id;
			}
			$this->results['errors'][] = "Oluşturma hatası ($slug): " . $result->get_error_message();
			return 0;
		}

		$term_id = (int) $result['term_id'];
		$this->slug_to_id[ $slug ] = $term_id; // Sonraki child'lar için haritaya ekle
		$this->results['created']++;
		return $term_id;
	}

	private function extract_image_url( DOMElement $node ): string {
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->tagName === 'image' ) {
				return $this->get_text( $child, 'url' );
			}
		}
		return '';
	}

	private function sideload_image( string $url ): int {
		global $wpdb;

		// Aynı URL daha önce indirildi mi?
		$existing = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_wc_cat_source_url' AND meta_value = %s LIMIT 1",
			$url
		) );
		if ( $existing ) return $existing;

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			$this->results['errors'][] = 'Görsel indirilemedi: ' . $url;
			return 0;
		}

		$filename   = basename( parse_url( $url, PHP_URL_PATH ) ) ?: 'category-image.jpg';
		$file_array = [ 'name' => $filename, 'tmp_name' => $tmp ];

		$att_id = media_handle_sideload( $file_array, 0 );
		if ( is_wp_error( $att_id ) ) {
			@unlink( $tmp );
			$this->results['errors'][] = 'Görsel medyaya eklenemedi: ' . $url;
			return 0;
		}

		update_post_meta( $att_id, '_wc_cat_source_url', $url );
		return $att_id;
	}

	private function get_text( DOMElement $parent, string $tag ): string {
		foreach ( $parent->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->tagName === $tag ) {
				return trim( $child->nodeValue );
			}
		}
		return '';
	}
}
