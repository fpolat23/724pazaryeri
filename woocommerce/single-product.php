<?php
/**
 * Tek ürün şablonu — 724PazarYeri (WooCommerce override)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

while ( have_posts() ) : the_post();
    global $product;
    $product = wc_get_product( get_the_ID() );
    if ( ! $product ) continue;
?>
<div class="bc"><div class="bc-in">
  <a class="bc-a" href="<?php echo esc_url( home_url('/') ); ?>">Anasayfa</a><span class="bc-sep">›</span>
  <?php
    // Ürün kategorilerinden dinamik breadcrumb
    $terms = wc_get_product_terms( $product->get_id(), 'product_cat', array( 'orderby' => 'parent', 'order' => 'ASC' ) );
    if ( ! empty( $terms ) ) {
      $main_term = end( $terms );
      // ana kategoriden alt kategoriye zincir
      $ancestors = get_ancestors( $main_term->term_id, 'product_cat' );
      $ancestors = array_reverse( $ancestors );
      foreach ( $ancestors as $anc_id ) {
        $anc = get_term( $anc_id, 'product_cat' );
        if ( $anc && ! is_wp_error( $anc ) ) {
          echo '<a class="bc-a" href="' . esc_url( get_term_link( $anc ) ) . '">' . esc_html( $anc->name ) . '</a><span class="bc-sep">›</span>';
        }
      }
      echo '<a class="bc-a" href="' . esc_url( get_term_link( $main_term ) ) . '">' . esc_html( $main_term->name ) . '</a><span class="bc-sep">›</span>';
    }
  ?>
  <span style="color:var(--ink)"><?php echo esc_html( $product->get_name() ); ?></span>
</div></div>

<div class="cw">
  <div class="pd-grid hb-grid">

    <!-- ═══ SOL: GALERİ ═══ -->
    <div class="hb-gallery">
      <?php
        $main_id   = $product->get_image_id();
        $gallery   = $product->get_gallery_image_ids();
        $all_imgs  = array_merge( $main_id ? array( $main_id ) : array(), $gallery );
        $main_full = $main_id ? wp_get_attachment_image_url( $main_id, 'large' ) : wc_placeholder_img_src();
        // indirim oranı
        $regular = (float) $product->get_regular_price();
        $sale    = (float) $product->get_price();
        $disc    = ( $regular > 0 && $product->is_on_sale() ) ? round( ( ( $regular - $sale ) / $regular ) * 100 ) : 0;
      ?>
      <div class="hb-thumbs">
        <?php foreach ( $all_imgs as $idx => $img_id ) :
          $thumb = wp_get_attachment_image_url( $img_id, 'thumbnail' );
          $full  = wp_get_attachment_image_url( $img_id, 'large' ); ?>
          <div class="hb-thumb<?php echo $idx === 0 ? ' on' : ''; ?>" data-full="<?php echo esc_url( $full ); ?>" onmouseenter="hbSetImg(this,'<?php echo esc_url( $full ); ?>')" onclick="hbSetImg(this,'<?php echo esc_url( $full ); ?>')">
            <img loading="lazy" decoding="async" src="<?php echo esc_url( $thumb ); ?>" alt="">
          </div>
        <?php endforeach; ?>
      </div>
      <div class="hb-main-img">
        <?php if ( $disc > 0 ) : ?><span class="hb-disc-badge">%<?php echo esc_html( $disc ); ?><br><small>indirim</small></span><?php endif; ?>
        <?php if ( $sale >= 1500 ) : ?><span class="hb-freeship-badge"><span class="hb-freeship-ico">🚚</span><span class="hb-freeship-txt">Ücretsiz<br>Kargo</span></span><?php endif; ?>
        <button class="hb-fav" id="imgFav" onclick="toggleImgFav()" aria-label="Favorilere ekle">🤍</button>
        <img loading="lazy" decoding="async" id="mainImgEl" src="<?php echo esc_url( $main_full ); ?>" alt="<?php the_title_attribute(); ?>">
        <div class="hb-zoom-hint">🔍 İncelemek için üzerine gelin</div>
      </div>

      <?php
        // ── SATICI ŞERİDİ (galeri altı) ──
        global $wpdb;
        $pzv_author_id  = (int) get_post_field( 'post_author', $product->get_id() );
        $pzv_vendor     = ( $pzv_author_id && class_exists( 'PZV_Vendor' ) ) ? PZV_Vendor::get( $pzv_author_id ) : null;
        if ( $pzv_vendor && get_user_meta( $pzv_author_id, 'pzv_status', true ) !== 'inactive' ) :
          $pzv_store_url  = PZV_Vendor::store_url( $pzv_author_id );
          $pzv_logo_id    = $pzv_vendor['logo'];
          $pzv_logo_url   = $pzv_logo_id ? wp_get_attachment_image_url( $pzv_logo_id, array( 40, 40 ) ) : '';
          $pzv_store_name = $pzv_vendor['store_name'];
          $pzv_city       = $pzv_vendor['city'];
      ?>
      <div class="pzv-seller-strip">
        <div class="pzv-ss-left">
          <?php if ( $pzv_logo_url ) : ?>
            <img class="pzv-ss-logo" src="<?php echo esc_url( $pzv_logo_url ); ?>" alt="<?php echo esc_attr( $pzv_store_name ); ?>">
          <?php else : ?>
            <div class="pzv-ss-logo-fb"><?php echo esc_html( mb_strtoupper( mb_substr( $pzv_store_name, 0, 1 ) ) ); ?></div>
          <?php endif; ?>
          <div class="pzv-ss-info">
            <a class="pzv-ss-name" href="<?php echo esc_url( $pzv_store_url ); ?>"><?php echo esc_html( $pzv_store_name ); ?></a>
            <?php if ( $pzv_city ) : ?><span class="pzv-ss-city">📍 <?php echo esc_html( $pzv_city ); ?></span><?php endif; ?>
          </div>
        </div>
        <a class="pzv-ss-btn" href="<?php echo esc_url( $pzv_store_url ); ?>">Mağazaya Git ›</a>
      </div>
      <?php endif; ?>
    </div>

    <!-- ═══ SAĞ KOLON: BİLGİ + SATIN AL ═══ -->
    <div class="hb-right-col">
    <!-- ═══ ORTA: ÜRÜN BİLGİSİ ═══ -->
    <div class="hb-info">
      <?php
        $brand_terms = wp_get_post_terms( $product->get_id(), 'product_brand' );
        if ( ! empty( $brand_terms ) && ! is_wp_error( $brand_terms ) ) :
          $brand = $brand_terms[0]; ?>
          <a class="hb-brand" href="<?php echo esc_url( get_term_link( $brand ) ); ?>"><?php echo esc_html( $brand->name ); ?> <span>Tüm ürünler ›</span></a>
        <?php endif; ?>

      <h1 class="hb-title"><?php the_title(); ?></h1>

      <div class="hb-rating-row">
        <?php $avg = $product->get_average_rating(); $count = $product->get_review_count(); ?>
        <span class="hb-stars"><?php echo str_repeat('★', round($avg)) . str_repeat('☆', 5 - round($avg)); ?></span>
        <span class="hb-rating-val"><?php echo esc_html( number_format((float)$avg,1) ); ?></span>
        <a class="hb-rating-count" href="#yorumlar" onclick="goTab(2)"><?php echo esc_html( $count ); ?> Değerlendirme</a>
        <?php if ( $product->get_total_sales() ) : ?><span class="hb-sold"><?php echo esc_html( $product->get_total_sales() ); ?>+ Satış</span><?php endif; ?>
      </div>

      <!-- Varyantlar -->
      <?php
        $attributes = $product->get_attributes();
        $color_map  = array('siyah'=>'#1a1917','beyaz'=>'#f5f5f5','gri'=>'#888','kirmizi'=>'#c0392b','mavi'=>'#1a4fa0','yesil'=>'#1a7a4a','kahve'=>'#6b3f1a','bej'=>'#d4b896','sari'=>'#f5c518','pembe'=>'#e84393','mor'=>'#8e44ad','turuncu'=>'#ff6a00');
        // Variable ürün: gerçek varyasyon verisini hazırla (stok, fiyat, resim, attribute eşlemesi)
        $pz_variations = array();
        $pz_is_variable_prod = $product->is_type('variable');
        if ( $pz_is_variable_prod ) {
          // get_available_variations() zaten tüm veriyi döndürür; tekrar yükleme YAPMA (hız)
          foreach ( $product->get_available_variations() as $vd ) {
            $img_src = '';
            if ( ! empty( $vd['image']['src'] ) ) {
              $img_src = $vd['image']['full_src'] ?: $vd['image']['src'];
            }
            $pz_variations[] = array(
              'id'         => $vd['variation_id'],
              'attributes' => $vd['attributes'],
              'in_stock'   => ! empty( $vd['is_in_stock'] ),
              'price_html' => isset( $vd['price_html'] ) ? $vd['price_html'] : '',
              'image'      => $img_src,
              'max_qty'    => isset( $vd['max_qty'] ) ? $vd['max_qty'] : '',
            );
          }
        }
        // Her attribute için ilk stokta olan varyasyondan varsayılan değeri belirle
        $pz_default_attrs = array();
        foreach ( $pz_variations as $vd ) {
          if ( ! $vd['in_stock'] ) continue;
          foreach ( $vd['attributes'] as $ak => $av ) {
            if ( ! isset( $pz_default_attrs[ $ak ] ) && $av !== '' ) {
              $pz_default_attrs[ $ak ] = $av;
            }
          }
        }
// YENİ KURAL: GÖRÜNÜR olan TÜM attribute'lar seçim butonu olarak göster (Hepsiburada tarzı)
      ?>
      <div class="hb-attrs-outer" id="hbAttrsOuter">
        <div class="hb-attrs-wrap" id="hbAttrsWrap">
      <?php
        foreach ( $attributes as $attribute ) :
          // Sadece "Görünür değil" + "Varyasyon değil" olanları atla
          if ( ! $attribute->get_visible() && ! $attribute->get_variation() ) continue;
          $attr_is_tax = $attribute->is_taxonomy();
          if ( $attr_is_tax ) {
            $terms = wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'all' ) );
          } else {
            $raw_opts = $attribute->get_options();
            // Eğer kullanıcı "|" yerine "," koymuşsa, otomatik patlat (her seçeneğin içinde virgül varsa)
            $expanded = array();
            foreach ( $raw_opts as $opt ) {
              if ( strpos( $opt, ',' ) !== false && strpos( $opt, '|' ) === false ) {
                foreach ( explode( ',', $opt ) as $piece ) {
                  $piece = trim( $piece );
                  if ( $piece !== '' ) $expanded[] = $piece;
                }
              } else {
                $expanded[] = $opt;
              }
            }
            $terms = array_map( function( $v ){ return (object) array( 'name' => $v, 'slug' => sanitize_title( $v ), 'raw' => $v ); }, $expanded );
          }
          if ( empty( $terms ) ) continue;
          $label = pz_attr_label( $attribute->get_name() );
          $is_color = ( stripos( $label, 'renk' ) !== false || stripos( $attribute->get_name(), 'color' ) !== false );
          // Bu attribute için varsayılan (ilk stokta) değeri bul
          $attr_full_key   = 'attribute_' . sanitize_title( $attribute->get_name() );
          $default_attr_val = isset( $pz_default_attrs[ $attr_full_key ] ) ? $pz_default_attrs[ $attr_full_key ] : null;
          $default_ti       = 0;
          $default_name     = isset( $terms[0] ) ? $terms[0]->name : '';
          if ( $default_attr_val !== null ) {
            foreach ( $terms as $_ti => $_term ) {
              $tv = $attr_is_tax ? $_term->slug : sanitize_title( $_term->name );
              if ( $tv === $default_attr_val || $_term->slug === $default_attr_val ) {
                $default_ti   = $_ti;
                $default_name = $_term->name;
                break;
              }
            }
          }
      ?>
        <div class="hb-variant-block">
          <div class="hb-variant-label"><?php echo esc_html( $label ); ?>: <span id="sel-<?php echo esc_attr( $attribute->get_name() ); ?>"><?php echo esc_html( $default_name ); ?></span></div>
          <?php if ( $is_color ) : ?>
            <div class="hb-colors">
              <?php foreach ( $terms as $ti => $term ) :
                $sw = isset( $color_map[ $term->slug ] ) ? $color_map[ $term->slug ] : '#ccc'; ?>
                <button type="button" class="hb-color<?php echo $ti===$default_ti?' on':''; ?>" data-attr="attribute_<?php echo esc_attr( sanitize_title($attribute->get_name()) ); ?>" data-value="<?php echo esc_attr( $attr_is_tax ? $term->slug : $term->name ); ?>" onclick="pzSelectVar(this,'<?php echo esc_js($term->name); ?>','<?php echo esc_attr($attribute->get_name()); ?>')" title="<?php echo esc_attr($term->name); ?>" style="background:<?php echo esc_attr($sw); ?>"></button>
              <?php endforeach; ?>
            </div>
          <?php else : ?>
            <div class="hb-variants">
              <?php foreach ( $terms as $ti => $term ) : ?>
                <button type="button" class="hb-var<?php echo $ti===$default_ti?' on':''; ?>" data-attr="attribute_<?php echo esc_attr( sanitize_title($attribute->get_name()) ); ?>" data-value="<?php echo esc_attr( $attr_is_tax ? $term->slug : $term->name ); ?>" onclick="pzSelectVar(this,'<?php echo esc_js($term->name); ?>','<?php echo esc_attr($attribute->get_name()); ?>')"><?php echo esc_html($term->name); ?></button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
        </div><!-- /hb-attrs-wrap -->
        <div class="hb-attrs-fade"></div>
      </div><!-- /hb-attrs-outer -->
      <button class="hb-attrs-more" id="hbAttrsMore" onclick="hbShowSpecs()">
        <span>Tüm Özellikleri Gör</span>
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
      </button>

      <!-- Öne çıkan özellikler (kısa) -->
      <?php
        $feat_attrs = array_slice( $attributes, 0, 4, true );
        if ( ! empty( $feat_attrs ) ) : ?>
        <div class="hb-quick-specs">
          <div class="hb-quick-specs-title">Öne Çıkan Özellikler</div>
          <div class="hb-quick-specs-grid">
            <?php foreach ( $feat_attrs as $attr ) :
              $aname = pz_attr_label( $attr->get_name() );
              $avals = $attr->is_taxonomy() ? wc_get_product_terms( $product->get_id(), $attr->get_name(), array('fields'=>'names') ) : $attr->get_options();
              if ( empty($avals) ) continue; ?>
              <div class="hb-qs-item"><span class="hb-qs-k"><?php echo esc_html($aname); ?></span><span class="hb-qs-v"><?php echo esc_html( implode(', ', $avals) ); ?></span></div>
            <?php endforeach; ?>
          </div>
          <a class="hb-all-specs" href="#" onclick="goTab(1);return false;">Tüm özellikleri gör ›</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- ═══ SAĞ: SATIN ALMA KUTUSU ═══ -->
    <div class="hb-buybox">
      <?php
        $in_stock  = $product->is_in_stock();
        $stock_qty = $product->get_stock_quantity();
        $price     = (float) $product->get_price();
      ?>
      <div class="hb-price-area">
        <?php if ( $disc > 0 ) : ?>
          <div class="hb-price-old"><?php echo wc_price( $regular ); ?></div>
        <?php endif; ?>
        <div class="hb-price-now"><?php echo wc_price( $price ); ?></div>
        <div class="hb-price-kdv">KDV Dahil</div>
        <?php if ( $disc > 0 ) : ?>
          <div class="hb-save-badge">%<?php echo esc_html($disc); ?> indirim · <?php echo wc_price( $regular - $price ); ?> kazanç</div>
        <?php endif; ?>
      </div>

      <!-- Taksit (komisyon/faiz oranlı, fiyata göre dinamik) -->
      <?php
        // Banka komisyon oranları (taksit sayısı => % oran)
        $komisyon = array(
          1  => 3.49,   // tek çekim
          2  => 6.10,
          3  => 7.90,
          4  => 9.80,
          5  => 11.70,
          6  => 13.60,
          7  => 15.40,
          8  => 17.40,
          9  => 19.30,
          10 => 21.10,
          11 => 22.90,
          12 => 24.90,
        );
        // Fiyata göre uygun taksit seçenekleri (taksit başına min ~50 TL)
        $min_per_installment = 50;
        $show_installments = array();
        foreach ( array(2,3,6,9,12) as $opt ) {
          if ( ( $price / $opt ) >= $min_per_installment ) {
            $show_installments[] = $opt;
          }
        }
        if ( ! empty( $show_installments ) ) :
      ?>
      <div class="hb-inst-head">
        <span>Taksit Seçenekleri</span>
        <button type="button" class="hb-inst-toggle" onclick="hbToggleInst(this)">Tümünü Gör ›</button>
      </div>
      <div class="hb-installments" id="hbInstList">
        <?php foreach ( $show_installments as $idx => $t ) :
          $rate       = isset( $komisyon[ $t ] ) ? $komisyon[ $t ] : 0;
          $total_with = $price * ( 1 + $rate / 100 );      // faizli toplam
          $per_month  = $total_with / $t;                   // aylık taksit
          $hidden     = $idx >= 2 ? ' hb-inst-hidden' : '';  // ilk 2 görünür, kalanı gizli
        ?>
          <div class="hb-inst<?php echo $hidden; ?>">
            <span class="hb-inst-x"><?php echo $t; ?> Taksit</span>
            <span class="hb-inst-mid"><?php echo wc_price( $per_month ); ?>/ay</span>
            <span class="hb-inst-v"><?php echo wc_price( $total_with ); ?></span>
          </div>
        <?php endforeach; ?>
        <div class="hb-inst-note">Taksitli işlemlerde banka komisyonu fiyata yansıtılır. Tek çekimde komisyon uygulanmaz.</div>
      </div>
      <?php endif; ?>

      <!-- Nakit indirim -->
      <div class="hb-724pay"><span class="hb-724pay-badge">Nakit</span> ödemede <strong><?php echo wc_price( $price * 0.05 ); ?></strong> ekstra indirim</div>

      <!-- Kargo / teslimat -->
      <div class="hb-delivery">
        <?php if ( $in_stock ) : ?>
        <div class="hb-del-row hb-del-kargo<?php echo ( $price >= 1500 ) ? ' hb-del-kargo-free' : ''; ?>">
          <div class="hb-del-ico-wrap">🚚</div>
          <div class="hb-del-content">
            <?php if ( $price >= 1500 ) : ?>
              <strong>Bu Ürün İçin Kargo Ücretsiz!</strong>
              <small>Tahmini teslimat: <?php echo esc_html( date_i18n( 'j F', strtotime('+2 days') ) ); ?> – <?php echo esc_html( date_i18n( 'j F', strtotime('+4 days') ) ); ?></small>
            <?php else : ?>
              <strong>1500₺ Üzeri Ücretsiz Kargo</strong>
              <small>Tahmini teslimat: <?php echo esc_html( date_i18n( 'j F', strtotime('+2 days') ) ); ?> – <?php echo esc_html( date_i18n( 'j F', strtotime('+4 days') ) ); ?></small>
            <?php endif; ?>
          </div>
          <?php if ( $price >= 1500 ) : ?>
            <span class="hb-del-badge hb-del-badge-green">ÜCRETSİZ</span>
          <?php endif; ?>
        </div>
        <div class="hb-del-row hb-del-hizli">
          <div class="hb-del-ico-wrap">⚡</div>
          <div class="hb-del-content">
            <strong>Hızlı Teslimat</strong>
            <small class="pship-txt">Kargo bilgisi hesaplanıyor…</small>
          </div>
        </div>
        <?php else : ?>
        <div class="hb-del-row hb-del-stok">
          <div class="hb-del-ico-wrap">📦</div>
          <div class="hb-del-content">
            <strong>Stok Bekleniyor</strong>
            <small>Bu ürün şu an stokta bulunmuyor</small>
          </div>
          <span class="hb-del-badge hb-del-badge-grey">STOKTA YOK</span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Adet + Sepet -->
      <?php $pz_is_variable = $product->is_type('variable'); ?>
      <form class="cart hb-cart-form <?php echo $pz_is_variable ? 'variations_form' : ''; ?>" action="<?php echo esc_url( apply_filters( 'woocommerce_add_to_cart_form_action', $product->get_permalink() ) ); ?>" method="post" enctype="multipart/form-data" data-product_id="<?php echo esc_attr( $product->get_id() ); ?>" data-product_variations="<?php echo $pz_is_variable ? esc_attr( wp_json_encode( $product->get_available_variations() ) ) : ''; ?>">
        <div class="hb-qty-row">
          <span class="hb-qty-label">Adet</span>
          <div class="hb-qty">
            <button type="button" onclick="qty(-1)">−</button>
            <input type="number" id="qtyVal" name="quantity" value="1" min="1" step="1" <?php echo $stock_qty ? 'max="'.esc_attr($stock_qty).'"' : ''; ?>>
            <button type="button" onclick="qty(1)">+</button>
          </div>
          <span id="pzStockInfo" class="hb-stock-info"><?php if ( $in_stock ) : ?><span class="hb-stock-ok"><?php echo $stock_qty ? '✓ '.esc_html($stock_qty).' adet stokta' : '✓ Stokta'; ?></span><?php else : ?><span class="hb-stock-no">Tükendi</span><?php endif; ?></span>
        </div>
        <?php if ( $pz_is_variable_prod ) : ?>
          <input type="hidden" name="product_id" value="<?php echo esc_attr($product->get_id()); ?>">
          <input type="hidden" name="variation_id" id="pzVariationId" value="">
          <?php
            // Her varyasyon attribute'u için gizli alan (JS dolduracak)
            foreach ( $product->get_variation_attributes() as $attr_name => $opts ) :
              $field = 'attribute_' . sanitize_title( $attr_name );
          ?>
            <input type="hidden" name="<?php echo esc_attr($field); ?>" id="pzAttr-<?php echo esc_attr($field); ?>" value="">
          <?php endforeach; ?>
          <div class="pz-var-warning" id="pzVarWarning" style="display:none;">Lütfen tüm seçenekleri belirleyin.</div>
        <?php endif; ?>
        <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="hb-add-cart single_add_to_cart_button" id="pzAddCart" <?php echo $in_stock?'':'disabled'; ?>>
          🛒 Sepete Ekle
        </button>
        <button type="submit" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>" class="hb-buy-now" data-redirect="checkout" <?php echo $in_stock?'':'disabled'; ?>>⚡ Hemen Al</button>
      </form>
      <?php if ( $pz_is_variable_prod ) : ?>
        <script type="application/json" id="pzVariationData"><?php echo wp_json_encode( $pz_variations ); ?></script>
      <?php endif; ?>
      <input type="hidden" id="hbCheckoutUrl" value="<?php echo esc_url( wc_get_checkout_url() ); ?>">

      <!-- Hızlı Aksiyonlar -->
      <?php
        // Ürün özellikleri (karşılaştırma için)
        $pz_qattrs = array();
        foreach ( $product->get_attributes() as $akey => $attr ) {
          if ( ! $attr->get_visible() ) continue;
          $alabel = pz_attr_label( $akey );
          $aval   = $attr->is_taxonomy()
            ? implode( ', ', wc_get_product_terms( $product->get_id(), $akey, array( 'fields' => 'names' ) ) )
            : implode( ', ', $attr->get_options() );
          if ( $aval ) $pz_qattrs[ $alabel ] = $aval;
        }
        $pz_qcomp = wp_json_encode( array(
          'id'       => $product->get_id(),
          'url'      => get_permalink(),
          'title'    => $product->get_name(),
          'img'      => $main_full,
          'price'    => $price,
          'regular'  => $regular,
          'discount' => $disc,
          'rating'   => round( (float) $product->get_average_rating(), 1 ),
          'reviews'  => $product->get_review_count(),
          'inStock'  => $in_stock,
          'freeShip' => ( $price >= 1500 ),
          'seller'   => get_bloginfo('name'),
          'attrs'    => $pz_qattrs,
        ) );
      ?>
      <div class="hb-quick-actions">
        <button class="hb-qa-btn hb-qa-fav" id="hbFavBtn"
          data-pid="<?php echo esc_attr( $product->get_id() ); ?>"
          onclick="pzToggleFav(this,<?php echo $product->get_id(); ?>,'<?php echo esc_js($product->get_name()); ?>','<?php echo esc_js($main_full); ?>','<?php echo esc_js(get_permalink()); ?>')">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
          <span>Favorilerime Ekle</span>
        </button>
        <button class="hb-qa-btn hb-qa-comp" id="hbCompBtn"
          onclick="pzToggleComp(this)"
          data-pzcomp="<?php echo esc_attr( $pz_qcomp ); ?>">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 8L22 12L18 16M6 8L2 12L6 16M14 4L10 20"/></svg>
          <span class="pcomp-lbl">Karşılaştır</span>
        </button>
        <?php if ( ! $in_stock ) : ?>
        <button class="hb-qa-btn hb-qa-notify" onclick="pzOpenNotify('stock',<?php echo $product->get_id(); ?>,'<?php echo esc_js($product->get_name()); ?>')">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span>Stoğa Gelince Haber Ver</span>
        </button>
        <?php endif; ?>
        <button class="hb-qa-btn hb-qa-notify" onclick="pzOpenNotify('price',<?php echo $product->get_id(); ?>,'<?php echo esc_js($product->get_name()); ?>')">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
          <span>Fiyat Düşünce Haber Ver</span>
        </button>
      </div>

      <?php
        // ── DİĞER SATICILAR (PZV) ──
        $pzv_other_offers = array();
        if ( isset( $pzv_author_id ) && $pzv_author_id && class_exists( 'PZV_Vendor' ) ) {
          // Tam başlık eşleşmesi önce denensin
          $pzv_same_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             WHERE p.post_type='product' AND p.post_status='publish'
               AND p.post_author!=%d AND p.post_title=%s
             LIMIT 8",
            $pzv_author_id, $product->get_name()
          ) ) );
          // Tam eşleşme yoksa başlık içeren ürünler
          if ( empty( $pzv_same_ids ) ) {
            $pzv_title_like = '%' . $wpdb->esc_like( $product->get_name() ) . '%';
            $pzv_same_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
              "SELECT p.ID FROM {$wpdb->posts} p
               WHERE p.post_type='product' AND p.post_status='publish'
                 AND p.post_author!=%d AND p.post_title LIKE %s
               ORDER BY p.post_date DESC LIMIT 8",
              $pzv_author_id, $pzv_title_like
            ) ) );
          }
          foreach ( $pzv_same_ids as $sim_id ) {
            $sim_p = wc_get_product( $sim_id );
            if ( ! $sim_p || ! $sim_p->is_purchasable() || ! $sim_p->get_price() ) continue;
            $sim_author = (int) get_post_field( 'post_author', $sim_id );
            if ( get_user_meta( $sim_author, 'pzv_status', true ) === 'inactive' ) continue;
            $sim_vendor = PZV_Vendor::get( $sim_author );
            if ( ! $sim_vendor ) continue;
            $sim_avg = (float) $wpdb->get_var( $wpdb->prepare(
              "SELECT AVG(cm.meta_value) FROM {$wpdb->comments} c
               INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID=cm.comment_id
               INNER JOIN {$wpdb->posts} p ON c.comment_post_ID=p.ID
               WHERE p.post_author=%d AND p.post_type='product' AND p.post_status='publish'
                 AND c.comment_approved='1' AND cm.meta_key='rating'",
              $sim_author
            ) );
            $pzv_other_offers[] = array(
              'vendor_name' => $sim_vendor['store_name'],
              'vendor_url'  => PZV_Vendor::store_url( $sim_author ),
              'product_url' => get_permalink( $sim_id ),
              'price'       => (float) $sim_p->get_price(),
              'rating'      => $sim_avg > 0 ? round( $sim_avg, 1 ) : 0,
              'logo_id'     => $sim_vendor['logo'],
            );
            if ( count( $pzv_other_offers ) >= 4 ) break;
          }
        }
        if ( ! empty( $pzv_other_offers ) ) :
      ?>
      <div class="pzv-other-sellers">
        <div class="pzv-os-header">
          <span class="pzv-os-title">🏪 Diğer Satıcılar</span>
          <span class="pzv-os-count"><?php echo esc_html( count( $pzv_other_offers ) ); ?> farklı satıcı</span>
        </div>
        <?php foreach ( $pzv_other_offers as $offer ) :
          $logo_url = $offer['logo_id'] ? wp_get_attachment_image_url( $offer['logo_id'], array( 36, 36 ) ) : '';
        ?>
        <div class="pzv-os-item">
          <div class="pzv-os-left">
            <?php if ( $logo_url ) : ?>
              <img class="pzv-os-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="">
            <?php else : ?>
              <div class="pzv-os-logo pzv-os-logo-fb"><?php echo esc_html( mb_strtoupper( mb_substr( $offer['vendor_name'], 0, 1 ) ) ); ?></div>
            <?php endif; ?>
            <div class="pzv-os-info">
              <span class="pzv-os-name"><?php echo esc_html( $offer['vendor_name'] ); ?></span>
              <?php if ( $offer['rating'] > 0 ) : ?>
                <span class="pzv-os-rating">★ <?php echo esc_html( number_format( $offer['rating'], 1 ) ); ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="pzv-os-right">
            <div class="pzv-os-price"><?php echo wc_price( $offer['price'] ); ?></div>
            <div class="pzv-os-actions">
              <a class="pzv-os-buy" href="<?php echo esc_url( $offer['product_url'] ); ?>">Satın Al</a>
              <a class="pzv-os-store" href="<?php echo esc_url( $offer['vendor_url'] ); ?>">Mağaza ›</a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    </div><!-- /hb-right-col -->

    <!-- ═══ GALERİ ALTI: GÜVEN + PAYLAŞ ═══ -->
    <div class="hb-gallery-extra">
      <!-- Güven rozetleri -->
      <div class="hb-trust">
        <div class="hb-trust-item">
          <span class="hb-trust-ico hb-ti-orange">
            <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          </span>
          <span class="hb-trust-body"><strong>Güvenli Ödeme</strong><small>256-bit SSL şifreleme</small></span>
        </div>
        <div class="hb-trust-item">
          <span class="hb-trust-ico hb-ti-blue">
            <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.49"/></svg>
          </span>
          <span class="hb-trust-body"><strong>14 Gün İade</strong><small>Koşulsuz iade garantisi</small></span>
        </div>
        <div class="hb-trust-item">
          <span class="hb-trust-ico hb-ti-green">
            <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
          </span>
          <span class="hb-trust-body"><strong>Orijinal Ürün</strong><small>%100 orijinallik garantisi</small></span>
        </div>
        <div class="hb-trust-item">
          <span class="hb-trust-ico hb-ti-purple">
            <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 9.81 19.79 19.79 0 0 1 1.72 1.18 2 2 0 0 1 3.7.01h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 7.91a16 16 0 0 0 6 6l1.27-.86a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 15z"/></svg>
          </span>
          <span class="hb-trust-body"><strong>7/24 Destek</strong><small>Her zaman yanınızdayız</small></span>
        </div>
      </div>

      <!-- Sosyal Paylaşım -->
      <?php
        $pz_share_url   = urlencode( get_permalink() );
        $pz_share_title = urlencode( wp_strip_all_tags( get_the_title() ) );
        $pz_share_img   = urlencode( wp_get_attachment_image_url( $product->get_image_id(), 'large' ) ?: wc_placeholder_img_src() );
        $pz_raw_url     = esc_js( get_permalink() );
        $pz_raw_title   = esc_js( wp_strip_all_tags( get_the_title() ) );
      ?>
      <div class="hb-share">
        <div class="hb-share-lbl">Bu ürünü paylaş</div>
        <div class="hb-share-btns">
          <a class="hb-share-btn hb-share-wa" href="https://wa.me/?text=<?php echo $pz_share_title; ?>%20<?php echo $pz_share_url; ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>
            <span>WhatsApp</span>
          </a>
          <a class="hb-share-btn hb-share-fb" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $pz_share_url; ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
            <span>Facebook</span>
          </a>
          <a class="hb-share-btn hb-share-tw" href="https://x.com/intent/tweet?text=<?php echo $pz_share_title; ?>&url=<?php echo $pz_share_url; ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
            <span>X</span>
          </a>
          <button class="hb-share-btn hb-share-ig" onclick="pzShareApp('instagram','<?php echo $pz_raw_url; ?>','<?php echo $pz_raw_title; ?>')">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
            <span>Instagram</span>
          </button>
          <button class="hb-share-btn hb-share-tt" onclick="pzShareApp('tiktok','<?php echo $pz_raw_url; ?>','<?php echo $pz_raw_title; ?>')">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.69a8.18 8.18 0 0 0 4.78 1.52V6.76a4.85 4.85 0 0 1-1.01-.07z"/></svg>
            <span>TikTok</span>
          </button>
          <a class="hb-share-btn hb-share-pt" href="https://pinterest.com/pin/create/button/?url=<?php echo $pz_share_url; ?>&media=<?php echo $pz_share_img; ?>&description=<?php echo $pz_share_title; ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>
            <span>Pinterest</span>
          </a>
          <a class="hb-share-btn hb-share-li" href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo $pz_share_url; ?>&title=<?php echo $pz_share_title; ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
            <span>LinkedIn</span>
          </a>
          <button class="hb-share-btn hb-share-copy" onclick="pzCopyLink('<?php echo $pz_raw_url; ?>')">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            <span>Kopyala</span>
          </button>
        </div>
      </div>
    </div>

  </div>

  <!-- Stok & Fiyat Alarm Modalı -->
  <div class="hb-notify-modal" id="hbNotifyModal">
    <div class="hb-notify-box">
      <button class="hb-notify-close" onclick="pzCloseNotify()">×</button>
      <div class="hb-notify-ico" id="hbNIcon"></div>
      <h3 id="hbNTitle"></h3>
      <p id="hbNDesc"></p>
      <input type="email" id="hbNEmail" placeholder="E-posta adresiniz" autocomplete="email">
      <button class="hb-notify-submit" id="hbNSubmit" onclick="pzSubmitNotify()">Beni Haberdar Et</button>
      <p class="hb-notify-fine">Bildirim aldığınızda tek tıkla iptal edebilirsiniz.</p>
    </div>
  </div>

  <!-- TABS -->
  <div class="pd-tabs">
    <div class="tab-nav">
      <div class="tbn on" onclick="tab(this,'t-desc')">Ürün Açıklaması</div>
      <div class="tbn" onclick="tab(this,'t-spec')">Teknik Özellikler</div>
      <div class="tbn" onclick="tab(this,'t-rev')">Değerlendirmeler (<?php echo esc_html( $product->get_review_count() ); ?>)</div>
      <div class="tbn" onclick="tab(this,'t-sim')">Benzer Ürünler</div>
      <div class="tbn" onclick="tab(this,'t-qa')">Soru & Cevap<?php
        $pz_q_count = get_comments( array( 'post_id' => $product->get_id(), 'type' => 'pz_question', 'status' => 'approve', 'count' => true ) );
        if ( $pz_q_count ) echo ' (' . intval( $pz_q_count ) . ')';
      ?></div>
    </div>
    <div class="tab-body">
      <!-- DESC -->
      <div class="tab-pane on" id="t-desc">
        <div class="desc-grid">
          <div class="desc-text"><?php
            $desc = $product->get_description();
            if ( $desc ) {
              echo wp_kses_post( wpautop( do_shortcode( $desc ) ) );
            } else {
              echo '<p style="color:var(--muted)">Bu ürün için açıklama henüz eklenmemiş.</p>';
            }
          ?>
            <!-- DİNAMİK KATEGORİLER + ETİKETLER -->
            <?php
              $pd_cats = get_the_terms( $product->get_id(), 'product_cat' );
              $pd_tags = get_the_terms( $product->get_id(), 'product_tag' );
            ?>
            <?php if ( $pd_cats && ! is_wp_error( $pd_cats ) ) : ?>
              <div class="pd-taxbox">
                <div class="pd-taxbox-title">Kategoriler</div>
                <div class="pd-taxbox-links">
                  <?php foreach ( $pd_cats as $pd_cat ) :
                    if ( $pd_cat->slug === 'uncategorized' ) continue; ?>
                    <a class="pd-tax-link" href="<?php echo esc_url( get_term_link( $pd_cat ) ); ?>"><?php echo esc_html( $pd_cat->name ); ?></a>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
            <?php if ( $pd_tags && ! is_wp_error( $pd_tags ) ) : ?>
              <div class="pd-taxbox">
                <div class="pd-taxbox-title pd-taxbox-title-tag"><span class="pd-taxbox-hash">#</span> Etiketler</div>
                <div class="pd-taxbox-links">
                  <?php foreach ( $pd_tags as $pd_tag ) :
                    $tag_words = preg_split( '/\s+/', trim( $pd_tag->name ) );
                    foreach ( $tag_words as $tw ) :
                      if ( $tw === '' ) continue;
                      $tw_url = home_url( '/?s=' . urlencode( $tw ) . '&post_type=product' );
                  ?>
                    <a class="pd-tax-tag" href="<?php echo esc_url( $tw_url ); ?>"><?php echo esc_html( $tw ); ?></a>
                  <?php endforeach; endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
          <div class="desc-highlight">
            <div class="dh-title">Öne Çıkan Özellikler</div>
            <?php
              // Ürün niteliklerinden dinamik öne çıkan özellikler
              $hl_attrs = $product->get_attributes();
              $hl_icons = array('⭐','🔧','📦','🎯','🪶','🔋','📡','🎨','📐','⚙️');
              if ( ! empty( $hl_attrs ) ) :
                $hi = 0;
                foreach ( $hl_attrs as $hl_attr ) :
                  $hl_name = pz_attr_label( $hl_attr->get_name() );
                  $hl_vals = $hl_attr->is_taxonomy()
                    ? wc_get_product_terms( $product->get_id(), $hl_attr->get_name(), array('fields'=>'names') )
                    : $hl_attr->get_options();
                  if ( empty( $hl_vals ) ) continue;
                  $icon = isset( $hl_icons[$hi] ) ? $hl_icons[$hi] : '✓';
            ?>
              <div class="dh-item"><div class="dh-ico"><?php echo $icon; ?></div><div><div class="dh-l"><?php echo esc_html( $hl_name ); ?></div><div class="dh-s"><?php echo esc_html( implode(', ', $hl_vals) ); ?></div></div></div>
            <?php $hi++; endforeach;
              // SKU, ağırlık, boyut da ekle
              if ( $product->get_sku() ) : ?>
                <div class="dh-item"><div class="dh-ico">🔖</div><div><div class="dh-l">Ürün Kodu</div><div class="dh-s"><?php echo esc_html( $product->get_sku() ); ?></div></div></div>
              <?php endif;
              if ( $product->get_weight() ) : ?>
                <div class="dh-item"><div class="dh-ico">⚖️</div><div><div class="dh-l">Ağırlık</div><div class="dh-s"><?php echo esc_html( $product->get_weight() ) . ' ' . esc_html( get_option('woocommerce_weight_unit') ); ?></div></div></div>
              <?php endif;
              else : ?>
              <div class="dh-item"><div class="dh-ico">📦</div><div><div class="dh-l">Ürün Bilgisi</div><div class="dh-s">Detaylar için Teknik Özellikler sekmesine bakın</div></div></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <!-- SPEC -->
      <div class="tab-pane" id="t-spec">
        <?php
          $display_attrs = $product->get_attributes();
          $has_extra = $product->get_sku() || $product->get_weight() || $product->has_dimensions();
          if ( ! empty( $display_attrs ) || $has_extra ) :
        ?>
        <div class="pz-specs-grid">
          <?php foreach ( $display_attrs as $attr ) :
            $name = pz_attr_label( $attr->get_name() );
            if ( $attr->is_taxonomy() ) {
              $values = wc_get_product_terms( $product->get_id(), $attr->get_name(), array( 'fields' => 'names' ) );
            } else {
              $values = $attr->get_options();
            }
            $value_str = implode( ', ', $values );
            if ( ! $value_str ) continue;
          ?>
            <div class="pz-spec-card">
              <div class="pz-sc-lbl"><?php echo esc_html( $name ); ?></div>
              <div class="pz-sc-val"><?php echo esc_html( $value_str ); ?></div>
            </div>
          <?php endforeach; ?>
          <?php if ( $product->get_sku() ) : ?>
            <div class="pz-spec-card"><div class="pz-sc-lbl">Ürün Kodu (SKU)</div><div class="pz-sc-val"><?php echo esc_html( $product->get_sku() ); ?></div></div>
          <?php endif; ?>
          <?php if ( $product->get_weight() ) : ?>
            <div class="pz-spec-card"><div class="pz-sc-lbl">Ağırlık</div><div class="pz-sc-val"><?php echo esc_html( $product->get_weight() ); ?> <?php echo esc_html( get_option('woocommerce_weight_unit') ); ?></div></div>
          <?php endif; ?>
          <?php if ( $product->has_dimensions() ) : ?>
            <div class="pz-spec-card"><div class="pz-sc-lbl">Boyutlar</div><div class="pz-sc-val"><?php echo esc_html( wc_format_dimensions( $product->get_dimensions(false) ) ); ?></div></div>
          <?php endif; ?>
        </div>
        <?php else : ?>
          <p style="text-align:center;color:var(--muted);padding:40px 0">Bu ürün için teknik özellik girilmemiş.</p>
        <?php endif; ?>
      </div>
      <!-- REVIEWS -->
      <div class="tab-pane" id="t-rev">
        <div class="rev-sum">
          <?php
            $avg = $product->get_average_rating();
            $count = $product->get_review_count();
            // Yıldız dağılımı — TEK SQL + 5 dakika cache (N+1 yerine)
            global $wpdb;
            $rating_counts = array(5=>0,4=>0,3=>0,2=>0,1=>0);
            $pz_rc_key = 'pz_rc_' . $product->get_id();
            $pz_rc_cached = get_transient( $pz_rc_key );
            if ( $pz_rc_cached !== false ) {
              $rating_counts = $pz_rc_cached;
            } else {
              $pid = $product->get_id();
              $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT cm.meta_value AS rating, COUNT(*) AS cnt
                 FROM {$wpdb->commentmeta} cm
                 INNER JOIN {$wpdb->comments} c ON c.comment_ID = cm.comment_id
                 WHERE c.comment_post_ID = %d AND c.comment_approved = '1' AND cm.meta_key = 'rating'
                 GROUP BY cm.meta_value", $pid ) );
              if ( $rows ) {
                foreach ( $rows as $row ) {
                  $r = (int) $row->rating;
                  if ( $r >= 1 && $r <= 5 ) $rating_counts[ $r ] = (int) $row->cnt;
                }
              }
              set_transient( $pz_rc_key, $rating_counts, 5 * MINUTE_IN_SECONDS );
            }
          ?>
          <div class="rev-big">
            <div class="rev-bn"><?php echo esc_html( number_format( (float) $avg, 1 ) ); ?></div>
            <div class="rev-bs"><?php echo str_repeat('★', round($avg)) . str_repeat('☆', 5 - round($avg)); ?></div>
            <div class="rev-bl"><?php echo esc_html( $count ); ?> değerlendirme</div>
          </div>
          <div class="rev-bars">
            <?php for ( $star = 5; $star >= 1; $star-- ) :
              $sc = $rating_counts[ $star ];
              $pct = $count > 0 ? round( ( $sc / $count ) * 100 ) : 0; ?>
              <div class="rev-br"><span class="rev-brl"><?php echo $star; ?>★</span><div class="rev-brt"><div class="rev-brf" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div><span class="rev-brc"><?php echo esc_html( $sc ); ?></span></div>
            <?php endfor; ?>
          </div>
          <a class="rev-write" href="#review_form" style="text-decoration:none;text-align:center;">Değerlendirme Yaz</a>
        </div>
        <div class="rev-filters">
          <div class="rev-f on" onclick="revFilter(this)">Tümü</div>
          <div class="rev-f" onclick="revFilter(this)">★ 5</div>
          <div class="rev-f" onclick="revFilter(this)">★ 4</div>
          <div class="rev-f" onclick="revFilter(this)">📷 Fotoğraflı</div>
          <div class="rev-f" onclick="revFilter(this)">✓ Onaylı Alım</div>
        </div>
        <?php
          // WooCommerce ürün yorumları (dinamik)
          $comments = get_comments( array(
            'post_id' => $product->get_id(),
            'status'  => 'approve',
            'type'    => 'review',
            'number'  => 10,
          ) );
          if ( $comments ) :
            foreach ( $comments as $comment ) :
              $rating   = (int) get_comment_meta( $comment->comment_ID, 'rating', true );
              $verified = wc_review_is_from_verified_owner( $comment->comment_ID );
              $author   = get_comment_author( $comment );
              $initials = mb_strtoupper( mb_substr( $author, 0, 2 ) );
        ?>
          <div class="rev-card">
            <div class="rev-ct">
              <div class="rev-av"><?php echo esc_html( $initials ); ?></div>
              <div>
                <div class="rev-u"><?php echo esc_html( $author ); ?><?php if ( $verified ) : ?><span class="rev-vbadge">✓ Onaylı Alım</span><?php endif; ?></div>
                <div class="rev-d"><?php echo esc_html( get_comment_date( 'j F Y', $comment ) ); ?></div>
              </div>
              <div class="rev-s"><?php echo str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating ); ?></div>
            </div>
            <div class="rev-t"><?php echo esc_html( get_comment_text( $comment ) ); ?></div>
            <div class="rev-help"><span>👍 Yararlı</span><span>👎</span><span>Yanıtla</span></div>
          </div>
        <?php
            endforeach;
          else :
        ?>
          <p style="text-align:center;color:var(--muted);padding:24px;white-space:normal;word-break:break-word;overflow-wrap:break-word;max-width:100%;box-sizing:border-box;">Bu ürün için henüz değerlendirme yok. İlk değerlendirmeyi sen yaz!</p>
        <?php endif; ?>

        <!-- Değerlendirme formu -->
        <div id="review_form_wrapper">
          <?php if ( comments_open() ) : ?>
          <div class="pz-rev-form-wrap" id="review_form">
            <h3 class="pz-rev-title">Değerlendirme Yaz</h3>
            <?php if ( is_user_logged_in() ) :
              global $current_user;
              wp_get_current_user(); ?>
              <p class="pz-rev-as"><?php echo esc_html( $current_user->display_name ); ?> olarak giriş yapıldı &nbsp;·&nbsp; <a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">Çıkış yap</a></p>
            <?php else : ?>
              <p class="pz-rev-as">Değerlendirme yazmak için <a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">giriş yapın</a>.</p>
            <?php endif; ?>
            <form id="pzReviewForm" method="post" action="<?php echo esc_url( site_url( '/wp-comments-post.php' ) ); ?>">
              <div class="pz-rev-stars-wrap">
                <span class="pz-rev-label">Puanınız <em>*</em></span>
                <div class="pz-rev-stars" id="pzRevStars" data-hint="">
                  <?php for ( $s = 5; $s >= 1; $s-- ) : ?>
                    <input type="radio" name="rating" id="pzStar<?php echo $s; ?>" value="<?php echo $s; ?>">
                    <label for="pzStar<?php echo $s; ?>" title="<?php echo $s; ?> yıldız">★</label>
                  <?php endfor; ?>
                </div>
                <span class="pz-rev-star-hint" id="pzRevStarHint"></span>
              </div>
              <div class="pz-rev-field">
                <label for="pzRevComment">Yorumunuz <em>*</em></label>
                <textarea id="pzRevComment" name="comment" rows="4" required minlength="10" placeholder="Ürün hakkındaki deneyiminizi paylaşın (en az 10 karakter)…"></textarea>
              </div>
              <input type="hidden" name="comment_post_ID" value="<?php echo esc_attr( get_the_ID() ); ?>">
              <input type="hidden" name="comment_parent" value="0">
              <?php wp_nonce_field( 'comment_nonce_field', 'comment_nonce' ); ?>
              <button type="submit" class="pz-rev-submit">Gönder</button>
              <p class="pz-rev-msg" id="pzRevMsg" style="display:none;"></p>
            </form>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <!-- SIMILAR -->
      <div class="tab-pane" id="t-sim">
        <div class="sim-grid">
          <?php
            // AKILLI BENZER ÜRÜNLER: önce aynı kategori, sonra etiket, sonra marka — mevcut ürün hariç
            $related_ids = array();
            $cur_id = $product->get_id();
            // 1) KATI: önce aynı YAPRAK kategori, yetmezse KARDEŞ kategoriler (cache'li)
            $cur_cat_objs = wp_get_post_terms( $cur_id, 'product_cat' );
            $cur_cats = array(); $cur_parent_ids = array();
            if ( ! empty( $cur_cat_objs ) && ! is_wp_error( $cur_cat_objs ) ) {
              $deep_c = array(); $shallow_c = array();
              foreach ( $cur_cat_objs as $tt ) {
                if ( $tt->slug === 'uncategorized' ) continue;
                $tt_children_c = wp_cache_get( 'pz_termch_' . $tt->term_id, 'pz_terms' );
                if ( $tt_children_c === false ) {
                  $tt_children = get_term_children( $tt->term_id, 'product_cat' );
                  wp_cache_set( 'pz_termch_' . $tt->term_id, $tt_children, 'pz_terms', 600 );
                } else { $tt_children = $tt_children_c; }
                $tt_is_leaf = empty( $tt_children );
                if ( $tt_is_leaf && $tt->parent > 0 ) {
                  $deep_c[] = $tt->term_id;
                  if ( ! in_array( $tt->parent, $cur_parent_ids ) ) $cur_parent_ids[] = $tt->parent;
                } elseif ( $tt_is_leaf && $tt->parent === 0 ) {
                  $shallow_c[] = $tt->term_id;
                }
              }
              $cur_cats = ! empty( $deep_c ) ? $deep_c : $shallow_c;
            }
            if ( ! empty( $cur_cats ) ) {
              $q1 = new WP_Query( array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => 8,
                'post__not_in' => array( $cur_id ),
                'orderby' => 'rand',
                'fields' => 'ids',
                'tax_query' => array( array( 'taxonomy'=>'product_cat', 'field'=>'term_id', 'terms'=>$cur_cats, 'include_children'=>false ) ),
              ) );
              if ( $q1->have_posts() ) $related_ids = array_merge( $related_ids, $q1->posts );
              wp_reset_postdata();
              // Kardeş kategoriler — yetersizse
              if ( count( $related_ids ) < 4 && ! empty( $cur_parent_ids ) ) {
                $sib_terms = get_terms( array(
                  'taxonomy' => 'product_cat',
                  'parent' => $cur_parent_ids[0],
                  'hide_empty' => true,
                  'fields' => 'ids',
                  'exclude' => $cur_cats,
                ) );
                if ( ! empty( $sib_terms ) && ! is_wp_error( $sib_terms ) ) {
                  $q1b = new WP_Query( array(
                    'post_type' => 'product',
                    'post_status' => 'publish',
                    'posts_per_page' => 8,
                    'post__not_in' => array_merge( array( $cur_id ), $related_ids ),
                    'orderby' => 'rand',
                    'fields' => 'ids',
                    'tax_query' => array( array( 'taxonomy'=>'product_cat', 'field'=>'term_id', 'terms'=>$sib_terms, 'include_children'=>false ) ),
                  ) );
                  if ( $q1b->have_posts() ) $related_ids = array_merge( $related_ids, $q1b->posts );
                  wp_reset_postdata();
                }
              }
            }
            // 2) Yetmiyorsa aynı etiketten doldur
            if ( count( $related_ids ) < 4 ) {
              $cur_tags = wp_get_post_terms( $cur_id, 'product_tag', array( 'fields' => 'ids' ) );
              if ( ! empty( $cur_tags ) && ! is_wp_error( $cur_tags ) ) {
                $q2 = new WP_Query( array(
                  'post_type' => 'product',
                  'post_status' => 'publish',
                  'posts_per_page' => 8,
                  'post__not_in' => array_merge( array( $cur_id ), $related_ids ),
                  'orderby' => 'rand',
                  'fields' => 'ids',
                  'tax_query' => array( array( 'taxonomy'=>'product_tag', 'field'=>'term_id', 'terms'=>$cur_tags ) ),
                ) );
                if ( $q2->have_posts() ) $related_ids = array_merge( $related_ids, $q2->posts );
                wp_reset_postdata();
              }
            }
            // 3) Hâlâ yetmiyorsa aynı markadan doldur
            if ( count( $related_ids ) < 4 ) {
              $cur_brands = wp_get_post_terms( $cur_id, 'product_brand', array( 'fields' => 'ids' ) );
              if ( ! empty( $cur_brands ) && ! is_wp_error( $cur_brands ) ) {
                $q3 = new WP_Query( array(
                  'post_type' => 'product',
                  'post_status' => 'publish',
                  'posts_per_page' => 8,
                  'post__not_in' => array_merge( array( $cur_id ), $related_ids ),
                  'orderby' => 'rand',
                  'fields' => 'ids',
                  'tax_query' => array( array( 'taxonomy'=>'product_brand', 'field'=>'term_id', 'terms'=>$cur_brands ) ),
                ) );
                if ( $q3->have_posts() ) $related_ids = array_merge( $related_ids, $q3->posts );
                wp_reset_postdata();
              }
            }
            // 4 ile sınırla, tekrarsız
            $related_ids = array_unique( $related_ids );
            $related_ids = array_slice( $related_ids, 0, 4 );
            if ( $related_ids ) :
              foreach ( $related_ids as $rid ) :
                $rp = wc_get_product( $rid );
                if ( ! $rp ) continue;
                $r_avg       = $rp->get_average_rating();
                $r_stars     = str_repeat('★', round($r_avg)) . str_repeat('☆', 5 - round($r_avg));
                $r_brand_terms = wp_get_post_terms( $rid, 'product_brand' );
                $r_brand     = ( ! empty( $r_brand_terms ) && ! is_wp_error( $r_brand_terms ) ) ? strtoupper( $r_brand_terms[0]->name ) : '';
                $r_price     = (float) $rp->get_price();
                $r_regular   = (float) $rp->get_regular_price();
                $r_disc      = ( $rp->is_on_sale() && $r_regular > 0 && $r_price > 0 ) ? round( (1 - $r_price / $r_regular) * 100 ) : 0;
                $r_img_id    = $rp->get_image_id();
                $r_img_url   = $r_img_id ? wp_get_attachment_image_url( $r_img_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src();
                $r_comp_data = wp_json_encode( array(
                  'id'      => $rid,
                  'url'     => get_permalink( $rid ),
                  'title'   => $rp->get_name(),
                  'img'     => $r_img_url,
                  'price'   => $r_price,
                  'regular' => $r_regular,
                  'discount'=> $r_disc,
                  'rating'  => round( (float) $rp->get_average_rating(), 1 ),
                  'reviews' => $rp->get_review_count(),
                  'inStock' => $rp->is_in_stock(),
                  'freeShip'=> ( $r_price >= 1500 ),
                  'seller'  => get_bloginfo('name'),
                  'attrs'   => array(),
                ) );
          ?>
            <div class="sim-card pcard"
              data-href="<?php echo esc_url( get_permalink( $rid ) ); ?>"
              data-product-id="<?php echo esc_attr( $rid ); ?>"
              data-pzcomp="<?php echo esc_attr( $r_comp_data ); ?>"
              onclick="if(!event.target.closest('button,a'))window.location.href=this.dataset.href"
              style="cursor:pointer">
              <div class="sim-img">
                <?php if ( $r_disc > 0 ) : ?><span class="sim-disc-badge">%<?php echo esc_html( $r_disc ); ?></span><?php endif; ?>
                <?php echo $rp->get_image( 'woocommerce_thumbnail' ); ?>
                <button class="sim-fav" data-pid="<?php echo esc_attr( $rid ); ?>"
                  onclick="event.stopPropagation();pzToggleFav(this,<?php echo (int)$rid; ?>,'<?php echo esc_js( $rp->get_name() ); ?>','<?php echo esc_js( $r_img_url ); ?>','<?php echo esc_js( get_permalink( $rid ) ); ?>')">
                  <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                </button>
              </div>
              <div class="sim-body">
                <?php if ( $r_brand ) : ?><div class="sim-br"><?php echo esc_html( $r_brand ); ?></div><?php endif; ?>
                <div class="sim-n"><?php echo esc_html( $rp->get_name() ); ?></div>
                <div class="sim-rat">
                  <span class="sim-st"><?php echo $r_stars; ?></span>
                  <span class="sim-rn"><?php echo esc_html( number_format( (float) $r_avg, 1 ) ); ?></span>
                  <?php if ( $rp->get_review_count() > 0 ) : ?><span class="sim-rc">(<?php echo esc_html( $rp->get_review_count() ); ?>)</span><?php endif; ?>
                </div>
                <div class="sim-pr">
                  <?php if ( $r_disc > 0 ) : ?><span class="sim-po"><?php echo wc_price( $r_regular ); ?></span><?php endif; ?>
                  <?php if ( $r_price > 0 ) : ?>
                    <span class="sim-p"><?php echo wc_price( $r_price ); ?></span>
                  <?php else : ?>
                    <span class="sim-p-req">Fiyat için sorun</span>
                  <?php endif; ?>
                  <?php if ( $r_disc > 0 ) : ?><span class="sim-ds">%<?php echo esc_html( $r_disc ); ?></span><?php endif; ?>
                </div>
                <button class="sim-comp pcomp-btn" onclick="event.stopPropagation();pzToggleComp(this)">
                  <svg class="pcomp-ico-cmp" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 8L22 12L18 16M6 8L2 12L6 16M14 4L10 20"/></svg>
                  <svg class="pcomp-ico-chk" viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" style="display:none"><polyline points="20 6 9 17 4 12"/></svg>
                  <span class="pcomp-lbl">Karşılaştır</span>
                </button>
              </div>
            </div>
          <?php endforeach; else : ?>
            <p style="grid-column:1/-1;text-align:center;color:var(--muted);padding:20px">Benzer ürün bulunamadı.</p>
          <?php endif; ?>
        </div>
      </div>
      <!-- Q&A -->
      <div class="tab-pane" id="t-qa">
        <!-- Soru sorma formu -->
        <div class="qa-form-box">
          <div class="qa-form-title">Bu ürün hakkında soru sor</div>
          <form class="qa-form" id="qaForm" onsubmit="return pzSubmitQuestion(event)">
            <input type="hidden" id="qaProductId" value="<?php echo esc_attr( $product->get_id() ); ?>">
            <div class="qa-form-row">
              <input type="text" id="qaName" class="qa-input-name" placeholder="Adınız" required>
            </div>
            <div class="qa-form-row">
              <input type="text" id="qaQuestion" class="qa-input-q" placeholder="Sorunuzu yazın..." required>
              <button type="submit" class="qa-ask" id="qaSubmitBtn">Soru Sor</button>
            </div>
            <div class="qa-form-note" id="qaFormNote" style="display:none;"></div>
          </form>
          <!-- Mevcut sorularda arama -->
          <div class="qa-searchbar">
            <input type="text" id="qaSearchInput" placeholder="🔍 Sorularda ara..." onkeyup="pzFilterQuestions(this.value)">
          </div>
        </div>

        <!-- Soru listesi (dinamik) -->
        <div class="qa-list" id="qaList">
          <?php
            // Cevaplama yetkisi (satıcı/yönetici)
            $pz_can_answer = function_exists('pz_can_answer_product') ? pz_can_answer_product( $product->get_id() ) : current_user_can('manage_options');
            // Bu ürüne ait onaylanmış soruları çek (comment_type = pz_question)
            $pz_questions = get_comments( array(
              'post_id' => $product->get_id(),
              'type'    => 'pz_question',
              'status'  => 'approve',
              'orderby' => 'comment_date',
              'order'   => 'DESC',
            ) );
            if ( ! empty( $pz_questions ) ) :
              foreach ( $pz_questions as $q ) :
                $q_author = $q->comment_author ? $q->comment_author : 'Misafir';
                $q_date   = human_time_diff( strtotime( $q->comment_date ), current_time('timestamp') ) . ' önce';
                // Cevap: bu sorunun meta'sında saklı (satıcı/yönetici cevabı)
                $q_answer = get_comment_meta( $q->comment_ID, 'pz_answer', true );
          ?>
            <div class="qa-item" data-q="<?php echo esc_attr( mb_strtolower( $q->comment_content ) ); ?>">
              <div class="qa-q"><span class="qa-ico qa-ico-q">S</span><?php echo esc_html( $q->comment_content ); ?></div>
              <div class="qa-meta"><?php echo esc_html( $q_author ); ?> tarafından soruldu · <?php echo esc_html( $q_date ); ?></div>
              <?php if ( $q_answer ) : ?>
                <div class="qa-a"><span class="qa-ico qa-ico-a">C</span><span class="qa-a-text"><?php echo esc_html( $q_answer ); ?></span></div>
              <?php else : ?>
                <div class="qa-a qa-a-pending" id="qaPending-<?php echo (int) $q->comment_ID; ?>"><span class="qa-ico qa-ico-a">C</span>Bu soru henüz yanıtlanmadı. Satıcı en kısa sürede yanıtlayacak.</div>
              <?php endif; ?>
              <?php if ( $pz_can_answer ) : ?>
                <div class="qa-answer-form" data-cid="<?php echo (int) $q->comment_ID; ?>">
                  <textarea class="qa-answer-input" placeholder="<?php echo $q_answer ? 'Cevabı düzenle...' : 'Bu soruyu yanıtlayın...'; ?>"><?php echo esc_textarea( $q_answer ); ?></textarea>
                  <button type="button" class="qa-answer-btn" onclick="pzSubmitAnswer(this, <?php echo (int) $q->comment_ID; ?>)"><?php echo $q_answer ? 'Cevabı Güncelle' : 'Yanıtla'; ?></button>
                </div>
              <?php endif; ?>
            </div>
          <?php
              endforeach;
            else :
          ?>
            <div class="qa-empty" id="qaEmpty">
              <div class="qa-empty-ico">💬</div>
              <div class="qa-empty-title">Henüz soru sorulmamış</div>
              <div class="qa-empty-text">Bu ürün hakkında ilk soruyu siz sorun!</div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══ ALTERNATİF ÜRÜN SEÇENEKLERİ (Hepsiburada tarzı) ═══ -->
<div class="cw">
  <?php
    // AKILLI ALTERNATİF ÜRÜNLER — KATI KURAL: önce aynı yaprak kategori, yoksa kardeş kategoriler
    // Cache anahtarı ile hızlandırılmış
    $pz_alt_cache_key = 'pz_alt_' . $product->get_id();
    $pz_alt_cached = get_transient( $pz_alt_cache_key );
    $alt_cat_objs = wp_get_post_terms( $product->get_id(), 'product_cat' );
    $alt_cats = array(); $alt_parent_ids = array(); $alt_used_ids = array();
    if ( ! empty( $alt_cat_objs ) && ! is_wp_error( $alt_cat_objs ) ) {
      $deepest = array(); $shallow = array();
      foreach ( $alt_cat_objs as $term ) {
        if ( $term->slug === 'uncategorized' ) continue;
        // Bu kategorinin alt kategorileri var mı? (cache'li)
        $children_cache = wp_cache_get( 'pz_termch_' . $term->term_id, 'pz_terms' );
        if ( $children_cache === false ) {
          $children = get_term_children( $term->term_id, 'product_cat' );
          wp_cache_set( 'pz_termch_' . $term->term_id, $children, 'pz_terms', 600 );
        } else {
          $children = $children_cache;
        }
        $is_leaf = empty( $children );
        if ( $is_leaf && $term->parent > 0 ) {
          // YAPRAK alt kategori (en spesifik)
          $deepest[] = $term->term_id;
          if ( ! in_array( $term->parent, $alt_parent_ids ) ) $alt_parent_ids[] = $term->parent;
        } elseif ( $is_leaf && $term->parent === 0 ) {
          // Üst seviye ama altı yok
          $shallow[] = $term->term_id;
        }
      }
      $alt_cats = ! empty( $deepest ) ? $deepest : $shallow;
      $alt_used_ids = $alt_cats;
    }
    $alt_q = null;
    if ( ! empty( $alt_cats ) && $pz_alt_cached === false ) {
      // 1. Önce SADECE aynı yaprak kategoriden dene
      $alt_q = new WP_Query( array(
        'post_type'      => 'product',
        'posts_per_page' => 12,
        'post_status'    => 'publish',
        'post__not_in'   => array( $product->get_id() ),
        'orderby'        => 'rand',
        'tax_query'      => array( array(
          'taxonomy' => 'product_cat',
          'field'    => 'term_id',
          'terms'    => $alt_cats,
          'include_children' => false,
        ) ),
      ) );
      // 2. Bu kategoride yeterli ürün yoksa, KARDEŞ kategorilere bak
      if ( $alt_q->found_posts < 2 && ! empty( $alt_parent_ids ) ) {
        $siblings = get_terms( array(
          'taxonomy' => 'product_cat',
          'parent' => $alt_parent_ids[0],
          'hide_empty' => true,
          'fields' => 'ids',
          'exclude' => $alt_used_ids,
        ) );
        if ( ! empty( $siblings ) && ! is_wp_error( $siblings ) ) {
          $alt_q = new WP_Query( array(
            'post_type'      => 'product',
            'posts_per_page' => 12,
        'no_found_rows' => true, 'update_post_term_cache' => false,
            'post_status'    => 'publish',
            'post__not_in'   => array( $product->get_id() ),
            'orderby'        => 'rand',
            'tax_query'      => array( array(
              'taxonomy' => 'product_cat',
              'field'    => 'term_id',
              'terms'    => array_merge( $alt_cats, $siblings ),
              'include_children' => false,
            ) ),
          ) );
        }
      }
    }
    if ( $pz_alt_cached !== false ) {
      $alt_post_ids = $pz_alt_cached;
      $alt_q = new WP_Query( array(
        'post_type' => 'product',
        'post_status' => 'publish',
        'posts_per_page' => count( $alt_post_ids ),
        'post__in' => $alt_post_ids,
        'orderby' => 'post__in',
        'ignore_sticky_posts' => true,
      ) );
    } elseif ( $alt_q && $alt_q->have_posts() ) {
      $alt_post_ids = wp_list_pluck( $alt_q->posts, 'ID' );
      set_transient( $pz_alt_cache_key, $alt_post_ids, 5 * MINUTE_IN_SECONDS );
    }
    if ( $alt_q && $alt_q->have_posts() ) :
      if ( true ) :
  ?>
  <div class="alt-products">
    <div class="alt-products-head">
      <h2 class="alt-products-title">Alternatif Ürün Seçenekleri</h2>
      <div class="alt-products-nav">
        <button class="alt-nav-btn" onclick="altScroll(-1)" aria-label="Önceki">‹</button>
        <button class="alt-nav-btn" onclick="altScroll(1)" aria-label="Sonraki">›</button>
      </div>
    </div>
    <div class="alt-products-row" id="altProductsRow">
      <?php while ( $alt_q->have_posts() ) : $alt_q->the_post(); global $product; echo bazario_product_card( $product ); endwhile; wp_reset_postdata(); ?>
    </div>
  </div>
  <?php
      endif;
    endif;
    // Tekrar mevcut ürünü global yap (alt taraf için)
    global $product;
    $product = wc_get_product( get_the_ID() );
  ?>
</div>

<!-- ═══ SON GEZİLEN ÜRÜNLER (JS ile doldurulur, localStorage) ═══ -->
<div class="cw" id="recentlyViewedWrap" data-rv-wrap="1" data-rv-exclude="0" style="display:none;">
  <div class="recent-products">
    <div class="recent-products-head">
      <h2 class="recent-products-title">🕐 Son Gezdiğin Ürünler</h2>
      <button type="button" class="recent-clear" onclick="pzClearRecent()" aria-label="Temizle">Temizle</button>
    </div>
    <div class="recent-products-row" id="recentProductsRow" data-rv-row="1"></div>
  </div>
</div>

<!-- Mevcut ürün ID + JS için kayıt (görünmez) -->
<script>window.pzCurrentProductId=<?php echo (int) $product->get_id(); ?>;</script>

<!-- FOOTER -->

<div class="toast" id="toast"></div>
<?php
endwhile;
get_footer();
