<?php get_header(); ?>

<?php if (is_front_page()): ?>

<!-- HERO SECTION -->
<section class="hero-section">
  <div class="container">
    <div class="hero-grid">

      <!-- Left Sidebar Categories -->
      <div class="hero-sidebar">
        <div class="hero-sidebar-title"><i class="fas fa-bars"></i> <?php _e('Tüm Kategoriler', '724pazaryeri'); ?></div>
        <ul class="sidebar-cat-list">
          <?php
          $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'number' => 12]);
          $icons = ['📱','💻','👗','🏠','🍳','📚','⚽','💄','🎮','🚗','🎵','🌿'];
          foreach ($cats as $i => $cat): ?>
          <li>
            <a href="<?php echo esc_url(get_term_link($cat)); ?>">
              <span><?php echo $icons[$i % count($icons)]; ?></span>
              <?php echo esc_html($cat->name); ?>
              <span class="arrow">›</span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Main Slider -->
      <div class="slider-wrapper" id="heroSlider">
        <div class="slider-track">
          <?php
          $slides = [
            ['bg' => 'linear-gradient(135deg,#1a1a2e,#16213e)', 'badge' => '⚡ SÜPER FIRSAT', 'title' => 'Elektronik\'te Büyük İndirim', 'sub' => 'Teknoloji ürünlerinde %60\'a varan indirimler', 'cta' => 'Hemen Keşfet'],
            ['bg' => 'linear-gradient(135deg,#2d6a4f,#1b4332)', 'badge' => '🌿 YENİ SEZON', 'title' => 'Bahar Koleksiyonu', 'sub' => 'Moda\'da yeni trendler geldi', 'cta' => 'Koleksiyonu Gör'],
            ['bg' => 'linear-gradient(135deg,#7b2d8b,#4a1259)', 'badge' => '🏠 EV & YAŞAM', 'title' => 'Ev Dekorasyonu', 'sub' => 'Hayalindeki evi yaratmak için', 'cta' => 'Alışverişe Başla'],
          ];
          foreach ($slides as $i => $s): ?>
          <div class="slide" style="background:<?php echo $s['bg']; ?>;min-height:360px;display:flex;align-items:center;">
            <div class="slide-content" style="padding:40px;">
              <span class="slide-badge"><?php echo esc_html($s['badge']); ?></span>
              <h2 class="slide-title"><?php echo esc_html($s['title']); ?></h2>
              <p class="slide-subtitle"><?php echo esc_html($s['sub']); ?></p>
              <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>" class="slide-cta"><?php echo esc_html($s['cta']); ?> →</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <button class="slider-prev" id="sliderPrev">‹</button>
        <button class="slider-next" id="sliderNext">›</button>
        <div class="slider-dots">
          <span class="slider-dot active"></span>
          <span class="slider-dot"></span>
          <span class="slider-dot"></span>
        </div>
      </div>

      <!-- Right Sidebar Banners -->
      <div style="display:flex;flex-direction:column;gap:12px;">
        <?php
        $sideBanners = [
          ['bg' => '#FF6000', 'icon' => '⚡', 'title' => 'Günün Fırsatları', 'sub' => '%70\'e varan indirim'],
          ['bg' => '#e53e3e', 'icon' => '🔥', 'title' => 'Çok Satanlar', 'sub' => 'En popüler ürünler'],
          ['bg' => '#3182ce', 'icon' => '🆓', 'title' => 'Ücretsiz Kargo', 'sub' => '100 TL üzeri siparişlerde'],
        ];
        foreach ($sideBanners as $b): ?>
        <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>"
          style="background:<?php echo $b['bg']; ?>;border-radius:8px;padding:20px;color:#fff;display:flex;align-items:center;gap:12px;transition:.2s;text-decoration:none;"
          onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'">
          <span style="font-size:32px;"><?php echo $b['icon']; ?></span>
          <div>
            <div style="font-weight:700;font-size:15px;"><?php echo esc_html($b['title']); ?></div>
            <div style="font-size:12px;opacity:.9;"><?php echo esc_html($b['sub']); ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

    </div>
  </div>
</section>

<!-- CATEGORY SHOWCASE -->
<section class="category-showcase">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php _e('Kategoriler', '724pazaryeri'); ?></h2>
      <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>" class="section-link"><?php _e('Tümünü Gör →', '724pazaryeri'); ?></a>
    </div>
    <div class="cat-showcase-grid">
      <?php
      $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'number' => 8]);
      $icons = ['📱','💻','👗','🏠','🍳','📚','⚽','💄'];
      foreach ($cats as $i => $cat): ?>
      <a href="<?php echo esc_url(get_term_link($cat)); ?>" class="cat-showcase-item">
        <span class="cat-showcase-icon"><?php echo $icons[$i % count($icons)]; ?></span>
        <div class="cat-showcase-name"><?php echo esc_html($cat->name); ?></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- FLASH SALE -->
<section class="flash-sale-section">
  <div class="container">
    <div style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">
      <div class="flash-header">
        <span class="flash-title">⚡ <?php _e('Flaş İndirim', '724pazaryeri'); ?></span>
        <span class="flash-badge"><?php _e('Sınırlı Stok', '724pazaryeri'); ?></span>
        <div class="countdown">
          <div class="countdown-item"><div class="countdown-num" id="cd-h">08</div><div class="countdown-label">SA</div></div>
          <span class="countdown-sep">:</span>
          <div class="countdown-item"><div class="countdown-num" id="cd-m">45</div><div class="countdown-label">DK</div></div>
          <span class="countdown-sep">:</span>
          <div class="countdown-item"><div class="countdown-num" id="cd-s">30</div><div class="countdown-label">SN</div></div>
        </div>
        <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>?orderby=sale" class="section-link" style="color:rgba(255,255,255,.8);margin-left:16px;"><?php _e('Tümünü Gör →', '724pazaryeri'); ?></a>
      </div>
      <div style="padding:16px;">
        <?php
        $flash_args = ['post_type' => 'product', 'posts_per_page' => 5, 'meta_query' => [['key' => '_sale_price', 'value' => '', 'compare' => '!=']]];
        $flash_q = new WP_Query($flash_args);
        if ($flash_q->have_posts()): ?>
        <div class="product-grid">
          <?php while ($flash_q->have_posts()): $flash_q->the_post();
            global $product; $product = wc_get_product(get_the_ID());
            get_template_part('template-parts/content', 'product-card');
          endwhile; wp_reset_postdata(); ?>
        </div>
        <?php else: echo '<p style="padding:20px;color:#999;">' . __('İndirimli ürün bulunamadı.', '724pazaryeri') . '</p>'; endif; ?>
      </div>
    </div>
  </div>
</section>

<!-- FEATURED BANNER -->
<div class="container">
  <div class="featured-banner" style="background:linear-gradient(135deg,#FF6000,#ff9a56);">
    <div class="featured-banner-overlay">
      <div class="featured-banner-content">
        <div class="featured-banner-sub"><?php _e('Özel Teklif', '724pazaryeri'); ?></div>
        <div class="featured-banner-title"><?php _e('Yeni Üyelere %15 İndirim', '724pazaryeri'); ?></div>
        <a href="<?php echo esc_url(wp_registration_url()); ?>" class="btn-primary"><?php _e('Hemen Üye Ol', '724pazaryeri'); ?></a>
      </div>
    </div>
  </div>
</div>

<!-- NEW PRODUCTS -->
<section class="products-section">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php _e('Yeni Gelenler', '724pazaryeri'); ?></h2>
      <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>?orderby=date" class="section-link"><?php _e('Tümünü Gör →', '724pazaryeri'); ?></a>
    </div>
    <?php
    $new_q = new WP_Query(['post_type' => 'product', 'posts_per_page' => 5, 'orderby' => 'date', 'order' => 'DESC']);
    if ($new_q->have_posts()): ?>
    <div class="product-grid">
      <?php while ($new_q->have_posts()): $new_q->the_post();
        global $product; $product = wc_get_product(get_the_ID());
        get_template_part('template-parts/content', 'product-card');
      endwhile; wp_reset_postdata(); ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- TOP SELLERS -->
<section class="products-section" style="background:#fff;padding:24px 0;">
  <div class="container">
    <div class="section-header">
      <h2 class="section-title"><?php _e('Çok Satanlar', '724pazaryeri'); ?></h2>
      <a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>?orderby=popularity" class="section-link"><?php _e('Tümünü Gör →', '724pazaryeri'); ?></a>
    </div>
    <?php
    $pop_q = new WP_Query(['post_type' => 'product', 'posts_per_page' => 5, 'meta_key' => 'total_sales', 'orderby' => 'meta_value_num', 'order' => 'DESC']);
    if ($pop_q->have_posts()): ?>
    <div class="product-grid">
      <?php while ($pop_q->have_posts()): $pop_q->the_post();
        global $product; $product = wc_get_product(get_the_ID());
        get_template_part('template-parts/content', 'product-card');
      endwhile; wp_reset_postdata(); ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php else: ?>
<!-- DEFAULT LOOP -->
<div class="container" style="padding:24px 0;">
  <?php if (have_posts()): while (have_posts()): the_post(); ?>
    <article <?php post_class('card' , ''); ?> style="background:#fff;border-radius:8px;padding:24px;margin-bottom:16px;box-shadow:0 2px 8px rgba(0,0,0,.1);">
      <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
      <div><?php the_excerpt(); ?></div>
    </article>
  <?php endwhile; else: ?>
    <p><?php _e('İçerik bulunamadı.', '724pazaryeri'); ?></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php get_footer(); ?>
