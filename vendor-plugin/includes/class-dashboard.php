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
        if ( is_user_logged_in() && PZV_Roles::is_vendor() ) {
            wp_enqueue_media();
        }
    }

    public static function render_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<div class="pzv-dash-login"><h3>🔒 Giriş Gerekli</h3><p>Satıcı paneline erişmek için <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">giriş yapın</a>.</p></div>';
        }
        $user_id = get_current_user_id();
        if ( ! PZV_Roles::is_vendor( $user_id ) ) {
            return '<div class="pzv-dash-login"><h3>⚠ Satıcı Değilsiniz</h3><p><a href="' . esc_url( home_url( '/satici-ol/' ) ) . '" class="button">Satıcı Başvurusu Yap →</a></p></div>';
        }
        $allowed_tabs = array( 'overview', 'products', 'add-product', 'orders', 'earnings', 'profile' );
        $tab = isset( $_GET['tab'] ) && in_array( sanitize_key( $_GET['tab'] ), $allowed_tabs, true ) ? sanitize_key( $_GET['tab'] ) : 'overview';
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
                    case 'profile':  self::tab_profile( $user_id ); break;
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
        echo '<div class="pzv-table-wrap"><table class="pzv-table"><thead><tr><th>Sipariş</th><th>Tarih</th><th>Müşteri</th><th>Tutar</th><th>Durum</th><th></th></tr></thead><tbody>';
        foreach ( $order_ids as $oid ) {
            $o = wc_get_order( $oid ); if ( ! $o ) continue;
            echo '<tr>';
            echo '<td><strong>#' . esc_html( $o->get_order_number() ) . '</strong></td>';
            echo '<td style="white-space:nowrap">' . esc_html( $o->get_date_created()->date_i18n( 'd.m.Y' ) ) . '</td>';
            echo '<td>' . esc_html( $o->get_formatted_billing_full_name() ) . '</td>';
            echo '<td style="white-space:nowrap">' . wp_kses_post( $o->get_formatted_order_total() ) . '</td>';
            echo '<td><span class="pzv-status pzv-status-' . esc_attr( $o->get_status() ) . '">' . esc_html( wc_get_order_status_name( $o->get_status() ) ) . '</span></td>';
            echo '<td style="white-space:nowrap"><a class="button button-small" href="' . esc_url( add_query_arg( array( 'tab' => 'orders', 'view' => $oid ), get_permalink() ) ) . '">Detay →</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function tab_products( $user_id ) {
        if ( ! empty( $_GET['edit'] ) ) {
            $edit_pid = (int) $_GET['edit'];
            if ( $edit_pid && (int) get_post_field( 'post_author', $edit_pid ) === (int) $user_id ) {
                self::tab_edit_product( $user_id, $edit_pid );
                return;
            }
        }

        $per_page   = 50;
        $cur_page   = max( 1, intval( $_GET['ppage'] ?? 1 ) );
        $cur_cat    = intval( $_GET['pcat']    ?? 0 );
        $cur_brand  = intval( $_GET['pbrand']  ?? 0 );
        $cur_status = sanitize_key( $_GET['pstatus'] ?? '' );
        $cur_search = sanitize_text_field( $_GET['ps'] ?? '' );
        $base_link  = get_permalink();

        // Tüm ürün ID'leri (filtre bağımsız; toplam ve term listesi için)
        $all_ids   = PZV_Vendor::get_product_ids( $user_id );
        $total_all = count( $all_ids );

        // Sorgu parametreleri
        $allowed_statuses = array( 'publish', 'draft', 'pending', 'private' );
        $query_args = array(
            'post_type'      => 'product',
            'author'         => $user_id,
            'post_status'    => ( $cur_status && in_array( $cur_status, $allowed_statuses, true ) )
                                    ? array( $cur_status )
                                    : $allowed_statuses,
            'posts_per_page' => $per_page,
            'paged'          => $cur_page,
            'no_found_rows'  => false,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        if ( $cur_search ) {
            $query_args['s'] = $cur_search;
        }
        $tax_q = array();
        if ( $cur_cat ) {
            $tax_q[] = array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cur_cat, 'include_children' => true );
        }
        if ( $cur_brand ) {
            $tax_q[] = array( 'taxonomy' => 'product_brand', 'field' => 'term_id', 'terms' => $cur_brand );
        }
        if ( ! empty( $tax_q ) ) {
            $tax_q['relation'] = 'AND';
            $query_args['tax_query'] = $tax_q;
        }
        $q           = new WP_Query( $query_args );
        $total       = $q->found_posts;
        $total_pages = $q->max_num_pages;

        // ── Kategoriler (hiyerarşik select için) ──
        $cats     = ! empty( $all_ids ) ? get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'object_ids' => $all_ids,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) ) : array();
        $term_map     = array();
        $top_cats     = array();
        $children_map = array();
        if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
            foreach ( $cats as $c ) {
                if ( $c->slug !== 'uncategorized' ) $term_map[ $c->term_id ] = $c;
            }
            foreach ( $term_map as $tid => $term ) {
                if ( $term->parent === 0 || ! isset( $term_map[ $term->parent ] ) ) {
                    $top_cats[] = $term;
                } else {
                    $children_map[ $term->parent ][] = $term;
                }
            }
        }

        // ── Markalar (select için) ──
        $brands = ! empty( $all_ids ) && taxonomy_exists( 'product_brand' ) ? get_terms( array(
            'taxonomy'   => 'product_brand',
            'hide_empty' => true,
            'object_ids' => $all_ids,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) ) : array();

        // Aktif filtre var mı?
        $has_filter = $cur_cat || $cur_brand || $cur_status || $cur_search;
        $clear_url  = add_query_arg( array( 'tab' => 'products' ), $base_link );
        ?>
        <div class="pzv-section-head">
            <h3>📦 Ürünlerim
                <span class="pzv-prod-count"><?php echo $has_filter ? esc_html( $total . ' / ' . $total_all ) : $total_all; ?></span>
            </h3>
            <a class="pzv-btn-primary" href="<?php echo esc_url( add_query_arg( 'tab', 'add_product', $base_link ) ); ?>">+ Yeni Ürün Ekle</a>
        </div>

        <!-- ── Kompakt filtre çubuğu ── -->
        <form method="get" action="<?php echo esc_url( $base_link ); ?>" class="pzv-filter-bar">
            <input type="hidden" name="tab" value="products">

            <div class="pzv-filter-search">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" name="ps" placeholder="Ürün adı ara…" value="<?php echo esc_attr( $cur_search ); ?>">
            </div>

            <select name="pcat" class="pzv-filter-select">
                <option value="">— Tüm Kategoriler —</option>
                <?php foreach ( $top_cats as $top ) :
                    $sel = selected( $cur_cat, $top->term_id, false ); ?>
                    <option value="<?php echo esc_attr( $top->term_id ); ?>" <?php echo $sel; ?>>
                        <?php echo esc_html( $top->name ); ?> (<?php echo intval( $top->count ); ?>)
                    </option>
                    <?php if ( isset( $children_map[ $top->term_id ] ) ) :
                        foreach ( $children_map[ $top->term_id ] as $child ) :
                            $csel = selected( $cur_cat, $child->term_id, false ); ?>
                        <option value="<?php echo esc_attr( $child->term_id ); ?>" <?php echo $csel; ?>>
                            &nbsp;&nbsp;› <?php echo esc_html( $child->name ); ?> (<?php echo intval( $child->count ); ?>)
                        </option>
                        <?php endforeach;
                    endif;
                endforeach; ?>
            </select>

            <?php if ( ! empty( $brands ) && ! is_wp_error( $brands ) ) : ?>
            <select name="pbrand" class="pzv-filter-select">
                <option value="">— Tüm Markalar —</option>
                <?php foreach ( $brands as $br ) :
                    $sel = selected( $cur_brand, $br->term_id, false ); ?>
                    <option value="<?php echo esc_attr( $br->term_id ); ?>" <?php echo $sel; ?>>
                        <?php echo esc_html( $br->name ); ?> (<?php echo intval( $br->count ); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <select name="pstatus" class="pzv-filter-select">
                <option value="">— Tüm Durumlar —</option>
                <option value="publish" <?php selected( $cur_status, 'publish' ); ?>>✓ Yayında</option>
                <option value="pending" <?php selected( $cur_status, 'pending' ); ?>>⏳ Onay Bekliyor</option>
                <option value="draft"   <?php selected( $cur_status, 'draft' );   ?>>📝 Taslak</option>
            </select>

            <button type="submit" class="pzv-filter-btn">Filtrele</button>
            <?php if ( $has_filter ) : ?>
            <a href="<?php echo esc_url( $clear_url ); ?>" class="pzv-filter-clear-btn">✕ Temizle</a>
            <?php endif; ?>
        </form>

        <!-- Aktif filtre etiketleri -->
        <?php if ( $has_filter ) : ?>
        <div class="pzv-filter-tags">
            <?php
            if ( $cur_cat && isset( $term_map[ $cur_cat ] ) ) {
                $tag_url = esc_url( add_query_arg( array( 'pcat' => 0 ), remove_query_arg( 'pcat' ) ) );
                echo '<span class="pzv-ftag pzv-ftag-cat">📂 ' . esc_html( $term_map[ $cur_cat ]->name ) . ' <a href="' . $tag_url . '">✕</a></span>';
            }
            if ( $cur_brand && ! empty( $brands ) && ! is_wp_error( $brands ) ) {
                foreach ( $brands as $br ) {
                    if ( $br->term_id === $cur_brand ) {
                        $tag_url = esc_url( remove_query_arg( 'pbrand' ) );
                        echo '<span class="pzv-ftag pzv-ftag-brand">🏷️ ' . esc_html( $br->name ) . ' <a href="' . $tag_url . '">✕</a></span>';
                        break;
                    }
                }
            }
            $status_labels = array( 'publish' => 'Yayında', 'pending' => 'Onay Bekliyor', 'draft' => 'Taslak' );
            if ( $cur_status && isset( $status_labels[ $cur_status ] ) ) {
                $tag_url = esc_url( remove_query_arg( 'pstatus' ) );
                echo '<span class="pzv-ftag pzv-ftag-status">📊 ' . esc_html( $status_labels[ $cur_status ] ) . ' <a href="' . $tag_url . '">✕</a></span>';
            }
            if ( $cur_search ) {
                $tag_url = esc_url( remove_query_arg( 'ps' ) );
                echo '<span class="pzv-ftag pzv-ftag-search">🔍 &ldquo;' . esc_html( $cur_search ) . '&rdquo; <a href="' . $tag_url . '">✕</a></span>';
            }
            ?>
        </div>
        <?php endif; ?>

        <?php if ( ! $q->have_posts() ) : ?>
            <div class="pzv-empty-state">
                <div class="pzv-empty-ico">📦</div>
                <div class="pzv-empty-title">Ürün bulunamadı</div>
                <div class="pzv-empty-text">Filtreleri değiştirip tekrar deneyin.</div>
                <?php if ( $has_filter ) : ?><a href="<?php echo esc_url( $clear_url ); ?>" class="pzv-btn-secondary">Filtreleri Temizle</a><?php endif; ?>
            </div>
        <?php else : ?>
            <div class="pzv-table-wrap"><table class="pzv-table">
                <thead><tr><th>Görsel</th><th>Ürün</th><th>SKU</th><th>Fiyat (₺)</th><th>Stok</th><th>Durum</th><th>İşlem</th></tr></thead>
                <tbody>
                <?php while ( $q->have_posts() ) : $q->the_post();
                    $pid         = get_the_ID();
                    $p           = wc_get_product( $pid ); if ( ! $p ) continue;
                    $cloned_from = get_post_meta( $pid, '_pzv_cloned_from', true );
                    ?>
                    <tr data-product="<?php echo (int) $pid; ?>">
                        <td><?php echo $p->get_image( array( 50, 50 ) ); ?></td>
                        <td>
                            <strong><?php echo esc_html( $p->get_name() ); ?></strong>
                            <?php if ( $cloned_from ) : ?><br><small style="color:#888;">📋 Katalog ürünü</small><?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $p->get_sku() ?: '-' ); ?></td>
                        <td><input type="number" class="pzv-quick-price" step="0.01" min="0" value="<?php echo esc_attr( $p->get_regular_price() ); ?>" style="width:90px;"></td>
                        <td><input type="number" class="pzv-quick-stock" min="0" value="<?php echo esc_attr( $p->get_stock_quantity() ); ?>" style="width:70px;"></td>
                        <td>
                            <select class="pzv-quick-status">
                                <option value="publish" <?php selected( $p->get_status(), 'publish' ); ?>>✓ Yayında</option>
                                <option value="pending" <?php selected( $p->get_status(), 'pending' ); ?>>⏳ Onay Bekliyor</option>
                                <option value="draft"   <?php selected( $p->get_status(), 'draft' );   ?>>📝 Taslak</option>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="button button-small button-primary pzv-quick-save">💾</button>
                            <a class="button button-small" href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank">👁</a>
                            <a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'tab' => 'products', 'edit' => $pid ), get_permalink() ) ); ?>">✏️</a>
                        </td>
                    </tr>
                <?php endwhile; wp_reset_postdata(); ?>
                </tbody>
            </table></div>

            <?php if ( $total_pages > 1 ) :
                $from    = ( $cur_page - 1 ) * $per_page + 1;
                $to      = min( $cur_page * $per_page, $total );
                $pg_args = array_filter( array( 'tab' => 'products', 'pcat' => $cur_cat ?: null, 'pbrand' => $cur_brand ?: null, 'pstatus' => $cur_status ?: null, 'ps' => $cur_search ?: null ) );
                $pg_base = add_query_arg( $pg_args, $base_link );
                ?>
            <div class="pzv-pagination">
                <?php if ( $cur_page > 1 ) echo '<a href="' . esc_url( add_query_arg( 'ppage', $cur_page - 1, $pg_base ) ) . '" class="pzv-page-btn">‹ Önceki</a>';
                $rs = max( 1, $cur_page - 2 );
                $re = min( $total_pages, $cur_page + 2 );
                if ( $rs > 1 ) echo '<a href="' . esc_url( add_query_arg( 'ppage', 1, $pg_base ) ) . '" class="pzv-page-btn">1</a><span class="pzv-page-dots">…</span>';
                for ( $i = $rs; $i <= $re; $i++ ) {
                    $cls = ( $i === $cur_page ) ? ' pzv-page-active' : '';
                    echo '<a href="' . esc_url( add_query_arg( 'ppage', $i, $pg_base ) ) . '" class="pzv-page-btn' . $cls . '">' . $i . '</a>';
                }
                if ( $re < $total_pages ) echo '<span class="pzv-page-dots">…</span><a href="' . esc_url( add_query_arg( 'ppage', $total_pages, $pg_base ) ) . '" class="pzv-page-btn">' . $total_pages . '</a>';
                if ( $cur_page < $total_pages ) echo '<a href="' . esc_url( add_query_arg( 'ppage', $cur_page + 1, $pg_base ) ) . '" class="pzv-page-btn">Sonraki ›</a>';
                ?>
                <span class="pzv-page-info"><?php printf( '%d–%d / %d ürün', $from, $to, $total ); ?></span>
            </div>
            <?php endif; ?>
        <?php endif;
    }

    private static function tab_add_product() {
        $cats = self::build_cat_options();
        ?>
        <h3>➕ Yeni Ürün Ekle</h3>

        <div class="pzv-product-form" id="pzv-new-product-form">
            <div class="pzv-pf-grid">
                <div class="pzv-pf-main">
                    <div class="pzv-form-row">
                        <label>Ürün Adı <span class="req">*</span></label>
                        <input type="text" id="pzv-np-title" placeholder="Ürün adını girin..." maxlength="200">
                    </div>
                    <div class="pzv-form-row">
                        <label>Kategori <span class="req">*</span></label>
                        <select id="pzv-np-cat"><option value="">— Kategori seçin —</option><?php echo $cats; ?></select>
                    </div>
                    <div class="pzv-pf-2col">
                        <div class="pzv-form-row">
                            <label>Normal Fiyat (₺) <span class="req">*</span></label>
                            <input type="number" id="pzv-np-price" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="pzv-form-row">
                            <label>İndirimli Fiyat (₺)</label>
                            <input type="number" id="pzv-np-sale-price" step="0.01" min="0" placeholder="Opsiyonel">
                        </div>
                    </div>
                    <div class="pzv-pf-2col">
                        <div class="pzv-form-row">
                            <label>Stok Miktarı <span class="req">*</span></label>
                            <input type="number" id="pzv-np-stock" min="0" placeholder="0">
                        </div>
                        <div class="pzv-form-row">
                            <label>SKU (Stok Kodu)</label>
                            <input type="text" id="pzv-np-sku" placeholder="Opsiyonel">
                        </div>
                    </div>
                    <div class="pzv-form-row">
                        <label>Kısa Açıklama</label>
                        <textarea id="pzv-np-short-desc" rows="2" placeholder="Birkaç cümleyle ürünü tanıtın..."></textarea>
                    </div>
                    <div class="pzv-form-row">
                        <label>Detaylı Açıklama</label>
                        <textarea id="pzv-np-desc" rows="5" placeholder="Ürün özellikleri, malzeme, kullanım bilgisi..."></textarea>
                    </div>
                    <div class="pzv-form-row">
                        <label>Yayın Durumu</label>
                        <select id="pzv-np-status">
                            <option value="pending">Onay için gönder</option>
                            <option value="draft">Taslak olarak kaydet</option>
                        </select>
                    </div>
                </div>
                <div class="pzv-pf-side">
                    <div class="pzv-pf-img-wrap">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:8px;">Öne Çıkan Görsel</label>
                        <div class="pzv-pf-image-box" id="pzv-np-imgbox">
                            <div class="pzv-pf-img-placeholder" id="pzv-np-placeholder">
                                <span style="font-size:36px;">📷</span>
                                <span>Görsel seçin</span>
                            </div>
                            <img id="pzv-np-img-preview" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;">
                        </div>
                        <input type="hidden" id="pzv-np-img-id" value="0">
                        <div class="pzv-pf-img-btns">
                            <button type="button" class="button" id="pzv-np-img-btn">📷 Görsel Seç</button>
                            <button type="button" class="button" id="pzv-np-img-remove" style="display:none;">✕ Kaldır</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pzv-pf-actions">
                <button type="button" class="button button-primary pzv-pf-submit" id="pzv-np-submit" data-form="new">
                    <span class="pzv-btn-txt">💾 Ürünü Kaydet</span>
                    <span class="pzv-btn-spin" style="display:none;">⏳ Kaydediliyor...</span>
                </button>
                <div class="pzv-form-msg" id="pzv-np-msg"></div>
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
        $requested = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'pending';
        // Vendor cannot publish directly — must go through admin approval
        $status    = ( $requested === 'draft' ) ? 'draft' : 'pending';

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
            'message'    => $status === 'draft'
                ? 'Ürün taslak olarak kaydedildi.'
                : 'Ürün onay için gönderildi. Yönetici onayından sonra yayınlanacak.',
            'went_pending' => ( $status === 'pending' ),
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
        $went_pending = false;
        if ( isset( $_POST['status'] ) ) {
            $new_status = sanitize_key( $_POST['status'] );
            if ( $new_status === 'publish' ) {
                // Vendor cannot publish directly — force pending approval
                $product->set_status( 'pending' );
                $went_pending = true;
            } elseif ( in_array( $new_status, array( 'draft', 'pending' ), true ) ) {
                $product->set_status( $new_status );
            }
        }
        // Allow price/stock saves to bypass the wp_insert_post_data pending filter
        if ( ! defined( 'PZV_DOING_QUICK_SAVE' ) ) define( 'PZV_DOING_QUICK_SAVE', true );
        $product->save();
        $msg = $went_pending ? 'Onay için gönderildi. Yönetici onayından sonra yayınlanacak.' : 'Güncellendi';
        wp_send_json_success( array( 'message' => $msg, 'went_pending' => $went_pending ) );
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
            <div class="pzv-table-wrap"><table class="pzv-table">
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
            </table></div>
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
        <div class="pzv-table-wrap"><table class="pzv-table">
            <thead><tr><th>Ürün</th><th>Adet</th><th>Tutar</th></tr></thead>
            <tbody>
            <?php foreach ( $order->get_items() as $item ) :
                $owner = (int) get_post_field( 'post_author', $item->get_product_id() );
                if ( $owner !== (int) $vendor_id ) continue;
                ?>
                <tr><td><strong><?php echo esc_html( $item->get_name() ); ?></strong></td>
                    <td style="white-space:nowrap"><?php echo (int) $item->get_quantity(); ?></td>
                    <td style="white-space:nowrap"><strong><?php echo wc_price( $item->get_total() ); ?></strong></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>

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
            <div class="pzv-table-wrap"><table class="pzv-table">
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
            </table></div>
        <?php endif;
    }

    private static function tab_profile( $user_id = 0 ) {
        if ( ! $user_id ) $user_id = get_current_user_id();
        $v = PZV_Vendor::get( $user_id );
        if ( ! $v ) return;
        $logo_url   = ! empty( $v['logo'] )   ? wp_get_attachment_image_url( $v['logo'],   'thumbnail' ) : '';
        $banner_url = ! empty( $v['banner'] ) ? wp_get_attachment_image_url( $v['banner'], 'medium' )    : '';
        $cats = self::build_cat_options();
        ?>
        <h3>⚙️ Mağaza Profilim</h3>
        <div class="pzv-product-form" id="pzv-profile-form">
            <div class="pzv-pf-grid">
                <div class="pzv-pf-main">
                    <div class="pzv-form-row">
                        <label>Mağaza Adı <span class="req">*</span></label>
                        <input type="text" id="pzv-prf-name" value="<?php echo esc_attr( $v['store_name'] ); ?>">
                    </div>
                    <div class="pzv-form-row">
                        <label>Mağaza URL (slug)</label>
                        <div class="pzv-prf-slug-wrap">
                            <span class="pzv-prf-slug-base"><?php echo esc_html( home_url('/magaza/') ); ?></span>
                            <input type="text" id="pzv-prf-slug" value="<?php echo esc_attr( $v['store_slug'] ); ?>">
                            <span class="pzv-prf-slug-end">/</span>
                        </div>
                    </div>
                    <div class="pzv-pf-2col">
                        <div class="pzv-form-row">
                            <label>Telefon</label>
                            <input type="tel" id="pzv-prf-phone" value="<?php echo esc_attr( $v['phone'] ); ?>">
                        </div>
                        <div class="pzv-form-row">
                            <label>Şehir</label>
                            <input type="text" id="pzv-prf-city" value="<?php echo esc_attr( $v['city'] ); ?>">
                        </div>
                    </div>
                    <div class="pzv-form-row">
                        <label>Adres</label>
                        <textarea id="pzv-prf-address" rows="2"><?php echo esc_textarea( $v['address'] ); ?></textarea>
                    </div>
                    <div class="pzv-form-row">
                        <label>Mağaza Açıklaması</label>
                        <textarea id="pzv-prf-desc" rows="3"><?php echo esc_textarea( $v['description'] ); ?></textarea>
                    </div>
                    <div class="pzv-form-row">
                        <label>IBAN (Ödeme için)</label>
                        <input type="text" id="pzv-prf-iban" value="<?php echo esc_attr( $v['iban'] ); ?>" placeholder="TR...">
                    </div>
                    <div class="pzv-form-row">
                        <label>Kargoya Verme Süresi</label>
                        <select id="pzv-prf-dispatch">
                            <option value="0"<?php selected( (int) $v['dispatch_days'], 0 ); ?>>Aynı gün (14:00'a kadar sipariş)</option>
                            <?php for ( $i = 1; $i <= 30; $i++ ) : ?>
                            <option value="<?php echo $i; ?>"<?php selected( (int) $v['dispatch_days'], $i ); ?>><?php echo $i; ?> iş günü</option>
                            <?php endfor; ?>
                        </select>
                        <small style="color:#888;font-size:12px;margin-top:4px;display:block;">Siparişi aldıktan kaç iş günü içinde kargoya veriyorsunuz?</small>
                    </div>
                    <div class="pzv-form-row">
                        <label>Vergi No / TC Kimlik No</label>
                        <input type="text" id="pzv-prf-tc-tax" value="<?php echo esc_attr( $v['tc_or_tax'] ?? '' ); ?>" placeholder="Vergi numarası veya TC kimlik no">
                    </div>
                    <div class="pzv-pf-2col">
                        <div class="pzv-form-row">
                            <label>Instagram</label>
                            <input type="url" id="pzv-prf-instagram" value="<?php echo esc_attr( $v['instagram_url'] ?? '' ); ?>" placeholder="https://instagram.com/maazaadi">
                        </div>
                        <div class="pzv-form-row">
                            <label>X (Twitter)</label>
                            <input type="url" id="pzv-prf-twitter" value="<?php echo esc_attr( $v['twitter_url'] ?? '' ); ?>" placeholder="https://x.com/maazaadi">
                        </div>
                    </div>
                    <div class="pzv-form-row">
                        <label>Çalışma Saatleri</label>
                        <input type="text" id="pzv-prf-working-hours" value="<?php echo esc_attr( $v['working_hours'] ?? '' ); ?>" placeholder="Örn: Hafta içi 09:00–18:00, Cmt 10:00–14:00">
                    </div>
                    <div class="pzv-form-row">
                        <label>İade Politikası</label>
                        <textarea id="pzv-prf-return-policy" rows="3" placeholder="Müşterilere gösterilen iade/değişim politikanız..."><?php echo esc_textarea( $v['return_policy'] ?? '' ); ?></textarea>
                    </div>
                </div>
                <div class="pzv-pf-side">
                    <div class="pzv-pf-img-wrap" style="margin-bottom:20px;">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:8px;">Mağaza Logosu</label>
                        <div class="pzv-pf-image-box pzv-pf-logo-box" id="pzv-prf-logo-box">
                            <div class="pzv-pf-img-placeholder" id="pzv-prf-logo-ph"<?php echo $logo_url ? ' style="display:none;"' : ''; ?>><span style="font-size:28px;">🏪</span><span>Logo</span></div>
                            <img id="pzv-prf-logo-img" src="<?php echo esc_url( $logo_url ); ?>" alt=""<?php echo ! $logo_url ? ' style="display:none;"' : ' style="width:100%;height:100%;object-fit:cover;"'; ?>>
                        </div>
                        <input type="hidden" id="pzv-prf-logo-id" value="<?php echo (int) ( $v['logo'] ?? 0 ); ?>">
                        <div class="pzv-pf-img-btns">
                            <button type="button" class="button" id="pzv-prf-logo-btn">Seç</button>
                            <button type="button" class="button" id="pzv-prf-logo-rm"<?php echo empty( $v['logo'] ) ? ' style="display:none;"' : ''; ?>>✕</button>
                        </div>
                        <small style="color:#888;">Önerilen: 200×200 px</small>
                    </div>
                    <div class="pzv-pf-img-wrap">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:8px;">Mağaza Bannerı</label>
                        <div class="pzv-pf-image-box pzv-pf-banner-box" id="pzv-prf-banner-box">
                            <div class="pzv-pf-img-placeholder" id="pzv-prf-banner-ph"<?php echo $banner_url ? ' style="display:none;"' : ''; ?>><span style="font-size:28px;">🖼️</span><span>Banner</span></div>
                            <img id="pzv-prf-banner-img" src="<?php echo esc_url( $banner_url ); ?>" alt=""<?php echo ! $banner_url ? ' style="display:none;"' : ' style="width:100%;height:100%;object-fit:cover;"'; ?>>
                        </div>
                        <input type="hidden" id="pzv-prf-banner-id" value="<?php echo (int) ( $v['banner'] ?? 0 ); ?>">
                        <div class="pzv-pf-img-btns">
                            <button type="button" class="button" id="pzv-prf-banner-btn">Seç</button>
                            <button type="button" class="button" id="pzv-prf-banner-rm"<?php echo empty( $v['banner'] ) ? ' style="display:none;"' : ''; ?>>✕</button>
                        </div>
                        <small style="color:#888;">Önerilen: 1200×300 px</small>
                    </div>
                </div>
            </div>
            <div class="pzv-pf-actions">
                <button type="button" class="button button-primary" id="pzv-prf-save">💾 Profili Kaydet</button>
                <div class="pzv-form-msg" id="pzv-prf-msg"></div>
            </div>
        </div>
        <?php
    }

    /** ─── Kategori seçeneklerini hiyerarşik olarak oluştur ─── */
    private static function build_cat_options( $parent = 0, $depth = 0 ) {
        $args = array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => $parent,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );
        $cats = get_terms( $args );
        $html = '';
        if ( is_wp_error( $cats ) || empty( $cats ) ) return $html;
        foreach ( $cats as $cat ) {
            if ( $cat->slug === 'uncategorized' ) continue;
            $pad   = str_repeat( '&nbsp;&nbsp;&nbsp;', $depth );
            $html .= '<option value="' . (int) $cat->term_id . '">' . $pad . esc_html( $cat->name ) . '</option>';
            $html .= self::build_cat_options( $cat->term_id, $depth + 1 );
        }
        return $html;
    }

    /** ─── TAB: Ürün Düzenle ─── */
    private static function tab_edit_product( $user_id, $product_id ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) { echo '<p>Ürün bulunamadı.</p>'; return; }
        $cats       = self::build_cat_options();
        $prod_cats  = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
        $first_cat  = ! empty( $prod_cats ) ? (int) $prod_cats[0] : 0;
        $img_id     = (int) $product->get_image_id();
        $img_url    = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';
        $back_url   = add_query_arg( 'tab', 'products', get_permalink() );
        ?>
        <p><a href="<?php echo esc_url( $back_url ); ?>" class="button">&larr; Ürünlerime dön</a></p>
        <h3>✏️ Ürün Düzenle</h3>

        <div class="pzv-product-form" id="pzv-edit-product-form" data-product="<?php echo (int) $product_id; ?>">
            <div class="pzv-pf-grid">
                <div class="pzv-pf-main">
                    <div class="pzv-form-row">
                        <label>Ürün Adı <span class="req">*</span></label>
                        <input type="text" id="pzv-ep-title" value="<?php echo esc_attr( $product->get_name() ); ?>" maxlength="200">
                    </div>
                    <div class="pzv-form-row">
                        <label>Kategori</label>
                        <select id="pzv-ep-cat">
                            <option value="0">— Değiştirmek için seçin —</option>
                            <?php
                            $all_cats = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ) );
                            if ( ! is_wp_error( $all_cats ) ) foreach ( $all_cats as $cat ) {
                                if ( $cat->slug === 'uncategorized' ) continue;
                                echo '<option value="' . (int) $cat->term_id . '"' . selected( $first_cat, $cat->term_id, false ) . '>' . esc_html( $cat->name ) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="pzv-pf-2col">
                        <div class="pzv-form-row">
                            <label>Normal Fiyat (₺) <span class="req">*</span></label>
                            <input type="number" id="pzv-ep-price" step="0.01" min="0" value="<?php echo esc_attr( $product->get_regular_price() ); ?>">
                        </div>
                        <div class="pzv-form-row">
                            <label>İndirimli Fiyat (₺)</label>
                            <input type="number" id="pzv-ep-sale-price" step="0.01" min="0" value="<?php echo esc_attr( $product->get_sale_price() ); ?>" placeholder="Boş bırakın">
                        </div>
                    </div>
                    <div class="pzv-pf-2col">
                        <div class="pzv-form-row">
                            <label>Stok Miktarı</label>
                            <input type="number" id="pzv-ep-stock" min="0" value="<?php echo esc_attr( (int) $product->get_stock_quantity() ); ?>">
                        </div>
                        <div class="pzv-form-row">
                            <label>SKU</label>
                            <input type="text" id="pzv-ep-sku" value="<?php echo esc_attr( $product->get_sku() ); ?>">
                        </div>
                    </div>
                    <div class="pzv-form-row">
                        <label>Kısa Açıklama</label>
                        <textarea id="pzv-ep-short-desc" rows="2"><?php echo esc_textarea( $product->get_short_description() ); ?></textarea>
                    </div>
                    <div class="pzv-form-row">
                        <label>Detaylı Açıklama</label>
                        <textarea id="pzv-ep-desc" rows="5"><?php echo esc_textarea( $product->get_description() ); ?></textarea>
                    </div>
                    <div class="pzv-form-row">
                        <label>Yayın Durumu</label>
                        <select id="pzv-ep-status">
                            <option value="publish" <?php selected( $product->get_status(), 'publish' ); ?>>✓ Yayında (onay gerekir)</option>
                            <option value="pending" <?php selected( $product->get_status(), 'pending' ); ?>>⏳ Onay Bekliyor</option>
                            <option value="draft"   <?php selected( $product->get_status(), 'draft' );   ?>>📝 Taslak</option>
                        </select>
                    </div>
                </div>
                <div class="pzv-pf-side">
                    <div class="pzv-pf-img-wrap">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:8px;">Öne Çıkan Görsel</label>
                        <div class="pzv-pf-image-box" id="pzv-ep-imgbox">
                            <div class="pzv-pf-img-placeholder" id="pzv-ep-placeholder"<?php echo $img_url ? ' style="display:none;"' : ''; ?>><span style="font-size:36px;">📷</span><span>Görsel seçin</span></div>
                            <img id="pzv-ep-img-preview" src="<?php echo esc_url( $img_url ); ?>" alt=""<?php echo ! $img_url ? ' style="display:none;"' : ' style="width:100%;height:100%;object-fit:cover;"'; ?>>
                        </div>
                        <input type="hidden" id="pzv-ep-img-id" value="<?php echo $img_id; ?>">
                        <div class="pzv-pf-img-btns">
                            <button type="button" class="button" id="pzv-ep-img-btn">📷 Değiştir</button>
                            <button type="button" class="button" id="pzv-ep-img-remove"<?php echo ! $img_id ? ' style="display:none;"' : ''; ?>>✕ Kaldır</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pzv-pf-actions">
                <button type="button" class="button button-primary pzv-pf-submit" id="pzv-ep-submit" data-form="edit">
                    <span class="pzv-btn-txt">💾 Değişiklikleri Kaydet</span>
                    <span class="pzv-btn-spin" style="display:none;">⏳ Kaydediliyor...</span>
                </button>
                <div class="pzv-form-msg" id="pzv-ep-msg"></div>
            </div>
        </div>
        <?php
    }

    /** AJAX: Yeni ürün oluştur */
    public static function ajax_new_product() {
        check_ajax_referer( 'pzv_nonce', 'nonce' );
        $uid = get_current_user_id();
        if ( ! PZV_Roles::is_vendor( $uid ) ) wp_send_json_error( array( 'message' => 'Yetki yok' ) );

        $title      = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $price      = floatval( $_POST['price'] ?? 0 );
        $sale_price = ( isset( $_POST['sale_price'] ) && $_POST['sale_price'] !== '' ) ? floatval( $_POST['sale_price'] ) : '';
        $stock      = (int) ( $_POST['stock'] ?? 0 );
        $sku        = sanitize_text_field( wp_unslash( $_POST['sku'] ?? '' ) );
        $desc       = wp_kses_post( wp_unslash( $_POST['desc'] ?? '' ) );
        $short_desc = wp_kses_post( wp_unslash( $_POST['short_desc'] ?? '' ) );
        $image_id   = (int) ( $_POST['image_id'] ?? 0 );
        $cat_id     = (int) ( $_POST['cat_id'] ?? 0 );
        $requested  = sanitize_key( $_POST['status'] ?? 'pending' );
        $status     = ( $requested === 'draft' ) ? 'draft' : 'pending';

        if ( ! $title ) wp_send_json_error( array( 'message' => 'Ürün adı gerekli.' ) );
        if ( $price <= 0 ) wp_send_json_error( array( 'message' => 'Geçerli bir fiyat girin.' ) );

        $product = new WC_Product_Simple();
        $product->set_name( $title );
        $product->set_status( $status );
        $product->set_description( $desc );
        $product->set_short_description( $short_desc );
        $product->set_regular_price( (string) $price );
        if ( $sale_price !== '' ) $product->set_sale_price( (string) $sale_price );
        $product->set_manage_stock( true );
        $product->set_stock_quantity( $stock );
        $product->set_stock_status( $stock > 0 ? 'instock' : 'outofstock' );
        if ( $sku ) $product->set_sku( $sku );
        if ( $image_id ) $product->set_image_id( $image_id );
        if ( $cat_id ) $product->set_category_ids( array( $cat_id ) );

        $pid = $product->save();
        if ( ! $pid ) wp_send_json_error( array( 'message' => 'Ürün oluşturulamadı.' ) );

        wp_update_post( array( 'ID' => $pid, 'post_author' => $uid ) );

        $saticim = get_page_by_path( 'saticim' );
        wp_send_json_success( array(
            'product_id'   => $pid,
            'message'      => $status === 'draft' ? 'Taslak kaydedildi.' : 'Onay için gönderildi. Yönetici onayından sonra yayınlanacak.',
            'went_pending' => ( $status === 'pending' ),
            'products_url' => $saticim ? add_query_arg( 'tab', 'products', get_permalink( $saticim ) ) : '',
        ) );
    }

    /** AJAX: Mevcut ürünü kaydet */
    public static function ajax_save_product_data() {
        check_ajax_referer( 'pzv_nonce', 'nonce' );
        $uid = get_current_user_id();
        if ( ! PZV_Roles::is_vendor( $uid ) ) wp_send_json_error( array( 'message' => 'Yetki yok' ) );

        $pid = (int) ( $_POST['product_id'] ?? 0 );
        if ( ! $pid ) wp_send_json_error( array( 'message' => 'Ürün ID eksik' ) );
        if ( (int) get_post_field( 'post_author', $pid ) !== $uid ) wp_send_json_error( array( 'message' => 'Bu ürün size ait değil' ) );

        $product = wc_get_product( $pid );
        if ( ! $product ) wp_send_json_error( array( 'message' => 'Ürün bulunamadı' ) );

        if ( ! empty( $_POST['title'] ) ) $product->set_name( sanitize_text_field( wp_unslash( $_POST['title'] ) ) );
        if ( isset( $_POST['price'] ) && is_numeric( $_POST['price'] ) ) $product->set_regular_price( (string) floatval( $_POST['price'] ) );
        if ( isset( $_POST['sale_price'] ) ) $product->set_sale_price( $_POST['sale_price'] !== '' ? (string) floatval( $_POST['sale_price'] ) : '' );
        if ( isset( $_POST['stock'] ) ) {
            $s = (int) $_POST['stock'];
            $product->set_manage_stock( true );
            $product->set_stock_quantity( $s );
            $product->set_stock_status( $s > 0 ? 'instock' : 'outofstock' );
        }
        if ( isset( $_POST['sku'] ) ) $product->set_sku( sanitize_text_field( wp_unslash( $_POST['sku'] ) ) );
        if ( isset( $_POST['desc'] ) ) $product->set_description( wp_kses_post( wp_unslash( $_POST['desc'] ) ) );
        if ( isset( $_POST['short_desc'] ) ) $product->set_short_description( wp_kses_post( wp_unslash( $_POST['short_desc'] ) ) );
        if ( isset( $_POST['image_id'] ) ) $product->set_image_id( (int) $_POST['image_id'] );
        if ( ! empty( $_POST['cat_id'] ) ) $product->set_category_ids( array( (int) $_POST['cat_id'] ) );

        $went_pending = false;
        $new_status   = sanitize_key( $_POST['status'] ?? '' );
        if ( $new_status ) {
            if ( $new_status === 'publish' ) { $product->set_status( 'pending' ); $went_pending = true; }
            elseif ( in_array( $new_status, array( 'draft', 'pending' ), true ) ) { $product->set_status( $new_status ); }
        }

        if ( ! defined( 'PZV_DOING_QUICK_SAVE' ) ) define( 'PZV_DOING_QUICK_SAVE', true );
        $product->save();

        wp_send_json_success( array(
            'message'      => $went_pending ? 'Onay için gönderildi.' : 'Güncellendi.',
            'went_pending' => $went_pending,
        ) );
    }

    /** AJAX: Profil kaydet (frontend dashboard) */
    public static function ajax_save_vendor_profile() {
        check_ajax_referer( 'pzv_nonce', 'nonce' );
        $uid = get_current_user_id();
        if ( ! PZV_Roles::is_vendor( $uid ) ) wp_send_json_error( array( 'message' => 'Yetki yok' ) );

        foreach ( array( 'store_name', 'phone', 'city', 'address', 'description', 'iban', 'tc_or_tax', 'working_hours' ) as $f ) {
            if ( isset( $_POST[ $f ] ) ) update_user_meta( $uid, 'pzv_' . $f, sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) );
        }
        foreach ( array( 'instagram_url', 'twitter_url' ) as $f ) {
            if ( isset( $_POST[ $f ] ) ) update_user_meta( $uid, 'pzv_' . $f, esc_url_raw( wp_unslash( $_POST[ $f ] ) ) );
        }
        if ( isset( $_POST['return_policy'] ) ) {
            update_user_meta( $uid, 'pzv_return_policy', sanitize_textarea_field( wp_unslash( $_POST['return_policy'] ) ) );
        }
        if ( ! empty( $_POST['store_slug'] ) ) update_user_meta( $uid, 'pzv_store_slug', sanitize_title( $_POST['store_slug'] ) );
        update_user_meta( $uid, 'pzv_logo',          (int) ( $_POST['logo']          ?? 0 ) );
        update_user_meta( $uid, 'pzv_banner',        (int) ( $_POST['banner']        ?? 0 ) );
        update_user_meta( $uid, 'pzv_dispatch_days', max( 0, min( 30, (int) ( $_POST['dispatch_days'] ?? 1 ) ) ) );

        wp_send_json_success( array( 'message' => 'Profil güncellendi.' ) );
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
