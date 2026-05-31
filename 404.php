<?php get_header(); ?>
<div class="container" style="padding:80px 0;text-align:center;">
  <div style="font-size:80px;">🔍</div>
  <h1 style="font-size:36px;font-weight:700;margin:16px 0 8px;">404</h1>
  <p style="color:#666;font-size:18px;margin-bottom:24px;"><?php _e('Aradığınız sayfa bulunamadı.', '724pazaryeri'); ?></p>
  <a href="<?php echo esc_url(home_url('/')); ?>" style="background:var(--primary);color:#fff;padding:12px 32px;border-radius:8px;font-weight:600;font-size:15px;display:inline-block;">
    <?php _e('Ana Sayfaya Dön', '724pazaryeri'); ?>
  </a>
</div>
<?php get_footer(); ?>
