<?php defined('ABSPATH') || exit;
get_header('shop'); ?>

<div class="container">
  <?php while (have_posts()): the_post(); global $product; $product = wc_get_product(get_the_ID()); ?>
  <div class="single-product-layout">

    <!-- Gallery -->
    <div>
      <div class="product-gallery">
        <div class="main-image" id="mainImage">
          <?php echo $product->get_image('woocommerce_single'); ?>
        </div>
        <?php
        $gallery_ids = $product->get_gallery_image_ids();
        if (!empty($gallery_ids)): ?>
        <div class="thumb-strip">
          <div class="thumb active" onclick="swapImage(this, '<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(), 'woocommerce_single')); ?>')">
            <?php echo $product->get_image('thumbnail'); ?>
          </div>
          <?php foreach ($gallery_ids as $gid): ?>
          <div class="thumb" onclick="swapImage(this, '<?php echo esc_url(wp_get_attachment_image_url($gid, 'woocommerce_single')); ?>')">
            <?php echo wp_get_attachment_image($gid, 'thumbnail'); ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Description Tabs -->
      <div style="background:#fff;border-radius:8px;padding:20px;margin-top:16px;box-shadow:0 2px 8px rgba(0,0,0,.1);">
        <div style="display:flex;gap:0;border-bottom:2px solid #eee;margin-bottom:16px;">
          <?php $tabs = wc_get_product_tabs(); foreach ($tabs as $key => $tab): ?>
          <button class="tab-btn <?php echo $key === 'description' ? 'active' : ''; ?>"
            data-tab="<?php echo esc_attr($key); ?>"
            style="padding:10px 20px;border:none;background:none;cursor:pointer;font-size:14px;font-weight:600;color:<?php echo $key === 'description' ? 'var(--primary)' : '#666'; ?>;border-bottom:3px solid <?php echo $key === 'description' ? 'var(--primary)' : 'transparent'; ?>;margin-bottom:-2px;">
            <?php echo esc_html($tab['title']); ?>
          </button>
          <?php endforeach; ?>
        </div>
        <?php foreach ($tabs as $key => $tab): ?>
        <div class="tab-panel" id="tab-<?php echo esc_attr($key); ?>" style="display:<?php echo $key === 'description' ? 'block' : 'none'; ?>;">
          <?php call_user_func($tab['callback'], $key, $tab); ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Product Details -->
    <div>
      <div class="product-details-box">
        <div class="product-detail-brand">
          <?php $brand = $product->get_attribute('pa_marka'); echo $brand ? esc_html($brand) : get_bloginfo('name'); ?>
        </div>
        <h1 class="product-detail-title"><?php the_title(); ?></h1>

        <!-- Rating -->
        <div class="product-detail-rating">
          <?php if ($product->get_average_rating() > 0): ?>
          <div class="stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <i class="<?php echo $i <= round($product->get_average_rating()) ? 'fas' : 'far'; ?> fa-star" style="color:#FFD700;"></i>
            <?php endfor; ?>
          </div>
          <span style="font-size:13px;color:#666;"><?php echo $product->get_average_rating(); ?> / 5 (<?php echo $product->get_rating_count(); ?> değerlendirme)</span>
          <?php endif; ?>
          <?php if ($product->is_in_stock()): ?>
            <span style="margin-left:auto;color:#38a169;font-size:13px;font-weight:600;">✓ <?php _e('Stokta Var', '724pazaryeri'); ?></span>
          <?php else: ?>
            <span style="margin-left:auto;color:#e53e3e;font-size:13px;font-weight:600;"><?php _e('Stok Yok', '724pazaryeri'); ?></span>
          <?php endif; ?>
        </div>

        <!-- Price -->
        <div class="product-detail-price-wrap">
          <?php if ($product->is_on_sale()): ?>
            <div class="product-detail-old"><?php echo wc_price($product->get_regular_price()); ?></div>
            <div>
              <span class="product-detail-price"><?php echo wc_price($product->get_sale_price()); ?></span>
              <?php $d = pazaryeri_get_discount($product); if ($d > 0): ?>
                <span class="product-detail-discount">%<?php echo $d; ?> İndirim</span>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="product-detail-price"><?php echo $product->get_price_html(); ?></div>
          <?php endif; ?>
        </div>

        <!-- Installment -->
        <?php $price = (float)$product->get_price(); if ($price > 500): ?>
        <div class="installment-info">
          <strong><?php _e('Taksit Seçenekleri:', '724pazaryeri'); ?></strong>
          <div style="margin-top:6px;font-size:12px;">
            3 x <?php echo wc_price($price/3); ?> •
            6 x <?php echo wc_price($price/6); ?> •
            12 x <?php echo wc_price($price/12); ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Add to Cart Form -->
        <?php woocommerce_template_single_add_to_cart(); ?>

        <!-- Trust Badges -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:16px;">
          <?php $badges = [['🚚','Ücretsiz Kargo','100 TL üzeri'],['🔄','Kolay İade','30 gün içinde'],['🔒','Güvenli Ödeme','256-bit SSL'],['📦','Hızlı Teslimat','2-3 iş günü']]; ?>
          <?php foreach ($badges as $b): ?>
          <div style="background:#f9f9f9;border-radius:8px;padding:10px;text-align:center;">
            <div style="font-size:22px;"><?php echo $b[0]; ?></div>
            <div style="font-size:12px;font-weight:600;"><?php echo esc_html($b[1]); ?></div>
            <div style="font-size:11px;color:#999;"><?php echo esc_html($b[2]); ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Seller Info -->
      <div style="background:#fff;border-radius:8px;padding:16px;margin-top:12px;box-shadow:0 2px 8px rgba(0,0,0,.1);">
        <h4 style="font-size:14px;margin-bottom:8px;"><?php _e('Satıcı Bilgisi', '724pazaryeri'); ?></h4>
        <div style="display:flex;align-items:center;gap:10px;">
          <div style="width:40px;height:40px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;">S</div>
          <div>
            <div style="font-weight:600;"><?php echo esc_html(get_bloginfo('name')); ?></div>
            <div style="font-size:12px;color:#999;">⭐ 4.8 · 1250 Satış</div>
          </div>
          <a href="#" style="margin-left:auto;color:var(--primary);font-size:13px;"><?php _e('Mağazaya Git', '724pazaryeri'); ?></a>
        </div>
      </div>
    </div>
  </div>

  <!-- Related Products -->
  <?php
  $related = wc_get_related_products($product->get_id(), 5);
  if (!empty($related)): ?>
  <div style="padding:24px 0;">
    <div class="section-header">
      <h2 class="section-title"><?php _e('Benzer Ürünler', '724pazaryeri'); ?></h2>
    </div>
    <div class="product-grid">
      <?php foreach ($related as $rid):
        $rp = wc_get_product($rid); if (!$rp) continue;
        $product = $rp;
        get_template_part('template-parts/content', 'product-card');
      endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php endwhile; ?>
</div>

<script>
function swapImage(el, src) {
  document.querySelectorAll('.thumb').forEach(function(t){ t.classList.remove('active'); });
  el.classList.add('active');
  document.querySelector('.main-image img').src = src;
}
document.querySelectorAll('.tab-btn').forEach(function(btn) {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tab-btn').forEach(function(b){ b.style.color='#666'; b.style.borderBottomColor='transparent'; });
    document.querySelectorAll('.tab-panel').forEach(function(p){ p.style.display='none'; });
    btn.style.color = 'var(--primary)'; btn.style.borderBottomColor = 'var(--primary)';
    document.getElementById('tab-' + btn.dataset.tab).style.display = 'block';
  });
});
</script>

<?php get_footer('shop'); ?>
