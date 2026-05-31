<?php
if (!defined('ABSPATH')) exit;
global $product;
if (!$product || !is_a($product, 'WC_Product')) return;

$discount  = pazaryeri_get_discount($product);
$is_new    = (time() - strtotime($product->get_date_created())) < 30 * DAY_IN_SECONDS;
$avg_rating = $product->get_average_rating();
$rating_count = $product->get_rating_count();
$wishlist   = is_user_logged_in() ? (array) get_user_meta(get_current_user_id(), '_pazaryeri_wishlist', true) : [];
$in_wish    = in_array($product->get_id(), $wishlist);
?>
<div class="product-card" data-product-id="<?php echo esc_attr($product->get_id()); ?>">

  <!-- Image -->
  <div class="product-image-wrap">
    <a href="<?php echo esc_url($product->get_permalink()); ?>">
      <?php echo $product->get_image('woocommerce_thumbnail'); ?>
    </a>

    <!-- Badges -->
    <div class="product-badges">
      <?php if ($discount > 0): ?>
        <span class="badge badge-sale">-%<?php echo $discount; ?></span>
      <?php endif; ?>
      <?php if ($is_new): ?>
        <span class="badge badge-new"><?php _e('Yeni', '724pazaryeri'); ?></span>
      <?php endif; ?>
      <?php if ($product->is_featured()): ?>
        <span class="badge badge-hot"><?php _e('Öne Çıkan', '724pazaryeri'); ?></span>
      <?php endif; ?>
    </div>

    <!-- Wishlist -->
    <button class="product-wishlist <?php echo $in_wish ? 'active' : ''; ?>"
      data-product-id="<?php echo esc_attr($product->get_id()); ?>"
      aria-label="<?php _e('Favorilere Ekle', '724pazaryeri'); ?>">
      <i class="<?php echo $in_wish ? 'fas' : 'far'; ?> fa-heart"></i>
    </button>
  </div>

  <!-- Info -->
  <div class="product-info">
    <?php $brand = $product->get_attribute('pa_marka'); if ($brand): ?>
    <div class="product-brand"><?php echo esc_html($brand); ?></div>
    <?php endif; ?>

    <h3 class="product-title">
      <a href="<?php echo esc_url($product->get_permalink()); ?>">
        <?php echo esc_html($product->get_name()); ?>
      </a>
    </h3>

    <?php if ($avg_rating > 0): ?>
    <div class="product-rating">
      <span class="stars">
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <i class="<?php echo $i <= round($avg_rating) ? 'fas' : 'far'; ?> fa-star" style="font-size:11px;"></i>
        <?php endfor; ?>
      </span>
      <span class="rating-count">(<?php echo $rating_count; ?>)</span>
    </div>
    <?php endif; ?>

    <!-- Price -->
    <div class="product-pricing">
      <?php if ($product->is_on_sale()): ?>
        <span class="product-old-price"><?php echo wc_price($product->get_regular_price()); ?></span>
        <span class="product-price"><?php echo wc_price($product->get_sale_price()); ?></span>
        <?php if ($discount > 0): ?>
          <span class="product-discount">%<?php echo $discount; ?> indirim</span>
        <?php endif; ?>
      <?php else: ?>
        <span class="product-price"><?php echo $product->get_price_html(); ?></span>
      <?php endif; ?>
      <?php
      $price = (float) $product->get_price();
      if ($price > 500):
        $installment = ceil($price / 12);
      ?>
      <div class="product-installment">
        12 x <?php echo wc_price($installment); ?> taksit
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Add to Cart -->
  <?php if ($product->is_in_stock()): ?>
    <a href="<?php echo esc_url($product->add_to_cart_url()); ?>"
       class="product-add-cart ajax_add_to_cart"
       data-product_id="<?php echo esc_attr($product->get_id()); ?>"
       data-quantity="1">
      <i class="fas fa-shopping-cart"></i> <?php _e('Sepete Ekle', '724pazaryeri'); ?>
    </a>
  <?php else: ?>
    <span class="product-add-cart" style="background:#ccc;cursor:not-allowed;">
      <?php _e('Stok Yok', '724pazaryeri'); ?>
    </span>
  <?php endif; ?>
</div>
