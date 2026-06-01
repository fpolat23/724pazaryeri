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

    /**
     * Trendyol'dan tüm onaylanmış ürünleri sayfalayarak çeker.
     *
     * @return array|\WP_Error
     */
    public function get_all_products( int $page_size = 50 ): array|\WP_Error {
        $all_products = [];
        $page         = 0;

        do {
            $result = $this->get_products( $page, $page_size );
            if ( is_wp_error( $result ) ) {
                return $result;
            }

            $items         = $result['content'] ?? [];
            $all_products  = array_merge( $all_products, $items );
            $total_pages   = $result['totalPages'] ?? 1;
            $page++;
        } while ( $page < $total_pages );

        return $all_products;
    }

    /**
     * Belirli bir sayfadaki ürünleri çeker.
     *
     * @return array|\WP_Error
     */
    public function get_products( int $page = 0, int $size = 50, bool $approved = true ): array|\WP_Error {
        $endpoint = sprintf(
            '/suppliers/%s/products?approved=%s&page=%d&size=%d',
            $this->supplier_id,
            $approved ? 'true' : 'false',
            $page,
            $size
        );

        return $this->request( $endpoint );
    }

    /**
     * Belirli bir ürünü barkod ile çeker.
     *
     * @return array|\WP_Error
     */
    public function get_product_by_barcode( string $barcode ): array|\WP_Error {
        $endpoint = sprintf(
            '/suppliers/%s/products?barcode=%s',
            $this->supplier_id,
            urlencode( $barcode )
        );

        $result = $this->request( $endpoint );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return $result['content'][0] ?? [];
    }

    /**
     * Stok ve fiyat güncellemesi yapar.
     *
     * @return array|\WP_Error
     */
    public function update_price_and_stock( array $items ): array|\WP_Error {
        $endpoint = sprintf( '/suppliers/%s/products/price-and-inventory', $this->supplier_id );
        return $this->request( $endpoint, 'POST', [ 'items' => $items ] );
    }

    private function request( string $endpoint, string $method = 'GET', array $body = [] ): array|\WP_Error {
        $url  = self::BASE_URL . $endpoint;
        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode( $this->api_key . ':' . $this->api_secret ),
                'Content-Type'  => 'application/json',
                'User-Agent'    => sprintf(
                    '%s - SelfIntegration',
                    get_option( 'tws_supplier_id', '' )
                ),
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
            $message = $data['errors'][0]['message'] ?? $data['message'] ?? "HTTP $code";
            return new \WP_Error( 'trendyol_api_error', $message, [ 'status' => $code ] );
        }

        return $data ?? [];
    }

    public function test_connection(): bool|\WP_Error {
        $result = $this->get_products( 0, 1 );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return true;
    }
}
