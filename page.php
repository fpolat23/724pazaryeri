<?php
/**
 * Genel sayfa şablonu — 724PazarYeri
 * WooCommerce Sepet/Ödeme sayfalarını otomatik tasarımlı sarmalayıcıda gösterir.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();

$is_cart     = function_exists( 'is_cart' ) && is_cart();
$is_checkout = function_exists( 'is_checkout' ) && is_checkout();
?>

<?php if ( $is_cart ) : ?>

  <!-- SEPET SAYFASI -->
  <div class="bc"><div class="bc-in">
    <a class="bc-a" href="<?php echo esc_url( home_url('/') ); ?>">Anasayfa</a><span class="bc-sep">›</span>
    <span style="color:var(--ink)">Sepetim</span>
  </div></div>
  <div class="cw">
    <div class="checkout-head">
      <h1 class="checkout-title">🛒 Sepetim</h1>
      <a class="checkout-secure" href="<?php echo esc_url( function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/') ); ?>" style="cursor:pointer">← Alışverişe devam et</a>
    </div>
    <?php
      while ( have_posts() ) : the_post();
        the_content();
      endwhile;
    ?>
    <div class="checkout-trust">
      <div class="ct-item"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><div><div class="ct-t">Güvenli Ödeme</div><div class="ct-s">Teslimata kadar koruma</div></div></div>
      <div class="ct-item"><svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg><div><div class="ct-t">14 Gün İade</div><div class="ct-s">Koşulsuz iade hakkı</div></div></div>
      <div class="ct-item"><svg viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13" rx="2"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg><div><div class="ct-t">Hızlı Kargo</div><div class="ct-s">1-3 iş günü teslimat</div></div></div>
      <div class="ct-item"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg><div><div class="ct-t">Taksit İmkânı</div><div class="ct-s">Tüm kartlara taksit</div></div></div>
    </div>
  </div>

<?php elseif ( $is_checkout ) : ?>

  <!-- ÖDEME SAYFASI -->
  <div class="bc"><div class="bc-in">
    <a class="bc-a" href="<?php echo esc_url( home_url('/') ); ?>">Anasayfa</a><span class="bc-sep">›</span>
    <a class="bc-a" href="<?php echo esc_url( function_exists('wc_get_cart_url') ? wc_get_cart_url() : '#' ); ?>">Sepet</a><span class="bc-sep">›</span>
    <span style="color:var(--ink)">Ödeme</span>
  </div></div>
  <div class="cw">
    <div class="checkout-steps">
      <div class="cstep done"><span class="cstep-num">✓</span> Sepet</div>
      <div class="cstep active"><span class="cstep-num">2</span> Teslimat & Ödeme</div>
      <div class="cstep"><span class="cstep-num">3</span> Sipariş Onayı</div>
    </div>
    <div class="checkout-head">
      <h1 class="checkout-title">Güvenli Ödeme</h1>
      <div class="checkout-secure"><svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> 256-bit SSL ile korunuyor</div>
    </div>
    <?php
      while ( have_posts() ) : the_post();
        the_content();
      endwhile;
    ?>
  </div>

<?php elseif ( function_exists( 'is_account_page' ) && is_account_page() ) : ?>

  <!-- ÜYE PANELİ -->
  <div class="bc"><div class="bc-in">
    <a class="bc-a" href="<?php echo esc_url( home_url('/') ); ?>">Anasayfa</a><span class="bc-sep">›</span>
    <span style="color:var(--ink)">Hesabım</span>
  </div></div>
  <div class="cw pz-account-wrap">
    <?php
      while ( have_posts() ) : the_post();
        the_content();
      endwhile;
    ?>
  </div>

<?php else : ?>

  <!-- NORMAL SAYFA -->
  <div class="cw">
    <?php while ( have_posts() ) : the_post(); ?>
      <?php if ( ! has_shortcode( get_the_content(), 'pazaryeri_home' ) && ! has_shortcode( get_the_content(), 'pazaryeri_contact' ) ) : ?>
        <h1 class="checkout-title" style="margin-bottom:18px;"><?php the_title(); ?></h1>
      <?php endif; ?>
      <div class="entry-content"><?php the_content(); ?></div>
    <?php endwhile; ?>
  </div>

<?php endif; ?>

<?php get_footer(); ?>
