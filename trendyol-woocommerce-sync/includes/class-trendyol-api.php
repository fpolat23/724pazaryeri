<?php
defined( 'ABSPATH' ) || exit;

class TWS_Trendyol_API {

    const BASE_URL = 'https://api.trendyol.com/sapigw';

    private string $api_key;
    private string $api_secret;
    private string $supplier_id;

    public function __construct( string $api_key, string $api_secret, string $supplier_id ) {
        $this->api_key     = $api_key;
        $this->api_secret  = $api_secret;
        $this->supplier_id = $supplier_id;
    }

    // ---------------------------------------------------------------
    // Import (Trendyol → WooCommerce)
    // ---------------------------------------------------------------

    public function get_all_products( int $page_size = 50 ): array|\WP_Error {
        $all_products = [];
        $page         = 0;

        do {
            $result = $this->get_products( $page, $page_size );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $items        = $result['content'] ?? [];
            $all_products = array_merge( $all_products, $items );
            $total_pages  = $result['totalPages'] ?? 1;
            $page++;
        } while ( $page < $total_pages );

        return $all_products;
    }

    public function get_products( int $page = 0, int $size = 50, bool $approved = true ): array|\WP_Error {
        return $this->request( sprintf(
            '/suppliers/%s/products?approved=%s&page=%d&size=%d',
            $this->supplier_id,
            $approved ? 'true' : 'false',
            $page,
            $size
        ) );
    }

    public function get_product_by_barcode( string $barcode ): array|\WP_Error {
        $result = $this->request( sprintf(
            '/suppliers/%s/products?barcode=%s',
            $this->supplier_id,
            urlencode( $barcode )
        ) );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $result['content'][0] ?? [];
    }

    public function update_price_and_stock( array $items ): array|\WP_Error {
        return $this->request( "/suppliers/{$this->supplier_id}/products/price-and-inventory", 'POST', [ 'items' => $items ] );
    }

    // ---------------------------------------------------------------
    // Export (WooCommerce → Trendyol)
    // ---------------------------------------------------------------

    /**
     * Trendyol kategorilerini arar.
     * Dönen format: [ ['id'=>int, 'name'=>string, 'parentId'=>int|null], ... ]
     */
    public function search_categories( string $name = '' ): array|\WP_Error {
        $endpoint = '/product-categories';
        if ( $name ) {
            $endpoint .= '?name=' . urlencode( $name );
        }
        $result = $this->request( $endpoint );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return $result['categories'] ?? [];
    }

    /**
     * Belirli bir kategorinin özelliklerini (attributes) getirir.
     */
    public function get_category_attributes( int $category_id ): array|\WP_Error {
        $result = $this->request( "/product-categories/{$category_id}/attributes" );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return $result['categoryAttributes'] ?? [];
    }

    /**
     * Marka arar.
     * Dönen format: [ ['id'=>int, 'name'=>string], ... ]
     */
    public function search_brands( string $name, int $page = 0, int $size = 10 ): array|\WP_Error {
        $result = $this->request( '/brands?' . http_build_query( [ 'name' => $name, 'page' => $page, 'size' => $size ] ) );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return $result['brands'] ?? $result;
    }

    /**
     * Tedarikçinin kargo şirketlerini getirir.
     */
    public function get_cargo_companies(): array|\WP_Error {
        $result = $this->request( "/suppliers/{$this->supplier_id}/cargo-companies" );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return is_array( $result ) ? $result : [];
    }

    /**
     * WooCommerce ürünlerini Trendyol'da oluşturur (yeni).
     * Döner: ['batchRequestId' => string]
     */
    public function create_products( array $items ): array|\WP_Error {
        return $this->request( "/suppliers/{$this->supplier_id}/v2/products", 'POST', [ 'items' => $items ] );
    }

    /**
     * Trendyol'daki mevcut ürünleri günceller.
     */
    public function update_products( array $items ): array|\WP_Error {
        return $this->request( "/suppliers/{$this->supplier_id}/v2/products", 'PUT', [ 'items' => $items ] );
    }

    /**
     * Toplu işlem isteğinin durumunu sorgular.
     */
    public function get_batch_status( string $batch_id ): array|\WP_Error {
        return $this->request( "/suppliers/{$this->supplier_id}/products/batch-requests/{$batch_id}" );
    }

    // ---------------------------------------------------------------
    // Ortak
    // ---------------------------------------------------------------

    public function test_connection(): bool|\WP_Error {
        $result = $this->get_products( 0, 1 );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return true;
    }

    private function request( string $endpoint, string $method = 'GET', array $body = [] ): array|\WP_Error {
        $url  = self::BASE_URL . $endpoint;
        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':' . $this->api_secret ),
                'Content-Type'  => 'application/json',
                'User-Agent'    => $this->supplier_id . ' - SelfIntegration',
            ],
        ];

        if ( ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );

        if ( $code < 200 || $code >= 300 ) {
            $message = $data['errors'][0]['message']
                ?? $data['error_description']
                ?? $data['message']
                ?? $data['title']
                ?? ( $raw ? substr( strip_tags( $raw ), 0, 200 ) : "HTTP $code" );
            return new \WP_Error( 'trendyol_api_error', "HTTP $code: $message", [ 'status' => $code, 'body' => $raw ] );
        }

        return $data ?? [];
    }
}
