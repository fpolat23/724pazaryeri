<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header(); ?>
<div class="cw">
  <div style="background:#fff;border:1.5px solid var(--border);border-radius:14px;padding:60px 30px;text-align:center;">
    <div style="font-size:64px;margin-bottom:16px;">🔍</div>
    <h1 style="font-family:'Archivo',sans-serif;font-size:28px;margin-bottom:10px;">Sayfa Bulunamadı</h1>
    <p style="color:var(--muted);margin-bottom:24px;">Aradığınız sayfa taşınmış veya silinmiş olabilir.</p>
    <a class="sbtn solid" href="<?php echo esc_url( home_url('/') ); ?>" style="display:inline-block;padding:12px 28px;background:var(--accent);color:#fff;border-radius:9px;font-weight:700;">Anasayfaya Dön →</a>
  </div>
</div>
<?php get_footer();
