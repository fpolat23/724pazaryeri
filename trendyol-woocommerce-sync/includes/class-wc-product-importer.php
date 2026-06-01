<?php
defined( 'ABSPATH' ) || exit;

class TWS_WC_Product_Importer {

    /**
     * Trendyol ürününü WooCommerce'e aktarır veya günceller.
     *
     * @return int|\WP_Error WC ürün ID
     */
    public function import( array $trendyol_product ): int|\WP_Error {
        $barcode = $trendyol_product['barcode'] ?? '';
        $sku     = $trendyol_product['stockCode'] ?? $barcode;

        // Mevcut ürünü bul
        $existing_id = $this->find_by_meta( '_trendyol_product_id', $trendyol_product['id'] ?? '' );
        if ( ! $existing_id ) {
            $existing_id = wc_get_product_id_by_sku( $sku );
        }

        $has_variants = ! empty( $trendyol_product['variants'] ) && count( $trendyol_product['variants'] ) > 1;

        if ( $has_variants ) {
            return $this->import_variable( $trendyol_product, $existing_id );
        }

        return $this->import_simple( $trendyol_product, $existing_id );
    }

    private function import_simple( array $data, int $existing_id = 0 ): int|\WP_Error {
        $product = $existing_id ? wc_get_product( $existing_id ) : new \WC_Product_Simple();
        if ( ! $product ) {
            $product = new \WC_Product_Simple();
        }

        $this->set_common_fields( $product, $data );

        // Stok
        $qty = $data['quantity'] ?? ( $data['stockInfo']['quantity'] ?? 0 );
        $product->set_manage_stock( true );
        $product->set_stock_quantity( (int) $qty );
        $product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );

        $product_id = $product->save();

        $this->attach_images( $product_id, $data['images'] ?? [] );
        $this->save_trendyol_meta( $product_id, $data );

        return $product_id;
    }

    private function import_variable( array $data, int $existing_id = 0 ): int|\WP_Error {
        $product = $existing_id ? wc_get_product( $existing_id ) : new \WC_Product_Variable();
        if ( ! $product || ! ( $product instanceof \WC_Product_Variable ) ) {
            $product = new \WC_Product_Variable();
        }

        $this->set_common_fields( $product, $data );

        // Varyant özelliklerini topla (renk, beden vb.)
        $attribute_map = [];
        foreach ( $data['variants'] as $variant ) {
            foreach ( $variant['attributes'] ?? [] as $attr ) {
                $key = sanitize_title( $attr['attributeName'] );
                $attribute_map[ $key ]['name']     = $attr['attributeName'];
                $attribute_map[ $key ]['values'][] = $attr['attributeValue'];
            }
        }

        $wc_attributes = [];
        foreach ( $attribute_map as $slug => $attr_data ) {
            $attribute = new \WC_Product_Attribute();
            $attribute->set_name( $attr_data['name'] );
            $attribute->set_options( array_unique( $attr_data['values'] ) );
            $attribute->set_visible( true );
            $attribute->set_variation( true );
            $wc_attributes[] = $attribute;
        }
        $product->set_attributes( $wc_attributes );

        $product_id = $product->save();

        // Mevcut varyasyonları sil, yeniden oluştur
        $existing_variations = $product->get_children();
        foreach ( $existing_variations as $var_id ) {
            wp_delete_post( $var_id, true );
        }

        foreach ( $data['variants'] as $variant ) {
            $this->create_variation( $product_id, $variant, $attribute_map );
        }

        $product->sync_managed_variation_stock_status();
        $this->attach_images( $product_id, $data['images'] ?? [] );
        $this->save_trendyol_meta( $product_id, $data );

        return $product_id;
    }

    private function create_variation( int $parent_id, array $variant, array $attribute_map ): void {
        $variation = new \WC_Product_Variation();
        $variation->set_parent_id( $parent_id );

        $barcode = $variant['barcode'] ?? '';
        $sku     = $variant['stockCode'] ?? $barcode;
        $variation->set_sku( $sku );

        $sale_price = $variant['salePrice'] ?? 0;
        $list_price = $variant['listPrice'] ?? $sale_price;
        $variation->set_regular_price( (string) $list_price );
        if ( $sale_price < $list_price ) {
            $variation->set_sale_price( (string) $sale_price );
        }

        $qty = $variant['quantity'] ?? 0;
        $variation->set_manage_stock( true );
        $variation->set_stock_quantity( (int) $qty );
        $variation->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );

        $variation_attrs = [];
        foreach ( $variant['attributes'] ?? [] as $attr ) {
            $key                  = 'attribute_' . sanitize_title( $attr['attributeName'] );
            $variation_attrs[ $key ] = $attr['attributeValue'];
        }
        $variation->set_attributes( $variation_attrs );

        $var_id = $variation->save();

        // Varyant görselini bağla
        if ( ! empty( $variant['images'][0]['url'] ) ) {
            $image_id = $this->sideload_image( $variant['images'][0]['url'], $var_id );
            if ( $image_id ) {
                update_post_meta( $var_id, '_thumbnail_id', $image_id );
            }
        }

        update_post_meta( $var_id, '_trendyol_barcode', $barcode );
    }

    private function set_common_fields( \WC_Product $product, array $data ): void {
        $product->set_name( wp_strip_all_tags( $data['title'] ?? '' ) );

        $description = $data['description'] ?? '';
        $product->set_description( wp_kses_post( $description ) );

        $sale_price = $data['salePrice'] ?? 0;
        $list_price = $data['listPrice'] ?? $sale_price;
        $product->set_regular_price( (string) $list_price );
        if ( $sale_price > 0 && $sale_price < $list_price ) {
            $product->set_sale_price( (string) $sale_price );
        }

        $sku = $data['stockCode'] ?? ( $data['barcode'] ?? '' );
        if ( $sku ) {
            $product->set_sku( $sku );
        }

        // Kategori
        $category_name = $data['categoryName'] ?? '';
        $cat_id = TWS_Category_Mapper::get_or_create( $category_name );
        if ( $cat_id ) {
            $product->set_category_ids( [ $cat_id ] );
        }

        // Marka attribute
        $brand = $data['brand'] ?? '';
        if ( $brand ) {
            $this->set_brand_attribute( $product, $brand );
        }

        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'visible' );
    }

    private function set_brand_attribute( \WC_Product $product, string $brand ): void {
        $existing_attrs = $product->get_attributes();

        $attribute = new \WC_Product_Attribute();
        $attribute->set_name( 'pa_marka' );
        $attribute->set_options( [ $brand ] );
        $attribute->set_visible( true );
        $attribute->set_variation( false );

        $existing_attrs['pa_marka'] = $attribute;
        $product->set_attributes( $existing_attrs );
    }

    /**
     * Trendyol görsellerini WordPress medya kütüphanesine aktarır.
     */
    private function attach_images( int $product_id, array $images ): void {
        if ( empty( $images ) ) {
            return;
        }

        $gallery_ids   = [];
        $featured_set  = (bool) get_post_meta( $product_id, '_thumbnail_id', true );

        foreach ( $images as $index => $image ) {
            $url = $image['url'] ?? '';
            if ( ! $url ) {
                continue;
            }

            $existing = $this->find_image_by_url( $url );
            $image_id = $existing ?: $this->sideload_image( $url, $product_id );

            if ( ! $image_id ) {
                continue;
            }

            if ( $index === 0 && ! $featured_set ) {
                set_post_thumbnail( $product_id, $image_id );
            } else {
                $gallery_ids[] = $image_id;
            }
        }

        if ( ! empty( $gallery_ids ) ) {
            update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
        }
    }

    private function sideload_image( string $url, int $post_id ): int {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $image_id = media_sideload_image( $url, $post_id, '', 'id' );

        return is_wp_error( $image_id ) ? 0 : (int) $image_id;
    }

    private function find_image_by_url( string $url ): int {
        global $wpdb;
        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_trendyol_image_url' AND meta_value = %s LIMIT 1",
                $url
            )
        );
        return $id ? (int) $id : 0;
    }

    private function save_trendyol_meta( int $product_id, array $data ): void {
        update_post_meta( $product_id, '_trendyol_product_id', $data['id'] ?? '' );
        update_post_meta( $product_id, '_trendyol_barcode', $data['barcode'] ?? '' );
        update_post_meta( $product_id, '_trendyol_last_sync', current_time( 'mysql' ) );
    }

    private function find_by_meta( string $key, string $value ): int {
        if ( empty( $value ) ) {
            return 0;
        }

        global $wpdb;
        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
                $key,
                $value
            )
        );

        return $id ? (int) $id : 0;
    }
}
