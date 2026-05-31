</main><!-- #main -->

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <!-- Brand -->
      <div class="footer-brand">
        <div class="logo-text">724<span style="color:var(--accent)">Pazaryeri</span></div>
        <p><?php _e('Türkiye\'nin en güvenilir online alışveriş pazaryeri. Milyonlarca ürün, güvenli ödeme, hızlı teslimat.', '724pazaryeri'); ?></p>
        <div class="footer-social">
          <a href="#" class="social-btn" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="social-btn" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          <a href="#" class="social-btn" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
          <a href="#" class="social-btn" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
        </div>
      </div>

      <!-- Kurumsal -->
      <div>
        <div class="footer-col-title"><?php _e('Kurumsal', '724pazaryeri'); ?></div>
        <div class="footer-links">
          <a href="#"><?php _e('Hakkımızda', '724pazaryeri'); ?></a>
          <a href="#"><?php _e('Kariyer', '724pazaryeri'); ?></a>
          <a href="#"><?php _e('Basın Odası', '724pazaryeri'); ?></a>
          <a href="#"><?php _e('Sürdürülebilirlik', '724pazaryeri'); ?></a>
          <a href="#"><?php _e('İletişim', '724pazaryeri'); ?></a>
        </div>
      </div>

      <!-- Yardım -->
      <div>
        <div class="footer-col-title"><?php _e('Yardım', '724pazaryeri'); ?></div>
        <div class="footer-links">
          <a href="#"><?php _e('Sıkça Sorulan Sorular', '724pazaryeri'); ?></a>
          <a href="#"><?php _e('Kargo Takibi', '724pazaryeri'); ?></a>
          <a href="#"><?php _e('İade ve Değişim', '724pazaryeri'); ?></a>
          <a href="#"><?php _e('Güvenli Alışveriş', '724pazaryeri'); ?></a>
          <a href="#"><?php _e('Çerez Politikası', '724pazaryeri'); ?></a>
        </div>
      </div>

      <!-- Satıcı -->
      <div>
        <div class="footer-col-title"><?php _e('Satıcılar', '724pazaryeri'); ?></div>
        <div class="footer-links">
          <a href="#"><?php _e('Satıcı Ol', '724pazaryeri'); ?></a>
          <a href="#"><?php _e('Satıcı Girişi', '724pazaryeri'); ?></a>
          <a href="#"><?php _e('Satıcı Rehberi', '724pazaryeri'); ?></a>
          <a href="#"><?php _e('Komisyon Oranları', '724pazaryeri'); ?></a>
          <a href="#"><?php _e('Kampanya Yönetimi', '724pazaryeri'); ?></a>
        </div>
      </div>

      <!-- Uygulama -->
      <div>
        <div class="footer-col-title"><?php _e('Uygulamayı İndir', '724pazaryeri'); ?></div>
        <div class="footer-app">
          <a href="#" class="app-btn">
            <span class="app-icon">🍎</span>
            <div>
              <div class="app-btn-text"><?php _e('App Store\'dan indir', '724pazaryeri'); ?></div>
              <div class="app-btn-name">App Store</div>
            </div>
          </a>
          <a href="#" class="app-btn">
            <span class="app-icon">🤖</span>
            <div>
              <div class="app-btn-text"><?php _e('Google Play\'den indir', '724pazaryeri'); ?></div>
              <div class="app-btn-name">Google Play</div>
            </div>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer Bottom -->
  <div class="footer-bottom">
    <div class="container" style="display:flex;justify-content:space-between;align-items:center;width:100%">
      <p>&copy; <?php echo date('Y'); ?> 724 Pazaryeri. <?php _e('Tüm hakları saklıdır.', '724pazaryeri'); ?></p>
      <div class="footer-payments">
        <span class="payment-icon">VISA</span>
        <span class="payment-icon">MC</span>
        <span class="payment-icon">TR Pay</span>
        <span class="payment-icon">İyzico</span>
        <span class="payment-icon">PayTR</span>
      </div>
    </div>
  </div>
</footer>

<!-- Toast Notifications -->
<div class="toast-container" id="toast-container"></div>

<!-- Quick View Modal -->
<div id="quick-view-modal" class="modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9999;align-items:center;justify-content:center;">
  <div class="modal-content" style="background:#fff;border-radius:12px;max-width:900px;width:90%;max-height:90vh;overflow:auto;position:relative;">
    <button onclick="document.getElementById('quick-view-modal').style.display='none'"
      style="position:absolute;top:12px;right:16px;font-size:24px;background:none;border:none;cursor:pointer;color:#999;z-index:1">✕</button>
    <div id="quick-view-content"></div>
  </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
