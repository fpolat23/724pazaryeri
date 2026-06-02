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

// ── Tüm ürün ID'leri (filtre menüsü için) ──
$all_q = new WP_Query( array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'author'         => $store_id,
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'no_found_rows'  => true,
) );
$all_store_ids = $all_q->posts;
wp_reset_postdata();

// ── Filtreli ürün sorgusu ──
$args = array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'author'         => $store_id,
    'posts_per_page' => 24,
    'paged'          => $paged,
    'orderby'        => 'date',
    'order'          => 'DESC',
);
$tax_q = array();
if ( $f_cat )   $tax_q[] = array( 'taxonomy' => 'product_cat',   'field' => 'term_id', 'terms' => $f_cat );
if ( $f_brand ) $tax_q[] = array( 'taxonomy' => 'product_brand', 'field' => 'term_id', 'terms' => $f_brand );
if ( ! empty( $tax_q ) ) { $tax_q['relation'] = 'AND'; $args['tax_query'] = $tax_q; }
if ( $f_min !== null || $f_max !== null ) {
    $pq = array( 'key' => '_price', 'type' => 'NUMERIC' );
    if ( $f_min !== null && $f_max !== null ) { $pq['value'] = array( $f_min, $f_max ); $pq['compare'] = 'BETWEEN'; }
    elseif ( $f_min !== null ) { $pq['value'] = $f_min; $pq['compare'] = '>='; }
    else { $pq['value'] = $f_max; $pq['compare'] = '<='; }
    $args['meta_query'] = array( $pq );
}
$store_products = new WP_Query( $args );

// ── Kategoriler ve markalar ──
$store_cats   = function_exists( 'pazaryeri_terms_for_products' ) ? pazaryeri_terms_for_products( $all_store_ids, 'product_cat' )   : array();
$store_brands = function_exists( 'pazaryeri_terms_for_products' ) ? pazaryeri_terms_for_products( $all_store_ids, 'product_brand' ) : array();

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
        $cat_terms = ! empty( $store_cats )
            ? get_terms( array( 'taxonomy' => 'product_cat', 'include' => $store_cats, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) )
            : array();
        if ( ! empty( $cat_terms ) && ! is_wp_error( $cat_terms ) ) {
            $cat_terms = array_filter( $cat_terms, function ( $t ) { return (int) $t->parent === 0; } );
        }
        if ( ! empty( $cat_terms ) && ! is_wp_error( $cat_terms ) ) :
      ?>
      <div class="shop-filter-box">
        <div class="shop-filter-title">Kategoriler</div>
        <ul class="shop-cat-list">
          <?php foreach ( $cat_terms as $ct ) :
            if ( $ct->slug === 'uncategorized' ) continue;
            $on = ( $f_cat === $ct->term_id ) ? ' class="on"' : ''; ?>
            <li<?php echo $on; ?>>
              <a href="<?php echo esc_url( add_query_arg( 'store_cat', $ct->term_id, $store_base ) ); ?>">
                <span><?php echo esc_html( $ct->name ); ?></span>
              </a>
            </li>
          <?php endforeach; ?>
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
        <span class="pz-store-count"><?php echo intval( $store_products->found_posts ); ?> ürün</span>
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
                    'total'     => $store_products->max_num_pages,
                    'current'   => isset( $_GET['pg'] ) ? max( 1, (int) $_GET['pg'] ) : $paged,
                    'prev_text' => '‹ Önceki',
                    'next_text' => 'Sonraki ›',
                ) );
            } else {
                // Temiz /page/N/ URL (rewrite rule ile çalışır)
                echo paginate_links( array(
                    'base'      => trailingslashit( $store_base ) . '%_%',
                    'format'    => 'page/%#%/',
                    'total'     => $store_products->max_num_pages,
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

<?php get_footer(); ?>
