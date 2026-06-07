<?php
/**
 * 724PazarYeri Tema Fonksiyonları
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'PAZARYERI_VERSION', '9.9.186' );
define( 'PAZARYERI_DIR', get_template_directory() );
define( 'PAZARYERI_URL', get_template_directory_uri() );

// Vendor plugin: wp-content/plugins yüklemediyse tema kendi dizininden yükler.
// Böylece tema güncellemesi plugin güncellemesini de kapsar.
if ( ! class_exists( 'PZV_Dashboard' ) ) {
    require_once PAZARYERI_DIR . '/vendor-plugin/pazaryeri-vendor.php';
}

/**
 * Bugünden itibaren N. iş gününün Unix timestamp'ini döndürür.
 * İstanbul timezone (UTC+3) kullanır — sunucu/WP timezone'dan bağımsız.
 */
function pz_workday_ts( $n ) {
    static $hols = array('01-01','04-23','05-01','05-19','07-15','08-30','10-29');
    $tz    = new DateTimeZone( 'Europe/Istanbul' );
    $now   = new DateTime( 'now', $tz );
    $count = 0;
    while ( $count < $n ) {
        $now->modify( '+1 day' );
        if ( (int) $now->format('N') < 6 && ! in_array( $now->format('m-d'), $hols, true ) ) {
            $count++;
        }
    }
    return $now->getTimestamp();
}


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
    // Asıl tasarım CSS'i - async yükle (render blocking önleme)
    wp_enqueue_style( 'pazaryeri-main', PAZARYERI_URL . '/assets/css/main.min.css', array(), PAZARYERI_VERSION );
    add_filter( 'style_loader_tag', function( $html, $handle ) {
        if ( 'pazaryeri-main' === $handle ) {
            $html = str_replace(
                "rel='stylesheet'",
                "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"",
                $html
            );
            $html .= "<noscript><link rel='stylesheet' href='" . PAZARYERI_URL . "/assets/css/main.min.css?ver=" . PAZARYERI_VERSION . "'></noscript>";
        }
        return $html;
    }, 10, 2 );
    // Fontlar - OMGF ile yerel olarak barındırılıyor, dış Google Fonts kaldırıldı
    // Preconnect: Cloudflare ve CDN için
    add_action( 'wp_head', function(){
        echo '<link rel="preconnect" href="https://cdn.cloudflare.com" crossorigin>';
        // LCP görseli preload - ana sayfa hero/banner
        if ( is_front_page() || is_home() ) {
            echo '<link rel="preload" as="image" href="' . esc_url( get_template_directory_uri() ) . '/assets/img/hero-bg.webp" type="image/webp">';
        }
    }, 1 );

    // JS
    wp_enqueue_script( 'pazaryeri-main', PAZARYERI_URL . '/assets/js/main.min.js', array(), PAZARYERI_VERSION, true );
    wp_localize_script( 'pazaryeri-main', 'bazario_ajax', array(
        'url'      => admin_url( 'admin-ajax.php' ),
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'rest_url' => rest_url( 'pz/v1/search' ),
        'nonce'    => wp_create_nonce( 'pazaryeri_nonce' ),
        'home_url' => home_url( '/' ),
    ) );
    wp_add_inline_script( 'pazaryeri-main', 'window.pzHomeUrl = ' . wp_json_encode( home_url('/') ) . ';', 'before' );

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
    // Flash Satış sayfası
    if ( ! get_page_by_path( 'flash-sale' ) ) {
        wp_insert_post( array(
            'post_title'     => '⚡ Flash Satış',
            'post_name'      => 'flash-sale',
            'post_content'   => '',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'page_template'  => 'page-flash-sale.php',
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
    // Flash Satış sayfası yoksa oluştur
    if ( ! get_page_by_path( 'flash-sale' ) ) {
        wp_insert_post( array(
            'post_title'    => '⚡ Flash Satış',
            'post_name'     => 'flash-sale',
            'post_content'  => '',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'page_template' => 'page-flash-sale.php',
        ) );
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


/* Sepet sayfası altına son gezilen ürünler */
add_action( 'woocommerce_after_cart', function() {
    echo pz_recently_viewed_block( '🕐 Belki Bunları da İstersin', false );
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
    // login.min.css sadece login/hesap sayfalarında yüklensin
    if ( ! is_account_page() && ! is_checkout() ) {
        wp_dequeue_style( 'woocommerce-login' );
    }
    // frontend.css (WooCommerce blocks) sadece WooCommerce sayfalarında yüklensin
    if ( function_exists( 'is_woocommerce' ) ) {
        if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
            wp_dequeue_style( 'wc-blocks-style' );
            wp_dequeue_style( 'wc-blocks-vendors-style' );
        }
    }
    // Digits login CSS - sadece hesap/checkout sayfalarında yüklensin
    if ( ! is_account_page() && ! is_checkout() && ! is_page( 'giris' ) && ! is_page( 'kayit' ) ) {
        wp_dequeue_style( 'digits-login-style' );
    }
    // Customer Reviews CSS - sadece ürün sayfalarında yüklensin
    if ( ! is_singular( 'product' ) && ! is_checkout() ) {
        wp_dequeue_style( 'cr-frontend-css' );
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
            $pz_raw_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
            $pz_ip    = filter_var( $pz_raw_ip, FILTER_VALIDATE_IP ) ? $pz_raw_ip : '0.0.0.0';
            $pz_ua    = isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 100 ) : '';
            $pz_token = wp_hash( $pz_ip . '|' . $pz_ua . '|' . time() );
            set_transient( 'pz_apply_math_' . $pz_token, $pz_n1 + $pz_n2, 30 * MINUTE_IN_SECONDS );
            set_transient( 'pz_apply_start_' . $pz_token, time(), 30 * MINUTE_IN_SECONDS );
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


/* ──────────────────────────────────────────────
   Bildirim Aboneliği — Stok & Fiyat Alarmı
────────────────────────────────────────────── */
function pz_notify_subscribe_handler() {
    check_ajax_referer( 'pazaryeri_nonce', 'nonce' );
    $type  = sanitize_text_field( $_POST['type']  ?? '' );
    $pid   = absint( $_POST['pid']   ?? 0 );
    $email = sanitize_email( $_POST['email'] ?? '' );
    if ( ! in_array( $type, array( 'stock', 'price' ), true ) || ! $pid || ! is_email( $email ) ) {
        wp_send_json_error( array( 'msg' => 'Geçersiz istek.' ) );
    }
    $key  = 'pz_notify_' . $type . '_' . $pid;
    $list = get_option( $key, array() );
    if ( ! is_array( $list ) ) $list = array();
    if ( ! in_array( $email, $list, true ) ) {
        $list[] = $email;
        update_option( $key, $list, false );
    }
    $pname = get_the_title( $pid );
    $label = $type === 'stock' ? 'stoğa girdiğinde' : 'fiyatı düştüğünde';
    $subj  = '724PazarYeri — ' . ( $type === 'stock' ? 'Stok Alarmı' : 'Fiyat Alarmı' ) . ' Oluşturuldu';
    $body  = "Merhaba,\n\n\"$pname\" ürünü $label size e-posta göndereceğiz.\n\nÜrün: " . get_permalink( $pid ) . "\n\n724PazarYeri Ekibi\n" . home_url('/');
    wp_mail( $email, $subj, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
    wp_send_json_success( array( 'msg' => 'Kayıt başarılı.' ) );
}
add_action( 'wp_ajax_pz_notify_subscribe',        'pz_notify_subscribe_handler' );
add_action( 'wp_ajax_nopriv_pz_notify_subscribe', 'pz_notify_subscribe_handler' );


/* ──────────────────────────────────────────────
   Karşılaştırma: Ürün özelliklerini AJAX ile döndür
────────────────────────────────────────────── */
function pz_get_comp_attrs_handler() {
    $ids = json_decode( wp_unslash( $_POST['ids'] ?? '[]' ), true );
    if ( ! is_array( $ids ) ) $ids = array();
    $ids = array_map( 'absint', $ids );
    $ids = array_filter( $ids );
    $result = array();
    foreach ( $ids as $pid ) {
        $product = wc_get_product( $pid );
        if ( ! $product ) continue;
        $attrs = array();
        foreach ( $product->get_attributes() as $akey => $attr ) {
            if ( ! $attr->get_visible() ) continue;
            $alabel = pz_attr_label( $akey );
            $aval   = $attr->is_taxonomy()
                ? implode( ', ', wc_get_product_terms( $pid, $akey, array( 'fields' => 'names' ) ) )
                : implode( ', ', $attr->get_options() );
            if ( $aval ) $attrs[ $alabel ] = $aval;
        }
        $result[ $pid ] = $attrs;
    }
    wp_send_json_success( $result );
}
add_action( 'wp_ajax_pz_get_comp_attrs',        'pz_get_comp_attrs_handler' );
add_action( 'wp_ajax_nopriv_pz_get_comp_attrs', 'pz_get_comp_attrs_handler' );


/* ──────────────────────────────────────────────
   Canlı Arama — REST API + admin-ajax (çift yol)
   REST: /wp-json/pz/v1/search?q=...  (birincil)
   AJAX: admin-ajax.php action=pz_ai_search (yedek)
────────────────────────────────────────────── */
function pz_normalize_tr( $str ) {
    $str = mb_strtolower( $str, 'UTF-8' );
    return strtr( $str, array(
        'ı'=>'i','ö'=>'o','ü'=>'u','ç'=>'c','ş'=>'s','ğ'=>'g',
        'İ'=>'i','Ö'=>'o','Ü'=>'u','Ç'=>'c','Ş'=>'s','Ğ'=>'g',
    ) );
}

/* Ortak arama mantığı — REST ve AJAX her ikisi de bu fonksiyonu kullanır */
function pz_ai_search_execute( $q ) {
    $q = trim( sanitize_text_field( $q ) );
    if ( mb_strlen( $q ) < 2 ) {
        return array( 'products' => array(), 'categories' => array(), 'brands' => array(), 'vendors' => array() );
    }

    global $wpdb;
    $result = array( 'products' => array(), 'categories' => array(), 'brands' => array(), 'vendors' => array() );
    $like   = '%' . $wpdb->esc_like( $q ) . '%';

    /* ── 1. KATEGORİLER ── */
    $cats = get_terms( array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => true,
        'search'     => $q,
        'number'     => 5,
        'orderby'    => 'count',
        'order'      => 'DESC',
        'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
    ) );
    if ( ! is_wp_error( $cats ) ) {
        foreach ( $cats as $cat ) {
            $result['categories'][] = array( 'name' => $cat->name, 'url' => get_term_link( $cat ) );
        }
    }

    /* ── 2. MARKALAR ── */
    foreach ( array( 'product_brand', 'pa_marka', 'pa_brand' ) as $brand_tax ) {
        if ( ! taxonomy_exists( $brand_tax ) ) continue;
        $brands = get_terms( array( 'taxonomy' => $brand_tax, 'hide_empty' => true, 'search' => $q, 'number' => 3 ) );
        if ( ! is_wp_error( $brands ) && $brands ) {
            foreach ( $brands as $b ) {
                $result['brands'][] = array( 'name' => $b->name, 'url' => get_term_link( $b ) );
            }
        }
        break;
    }

    /* ── 3. BAŞLIK araması (fiyatı 0 olan ürünler hariç) ── */
    $title_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_price'
         WHERE p.post_type = 'product' AND p.post_status = 'publish' AND p.post_title LIKE %s
           AND CAST(pm.meta_value AS DECIMAL(10,2)) > 0
         ORDER BY CASE WHEN p.post_title LIKE %s THEN 0 ELSE 1 END, p.ID DESC
         LIMIT 6",
        $like, $wpdb->esc_like( $q ) . '%'
    ) );

    /* ── 4a. SKU araması — basit ürünler (tam eşleşme önce) ── */
    $sku_simple = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT p.ID
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
         WHERE p.post_type = 'product' AND p.post_status = 'publish'
           AND pm.meta_key = '_sku' AND pm.meta_value != '' AND pm.meta_value LIKE %s
         ORDER BY CASE WHEN pm.meta_value = %s THEN 0 ELSE 1 END, p.ID DESC
         LIMIT 6",
        $like, $q
    ) );

    /* ── 4b. SKU araması — varyasyonlar (ana ürün ID'si döner) ── */
    $sku_var_parents = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT p.post_parent
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
         WHERE p.post_type = 'product_variation' AND p.post_status = 'publish'
           AND pm.meta_key = '_sku' AND pm.meta_value != '' AND pm.meta_value LIKE %s
           AND p.post_parent > 0
         LIMIT 6",
        $like
    ) );
    $sku_variation = array();
    foreach ( (array) $sku_var_parents as $pid ) {
        if ( get_post_status( (int) $pid ) === 'publish' ) {
            $sku_variation[] = (int) $pid;
        }
    }

    /* ── 5. Türkçe normalize araması ── */
    $norm_ids = array();
    $q_norm   = pz_normalize_tr( $q );
    if ( $q_norm !== mb_strtolower( $q, 'UTF-8' ) ) {
        $rows = $wpdb->get_results(
            "SELECT ID, post_title FROM {$wpdb->posts}
             WHERE post_type = 'product' AND post_status = 'publish' LIMIT 2000"
        );
        foreach ( (array) $rows as $row ) {
            if ( strpos( pz_normalize_tr( $row->post_title ), $q_norm ) !== false ) {
                $norm_ids[] = (int) $row->ID;
            }
        }
    }

    /* ── Birleştir ── */
    $all_ids = array_unique( array_merge(
        (array) $title_ids,
        (array) $sku_simple,
        $sku_variation,
        $norm_ids
    ) );
    $all_ids = array_slice( array_filter( array_map( 'intval', $all_ids ) ), 0, 8 );

    foreach ( $all_ids as $pid ) {
        $product = wc_get_product( $pid );
        if ( ! $product || $product->get_status() !== 'publish' ) continue;
        // Fiyatı 0 veya girilmemiş ürünleri arama sonuçlarında gösterme
        if ( (float) $product->get_price() <= 0 ) continue;
        $img = get_the_post_thumbnail_url( $pid, 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src();
        $result['products'][] = array(
            'title' => $product->get_name(),
            'url'   => get_permalink( $pid ),
            'img'   => $img,
            'price' => wp_strip_all_tags( $product->get_price_html() ),
            'sku'   => $product->get_sku(),
        );
    }

    /* ── 6. MAĞAZA (satıcı) araması ── */
    if ( class_exists( 'PZV_Vendor' ) && defined( 'PZV_ROLE' ) ) {
        // pzv_store_name meta'sında ara
        $vendor_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT u.ID, um_name.meta_value AS store_name, um_slug.meta_value AS store_slug
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->usermeta} um_role ON um_role.user_id = u.ID
                 AND um_role.meta_key = '{$wpdb->prefix}capabilities'
                 AND um_role.meta_value LIKE %s
             INNER JOIN {$wpdb->usermeta} um_name ON um_name.user_id = u.ID
                 AND um_name.meta_key = 'pzv_store_name'
                 AND um_name.meta_value LIKE %s
             LEFT JOIN {$wpdb->usermeta} um_slug ON um_slug.user_id = u.ID
                 AND um_slug.meta_key = 'pzv_store_slug'
             LEFT JOIN {$wpdb->usermeta} um_status ON um_status.user_id = u.ID
                 AND um_status.meta_key = 'pzv_status'
             WHERE ( um_status.meta_value IS NULL OR um_status.meta_value != 'inactive' )
             ORDER BY CASE WHEN um_name.meta_value = %s THEN 0 ELSE 1 END, u.ID DESC
             LIMIT 4",
            '%' . PZV_ROLE . '%',
            $like,
            $q
        ) );

        foreach ( (array) $vendor_rows as $row ) {
            $vid        = (int) $row->ID;
            $store_name = $row->store_name;
            $store_slug = $row->store_slug ?: sanitize_title( get_userdata( $vid )->user_login );
            $store_url  = home_url( '/magaza/' . $store_slug . '/' );
            $logo_id    = (int) get_user_meta( $vid, 'pzv_logo', true );
            $logo_url   = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
            $city       = get_user_meta( $vid, 'pzv_city', true );
            $prod_count = PZV_Vendor::product_count( $vid, 'publish' );

            $result['vendors'][] = array(
                'name'      => $store_name,
                'url'       => $store_url,
                'logo'      => $logo_url,
                'city'      => $city,
                'products'  => (int) $prod_count,
            );
        }

        // display_name ile de ara (mağaza adı girilmemişse)
        if ( empty( $result['vendors'] ) ) {
            $fallback_users = get_users( array(
                'role'       => PZV_ROLE,
                'search'     => '*' . $q . '*',
                'search_columns' => array( 'display_name', 'user_login' ),
                'number'     => 4,
                'meta_query' => array(
                    'relation' => 'OR',
                    array( 'key' => 'pzv_status', 'compare' => 'NOT EXISTS' ),
                    array( 'key' => 'pzv_status', 'value' => 'inactive', 'compare' => '!=' ),
                ),
            ) );
            foreach ( $fallback_users as $fu ) {
                $store_name = get_user_meta( $fu->ID, 'pzv_store_name', true ) ?: $fu->display_name;
                $store_slug = get_user_meta( $fu->ID, 'pzv_store_slug', true ) ?: sanitize_title( $fu->user_login );
                $store_url  = home_url( '/magaza/' . $store_slug . '/' );
                $logo_id    = (int) get_user_meta( $fu->ID, 'pzv_logo', true );
                $logo_url   = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
                $city       = get_user_meta( $fu->ID, 'pzv_city', true );
                $prod_count = PZV_Vendor::product_count( $fu->ID, 'publish' );
                $result['vendors'][] = array(
                    'name'     => $store_name,
                    'url'      => $store_url,
                    'logo'     => $logo_url,
                    'city'     => $city,
                    'products' => (int) $prod_count,
                );
            }
        }
    }

    return $result;
}

/* ── REST API endpoint: GET /wp-json/pz/v1/search?q=... ── */
add_action( 'rest_api_init', function () {
    register_rest_route( 'pz/v1', '/search', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => function ( WP_REST_Request $req ) {
            $q      = sanitize_text_field( $req->get_param( 'q' ) ?? '' );
            $result = pz_ai_search_execute( $q );
            return new WP_REST_Response( array( 'success' => true, 'data' => $result ), 200 );
        },
        'permission_callback' => '__return_true',
        'args'                => array(
            'q' => array( 'type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ),
        ),
    ) );
} );

/* ── admin-ajax yedek: POST admin-ajax.php action=pz_ai_search ── */
function pz_ai_search_handler() {
    $q      = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
    $result = pz_ai_search_execute( $q );
    wp_send_json_success( $result );
}
add_action( 'wp_ajax_pz_ai_search',        'pz_ai_search_handler' );
add_action( 'wp_ajax_nopriv_pz_ai_search', 'pz_ai_search_handler' );


/* ══════════════════════════════════════════════════════════════
   ON-PAGE SEO — Yoast title / metadesc / OG şablonları
   v9.9.173
   ══════════════════════════════════════════════════════════════ */

// Yardımcı: title'ı 60 karaktere sığdır, kelime ortasında kesme
if ( ! function_exists( 'pz_trim_title' ) ) {
    function pz_trim_title( $str, $max = 60 ) {
        $str = html_entity_decode( $str, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $str = wp_strip_all_tags( $str );
        if ( mb_strlen( $str ) <= $max ) return $str;
        $cut = mb_substr( $str, 0, $max );
        $last_space = mb_strrpos( $cut, ' ' );
        return $last_space ? mb_substr( $cut, 0, $last_space ) . '…' : $cut . '…';
    }
}

// Yardımcı: fiyatı temiz sayısal string'e çevir (wc_price HTML'ini temizler)
if ( ! function_exists( 'pz_clean_price' ) ) {
    function pz_clean_price( $price_raw ) {
        $clean = wp_strip_all_tags( html_entity_decode( $price_raw ) );
        $clean = preg_replace( '/\s+/', ' ', trim( $clean ) );
        return $clean;
    }
}

/* ── 1. ÜRÜN sayfası title şablonu ──────────────────────────── */
add_filter( 'wpseo_title', function ( $title ) {
    if ( ! is_singular( 'product' ) ) return $title;
    global $post;
    $product = wc_get_product( $post->ID );
    if ( ! $product ) return $title;

    $name = html_entity_decode( $product->get_name(), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $site = get_bloginfo( 'name' );

    $brand_terms = wp_get_post_terms( $post->ID, 'product_brand' );
    if ( ! empty( $brand_terms ) && ! is_wp_error( $brand_terms ) ) {
        $brand_name = html_entity_decode( $brand_terms[0]->name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        if ( mb_stripos( $name, $brand_name ) === 0 ) {
            $raw = $name . ' | ' . $site;
        } else {
            $raw = $brand_name . ' ' . $name . ' | ' . $site;
        }
    } else {
        $cats = wc_get_product_terms( $post->ID, 'product_cat', array( 'fields' => 'names', 'number' => 1 ) );
        if ( ! empty( $cats ) ) {
            $raw = $name . ' - ' . html_entity_decode( $cats[0], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) . ' | ' . $site;
        } else {
            $raw = $name . ' Satın Al | ' . $site;
        }
    }

    return pz_trim_title( $raw, 60 );
}, 20 );

/* ── 2. ÜRÜN sayfası metadesc şablonu ───────────────────────── */
add_filter( 'wpseo_metadesc', function ( $desc ) {
    if ( ! is_singular( 'product' ) ) return $desc;
    global $post;
    // Yoast'ta elle girilmişse dokunma
    $custom = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
    if ( $custom ) return $desc;

    $product = wc_get_product( $post->ID );
    if ( ! $product ) return $desc;

    $name   = $product->get_name();
    $price  = pz_clean_price( wc_price( $product->get_price() ) );
    $site   = get_bloginfo( 'name' );
    $stock  = $product->is_in_stock() ? 'Stokta mevcut.' : 'Stok sınırlı.';

    // ikinci el mi?
    $condition = get_post_meta( $post->ID, 'pz_condition', true );
    $cond_str  = ( $condition === 'used' ) ? 'İkinci el ' : '';

    // Kısa açıklama varsa kullan
    $short = wp_strip_all_tags( $product->get_short_description() );
    if ( $short ) {
        $base = $cond_str . mb_substr( $short, 0, 100 );
    } else {
        $cats = wc_get_product_terms( $post->ID, 'product_cat', array( 'fields' => 'names', 'number' => 1 ) );
        $cat  = ! empty( $cats ) ? $cats[0] : '';
        $base = $cond_str . $name . ( $cat ? ' · ' . $cat : '' );
    }

    return mb_substr( $base . ' - ' . $price . '. ' . $stock, 0, 155 );
}, 20 );

/* ── 3. KATEGORİ sayfası title şablonu ──────────────────────── */
add_filter( 'wpseo_title', function ( $title ) {
    if ( ! is_product_category() ) return $title;
    $term = get_queried_object();
    if ( ! $term ) return $title;
    $site  = get_bloginfo( 'name' );
    $paged = max( 1, (int) get_query_var( 'paged' ) );

    $parent_part = '';
    if ( $term->parent ) {
        $parent = get_term( $term->parent, 'product_cat' );
        if ( $parent && ! is_wp_error( $parent ) ) {
            $parent_part = ' · ' . $parent->name;
        }
    }

    $raw = $term->name . $parent_part . ' Ürünleri | ' . $site;
    if ( $paged > 1 ) $raw .= ' (Sayfa ' . $paged . ')';

    return pz_trim_title( $raw, 60 );
}, 20 );

/* ── 4. KATEGORİ sayfası metadesc şablonu ───────────────────── */
add_filter( 'wpseo_metadesc', function ( $desc ) {
    if ( ! is_product_category() ) return $desc;
    $term = get_queried_object();
    if ( ! $term ) return $desc;
    $custom = get_term_meta( $term->term_id, '_yoast_wpseo_metadesc', true );
    if ( $custom ) return $desc;
    $site  = get_bloginfo( 'name' );
    $count = (int) $term->count;
    $base  = $term->description
        ? mb_substr( wp_strip_all_tags( $term->description ), 0, 110 )
        : $term->name . ' kategorisindeki tüm ürünleri keşfedin';
    return mb_substr( $base . '. ' . $count . ' urun listeleniyor.', 0, 155 );
}, 20 );

/* ── 5. MARKA sayfası (product_brand) title + metadesc ──────── */
add_filter( 'wpseo_title', function ( $title ) {
    if ( ! is_tax( 'product_brand' ) ) return $title;
    $term = get_queried_object();
    if ( ! $term ) return $title;
    $site = get_bloginfo( 'name' );
    $raw  = $term->name . ' Ürünleri | ' . $site;
    return pz_trim_title( $raw, 60 );
}, 20 );

add_filter( 'wpseo_metadesc', function ( $desc ) {
    if ( ! is_tax( 'product_brand' ) ) return $desc;
    $term = get_queried_object();
    if ( ! $term ) return $desc;
    $custom = get_term_meta( $term->term_id, '_yoast_wpseo_metadesc', true );
    if ( $custom ) return $desc;
    $site  = get_bloginfo( 'name' );
    $count = (int) $term->count;
    $base  = $term->description
        ? mb_substr( wp_strip_all_tags( $term->description ), 0, 110 )
        : $term->name . ' markalı orijinal ürünler';
    return mb_substr( $base . '. ' . $count . ' urun listeleniyor.', 0, 155 );
}, 20 );

/* ── 5. ÜRÜN Open Graph görseli — öne çıkan görseli zorla ───── */
add_filter( 'wpseo_opengraph_image', function ( $img ) {
    if ( ! is_singular( 'product' ) ) return $img;
    global $post;
    $product = wc_get_product( $post->ID );
    if ( ! $product ) return $img;
    $img_id = $product->get_image_id();
    if ( ! $img_id ) return $img;
    $url = wp_get_attachment_image_url( $img_id, 'large' );
    return $url ?: $img;
}, 20 );

/* ── 6. Twitter Card görseli ─────────────────────────────────── */
add_filter( 'wpseo_twitter_image', function ( $img ) {
    if ( ! is_singular( 'product' ) ) return $img;
    global $post;
    $product = wc_get_product( $post->ID );
    if ( ! $product ) return $img;
    $img_id = $product->get_image_id();
    if ( ! $img_id ) return $img;
    $url = wp_get_attachment_image_url( $img_id, 'large' );
    return $url ?: $img;
}, 20 );

/* ── 7. OG: site_name, price, availability ───────────────────── */
add_action( 'wpseo_add_opengraph_additional_images', function ( $ogimage ) {
    // Galeri görsellerini OG'ye ekle (ilk 3)
    if ( ! is_singular( 'product' ) ) return;
    global $post;
    $product = wc_get_product( $post->ID );
    if ( ! $product ) return;
    $gallery = array_slice( $product->get_gallery_image_ids(), 0, 3 );
    foreach ( $gallery as $gid ) {
        $url = wp_get_attachment_image_url( $gid, 'large' );
        if ( $url ) $ogimage->add_image_by_url( $url );
    }
} );

add_action( 'wp_head', function () {
    if ( ! is_singular( 'product' ) ) return;
    global $post;
    $product = wc_get_product( $post->ID );
    if ( ! $product ) return;
    $price    = $product->get_price();
    $currency = get_woocommerce_currency();
    $avail    = $product->is_in_stock() ? 'instock' : 'oos';
    // Facebook/Instagram ürün meta etiketleri
    echo '<meta property="product:price:amount" content="' . esc_attr( $price ) . '">' . "\n";
    echo '<meta property="product:price:currency" content="' . esc_attr( $currency ) . '">' . "\n";
    echo '<meta property="product:availability" content="' . esc_attr( $avail ) . '">' . "\n";
    if ( $product->get_sku() ) {
        echo '<meta property="product:retailer_item_id" content="' . esc_attr( $product->get_sku() ) . '">' . "\n";
    }
    // Marka
    $brand_terms = wp_get_post_terms( $post->ID, 'product_brand' );
    if ( ! empty( $brand_terms ) && ! is_wp_error( $brand_terms ) ) {
        echo '<meta property="product:brand" content="' . esc_attr( $brand_terms[0]->name ) . '">' . "\n";
    }
}, 10 );

/* ══════════════════════════════════════════════════════════════
   CANONICAL URL & DUPLICATE CONTENT KORUMALARI
   ══════════════════════════════════════════════════════════════ */

/* ── 8. Canonical: tüm kirli parametreleri temizle ───────────── */
// Temizlenen parametreler:
// ?tab=, ?ppage=                   → ürün sayfası UI parametreleri
// ?min_price=, ?max_price=         → WooCommerce fiyat filtresi
// ?orderby=                        → sıralama
// ?filter_*                        → WooCommerce attribute filtresi
// ?attribute_pa_*                  → varyasyon seçim parametreleri
// ?paged=                          → sayfalama (sadece canonical; prev/next ayrı)
add_filter( 'wpseo_canonical', function ( $canonical ) {

    // ── Ürün sayfası: tüm query parametrelerini at, salt permalink döndür
    if ( is_singular( 'product' ) ) {
        global $post;
        return get_permalink( $post->ID );
    }

    // ── Arşiv sayfaları (kategori, mağaza, marka, product_tag)
    if ( is_product_category() || is_shop() || is_product_tag() || is_tax( 'product_brand' ) ) {
        $paged = max( 1, (int) get_query_var( 'paged' ) );
        $term  = get_queried_object();

        if ( is_shop() ) {
            $base = wc_get_page_permalink( 'shop' );
        } elseif ( $term && isset( $term->term_id ) ) {
            $link = get_term_link( $term );
            $base = is_wp_error( $link ) ? $canonical : $link;
        } else {
            return $canonical;
        }

        // Sayfalama varsa /page/N/ formatında canonical — query string değil
        if ( $paged > 1 ) {
            return trailingslashit( $base ) . 'page/' . $paged . '/';
        }
        return trailingslashit( $base );
    }

    return $canonical;
}, 25 );

/* ── 9. Sayfalama: rel prev/next ─────────────────────────────── */
add_action( 'wp_head', function () {
    if ( ! ( is_product_category() || is_shop() || is_product_tag() || is_tax( 'product_brand' ) ) ) return;
    global $wp_query;
    $paged = max( 1, (int) get_query_var( 'paged' ) );
    $max   = (int) $wp_query->max_num_pages;
    $term  = get_queried_object();

    if ( is_shop() ) {
        $base = trailingslashit( wc_get_page_permalink( 'shop' ) );
    } elseif ( $term && isset( $term->term_id ) && ! is_wp_error( get_term_link( $term ) ) ) {
        $base = trailingslashit( get_term_link( $term ) );
    } else {
        return;
    }

    if ( $paged > 1 ) {
        $prev = $paged === 2 ? $base : $base . 'page/' . ( $paged - 1 ) . '/';
        echo '<link rel="prev" href="' . esc_url( $prev ) . '">' . "\n";
    }
    if ( $paged < $max ) {
        echo '<link rel="next" href="' . esc_url( $base . 'page/' . ( $paged + 1 ) . '/' ) . '">' . "\n";
    }
}, 5 );

/* ── 10. Ürün slug otomatik temizleme ────────────────────────── */
add_filter( 'wp_unique_post_slug', function ( $slug, $post_id, $post_status, $post_type ) {
    if ( $post_type !== 'product' ) return $slug;
    $stop  = array( '-ve-', '-ile-', '-bir-', '-bu-', '-da-', '-de-', '-den-', '-dan-', '-icin-', '-için-', '-mi-', '-mu-', '-mü-' );
    $clean = str_replace( $stop, '-', $slug );
    $clean = preg_replace( '/-{2,}/', '-', $clean );
    return trim( $clean, '-' );
}, 10, 4 );

/* ── 11. noindex kuralları ────────────────────────────────────── */
// Noindex yapılan sayfalar:
// a) Taslak/pending/private ürünler
// b) ?orderby=, ?min_price=, ?max_price=, ?filter_* içeren filtre URL'leri
// c) product_tag arşiv sayfaları (binlerce unindexed sayfa kaynağı)
add_filter( 'wpseo_robots', function ( $robots ) {

    // a) Taslak/pending ürünler
    if ( is_singular( 'product' ) ) {
        global $post;
        $product = wc_get_product( $post->ID );
        if ( $product && in_array( $product->get_status(), array( 'draft', 'pending', 'private' ), true ) ) {
            return 'noindex,nofollow';
        }
        return $robots;
    }

    // b) Filtre/sıralama parametreli arşiv URL'leri
    if ( is_product_category() || is_shop() || is_tax( 'product_brand' ) ) {
        $dirty_params = array( 'orderby', 'min_price', 'max_price', 'rating_filter', 'on_sale' );
        foreach ( $dirty_params as $param ) {
            if ( isset( $_GET[ $param ] ) ) {
                return 'noindex,follow';
            }
        }
        // ?filter_* (WooCommerce attribute filtresi)
        foreach ( array_keys( $_GET ) as $key ) {
            if ( strpos( $key, 'filter_' ) === 0 || strpos( $key, 'query_type_' ) === 0 ) {
                return 'noindex,follow';
            }
        }
        return $robots;
    }

    // c) product_tag sayfaları — tümünü noindex yap
    if ( is_product_tag() ) {
        return 'noindex,follow';
    }

    return $robots;
}, 10 );

/* ── 12. WooCommerce: ürün arşiv sayfası <title> tag ────────── */
add_filter( 'woocommerce_page_title', function ( $title ) {
    if ( is_shop() ) {
        return get_bloginfo( 'name' ) . ' — Tüm Ürünler';
    }
    return $title;
} );


/* ══════════════════════════════════════════════════════════════
   ÜRÜN SAYFASI — Schema.org JSON-LD
   • Yoast'ın Product schema'sı devre dışı — çakışmayı önler
   • itemCondition, priceValidUntil, iade politikası, kargo eklendi
   • Variable ürünlerde offers array olarak üretiliyor
   ══════════════════════════════════════════════════════════════ */

// 1) Yoast'ın kendi Product + Offer schema'sını kapat, BreadcrumbList'e dokunma
add_filter( 'wpseo_schema_graph_pieces', function ( $pieces, $context ) {
    foreach ( $pieces as $key => $piece ) {
        $class = get_class( $piece );
        // Yalnızca Product ve Offer piece'lerini çıkar
        if (
            ( substr( $class, -8 ) === '\Product' ) ||
            ( substr( $class, -6 ) === '\Offer' )
        ) {
            unset( $pieces[ $key ] );
        }
    }
    return $pieces;
}, 11, 2 );

// 2) Tema'nın kapsamlı Product schema'sı
add_action( 'wp_head', function () {
    if ( ! is_singular( 'product' ) ) return;
    global $post;
    $product = wc_get_product( $post->ID );
    if ( ! $product ) return;

    // --- Satıcı ---
    $author_id   = (int) $post->post_author;
    $vendor      = class_exists( 'PZV_Vendor' ) ? PZV_Vendor::get( $author_id ) : null;
    $seller_name = $vendor ? $vendor['store_name'] : get_bloginfo( 'name' );
    $seller_url  = $vendor ? PZV_Vendor::store_url( $author_id ) : home_url( '/' );

    // --- Görseller ---
    $img_id   = $product->get_image_id();
    $img_url  = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : wc_placeholder_img_src();
    $img_list = array( $img_url );
    foreach ( $product->get_gallery_image_ids() as $gid ) {
        $gu = wp_get_attachment_image_url( $gid, 'large' );
        if ( $gu ) $img_list[] = $gu;
    }

    // --- Temel değişkenler ---
    $in_stock    = $product->is_in_stock();
    $sku         = $product->get_sku();
    $permalink   = get_permalink( $post->ID );
    $currency    = get_woocommerce_currency();
    $avail       = $in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';

    // itemCondition: ürün meta'sında 'pz_condition' = 'used' ise ikinci el
    $condition_meta = get_post_meta( $post->ID, 'pz_condition', true );
    $condition = ( $condition_meta === 'used' )
        ? 'https://schema.org/UsedCondition'
        : 'https://schema.org/NewCondition';

    // priceValidUntil: bugünden 30 gün
    $price_valid_until = gmdate( 'Y-m-d', strtotime( '+30 days' ) );

    // İade politikası (tüm ürünler için sabit 14 gün)
    $return_policy = array(
        '@type'                    => 'MerchantReturnPolicy',
        'applicableCountry'        => 'TR',
        'returnPolicyCategory'     => 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'merchantReturnDays'       => 14,
        'returnMethod'             => 'https://schema.org/ReturnByMail',
        'returnFees'               => 'https://schema.org/FreeReturn',
    );

    // Kargo detayları
    $shipping_details = array(
        '@type'               => 'OfferShippingDetails',
        'shippingRate'        => array(
            '@type'    => 'MonetaryAmount',
            'value'    => 0,
            'currency' => $currency,
        ),
        'shippingDestination' => array(
            '@type'           => 'DefinedRegion',
            'addressCountry'  => 'TR',
        ),
        'deliveryTime'        => array(
            '@type'        => 'ShippingDeliveryTime',
            'handlingTime' => array(
                '@type'    => 'QuantitativeValue',
                'minValue' => 0,
                'maxValue' => 1,
                'unitCode' => 'DAY',
            ),
            'transitTime'  => array(
                '@type'    => 'QuantitativeValue',
                'minValue' => 1,
                'maxValue' => 3,
                'unitCode' => 'DAY',
            ),
        ),
    );

    // --- Offer üretici yardımcı fonksiyon ---
    $pz_make_offer = function( $offer_url, $price, $in_stk ) use (
        $currency, $avail, $seller_name, $seller_url,
        $condition, $price_valid_until, $return_policy, $shipping_details
    ) {
        return array(
            '@type'                     => 'Offer',
            'url'                       => $offer_url,
            'priceCurrency'             => $currency,
            'price'                     => (string) $price,
            'priceValidUntil'           => $price_valid_until,
            'availability'              => $in_stk ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition'             => $condition,
            'seller'                    => array( '@type' => 'Organization', 'name' => $seller_name, 'url' => $seller_url ),
            'hasMerchantReturnPolicy'   => $return_policy,
            'shippingDetails'           => $shipping_details,
        );
    };

    // --- Variable vs Simple offers ---
    if ( $product->is_type( 'variable' ) ) {
        $offers = array();
        foreach ( $product->get_available_variations() as $vd ) {
            if ( empty( $vd['variation_id'] ) ) continue;
            $var       = wc_get_product( $vd['variation_id'] );
            if ( ! $var ) continue;
            $var_price = $var->get_price();
            if ( $var_price === '' || $var_price === null ) continue;
            $var_url   = add_query_arg(
                array_map( 'urlencode', $vd['attributes'] ),
                $permalink
            );
            $offers[]  = $pz_make_offer( $var_url, $var_price, $var->is_in_stock() );
        }
        // Varyasyon listesi boşsa tek offer dön
        if ( empty( $offers ) ) {
            $offers = $pz_make_offer( $permalink, $product->get_price(), $in_stock );
        }
    } else {
        $offers = $pz_make_offer( $permalink, $product->get_price(), $in_stock );
    }

    // --- Ana schema ---
    $schema = array(
        '@context'    => 'https://schema.org/',
        '@type'       => 'Product',
        'name'        => $product->get_name(),
        'image'       => $img_list,
        'description' => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
        'url'         => $permalink,
        'offers'      => $offers,
    );

    if ( $sku )                   $schema['sku']    = $sku;
    if ( $product->get_weight() ) $schema['weight'] = $product->get_weight() . ' ' . get_option( 'woocommerce_weight_unit' );

    $brand_terms = wp_get_post_terms( $post->ID, 'product_brand' );
    if ( ! empty( $brand_terms ) && ! is_wp_error( $brand_terms ) ) {
        $schema['brand'] = array( '@type' => 'Brand', 'name' => $brand_terms[0]->name );
    }

    $avg = $product->get_average_rating();
    $cnt = $product->get_review_count();
    if ( $avg > 0 && $cnt > 0 ) {
        $schema['aggregateRating'] = array(
            '@type'       => 'AggregateRating',
            'ratingValue' => round( (float) $avg, 1 ),
            'reviewCount' => (int) $cnt,
            'bestRating'  => 5,
            'worstRating' => 1,
        );
    }

    $original_id = (int) get_post_meta( $post->ID, '_pzv_cloned_from', true );
    if ( $original_id && get_post_status( $original_id ) === 'publish' ) {
        $schema['isVariantOf'] = array( '@type' => 'Product', 'url' => get_permalink( $original_id ) );
    }

    echo '<script type="application/ld+json">' . "\n" . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n</script>\n";
}, 5 );

// Ürün breadcrumb schema
add_action( 'wp_head', function () {
    if ( ! is_singular( 'product' ) ) return;
    global $post;
    $product = wc_get_product( $post->ID );
    if ( ! $product ) return;
    $items = array();
    $pos   = 1;
    $items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => 'Ana Sayfa', 'item' => home_url( '/' ) );
    $terms = wc_get_product_terms( $product->get_id(), 'product_cat', array( 'orderby' => 'parent', 'order' => 'ASC' ) );
    if ( ! empty( $terms ) ) {
        $main_term = end( $terms );
        foreach ( array_reverse( get_ancestors( $main_term->term_id, 'product_cat' ) ) as $anc_id ) {
            $anc = get_term( $anc_id, 'product_cat' );
            if ( $anc && ! is_wp_error( $anc ) ) {
                $items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => $anc->name, 'item' => get_term_link( $anc ) );
            }
        }
        $items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => $main_term->name, 'item' => get_term_link( $main_term ) );
    }
    $items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => $product->get_name(), 'item' => get_permalink( $post->ID ) );
    $schema  = array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items );
    echo '<script type="application/ld+json">' . "\n" . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n</script>\n";
}, 6 );

// Yoast odak anahtar kelimesi otomasyonu
add_action( 'save_post_product', function ( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) ) return;
    $product = wc_get_product( $post_id );
    if ( ! $product ) return;
    $cats     = wc_get_product_terms( $post_id, 'product_cat', array( 'fields' => 'names', 'number' => 1 ) );
    $focus_kw = $product->get_name() . ( ! empty( $cats ) ? ' ' . $cats[0] : '' );
    update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( mb_substr( $focus_kw, 0, 60 ) ) );
}, 20 );

/* ══════════════════════════════════════════════════════════════
   KATEGORİ SAYFASI — Schema.org (functions.php'ye taşındı)
   ══════════════════════════════════════════════════════════════ */
add_action( 'wp_head', function () {
    if ( ! ( is_shop() || is_product_category() || is_product_tag() || is_tax( 'product_brand' ) ) ) return;
    $term  = get_queried_object();
    $items = array();
    $pos   = 1;
    $items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => 'Ana Sayfa', 'item' => home_url( '/' ) );
    if ( $term && isset( $term->term_id ) ) {
        foreach ( array_reverse( get_ancestors( $term->term_id, $term->taxonomy ) ) as $anc_id ) {
            $anc      = get_term( $anc_id, $term->taxonomy );
            $anc_link = $anc && ! is_wp_error( $anc ) ? get_term_link( $anc ) : false;
            if ( $anc_link && ! is_wp_error( $anc_link ) ) {
                $items[] = array( '@type' => 'ListItem', 'position' => $pos++, 'name' => $anc->name, 'item' => $anc_link );
            }
        }
        $term_link = get_term_link( $term );
        if ( ! is_wp_error( $term_link ) ) {
            $items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => $term->name, 'item' => $term_link );
        }
    } else {
        $items[] = array( '@type' => 'ListItem', 'position' => $pos, 'name' => 'Tüm Ürünler', 'item' => wc_get_page_permalink( 'shop' ) );
    }
    $schema = array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items );
    echo '<script type="application/ld+json">' . "\n" . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n</script>\n";
    if ( $term && isset( $term->term_id ) ) {
        $term_link = get_term_link( $term );
        if ( ! is_wp_error( $term_link ) ) {
            $col = array(
                '@context'    => 'https://schema.org',
                '@type'       => 'CollectionPage',
                'name'        => $term->name,
                'description' => $term->description ?: ( $term->name . ' kategorisindeki ürünler' ),
                'url'         => $term_link,
            );
            echo '<script type="application/ld+json">' . "\n" . wp_json_encode( $col, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n</script>\n";
        }
    }
}, 5 );

/* ═══════════════════════════════════════════════════════════
   NAKİT ÖDEME İSKONTO SİSTEMİ — %5
   • Sepet: ürün başına badge + toplam satırı
   • Ödeme sayfası: nakit seçilince fee olarak otomatik düşüm
═══════════════════════════════════════════════════════════ */

if ( ! defined( 'PZ_NAKIT_RATE' ) ) define( 'PZ_NAKIT_RATE', 0.05 ); // Varsayılan %5

/** Bir ürünün vendor'unun nakit iskonto oranını döndürür (0–1 arası) */
function pz_get_vendor_nakit_rate( $product_id = 0 ) {
    $vendor_id = 0;
    if ( $product_id ) {
        $vendor_id = (int) get_post_meta( $product_id, '_pzv_vendor_id', true );
        if ( ! $vendor_id ) {
            $vendor_id = (int) get_post_field( 'post_author', $product_id );
        }
    }
    if ( $vendor_id ) {
        $rate_meta = get_user_meta( $vendor_id, 'pzv_nakit_rate', true );
        if ( $rate_meta !== '' && $rate_meta !== false ) {
            return max( 0, min( 50, (float) $rate_meta ) ) / 100;
        }
    }
    return PZ_NAKIT_RATE; // Varsayılan %5
}

function pz_nakit_payment_ids() {
    return apply_filters( 'pz_nakit_payment_ids', array(
        'bacs', 'cod', 'nakit', 'nakit_odeme', 'nakit_payment', 'cash', 'kapida', 'kapida_odeme',
    ) );
}

function pz_is_nakit_selected() {
    if ( ! function_exists( 'WC' ) ) return false;
    // $_POST öncelikli — checkout submit sırasında session henüz güncellenmemiş olabilir
    if ( isset( $_POST['payment_method'] ) ) {
        $chosen = strtolower( sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) );
    } elseif ( WC()->session ) {
        $chosen = strtolower( (string) WC()->session->get( 'chosen_payment_method', '' ) );
    } else {
        return false;
    }
    foreach ( pz_nakit_payment_ids() as $id ) {
        if ( $chosen === strtolower( $id ) || strpos( $chosen, strtolower( $id ) ) !== false ) return true;
    }
    return false;
}

function pz_item_nakit_discount( $cart_item ) {
    if ( empty( $cart_item['data'] ) ) return 0;
    $product_id = $cart_item['data']->get_id();
    $rate       = pz_get_vendor_nakit_rate( $product_id );
    if ( $rate <= 0 ) return 0;
    // Müşterinin gerçekte ödeyeceği vergi dahil fiyat üzerinden hesapla
    return (float) $cart_item['data']->get_price() * (int) $cart_item['quantity'] * $rate;
}

function pz_cart_total_nakit() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) return 0;
    $total = 0;
    foreach ( WC()->cart->get_cart() as $item ) {
        $total += pz_item_nakit_discount( $item );
    }
    // Tek seferlik yuvarlama — her yerde aynı tutarı verir
    return round( $total, 2 );
}

/* ── NAKİT İSKONTO — SANAL KUPON SİSTEMİ ─────────────────────────────
   Fee sistemi WooCommerce bug nedeniyle negatif fee'lerde vergi hesaplıyor.
   Coupon sistemi vergi hesabının tamamen dışında çalışır — vergi sorunu yok.
   Sanal kupon: veritabanına kaydedilmez, sadece o oturum için geçerlidir.
──────────────────────────────────────────────────────────────────── */
define( 'PZ_NAKIT_COUPON_CODE', 'pz-nakit-iskonto-otomatik' );

/* Sanal kupon verisini sağla — DB'ye kaydetmeden çalışır */
add_filter( 'woocommerce_get_shop_coupon_data', function( $data, $code, $coupon ) {
    if ( strtolower( $code ) !== PZ_NAKIT_COUPON_CODE ) return $data;
    $discount = pz_cart_total_nakit();
    return array(
        'id'                         => 0,
        'code'                       => PZ_NAKIT_COUPON_CODE,
        'amount'                     => pz_cart_total_nakit(), // vendor oranlarına göre hesaplanmış tutar
        'discount_type'              => 'fixed_cart',
        'individual_use'             => false,
        'product_ids'                => array(),
        'excluded_product_ids'       => array(),
        'usage_limit'                => 0,
        'usage_limit_per_user'       => 0,
        'usage_count'                => 0,
        'date_expires'               => null,
        'free_shipping'              => false,
        'product_categories'         => array(),
        'excluded_product_categories'=> array(),
        'exclude_sale_items'         => false,
        'minimum_amount'             => '',
        'maximum_amount'             => '',
        'customer_email'             => array(),
        'description'                => 'Nakit Odeme Iskontosu',
    );
}, 10, 3 );

/* Kupon label'ını dinamik olarak göster */
add_filter( 'woocommerce_cart_totals_coupon_label', function( $label, $coupon ) {
    if ( $coupon->get_code() === PZ_NAKIT_COUPON_CODE ) {
        $rates = array();
        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart() as $item ) {
                if ( empty( $item['data'] ) ) continue;
                $rate = pz_get_vendor_nakit_rate( $item['data']->get_id() );
                $rates[ round( $rate * 100, 1 ) ] = true;
            }
        }
        if ( count( $rates ) === 1 ) {
            $pct = array_key_first( $rates );
            return '💳 Nakit Ödeme İskontosu (%' . $pct . ')';
        }
        return '💳 Nakit Ödeme İskontosu';
    }
    return $label;
}, 10, 2 );

/* [Kaldır] linkini gizle — müşteri bu kuponu kaldıramamalı */
add_filter( 'woocommerce_cart_totals_coupon_html', function( $coupon_html, $coupon, $discount_amount_html ) {
    if ( $coupon->get_code() === PZ_NAKIT_COUPON_CODE ) {
        return $discount_amount_html;
    }
    return $coupon_html;
}, 10, 3 );

/* Nakit seçilince kuponu ekle, seçilmeyince kaldır */
add_action( 'woocommerce_before_calculate_totals', function( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    $code       = PZ_NAKIT_COUPON_CODE;
    $has_coupon = $cart->has_discount( $code );
    $is_nakit   = pz_is_nakit_selected();

    if ( $is_nakit && ! $has_coupon ) {
        $cart->apply_coupon( $code );
    } elseif ( ! $is_nakit && $has_coupon ) {
        $cart->remove_coupon( $code );
    }
} );

/* Kuponun manuel eklenmesini/kaldırılmasını engelle */
add_filter( 'woocommerce_coupon_is_valid', function( $valid, $coupon, $discount ) {
    if ( $coupon->get_code() === PZ_NAKIT_COUPON_CODE ) {
        return pz_is_nakit_selected();
    }
    return $valid;
}, 10, 3 );

/* Sipariş oluştuktan sonra kupon zaten siparişe yansımış olur — ekstra işlem yok */
add_action( 'woocommerce_checkout_update_order_meta', function( $order_id ) {} );

add_filter( 'woocommerce_cart_item_name', function ( $name, $cart_item, $cart_item_key ) {
    if ( is_admin() ) return $name;
    $d = pz_item_nakit_discount( $cart_item );
    if ( $d <= 0 ) return $name;
    $name .= '<span class="pz-nakit-item-badge">'
           . '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>'
           . ' Nakit &ouml;demede <strong>' . wc_price( $d ) . '</strong> indirim</span>';
    return $name;
}, 10, 3 );

/* Sepet/ödeme sayfasında nakit iskonto bilgi satırı (nakit seçilmemişken) */
add_action( 'woocommerce_cart_totals_before_order_total', 'pz_render_nakit_total_row' );
add_action( 'woocommerce_review_order_before_order_total', 'pz_render_nakit_total_row' );
function pz_render_nakit_total_row() {
    $discount = pz_cart_total_nakit();
    if ( $discount <= 0 ) return;
    if ( pz_is_nakit_selected() ) return;
    // Oranı dinamik göster
    $rates = array();
    foreach ( WC()->cart->get_cart() as $item ) {
        if ( empty( $item['data'] ) ) continue;
        $rate = pz_get_vendor_nakit_rate( $item['data']->get_id() );
        $rates[ round( $rate * 100, 1 ) ] = true;
    }
    $rate_label = count( $rates ) === 1 ? '%' . array_key_first( $rates ) : '';
    ?>
    <tr class="pz-nakit-total-row">
        <th>
            <svg class="pz-nakit-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
            Nakit &Ouml;deme<?php echo $rate_label ? ' &dot; ' . esc_html( $rate_label ) : ''; ?> &nbsp;<span class="pz-nakit-total-hint">Nakit se&ccedil;ilirse uygulan&iacute;r</span>
        </th>
        <td><span class="pz-nakit-save pz-nakit-save-preview">-<?php echo wc_price( $discount ); ?></span></td>
    </tr>
    <?php
}

/* Sepet üstü nakit teşvik banner — sadece sepette */
add_action( 'woocommerce_before_cart_totals', function () {
    $discount = pz_cart_total_nakit();
    if ( $discount <= 0 ) return;
    echo '<div class="pz-nakit-banner">'
       . '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>'
       . '<span>Nakit &ouml;deme se&ccedil;erseniz <strong>' . wc_price( $discount ) . '</strong> tasarruf edersiniz!</span>'
       . '</div>';
} );

/* Fee HTML'ini yeşil yap */
add_filter( 'woocommerce_cart_totals_fee_html', function ( $fee_html, $fee ) {
    if ( strpos( $fee->name, 'Nakit' ) !== false && (float) $fee->total < 0 ) {
        return '<span class="pz-nakit-save pz-nakit-applied">' . $fee_html . '</span>';
    }
    return $fee_html;
}, 10, 2 );

/* Sipariş onay/teşekkür sayfasında fee label'i güzelleştir
   NOT: woocommerce_get_order_item_totals label alanında HTML tag'leri
   escape edilir; bu yüzden sadece düz metin + emoji kullanıyoruz. */
add_filter( 'woocommerce_get_order_item_totals', function ( $totals, $order, $tax_display ) {
    foreach ( $totals as $key => $total ) {
        if ( isset( $total['label'] ) && strpos( $total['label'], 'Nakit' ) !== false ) {
            $totals[ $key ]['label'] = '💳 ' . esc_html( strip_tags( $total['label'] ) );
        }
    }
    return $totals;
}, 10, 3 );

/* Ödeme sayfasında ödeme yöntemi değişince sepeti AJAX ile yenile
   → woocommerce_cart_calculate_fees tetiklenir → nakit fee eklenir/kaldırılır */
add_action( 'woocommerce_review_order_before_payment', function () {
    if ( ! is_checkout() ) return;
    ?>
    <script>
    (function($){
        if ( typeof wc_checkout_params === 'undefined' ) return;
        $( document.body ).on( 'change', 'input[name="payment_method"]', function(){
            $( document.body ).trigger( 'update_checkout' );
        });
    })(jQuery);
    </script>
    <?php
} );

/* ── Sipariş oluşturulunca nakit iskontosu garantisi ──────────────────
   woocommerce_cart_calculate_fees bazı akışlarda siparişe yansımaz.
   woocommerce_checkout_update_order_meta daha erken ve güvenilir tetiklenir;
   sipariş nesnesi üzerinde ödeme yöntemini kesin okuyup fee ekler.
   Cart hook zaten çalıştıysa tekrar eklemez.
─────────────────────────────────────────────────────────────────── */
add_action( 'woocommerce_checkout_update_order_meta', function ( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    $payment  = strtolower( (string) $order->get_payment_method() );
    $is_nakit = false;
    foreach ( pz_nakit_payment_ids() as $id ) {
        if ( $payment === strtolower( $id ) || strpos( $payment, strtolower( $id ) ) !== false ) {
            $is_nakit = true;
            break;
        }
    }
    if ( ! $is_nakit ) return;

    // Cart fee hook zaten çalıştıysa tekrar ekleme
    foreach ( $order->get_fees() as $fee ) {
        if ( strpos( $fee->get_name(), 'Nakit' ) !== false ) return;
    }

    // Sipariş kalemlerinden vendor oranına göre iskonto hesapla
    $discount = 0;
    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        $rate       = pz_get_vendor_nakit_rate( $product_id );
        $discount  += (float) $item->get_subtotal() * $rate;
    }
    $discount = round( $discount, 2 );
    if ( $discount <= 0 ) return;

    $fee_item = new WC_Order_Item_Fee();
    $fee_item->set_name( 'Nakit Odeme Iskontosu (%5)' );
    $fee_item->set_amount( -$discount );
    $fee_item->set_total( -$discount );
    $fee_item->set_tax_status( 'none' );
    $fee_item->set_tax_class( '' );
    $order->add_item( $fee_item );
    $order->calculate_totals( false );
    $order->save();
} );

/* ══════════════════════════════════════════════════════════════
   SITEMAP — Gereksiz taksonomi ve post type sitemaplarını kapat
   ══════════════════════════════════════════════════════════════ */

// Taksonomi bazlı sitemap'leri kapat
add_filter( 'wpseo_sitemap_exclude_taxonomy', function ( $excluded, $taxonomy ) {
    $exclude_list = array(
        'product_tag',
        'product_shipping_class',
        'pa_kordon_tipi',
        'pa_materyal',
        'pa_standart',
        'pa_tarz',
        'pa_tas_cinsi',
    );
    return in_array( $taxonomy, $exclude_list, true ) ? true : $excluded;
}, 10, 2 );

// Post type bazlı sitemap'leri kapat
add_filter( 'wpseo_sitemap_exclude_post_type', function ( $excluded, $post_type ) {
    $exclude_list = array( 'pz_saticim', 'pzv_vendor', 'pro_vendor', 'product_variation' );
    return in_array( $post_type, $exclude_list, true ) ? true : $excluded;
}, 10, 2 );

// Yazar sitemap'ini kapat
add_filter( 'wpseo_sitemap_exclude_author', '__return_true' );

// Sitemap index'i oluştururken pa_* ve vendor sitemaplarını çıkar
add_filter( 'wpseo_sitemap_index', function ( $xml ) {
    // pa_ ile başlayan tüm attribute sitemap'lerini kaldır
    $xml = preg_replace( '/<sitemap>\s*<loc>[^<]*\/pa_[^<]*<\/loc>[^<]*<lastmod>[^<]*<\/lastmod>\s*<\/sitemap>\s*/s', '', $xml );
    // vendor, author, tag sitemaplarını kaldır
    $patterns = array(
        'pzv-vendor-sitemap.xml',
        'pro-vendor-sitemap.xml',
        'author-sitemap.xml',
        'product-tag-sitemap.xml',
        'product_shipping_class-sitemap.xml',
    );
    foreach ( $patterns as $p ) {
        $xml = preg_replace( '/<sitemap>\s*<loc>[^<]*' . preg_quote( $p, '/' ) . '<\/loc>[^<]*<lastmod>[^<]*<\/lastmod>\s*<\/sitemap>\s*/s', '', $xml );
    }
    return $xml;
} );

/* ══════════════════════════════════════════════════════════════
   5XX KORUMA — Aşırı uzun filtre URL'lerini yakala, 404 döndür
   /shop/category-X-or-Y-or-Z/ formatındaki URL'ler sunucu 500
   hatasına yol açıyor. 200 karakteri aşan shop URL'lerini engelle.
   ══════════════════════════════════════════════════════════════ */
add_action( 'template_redirect', function () {
    if ( ! isset( $_SERVER['REQUEST_URI'] ) ) return;
    $uri = $_SERVER['REQUEST_URI'];

    // /shop/ veya /product-tag/ ile başlayan çok uzun URL'leri engelle
    $is_shop_url = ( strpos( $uri, '/shop/' ) !== false || strpos( $uri, '/product-tag/' ) !== false );
    if ( ! $is_shop_url ) return;

    // URL 200 karakteri aşıyorsa veya 2'den fazla -or- içeriyorsa 404
    $uri_length   = strlen( $uri );
    $or_count     = substr_count( strtolower( $uri ), '-or-' );

    if ( $uri_length > 200 || $or_count >= 2 ) {
        status_header( 404 );
        nocache_headers();
        include get_404_template();
        exit;
    }
}, 1 );
