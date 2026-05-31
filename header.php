<?php
/**
 * Tema header — 724PazarYeri
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'pazaryeri-site' ); ?>>
<?php wp_body_open(); ?>
<div class="pazaryeri-wrap">
<!-- OVERLAY -->
<div class="overlay" id="overlay" onclick="closeAll()"></div>

<!-- ANNOUNCE -->
<div class="announce">🚀 Yeni satıcılara özel: İlk 3 ay <em>komisyon sıfır!</em> — <a href="<?php echo esc_url( function_exists('pazaryeri_become_seller_url') ? pazaryeri_become_seller_url() : home_url('/magaza-ac/') ); ?>" style="color:#fff;text-decoration:underline;">Hemen mağaza aç</a></div>

<!-- HEADER -->
<div class="header">

  <!-- ═══ MOBİL HEADER (temiz, sadece mobilde) ═══ -->
  <div class="m-header">
    <div class="m-header-top">
      <button class="m-burger" onclick="pzToggleMobileMenu()" aria-label="Menü">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><circle cx="5" cy="5" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="19" cy="5" r="2"/><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/></svg>
      </button>
      <a class="m-logo" href="<?php echo esc_url( home_url('/') ); ?>">
        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo-icon.png' ); ?>" alt="" class="m-logo-icon" loading="eager" decoding="async">
        <span class="m-logo-txt"><span class="m-lg-724">724</span><span class="m-lg-pz">PazarYeri</span><span class="m-lg-com">.com</span></span>
      </a>
      <div class="m-actions">
        <a class="m-act" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="Sepet">
          <svg viewBox="0 0 24 24" width="23" height="23" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
          <?php $mh_count = ( function_exists('WC') && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0; if ( $mh_count > 0 ) : ?><span class="m-badge"><?php echo esc_html( $mh_count ); ?></span><?php endif; ?>
        </a>
      </div>
    </div>
    <form class="m-search pz-ai-search" role="search" method="get" action="<?php echo esc_url( home_url('/') ); ?>" autocomplete="off">
      <input type="search" name="s" class="pz-ai-input" placeholder="Ürün, mağaza veya marka ara..." autocomplete="off">
      <input type="hidden" name="post_type" value="product">
      <button type="submit" aria-label="Ara">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#fff" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </button>
      <div class="pz-ai-dropdown" role="listbox"></div>
    </form>
  </div>

  <div class="header-inner">

    <!-- MOBİL HAMBURGER (sadece mobilde görünür) -->
    <button class="mobile-burger" id="mobileBurger" onclick="pzToggleMobileMenu()" aria-label="Menü">
      <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><circle cx="5" cy="5" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="19" cy="5" r="2"/><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/><circle cx="5" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><circle cx="19" cy="19" r="2"/></svg>
    </button>

    <!-- LOGO -->
    <a class="logo-wrap" href="<?php echo esc_url( home_url('/') ); ?>" onclick="closeAll()" style="text-decoration:none;color:inherit;">
      <img class="logo-icon" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/logo-icon.png' ); ?>" alt="" loading="eager" decoding="async" />
      <div class="logo-text"><span class="lg-724">724</span><span class="lg-pz">PazarYeri</span><span class="lg-com">.com</span></div>
    </a>

    <!-- DELIVER -->
    <div class="deliver-btn" onclick="toggleDrop('drop-deliver','btn-deliver')">
      <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
      <div>
        <div class="deliver-top">Teslimat yeri</div>
        <div class="deliver-city">İzmir 35xxx</div>
      </div>
    </div>

    <!-- SEARCH -->
    <div style="flex:1;position:relative;" id="search-wrap">
          <!-- MOBİL SAĞ İKONLAR (arama/bildirim) -->
    <div class="mob-head-icons">
      <button type="button" class="mhi-btn" onclick="document.querySelector('.search-bar input')?.focus()" aria-label="Ara">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      </button>
      <button type="button" class="mhi-btn" aria-label="Bildirimler">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      </button>
    </div>

<div class="search-bar pz-ai-search">
        <button class="search-cat-btn" onclick="toggleDrop('drop-scat','btn-scat')">
          Tüm Kategoriler <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
        <input type="text" placeholder="Ürün, mağaza veya marka ara..." id="search-input" class="pz-ai-input" autocomplete="off">
        <button class="search-go">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
      
        <div class="pz-ai-dropdown" role="listbox"></div>
      </div>
      <!-- search dropdown -->
      <div class="search-drop" id="drop-search">
        <div class="sdrop-section">
          <div class="sdrop-label">Son Aramalar</div>
          <a class="sdrop-item" href="/?s=sony+fotoğraf+makinesi"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>sony fotoğraf makinesi</a>
          <a class="sdrop-item" href="/?s=el+yapımı+seramik"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>el yapımı seramik</a>
          <a class="sdrop-item" href="/?s=macbook+m3"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 15"/></svg>macbook m3</a>
        </div>
        <div class="sdrop-divider"></div>
        <div class="sdrop-section">
          <div class="sdrop-label">Popüler Aramalar</div>
          <a class="sdrop-item" href="/?s=vintage+deri+çanta"><svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>vintage deri çanta</a>
          <a class="sdrop-item" href="/?s=orijinal+yağlıboya+tablo"><svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>orijinal yağlıboya tablo</a>
          <a class="sdrop-item" href="/?s=elektro+gitar+fender"><svg viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>elektro gitar fender</a>
        </div>
        <div class="sdrop-divider"></div>
        <div class="sdrop-section">
          <div class="sdrop-label">Kategoriler</div>
          <div class="sdrop-tags">
            <a class="sdrop-tag" href="/product-category/elektronik/">📱 Elektronik</a>
            <a class="sdrop-tag" href="/product-category/moda/">👗 Moda</a>
            <a class="sdrop-tag" href="/product-category/ev-dekor/">🏠 Ev & Dekor</a>
            <a class="sdrop-tag" href="/product-category/el-yapimi/">🎨 El Yapımı</a>
            <a class="sdrop-tag" href="/product-category/kitap-ve-hobi/">📚 Kitap</a>
          </div>
        </div>
      </div>
      <!-- search cat dropdown -->
      <div class="nav-drop" id="drop-scat" style="left:0;right:auto;min-width:230px;max-height:420px;overflow-y:auto;top:calc(100% + 8px);">
        <a class="ndrop-row" href="<?php echo esc_url( wc_get_page_permalink('shop') ); ?>" style="text-decoration:none;color:inherit;"><div class="ndrop-icon">🛍️</div><div class="ndrop-label">Tüm Kategoriler</div></a>
        <?php
          // Arama kategori menüsü: ana kategorilerden dinamik (görsel varsa görsel)
          $scat_emojis = array('elektronik'=>'📱','moda'=>'👗','ev-dekor'=>'🏠','el-yapimi'=>'🎨','kitap-ve-hobi'=>'📚','spor'=>'⚽','kozmetik'=>'💄','otomotiv'=>'🚗','anne-ve-bebek'=>'🍼','muzik'=>'🎵');
          $scat_cats = get_terms( array(
            'taxonomy'   => 'product_cat',
            'parent'     => 0,
            'hide_empty' => true,
            'orderby'    => 'name',
            'exclude'    => array( get_option('default_product_cat') ),
          ) );
          if ( ! empty( $scat_cats ) && ! is_wp_error( $scat_cats ) ) :
            foreach ( $scat_cats as $scat ) :
              $scat_thumb_id = get_term_meta( $scat->term_id, 'thumbnail_id', true );
              $scat_img = $scat_thumb_id ? wp_get_attachment_image_url( $scat_thumb_id, 'thumbnail' ) : '';
              $scat_emoji = isset( $scat_emojis[ $scat->slug ] ) ? $scat_emojis[ $scat->slug ] : '📦';
        ?>
          <a class="ndrop-row" href="<?php echo esc_url( get_term_link($scat) ); ?>" style="text-decoration:none;color:inherit;">
            <div class="ndrop-icon"><?php if ( $scat_img ) : ?><img src="<?php echo esc_url($scat_img); ?>" alt="<?php echo esc_attr($scat->name); ?>"><?php else : ?><?php echo $scat_emoji; ?><?php endif; ?></div>
            <div class="ndrop-label"><?php echo esc_html($scat->name); ?></div>
          </a>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <!-- NAV RIGHT -->
    <div class="nav-right">

      <!-- deliver drop -->
      <div class="nav-drop" id="drop-deliver" style="right:auto;left:0;top:58px;position:fixed;margin-left:220px;">
        <div class="ndrop-head"><div class="ndrop-title">📍 Teslimat Konumu</div><div class="ndrop-sub">Mevcut: İzmir, 35xxx</div></div>
        <a href="/my-account/edit-address/billing/" style="text-decoration:none;color:inherit;"><div class="ndrop-row"><div class="ndrop-icon">🏠</div><div><div class="ndrop-label">Ev Adresim</div><div class="ndrop-hint">İzmir Bornova, 35xxx</div></div></div></a>
        <a href="/my-account/edit-address/shipping/" style="text-decoration:none;color:inherit;"><div class="ndrop-row"><div class="ndrop-icon">🏢</div><div><div class="ndrop-label">İş Adresim</div><div class="ndrop-hint">Adres ekle</div></div></div></a>
        <a href="/my-account/edit-address/" style="text-decoration:none;color:inherit;"><div class="ndrop-row"><div class="ndrop-icon">➕</div><div><div class="ndrop-label">Yeni Adres Ekle</div></div></div></a>
        <a href="/my-account/edit-address/" class="ndrop-btn" style="text-decoration:none;">Güncelle</a>
      </div>

      <!-- ACCOUNT -->
      <div class="nav-btn" id="btn-account" onclick="toggleDrop('drop-account','btn-account')">
        <span class="nav-top">Merhaba, Giriş Yap</span>
        <span class="nav-bot">Hesabım ▾</span>
        <div class="nav-drop" id="drop-account">
          <div class="ndrop-head"><div class="ndrop-title">Hesabıma Git</div><div class="ndrop-sub">Üye girişi veya yeni kayıt</div></div>
          <a href="/my-account/edit-account/" style="text-decoration:none;color:inherit;"><div class="ndrop-row"><div class="ndrop-icon">👤</div><div><div class="ndrop-label">Profilim</div><div class="ndrop-hint">Bilgilerimi düzenle</div></div></div></a>
          <a href="/my-account/orders/" style="text-decoration:none;color:inherit;"><div class="ndrop-row"><div class="ndrop-icon">📦</div><div><div class="ndrop-label">Siparişlerim</div><div class="ndrop-hint">Takip et & yönet</div></div></div></a>
          <?php if ( ( class_exists("PZV_Roles") && PZV_Roles::is_vendor() ) || current_user_can("manage_woocommerce") ) : ?><a href="<?php echo esc_url( home_url("/saticim/") ); ?>" style="text-decoration:none;color:inherit;"><div class="ndrop-row" style="background:linear-gradient(135deg,#fff5ec,#ffe9d4);border-radius:8px;margin:4px 0;"><div class="ndrop-icon">🏪</div><div><div class="ndrop-label" style="color:#ff6a00;font-weight:700;">Mağazam</div><div class="ndrop-hint">Satıcı paneline git</div></div></div></a><?php endif; ?>
          <a href="/my-account/wishlist/" style="text-decoration:none;color:inherit;"><div class="ndrop-row"><div class="ndrop-icon">❤️</div><div><div class="ndrop-label">Favori Listelerim</div></div></div></a>
          <a href="/my-account/points-and-rewards/" style="text-decoration:none;color:inherit;"><div class="ndrop-row"><div class="ndrop-icon">⭐</div><div><div class="ndrop-label">Puanlarım</div><div class="ndrop-hint">1.240 puan</div></div></div></a>
          <a href="/my-account/coupons/" style="text-decoration:none;color:inherit;"><div class="ndrop-row"><div class="ndrop-icon">🎟️</div><div><div class="ndrop-label">Kuponlarım</div><div class="ndrop-hint">3 aktif kupon</div></div></div></a>
          <div class="ndrop-divider"></div>
          <a href="/my-account/" class="ndrop-btn" style="text-decoration:none;">Giriş Yap</a>
          <a href="/my-account/?action=register" class="ndrop-btn out" style="margin-top:0;text-decoration:none;">Üye Ol</a>
        </div>
      </div>

      <!-- RETURNS -->
      <div class="nav-btn" id="btn-orders" onclick="toggleDrop('drop-orders','btn-orders')">
        <span class="nav-top">İadeler &</span>
        <span class="nav-bot">Siparişler ▾</span>
        <div class="nav-drop" id="drop-orders">
          <div class="ndrop-head"><div class="ndrop-title">Siparişlerim</div></div>
          <a href="/my-account/orders/?status=active" style="text-decoration:none;color:inherit;"><div class="ndrop-row"><div class="ndrop-icon">🚚</div><div><div class="ndrop-label">Aktif Siparişler</div><div class="ndrop-hint">2 sipariş yolda</div></div></div></a>
          <a href="/my-account/orders/?status=completed" style="text-decoration:none;color:inherit;"><div class="ndrop-row"><div class="ndrop-icon">✅</div><div><div class="ndrop-label">Tamamlananlar</div><div class="ndrop-hint">14 sipariş</div></div></div></a>
          <a href="/my-account/returns/" style="text-decoration:none;color:inherit;"><div class="ndrop-row"><div class="ndrop-icon">🔄</div><div><div class="ndrop-label">İade Taleplerim</div></div></div></a>
          <a href="/my-account/reviews/" style="text-decoration:none;color:inherit;"><div class="ndrop-row"><div class="ndrop-icon">⭐</div><div><div class="ndrop-label">Değerlendirmelerim</div></div></div></a>
        </div>
      </div>

      <!-- CART -->
      <div class="cart-icon-wrap" id="btn-cart" onclick="toggleDrop('drop-cart','btn-cart')">
        <svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        <span class="cart-count"><?php echo WC()->cart ? esc_html( WC()->cart->get_cart_contents_count() ) : 0; ?></span>
        <span class="cart-label">Sepet</span>
        <div class="nav-drop cart-drop" id="drop-cart">
          <?php
            $cart       = WC()->cart;
            $cart_count = $cart ? $cart->get_cart_contents_count() : 0;
          ?>
          <div class="ndrop-head"><div class="ndrop-title">🛒 Sepetim (<?php echo esc_html( $cart_count ); ?> ürün)</div></div>

          <?php if ( $cart && ! $cart->is_empty() ) : ?>

            <div class="cart-scroll">
            <?php
              $items = $cart->get_cart();
              $last  = array_key_last( $items );
              foreach ( $items as $cart_item_key => $cart_item ) :
                $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) continue;
                $product_name = $_product->get_name();
                $thumbnail    = $_product->get_image( array(42,42) );
                $product_perm = $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '';
                $line_total   = $cart->get_product_subtotal( $_product, $cart_item['quantity'] );
                $qty          = $cart_item['quantity'];
                $border       = ( $cart_item_key === $last ) ? ' style="border:none"' : '';
            ?>
              <div class="cart-item"<?php echo $border; ?>>
                <div class="ci-img"><?php echo $thumbnail; ?></div>
                <div style="flex:1;min-width:0">
                  <a class="ci-name" href="<?php echo esc_url( $product_perm ); ?>" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $product_name ); ?></a>
                  <div class="ci-meta-row">
                    <span class="ci-qty-badge"><?php echo esc_html( $qty ); ?> adet</span>
                    <span class="ci-price"><?php echo wp_kses_post( $line_total ); ?></span>
                  </div>
                </div>
                <a class="ci-remove" href="<?php echo esc_url( wc_get_cart_remove_url( $cart_item_key ) ); ?>" title="Kaldır" onclick="event.stopPropagation();">✕</a>
              </div>
            <?php endforeach; ?>
            </div>

            <div class="cart-total">
              <span style="font-size:12px;color:var(--muted)">Toplam:</span>
              <span class="ct-total"><?php echo wp_kses_post( $cart->get_cart_subtotal() ); ?></span>
            </div>
            <a class="ndrop-btn" href="<?php echo esc_url( wc_get_cart_url() ); ?>" style="margin-top:10px;text-decoration:none;">Sepete Git →</a>
            <a class="ndrop-btn out" href="<?php echo esc_url( wc_get_checkout_url() ); ?>" style="margin-top:0;text-decoration:none;">Ödemeye Geç</a>
            <div style="height:8px"></div>

          <?php else : ?>

            <div class="cart-empty">
              <div class="cart-empty-ico">🛒</div>
              <div class="cart-empty-t">Sepetin boş</div>
              <div class="cart-empty-s">Hemen alışverişe başla, fırsatları kaçırma!</div>
              <a class="ndrop-btn" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" style="text-decoration:none;">Alışverişe Başla →</a>
            </div>

          <?php endif; ?>
        </div>
      </div>

      <a class="sell-btn" href="<?php echo esc_url( function_exists('pazaryeri_become_seller_url') ? pazaryeri_become_seller_url() : home_url('/magaza-ac/') ); ?>" style="text-decoration:none;display:inline-block;">+ Sat</a>
    </div>
  </div>
</div>

<!-- CATEGORY BAR -->
<div class="catbar">
  <div class="catbar-inner">

    <!-- ALL CATS (Amazon hamburger) -->
    <div class="all-cats-btn" id="btn-allcats" onclick="toggleMega()">
      <svg viewBox="0 0 24 24"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      Tüm Kategoriler
    </div>

    <div class="cat-items">
      <a class="ci" href="<?php echo esc_url( home_url('/') ); ?>" onclick="closeAll()">Anasayfa</a>
      <?php
        // Üst menü: ana ürün kategorilerini dinamik çek (en fazla 8)
        $top_cats = get_terms( array(
          'taxonomy'   => 'product_cat',
          'parent'     => 0,
          'hide_empty' => true,
          'number'     => 8,
          'orderby'    => 'name',
          'exclude'    => array( get_option('default_product_cat') ),
        ) );
        if ( ! empty( $top_cats ) && ! is_wp_error( $top_cats ) ) :
          foreach ( $top_cats as $tc ) :
            // Alt kategorisi var mı?
            $tc_children = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>$tc->term_id, 'hide_empty'=>true, 'number'=>1 ) );
            $has_child = ( ! empty( $tc_children ) && ! is_wp_error( $tc_children ) );
            $drop_id = 'cidrop-' . $tc->term_id;
            $ci_id   = 'ci-' . $tc->term_id;
      ?>
        <?php if ( $has_child ) :
          $tc_subs = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>$tc->term_id, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC' ) );
        ?>
          <div class="ci-wrap" onmouseenter="ciOpen('<?php echo esc_js($drop_id); ?>')" onmouseleave="ciClose('<?php echo esc_js($drop_id); ?>')">
            <a class="ci" id="<?php echo esc_attr($ci_id); ?>" href="<?php echo esc_url( get_term_link($tc) ); ?>"><?php echo esc_html($tc->name); ?> ▾</a>
            <div class="ci-drop" id="<?php echo esc_attr($drop_id); ?>">
              <div class="ci-drop-head"><?php echo esc_html($tc->name); ?></div>
              <div class="ci-drop-grid">
                <?php foreach ( $tc_subs as $sub ) :
                  $sub_kids = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>$sub->term_id, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC' ) ); ?>
                  <div class="ci-drop-col">
                    <a class="ci-drop-title" href="<?php echo esc_url( get_term_link($sub) ); ?>"><?php echo esc_html($sub->name); ?></a>
                    <?php if ( ! empty($sub_kids) && ! is_wp_error($sub_kids) ) : foreach ( $sub_kids as $kid ) : ?>
                      <a class="ci-drop-link" href="<?php echo esc_url( get_term_link($kid) ); ?>"><?php echo esc_html($kid->name); ?></a>
                    <?php endforeach; endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php else : ?>
          <a class="ci" href="<?php echo esc_url( get_term_link($tc) ); ?>" onclick="closeAll()"><?php echo esc_html($tc->name); ?></a>
        <?php endif; ?>
      <?php endforeach; endif; ?>
      <a class="ci" href="<?php echo esc_url( home_url('/flash-sale/') ); ?>" style="color:var(--accent2);font-weight:600;" onclick="closeAll()">⚡ Flash Satış</a>
    </div>
  </div>
</div>

<!-- MEGA + CI DROPS HOST -->
<div class="mega-host">

  <!-- MEGA (all cats) -->
  <div class="mega-panel" id="mega-panel">
    <?php
      // Mega menü: ana kategoriler + alt kategoriler (dinamik, hiyerarşik)
      $mega_cats = get_terms( array(
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => true,
        'orderby'    => 'name',
        'exclude'    => array( get_option('default_product_cat') ),
      ) );
      // Emoji yedekleri (kategori görseli yoksa kullanılır)
      $cat_emojis = array(
        'elektronik'=>'📱','moda'=>'👗','ev-dekor'=>'🏠','el-yapimi'=>'🎨',
        'kitap-ve-hobi'=>'📚','spor'=>'⚽','kozmetik'=>'💄','otomotiv'=>'🚗',
        'anne-ve-bebek'=>'🍼','muzik'=>'🎵','bilgisayar'=>'💻','telefon'=>'📞',
      );
      if ( ! empty( $mega_cats ) && ! is_wp_error( $mega_cats ) ) :
    ?>
    <div class="mega-left" id="mega-left">
      <?php foreach ( $mega_cats as $mi => $mc ) :
        // Kategori görseli (WooCommerce kategori resmi)
        $cat_thumb_id = get_term_meta( $mc->term_id, 'thumbnail_id', true );
        $cat_img = $cat_thumb_id ? wp_get_attachment_image_url( $cat_thumb_id, 'thumbnail' ) : '';
        $emoji = isset( $cat_emojis[ $mc->slug ] ) ? $cat_emojis[ $mc->slug ] : '📦'; ?>
        <a class="ml-item<?php echo $mi===0?' on':''; ?>" data-key="mk-<?php echo esc_attr($mc->term_id); ?>" href="<?php echo esc_url( get_term_link($mc) ); ?>" onmouseenter="megaShow('mk-<?php echo esc_attr($mc->term_id); ?>',this)" style="text-decoration:none;color:inherit;">
          <span class="ml-ico"><?php if ( $cat_img ) : ?><img src="<?php echo esc_url( $cat_img ); ?>" alt="<?php echo esc_attr($mc->name); ?>"><?php else : ?><?php echo $emoji; ?><?php endif; ?></span>
          <?php echo esc_html($mc->name); ?> <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="mega-right" id="mega-right">
      <?php foreach ( $mega_cats as $mi => $mc ) :
        // Her ana kategorinin alt kategorileri
        $mc_subs = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>$mc->term_id, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC' ) );
      ?>
        <div class="mega-cat-panel<?php echo $mi===0?' on':''; ?>" id="megapanel-mk-<?php echo esc_attr($mc->term_id); ?>">
          <?php if ( ! empty( $mc_subs ) && ! is_wp_error( $mc_subs ) ) : ?>
            <div class="mega-cols">
              <?php foreach ( $mc_subs as $sub ) :
                // Alt-alt kategoriler (3. seviye)
                $sub_children = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>$sub->term_id, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC' ) ); ?>
                <div class="mega-col">
                  <a class="mega-col-title" href="<?php echo esc_url( get_term_link($sub) ); ?>"><?php echo esc_html($sub->name); ?></a>
                  <?php if ( ! empty( $sub_children ) && ! is_wp_error( $sub_children ) ) : ?>
                    <?php foreach ( $sub_children as $sc ) : ?>
                      <a class="mega-col-link" href="<?php echo esc_url( get_term_link($sc) ); ?>"><?php echo esc_html($sc->name); ?></a>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else : ?>
            <div class="mega-empty">
              <a href="<?php echo esc_url( get_term_link($mc) ); ?>" class="mega-empty-link"><?php echo esc_html($mc->name); ?> ürünlerini gör →</a>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- CI DROPS -->
  <?php /* eski ayrı dropdown panelleri kaldırıldı; artık ci-wrap içinde */ ?>

</div><!-- /mega-host -->

<!-- ═══ MOBİL MENÜ (DRAWER) ═══ -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="pzCloseMobileMenu()"></div>
<aside class="mobile-menu" id="mobileMenu">
  <div class="mm-head">
    <span class="mm-title">Menü</span>
    <button class="mm-close" onclick="pzCloseMobileMenu()" aria-label="Kapat">×</button>
  </div>
  <div class="mm-actions">
    <a class="mm-action mm-action-seller" href="<?php echo esc_url( home_url('/satici-ol/') ); ?>">
      <span class="mm-action-ico">🚀</span> Satıcı Ol
    </a>
    <?php if ( is_user_logged_in() && ( ( class_exists('PZV_Roles') && PZV_Roles::is_vendor() ) || current_user_can('manage_woocommerce') ) ) : ?>
    <a class="mm-action mm-action-store" href="<?php echo esc_url( home_url('/saticim/') ); ?>">
      <span class="mm-action-ico">🏪</span> Mağazam
    </a>
    <?php endif; ?>
    <a class="mm-action" href="<?php echo esc_url( function_exists('wc_get_account_endpoint_url') ? wc_get_page_permalink('myaccount') : home_url('/hesabim/') ); ?>">
      <span class="mm-action-ico">👤</span> Hesabım
    </a>
    <a class="mm-action" href="<?php echo esc_url( function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/sepet/') ); ?>">
      <span class="mm-action-ico">🛒</span> Sepetim
    </a>
  </div>
  <div class="mm-cats-title">Kategoriler</div>
  <ul class="mm-cats">
    <li class="mm-cat-home"><a href="<?php echo esc_url( home_url('/') ); ?>"><span class="mm-cat-ico">🏠</span> Anasayfa</a></li>
    <?php
      $mm_cats = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>0, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC', 'number'=>40 ) );
      if ( ! empty( $mm_cats ) && ! is_wp_error( $mm_cats ) ) :
        foreach ( $mm_cats as $mmc ) :
          if ( $mmc->slug === 'uncategorized' ) continue;
          // Kategori ikonu/resmi
          $mm_thumb = get_term_meta( $mmc->term_id, 'thumbnail_id', true );
          $mm_img   = $mm_thumb ? wp_get_attachment_image_url( $mm_thumb, 'thumbnail' ) : '';
          // Alt kategoriler
          $mm_subs  = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>$mmc->term_id, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC' ) );
          $mm_has_subs = ( ! empty( $mm_subs ) && ! is_wp_error( $mm_subs ) );
        ?>
          <li class="mm-cat-item<?php echo $mm_has_subs ? ' has-subs' : ''; ?>">
            <div class="mm-cat-row">
              <a class="mm-cat-link" href="<?php echo esc_url( get_term_link( $mmc ) ); ?>">
                <span class="mm-cat-ico">
                  <?php if ( $mm_img ) : ?><img src="<?php echo esc_url( $mm_img ); ?>" alt="" loading="lazy"><?php else : ?>📦<?php endif; ?>
                </span>
                <?php echo esc_html( $mmc->name ); ?>
              </a>
              <?php if ( $mm_has_subs ) : ?>
                <button class="mm-cat-toggle" type="button" onclick="pzToggleSubmenu(this)" aria-label="Alt kategoriler">
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
              <?php endif; ?>
            </div>
            <?php if ( $mm_has_subs ) : ?>
              <ul class="mm-subs">
                <li class="mm-sub-all"><a href="<?php echo esc_url( get_term_link( $mmc ) ); ?>">📋 Tümünü Göster</a></li>
                <?php foreach ( $mm_subs as $mm_sub ) :
                  // 3. seviye alt kategoriler
                  $mm_subsubs = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>$mm_sub->term_id, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC' ) );
                  $mm_has_subsubs = ( ! empty( $mm_subsubs ) && ! is_wp_error( $mm_subsubs ) );
                ?>
                  <li class="mm-sub-item<?php echo $mm_has_subsubs ? ' has-subs' : ''; ?>">
                    <div class="mm-sub-row">
                      <a class="mm-sub-link" href="<?php echo esc_url( get_term_link( $mm_sub ) ); ?>"><?php echo esc_html( $mm_sub->name ); ?></a>
                      <?php if ( $mm_has_subsubs ) : ?>
                        <button class="mm-sub-toggle" type="button" onclick="pzToggleSubmenu2(this)" aria-label="Alt kategoriler">
                          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                        </button>
                      <?php endif; ?>
                    </div>
                    <?php if ( $mm_has_subsubs ) : ?>
                      <ul class="mm-subsubs">
                        <li class="mm-subsub-all"><a href="<?php echo esc_url( get_term_link( $mm_sub ) ); ?>">📋 Tümünü Göster</a></li>
                        <?php foreach ( $mm_subsubs as $mm_subsub ) : ?>
                          <li><a href="<?php echo esc_url( get_term_link( $mm_subsub ) ); ?>"><?php echo esc_html( $mm_subsub->name ); ?></a></li>
                        <?php endforeach; ?>
                      </ul>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </li>
        <?php endforeach;
      endif; ?>
    <li><a href="<?php echo esc_url( home_url('/shop/') ); ?>">Tüm Ürünler</a></li>
  </ul>
</aside>
