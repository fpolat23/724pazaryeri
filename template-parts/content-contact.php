<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="bc"><div class="bc-in">
  <a class="bc-a" href="<?php echo esc_url( home_url('/') ); ?>">Anasayfa</a><span class="bc-sep">›</span>
  <span style="color:var(--ink)">İletişim</span>
</div></div>

<div class="cw">
  <!-- HERO -->
  <div class="contact-hero">
    <div class="ch-left">
      <h2>Size Nasıl <em>Yardımcı</em> Olabiliriz?</h2>
      <p>7/24 destek ekibimiz tüm sorularınızda yanınızda. Ortalama yanıt süremiz 12 dakika.</p>
      <div class="ch-badges">
        <span class="ch-badge">⚡ Ort. 12 dk yanıt</span>
        <span class="ch-badge">⭐ 4.9/5 memnuniyet</span>
        <span class="ch-badge">🌍 Türkçe & İngilizce</span>
      </div>
    </div>
    <div class="ch-right">
      <div class="ch-avg">12</div>
      <div class="ch-avg-lbl">dakika yanıt süresi</div>
    </div>
  </div>

  <!-- KANALLAR -->
  <div class="contact-channels">
    <div class="cc-card">
      <div class="cc-icon">💬</div>
      <div class="cc-title">Canlı Destek</div>
      <div class="cc-sub">Ekibimizle anlık sohbet edin. Ortalama yanıt 2 dakika.</div>
      <button class="cc-btn primary" onclick="document.getElementById('cf-msg')&&document.getElementById('cf-msg').focus()">Sohbet Başlat</button>
    </div>
    <div class="cc-card">
      <div class="cc-icon">📧</div>
      <div class="cc-title">E-posta Desteği</div>
      <div class="cc-sub"><?php echo esc_html( get_option('admin_email') ); ?> adresine yazın. En geç 4 saat içinde yanıt.</div>
      <a class="cc-btn" href="mailto:<?php echo esc_attr( get_option('admin_email') ); ?>">E-posta Gönder</a>
    </div>
    <div class="cc-card">
      <div class="cc-icon"><svg viewBox="0 0 24 24" width="28" height="28" fill="#25D366"><path d="M17.5 14.4c-.3-.1-1.7-.8-1.9-.9-.3-.1-.5-.1-.7.1-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-1.7-.8-2.8-1.5-3.9-3.4-.3-.5.3-.5.8-1.5.1-.2 0-.4 0-.5 0-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.3 5.2 4.6 2 .8 2.7.9 3.7.8.6-.1 1.7-.7 2-1.4.2-.7.2-1.2.2-1.4-.1-.1-.3-.2-.6-.3zM12 2C6.5 2 2 6.5 2 12c0 1.8.5 3.4 1.3 4.9L2 22l5.3-1.4c1.4.8 3 1.2 4.7 1.2 5.5 0 10-4.5 10-10S17.5 2 12 2z"/></svg></div>
      <div class="cc-title">WhatsApp Desteği</div>
      <div class="cc-sub">Hafta içi 09:00-21:00, Hafta sonu 10:00-18:00</div>
      <a class="cc-btn" href="https://wa.me/905307887540" target="_blank" rel="noopener">WhatsApp'tan Yaz</a>
    </div>
  </div>

  <!-- FORM + FAQ -->
  <div class="contact-main-grid">
    <div class="contact-form-wrap">
      <div class="cfh">
        <div class="cfh-title">Bize Ulaşın</div>
        <div class="cfh-sub">Formu doldurun, en kısa sürede dönüş yapacağız.</div>
      </div>
      <div class="cf-body">
        <form id="pazaryeriContactForm" onsubmit="return submitContactForm(event)">
          <?php wp_nonce_field( 'pazaryeri_contact', 'pazaryeri_contact_nonce' ); ?>
          <div class="cf-row">
            <div class="cf-field"><label class="cf-label">Ad *</label><input class="cf-input" name="ad" placeholder="Adınız" required></div>
            <div class="cf-field"><label class="cf-label">Soyad</label><input class="cf-input" name="soyad" placeholder="Soyadınız"></div>
          </div>
          <div class="cf-row">
            <div class="cf-field"><label class="cf-label">E-posta *</label><input class="cf-input" type="email" name="email" id="cf-email" placeholder="ornek@email.com" required></div>
            <div class="cf-field"><label class="cf-label">Telefon</label><input class="cf-input" name="telefon" placeholder="05xx xxx xx xx"></div>
          </div>
          <div class="cf-field" style="margin-bottom:8px"><label class="cf-label">Konu Kategorisi</label></div>
          <div class="cf-cats">
            <span class="cf-cat on" onclick="selectCat(this)" data-v="Sipariş Sorunu">📦 Sipariş Sorunu</span>
            <span class="cf-cat" onclick="selectCat(this)" data-v="Ödeme">💳 Ödeme</span>
            <span class="cf-cat" onclick="selectCat(this)" data-v="İade & İptal">🔄 İade & İptal</span>
            <span class="cf-cat" onclick="selectCat(this)" data-v="Satıcı Sorunu">🏪 Satıcı Sorunu</span>
            <span class="cf-cat" onclick="selectCat(this)" data-v="Hesap & Güvenlik">🔒 Hesap & Güvenlik</span>
            <span class="cf-cat" onclick="selectCat(this)" data-v="Teknik Sorun">📱 Teknik Sorun</span>
            <span class="cf-cat" onclick="selectCat(this)" data-v="Öneri">💡 Öneri & Geri Bildirim</span>
            <span class="cf-cat" onclick="selectCat(this)" data-v="İş Ortaklığı">🤝 İş Ortaklığı</span>
          </div>
          <input type="hidden" name="kategori" id="cf-kategori" value="Sipariş Sorunu">
          <div class="cf-row full"><div class="cf-field"><label class="cf-label">Konu *</label><input class="cf-input" name="konu" id="cf-subject" placeholder="Mesajınızın konusu" required></div></div>
          <div class="cf-row full"><div class="cf-field"><label class="cf-label">Mesaj *</label><textarea class="cf-textarea" name="mesaj" id="cf-msg" placeholder="Sorununuzu veya mesajınızı detaylıca yazın..." required></textarea></div></div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:12px;color:var(--muted)">
            <input type="checkbox" id="cfConsent" required style="width:14px;height:14px;accent-color:var(--accent)" checked>
            <label for="cfConsent">KVKK kapsamında kişisel verilerimin işlenmesini kabul ediyorum.</label>
          </div>
          <button type="submit" class="cf-submit" id="cfSubmitBtn">Gönder →</button>
          <div class="cf-note" id="cfNote">Yanıtınız tahminen 2-4 saat içinde iletilecektir.</div>
        </form>
      </div>
    </div>

    <!-- FAQ -->
    <div class="faq-wrap">
      <div class="faq-head">
        <div class="faq-title">Sıkça Sorulan Sorular</div>
        <input type="text" class="faq-srch" placeholder="🔍 Soru ara..." onkeyup="faqSearch(this.value)">
      </div>
      <div class="faq-item"><div class="faq-q" onclick="toggleFaq(this)">Siparişim ne zaman gelir? <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div><div class="faq-a">Standart kargoda 1-3 iş günü, ekspres kargoda aynı gün teslimat yapılır. Takip numaranız sipariş onayıyla e-posta ile gönderilir.</div></div>
      <div class="faq-item"><div class="faq-q" onclick="toggleFaq(this)">İade nasıl yapabilirim? <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div><div class="faq-a">Teslimat tarihinden itibaren 14 gün içinde ücretsiz iade hakkınız var. Siparişlerim sayfasından iade talebi oluşturabilirsiniz.</div></div>
      <div class="faq-item"><div class="faq-q" onclick="toggleFaq(this)">Ödeme güvenli mi? <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div><div class="faq-a">Tüm ödemeler 256-bit SSL ile korunur. Ödemeniz, teslim aldığınızı onaylayana kadar güvenli havuzda tutulur.</div></div>
      <div class="faq-item"><div class="faq-q" onclick="toggleFaq(this)">Satıcı güvenilir mi nasıl anlarım? <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div><div class="faq-a">Tüm satıcılar kimlik doğrulamasından geçer. Profilde puan, yorum ve işlem geçmişini görebilirsiniz.</div></div>
      <div class="faq-item"><div class="faq-q" onclick="toggleFaq(this)">Mağaza nasıl açabilirim? <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div><div class="faq-a">Kayıt olduktan sonra "Sat" butonuyla mağazanızı dakikalar içinde açabilirsiniz. İlk 3 ay komisyon sıfır!</div></div>
      <div class="faq-item" style="border:none"><div class="faq-q" onclick="toggleFaq(this)">Ödememi ne zaman alırım? <svg viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg></div><div class="faq-a">Alıcı teslim aldığını onayladıktan sonra 15 gün içinde ödemeniz hesabınıza aktarılır.</div></div>
    </div>
  </div>

  <!-- OFİS BİLGİLERİ -->
  <div class="office-cards">
    <div class="oc"><div class="oc-icon">🏢</div><div class="oc-title">Merkez Ofis</div><div class="oc-detail">Adalet Mahallesi Manas Bulvarı No: 34<br>Folkart Towers İç Kapı No:3408<br>Bayraklı İZMİR</div></div>
    <div class="oc"><div class="oc-icon"><svg viewBox="0 0 24 24" width="28" height="28" fill="#25D366"><path d="M17.5 14.4c-.3-.1-1.7-.8-1.9-.9-.3-.1-.5-.1-.7.1-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-1.7-.8-2.8-1.5-3.9-3.4-.3-.5.3-.5.8-1.5.1-.2 0-.4 0-.5 0-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.3 5.2 4.6 2 .8 2.7.9 3.7.8.6-.1 1.7-.7 2-1.4.2-.7.2-1.2.2-1.4-.1-.1-.3-.2-.6-.3zM12 2C6.5 2 2 6.5 2 12c0 1.8.5 3.4 1.3 4.9L2 22l5.3-1.4c1.4.8 3 1.2 4.7 1.2 5.5 0 10-4.5 10-10S17.5 2 12 2z"/></svg></div><div class="oc-title">WhatsApp</div><div class="oc-detail"><a href="https://wa.me/905307887540" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;">0530 788 75 40</a><br>Hf. içi 09:00-21:00<br>Hf. sonu 10:00-18:00</div></div>
    <div class="oc"><div class="oc-icon">📧</div><div class="oc-title">E-posta</div><div class="oc-detail"><?php echo esc_html( get_option('admin_email') ); ?></div></div>
  </div>
</div>