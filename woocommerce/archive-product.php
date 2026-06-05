<?php
/**
 * Ürün Arşivi (Shop + Kategori) — Hepsiburada tarzı
 * Solda filtreler, sağda ürün ızgarası.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

// Mevcut kategori / başlık
$current_term = is_tax() ? get_queried_object() : null;
$page_title   = $current_term ? $current_term->name : ( is_search() ? 'Arama Sonuçları' : 'Tüm Ürünler' );
$base_url     = $current_term ? get_term_link( $current_term ) : wc_get_page_permalink( 'shop' );
if ( is_wp_error( $base_url ) ) $base_url = wc_get_page_permalink( 'shop' );

// Breadcrumb için kategori zinciri
?>
<div class="bc"><div class="bc-in">
  <a class="bc-a" href="<?php echo esc_url( home_url('/') ); ?>">Anasayfa</a><span class="bc-sep">›</span>
  <?php
    if ( $current_term ) {
      $ancestors = array_reverse( get_ancestors( $current_term->term_id, 'product_cat' ) );
      foreach ( $ancestors as $anc_id ) {
        $anc = get_term( $anc_id, 'product_cat' );
        if ( $anc && ! is_wp_error( $anc ) ) {
          echo '<a class="bc-a" href="' . esc_url( get_term_link( $anc ) ) . '">' . esc_html( $anc->name ) . '</a><span class="bc-sep">›</span>';
        }
      }
    }
  ?>
  <span style="color:var(--ink)"><?php echo esc_html( $page_title ); ?></span>
</div></div>

<div class="cw">
  <div class="shop-layout">

    <!-- ═══ SOL: FİLTRELER ═══ -->
    <aside class="shop-sidebar">
      <div class="shop-filter-head">
        <span>Filtreler</span>
        <a href="<?php echo esc_url( $base_url ); ?>" class="shop-filter-clear">Temizle</a>
      </div>

      <!-- Kategoriler -->
      <?php
        // Mevcut kategorinin alt kategorileri, yoksa ana kategoriler
        $parent_id = $current_term ? $current_term->term_id : 0;
        $subcats = get_terms( array(
          'taxonomy'   => 'product_cat',
          'parent'     => $parent_id,
          'hide_empty' => true,
          'orderby'    => 'name',
          'order'      => 'ASC',
        ) );
        // Alt kategori yoksa ve bir kategorideysek, kardeş kategorileri göster
        if ( ( empty( $subcats ) || is_wp_error( $subcats ) ) && $current_term ) {
          $subcats = get_terms( array(
            'taxonomy'   => 'product_cat',
            'parent'     => $current_term->parent,
            'hide_empty' => true,
            'orderby'    => 'name',
            'order'      => 'ASC',
          ) );
        }
        if ( ! empty( $subcats ) && ! is_wp_error( $subcats ) ) :
      ?>
      <div class="shop-filter-box">
        <div class="shop-filter-title">Kategoriler</div>
        <ul class="shop-cat-list">
          <?php foreach ( $subcats as $sc ) :
            if ( $sc->slug === 'uncategorized' ) continue;
            $active = ( $current_term && $sc->term_id === $current_term->term_id ) ? ' class="on"' : ''; ?>
            <li<?php echo $active; ?>>
              <a href="<?php echo esc_url( get_term_link( $sc ) ); ?>">
                <span><?php echo esc_html( $sc->name ); ?></span>
                <span class="shop-cat-count"><?php echo esc_html( $sc->count ); ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- Fiyat aralığı -->
      <div class="shop-filter-box">
        <div class="shop-filter-title">Fiyat Aralığı</div>
        <form method="get" class="shop-price-form" action="">
          <?php
            // Mevcut min/max korunsun
            $cur_min = isset( $_GET['min_price'] ) ? esc_attr( $_GET['min_price'] ) : '';
            $cur_max = isset( $_GET['max_price'] ) ? esc_attr( $_GET['max_price'] ) : '';
          ?>
          <div class="shop-price-inputs">
            <input type="number" name="min_price" placeholder="En az" value="<?php echo $cur_min; ?>" min="0">
            <span>—</span>
            <input type="number" name="max_price" placeholder="En çok" value="<?php echo $cur_max; ?>" min="0">
          </div>
          <?php if ( isset( $_GET['orderby'] ) ) : ?><input type="hidden" name="orderby" value="<?php echo esc_attr( $_GET['orderby'] ); ?>"><?php endif; ?>
          <button type="submit" class="shop-price-btn">Uygula</button>
        </form>
        <!-- Hızlı fiyat aralıkları -->
        <ul class="shop-price-quick">
          <li><a href="<?php echo esc_url( add_query_arg( array('min_price'=>0,'max_price'=>500), $base_url ) ); ?>">0 - 500 ₺</a></li>
          <li><a href="<?php echo esc_url( add_query_arg( array('min_price'=>500,'max_price'=>1500), $base_url ) ); ?>">500 - 1.500 ₺</a></li>
          <li><a href="<?php echo esc_url( add_query_arg( array('min_price'=>1500,'max_price'=>5000), $base_url ) ); ?>">1.500 - 5.000 ₺</a></li>
          <li><a href="<?php echo esc_url( add_query_arg( array('min_price'=>5000,'max_price'=>''), $base_url ) ); ?>">5.000 ₺ ve üzeri</a></li>
        </ul>
      </div>

      <!-- Markalar (bulunulan kategoriye özel, dinamik + kaydırılabilir) -->
      <?php
        // Bu kategorideki ürünlerin gerçek markalarını topla
        $cat_brand_ids = array();
        if ( $current_term ) {
          // HIZLI: tek SQL ile kategori ürünlerinin markalarını al
          $cat_pids = pazaryeri_cat_product_ids( $current_term->term_id );
          $cat_brand_ids = pazaryeri_terms_for_products( $cat_pids, 'product_brand' );
        }
        // Kategori sayfasıysa o kategorinin markaları, değilse tüm markalar
        if ( ! empty( $cat_brand_ids ) ) {
          $cat_brands = get_terms( array( 'taxonomy'=>'product_brand', 'include'=>$cat_brand_ids, 'hide_empty'=>true, 'orderby'=>'count', 'order'=>'DESC' ) );
        } else {
          $cat_brands = get_terms( array( 'taxonomy'=>'product_brand', 'hide_empty'=>true, 'orderby'=>'count', 'order'=>'DESC' ) );
        }
        if ( ! empty( $cat_brands ) && ! is_wp_error( $cat_brands ) ) :
          $cur_brand = is_tax('product_brand') ? get_queried_object_id() : 0;
      ?>
      <div class="shop-filter-box">
        <div class="shop-filter-title">Markalar</div>
        <?php if ( count( $cat_brands ) > 6 ) : ?>
          <input type="text" class="shop-brand-search" placeholder="Marka ara..." onkeyup="filterBrandList(this.value)">
        <?php endif; ?>
        <ul class="shop-brand-list shop-scroll" id="shopBrandList">
          <?php foreach ( $cat_brands as $br ) :
            if ( $br->count < 1 ) continue;
            $b_active = ( $br->term_id === $cur_brand ) ? ' class="on"' : ''; ?>
            <li<?php echo $b_active; ?> data-brand-name="<?php echo esc_attr( mb_strtolower($br->name) ); ?>">
              <a href="<?php echo esc_url( get_term_link( $br ) ); ?>">
                <span><?php echo esc_html( $br->name ); ?></span>
                <span class="shop-cat-count"><?php echo esc_html( $br->count ); ?></span>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <!-- ═══ ÖZELLİK FİLTRELERİ (renk, beden, vb. - kategoriye özel) ═══ -->
      <?php
        // Son alt kategori mi? (alt kategorisi olmayan = yaprak kategori)
        $is_leaf_cat = false;
        if ( $current_term ) {
          $child_check = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>$current_term->term_id, 'hide_empty'=>false, 'number'=>1, 'fields'=>'ids' ) );
          $is_leaf_cat = ( empty( $child_check ) || is_wp_error( $child_check ) );
        }
        // HIZLI: cache'li ürün ID'leri (markalar ile aynı sorgu, tekrar çalışmaz)
        $cat_prod_ids = $current_term ? pazaryeri_cat_product_ids( $current_term->term_id ) : array();

        // Tüm ürün niteliği taksonomilerini al (pa_renk, pa_beden, ...)
        $attribute_taxes = wc_get_attribute_taxonomies();
        $pz_color_shown = false; // renk filtresi mükerrerliğini önle
        // Gösterilecek TEK renk niteliğini belirle: Türkçe "renk" öncelikli, yoksa ilk color
        $pz_color_tax = '';
        if ( ! empty( $attribute_taxes ) ) {
          $pz_color_candidates = array();
          foreach ( $attribute_taxes as $at_c ) {
            $sl = strtolower( $at_c->attribute_name );
            if ( strpos($sl,'renk')!==false || strpos($sl,'color')!==false || strpos($sl,'colour')!==false ) {
              $pz_color_candidates[] = $at_c->attribute_name;
            }
          }
          // Türkçe 'renk' içereni tercih et
          foreach ( $pz_color_candidates as $c ) { if ( strpos(strtolower($c),'renk')!==false ) { $pz_color_tax = $c; break; } }
          if ( ! $pz_color_tax && ! empty( $pz_color_candidates ) ) $pz_color_tax = $pz_color_candidates[0];
        }
        if ( ! empty( $attribute_taxes ) && ! empty( $cat_prod_ids ) ) :
          foreach ( $attribute_taxes as $attr_tax ) :
            $tax_name = wc_attribute_taxonomy_name( $attr_tax->attribute_name ); // pa_renk
            // HIZLI: tek SQL ile bu niteliğin kullanılan değerlerini al
            $used_term_ids = pazaryeri_terms_for_products( $cat_prod_ids, $tax_name );
            if ( empty( $used_term_ids ) ) continue;

            $attr_terms = get_terms( array( 'taxonomy' => $tax_name, 'include' => $used_term_ids, 'hide_empty' => false ) );
            if ( empty( $attr_terms ) || is_wp_error( $attr_terms ) ) continue;

            $attr_label = wc_attribute_label( $tax_name );
            $attr_slug_l = strtolower( $attr_tax->attribute_name );
            $is_color = ( strpos( $attr_slug_l, 'renk' ) !== false || strpos( $attr_slug_l, 'color' ) !== false || strpos( $attr_slug_l, 'colour' ) !== false );
            $is_size  = ( strpos( $attr_slug_l, 'beden' ) !== false || strpos( $attr_slug_l, 'size' ) !== false || strpos( $attr_slug_l, 'numara' ) !== false );
            // Mükerrer renk filtresini önle: sadece seçilen tek renk niteliği gösterilsin
            if ( $is_color ) {
              if ( $pz_color_tax && $attr_tax->attribute_name !== $pz_color_tax ) continue; // diğer renk niteliklerini atla
              if ( $pz_color_shown ) continue;
              $pz_color_shown = true;
            }
            // Renk/beden dışındaki "diğer nitelikler" sadece son alt kategoride gösterilsin
            if ( ! $is_color && ! $is_size && ! $is_leaf_cat ) continue;
            $filter_key = 'filter_' . $attr_tax->attribute_name;
            $active_vals = isset( $_GET[ $filter_key ] ) ? explode( ',', sanitize_text_field( $_GET[ $filter_key ] ) ) : array();
            // Renk kod eşlemesi
            $color_hex = array('siyah'=>'#222','beyaz'=>'#fff','kırmızı'=>'#e53935','kirmizi'=>'#e53935','mavi'=>'#1e88e5','yeşil'=>'#43a047','yesil'=>'#43a047','sarı'=>'#fdd835','sari'=>'#fdd835','turuncu'=>'#fb8c00','mor'=>'#8e24aa','pembe'=>'#ec407a','gri'=>'#9e9e9e','kahverengi'=>'#6d4c41','lacivert'=>'#283593','bej'=>'#d7ccc8','gümüş'=>'#cfd8dc','gold'=>'#ffd700','altın'=>'#ffd700');
      ?>
      <div class="shop-filter-box">
        <div class="shop-filter-title"><?php echo esc_html( $attr_label ); ?></div>
        <?php if ( $is_color ) : ?>
          <div class="shop-color-grid">
            <?php foreach ( $attr_terms as $at ) :
              $nm = mb_strtolower( $at->name );
              $hex = isset( $color_hex[ $nm ] ) ? $color_hex[ $nm ] : '#cccccc';
              $on = in_array( $at->slug, $active_vals, true ) ? ' on' : '';
              $url = add_query_arg( $filter_key, $at->slug, $base_url ); ?>
              <a class="shop-color-sw<?php echo $on; ?>" href="<?php echo esc_url( $url ); ?>" title="<?php echo esc_attr( $at->name ); ?>" style="background:<?php echo esc_attr( $hex ); ?>;"><span class="sw-check">✓</span></a>
            <?php endforeach; ?>
          </div>
        <?php elseif ( $is_size ) : ?>
          <div class="shop-size-grid">
            <?php foreach ( $attr_terms as $at ) :
              $on = in_array( $at->slug, $active_vals, true ) ? ' on' : '';
              $url = add_query_arg( $filter_key, $at->slug, $base_url ); ?>
              <a class="shop-size-btn<?php echo $on; ?>" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $at->name ); ?></a>
            <?php endforeach; ?>
          </div>
        <?php else : ?>
          <ul class="shop-attr-list">
            <?php foreach ( $attr_terms as $at ) :
              $on = in_array( $at->slug, $active_vals, true ) ? ' class="on"' : '';
              $url = add_query_arg( $filter_key, $at->slug, $base_url ); ?>
              <li<?php echo $on; ?>><a href="<?php echo esc_url( $url ); ?>"><span><?php echo esc_html( $at->name ); ?></span></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
      <?php
          endforeach;
        endif;
      ?>

      <!-- Kargo / durum -->
      <div class="shop-filter-box">
        <div class="shop-filter-title">Kargo</div>
        <ul class="shop-ship-list">
          <li><a href="<?php echo esc_url( add_query_arg( 'free_shipping', '1', $base_url ) ); ?>">🚚 1500₺ Üzeri Ücretsiz Kargo</a></li>
          <li><a href="<?php echo esc_url( add_query_arg( 'fast_delivery', '1', $base_url ) ); ?>">⚡ Hızlı Teslimat</a></li>
        </ul>
      </div>
    </aside>

    <!-- ═══ SAĞ: ÜRÜN IZGARASI ═══ -->
    <main class="shop-main">

      <!-- MOBİL ALT KATEGORİ ŞERİDİ (yatay kaydırmalı, kategori resimleriyle) -->
      <?php
        // Alt kategoriler varsa onları, yoksa kardeş kategorileri göster
        $strip_terms = array();
        $strip_parent_link = '';
        if ( $current_term ) {
          $strip_kids = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>$current_term->term_id, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC' ) );
          if ( ! empty( $strip_kids ) && ! is_wp_error( $strip_kids ) ) {
            $strip_terms = $strip_kids;
            $strip_parent_link = get_term_link( $current_term );
          } elseif ( $current_term->parent ) {
            // Alt kategori yoksa kardeşleri göster
            $strip_sibs = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>$current_term->parent, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC' ) );
            if ( ! empty( $strip_sibs ) && ! is_wp_error( $strip_sibs ) ) {
              $strip_terms = $strip_sibs;
              $parent_term = get_term( $current_term->parent, 'product_cat' );
              if ( $parent_term && ! is_wp_error( $parent_term ) ) $strip_parent_link = get_term_link( $parent_term );
            }
          }
        }
        if ( ! empty( $strip_terms ) ) :
      ?>
      <div class="mob-cat-strip">
        <?php if ( $strip_parent_link && ! is_wp_error( $strip_parent_link ) ) : ?>
          <a class="mcs-item mcs-all<?php echo ( $current_term && empty($strip_kids) ) ? '' : ' on'; ?>" href="<?php echo esc_url( $strip_parent_link ); ?>">
            <div class="mcs-img mcs-img-all"><span>⊞</span></div>
            <span class="mcs-name">Tümünü Gör</span>
          </a>
        <?php endif; ?>
        <?php foreach ( $strip_terms as $st ) :
          if ( $st->slug === 'uncategorized' ) continue;
          $st_thumb = get_term_meta( $st->term_id, 'thumbnail_id', true );
          $st_img = $st_thumb ? wp_get_attachment_image_url( $st_thumb, 'thumbnail' ) : '';
          $st_active = ( $current_term && $st->term_id === $current_term->term_id ) ? ' on' : '';
        ?>
          <a class="mcs-item<?php echo $st_active; ?>" href="<?php echo esc_url( get_term_link( $st ) ); ?>">
            <div class="mcs-img">
              <?php if ( $st_img ) : ?>
                <img loading="lazy" decoding="async" src="<?php echo esc_url( $st_img ); ?>" alt="<?php echo esc_attr( $st->name ); ?>" width="100" height="100">
              <?php else : ?>
                <span class="mcs-emoji">📦</span>
              <?php endif; ?>
            </div>
            <span class="mcs-name"><?php echo esc_html( $st->name ); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- MOBİL FİLTRE AÇ butonu (sadece mobilde görünür) -->
      <button class="mob-filter-btn" id="mobFilterBtn" onclick="pzToggleFilters()" type="button">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="10" y1="18" x2="14" y2="18"/></svg>
        Filtreler
      </button>

      <!-- Üst bar: başlık + sıralama -->
      <div class="shop-topbar">
        <div class="shop-topbar-left">
          <h1 class="shop-title"><?php echo esc_html( $page_title ); ?></h1>
          <?php
            global $wp_query;
            $total = $wp_query->found_posts;
          ?>
          <span class="shop-count"><?php echo esc_html( $total ); ?> ürün listeleniyor</span>
        </div>
        <div class="shop-topbar-right">
          <?php woocommerce_catalog_ordering(); ?>
        </div>
      </div>

      <?php if ( woocommerce_product_loop() ) : ?>
        <div class="pgrid shop-grid">
          <?php
            while ( have_posts() ) : the_post();
              global $product; $product = wc_get_product();
              echo bazario_product_card( $product );
            endwhile;
          ?>
        </div>

        <!-- Sayfalama -->
        <div class="shop-pagination">
          <?php
            echo paginate_links( array(
              'total'   => $wp_query->max_num_pages,
              'current' => max( 1, get_query_var( 'paged' ) ),
              'prev_text' => '‹ Önceki',
              'next_text' => 'Sonraki ›',
            ) );
          ?>
        </div>
      <?php else : ?>
        <div class="shop-empty">
          <div class="shop-empty-ico">🔍</div>
          <div class="shop-empty-title">Ürün bulunamadı</div>
          <div class="shop-empty-text">Bu kriterlere uygun ürün yok. Filtreleri değiştirerek tekrar deneyin.</div>
          <a href="<?php echo esc_url( $base_url ); ?>" class="shop-empty-btn">Filtreleri Temizle</a>
        </div>
      <?php endif; ?>
    </main>

  </div>
</div>

<?php
get_footer();
