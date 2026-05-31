<?php
/**
 * Dokan Mağaza Sayfası — 724PazarYeri özel şablonu
 * Ürünleri site ürün kartıyla, site tasarımıyla gösterir.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

$store_user  = dokan()->vendor->get( get_query_var( 'author' ) );
$store_info  = $store_user->get_shop_info();
$store_id    = $store_user->get_id();
$store_name  = $store_user->get_shop_name();
$store_banner = $store_user->get_banner();
$store_avatar = $store_user->get_avatar();
$store_rating = method_exists($store_user,'get_rating') ? $store_user->get_rating() : array('rating'=>0,'count'=>0);
$store_phone  = isset($store_info['phone']) ? $store_info['phone'] : '';
$store_address = function_exists('dokan_get_seller_address') ? dokan_get_seller_address( $store_id ) : '';
?>

<!-- Breadcrumb -->
<div class="bc"><div class="bc-in">
  <a class="bc-a" href="<?php echo esc_url( home_url('/') ); ?>">Anasayfa</a><span class="bc-sep">›</span>
  <span style="color:var(--ink)"><?php echo esc_html( $store_name ); ?></span>
</div></div>

<div class="pz-store">

  <!-- Mağaza üst başlık -->
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
          <?php if ( ! empty( $store_rating['rating'] ) ) : ?>
            <span class="pz-store-rating">★ <?php echo esc_html( number_format( (float) $store_rating['rating'], 1 ) ); ?></span>
            <span class="pz-store-rating-count">(<?php echo intval( $store_rating['count'] ); ?> değerlendirme)</span>
          <?php else : ?>
            <span class="pz-store-rating">★ Yeni Mağaza</span>
          <?php endif; ?>
          <?php if ( $store_address ) : ?>
            <span class="pz-store-loc">📍 <?php echo esc_html( wp_strip_all_tags( $store_address ) ); ?></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Mağaza ürünleri: SOL FİLTRE + ÜRÜN GRID -->
  <?php
    $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
    $store_base = get_permalink() ? get_permalink() : home_url( '/store/' );
    if ( function_exists('dokan_get_store_url') ) $store_base = dokan_get_store_url( $store_id );

    // Filtre parametreleri
    $f_cat   = isset($_GET['store_cat']) ? absint($_GET['store_cat']) : 0;
    $f_min   = isset($_GET['min_price']) && $_GET['min_price']!=='' ? floatval($_GET['min_price']) : null;
    $f_max   = isset($_GET['max_price']) && $_GET['max_price']!=='' ? floatval($_GET['max_price']) : null;
    $f_brand = isset($_GET['store_brand']) ? absint($_GET['store_brand']) : 0;

    // Mağazanın TÜM ürün ID'leri (filtre seçenekleri için)
    $all_store_ids_q = new WP_Query( array(
      'post_type'=>'product','post_status'=>'publish','posts_per_page'=>-1,
      'fields'=>'ids','author'=>$store_id,'no_found_rows'=>true,
    ) );
    $all_store_ids = $all_store_ids_q->have_posts() ? $all_store_ids_q->posts : array();
    wp_reset_postdata();

    // Ürün sorgusu (filtreli)
    $args = array(
      'post_type'=>'product','post_status'=>'publish','posts_per_page'=>24,
      'paged'=>$paged,'author'=>$store_id,'orderby'=>'date','order'=>'DESC',
    );
    $tax_q = array();
    if ( $f_cat ) $tax_q[] = array('taxonomy'=>'product_cat','field'=>'term_id','terms'=>$f_cat);
    if ( $f_brand ) $tax_q[] = array('taxonomy'=>'product_brand','field'=>'term_id','terms'=>$f_brand);
    if ( ! empty($tax_q) ) { $tax_q['relation']='AND'; $args['tax_query']=$tax_q; }
    if ( $f_min !== null || $f_max !== null ) {
      $pq = array('key'=>'_price','type'=>'NUMERIC');
      if ($f_min!==null && $f_max!==null){ $pq['value']=array($f_min,$f_max); $pq['compare']='BETWEEN'; }
      elseif ($f_min!==null){ $pq['value']=$f_min; $pq['compare']='>='; }
      else { $pq['value']=$f_max; $pq['compare']='<='; }
      $args['meta_query']=array($pq);
    }
    $store_products = new WP_Query( $args );

    // Mağaza kategorileri ve markaları (tek SQL ile)
    $store_cats = function_exists('pazaryeri_terms_for_products') ? pazaryeri_terms_for_products($all_store_ids,'product_cat') : array();
    $store_brands = function_exists('pazaryeri_terms_for_products') ? pazaryeri_terms_for_products($all_store_ids,'product_brand') : array();
  ?>
  <div class="shop-layout pz-store-layout">

    <!-- SOL FİLTRELER -->
    <aside class="shop-sidebar">
      <div class="shop-filter-head">
        <span>Filtreler</span>
        <a href="<?php echo esc_url( $store_base ); ?>" class="shop-filter-clear">Temizle</a>
      </div>

      <!-- Kategoriler -->
      <?php
        // Sadece ANA kategoriler (parent=0) — alt kategorileri gösterme
        $cat_terms = ! empty($store_cats) ? get_terms(array('taxonomy'=>'product_cat','include'=>$store_cats,'hide_empty'=>false,'orderby'=>'name','order'=>'ASC')) : array();
        if ( ! empty($cat_terms) && ! is_wp_error($cat_terms) ) {
          // Alt kategorileri filtrele: sadece üst kategorisi olmayanlar (parent=0) kalsın
          $cat_terms = array_filter( $cat_terms, function( $t ){ return (int) $t->parent === 0; } );
        }
        if ( ! empty($cat_terms) && ! is_wp_error($cat_terms) ) :
      ?>
      <div class="shop-filter-box">
        <div class="shop-filter-title">Kategoriler</div>
        <ul class="shop-cat-list">
          <?php foreach ( $cat_terms as $ct ) :
            if ( $ct->slug === 'uncategorized' ) continue;
            $on = ( $f_cat === $ct->term_id ) ? ' class="on"' : ''; ?>
            <li<?php echo $on; ?>>
              <a href="<?php echo esc_url( add_query_arg('store_cat',$ct->term_id,$store_base) ); ?>">
                <span><?php echo esc_html($ct->name); ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- Fiyat Aralığı -->
      <div class="shop-filter-box">
        <div class="shop-filter-title">Fiyat Aralığı</div>
        <form method="get" action="<?php echo esc_url( $store_base ); ?>" class="shop-price-form">
          <?php if ($f_cat): ?><input type="hidden" name="store_cat" value="<?php echo $f_cat; ?>"><?php endif; ?>
          <?php if ($f_brand): ?><input type="hidden" name="store_brand" value="<?php echo $f_brand; ?>"><?php endif; ?>
          <div class="shop-price-row">
            <input type="number" name="min_price" placeholder="En az" value="<?php echo $f_min!==null?esc_attr($f_min):''; ?>">
            <span>—</span>
            <input type="number" name="max_price" placeholder="En çok" value="<?php echo $f_max!==null?esc_attr($f_max):''; ?>">
          </div>
          <button type="submit" class="shop-price-btn">Uygula</button>
        </form>
      </div>

      <!-- Markalar -->
      <?php
        $brand_terms = ! empty($store_brands) ? get_terms(array('taxonomy'=>'product_brand','include'=>$store_brands,'hide_empty'=>false,'orderby'=>'name','order'=>'ASC')) : array();
        if ( ! empty($brand_terms) && ! is_wp_error($brand_terms) ) :
      ?>
      <div class="shop-filter-box">
        <div class="shop-filter-title">Markalar</div>
        <ul class="shop-cat-list">
          <?php foreach ( $brand_terms as $bt ) :
            $on = ( $f_brand === $bt->term_id ) ? ' class="on"' : ''; ?>
            <li<?php echo $on; ?>>
              <a href="<?php echo esc_url( add_query_arg('store_brand',$bt->term_id,$store_base) ); ?>">
                <span><?php echo esc_html($bt->name); ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </aside>

    <!-- SAĞ: ÜRÜNLER -->
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
            echo paginate_links( array(
              'total'     => $store_products->max_num_pages,
              'current'   => $paged,
              'prev_text' => '‹ Önceki',
              'next_text' => 'Sonraki ›',
            ) );
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
