<?php defined('ABSPATH') || exit;
get_header('shop'); ?>

<div class="container">
  <div class="shop-layout">

    <!-- Sidebar / Filters -->
    <aside class="shop-sidebar">
      <div class="filter-widget">
        <h3 class="filter-title"><?php _e('Filtrele', '724pazaryeri'); ?></h3>

        <!-- Price Filter -->
        <div style="margin-bottom:16px;">
          <div class="filter-title" style="font-size:13px;"><?php _e('Fiyat Aralığı', '724pazaryeri'); ?></div>
          <div class="price-range">
            <?php if (class_exists('WC_Widget_Price_Filter')): ?>
              <?php the_widget('WC_Widget_Price_Filter'); ?>
            <?php else: ?>
              <input type="range" min="0" max="10000" value="5000">
              <div style="display:flex;justify-content:space-between;font-size:12px;color:#999;margin-top:4px;">
                <span>0 TL</span><span>10.000 TL</span>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Category Filter -->
        <div class="filter-title" style="font-size:13px;"><?php _e('Kategori', '724pazaryeri'); ?></div>
        <ul class="filter-options">
          <?php
          $cats = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'parent' => 0, 'number' => 10]);
          foreach ($cats as $cat): ?>
          <li class="filter-option">
            <label>
              <input type="checkbox" name="cat" value="<?php echo esc_attr($cat->slug); ?>">
              <a href="<?php echo esc_url(get_term_link($cat)); ?>"><?php echo esc_html($cat->name); ?></a>
              <span class="filter-count"><?php echo $cat->count; ?></span>
            </label>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <?php if (is_active_sidebar('shop-filters')): ?>
        <div class="filter-widget"><?php dynamic_sidebar('shop-filters'); ?></div>
      <?php endif; ?>
    </aside>

    <!-- Main Content -->
    <div class="shop-main">

      <?php do_action('woocommerce_before_main_content'); ?>

      <!-- Toolbar -->
      <div class="shop-toolbar">
        <?php woocommerce_result_count(); ?>
        <div class="view-toggle">
          <button class="view-btn active" data-view="grid" title="<?php _e('Izgara', '724pazaryeri'); ?>"><i class="fas fa-th"></i></button>
          <button class="view-btn" data-view="list" title="<?php _e('Liste', '724pazaryeri'); ?>"><i class="fas fa-list"></i></button>
        </div>
        <?php woocommerce_catalog_ordering(); ?>
      </div>

      <?php if (woocommerce_product_loop()): ?>
        <?php do_action('woocommerce_before_shop_loop'); ?>
        <div class="product-grid" id="product-grid">
          <?php while (have_posts()): the_post();
            global $product; $product = wc_get_product(get_the_ID());
            get_template_part('template-parts/content', 'product-card');
          endwhile; ?>
        </div>
        <?php do_action('woocommerce_after_shop_loop'); ?>
      <?php else: ?>
        <?php do_action('woocommerce_no_products_found'); ?>
      <?php endif; ?>

      <?php do_action('woocommerce_after_main_content'); ?>

      <!-- Pagination -->
      <div class="pagination">
        <?php echo paginate_links(['prev_text' => '‹', 'next_text' => '›', 'type' => 'array']
          ? implode('', array_map(function($p) {
              return str_replace(['page-numbers current','page-numbers'], ['page-btn active','page-btn'], $p);
            }, (array) paginate_links(['prev_text' => '‹', 'next_text' => '›', 'type' => 'array'])))
          : ''
        ); ?>
      </div>
    </div>
  </div>
</div>

<?php get_footer('shop'); ?>
