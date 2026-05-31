<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="footer">
  <div class="footer-inner">
    <div class="fgrid">
      <div>
        <div class="fbrand">724<span>PazarYeri</span></div>
        <div class="fdesc">Türkiye'nin en güvenilir kişiden kişiye alışveriş platformu. Milyonlarca ilan, binlerce güvenilir satıcı.</div>
        <div class="fsocials"><div class="fsoc">𝕏</div><div class="fsoc">f</div><div class="fsoc">in</div><div class="fsoc">▶</div></div>
      </div>
      <div><div class="fctitle">Alışveriş</div><a class="flink" href="<?php echo esc_url( home_url('/nasil-alinir/') ); ?>">Nasıl Alınır?</a><a class="flink" href="<?php echo esc_url( home_url('/guvenli-odeme/') ); ?>">Güvenli Ödeme</a><a class="flink" href="<?php echo esc_url( home_url('/kargo-takibi/') ); ?>">Kargo Takibi</a><a class="flink" href="<?php echo esc_url( home_url('/iade-sureci/') ); ?>">İade Süreci</a><a class="flink" href="<?php echo esc_url( home_url('/alici-guvencesi/') ); ?>">Alıcı Güvencesi</a></div>
      <div><div class="fctitle">Satış Yap</div><a class="flink" href="<?php echo esc_url( home_url('/nasil-satilir/') ); ?>">Nasıl Satılır?</a><a class="flink" href="<?php echo esc_url( home_url('/satici-ol/') ); ?>">Mağaza Aç</a><a class="flink" href="<?php echo esc_url( home_url('/komisyon-oranlari/') ); ?>">Komisyon Oranları</a><a class="flink" href="<?php echo esc_url( home_url('/satici-rehberi/') ); ?>">Satıcı Rehberi</a><a class="flink" href="<?php echo esc_url( home_url('/odeme-almak/') ); ?>">Ödeme Almak</a></div>
      <div><div class="fctitle">Yardım</div><a class="flink" href="<?php echo esc_url( home_url('/yardim/') ); ?>">Yardım Merkezi</a><a class="flink" href="<?php echo esc_url( home_url('/iletisim/') ); ?>">Bize Ulaş</a><a class="flink" href="<?php echo esc_url( home_url('/guvenlik/') ); ?>">Güvenlik</a><a class="flink" href="<?php echo esc_url( home_url('/gizlilik/') ); ?>">Gizlilik</a><a class="flink" href="<?php echo esc_url( home_url('/kullanim-kosullari/') ); ?>">Kullanım Koşulları</a></div>
      <div class="fapp-col">
        <div class="fctitle">Mobil Uygulama</div>
        <div class="fapp-desc">Uygulamayı indir, fırsatları kaçırma!</div>
        <a class="fapp-badge" href="https://play.google.com/store/apps/details?id=com.pazaryeri.mobil" target="_blank" rel="noopener" aria-label="Google Play'den indir">
          <svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true"><path fill="#EA4335" d="M3.6 1.8c-.3.3-.5.8-.5 1.4v17.6c0 .6.2 1.1.5 1.4l.1.1L13.5 12 3.7 1.7l-.1.1z" transform="translate(0)"/><path fill="#FBBC04" d="M17 15.3l-3.5-3.3 3.5-3.3 4 2.3c1.1.6 1.1 1.7 0 2.3l-4 2.3z"/><path fill="#34A853" d="M17 15.3L13.5 12 3.6 22.2c.4.4 1 .4 1.7.1L17 15.3z"/><path fill="#4285F4" d="M3.6 1.8L13.5 12 17 8.7 5.3 1.7c-.7-.4-1.3-.3-1.7.1z"/></svg>
          <span class="fapp-badge-txt"><small>İndir</small><b>Google Play</b></span>
        </a>
      </div>
    </div>
    <div class="fbot">
      <div class="fcopy">© 2025 <em>724PazarYeri.com</em> — Tüm hakları saklıdır.</div>
      <div style="display:flex;gap:12px;"><span style="font-size:11px;color:rgba(255,255,255,.25);">🔒 SSL Güvenli</span><span style="font-size:11px;color:rgba(255,255,255,.25);">💳 Tüm kartlar</span></div>
    </div>
  </div>
</div>

<!-- ═══ MOBİL ALT NAVİGASYON ÇUBUĞU ═══ -->
<nav class="mob-bottom-nav">
  <a href="<?php echo esc_url( home_url('/') ); ?>" class="mbn-item<?php echo ( is_front_page() || is_home() ) ? ' on' : ''; ?>">
    <span class="mbn-ico">🏠</span><span class="mbn-label">Anasayfa</span>
  </a>
  <button type="button" class="mbn-item mbn-cat-btn" onclick="pzOpenCatPage()">
    <span class="mbn-ico">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
    </span><span class="mbn-label">Kategoriler</span>
  </button>
  <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="mbn-item">
    <span class="mbn-ico">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
    </span><span class="mbn-label">Favoriler</span>
  </a>
  <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="mbn-item mbn-cart">
    <span class="mbn-ico">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      <?php $mbn_count = ( function_exists('WC') && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0; if ( $mbn_count > 0 ) : ?>
        <span class="mbn-badge"><?php echo esc_html( $mbn_count ); ?></span>
      <?php endif; ?>
    </span><span class="mbn-label">Sepet</span>
  </a>
  <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="mbn-item">
    <span class="mbn-ico">
      <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    </span><span class="mbn-label">Hesabım</span>
  </a>
</nav>

<!-- ═══ TAM EKRAN KATEGORİ SAYFASI (alt menü kategori butonu) ═══ -->
<div class="cat-page" id="catPage">
  <div class="cat-page-head">
    <span class="cat-page-title">Tüm Kategoriler</span>
    <button type="button" class="cat-page-close" onclick="pzCloseCatPage()" aria-label="Kapat">&times;</button>
  </div>
  <div class="cat-page-body">
    <?php
      $cp_cats = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>0, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC', 'number'=>60 ) );
      if ( ! empty( $cp_cats ) && ! is_wp_error( $cp_cats ) ) :
        foreach ( $cp_cats as $cp_cat ) :
          if ( $cp_cat->slug === 'uncategorized' ) continue;
          $cp_thumb = get_term_meta( $cp_cat->term_id, 'thumbnail_id', true );
          $cp_img   = $cp_thumb ? wp_get_attachment_image_url( $cp_thumb, 'thumbnail' ) : '';
          $cp_subs  = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>$cp_cat->term_id, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC', 'number'=>30 ) );
          $cp_has   = ( ! empty( $cp_subs ) && ! is_wp_error( $cp_subs ) );
        ?>
        <div class="cat-page-group">
          <a class="cat-page-main" href="<?php echo esc_url( get_term_link( $cp_cat ) ); ?>">
            <span class="cat-page-ico"><?php if ( $cp_img ) : ?><img src="<?php echo esc_url( $cp_img ); ?>" alt="" loading="lazy"><?php else : ?>📦<?php endif; ?></span>
            <span class="cat-page-name"><?php echo esc_html( $cp_cat->name ); ?></span>
            <span class="cat-page-arrow">›</span>
          </a>
          <?php if ( $cp_has ) : ?>
            <div class="cat-page-subs">
              <?php foreach ( $cp_subs as $cp_sub ) : ?>
                <a class="cat-page-sub" href="<?php echo esc_url( get_term_link( $cp_sub ) ); ?>"><?php echo esc_html( $cp_sub->name ); ?></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php endforeach;
      endif; ?>
  </div>
</div>

</div><!-- /.pazaryeri-wrap -->
<?php wp_footer(); ?>
</body>
</html>
