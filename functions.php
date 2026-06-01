<?php
/**
 * 724PazarYeri Tema Fonksiyonları
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PAZARYERI_VERSION', '9.9.65' );
define( 'PAZARYERI_DIR', get_template_directory() );
define( 'PAZARYERI_URL', get_template_directory_uri() );

/**
 * Attribute etiketini oku; wc_attribute_label kayıtlı değilse
 * pa_ önekini siler ve kelimeyi düzgün biçimlendirir.
 */
function pz_attr_label( $attr_name ) {
    $label = wc_attribute_label( $attr_name );
    if ( strpos( $label, 'pa_' ) === 0 ) {
        $label = str_replace( array( 'pa_', '_', '-' ), array( '', ' ', ' ' ), $label );
        $label = mb_convert_case( trim( $label ), MB_CASE_TITLE, 'UTF-8' );
    }
    return $label;
}

/* ──────────────────────────────────────────────
   1) Tema desteği
────────────────────────────────────────────── */
function pazaryeri_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'custom-logo' );
    add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
    add_theme_support( 'automatic-feed-links' );

    // WooCommerce
    add_theme_support( 'woocommerce' );
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );

    // Menüler
    register_nav_menus( array(
        'primary' => 'Ana Menü',
        'footer'  => 'Alt Menü',
    ) );
}
add_action( 'after_setup_theme', 'pazaryeri_setup' );


/* ──────────────────────────────────────────────
   2) CSS / JS yükle
────────────────────────────────────────────── */
function pazaryeri_enqueue_assets() {
    // Tema ana style.css (header için gerekli)
    wp_enqueue_style( 'pazaryeri-style', get_stylesheet_uri(), array(), PAZARYERI_VERSION );
    // Asıl tasarım CSS'i
    wp_enqueue_style( 'pazaryeri-main', PAZARYERI_URL . '/assets/css/main.min.css', array(), PAZARYERI_VERSION );
    // Fontlar
    // Preconnect: tarayıcı DNS'i önceden hazırlasın
    add_action( 'wp_head', function(){
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    }, 1 );
    wp_enqueue_style( 'pazaryeri-fonts', 'https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800;900&family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap', array(), null );

    // JS
    wp_enqueue_script( 'pazaryeri-main', PAZARYERI_URL . '/assets/js/main.min.js', array(), PAZARYERI_VERSION, true );
    wp_localize_script( 'pazaryeri-main', 'bazario_ajax', array(
        'url'   => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'pazaryeri_nonce' ),
    ) );

    // WooCommerce AJAX sepete ekle
    if ( class_exists( 'WooCommerce' ) ) {
        wp_enqueue_script( 'wc-add-to-cart' );
        wp_enqueue_script( 'wc-cart-fragments' );
    }
}
add_action( 'wp_enqueue_scripts', 'pazaryeri_enqueue_assets' );


/* ──────────────────────────────────────────────
   3) WooCommerce yardımcı fonksiyonları
────────────────────────────────────────────── */
require_once PAZARYERI_DIR . '/inc/woocommerce-functions.php';
require_once PAZARYERI_DIR . '/inc/account.php';
require_once PAZARYERI_DIR . '/inc/help-pages.php';


/* ──────────────────────────────────────────────
   4) İLETİŞİM FORMU — AJAX
────────────────────────────────────────────── */
function pazaryeri_contact_submit() {
    check_ajax_referer( 'pazaryeri_nonce', 'nonce' );
    $ad       = isset($_POST['ad']) ? sanitize_text_field($_POST['ad']) : '';
    $soyad    = isset($_POST['soyad']) ? sanitize_text_field($_POST['soyad']) : '';
    $email    = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    $telefon  = isset($_POST['telefon']) ? sanitize_text_field($_POST['telefon']) : '';
    $kategori = isset($_POST['kategori']) ? sanitize_text_field($_POST['kategori']) : '';
    $konu     = isset($_POST['konu']) ? sanitize_text_field($_POST['konu']) : '';
    $mesaj    = isset($_POST['mesaj']) ? sanitize_textarea_field($_POST['mesaj']) : '';

    if ( empty($ad) || empty($email) || empty($mesaj) || ! is_email($email) ) {
        wp_send_json_error( array( 'message' => 'Lütfen zorunlu alanları doğru doldurun.' ) );
    }
    $to = get_option('admin_email');
    $subject = sprintf('[İletişim - %s] %s', $kategori, $konu);
    $body  = "Ad Soyad: {$ad} {$soyad}\nE-posta: {$email}\nTelefon: {$telefon}\nKategori: {$kategori}\nKonu: {$konu}\n\nMesaj:\n{$mesaj}\n";
    $headers = array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $email );
    $sent = wp_mail( $to, $subject, $body, $headers );
    wp_insert_post( array(
        'post_type'    => 'pazaryeri_message',
        'post_title'   => $konu . ' — ' . $ad . ' ' . $soyad,
        'post_content' => $body,
        'post_status'  => 'private',
    ) );
    if ( $sent ) {
        wp_send_json_success( array( 'message' => 'Mesajınız iletildi! En geç 4 saat içinde ' . $email . ' adresinize dönüş yapacağız.' ) );
    } else {
        wp_send_json_success( array( 'message' => 'Mesajınız kaydedildi! En kısa sürede dönüş yapacağız.' ) );
    }
}
add_action( 'wp_ajax_pazaryeri_contact', 'pazaryeri_contact_submit' );
add_action( 'wp_ajax_nopriv_pazaryeri_contact', 'pazaryeri_contact_submit' );


/* ──────────────────────────────────────────────
   5) İletişim mesajları CPT
────────────────────────────────────────────── */
add_action( 'init', function () {
    register_post_type( 'pazaryeri_message', array(
        'labels'    => array( 'name' => 'İletişim Mesajları', 'singular_name' => 'Mesaj' ),
        'public'    => false,
        'show_ui'   => true,
        'menu_icon' => 'dashicons-email',
        'supports'  => array( 'title', 'editor' ),
    ) );
} );


/* ──────────────────────────────────────────────
   6) İletişim shortcode (sayfada [pazaryeri_contact] ile de kullanılabilir)
────────────────────────────────────────────── */
add_shortcode( 'pazaryeri_contact', function () {
    ob_start();
    get_template_part( 'template-parts/content', 'contact' );
    return ob_get_clean();
} );
add_shortcode( 'pazaryeri_home', function () {
    ob_start();
    get_template_part( 'template-parts/content', 'home' );
    return ob_get_clean();
} );


/* ──────────────────────────────────────────────
   7) AJAX add to cart ayarı + buton metni
────────────────────────────────────────────── */
add_filter( 'woocommerce_product_single_add_to_cart_text', function() { return '🛒 Sepete Ekle'; } );
add_filter( 'option_woocommerce_enable_ajax_add_to_cart', function() { return 'yes'; } );

// İçerik genişliği
if ( ! isset( $content_width ) ) $content_width = 1320;


/* ──────────────────────────────────────────────
   8) Kurulumda örnek sayfaları oluştur (tema etkinleştirilince)
────────────────────────────────────────────── */
/* ──────────────────────────────────────────────
   9) Ürün detay şablonunu kesin olarak yükle
   (WooCommerce/Elementor/başka eklenti ezmesin diye)
────────────────────────────────────────────── */
add_filter( 'template_include', function ( $template ) {
    if ( ! function_exists( 'is_product' ) ) return $template;
    // Tek ürün sayfası
    if ( is_product() ) {
        $custom = PAZARYERI_DIR . '/woocommerce/single-product.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    // Shop ana sayfası, ürün kategorisi, etiket, marka arşivleri
    if ( is_shop() || is_product_category() || is_product_tag() || is_tax( 'product_brand' ) ) {
        $custom = PAZARYERI_DIR . '/woocommerce/archive-product.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    return $template;
}, 999 );

// Elementor/sayfa oluşturucu ürün şablonu override'larını devre dışı bırak
add_filter( 'elementor/theme/need_override_location', '__return_false', 999 );


/* ──────────────────────────────────────────────
   Yardım sayfalarını garanti et (tema yeniden etkinleştirilmese de)
────────────────────────────────────────────── */
add_action( 'init', function () {
    // Bir kerelik çalışsın (sürüm bazlı flag)
    if ( get_option( 'pazaryeri_pages_created' ) === PAZARYERI_VERSION ) {
        return;
    }
    if ( function_exists( 'pazaryeri_create_help_pages' ) ) {
        pazaryeri_create_help_pages();
    }
    // İletişim sayfası
    if ( ! get_page_by_path( 'iletisim' ) ) {
        wp_insert_post( array(
            'post_title'   => 'İletişim',
            'post_name'    => 'iletisim',
            'post_content' => '[pazaryeri_contact]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
    }
    // Satıcı Ol sayfası
    $satici_page = get_page_by_path( 'satici-ol' );
    if ( ! $satici_page ) {
        wp_insert_post( array(
            'post_title'   => 'Satıcı Ol — Başvuru Formu',
            'post_name'    => 'satici-ol',
            'post_content' => '[pazaryeri_seller_apply]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
    } else {
        // Sayfa varsa, içeriğinde shortcode'u garantile (kullanıcı resim/yanlış içerik koymuşsa düzelt)
        if ( strpos( $satici_page->post_content, '[pazaryeri_seller_apply]' ) === false ) {
            wp_update_post( array(
                'ID'           => $satici_page->ID,
                'post_content' => '[pazaryeri_seller_apply]',
                'post_status'  => 'publish',
            ) );
        }
    }
    update_option( 'pazaryeri_pages_created', PAZARYERI_VERSION );
    flush_rewrite_rules();
}, 20 );


add_action( 'after_switch_theme', function () {
    // İletişim sayfası
    if ( ! get_page_by_path( 'iletisim' ) ) {
        wp_insert_post( array(
            'post_title'   => 'İletişim',
            'post_name'    => 'iletisim',
            'post_content' => '[pazaryeri_contact]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
    }
    // WooCommerce sayfalarını ve endpoint'lerini garanti et
    if ( class_exists( 'WooCommerce' ) ) {
        WC_Install::create_pages();
        // My Account endpoint'lerini yeniden kaydet
        if ( function_exists( 'WC' ) && isset( WC()->query ) ) {
            WC()->query->init_query_vars();
            WC()->query->add_endpoints();
        }
    }
    // Yardım/bilgi sayfalarını oluştur
    if ( function_exists( 'pazaryeri_create_help_pages' ) ) {
        pazaryeri_create_help_pages();
    }
    // Kritik: rewrite kurallarını yenile (adres/endpoint hatalarını çözer)
    flush_rewrite_rules();
} );

// WooCommerce endpoint query var'larını her zaman kayıtlı tut (adres ekleme hatası önlemi)
add_action( 'init', function () {
    if ( class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && isset( WC()->query ) ) {
        WC()->query->add_endpoints();
    }
}, 5 );


/* ──────────────────────────────────────────────
   WP Rocket / cache: kritik JS'i (slider) erteleme/geciktirme dışı bırak
────────────────────────────────────────────── */
// WP Rocket — JS'i geciktirme (delay) dışında tut
add_filter( 'rocket_delay_js_exclusions', function ( $excluded ) {
    $excluded[] = 'assets/js/main.js';
    return $excluded;
} );
// WP Rocket — JS birleştirme dışında tut
add_filter( 'rocket_exclude_js', function ( $excluded ) {
    $excluded[] = '/wp-content/themes/724pazaryeri-theme/assets/js/main.js';
    return $excluded;
} );
// WP Rocket — defer dışında tut
add_filter( 'rocket_defer_inline_exclusions', function ( $ex ) { return $ex; } );


/* ───────────────────────────────────────────────
   Son Gezilen Ürünler — Sepet sayfası altına ekle
─────────────────────────────────────────────── */
add_action( 'woocommerce_after_cart', function() {
    if ( function_exists( 'pz_recently_viewed_block' ) ) {
        echo '<div style="margin-top:28px;">';
        echo pz_recently_viewed_block( '🕐 Belki Bunları da İstersin', false );
        echo '</div>';
    }
}, 20 );


/* ───────────────────────────────────────────────
   PERFORMANS: gereksiz WP istek ve script kapatma
─────────────────────────────────────────────── */
// Emoji script'leri (gereksiz, render-blocking)
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );

// oEmbed (Twitter/YouTube embed) — kullanılmıyor
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );

// REST API link (head'den kaldır, REST yine çalışır)
remove_action( 'wp_head', 'rest_output_link_wp_head' );

// RSD/WLwManifest (eski WP istekleri)
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );

// Shortlink (?p= linki) — gereksiz
remove_action( 'wp_head', 'wp_shortlink_wp_head' );

// jQuery Migrate (eski jQuery uyumluluğu) — WP 5.5+ değiştirme
add_action( 'wp_default_scripts', function( $scripts ){
    if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
        $script = $scripts->registered['jquery'];
        if ( $script->deps ) {
            $script->deps = array_diff( $script->deps, array( 'jquery-migrate' ) );
        }
    }
} );

// DNS prefetch limitle (WooCommerce'in 4-5 prefetch'i)
add_filter( 'wp_resource_hints', function( $hints, $relation_type ){
    if ( 'dns-prefetch' === $relation_type ) {
        return array_filter( $hints, function( $h ){
            return strpos( $h, 's.w.org' ) === false;
        });
    }
    return $hints;
}, 10, 2 );

// Resimlere lazy loading + decoding=async otomatik (WP zaten lazy yapıyor ama tüm img'lere)
add_filter( 'wp_get_attachment_image_attributes', function( $attr ){
    if ( ! isset( $attr['loading'] ) ) $attr['loading'] = 'lazy';
    if ( ! isset( $attr['decoding'] ) ) $attr['decoding'] = 'async';
    return $attr;
} );

// Heartbeat'i frontend'de durdur (admin'de devam etsin)
add_action( 'init', function(){
    if ( ! is_admin() ) {
        wp_deregister_script( 'heartbeat' );
    }
}, 1 );

// WooCommerce stil/script dosyalari sadece woocommerce sayfalarinda yuklensin
add_action( 'wp_enqueue_scripts', function(){
    if ( function_exists( 'is_woocommerce' ) ) {
        if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
            wp_dequeue_style( 'woocommerce-general' );
            wp_dequeue_style( 'woocommerce-layout' );
            wp_dequeue_style( 'woocommerce-smallscreen' );
            wp_dequeue_script( 'wc-cart-fragments' );
            wp_dequeue_script( 'woocommerce' );
        }
    }
}, 99 );


/* ───────────────────────────────────────────────
   Ücretsiz Kargo Eşiği (1500₺) Bildirimi
─────────────────────────────────────────────── */
add_action( 'woocommerce_before_cart', 'pz_free_shipping_notice', 5 );
add_action( 'woocommerce_before_checkout_form', 'pz_free_shipping_notice', 5 );
function pz_free_shipping_notice() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) return;
    $threshold = 1500;
    $subtotal = (float) WC()->cart->get_subtotal();
    if ( $subtotal <= 0 ) return;
    if ( $subtotal >= $threshold ) {
        echo '<div class="pz-fs-notice pz-fs-success">🎉 <strong>Tebrikler!</strong> Siparişin ücretsiz kargoya hak kazandı.</div>';
    } else {
        $left = $threshold - $subtotal;
        $pct  = min( 100, round( ( $subtotal / $threshold ) * 100 ) );
        echo '<div class="pz-fs-notice pz-fs-progress">';
        echo '<div class="pz-fs-text">🚚 Ücretsiz kargoya kalan: <strong>' . wc_price( $left ) . '</strong></div>';
        echo '<div class="pz-fs-bar"><div class="pz-fs-bar-fill" style="width:' . esc_attr( $pct ) . '%;"></div></div>';
        echo '</div>';
    }
}


/* ───────────────────────────────────────────────
   SATICI BAŞVURU SİSTEMİ
─────────────────────────────────────────────── */

// CPT: Satıcı Başvuruları (admin panelinde yönetilebilir)
add_action( 'init', function () {
    register_post_type( 'seller_apply', array(
        'labels'    => array( 'name' => 'Satıcı Başvuruları', 'singular_name' => 'Başvuru' ),
        'public'    => false,
        'show_ui'   => true,
        'menu_icon' => 'dashicons-store',
        'supports'  => array( 'title' ),
        'capability_type' => 'post',
    ) );
} );

// Shortcode: [pazaryeri_seller_apply]
add_shortcode( 'pazaryeri_seller_apply', function () {
    ob_start();
    $sent = isset( $_GET['gonderildi'] ) && $_GET['gonderildi'] === '1';
    $error = isset( $_GET['hata'] ) ? sanitize_text_field( $_GET['hata'] ) : '';
    $user = wp_get_current_user();
    ?>
    <div class="pz-seller-page">
      <div class="pz-seller-hero">
        <div class="pz-seller-hero-ico">🚀</div>
        <h1 class="pz-seller-hero-title">Satıcı Ol, Kazanmaya Başla</h1>
        <p class="pz-seller-hero-sub">Türkiye'nin en güvenilir kişiden kişiye pazarında mağazanı aç. <strong>İlk 3 ay komisyon SIFIR.</strong></p>
        <div class="pz-seller-perks">
          <div class="pz-seller-perk"><span>✓</span> Kolay mağaza kurulumu</div>
          <div class="pz-seller-perk"><span>✓</span> Hızlı ödeme garantisi</div>
          <div class="pz-seller-perk"><span>✓</span> 7/24 satıcı desteği</div>
          <div class="pz-seller-perk"><span>✓</span> Milyonlara ulaşma</div>
        </div>
      </div>

      <?php if ( $sent ) : ?>
        <div class="pz-seller-success">
          <div class="pz-seller-success-ico">🎉</div>
          <h2>Başvurun Bize Ulaştı!</h2>
          <p>Ekibimiz başvurunu inceleyecek ve en kısa sürede ( <strong>24-48 saat</strong> içinde ) seninle iletişime geçecek.</p>
          <p><a href="<?php echo esc_url( home_url('/') ); ?>" class="pz-seller-back-btn">← Anasayfaya Dön</a></p>
        </div>
      <?php else : ?>
        <?php if ( $error ) : ?>
          <div class="pz-seller-error">⚠ <?php echo esc_html( $error ); ?></div>
        <?php endif; ?>
        <form class="pz-seller-form" method="post" action="">
          <?php
            wp_nonce_field( 'pz_seller_apply', 'pz_seller_nonce' );
            // Robot kontrolü: rastgele iki sayı
            $pz_n1 = rand(1, 9); $pz_n2 = rand(1, 9);
            // Token: IP + UserAgent hash (cookie YOK - headers already sent riski olmasın)
            $pz_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
            $pz_ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( $_SERVER['HTTP_USER_AGENT'], 0, 100 ) : '';
            $pz_token = md5( $pz_ip . '|' . $pz_ua . '|' . wp_salt() );
            @set_transient( 'pz_apply_math_' . $pz_token, $pz_n1 + $pz_n2, 30 * MINUTE_IN_SECONDS );
            @set_transient( 'pz_apply_start_' . $pz_token, time(), 30 * MINUTE_IN_SECONDS );
          ?>
          <input type="hidden" name="pz_seller_action" value="submit">
          <input type="hidden" name="pz_apply_token" value="<?php echo esc_attr( $pz_token ); ?>">
          <!-- Honeypot: gizli alan, sadece botlar doldurur -->
          <div style="position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden;" aria-hidden="true">
            <label>Web siteniz (boş bırakın):</label>
            <input type="text" name="pz_website_hp" tabindex="-1" autocomplete="off" value="">
          </div>

          <h3 class="pz-seller-form-title">📝 Başvuru Formu</h3>

          <div class="pz-seller-grid">
            <div class="pz-seller-field">
              <label>Ad Soyad <span>*</span></label>
              <input type="text" name="ad_soyad" required value="<?php echo esc_attr( $user->display_name ?: '' ); ?>">
            </div>
            <div class="pz-seller-field">
              <label>E-posta <span>*</span></label>
              <div class="pz-email-row">
                <input type="email" name="email" id="pz-email-input" required value="<?php echo esc_attr( $user->user_email ?: '' ); ?>">
                <button type="button" class="pz-send-code-btn" onclick="pzSendVerifyCode(this)">📩 Kod Gönder</button>
              </div>
              <div class="pz-code-row" id="pz-code-row" style="display:none;">
                <input type="text" name="email_code" maxlength="6" pattern="[0-9]{6}" placeholder="6 haneli kod" autocomplete="off">
                <span class="pz-code-help">📥 E-postanı kontrol et</span>
              </div>
            </div>
            <div class="pz-seller-field">
              <label>Telefon <span>*</span></label>
              <input type="tel" name="telefon" required maxlength="20" pattern="^(\+90\s?)?0?5[0-9]{2}\s?[0-9]{3}\s?[0-9]{2}\s?[0-9]{2}$" placeholder="05XX XXX XX XX" title="Geçerli bir Türkiye cep telefonu (05XX XXX XX XX) girin">
            </div>
            <div class="pz-seller-field">
              <label>Şehir <span>*</span></label>
              <input type="text" name="sehir" required placeholder="Örn: İzmir">
            </div>
            <div class="pz-seller-field pz-seller-full">
              <label>Açmak İstediğin Mağaza Adı <span>*</span></label>
              <input type="text" name="magaza_adi" required placeholder="Örn: Ahmet Tekstil">
            </div>
            <div class="pz-seller-field pz-seller-full">
              <label>Hangi Kategoride Satış Yapacaksın? <span>*</span></label>
              <select name="kategori" required>
                <option value="">Seçin...</option>
                <option>Giyim & Aksesuar</option>
                <option>Ayakkabı & Çanta</option>
                <option>Elektronik</option>
                <option>Ev & Yaşam</option>
                <option>Kozmetik & Kişisel Bakım</option>
                <option>Anne & Bebek</option>
                <option>Spor & Outdoor</option>
                <option>Kitap, Müzik, Film</option>
                <option>Süpermarket / Gıda</option>
                <option>Otomotiv</option>
                <option>Hobi & El Sanatları</option>
                <option>Diğer</option>
              </select>
            </div>
            <div class="pz-seller-field pz-seller-full">
              <label>E-ticaret Deneyimin <span>*</span></label>
              <select name="deneyim" required>
                <option value="">Seçin...</option>
                <option>Hiç yok, yeni başlıyorum</option>
                <option>1 yıldan az</option>
                <option>1-3 yıl</option>
                <option>3 yıldan fazla</option>
              </select>
            </div>
            <div class="pz-seller-field pz-seller-full">
              <label>Vergi Mükellefi misin?</label>
              <select name="vergi">
                <option value="">Seçin...</option>
                <option>Evet, şahıs şirketi</option>
                <option>Evet, limited / anonim şirket</option>
                <option>Hayır, bireysel satış yapacağım</option>
              </select>
            </div>
            <div class="pz-seller-field pz-seller-full">
              <label>Eklemek İstediğin Notlar / Sorular</label>
              <textarea name="mesaj" rows="4" placeholder="Eklemek istediğin her şeyi buraya yazabilirsin..."></textarea>
            </div>
            <div class="pz-seller-field pz-seller-full">
              <label>🤖 Robot olmadığını kanıtla <span>*</span></label>
              <div class="pz-math-row">
                <span class="pz-math-q"><strong><?php echo (int) $pz_n1; ?> + <?php echo (int) $pz_n2; ?> = ?</strong></span>
                <input type="number" name="pz_math" required min="0" max="20" placeholder="Sonuç" style="max-width:120px;">
              </div>
            </div>
            <div class="pz-seller-field pz-seller-full pz-seller-consent">
              <label><input type="checkbox" name="kvkk" required> <a href="<?php echo esc_url( home_url('/gizlilik/') ); ?>" target="_blank">KVKK Aydınlatma Metni</a>'ni okudum, kişisel verilerimin başvurum kapsamında işlenmesini kabul ediyorum.</label>
            </div>
          </div>

          <button type="submit" class="pz-seller-submit">🚀 Başvurumu Gönder</button>
          <p class="pz-seller-note">Başvurun ekibimize ulaşacak ve 24-48 saat içinde dönüş yapılacaktır.</p>
        </form>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
} );

// Form gönderim handler
add_action( 'init', function () {
    if ( ! isset( $_POST['pz_seller_action'] ) || $_POST['pz_seller_action'] !== 'submit' ) return;
    if ( ! isset( $_POST['pz_seller_nonce'] ) || ! wp_verify_nonce( $_POST['pz_seller_nonce'], 'pz_seller_apply' ) ) {
        wp_safe_redirect( add_query_arg( 'hata', 'Güvenlik kontrolü başarısız. Tekrar deneyin.', wp_get_referer() ?: home_url('/satici-ol/') ) );
        exit;
    }

    $ad = sanitize_text_field( wp_unslash( $_POST['ad_soyad'] ?? '' ) );
    $em = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    $tl = sanitize_text_field( wp_unslash( $_POST['telefon'] ?? '' ) );
    $sh = sanitize_text_field( wp_unslash( $_POST['sehir'] ?? '' ) );
    $mg = sanitize_text_field( wp_unslash( $_POST['magaza_adi'] ?? '' ) );
    $kt = sanitize_text_field( wp_unslash( $_POST['kategori'] ?? '' ) );
    $dn = sanitize_text_field( wp_unslash( $_POST['deneyim'] ?? '' ) );
    $vr = sanitize_text_field( wp_unslash( $_POST['vergi'] ?? '' ) );
    $ms = sanitize_textarea_field( wp_unslash( $_POST['mesaj'] ?? '' ) );
    $kvkk = ! empty( $_POST['kvkk'] );

    if ( ! $ad || ! $em || ! $tl || ! $sh || ! $mg || ! $kt || ! $dn || ! $kvkk ) {
        wp_safe_redirect( add_query_arg( "hata", "Lütfen tüm zorunlu alanları doldurun ve KVKK onayını işaretleyin.", wp_get_referer() ?: home_url('/satici-ol/') ) );
        exit;
    }
    if ( ! is_email( $em ) ) {
        wp_safe_redirect( add_query_arg( 'hata', 'Geçerli bir e-posta adresi girin.', wp_get_referer() ?: home_url('/satici-ol/') ) );
        exit;
    }

    // ── TELEFON FORMAT KONTROLÜ ──
    $tl_clean = preg_replace( '/[^0-9]/', '', $tl );
    if ( strpos( $tl_clean, '90' ) === 0 && strlen( $tl_clean ) === 12 ) {
        $tl_clean = substr( $tl_clean, 2 );
    } elseif ( strpos( $tl_clean, '0' ) === 0 ) {
        $tl_clean = substr( $tl_clean, 1 );
    }
    if ( strlen( $tl_clean ) !== 10 || $tl_clean[0] !== '5' ) {
        wp_safe_redirect( add_query_arg( 'hata', 'Geçerli bir cep telefonu girin (05XX XXX XX XX formatında).', home_url('/satici-ol/') ) );
        exit;
    }
    // Standart formata getir: 0530 788 75 40
    $tl = '0' . substr( $tl_clean, 0, 3 ) . ' ' . substr( $tl_clean, 3, 3 ) . ' ' . substr( $tl_clean, 6, 2 ) . ' ' . substr( $tl_clean, 8, 2 );
    // Karşılaştırma için normalize formu (sadece rakam, 10 hane)
    $tl_normalized = $tl_clean;

    // ── DUPLICATE KONTROL: aynı e-posta veya telefonla başvuru yapılmış mı? ──
    // 1) Aynı e-posta ile aktif vendor varsa (vendor eklentisi varsa)
    if ( class_exists( 'PZV_Roles' ) ) {
        $exu = get_user_by( 'email', $em );
        if ( $exu && method_exists( 'PZV_Roles', 'is_vendor' ) && PZV_Roles::is_vendor( $exu->ID ) ) {
            wp_safe_redirect( add_query_arg( 'hata', 'Bu e-posta adresiyle zaten aktif bir satıcı hesabı var. Lütfen mevcut hesabınızla giriş yapın.', home_url('/satici-ol/') ) );
            exit;
        }
    }
    // 2) Aynı e-posta veya telefonla seller_apply CPT'de kayıt var mı? (Doğrudan SQL - init hook'unda güvenli)
    global $wpdb;
    $dup_email_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT pm.post_id FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = 'email' AND pm.meta_value = %s
           AND p.post_type = 'seller_apply'
           AND p.post_status IN ('publish','pending','draft','private')
         LIMIT 1",
        $em
    ) );
    if ( $dup_email_id ) {
        wp_safe_redirect( add_query_arg( 'hata', 'Daha önce bu e-posta adresiyle başvuru yapılmış. Aynı kişi tekrar başvuramaz. Durumunuz için bizimle iletişime geçin: fpolat23@gmail.com', home_url('/satici-ol/') ) );
        exit;
    }
    $dup_phone_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT pm.post_id FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE (pm.meta_key = 'telefon_norm' AND pm.meta_value = %s)
            OR (pm.meta_key = 'telefon' AND pm.meta_value = %s)
         AND p.post_type = 'seller_apply'
         AND p.post_status IN ('publish','pending','draft','private')
         LIMIT 1",
        $tl_normalized, $tl
    ) );
    if ( $dup_phone_id ) {
        wp_safe_redirect( add_query_arg( 'hata', 'Daha önce bu telefon numarasıyla başvuru yapılmış. Aynı kişi tekrar başvuramaz. Durumunuz için bizimle iletişime geçin: fpolat23@gmail.com', home_url('/satici-ol/') ) );
        exit;
    }

    // Admin panelinde kayıt (CPT)
    $post_id = wp_insert_post( array(
        'post_type'   => 'seller_apply',
        'post_title'  => $mg . ' — ' . $ad,
        'post_status' => 'publish',
        'meta_input'  => array(
            'ad_soyad'    => $ad,
            'email'       => $em,
            'telefon'     => $tl,
            'telefon_norm'=> $tl_normalized,
            'sehir'       => $sh,
            'magaza_adi'  => $mg,
            'kategori'    => $kt,
            'deneyim'     => $dn,
            'vergi'       => $vr,
            'mesaj'       => $ms,
            'tarih'       => current_time( 'mysql' ),
            'ip'          => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
        ),
    ) );

    // Admin'e mail
    $admin_email = get_option( 'admin_email' );
    $subject = sprintf( '[724PazarYeri] Yeni Satıcı Başvurusu — %s (%s)', $mg, $kt );
    $body  = "Yeni bir satıcı başvurusu alındı.

";
    $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
";
    $body .= "👤 Ad Soyad : {$ad}
";
    $body .= "📧 E-posta  : {$em}
";
    $body .= "📱 Telefon  : {$tl}
";
    $body .= "📍 Şehir    : {$sh}
";
    $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
";
    $body .= "🏪 Mağaza Adı  : {$mg}
";
    $body .= "📂 Kategori    : {$kt}
";
    $body .= "⏳ Deneyim     : {$dn}
";
    $body .= "🏛 Vergi Durumu: " . ( $vr ?: '-' ) . "
";
    $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
";
    if ( $ms ) $body .= "💬 Notlar:
{$ms}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
";
    $body .= "
🔗 Yönetim Paneli: " . admin_url( 'edit.php?post_type=seller_apply' ) . "
";
    $body .= "📅 Tarih: " . current_time( 'd.m.Y H:i' ) . "
";

    $headers = array( 'Content-Type: text/plain; charset=UTF-8', 'Reply-To: ' . $ad . ' <' . $em . '>' );
    $admin_sent = wp_mail( $admin_email, $subject, $body, $headers );
    // Başvuruya mail durumu meta'sı ekle (admin panelde görünür)
    if ( $post_id ) {
        update_post_meta( $post_id, 'mail_admin_sent', $admin_sent ? '✓ Gönderildi' : '✗ Gönderilemedi' );
        update_post_meta( $post_id, 'mail_admin_to', $admin_email );
    }

    // Başvurana onay maili
    $user_subject = '724PazarYeri — Satıcı Başvurun Alındı';
    $user_body  = "Merhaba {$ad},

";
    $user_body .= "724PazarYeri'ne satıcı başvurun başarıyla alındı. 🎉

";
    $user_body .= "Ekibimiz başvurunu inceleyecek ve en kısa sürede (24-48 saat içinde) seninle iletişime geçecek.

";
    $user_body .= "Başvuru özetin:
";
    $user_body .= "• Mağaza Adı: {$mg}
";
    $user_body .= "• Kategori  : {$kt}
";
    $user_body .= "• Telefon   : {$tl}

";
    $user_body .= "Herhangi bir sorun olursa bize ulaşabilirsin: " . home_url('/iletisim/') . "

";
    $user_body .= "Sevgiler,
724PazarYeri Ekibi
" . home_url('/');
    $user_sent = wp_mail( $em, $user_subject, $user_body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
    if ( $post_id ) {
        update_post_meta( $post_id, 'mail_user_sent', $user_sent ? '✓ Gönderildi' : '✗ Gönderilemedi' );
    }

    wp_safe_redirect( add_query_arg( 'gonderildi', '1', home_url('/satici-ol/') ) );
    exit;
}, 5 );

// Admin: başvuru detaylarını liste kolonlarında göster
add_filter( 'manage_seller_apply_posts_columns', function( $cols ){
    $new = array( 'cb' => $cols['cb'] ?? '' );
    $new['title']    = 'Mağaza / Başvuran';
    $new['telefon']  = 'Telefon';
    $new['email']    = 'E-posta';
    $new['kategori'] = 'Kategori';
    $new['sehir']    = 'Şehir';
    $new['date']     = 'Tarih';
    return $new;
} );
add_action( 'manage_seller_apply_posts_custom_column', function( $col, $pid ) {
    $v = get_post_meta( $pid, $col, true );
    echo esc_html( $v );
}, 10, 2 );

/* ───────────────────────────────────────────────
   Satıcı Başvurusu - Admin: META BOX (tüm bilgileri göster)
─────────────────────────────────────────────── */
add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'pz_seller_apply_details',
        '📋 Başvuru Bilgileri',
        'pz_seller_apply_meta_box',
        'seller_apply',
        'normal',
        'high'
    );
} );
function pz_seller_apply_meta_box( $post ) {
    $fields = array(
        'ad_soyad'        => array( 'icon' => '👤', 'label' => 'Ad Soyad' ),
        'email'           => array( 'icon' => '📧', 'label' => 'E-posta' ),
        'telefon'         => array( 'icon' => '📱', 'label' => 'Telefon' ),
        'sehir'           => array( 'icon' => '📍', 'label' => 'Şehir' ),
        'magaza_adi'      => array( 'icon' => '🏪', 'label' => 'Mağaza Adı' ),
        'kategori'        => array( 'icon' => '📂', 'label' => 'Kategori' ),
        'deneyim'         => array( 'icon' => '⏳', 'label' => 'Deneyim' ),
        'vergi'           => array( 'icon' => '🏛', 'label' => 'Vergi Durumu' ),
        'mesaj'           => array( 'icon' => '💬', 'label' => 'Notlar / Mesaj' ),
        'tarih'           => array( 'icon' => '📅', 'label' => 'Başvuru Tarihi' ),
        'ip'              => array( 'icon' => '🌐', 'label' => 'IP Adresi' ),
        'mail_admin_sent' => array( 'icon' => '📨', 'label' => 'Admin Mail Durumu' ),
        'mail_user_sent'  => array( 'icon' => '📩', 'label' => 'Onay Mail Durumu' ),
    );
    echo '<table class="form-table" style="background:#fafafa;padding:14px;border-radius:8px;">';
    foreach ( $fields as $key => $meta ) {
        $v = get_post_meta( $post->ID, $key, true );
        if ( $key === 'mesaj' ) {
            echo '<tr><th style="vertical-align:top;padding:10px 0;width:200px;"><strong>' . $meta['icon'] . ' ' . esc_html( $meta['label'] ) . '</strong></th><td><div style="background:#fff;padding:10px;border-radius:6px;border:1px solid #ddd;min-height:60px;white-space:pre-wrap;">' . ( $v ? esc_html( $v ) : '<em style="color:#999;">Belirtilmedi</em>' ) . '</div></td></tr>';
        } elseif ( $key === 'email' && $v ) {
            echo '<tr><th style="padding:10px 0;"><strong>' . $meta['icon'] . ' ' . esc_html( $meta['label'] ) . '</strong></th><td><a href="mailto:' . esc_attr( $v ) . '" style="font-size:14px;font-weight:600;">' . esc_html( $v ) . '</a></td></tr>';
        } elseif ( $key === 'telefon' && $v ) {
            echo '<tr><th style="padding:10px 0;"><strong>' . $meta['icon'] . ' ' . esc_html( $meta['label'] ) . '</strong></th><td><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $v ) ) . '" style="font-size:14px;font-weight:600;">' . esc_html( $v ) . '</a></td></tr>';
        } else {
            echo '<tr><th style="padding:10px 0;"><strong>' . $meta['icon'] . ' ' . esc_html( $meta['label'] ) . '</strong></th><td style="font-size:14px;">' . ( $v ? esc_html( $v ) : '<em style="color:#999;">Belirtilmedi</em>' ) . '</td></tr>';
        }
    }
    echo '</table>';

    // Hızlı işlem butonları
    $email = get_post_meta( $post->ID, 'email', true );
    $tel   = get_post_meta( $post->ID, 'telefon', true );
    if ( $email || $tel ) {
        echo '<div style="margin-top:18px;padding-top:14px;border-top:1px solid #ddd;">';
        echo '<strong>Hızlı İletişim:</strong> ';
        if ( $email ) echo '<a href="mailto:' . esc_attr( $email ) . '" class="button button-primary" style="margin-right:8px;">📧 E-posta Gönder</a>';
        if ( $tel )   echo '<a href="tel:' . esc_attr( preg_replace('/[^0-9+]/', '', $tel) ) . '" class="button">📞 Telefon Et</a>';
        echo '</div>';
    }
}

/* ───────────────────────────────────────────────
   Satıcı Başvurusu - MAIL gönderme iyileştirmesi
   - Gönderici (From) sitenin kendi domain'i (spam filtre dostu)
   - Mail başarısızsa admin paneline NOT eklenir, kayıp olmaz
─────────────────────────────────────────────── */
// WP Mail SMTP eklentisi aktifse onun ayarlarını ezmeyiz - sadece eklenti YOKSA fallback uygulanır
add_filter( 'wp_mail_from', function( $email ) {
    // WP Mail SMTP varsa kendi ayarını yapsın
    if ( defined( 'WPMS_PLUGIN_VER' ) || class_exists( 'WPMailSMTP\Core' ) ) {
        return $email;
    }
    $host = wp_parse_url( home_url(), PHP_URL_HOST );
    if ( $host && strpos( $email, 'wordpress@' ) === 0 ) {
        return 'noreply@' . preg_replace( '/^www\./', '', $host );
    }
    return $email;
}, 5 );
add_filter( 'wp_mail_from_name', function( $name ) {
    // WP Mail SMTP varsa kendi ayarını yapsın
    if ( defined( 'WPMS_PLUGIN_VER' ) || class_exists( 'WPMailSMTP\Core' ) ) {
        return $name;
    }
    return '724PazarYeri';
}, 5 );

// Mail başarısızsa logla (admin paneline yazılır)
add_action( 'wp_mail_failed', function( $err ) {
    if ( ! is_wp_error( $err ) ) return;
    error_log( '[724PazarYeri Mail Hatası] ' . $err->get_error_message() );
    update_option( 'pz_last_mail_error', array(
        'time' => current_time( 'mysql' ),
        'msg'  => $err->get_error_message(),
    ) );
} );

// Admin dashboard'a uyarı: son mail hatası varsa
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $err = get_option( 'pz_last_mail_error' );
    if ( ! $err || empty( $err['msg'] ) ) return;
    // 24 saatten eski hataları gizle
    if ( strtotime( $err['time'] ) < ( time() - 86400 ) ) return;
    echo '<div class="notice notice-warning is-dismissible"><p><strong>⚠ E-posta gönderim hatası:</strong> ' . esc_html( $err['msg'] ) . ' <br><small>Tarih: ' . esc_html( $err['time'] ) . '. Bu uyarı 24 saat sonra otomatik kaybolur. Mail gönderimi için <a href="https://wordpress.org/plugins/wp-mail-smtp/" target="_blank">WP Mail SMTP</a> eklentisi kurmanız önerilir.</p></div>';
} );

/* ───────────────────────────────────────────────
   Satıcı Başvurusu - E-POSTA DOĞRULAMA KODU AJAX
─────────────────────────────────────────────── */
add_action( 'wp_ajax_pz_send_email_code',        'pz_ajax_send_email_code' );
add_action( 'wp_ajax_nopriv_pz_send_email_code', 'pz_ajax_send_email_code' );
function pz_ajax_send_email_code() {
    // Hataları yutmadan dön
    if ( ! headers_sent() ) {
        nocache_headers();
    }
    $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
    if ( ! is_email( $email ) ) {
        wp_send_json_error( array( 'message' => 'Geçerli bir e-posta adresi girin.' ) );
    }

    // Rate limit: 60 saniyede en fazla 1 kod isteği (Redis hatalarına dayanıklı)
    $rl_key = 'pz_email_rl_' . md5( $email );
    $rl_val = @get_transient( $rl_key );
    if ( $rl_val ) {
        wp_send_json_error( array( 'message' => 'Çok hızlı! Yeni kod istemeden önce 60 saniye bekleyin.' ) );
    }
    @set_transient( $rl_key, 1, 60 );

    // Kod üret (PHP 7+ random_int, eski sürümlerde mt_rand fallback)
    if ( function_exists( 'random_int' ) ) {
        $code = sprintf( '%06d', random_int( 100000, 999999 ) );
    } else {
        $code = sprintf( '%06d', mt_rand( 100000, 999999 ) );
    }
    @set_transient( 'pz_email_code_' . md5( $email ), $code, 15 * MINUTE_IN_SECONDS );

    // Mail gönder
    $subject = '724PazarYeri - E-posta Doğrulama Kodun';
    $body  = "Merhaba,\n\n";
    $body .= "724PazarYeri satıcı başvuru formundaki e-posta doğrulama kodun:\n\n";
    $body .= "    🔐  " . $code . "\n\n";
    $body .= "Bu kodu form sayfasındaki kutuya yazıp başvurunu tamamlayabilirsin.\n";
    $body .= "Kod 15 dakika geçerlidir.\n\n";
    $body .= "Eğer bu başvuruyu sen yapmadıysan bu maili dikkate alma.\n\n";
    $body .= "724PazarYeri Ekibi\n" . home_url('/');

    $sent = wp_mail( $email, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );

    if ( $sent ) {
        wp_send_json_success( array( 'message' => '✓ Doğrulama kodu e-postana gönderildi. Spam klasörünü de kontrol et.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'E-posta gönderilemedi. Sistem yöneticisine bildirin.' ) );
    }
}
