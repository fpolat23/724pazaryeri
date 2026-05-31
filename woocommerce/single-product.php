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
        <button class="hb-fav" id="imgFav" onclick="toggleImgFav()" aria-label="Favorilere ekle">🤍</button>
        <img loading="lazy" decoding="async" id="mainImgEl" src="<?php echo esc_url( $main_full ); ?>" alt="<?php the_title_attribute(); ?>">
        <div class="hb-zoom-hint">🔍 İncelemek için üzerine gelin</div>
      </div>
    </div>

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

      <?php
        // Dokan satıcı bilgisi
        $vendor_html = '';
        if ( function_exists( 'dokan_get_vendor_by_product' ) ) {
          $vendor = dokan_get_vendor_by_product( $product->get_id() );
          if ( $vendor ) {
            $shop_name = $vendor->get_shop_name();
            $shop_url  = $vendor->get_shop_url();
            $v_rating  = $vendor->get_rating();
            $v_rval    = is_array($v_rating) && isset($v_rating['rating']) ? (float)$v_rating['rating'] : 5.0;
            ?>
            <div class="hb-seller-line">
              <span class="hb-seller-label">Satıcı:</span>
              <a class="hb-seller-name" href="<?php echo esc_url( $shop_url ); ?>"><?php echo esc_html( $shop_name ); ?></a>
              <span class="hb-seller-rating">★ <?php echo esc_html( number_format($v_rval,1) ); ?></span>
              <a class="hb-seller-visit" href="<?php echo esc_url( $shop_url ); ?>">Mağazaya Git ›</a>
            </div>
          <?php }
        } ?>

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
        <div class="hb-del-row"><span class="hb-del-ico">🚚</span><div><strong>1500₺ Üzeri Ücretsiz Kargo</strong><br><small>Tahmini teslimat: <?php echo esc_html( date_i18n( 'j F', strtotime('+2 days') ) ); ?> - <?php echo esc_html( date_i18n( 'j F', strtotime('+4 days') ) ); ?></small></div></div>
        <div class="hb-del-row"><span class="hb-del-ico">⚡</span><div><strong>Hızlı Teslimat</strong><br><small class="pship-txt">Kargo bilgisi hesaplanıyor…</small></div></div>
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

      <!-- Güven rozetleri -->
      <div class="hb-trust">
        <div class="hb-trust-item">🔒 Güvenli Ödeme</div>
        <div class="hb-trust-item">🔄 14 Gün İade</div>
        <div class="hb-trust-item">✓ Orijinal Ürün</div>
        <div class="hb-trust-item">📞 7/24 Destek</div>
      </div>

      <?php
        // ── DİĞER SATICILAR (Dokan) ──
        // Aynı isimli/benzer ürünü satan diğer Dokan satıcılarını bul
        $other_offers = array();
        if ( function_exists( 'dokan_get_vendor_by_product' ) ) {
          $current_vendor = dokan_get_vendor_by_product( $product->get_id() );
          $current_vid = $current_vendor ? $current_vendor->get_id() : 0;
          // Aynı SKU veya aynı başlıkla diğer ürünleri bul
          $title = $product->get_name();
          $similar = new WP_Query( array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 6,
        'no_found_rows' => true, 'update_post_term_cache' => false,
            'post__not_in'   => array( $product->get_id() ),
            's'              => $title,
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_price',
            'order'          => 'ASC',
          ) );
          if ( $similar->have_posts() ) {
            while ( $similar->have_posts() ) { $similar->the_post();
              $op = wc_get_product( get_the_ID() );
              if ( ! $op ) continue;
              $ov = dokan_get_vendor_by_product( get_the_ID() );
              if ( ! $ov ) continue;
              $other_offers[] = array(
                'vendor_name' => $ov->get_shop_name(),
                'vendor_url'  => $ov->get_shop_url(),
                'price'       => $op->get_price(),
                'rating'      => $ov->get_rating(),
                'product_url' => get_permalink( get_the_ID() ),
              );
            }
            wp_reset_postdata();
          }
        }
        if ( ! empty( $other_offers ) ) :
      ?>
      <div class="hb-other-sellers">
        <div class="hb-os-title">🏪 Bu üründe <?php echo count( $other_offers ); ?> farklı satıcı</div>
        <?php foreach ( array_slice( $other_offers, 0, 4 ) as $offer ) :
          $r = $offer['rating'];
          $rval = is_array($r) && isset($r['rating']) ? (float)$r['rating'] : 5.0; ?>
          <a class="hb-os-item" href="<?php echo esc_url( $offer['product_url'] ); ?>">
            <div class="hb-os-info">
              <span class="hb-os-name"><?php echo esc_html( $offer['vendor_name'] ); ?></span>
              <span class="hb-os-rating">★ <?php echo esc_html( number_format($rval,1) ); ?></span>
            </div>
            <div class="hb-os-price"><?php echo wc_price( $offer['price'] ); ?></div>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
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
                <div class="pd-taxbox-title">Etiketler</div>
                <div class="pd-taxbox-links">
                  <?php foreach ( $pd_tags as $pd_tag ) : ?>
                    <a class="pd-tax-link pd-tax-tag" href="<?php echo esc_url( get_term_link( $pd_tag ) ); ?>">#<?php echo esc_html( $pd_tag->name ); ?></a>
                  <?php endforeach; ?>
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
        <table class="specs-table">
          <?php
            // Ürün nitelikleri (Ürün > Nitelikler sekmesinden gelir)
            $display_attrs = $product->get_attributes();
            if ( ! empty( $display_attrs ) ) :
              foreach ( $display_attrs as $attr ) :
                $name = pz_attr_label( $attr->get_name() );
                if ( $attr->is_taxonomy() ) {
                  $values = wc_get_product_terms( $product->get_id(), $attr->get_name(), array( 'fields' => 'names' ) );
                } else {
                  $values = $attr->get_options();
                }
                $value_str = implode( ', ', $values );
          ?>
            <tr><td><?php echo esc_html( $name ); ?></td><td><?php echo esc_html( $value_str ); ?></td></tr>
          <?php endforeach; else : ?>
            <tr><td colspan="2" style="text-align:center;color:var(--muted)">Bu ürün için teknik özellik girilmemiş.</td></tr>
          <?php endif; ?>
          <?php
            // SKU & ağırlık & boyut (WooCommerce gönderim sekmesi)
            if ( $product->get_sku() ) : ?><tr><td>Ürün Kodu (SKU)</td><td><?php echo esc_html( $product->get_sku() ); ?></td></tr><?php endif;
            if ( $product->get_weight() ) : ?><tr><td>Ağırlık</td><td><?php echo esc_html( $product->get_weight() ) . ' ' . esc_html( get_option('woocommerce_weight_unit') ); ?></td></tr><?php endif;
            if ( $product->has_dimensions() ) : ?><tr><td>Boyutlar</td><td><?php echo esc_html( wc_format_dimensions( $product->get_dimensions(false) ) ); ?></td></tr><?php endif;
          ?>
        </table>
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
                $r_avg = $rp->get_average_rating();
                $r_stars = str_repeat('★', round($r_avg)) . str_repeat('☆', 5 - round($r_avg));
                $r_brand_terms = wp_get_post_terms( $rid, 'product_brand' );
                $r_brand = ( ! empty( $r_brand_terms ) && ! is_wp_error( $r_brand_terms ) ) ? strtoupper( $r_brand_terms[0]->name ) : '';
          ?>
            <div class="sim-card" data-href="<?php echo esc_url( get_permalink( $rid ) ); ?>" onclick="window.location.href=this.dataset.href" style="cursor:pointer">
              <div class="sim-img"><?php echo $rp->get_image( 'woocommerce_thumbnail' ); ?></div>
              <div class="sim-body">
                <?php if ( $r_brand ) : ?><div class="sim-br"><?php echo esc_html( $r_brand ); ?></div><?php endif; ?>
                <div class="sim-n"><?php echo esc_html( $rp->get_name() ); ?></div>
                <div class="sim-rat"><span class="sim-st"><?php echo $r_stars; ?></span><span class="sim-rn"><?php echo esc_html( $r_avg ); ?></span></div>
                <div class="sim-pr">
                  <span class="sim-p"><?php echo wc_price( $rp->get_price() ); ?></span>
                  <?php if ( $rp->is_on_sale() && $rp->get_regular_price() ) : ?><span class="sim-po"><?php echo wc_price( $rp->get_regular_price() ); ?></span><?php endif; ?>
                </div>
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
