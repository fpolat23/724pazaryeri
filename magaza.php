<?php
/**
 * PZV Mağaza Sayfası — /magaza/{slug}/
 * Dokan store.php ile aynı HTML yapısı, PZV verisi kullanır.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Vendor'ı slug'dan bul ──
$store_slug = sanitize_title( get_query_var( 'pzv_store' ) );
if ( ! $store_slug ) {
    global $wp_query;
    $wp_query->set_404();
    status_header( 404 );
    get_template_part( '404' );
    exit;
}

$vendor_users = get_users( array(
    'meta_key'   => 'pzv_store_slug',
    'meta_value' => $store_slug,
    'role'       => PZV_ROLE,
    'number'     => 1,
) );

// Slug eşleşmiyorsa kullanıcı adıyla dene
if ( empty( $vendor_users ) ) {
    $vendor_users = get_users( array(
        'login'  => $store_slug,
        'role'   => PZV_ROLE,
        'number' => 1,
    ) );
}

if ( empty( $vendor_users ) ) {
    global $wp_query;
    $wp_query->set_404();
    status_header( 404 );
    get_template_part( '404' );
    exit;
}

$store_id = $vendor_users[0]->ID;
$v        = PZV_Vendor::get( $store_id );
if ( ! $v ) {
    global $wp_query;
    $wp_query->set_404();
    status_header( 404 );
    get_template_part( '404' );
    exit;
}

// Pasif satıcı → 404
if ( get_user_meta( $store_id, 'pzv_status', true ) === 'inactive' ) {
    global $wp_query;
    $wp_query->set_404();
    status_header( 404 );
    get_template_part( '404' );
    exit;
}

// ── Mağaza verileri ──
$store_name    = $v['store_name'];
$store_base    = PZV_Vendor::store_url( $store_id );
$store_banner  = ! empty( $v['banner'] ) ? wp_get_attachment_image_url( $v['banner'], 'full' ) : '';
$store_avatar  = ! empty( $v['logo'] )   ? wp_get_attachment_image_url( $v['logo'],   'medium' ) : '';
$store_address = trim( implode( ', ', array_filter( array( $v['city'], $v['address'] ) ) ) );
$store_phone   = $v['phone'];
$store_desc    = $v['description'];

// ── Filtre parametreleri ──
// Filtre varsa ?pg=N, yoksa /page/N/ rewrite kullan
$has_filter_qs_early = isset( $_GET['store_cat'] ) || isset( $_GET['store_brand'] )
    || ( isset( $_GET['min_price'] ) && $_GET['min_price'] !== '' )
    || ( isset( $_GET['max_price'] ) && $_GET['max_price'] !== '' );
$paged   = $has_filter_qs_early
    ? max( 1, (int) ( $_GET['pg'] ?? 1 ) )
    : max( 1, (int) get_query_var( 'paged' ) );
$f_cat   = isset( $_GET['store_cat'] )   ? absint( $_GET['store_cat'] )   : 0;
$f_brand = isset( $_GET['store_brand'] ) ? absint( $_GET['store_brand'] ) : 0;
$f_min   = isset( $_GET['min_price'] ) && $_GET['min_price'] !== '' ? floatval( $_GET['min_price'] ) : null;
$f_max   = isset( $_GET['max_price'] ) && $_GET['max_price'] !== '' ? floatval( $_GET['max_price'] ) : null;

// ── Tüm sorgular doğrudan DB — Dokan / diğer plugin hook'larını tamamen atlatır ──
global $wpdb;

// Filtre sidebar: bu satıcının kategorileri ve markaları
$store_cats = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
    "SELECT DISTINCT tt.term_id
     FROM {$wpdb->term_relationships} tr
     INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
     INNER JOIN {$wpdb->posts} p           ON tr.object_id        = p.ID
     WHERE p.post_type = 'product' AND p.post_status = 'publish'
       AND p.post_author = %d AND tt.taxonomy = 'product_cat'",
    $store_id
) ) );

$store_brands = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
    "SELECT DISTINCT tt.term_id
     FROM {$wpdb->term_relationships} tr
     INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
     INNER JOIN {$wpdb->posts} p           ON tr.object_id        = p.ID
     WHERE p.post_type = 'product' AND p.post_status = 'publish'
       AND p.post_author = %d AND tt.taxonomy = 'product_brand'",
    $store_id
) ) );

// Filtreli sorgu — JOIN ile, WP_Query author parametresine bağımlılık yok
$sql_joins      = '';
$sql_conditions = $wpdb->prepare(
    "p.post_type = 'product' AND p.post_status = 'publish' AND p.post_author = %d",
    $store_id
);

if ( $f_cat ) {
    $sql_joins      .= " INNER JOIN {$wpdb->term_relationships} tr_c"
                     . "   ON tr_c.object_id = p.ID"
                     . " INNER JOIN {$wpdb->term_taxonomy} tt_c"
                     . "   ON tr_c.term_taxonomy_id = tt_c.term_taxonomy_id"
                     . "  AND tt_c.taxonomy = 'product_cat'";
    $sql_conditions .= $wpdb->prepare( ' AND tt_c.term_id = %d', $f_cat );
}
if ( $f_brand ) {
    $sql_joins      .= " INNER JOIN {$wpdb->term_relationships} tr_b"
                     . "   ON tr_b.object_id = p.ID"
                     . " INNER JOIN {$wpdb->term_taxonomy} tt_b"
                     . "   ON tr_b.term_taxonomy_id = tt_b.term_taxonomy_id"
                     . "  AND tt_b.taxonomy = 'product_brand'";
    $sql_conditions .= $wpdb->prepare( ' AND tt_b.term_id = %d', $f_brand );
}
if ( $f_min !== null || $f_max !== null ) {
    $sql_joins .= " INNER JOIN {$wpdb->postmeta} pm_pr"
                . "   ON pm_pr.post_id = p.ID AND pm_pr.meta_key = '_price'";
    if ( $f_min !== null && $f_max !== null ) {
        $sql_conditions .= $wpdb->prepare(
            ' AND CAST(pm_pr.meta_value AS DECIMAL(10,2)) BETWEEN %f AND %f',
            $f_min, $f_max
        );
    } elseif ( $f_min !== null ) {
        $sql_conditions .= $wpdb->prepare( ' AND CAST(pm_pr.meta_value AS DECIMAL(10,2)) >= %f', $f_min );
    } else {
        $sql_conditions .= $wpdb->prepare( ' AND CAST(pm_pr.meta_value AS DECIMAL(10,2)) <= %f', $f_max );
    }
}

$total_products = (int) $wpdb->get_var(
    "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p{$sql_joins} WHERE {$sql_conditions}"
);
$max_num_pages  = $total_products > 0 ? (int) ceil( $total_products / 24 ) : 1;
$sql_offset     = ( $paged - 1 ) * 24;
$page_ids       = array_map( 'intval', $wpdb->get_col(
    "SELECT DISTINCT p.ID FROM {$wpdb->posts} p{$sql_joins} WHERE {$sql_conditions}"
    . " ORDER BY p.post_date DESC LIMIT 24 OFFSET {$sql_offset}"
) );

// WP_Query sadece bu sayfadaki 24 ID için (post__in kullanır, author filtresi yok)
$store_products = new WP_Query( empty( $page_ids )
    ? array( 'post_type' => 'product', 'post__in' => array( 0 ), 'posts_per_page' => 1, 'no_found_rows' => true )
    : array(
        'post_type'      => 'product',
        'post__in'       => $page_ids,
        'posts_per_page' => count( $page_ids ),
        'orderby'        => 'post__in',
        'no_found_rows'  => true,
    )
);

get_header();
?>

<!-- Breadcrumb -->
<div class="bc"><div class="bc-in">
  <a class="bc-a" href="<?php echo esc_url( home_url('/') ); ?>">Anasayfa</a><span class="bc-sep">›</span>
  <span style="color:var(--ink)"><?php echo esc_html( $store_name ); ?></span>
</div></div>

<div class="pz-store">

  <!-- ── Mağaza başlık ── -->
  <div class="pz-store-header">
    <?php if ( $store_banner ) : ?>
      <div class="pz-store-banner">
        <img class="pz-store-banner-img" src="<?php echo esc_url( $store_banner ); ?>" alt="<?php echo esc_attr( $store_name ); ?>">
      </div>
    <?php else : ?>
      <div class="pz-store-banner pz-store-banner-default"></div>
    <?php endif; ?>

    <div class="pz-store-info">
      <div class="pz-store-avatar<?php echo $store_avatar ? '' : ' pz-store-avatar-letter'; ?>">
        <?php if ( $store_avatar ) : ?>
          <img src="<?php echo esc_url( $store_avatar ); ?>" alt="<?php echo esc_attr( $store_name ); ?>">
        <?php else : ?>
          <span><?php echo esc_html( mb_strtoupper( mb_substr( $store_name, 0, 1 ) ) ); ?></span>
        <?php endif; ?>
      </div>
      <div class="pz-store-meta">
        <h1 class="pz-store-name"><?php echo esc_html( $store_name ); ?> <span class="pz-store-verified">✓</span></h1>
        <div class="pz-store-sub">
          <span class="pz-store-rating">★ Onaylı Satıcı</span>
          <?php if ( $store_address ) : ?>
            <span class="pz-store-loc">📍 <?php echo esc_html( $store_address ); ?></span>
          <?php endif; ?>
          <?php if ( $store_phone ) : ?>
            <span class="pz-store-loc">📞 <?php echo esc_html( $store_phone ); ?></span>
          <?php endif; ?>
        </div>
        <?php if ( $store_desc ) : ?>
          <p class="pz-store-desc"><?php echo esc_html( $store_desc ); ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Layout: sol filtre + sağ ürünler ── -->
  <div class="shop-layout pz-store-layout">

    <!-- SOL FİLTRELER -->
    <aside class="shop-sidebar">
      <div class="shop-filter-head">
        <span>Filtreler</span>
        <a href="<?php echo esc_url( $store_base ); ?>" class="shop-filter-clear">Temizle</a>
      </div>

      <?php
        // ── Hiyerarşik kategori ağacı ──
        $all_cat_objs = ! empty( $store_cats )
            ? get_terms( array( 'taxonomy' => 'product_cat', 'include' => $store_cats,
                                'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) )
            : array();

        $cat_map      = array(); // term_id => WP_Term
        $cat_children = array(); // parent_id => [term_id, ...]

        if ( ! empty( $all_cat_objs ) && ! is_wp_error( $all_cat_objs ) ) {
            foreach ( $all_cat_objs as $_ct ) {
                if ( $_ct->slug === 'uncategorized' ) continue;
                $cat_map[ $_ct->term_id ] = $_ct;
            }
            foreach ( $cat_map as $_tid => $_term ) {
                $cat_children[ (int) $_term->parent ][] = $_tid;
            }
        }

        // Kök kategoriler: üst kategorisi satıcının kategorileri arasında olmayan
        $cat_roots = array();
        foreach ( $cat_map as $_tid => $_term ) {
            if ( ! isset( $cat_map[ (int) $_term->parent ] ) ) $cat_roots[] = $_tid;
        }
        usort( $cat_roots, function( $a, $b ) use ( $cat_map ) {
            return strcmp( $cat_map[$a]->name, $cat_map[$b]->name );
        } );

        // Aktif kategorinin ata yolunu bul (otomatik aç)
        $cat_ancestors = array();
        if ( $f_cat && isset( $cat_map[$f_cat] ) ) {
            $_pid = (int) $cat_map[$f_cat]->parent;
            while ( $_pid && isset( $cat_map[$_pid] ) ) {
                $cat_ancestors[] = $_pid;
                $_pid = (int) $cat_map[$_pid]->parent;
            }
        }

        // Özyinelemeli render
        if ( ! function_exists( 'pzv_magaza_cat_tree' ) ) {
            function pzv_magaza_cat_tree( $ids, $cat_map, $cat_children, $f_cat, $store_base, $cat_ancestors, $depth = 0 ) {
                foreach ( $ids as $_tid ) {
                    if ( ! isset( $cat_map[$_tid] ) ) continue;
                    $_term   = $cat_map[$_tid];
                    $_hasCh  = isset( $cat_children[$_tid] );
                    $_isOn   = ( $f_cat === (int)$_tid );
                    $_open   = ( $_isOn || in_array( $_tid, $cat_ancestors, true ) );
                    $_pad    = ( $depth * 14 + 4 );
                    $_cls    = 'pzv-cat-li' . ( $_isOn ? ' pzv-cat-on' : '' ) . ( $_open && $_hasCh ? ' pzv-cat-open' : '' );
                    ?>
                    <li class="<?php echo $_cls; ?>">
                      <?php if ( $_hasCh ) : ?>
                        <div class="pzv-cat-row">
                          <a href="<?php echo esc_url( add_query_arg( 'store_cat', $_tid, $store_base ) ); ?>"
                             class="pzv-cat-lnk" style="padding-left:<?php echo $_pad; ?>px">
                            <?php echo esc_html( $_term->name ); ?>
                          </a>
                          <button class="pzv-cat-tog" type="button" onclick="pzvCatToggle(this)" aria-label="Aç/Kapat">
                            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                          </button>
                        </div>
                        <ul class="pzv-cat-sub"<?php echo $_open ? '' : ' style="display:none"'; ?>>
                          <li class="pzv-cat-li">
                            <a href="<?php echo esc_url( add_query_arg( 'store_cat', $_tid, $store_base ) ); ?>"
                               class="pzv-cat-lnk pzv-cat-showall" style="padding-left:<?php echo ($_pad+14); ?>px">
                              Tümünü Göster
                            </a>
                          </li>
                          <?php pzv_magaza_cat_tree( $cat_children[$_tid], $cat_map, $cat_children, $f_cat, $store_base, $cat_ancestors, $depth + 1 ); ?>
                        </ul>
                      <?php else : ?>
                        <a href="<?php echo esc_url( add_query_arg( 'store_cat', $_tid, $store_base ) ); ?>"
                           class="pzv-cat-lnk" style="padding-left:<?php echo $_pad; ?>px">
                          <?php echo esc_html( $_term->name ); ?>
                        </a>
                      <?php endif; ?>
                    </li>
                    <?php
                }
            }
        }

        if ( ! empty( $cat_roots ) ) :
      ?>
      <div class="shop-filter-box">
        <div class="shop-filter-title">Kategoriler</div>
        <ul class="pzv-cat-tree">
          <?php pzv_magaza_cat_tree( $cat_roots, $cat_map, $cat_children, $f_cat, $store_base, $cat_ancestors ); ?>
        </ul>
      </div>
      <?php endif; ?>

      <div class="shop-filter-box">
        <div class="shop-filter-title">Fiyat Aralığı</div>
        <form method="get" action="<?php echo esc_url( $store_base ); ?>" class="shop-price-form">
          <?php if ( $f_cat )   : ?><input type="hidden" name="store_cat"   value="<?php echo $f_cat; ?>"><?php endif; ?>
          <?php if ( $f_brand ) : ?><input type="hidden" name="store_brand" value="<?php echo $f_brand; ?>"><?php endif; ?>
          <div class="shop-price-row">
            <input type="number" name="min_price" placeholder="En az"  value="<?php echo $f_min !== null ? esc_attr( $f_min ) : ''; ?>">
            <span>—</span>
            <input type="number" name="max_price" placeholder="En çok" value="<?php echo $f_max !== null ? esc_attr( $f_max ) : ''; ?>">
          </div>
          <button type="submit" class="shop-price-btn">Uygula</button>
        </form>
      </div>

      <?php
        $brand_terms = ! empty( $store_brands )
            ? get_terms( array( 'taxonomy' => 'product_brand', 'include' => $store_brands, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) )
            : array();
        if ( ! empty( $brand_terms ) && ! is_wp_error( $brand_terms ) ) :
      ?>
      <div class="shop-filter-box">
        <div class="shop-filter-title">Markalar</div>
        <ul class="shop-cat-list">
          <?php foreach ( $brand_terms as $bt ) :
            $on = ( $f_brand === $bt->term_id ) ? ' class="on"' : ''; ?>
            <li<?php echo $on; ?>>
              <a href="<?php echo esc_url( add_query_arg( 'store_brand', $bt->term_id, $store_base ) ); ?>">
                <span><?php echo esc_html( $bt->name ); ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </aside>

    <!-- SAĞ ÜRÜNLER -->
    <div class="shop-main">
      <div class="pz-store-products-head">
        <h2 class="pz-store-products-title">Mağaza Ürünleri</h2>
        <span class="pz-store-count"><?php echo intval( $total_products ); ?> ürün</span>
      </div>

      <?php if ( $store_products->have_posts() ) : ?>
        <div class="pgrid pz-store-grid">
          <?php
            while ( $store_products->have_posts() ) : $store_products->the_post();
              global $product;
              if ( $product ) echo bazario_product_card( $product );
            endwhile;
          ?>
        </div>
        <div class="shop-pagination">
          <?php
            // Filtre parametreleri varsa query string olarak ekle, yoksa temiz /page/N/ URL kullan
            $has_filter_qs = ( $f_cat || $f_brand || $f_min !== null || $f_max !== null );
            if ( $has_filter_qs ) {
                // Query string modunda sayfalama
                $pg_base = add_query_arg( array_filter( array(
                    'store_cat'   => $f_cat   ?: null,
                    'store_brand' => $f_brand ?: null,
                    'min_price'   => $f_min !== null ? $f_min : null,
                    'max_price'   => $f_max !== null ? $f_max : null,
                    'pg'          => '%#%',
                ) ), $store_base );
                echo paginate_links( array(
                    'base'      => $pg_base,
                    'format'    => '',
                    'total'     => $max_num_pages,
                    'current'   => isset( $_GET['pg'] ) ? max( 1, (int) $_GET['pg'] ) : $paged,
                    'prev_text' => '‹ Önceki',
                    'next_text' => 'Sonraki ›',
                ) );
            } else {
                // Temiz /page/N/ URL (rewrite rule ile çalışır)
                echo paginate_links( array(
                    'base'      => trailingslashit( $store_base ) . '%_%',
                    'format'    => 'page/%#%/',
                    'total'     => $max_num_pages,
                    'current'   => $paged,
                    'prev_text' => '‹ Önceki',
                    'next_text' => 'Sonraki ›',
                ) );
            }
          ?>
        </div>
        <?php wp_reset_postdata(); ?>
      <?php else : ?>
        <div class="pz-store-empty">
          <div class="pz-store-empty-ico">🛍️</div>
          <div class="pz-store-empty-title">Ürün bulunamadı</div>
          <div class="pz-store-empty-text">Bu filtrelere uygun ürün yok. Filtreleri temizleyip tekrar deneyin.</div>
        </div>
      <?php endif; ?>
    </div>

  </div>

</div>

<script>
function pzvCatToggle(btn){
  var li = btn.closest('.pzv-cat-li');
  var ul = li ? li.querySelector(':scope > .pzv-cat-sub') : null;
  if (!ul) return;
  var isOpen = li.classList.contains('pzv-cat-open');
  if (isOpen) {
    ul.style.display = 'none';
    li.classList.remove('pzv-cat-open');
  } else {
    ul.style.display = '';
    li.classList.add('pzv-cat-open');
  }
}
</script>
<?php get_footer(); ?>
