<?php
/**
 * Tema header — 724PazarYeri
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Dinamik header verileri ──────────────────────────────────────
$hdr_logged_in  = is_user_logged_in();
$hdr_user_id    = $hdr_logged_in ? get_current_user_id() : 0;
$hdr_user       = $hdr_logged_in ? wp_get_current_user() : null;
$hdr_first_name = $hdr_logged_in ? ( $hdr_user->first_name ?: $hdr_user->display_name ) : '';
$hdr_is_vendor  = $hdr_logged_in && class_exists('PZV_Roles') && ( PZV_Roles::is_vendor( $hdr_user_id ) || current_user_can('manage_woocommerce') );

// Avatar (WC my-account gravatar veya harf)
$hdr_avatar_url = $hdr_logged_in ? get_avatar_url( $hdr_user_id, array( 'size' => 40, 'default' => 'blank' ) ) : '';
$hdr_avatar_letter = $hdr_logged_in ? mb_strtoupper( mb_substr( $hdr_first_name ?: $hdr_user->user_login, 0, 1 ) ) : '';

// Puanlar (WooCommerce Points & Rewards)
$hdr_points = 0;
if ( $hdr_logged_in ) {
    if ( class_exists('WC_Points_Rewards_Manager') ) {
        $hdr_points = (int) WC_Points_Rewards_Manager::get_users_points( $hdr_user_id );
    } else {
        $hdr_points = (int) get_user_meta( $hdr_user_id, 'wc_points_balance', true );
    }
}
$hdr_points_label = $hdr_points > 0 ? number_format( $hdr_points, 0, ',', '.' ) . ' puan' : 'Puan yok';

// Aktif kuponlar (kullanıcıya özel kısıtlı kuponlar)
$hdr_coupon_count = 0;
if ( $hdr_logged_in && $hdr_user ) {
    $coupon_q = new WP_Query( array(
        'post_type'      => 'shop_coupon',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array( array(
            'key'     => 'customer_email',
            'value'   => $hdr_user->user_email,
            'compare' => 'LIKE',
        ) ),
    ) );
    // Sadece süresi dolmamış kuponlar
    $now = current_time( 'timestamp' );
    foreach ( $coupon_q->posts as $cid ) {
        $exp = get_post_meta( $cid, 'date_expires', true );
        if ( ! $exp || (int) $exp >= $now ) $hdr_coupon_count++;
    }
    wp_reset_postdata();
}
$hdr_coupon_label = $hdr_coupon_count > 0 ? $hdr_coupon_count . ' aktif kupon' : 'Kupon yok';

// Sipariş sayıları
$hdr_orders_active    = 0;
$hdr_orders_completed = 0;
$hdr_orders_cancelled = 0;
$hdr_last_order       = null;
if ( $hdr_logged_in && function_exists('wc_get_orders') ) {
    $hdr_orders_active    = count( wc_get_orders( array( 'customer' => $hdr_user_id, 'status' => array( 'pending', 'processing', 'on-hold' ), 'limit' => -1, 'return' => 'ids' ) ) );
    $hdr_orders_completed = count( wc_get_orders( array( 'customer' => $hdr_user_id, 'status' => array( 'completed' ), 'limit' => -1, 'return' => 'ids' ) ) );
    $hdr_orders_cancelled = count( wc_get_orders( array( 'customer' => $hdr_user_id, 'status' => array( 'refunded', 'cancelled' ), 'limit' => -1, 'return' => 'ids' ) ) );
    // Son sipariş (durum + tutar gösterimi için)
    $last_orders = wc_get_orders( array( 'customer' => $hdr_user_id, 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC' ) );
    $hdr_last_order = ! empty( $last_orders ) ? $last_orders[0] : null;
}
$hdr_orders_active_label    = $hdr_orders_active > 0 ? $hdr_orders_active . ' sipariş yolda' : 'Aktif sipariş yok';
$hdr_orders_completed_label = $hdr_orders_completed > 0 ? $hdr_orders_completed . ' sipariş' : 'Henüz yok';
$hdr_orders_cancelled_label = $hdr_orders_cancelled > 0 ? $hdr_orders_cancelled . ' sipariş' : 'Yok';

// Değerlendirmeler (yorum sayısı)
$hdr_reviews_count = 0;
if ( $hdr_logged_in ) {
    $hdr_reviews_count = (int) get_comments( array(
        'user_id'    => $hdr_user_id,
        'type'       => 'review',
        'status'     => 'approve',
        'count'      => true,
        'no_found_rows' => true,
    ) );
}
$hdr_reviews_label = $hdr_reviews_count > 0 ? $hdr_reviews_count . ' değerlendirme' : 'Henüz yok';
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
        <span class="nav-top"><?php echo $hdr_logged_in ? 'Merhaba, ' . esc_html( $hdr_first_name ) : 'Merhaba, Giriş Yap'; ?></span>
        <span class="nav-bot">Hesabım ▾</span>
        <div class="nav-drop ndrop-account-drop" id="drop-account">

          <?php if ( $hdr_logged_in ) : ?>
          <!-- Giriş yapılmış: kullanıcı başlığı -->
          <div class="ndrop-head ndrop-head-user">
            <div class="ndrop-avatar-wrap">
              <?php if ( $hdr_avatar_url ) : ?>
                <img class="ndrop-avatar-img" src="<?php echo esc_url( $hdr_avatar_url ); ?>" alt="<?php echo esc_attr( $hdr_first_name ); ?>">
              <?php else : ?>
                <div class="ndrop-avatar-letter"><?php echo esc_html( $hdr_avatar_letter ); ?></div>
              <?php endif; ?>
            </div>
            <div>
              <div class="ndrop-title">Merhaba, <?php echo esc_html( $hdr_first_name ); ?>!</div>
              <div class="ndrop-sub"><?php echo esc_html( $hdr_user->user_email ); ?></div>
            </div>
          </div>

          <?php if ( $hdr_is_vendor ) : ?>
          <a href="<?php echo esc_url( home_url('/saticim/') ); ?>" class="ndrop-vendor-row">
            <div class="ndrop-vendor-ico">🏪</div>
            <div>
              <div class="ndrop-vendor-label">Mağazam</div>
              <div class="ndrop-vendor-hint">Satıcı paneline git</div>
            </div>
            <div class="ndrop-vendor-arrow">›</div>
          </a>
          <?php endif; ?>

          <div class="ndrop-grid">
            <a href="<?php echo esc_url( wc_get_account_endpoint_url('edit-account') ); ?>" class="ndrop-tile">
              <div class="ndrop-tile-ico">👤</div>
              <div class="ndrop-tile-label">Profilim</div>
            </a>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url('orders') ); ?>" class="ndrop-tile">
              <div class="ndrop-tile-ico">📦</div>
              <div class="ndrop-tile-label">Siparişlerim</div>
            </a>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url('wishlist') ); ?>" class="ndrop-tile">
              <div class="ndrop-tile-ico">❤️</div>
              <div class="ndrop-tile-label">Favorilerim</div>
            </a>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url('edit-address') ); ?>" class="ndrop-tile">
              <div class="ndrop-tile-ico">📍</div>
              <div class="ndrop-tile-label">Adreslerim</div>
            </a>
          </div>

          <div class="ndrop-stat-row">
            <a href="<?php echo esc_url( wc_get_account_endpoint_url('points-and-rewards') ); ?>" class="ndrop-stat">
              <div class="ndrop-stat-val"><?php echo esc_html( number_format( $hdr_points, 0, ',', '.' ) ); ?></div>
              <div class="ndrop-stat-key">Puan</div>
            </a>
            <div class="ndrop-stat-divider"></div>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url('orders') ); ?>" class="ndrop-stat">
              <div class="ndrop-stat-val"><?php echo esc_html( $hdr_orders_active ); ?></div>
              <div class="ndrop-stat-key">Aktif Sipariş</div>
            </a>
            <div class="ndrop-stat-divider"></div>
            <a href="<?php echo esc_url( home_url('/my-account/coupons/') ); ?>" class="ndrop-stat">
              <div class="ndrop-stat-val"><?php echo esc_html( $hdr_coupon_count ); ?></div>
              <div class="ndrop-stat-key">Kupon</div>
            </a>
          </div>

          <div class="ndrop-foot">
            <a href="<?php echo esc_url( wp_logout_url( home_url('/') ) ); ?>" class="ndrop-logout">Çıkış Yap</a>
          </div>

          <?php else : ?>
          <!-- Giriş yapılmamış -->
          <div class="ndrop-head">
            <div class="ndrop-title">Hesabıma Git</div>
            <div class="ndrop-sub">Üye girişi veya yeni kayıt</div>
          </div>

          <div class="ndrop-guest-list">
            <div class="ndrop-row"><div class="ndrop-icon">👤</div><div><div class="ndrop-label">Profilim</div><div class="ndrop-hint">Bilgilerimi düzenle</div></div></div>
            <div class="ndrop-row"><div class="ndrop-icon">📦</div><div><div class="ndrop-label">Siparişlerim</div><div class="ndrop-hint">Takip et & yönet</div></div></div>
            <div class="ndrop-row"><div class="ndrop-icon">❤️</div><div><div class="ndrop-label">Favori Listelerim</div></div></div>
            <div class="ndrop-row"><div class="ndrop-icon">⭐</div><div><div class="ndrop-label">Puanlarım</div></div></div>
            <div class="ndrop-row"><div class="ndrop-icon">🎟️</div><div><div class="ndrop-label">Kuponlarım</div></div></div>
          </div>

          <div class="ndrop-divider"></div>
          <a href="<?php echo esc_url( wc_get_page_permalink('myaccount') ); ?>" class="ndrop-btn" style="text-decoration:none;">Giriş Yap</a>
          <a href="<?php echo esc_url( wc_get_page_permalink('myaccount') ); ?>?action=register" class="ndrop-btn out" style="margin-top:6px;text-decoration:none;">Üye Ol</a>
          <div style="height:10px;"></div>
          <?php endif; ?>

        </div>
      </div>

      <!-- RETURNS / SİPARİŞLER -->
      <div class="nav-btn" id="btn-orders" onclick="toggleDrop('drop-orders','btn-orders')">
        <span class="nav-top">İadeler &</span>
        <span class="nav-bot">Siparişler <?php if ( $hdr_orders_active > 0 ) : ?><span class="nav-badge"><?php echo esc_html( $hdr_orders_active ); ?></span><?php endif; ?> ▾</span>
        <div class="nav-drop ndrop-orders-drop" id="drop-orders">

          <!-- Başlık + özet sayılar -->
          <div class="ndrop-head ndrop-orders-head">
            <div class="ndrop-title">Siparişlerim</div>
            <div class="ndrop-orders-summary">
              <span class="ndrop-os ndrop-os-active"><?php echo esc_html( $hdr_orders_active ); ?> Aktif</span>
              <span class="ndrop-os ndrop-os-done"><?php echo esc_html( $hdr_orders_completed ); ?> Tamamlandı</span>
            </div>
          </div>

          <?php if ( $hdr_last_order ) :
            $lo_status    = $hdr_last_order->get_status();
            $lo_total     = $hdr_last_order->get_formatted_order_total();
            $lo_date      = $hdr_last_order->get_date_created() ? $hdr_last_order->get_date_created()->date_i18n( 'd M Y' ) : '';
            $lo_id        = $hdr_last_order->get_id();
            $lo_url       = $hdr_last_order->get_view_order_url();
            $lo_status_labels = array(
                'pending'    => array( 'Bekliyor',    'ndrop-st-pending' ),
                'processing' => array( 'Hazırlanıyor','ndrop-st-processing' ),
                'on-hold'    => array( 'Beklemede',   'ndrop-st-pending' ),
                'completed'  => array( 'Teslim Edildi','ndrop-st-done' ),
                'cancelled'  => array( 'İptal',        'ndrop-st-cancel' ),
                'refunded'   => array( 'İade Edildi',  'ndrop-st-cancel' ),
                'failed'     => array( 'Başarısız',    'ndrop-st-cancel' ),
            );
            $lo_sl = isset( $lo_status_labels[ $lo_status ] ) ? $lo_status_labels[ $lo_status ] : array( ucfirst( $lo_status ), 'ndrop-st-pending' );
          ?>
          <a href="<?php echo esc_url( $lo_url ); ?>" class="ndrop-last-order">
            <div class="ndrop-lo-left">
              <div class="ndrop-lo-label">Son Siparişiniz</div>
              <div class="ndrop-lo-meta">#<?php echo esc_html( $lo_id ); ?><?php if ( $lo_date ) : ?> &bull; <?php echo esc_html( $lo_date ); ?><?php endif; ?></div>
            </div>
            <div class="ndrop-lo-right">
              <div class="ndrop-lo-total"><?php echo wp_kses_post( $lo_total ); ?></div>
              <span class="ndrop-st <?php echo esc_attr( $lo_sl[1] ); ?>"><?php echo esc_html( $lo_sl[0] ); ?></span>
            </div>
          </a>
          <?php endif; ?>

          <!-- Durum linkleri -->
          <div class="ndrop-orders-links">
            <a href="<?php echo esc_url( wc_get_account_endpoint_url('orders') ); ?>" class="ndrop-ol">
              <span class="ndrop-ol-ico ndrop-ol-ico-active">🚚</span>
              <span class="ndrop-ol-txt">
                <strong>Aktif Siparişler</strong>
                <em><?php echo esc_html( $hdr_orders_active_label ); ?></em>
              </span>
              <?php if ( $hdr_orders_active > 0 ) : ?>
                <span class="ndrop-ol-badge ndrop-ol-badge-active"><?php echo esc_html( $hdr_orders_active ); ?></span>
              <?php endif; ?>
            </a>

            <a href="<?php echo esc_url( add_query_arg( 'status', 'completed', wc_get_account_endpoint_url('orders') ) ); ?>" class="ndrop-ol">
              <span class="ndrop-ol-ico ndrop-ol-ico-done">✅</span>
              <span class="ndrop-ol-txt">
                <strong>Tamamlananlar</strong>
                <em><?php echo esc_html( $hdr_orders_completed_label ); ?></em>
              </span>
            </a>

            <a href="<?php echo esc_url( add_query_arg( 'status', 'cancelled', wc_get_account_endpoint_url('orders') ) ); ?>" class="ndrop-ol">
              <span class="ndrop-ol-ico ndrop-ol-ico-cancel">🔄</span>
              <span class="ndrop-ol-txt">
                <strong>İade &amp; İptal</strong>
                <em><?php echo esc_html( $hdr_orders_cancelled_label ); ?></em>
              </span>
            </a>

            <a href="<?php echo esc_url( wc_get_account_endpoint_url('reviews') ); ?>" class="ndrop-ol">
              <span class="ndrop-ol-ico ndrop-ol-ico-review">⭐</span>
              <span class="ndrop-ol-txt">
                <strong>Değerlendirmelerim</strong>
                <em><?php echo esc_html( $hdr_reviews_label ); ?></em>
              </span>
            </a>
          </div>

          <div class="ndrop-orders-footer">
            <a href="<?php echo esc_url( wc_get_account_endpoint_url('orders') ); ?>" class="ndrop-orders-all">Tüm Siparişleri Gör →</a>
          </div>

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
