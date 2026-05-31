=== 724PazarYeri Teması ===

C2C pazar yeri WordPress teması. WooCommerce ile tam entegre.

== KURULUM ==
1. WordPress Panel → Görünüm → Temalar → Yeni Ekle → Tema Yükle
2. 724pazaryeri-theme.zip dosyasını seçin → Şimdi Kur → Etkinleştir
3. WooCommerce kurulu değilse kurun (Eklentiler → Yeni Ekle → WooCommerce)

== KURULUM SONRASI ==
1. ANASAYFA: Otomatik olarak front-page.php gösterilir (slider, kategoriler, ürünler).
   - İsterseniz Ayarlar → Okuma → "Ana sayfanız: En son yazılar" kalabilir;
     tema yine de tasarımlı anasayfayı (front-page.php) gösterir.

2. ÜRÜNLER: WooCommerce → Ürünler → Yeni Ekle.
   Kategorileri şu slug'larla oluşturun (anasayfa sekmeleri için):
   elektronik, moda, ev-dekor, el-yapimi, kitap-ve-hobi, spor, kozmetik

3. SEPET & ÖDEME: WooCommerce kurulumda bu sayfaları otomatik oluşturur.
   Tema bunları otomatik tasarımlı gösterir (page.php).

4. İLETİŞİM: "İletişim" sayfası tema etkinleştirilince otomatik oluşur.
   (İçinde [pazaryeri_contact] shortcode'u veya "724 İletişim Sayfası" şablonu).

5. MENÜ: Görünüm → Menüler → "Ana Menü" konumuna menü atayabilirsiniz (opsiyonel).

== ÖZELLİKLER ==
- Hero slider, Alibaba tarzı dikey kategori menüsü (flyout + banner)
- Sekmeli & kategoriye göre random ürün listesi (AJAX)
- Ürün detay: galeri, varyant, taksit, dinamik yorumlar, ilgili ürünler
- Header sepeti: canlı WooCommerce sepeti
- İletişim: AJAX form (admin'de "İletişim Mesajları")
- Alibaba turuncu (#FF6A00) + Archivo başlık fontu

== GEREKSİNİMLER ==
WordPress 6.0+, PHP 7.4+, WooCommerce 6.0+
