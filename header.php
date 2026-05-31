<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- TOP BAR -->
<div class="top-bar">
  <div class="container">
    <span><?php _e('📦 100 TL üzeri ücretsiz kargo', '724pazaryeri'); ?></span>
    <div class="top-bar-links">
      <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>"><?php _e('Siparişlerim', '724pazaryeri'); ?></a>
      <a href="#"><?php _e('Satıcı Ol', '724pazaryeri'); ?></a>
      <a href="#"><?php _e('Yardım', '724pazaryeri'); ?></a>
      <?php if (is_user_logged_in()): ?>
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('edit-account')); ?>"><?php echo esc_html(wp_get_current_user()->display_name); ?></a>
        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>"><?php _e('Çıkış', '724pazaryeri'); ?></a>
      <?php else: ?>
        <a href="<?php echo esc_url(wp_login_url()); ?>"><?php _e('Giriş Yap', '724pazaryeri'); ?></a>
        <a href="<?php echo esc_url(wp_registration_url()); ?>"><?php _e('Üye Ol', '724pazaryeri'); ?></a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- HEADER -->
<header class="site-header">
  <div class="container">
    <div class="header-inner">
      <!-- Logo -->
      <a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo">
        <?php if (has_custom_logo()): the_custom_logo(); else: ?>
          <div class="logo-text">724<span>Pazaryeri</span></div>
        <?php endif; ?>
      </a>

      <!-- Search -->
      <div class="header-search">
        <form class="search-form" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
          <input class="search-input" type="search" name="s"
            placeholder="<?php _e('Ürün, marka veya kategori ara...', '724pazaryeri'); ?>"
            value="<?php echo get_search_query(); ?>"
            autocomplete="off">
          <input type="hidden" name="post_type" value="product">
          <button class="search-btn" type="submit" aria-label="<?php _e('Ara', '724pazaryeri'); ?>">
            <i class="fas fa-search"></i>
          </button>
        </form>
      </div>

      <!-- Actions -->
      <div class="header-actions">
        <?php if (is_user_logged_in()): ?>
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('dashboard')); ?>" class="header-action-btn">
          <i class="fas fa-user icon"></i>
          <span class="label"><?php _e('Hesabım', '724pazaryeri'); ?></span>
        </a>
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('wishlist')); ?>" class="header-action-btn">
          <i class="fas fa-heart icon"></i>
          <span class="label"><?php _e('Favoriler', '724pazaryeri'); ?></span>
        </a>
        <?php else: ?>
        <a href="<?php echo esc_url(wp_login_url()); ?>" class="header-action-btn">
          <i class="fas fa-user icon"></i>
          <span class="label"><?php _e('Giriş Yap', '724pazaryeri'); ?></span>
        </a>
        <?php endif; ?>
        <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="header-action-btn">
          <i class="fas fa-shopping-cart icon"></i>
          <span class="label"><?php _e('Sepet', '724pazaryeri'); ?></span>
          <?php $count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0; ?>
          <?php if ($count > 0): ?><span class="cart-count"><?php echo $count; ?></span><?php endif; ?>
        </a>
      </div>
    </div>
  </div>
</header>

<!-- CATEGORY NAV -->
<nav class="category-nav" aria-label="<?php _e('Kategoriler', '724pazaryeri'); ?>">
  <div class="container">
    <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>" class="cat-nav-item all-cats-btn">
      <i class="fas fa-bars"></i> <?php _e('Tüm Kategoriler', '724pazaryeri'); ?>
    </a>
    <?php
    $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'number' => 10]);
    $icons = ['📱','💻','👗','🏠','🍳','📚','⚽','💄','🎮','🚗'];
    foreach ($cats as $i => $cat):
    ?>
    <a href="<?php echo esc_url(get_term_link($cat)); ?>" class="cat-nav-item">
      <span class="cat-icon"><?php echo $icons[$i % count($icons)]; ?></span>
      <?php echo esc_html($cat->name); ?>
    </a>
    <?php endforeach; ?>
  </div>
</nav>

<!-- BREADCRUMB (non-home) -->
<?php if (!is_front_page()): ?>
<div class="breadcrumb">
  <div class="container">
    <ol>
      <li><a href="<?php echo esc_url(home_url('/')); ?>"><?php _e('Ana Sayfa', '724pazaryeri'); ?></a></li>
      <li><span class="breadcrumb-sep">›</span></li>
      <?php if (is_woocommerce()): woocommerce_breadcrumb(['wrap_before' => '', 'wrap_after' => '', 'before' => '', 'after' => '', 'delimiter' => '<span class="breadcrumb-sep">›</span>']); endif; ?>
      <?php if (!is_woocommerce() && is_singular()): ?>
      <li><span class="breadcrumb-current"><?php the_title(); ?></span></li>
      <?php endif; ?>
    </ol>
  </div>
</div>
<?php endif; ?>

<main id="main" class="site-main">
