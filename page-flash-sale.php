<?php
/**
 * Template Name: Flash Satış
 * Template Post Type: page
 *
 * İndirimli ürünleri kategori sayfası görünümünde listeler.
 * Slug: flash-sale
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Sayfalama ──────────────────────────────────────────────────────
$paged     = max( 1, get_query_var( 'paged' ) );
$per_page  = (int) get_option( 'posts_per_page', 24 );
$orderby   = sanitize_text_field( $_GET['orderby'] ?? 'date' );

$sort_map = array(
    'date'            => array( 'orderby' => 'date',       'order' => 'DESC', 'meta_key' => '' ),
    'price'           => array( 'orderby' => 'meta_value_num', 'order' => 'ASC',  'meta_key' => '_price' ),
    'price-desc'      => array( 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => '_price' ),
    'popularity'      => array( 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => 'total_sales' ),
    'rating'          => array( 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => '_wc_average_rating' ),
    'discount'        => array( 'orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => '_pz_discount_pct' ),
);
$sort = $sort_map[ $orderby ] ?? $sort_map['date'];

// ── Kategori filtresi ──────────────────────────────────────────────
$cat_slug = sanitize_text_field( $_GET['cat'] ?? '' );
$tax_query = array();
if ( $cat_slug ) {
    $tax_query[] = array(
        'taxonomy' => 'product_cat',
        'field'    => 'slug',
        'terms'    => $cat_slug,
    );
}

// ── İndirimli ürün sorgusu ─────────────────────────────────────────
$args = array(
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'orderby'        => $sort['orderby'],
    'order'          => $sort['order'],
    'meta_query'     => array(
        array(
            'key'     => '_sale_price',
            'value'   => '',
            'compare' => '!=',
        ),
        array(
            'key'     => '_sale_price',
            'value'   => 0,
            'compare' => '>',
            'type'    => 'NUMERIC',
        ),
    ),
    'tax_query'      => $tax_query ?: array(),
);
if ( $sort['meta_key'] ) $args['meta_key'] = $sort['meta_key'];

$flash_query = new WP_Query( $args );
$total       = $flash_query->found_posts;
$max_pages   = $flash_query->max_num_pages;

// ── İndirimli ürünlerin bulunduğu kategoriler ──────────────────────
$sale_product_ids = wc_get_product_ids_on_sale();
$flash_cats = array();
if ( ! empty( $sale_product_ids ) ) {
    $flash_cats = get_terms( array(
        'taxonomy'   => 'product_cat',
        'object_ids' => $sale_product_ids,
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
        'number'     => 20,
        'parent'     => 0, // Sadece ana kategoriler
    ) );
    if ( is_wp_error( $flash_cats ) ) $flash_cats = array();
}

$base_url = home_url( '/flash-sale/' );

get_header();
?>

<!-- Breadcrumb -->
<div class="bc"><div class="bc-in">
  <a class="bc-a" href="<?php echo esc_url( home_url('/') ); ?>">Anasayfa</a><span class="bc-sep">›</span>
  <span style="color:var(--ink)">⚡ Flash Satış</span>
</div></div>

<div class="cw">
  <div class="shop-layout">

    <!-- ═══ SOL: FİLTRELER ═══ -->
    <aside class="shop-sidebar">
      <div class="shop-filter-head">
        <span>Filtreler</span>
        <?php if ( $cat_slug ) : ?>
          <a href="<?php echo esc_url( $base_url ); ?>" class="shop-filter-clear">Temizle</a>
        <?php endif; ?>
      </div>

      <!-- Kategoriler -->
      <?php if ( ! empty( $flash_cats ) ) : ?>
      <div class="shop-filter-box">
        <div class="shop-filter-title">Kategoriler</div>
        <ul class="shop-cat-list">
          <li <?php echo ! $cat_slug ? 'class="on"' : ''; ?>>
            <a href="<?php echo esc_url( $base_url ); ?>">
              <span>Tümü</span>
              <span class="shop-cat-count"><?php echo count( $sale_product_ids ); ?></span>
            </a>
          </li>
          <?php foreach ( $flash_cats as $fc ) :
            $on = ( $cat_slug === $fc->slug ) ? ' class="on"' : '';
            $cat_url = add_query_arg( 'cat', $fc->slug, $base_url );
          ?>
          <li<?php echo $on; ?>>
            <a href="<?php echo esc_url( $cat_url ); ?>">
              <span><?php echo esc_html( $fc->name ); ?></span>
              <span class="shop-cat-count"><?php echo (int) $fc->count; ?></span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- İndirim oranı filtresi -->
      <div class="shop-filter-box">
        <div class="shop-filter-title">İndirim Oranı</div>
        <ul class="shop-attr-list">
          <?php
          $discount_filters = array(
            '10'  => '%10 ve üzeri',
            '20'  => '%20 ve üzeri',
            '30'  => '%30 ve üzeri',
            '50'  => '%50 ve üzeri',
            '70'  => '%70 ve üzeri',
          );
          $active_discount = sanitize_text_field( $_GET['min_discount'] ?? '' );
          foreach ( $discount_filters as $pct => $label ) :
            $d_url = add_query_arg( array( 'min_discount' => $pct, 'cat' => $cat_slug ?: false ), $base_url );
            $on = ( $active_discount == $pct ) ? ' class="on"' : '';
          ?>
          <li<?php echo $on; ?>><a href="<?php echo esc_url( $d_url ); ?>"><span><?php echo esc_html( $label ); ?></span></a></li>
          <?php endforeach; ?>
        </ul>
      </div>

    </aside>

    <!-- ═══ SAĞ: ÜRÜN IZGARASI ═══ -->
    <main class="shop-main">

      <!-- Flash Satış başlık bandı -->
      <div class="pz-flash-banner">
        <span class="pz-flash-icon">⚡</span>
        <div class="pz-flash-text">
          <strong>Flash Satış</strong>
          <span>Sınırlı süre, özel fiyatlar</span>
        </div>
        <div class="pz-flash-count">
          <span><?php echo (int) $total; ?></span> ürün
        </div>
      </div>

      <!-- Kategori şeridi (mobil) -->
      <?php if ( ! empty( $flash_cats ) ) : ?>
      <div class="mob-cat-strip">
        <a class="mcs-item<?php echo ! $cat_slug ? ' on' : ''; ?>" href="<?php echo esc_url( $base_url ); ?>">
          <div class="mcs-img mcs-img-all"><span>⊞</span></div>
          <span class="mcs-name">Tümü</span>
        </a>
        <?php foreach ( $flash_cats as $fc ) :
          $thumb_id = get_term_meta( $fc->term_id, 'thumbnail_id', true );
          $img = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '';
        ?>
        <a class="mcs-item<?php echo ( $cat_slug === $fc->slug ) ? ' on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'cat', $fc->slug, $base_url ) ); ?>">
          <div class="mcs-img"><?php echo $img ? '<img src="' . esc_url( $img ) . '" alt="">' : '<span>🏷️</span>'; ?></div>
          <span class="mcs-name"><?php echo esc_html( $fc->name ); ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Üst bar: başlık + sıralama -->
      <div class="shop-topbar">
        <div class="shop-topbar-left">
          <h1 class="shop-title">
            ⚡ <?php echo $cat_slug ? esc_html( get_term_by( 'slug', $cat_slug, 'product_cat' )->name ?? 'Flash Satış' ) : 'Flash Satış'; ?>
          </h1>
          <span class="shop-count"><?php echo (int) $total; ?> ürün listeleniyor</span>
        </div>
        <div class="shop-topbar-right">
          <!-- Sıralama -->
          <select class="orderby" onchange="location.href=this.value">
            <?php
            $sort_opts = array(
              'date'       => 'En Yeni',
              'discount'   => 'En Yüksek İndirim',
              'price'      => 'Fiyat: Düşükten Yükseğe',
              'price-desc' => 'Fiyat: Yüksekten Düşüğe',
              'popularity' => 'En Çok Satan',
            );
            foreach ( $sort_opts as $val => $label ) :
              $url = add_query_arg( array( 'orderby' => $val, 'cat' => $cat_slug ?: false ), $base_url );
            ?>
            <option value="<?php echo esc_url( $url ); ?>" <?php selected( $orderby, $val ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Ürün ızgarası -->
      <?php if ( $flash_query->have_posts() ) : ?>
        <div class="pgrid shop-grid">
          <?php
          while ( $flash_query->have_posts() ) :
            $flash_query->the_post();
            global $product;
            // İndirim oranı filtresi
            if ( isset( $_GET['min_discount'] ) && $product->is_on_sale() ) {
                $reg   = (float) $product->get_regular_price();
                $sale  = (float) $product->get_price();
                $pct   = $reg > 0 ? round( ( $reg - $sale ) / $reg * 100 ) : 0;
                if ( $pct < (int) $_GET['min_discount'] ) continue;
            }
            echo bazario_product_card( $product );
          endwhile;
          wp_reset_postdata();
          ?>
        </div>

        <!-- Sayfalama -->
        <div class="shop-pagination">
          <?php
          echo paginate_links( array(
            'base'      => trailingslashit( $base_url ) . '%_%',
            'format'    => 'page/%#%',
            'total'     => $max_pages,
            'current'   => $paged,
            'prev_text' => '‹ Önceki',
            'next_text' => 'Sonraki ›',
            'add_args'  => array_filter( array(
                'cat'          => $cat_slug ?: false,
                'orderby'      => $orderby !== 'date' ? $orderby : false,
                'min_discount' => isset( $_GET['min_discount'] ) ? $_GET['min_discount'] : false,
            ) ),
          ) );
          ?>
        </div>

      <?php else : ?>
        <div class="shop-empty">
          <div class="shop-empty-ico">⚡</div>
          <div class="shop-empty-title">Şu an aktif flash satış yok</div>
          <div class="shop-empty-text">Yeni fırsatlar için takipte kalın!</div>
          <a href="<?php echo esc_url( wc_get_page_permalink('shop') ); ?>" class="shop-empty-btn">Tüm Ürünleri Gör</a>
        </div>
      <?php endif; ?>

    </main>
  </div>
</div>

<?php get_footer(); ?>
