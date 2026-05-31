<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PZV_Dashboard {
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    public function enqueue() {
        if ( ! is_page() ) return;
        global $post;
        if ( ! $post ) return;
        // Birden fazla şekilde kontrol: shortcode + sayfa slug
        $has_shortcode = ( strpos( $post->post_content, '[pazaryeri_vendor_dashboard]' ) !== false );
        $is_saticim_page = ( $post->post_name === 'saticim' );
        if ( ! $has_shortcode && ! $is_saticim_page ) return;
        wp_enqueue_style( 'pzv-dash', PZV_URL . 'assets/dashboard.css', array(), PZV_VERSION );
        wp_enqueue_script( 'pzv-dash', PZV_URL . 'assets/dashboard.js', array(), PZV_VERSION, true );
        wp_localize_script( 'pzv-dash', 'pzv', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pzv_nonce' ),
        ) );
    }

    public static function render_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<div class="pzv-dash-login"><h3>🔒 Giriş Gerekli</h3><p>Satıcı paneline erişmek için <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">giriş yapın</a>.</p></div>';
        }
        $user_id = get_current_user_id();
        if ( ! PZV_Roles::is_vendor( $user_id ) ) {
            return '<div class="pzv-dash-login"><h3>⚠ Satıcı Değilsiniz</h3><p><a href="' . esc_url( home_url( '/satici-ol/' ) ) . '" class="button">Satıcı Başvurusu Yap →</a></p></div>';
        }
        $tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'overview';
        ob_start();
        ?>
        <div class="pzv-dash">
            <?php self::render_header( $user_id ); ?>
            <?php self::render_tabs( $tab ); ?>
            <div class="pzv-dash-content">
                <?php
                switch ( $tab ) {
                    case 'products': self::tab_products( $user_id ); break;
                    case 'add-product': self::tab_add_product(); break;
                    case 'orders':   self::tab_orders( $user_id ); break;
                    case 'earnings': self::tab_earnings( $user_id ); break;
                    case 'profile':  self::tab_profile(); break;
                    default:         self::tab_overview( $user_id );
                }
                ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_header( $user_id ) {
        $v = PZV_Vendor::get( $user_id );
        $pending = PZV_Commission::vendor_pending( $user_id );
        ?>
        <div class="pzv-dash-header">
            <div>
                <div class="pzv-dash-store-name">🏪 <?php echo esc_html( $v['store_name'] ); ?></div>
                <div class="pzv-dash-store-meta">
                    <a href="<?php echo esc_url( PZV_Vendor::store_url( $user_id ) ); ?>" target="_blank">Mağazaya git ↗</a>
                </div>
            </div>
            <div class="pzv-dash-balance">
                <div class="pzv-dash-balance-l">Bekleyen Kazancım</div>
                <div class="pzv-dash-balance-n"><?php echo wc_price( $pending ); ?></div>
            </div>
        </div>
        <?php
    }

    private static function render_tabs( $current ) {
        $tabs = array(
            'overview'    => array( 'icon' => '📊', 'label' => 'Özet' ),
            'products'    => array( 'icon' => '📦', 'label' => 'Ürünlerim' ),
            'add-product' => array( 'icon' => '➕', 'label' => 'Ürün Ekle' ),
            'orders'      => array( 'icon' => '📋', 'label' => 'Siparişlerim' ),
            'earnings'    => array( 'icon' => '💰', 'label' => 'Kazançlarım' ),
            'profile'     => array( 'icon' => '⚙️', 'label' => 'Profilim' ),
        );
        $base = get_permalink();
        ?>
        <nav class="pzv-dash-tabs">
            <?php foreach ( $tabs as $k => $t ) :
                $url    = add_query_arg( 'tab', $k, $base );
                $active = ( $current === $k ) ? ' pzv-active' : '';
                ?>
                <a href="<?php echo esc_url( $url ); ?>" class="pzv-tab<?php echo $active; ?>">
                    <span class="pzv-tab-icon"><?php echo $t['icon']; ?></span>
                    <span class="pzv-tab-label"><?php echo esc_html( $t['label'] ); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }

    private static function tab_overview( $user_id ) {
        $sales = PZV_Commission::vendor_total_sales( $user_id );
        $pending = PZV_Commission::vendor_pending( $user_id );
        $paid = PZV_Commission::vendor_paid( $user_id );
        $product_count = count( PZV_Vendor::get_product_ids( $user_id ) );
        $order_count = count( PZV_Vendor::get_order_ids( $user_id ) );
        ?>
        <div class="pzv-stats">
            <div class="pzv-stat"><div class="pzv-stat-n"><?php echo (int) $product_count; ?></div><div class="pzv-stat-l">📦 Ürünüm</div></div>
            <div class="pzv-stat"><div class="pzv-stat-n"><?php echo (int) $order_count; ?></div><div class="pzv-stat-l">📋 Sipariş</div></div>
            <div class="pzv-stat"><div class="pzv-stat-n"><?php echo wc_price( $sales ); ?></div><div class="pzv-stat-l">💵 Toplam Satış</div></div>
            <div class="pzv-stat pzv-stat-pending"><div class="pzv-stat-n"><?php echo wc_price( $pending ); ?></div><div class="pzv-stat-l">⏳ Bekleyen</div></div>
            <div class="pzv-stat pzv-stat-paid"><div class="pzv-stat-n"><?php echo wc_price( $paid ); ?></div><div class="pzv-stat-l">✓ Ödenmiş</div></div>
        </div>
        <h3>Son Siparişler</h3>
        <?php
        $order_ids = array_slice( PZV_Vendor::get_order_ids( $user_id ), 0, 10 );
        if ( empty( $order_ids ) ) {
            echo '<p>Henüz sipariş yok.</p>';
            return;
        }
        echo '<table class="pzv-table"><thead><tr><th>Sipariş</th><th>Tarih</th><th>Müşteri</th><th>Tutar</th><th>Durum</th><th></th></tr></thead><tbody>';
        foreach ( $order_ids as $oid ) {
            $o = wc_get_order( $oid ); if ( ! $o ) continue;
            echo '<tr>';
            echo '<td><strong>#' . esc_html( $o->get_order_number() ) . '</strong></td>';
            echo '<td>' . esc_html( $o->get_date_created()->date_i18n( 'd.m.Y' ) ) . '</td>';
            echo '<td>' . esc_html( $o->get_formatted_billing_full_name() ) . '</td>';
            echo '<td>' . wp_kses_post( $o->get_formatted_order_total() ) . '</td>';
            echo '<td><span class="pzv-status pzv-status-' . esc_attr( $o->get_status() ) . '">' . esc_html( wc_get_order_status_name( $o->get_status() ) ) . '</span></td>';
            echo '<td><a class="button button-small" href="' . esc_url( add_query_arg( array( 'tab' => 'orders', 'view' => $oid ), get_permalink() ) ) . '">Detay →</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    private static function tab_products( $user_id ) {
        $product_ids = PZV_Vendor::get_product_ids( $user_id );
        ?>
        <div class="pzv-section-head">
            <h3>📦 Ürünlerim (<?php echo count( $product_ids ); ?>)</h3>
            <a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>">➕ Yeni Ürün Ekle</a>
        </div>
        <?php if ( empty( $product_ids ) ) : ?>
            <p>Henüz ürününüz yok. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>">İlk ürünü ekleyin →</a></p>
        <?php else : ?>
            <table class="pzv-table">
                <thead><tr><th>Görsel</th><th>Ürün</th><th>SKU</th><th>Fiyat (₺)</th><th>Stok</th><th>Durum</th><th>İşlem</th></tr></thead>
                <tbody>
                <?php foreach ( $product_ids as $pid ) :
                    $p = wc_get_product( $pid ); if ( ! $p ) continue;
                    $cloned_from = get_post_meta( $pid, '_pzv_cloned_from', true );
                    ?>
                    <tr data-product="<?php echo (int) $pid; ?>">
                        <td><?php echo $p->get_image( array( 50, 50 ) ); ?></td>
                        <td>
                            <strong><?php echo esc_html( $p->get_name() ); ?></strong>
                            <?php if ( $cloned_from ) : ?><br><small style="color:#888;">📋 Katalog ürünü</small><?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $p->get_sku() ?: '-' ); ?></td>
                        <td>
                            <input type="number" class="pzv-quick-price" step="0.01" min="0" value="<?php echo esc_attr( $p->get_regular_price() ); ?>" style="width:90px;">
                        </td>
                        <td>
                            <input type="number" class="pzv-quick-stock" min="0" value="<?php echo esc_attr( $p->get_stock_quantity() ); ?>" style="width:70px;">
                        </td>
                        <td>
                            <select class="pzv-quick-status">
                                <option value="publish" <?php selected( $p->get_status(), 'publish' ); ?>>Yayında</option>
                                <option value="draft" <?php selected( $p->get_status(), 'draft' ); ?>>Taslak</option>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="button button-small button-primary pzv-quick-save">💾</button>
                            <a class="button button-small" href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank">👁</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

    private static function tab_add_product() {
        ?>
        <h3>➕ Mağazama Ürün Ekle</h3>
        <p style="color:#646970;margin-bottom:18px;">
            Sitedeki bir ürünü arayıp seç. Kendi <strong>stoğun</strong> ve <strong>fiyatınla</strong> mağazana ekle.
            Aynı ürünü farklı satıcılar farklı fiyatlardan satabilir.
        </p>

        <div class="pzv-add-product">
            <div class="pzv-search-card">
                <div class="pzv-search-icon">🔍</div>
                <div class="pzv-search-body">
                    <label class="pzv-search-label">Ürün Ara</label>
                    <div class="pzv-search-row">
                        <input type="text" id="pzv-product-search" placeholder="Ürün adı, SKU veya kategori (örn: Adidas Samba)" autocomplete="off">
                        <button type="button" id="pzv-search-btn" class="button button-primary">
                            <span class="pzv-btn-text">Ara</span>
                            <span class="pzv-btn-spinner" style="display:none;">⏳</span>
                        </button>
                    </div>
                    <div class="pzv-search-status">İlk 2 harften sonra otomatik arar veya butona basın.</div>
                </div>
            </div>
            <div class="pzv-search-results" id="pzv-search-results"></div>
        </div>

        <!-- Klonlama modal -->
        <div class="pzv-modal" id="pzv-clone-modal" style="display:none;">
            <div class="pzv-modal-box">
                <button type="button" class="pzv-modal-close">&times;</button>
                <h3>📦 Mağazana Ekle</h3>
                <div class="pzv-modal-product"></div>
                <div class="pzv-modal-form">
                    <div class="pzv-form-row">
                        <label>💰 Senin Satış Fiyatın (₺) <span class="req">*</span></label>
                        <input type="number" id="pzv-clone-price" step="0.01" min="0" placeholder="Örn: 1450">
                        <small>Diğer satıcıların fiyatları referans, sen istediğini gir</small>
                    </div>
                    <div class="pzv-form-row">
                        <label>📦 Stok Adedi <span class="req">*</span></label>
                        <input type="number" id="pzv-clone-stock" min="0" placeholder="Örn: 10">
                    </div>
                    <div class="pzv-form-row">
                        <label>🏷️ SKU (kendi stok kodun, opsiyonel)</label>
                        <input type="text" id="pzv-clone-sku" placeholder="Örn: GES-001">
                    </div>
                    <div class="pzv-form-row">
                        <label>📝 Yayın durumu</label>
                        <select id="pzv-clone-status">
                            <option value="publish">Hemen yayınla</option>
                            <option value="draft">Taslak olarak kaydet (sonra yayınlarım)</option>
                        </select>
                    </div>
                </div>
                <div class="pzv-modal-actions">
                    <button type="button" class="button pzv-modal-cancel">İptal</button>
                    <button type="button" class="button button-primary pzv-modal-clone">📥 Mağazama Ekle</button>
                </div>
                <div class="pzv-modal-msg"></div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Ürün ara (vendor için)
     */
    public static function ajax_search_products() {
        check_ajax_referer( 'pzv_nonce', 'nonce' );
        $uid = get_current_user_id();
        if ( ! PZV_Roles::is_vendor( $uid ) ) wp_send_json_error( array( 'message' => 'Yetki yok' ) );
        $q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
        if ( strlen( $q ) < 2 ) wp_send_json_success( array( 'results' => array() ) );

        // Vendor'ın zaten eklediği ürünlerin parent ID'leri (klonladığı orijinal)
        global $wpdb;
        $my_clones = $wpdb->get_col( $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_pzv_cloned_from'
               AND p.post_author = %d
               AND p.post_status != 'trash'",
            $uid
        ) );
        $my_clones = array_map( 'intval', $my_clones );

        // WP_Query ile ara
        $args = array(
            'post_type'      => 'product',
            's'              => $q,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'no_found_rows'  => true,
            'orderby'        => 'relevance',
        );
        $query = new WP_Query( $args );
        $results = array();
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $pid = get_the_ID();
                $product = wc_get_product( $pid );
                if ( ! $product ) continue;
                // Kendi ürünleri hariç (zaten kendi listesinde)
                if ( (int) get_post_field( 'post_author', $pid ) === $uid ) continue;
                $already_added = in_array( $pid, $my_clones, true );
                $author_id = (int) get_post_field( 'post_author', $pid );
                $author = get_userdata( $author_id );
                $store_name = $author ? ( get_user_meta( $author_id, 'pzv_store_name', true ) ?: $author->display_name ) : '?';
                $results[] = array(
                    'id'           => $pid,
                    'name'         => $product->get_name(),
                    'sku'          => $product->get_sku() ?: '-',
                    'price'        => $product->get_price(),
                    'price_html'   => wp_strip_all_tags( $product->get_price_html() ),
                    'image'        => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ),
                    'seller'       => $store_name,
                    'already_added' => $already_added,
                    'permalink'    => get_permalink( $pid ),
                );
            }
            wp_reset_postdata();
        }
        wp_send_json_success( array( 'results' => $results, 'count' => count( $results ) ) );
    }

    /**
     * AJAX: Ürünü klonla (vendor'ın kendi mağazasına ekle)
     */
    public static function ajax_clone_product() {
        check_ajax_referer( 'pzv_nonce', 'nonce' );
        $uid = get_current_user_id();
        if ( ! PZV_Roles::is_vendor( $uid ) ) wp_send_json_error( array( 'message' => 'Yetki yok' ) );

        $source_id = isset( $_POST['source_id'] ) ? (int) $_POST['source_id'] : 0;
        $price     = isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0;
        $stock     = isset( $_POST['stock'] ) ? (int) $_POST['stock'] : 0;
        $sku       = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';
        $status    = isset( $_POST['status'] ) && in_array( $_POST['status'], array( 'publish', 'draft' ), true ) ? $_POST['status'] : 'publish';

        if ( ! $source_id || ! $price || $stock < 0 ) {
            wp_send_json_error( array( 'message' => 'Lütfen tüm zorunlu alanları doldurun.' ) );
        }

        $source = wc_get_product( $source_id );
        if ( ! $source ) {
            wp_send_json_error( array( 'message' => 'Kaynak ürün bulunamadı.' ) );
        }

        // Kendi ürününü klonlayamaz
        if ( (int) get_post_field( 'post_author', $source_id ) === $uid ) {
            wp_send_json_error( array( 'message' => 'Bu ürün zaten size ait.' ) );
        }

        // Aynı ürün için ikinci klon engelle
        global $wpdb;
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE p.post_author = %d
               AND pm.meta_key = '_pzv_cloned_from'
               AND pm.meta_value = %d
               AND p.post_status != 'trash'
             LIMIT 1",
            $uid, $source_id
        ) );
        if ( $existing ) {
            wp_send_json_error( array( 'message' => 'Bu ürünü zaten mağazanıza eklemişsiniz. Düzenlemek için "Ürünlerim" sekmesini kullanın.' ) );
        }

        // Sadece basit ürün klonlanır (variable çok karmaşık, ileride eklenecek)
        if ( $source->get_type() !== 'simple' ) {
            wp_send_json_error( array( 'message' => 'Şu an sadece basit ürünler eklenebilir. Variable (varyasyonlu) ürünler için sistem yöneticisi ile görüşün.' ) );
        }

        // Yeni ürünü oluştur
        $new = new WC_Product_Simple();
        $new->set_name( $source->get_name() );
        $new->set_status( $status );
        $new->set_description( $source->get_description() );
        $new->set_short_description( $source->get_short_description() );
        $new->set_regular_price( (string) $price );
        $new->set_manage_stock( true );
        $new->set_stock_quantity( $stock );
        $new->set_stock_status( $stock > 0 ? 'instock' : 'outofstock' );
        if ( $sku ) $new->set_sku( $sku );
        $new->set_weight( $source->get_weight() );
        $new->set_length( $source->get_length() );
        $new->set_width( $source->get_width() );
        $new->set_height( $source->get_height() );
        $new->set_image_id( $source->get_image_id() );
        $new->set_gallery_image_ids( $source->get_gallery_image_ids() );
        // Kategorileri kopyala
        $cat_ids = wp_get_post_terms( $source_id, 'product_cat', array( 'fields' => 'ids' ) );
        if ( ! is_wp_error( $cat_ids ) && ! empty( $cat_ids ) ) {
            $new->set_category_ids( $cat_ids );
        }
        $new_id = $new->save();
        if ( ! $new_id ) {
            wp_send_json_error( array( 'message' => 'Ürün oluşturulamadı.' ) );
        }

        // post_author = vendor
        wp_update_post( array( 'ID' => $new_id, 'post_author' => $uid ) );
        // Meta: hangi orijinalden klonlandı (duplicate engellemek için)
        update_post_meta( $new_id, '_pzv_cloned_from', $source_id );
        update_post_meta( $new_id, '_pzv_clone_date', current_time( 'mysql' ) );

        wp_send_json_success( array(
            'product_id' => $new_id,
            'message'    => 'Ürün mağazanıza eklendi!',
            'edit_url'   => add_query_arg( 'tab', 'products', get_permalink( get_page_by_path( 'saticim' ) ) ),
            'view_url'   => get_permalink( $new_id ),
        ) );
    }

    /**
     * AJAX: Vendor kendi ürününü güncelle (hızlı fiyat/stok)
     */
    public static function ajax_update_my_product() {
        check_ajax_referer( 'pzv_nonce', 'nonce' );
        $uid = get_current_user_id();
        if ( ! PZV_Roles::is_vendor( $uid ) ) wp_send_json_error( array( 'message' => 'Yetki yok' ) );

        $pid = (int) ( $_POST['product_id'] ?? 0 );
        $owner = (int) get_post_field( 'post_author', $pid );
        if ( ! $pid || $owner !== $uid ) wp_send_json_error( array( 'message' => 'Bu ürün size ait değil.' ) );

        $product = wc_get_product( $pid );
        if ( ! $product ) wp_send_json_error( array( 'message' => 'Ürün bulunamadı.' ) );

        if ( isset( $_POST['price'] ) ) $product->set_regular_price( floatval( $_POST['price'] ) );
        if ( isset( $_POST['stock'] ) ) {
            $stock = (int) $_POST['stock'];
            $product->set_manage_stock( true );
            $product->set_stock_quantity( $stock );
            $product->set_stock_status( $stock > 0 ? 'instock' : 'outofstock' );
        }
        if ( isset( $_POST['status'] ) && in_array( $_POST['status'], array( 'publish', 'draft' ), true ) ) {
            $product->set_status( $_POST['status'] );
        }
        $product->save();
        wp_send_json_success( array( 'message' => 'Güncellendi' ) );
    }

    private static function tab_orders( $user_id ) {
        if ( isset( $_GET['view'] ) ) {
            $oid = (int) $_GET['view'];
            $vendor_orders = PZV_Vendor::get_order_ids( $user_id );
            if ( ! in_array( $oid, $vendor_orders, true ) ) {
                echo '<p>⚠ Bu siparişe erişim yetkiniz yok.</p>'; return;
            }
            self::render_order_view( $oid, $user_id );
            return;
        }
        $order_ids = PZV_Vendor::get_order_ids( $user_id );
        ?>
        <h3>📋 Siparişlerim (<?php echo count( $order_ids ); ?>)</h3>
        <?php if ( empty( $order_ids ) ) : ?>
            <p>Henüz sipariş yok.</p>
        <?php else : ?>
            <table class="pzv-table">
                <thead><tr><th>#</th><th>Tarih</th><th>Müşteri</th><th>Ürünüm</th><th>Kazancım</th><th>Durum</th><th></th></tr></thead>
                <tbody>
                <?php foreach ( $order_ids as $oid ) :
                    $o = wc_get_order( $oid ); if ( ! $o ) continue;
                    list( $items, $earning ) = self::vendor_order_summary( $oid, $user_id );
                    ?>
                    <tr>
                        <td><strong>#<?php echo esc_html( $o->get_order_number() ); ?></strong></td>
                        <td><?php echo esc_html( $o->get_date_created()->date_i18n( 'd.m.Y' ) ); ?></td>
                        <td><?php echo esc_html( $o->get_formatted_billing_full_name() ); ?></td>
                        <td><?php echo (int) $items; ?> adet</td>
                        <td><strong><?php echo wc_price( $earning ); ?></strong></td>
                        <td><span class="pzv-status pzv-status-<?php echo esc_attr( $o->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></span></td>
                        <td><a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'orders', 'view' => $oid ), get_permalink() ) ); ?>">Detay →</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

    private static function vendor_order_summary( $order_id, $vendor_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return array( 0, 0 );
        $items = 0; $earning = 0;
        foreach ( $order->get_items() as $item ) {
            $owner = (int) get_post_field( 'post_author', $item->get_product_id() );
            if ( $owner !== (int) $vendor_id ) continue;
            $items += $item->get_quantity();
            $earning += (float) $item->get_total();
        }
        global $wpdb;
        $sum_v = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(vendor_amt) FROM " . PZV_Commission::table_name() . " WHERE order_id = %d AND vendor_id = %d",
            $order_id, $vendor_id
        ) );
        if ( $sum_v ) $earning = (float) $sum_v;
        return array( $items, $earning );
    }

    private static function render_order_view( $order_id, $vendor_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;
        ?>
        <p><a href="<?php echo esc_url( add_query_arg( 'tab', 'orders', get_permalink() ) ); ?>">← Siparişlere dön</a></p>
        <h3>📋 Sipariş #<?php echo esc_html( $order->get_order_number() ); ?></h3>

        <div class="pzv-order-grid">
            <div class="pzv-order-box">
                <h4>👤 Müşteri</h4>
                <p><strong><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></strong><br>
                <?php echo esc_html( $order->get_billing_email() ); ?><br>
                <?php echo esc_html( $order->get_billing_phone() ); ?></p>
            </div>
            <div class="pzv-order-box">
                <h4>📍 Teslimat</h4>
                <p><?php echo wp_kses_post( $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address() ); ?></p>
            </div>
            <div class="pzv-order-box">
                <h4>📊 Bilgi</h4>
                <p><strong>Durum:</strong> <span class="pzv-status pzv-status-<?php echo esc_attr( $order->get_status() ); ?>"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span></p>
                <p><strong>Tarih:</strong> <?php echo esc_html( $order->get_date_created()->date_i18n( 'd.m.Y H:i' ) ); ?></p>
            </div>
        </div>

        <h4 style="margin-top:24px;">📦 Sipariş Ürünlerim</h4>
        <table class="pzv-table">
            <thead><tr><th>Ürün</th><th>Adet</th><th>Tutar</th></tr></thead>
            <tbody>
            <?php foreach ( $order->get_items() as $item ) :
                $owner = (int) get_post_field( 'post_author', $item->get_product_id() );
                if ( $owner !== (int) $vendor_id ) continue;
                ?>
                <tr><td><strong><?php echo esc_html( $item->get_name() ); ?></strong></td>
                    <td><?php echo (int) $item->get_quantity(); ?></td>
                    <td><strong><?php echo wc_price( $item->get_total() ); ?></strong></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h4 style="margin-top:24px;">🚚 Sipariş Yönetimi</h4>
        <form class="pzv-order-form" data-order="<?php echo (int) $order_id; ?>">
            <table class="pzv-form-table">
                <tr><th>Yeni Durum:</th>
                    <td><select name="status">
                        <?php foreach ( wc_get_order_statuses() as $key => $label ) :
                            $val = str_replace( 'wc-', '', $key );
                            ?>
                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $val, $order->get_status() ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select></td>
                </tr>
                <tr><th>Kargo Firması:</th>
                    <td><input type="text" name="shipping_company" value="<?php echo esc_attr( $order->get_meta( '_pzv_shipping_company' ) ); ?>" placeholder="Örn: Aras Kargo"></td>
                </tr>
                <tr><th>Takip Numarası:</th>
                    <td><input type="text" name="tracking_number" value="<?php echo esc_attr( $order->get_meta( '_pzv_tracking_number' ) ); ?>" placeholder="Kargo takip kodu"></td>
                </tr>
                <tr><th>Müşteriye Not:</th>
                    <td><textarea name="note" rows="2" placeholder="Bu not müşteriye e-posta ile gönderilir (opsiyonel)"></textarea></td>
                </tr>
            </table>
            <p><button type="button" class="button button-primary pzv-update-order">💾 Güncelle ve Müşteriye Bildir</button></p>
            <div class="pzv-order-msg"></div>
        </form>
        <?php
    }

    private static function tab_earnings( $user_id ) {
        $pending = PZV_Commission::vendor_pending( $user_id );
        $paid = PZV_Commission::vendor_paid( $user_id );
        $records = PZV_Commission::vendor_records( $user_id, 100 );
        ?>
        <h3>💰 Kazançlarım</h3>
        <div class="pzv-stats">
            <div class="pzv-stat pzv-stat-pending"><div class="pzv-stat-n"><?php echo wc_price( $pending ); ?></div><div class="pzv-stat-l">⏳ Bekleyen</div></div>
            <div class="pzv-stat pzv-stat-paid"><div class="pzv-stat-n"><?php echo wc_price( $paid ); ?></div><div class="pzv-stat-l">✓ Ödenmiş</div></div>
        </div>
        <h4>📋 Detay</h4>
        <?php if ( empty( $records ) ) : ?>
            <p>Henüz kazanç yok.</p>
        <?php else : ?>
            <table class="pzv-table">
                <thead><tr><th>Tarih</th><th>Sipariş</th><th>Ürün</th><th>Satış</th><th>Komisyon</th><th>Kazancınız</th><th>Durum</th></tr></thead>
                <tbody>
                <?php foreach ( $records as $r ) :
                    $p = wc_get_product( $r->product_id );
                    $status_label = $r->status === 'paid' ? '✓ Ödendi' : ( $r->status === 'pending' ? '⏳ Bekliyor' : '✗ İptal' );
                    ?>
                    <tr>
                        <td><?php echo esc_html( mysql2date( 'd.m.Y', $r->created_at ) ); ?></td>
                        <td>#<?php echo (int) $r->order_id; ?></td>
                        <td><?php echo $p ? esc_html( $p->get_name() ) : '-'; ?></td>
                        <td><?php echo wc_price( $r->line_total ); ?></td>
                        <td><?php echo wc_price( $r->commission_amt ); ?> <small>(%<?php echo (float) $r->commission_rate; ?>)</small></td>
                        <td><strong><?php echo wc_price( $r->vendor_amt ); ?></strong></td>
                        <td><span class="pzv-status pzv-status-<?php echo esc_attr( $r->status ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

    private static function tab_profile() {
        ?>
        <h3>⚙️ Profilim</h3>
        <p>Mağaza bilgilerinizi düzenleyin:</p>
        <p>
            <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=pzv-vendor-info' ) ); ?>">🏪 Mağaza Bilgileri</a>
            <a class="button" href="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>">👤 Kullanıcı Profili</a>
        </p>
        <?php
    }

    /** AJAX: vendor sipariş güncelle */
    public static function ajax_update_order() {
        check_ajax_referer( 'pzv_nonce', 'nonce' );
        $uid = get_current_user_id();
        if ( ! PZV_Roles::is_vendor( $uid ) ) wp_send_json_error( array( 'message' => 'Yetki yok' ) );
        $oid = (int) $_POST['order_id'];
        $vendor_orders = PZV_Vendor::get_order_ids( $uid );
        if ( ! in_array( $oid, $vendor_orders, true ) ) wp_send_json_error( array( 'message' => 'Erişim yok' ) );
        $order = wc_get_order( $oid );
        if ( ! $order ) wp_send_json_error( array( 'message' => 'Sipariş bulunamadı' ) );

        $status   = sanitize_key( $_POST['status'] ?? '' );
        $company  = sanitize_text_field( $_POST['shipping_company'] ?? '' );
        $tracking = sanitize_text_field( $_POST['tracking_number'] ?? '' );
        $note     = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( $status && $status !== $order->get_status() ) {
            $order->update_status( $status, 'Satıcı güncellemesi' );
        }
        if ( $company )  $order->update_meta_data( '_pzv_shipping_company', $company );
        if ( $tracking ) $order->update_meta_data( '_pzv_tracking_number', $tracking );

        $public_msg = '';
        if ( $company || $tracking ) {
            $public_msg = 'Kargo: ' . $company . ( $tracking ? ' / Takip No: ' . $tracking : '' );
        }
        if ( $note ) $public_msg .= ( $public_msg ? "\n" : '' ) . $note;
        if ( $public_msg ) $order->add_order_note( $public_msg, 1 ); // 1 = müşteriye e-posta
        $order->save();
        wp_send_json_success( array( 'message' => 'Güncellendi ve müşteriye bildirildi' ) );
    }
}
