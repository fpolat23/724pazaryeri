<?php
defined( 'ABSPATH' ) || exit;

class TWS_Trendyol_Exporter {

    private TWS_Trendyol_API $api;

    public function __construct( TWS_Trendyol_API $api ) {
        $this->api = $api;
    }

    /**
     * WC ürününü Trendyol'a aktarır.
     *
     * $config anahtarları:
     *   category_id       int      Trendyol kategori ID
     *   brand_id          int      Trendyol marka ID
     *   cargo_company_id  int      Kargo şirketi ID
     *   dimensional_weight float   Desi (varsayılan 1)
     *   attributes        array    [ ['attributeId'=>int, 'attributeValueId'=>int|null, 'customAttributeValue'=>string|null], ... ]
     *
     * @return array|\WP_Error  ['batchRequestId'=>string]
     */
    public function export( int $product_id, array $config ): array|\WP_Error {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new \WP_Error( 'not_found', 'Ürün bulunamadı.' );
        }

        $config['category_id']      = (int) ( $config['category_id'] ?? 0 );
        $config['brand_id']         = (int) ( $config['brand_id'] ?? 0 );
        $config['cargo_company_id'] = (int) ( $config['cargo_company_id'] ?? 0 );

        if ( ! $config['category_id'] || ! $config['brand_id'] || ! $config['cargo_company_id'] ) {
            return new \WP_Error( 'missing_config', 'Kategori, marka ve kargo şirketi zorunludur.' );
        }

        update_post_meta( $product_id, '_trendyol_export_config', $config );

        $items = [];

        if ( $product->is_type( 'variable' ) ) {
            foreach ( $product->get_children() as $var_id ) {
                $variation = wc_get_product( $var_id );
                if ( ! $variation ) {
                    continue;
                }
                $item = $this->build_item( $variation, $product, $config );
                if ( is_wp_error( $item ) ) {
                    return $item;
                }
                $items[] = $item;
            }
        } else {
            $item = $this->build_item( $product, null, $config );
            if ( is_wp_error( $item ) ) {
                return $item;
            }
            $items[] = $item;
        }

        if ( empty( $items ) ) {
            return new \WP_Error( 'no_items', 'Aktarılacak ürün öğesi oluşturulamadı.' );
        }

        $already_exported = (bool) get_post_meta( $product_id, '_trendyol_export_product_id', true );
        $result = $already_exported
            ? $this->api->update_products( $items )
            : $this->api->create_products( $items );

        if ( is_wp_error( $result ) ) {
            update_post_meta( $product_id, '_trendyol_export_status', 'error' );
            return $result;
        }

        $batch_id = $result['batchRequestId'] ?? '';
        update_post_meta( $product_id, '_trendyol_batch_id', $batch_id );
        update_post_meta( $product_id, '_trendyol_export_status', 'pending' );
        update_post_meta( $product_id, '_trendyol_export_date', current_time( 'mysql' ) );

        return [ 'batchRequestId' => $batch_id ];
    }

    /**
     * Toplu işlem durumunu sorgular ve ürün meta'sını günceller.
     *
     * @return array  ['status'=>'pending|success|error', 'trendyolStatus'=>string, 'failedReasons'=>array]
     */
    public function check_status( int $product_id ): array|\WP_Error {
        $batch_id = get_post_meta( $product_id, '_trendyol_batch_id', true );
        if ( ! $batch_id ) {
            return new \WP_Error( 'no_batch', 'Bu ürün için aktarım kaydı bulunamadı.' );
        }

        $result = $this->api->get_batch_status( $batch_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $status = strtoupper( $result['status'] ?? 'PROCESSING' );

        $local_status = match ( $status ) {
            'COMPLETED' => 'success',
            'FAILED'    => 'error',
            default     => 'pending',
        };

        update_post_meta( $product_id, '_trendyol_export_status', $local_status );

        if ( $local_status === 'success' ) {
            // Ürünün barkodunu export_product_id olarak kaydet (güncelleme için)
            $sku = get_post_meta( $product_id, '_sku', true );
            if ( $sku ) {
                update_post_meta( $product_id, '_trendyol_export_product_id', $sku );
            }
        }

        return [
            'status'         => $local_status,
            'trendyolStatus' => $status,
            'failedReasons'  => $result['failedReasons'] ?? $result['items'] ?? [],
        ];
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function build_item( \WC_Product $product, ?\WC_Product $parent, array $config ): array|\WP_Error {
        $sku = $product->get_sku();
        if ( ! $sku ) {
            $name = $parent ? $parent->get_name() : $product->get_name();
            return new \WP_Error( 'no_sku', sprintf( '"%s" ürününün SKU\'su yok.', $name ) );
        }

        $name        = $parent ? $parent->get_name() : $product->get_name();
        $description = $parent ? $parent->get_description() : $product->get_description();
        if ( ! $description ) {
            $description = $name;
        }

        $list_price = (float) $product->get_regular_price();
        $sale_price = (float) ( $product->get_sale_price() ?: $list_price );

        if ( $list_price <= 0 ) {
            return new \WP_Error( 'no_price', sprintf( '"%s" ürününün fiyatı belirtilmemiş.', $name ) );
        }

        $images = $this->collect_images( $product, $parent );
        if ( empty( $images ) ) {
            return new \WP_Error( 'no_images', sprintf( '"%s" ürününe ait görsel bulunamadı.', $name ) );
        }

        $qty      = (int) ( $product->get_stock_quantity() ?? 0 );
        $main_sku = $parent ? ( $parent->get_sku() ?: $sku ) : $sku;

        $item = [
            'barcode'           => $sku,
            'title'             => mb_substr( wp_strip_all_tags( $name ), 0, 100 ),
            'productMainId'     => $main_sku,
            'brandId'           => $config['brand_id'],
            'categoryId'        => $config['category_id'],
            'quantity'          => $qty,
            'stockCode'         => $sku,
            'dimensionalWeight' => (float) ( $config['dimensional_weight'] ?? 1 ),
            'description'       => mb_substr( wp_strip_all_tags( $description ), 0, 500 ),
            'currencyType'      => 'TRY',
            'listPrice'         => $list_price,
            'salePrice'         => $sale_price,
            'cargoCompanyId'    => $config['cargo_company_id'],
            'images'            => $images,
            'attributes'        => $config['attributes'] ?? [],
        ];

        // Varyasyona özgü özellikler
        if ( $product->is_type( 'variation' ) ) {
            foreach ( $product->get_variation_attributes() as $attr_slug => $attr_value ) {
                $map_key = 'attr_map_' . sanitize_key( $attr_slug );
                $trendyol_attr_id = $config[ $map_key ] ?? null;
                if ( $trendyol_attr_id ) {
                    $item['attributes'][] = [
                        'attributeId'        => (int) $trendyol_attr_id,
                        'customAttributeValue' => $attr_value,
                    ];
                }
            }
        }

        return $item;
    }

    private function collect_images( \WC_Product $product, ?\WC_Product $parent ): array {
        $images  = [];
        $seen    = [];
        $sources = $parent ? [ $parent, $product ] : [ $product ];

        foreach ( $sources as $src ) {
            $ids = array_filter( array_merge(
                [ $src->get_image_id() ],
                $src->get_gallery_image_ids()
            ) );

            foreach ( $ids as $id ) {
                if ( in_array( $id, $seen, true ) ) {
                    continue;
                }
                $url = wp_get_attachment_url( $id );
                if ( $url ) {
                    $images[] = [ 'url' => $url ];
                    $seen[]   = $id;
                }
            }
        }

        return $images;
    }
}
