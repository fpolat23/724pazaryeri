<?php
/**
 * Plugin Name: PazarYeri Vendor System
 * Description: Hafif WooCommerce çoklu satıcı sistemi. Kategori bazlı komisyon, frontend dashboard, sipariş yönetimi.
 * Version:     1.4.2
 * Author:      724PazarYeri
 * Requires PHP: 7.2
 * Requires at least: 5.6
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'PZV_VERSION' ) ) define( 'PZV_VERSION', '1.4.0' );
if ( ! defined( 'PZV_FILE' ) )   define( 'PZV_FILE',   __FILE__ );
if ( ! defined( 'PZV_DIR' ) )    define( 'PZV_DIR',    plugin_dir_path( __FILE__ ) );
if ( ! defined( 'PZV_URL' ) )    define( 'PZV_URL',    plugin_dir_url( __FILE__ ) );
if ( ! defined( 'PZV_ROLE' ) )   define( 'PZV_ROLE',   'pzv_vendor' );

if ( ! class_exists( 'PZV_Roles' ) )      require_once PZV_DIR . 'includes/class-roles.php';
if ( ! class_exists( 'PZV_Vendor' ) )     require_once PZV_DIR . 'includes/class-vendor.php';
if ( ! class_exists( 'PZV_Commission' ) ) require_once PZV_DIR . 'includes/class-commission.php';
if ( ! class_exists( 'PZV_Admin' ) )      require_once PZV_DIR . 'includes/class-admin.php';
if ( ! class_exists( 'PZV_Dashboard' ) )  require_once PZV_DIR . 'includes/class-dashboard.php';

register_activation_hook( __FILE__, function () {
    PZV_Roles::create_role();
    PZV_Commission::create_table();
    if ( ! get_page_by_path( 'saticim' ) ) {
        wp_insert_post( array(
            'post_title'   => 'Satıcı Paneli',
            'post_name'    => 'saticim',
            'post_content' => '[pazaryeri_vendor_dashboard]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ) );
    }
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );

add_action( 'admin_init', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>PazarYeri Vendor:</strong> WooCommerce gerekli.</p></div>';
        } );
    }
} );

// HPOS uyumluluk deklarasyonu
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) return;
    new PZV_Admin();
    new PZV_Dashboard();
    new PZV_Commission();

    // Vendor ürünlerini otomatik "onay bekliyor" statüsüne düşür
    add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
        if ( $data['post_type'] !== 'product' ) return $data;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $data;
        if ( defined( 'PZV_DOING_QUICK_SAVE' ) ) return $data;
        if ( ! is_user_logged_in() ) return $data;
        if ( current_user_can( 'manage_woocommerce' ) ) return $data;
        $uid = get_current_user_id();
        if ( ! PZV_Roles::is_vendor( $uid ) ) return $data;
        if ( $data['post_status'] === 'publish' ) {
            $data['post_status'] = 'pending';
        }
        return $data;
    }, 99, 2 );

    // Ürün onay beklemeye girdiğinde yöneticiye e-posta gönder
    add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
        if ( $post->post_type !== 'product' ) return;
        if ( $new_status !== 'pending' || $old_status === 'pending' ) return;
        $author_id = (int) $post->post_author;
        if ( ! PZV_Roles::is_vendor( $author_id ) ) return;
        $vendor      = PZV_Vendor::get( $author_id );
        $store_name  = $vendor ? $vendor['store_name'] : 'bilinmiyor';
        $admin_email = get_option( 'admin_email' );
        $subject     = '724PazarYeri - Onay Bekleyen Ürün: ' . $post->post_title;
        $body        = "Satıcı \"{$store_name}\" bir ürünü onayınızı bekliyor.\n\n"
                     . "Ürün: {$post->post_title}\n"
                     . "Düzenle: " . admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) . "\n\n"
                     . "Bekleyen ürünler: " . admin_url( 'admin.php?page=pzv-pending' );
        wp_mail( $admin_email, $subject, $body );
    }, 10, 3 );
} );

add_shortcode( 'pazaryeri_vendor_dashboard', array( 'PZV_Dashboard', 'render_shortcode' ) );

// ── Cache uyumluluğu ────────────────────────────────────────────────────────
// WP Rocket ve diğer cache eklentileri vendor dashboard sayfasını önbelleğe
// almamalı; her istek kullanıcıya özel ve ?tab= parametresine duyarlıdır.

// WP Rocket: /saticim/ URL'sini reddedilen URI listesine ekle
add_filter( 'rocket_cache_reject_uri', function( $uris ) {
    $uris[] = '/saticim/';
    return $uris;
} );

// WP Rocket: ?tab= query parametresini cache key'e dahil et (ek güvence)
add_filter( 'rocket_cache_query_strings', function( $qs ) {
    $qs[] = 'tab';
    $qs[] = 'view';
    $qs[] = 'edit';
    $qs[] = 'paged';
    return $qs;
} );

// Tüm cache eklentileri: vendor dashboard sayfasında cache başlıklarını kapat
add_action( 'template_redirect', function() {
    if ( ! is_page() ) return;
    global $post;
    if ( ! $post ) return;
    $is_dash = ( $post->post_name === 'saticim' )
            || ( strpos( $post->post_content, '[pazaryeri_vendor_dashboard]' ) !== false );
    if ( ! $is_dash ) return;
    if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
    if ( ! defined( 'DONOTMINIFY' ) )   define( 'DONOTMINIFY',   true );
    nocache_headers();
}, 1 );

// WP Rocket: config dosyasını yenile + /saticim/ cache'ini temizle (versiyon değişince)
// rocket_cache_reject_uri/rocket_cache_query_strings filtreleri config dosyasına yazılana
// kadar etkili olmaz; bu hook yeni kurulum/güncelleme sonrasında bunu otomatik yapar.
add_action( 'init', function() {
    if ( wp_doing_ajax() || wp_doing_cron() ) return;
    $pzv_ver = defined( 'PZV_VERSION' ) ? PZV_VERSION : '0';
    $opt_key  = 'pzv_rocket_config_flushed';
    if ( get_option( $opt_key ) === $pzv_ver ) return;

    $page = get_page_by_path( 'saticim' );
    if ( function_exists( 'rocket_generate_config_file' ) ) {
        rocket_generate_config_file();
    }
    if ( $page && function_exists( 'rocket_clean_files' ) ) {
        rocket_clean_files( array( get_permalink( $page->ID ) ) );
    }
    if ( function_exists( 'w3tc_flush_url' ) && $page ) {
        w3tc_flush_url( get_permalink( $page->ID ) );
    }
    update_option( $opt_key, $pzv_ver, false );
}, 25 );

// AJAX endpoints
add_action( 'wp_ajax_pzv_approve_vendor',       array( 'PZV_Admin',    'ajax_approve_vendor' ) );
add_action( 'wp_ajax_pzv_save_commission',      array( 'PZV_Admin',    'ajax_save_commission' ) );
add_action( 'wp_ajax_pzv_mark_paid',            array( 'PZV_Admin',    'ajax_mark_paid' ) );
add_action( 'wp_ajax_pzv_search_products',      array( 'PZV_Dashboard','ajax_search_products' ) );
add_action( 'wp_ajax_pzv_clone_product',        array( 'PZV_Dashboard','ajax_clone_product' ) );
add_action( 'wp_ajax_pzv_update_my_product',    array( 'PZV_Dashboard','ajax_update_my_product' ) );
add_action( 'wp_ajax_pzv_update_order',         array( 'PZV_Dashboard','ajax_update_order' ) );
add_action( 'wp_ajax_pzv_approve_product',      array( 'PZV_Admin',    'ajax_approve_product' ) );
add_action( 'wp_ajax_pzv_dokan_migrate',        array( 'PZV_Admin',    'ajax_dokan_migrate' ) );
add_action( 'wp_ajax_pzv_load_tab',             array( 'PZV_Dashboard','ajax_load_tab' ) );
add_action( 'wp_ajax_pzv_new_product',          array( 'PZV_Dashboard','ajax_new_product' ) );
add_action( 'wp_ajax_pzv_save_product_data',    array( 'PZV_Dashboard','ajax_save_product_data' ) );
add_action( 'wp_ajax_pzv_save_vendor_profile',  array( 'PZV_Dashboard','ajax_save_vendor_profile' ) );
add_action( 'wp_ajax_pzv_toggle_vendor_status', array( 'PZV_Admin',    'ajax_toggle_vendor_status' ) );
add_action( 'wp_ajax_pzv_delete_vendor',        array( 'PZV_Admin',    'ajax_delete_vendor' ) );
add_action( 'wp_ajax_pzv_fix_product_authors',  array( 'PZV_Admin',    'ajax_fix_product_authors' ) );

// Frontend: pasif satıcıların ürünlerini + fiyatı 0 olan ürünleri gizle
add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() ) return;
    if ( ! $query->is_main_query() ) return;
    if ( $query->get( 'post_type' ) !== 'product' ) return;

    // Pasif satıcıların ürünlerini gizle
    $inactive_vendors = get_users( array(
        'meta_key'   => 'pzv_status',
        'meta_value' => 'inactive',
        'role'       => PZV_ROLE,
        'fields'     => 'ID',
        'number'     => -1,
    ) );
    if ( ! empty( $inactive_vendors ) ) {
        $current_not_in = (array) $query->get( 'author__not_in' );
        $query->set( 'author__not_in', array_unique( array_merge( $current_not_in, $inactive_vendors ) ) );
    }

    // Fiyatı 0 veya girilmemiş ürünleri her yerde gizle
    $meta_query   = (array) $query->get( 'meta_query' );
    $meta_query[] = array(
        'key'     => '_price',
        'value'   => '0',
        'compare' => '>',
        'type'    => 'NUMERIC',
    );
    $query->set( 'meta_query', $meta_query );
} );

// Mağaza URL rewrite: /magaza/{slug}/ ve /magaza/{slug}/page/{n}/
add_action( 'init', function () {
    add_rewrite_rule( '^magaza/([^/]+)/page/([0-9]+)/?$', 'index.php?pzv_store=$matches[1]&paged=$matches[2]', 'top' );
    add_rewrite_rule( '^magaza/([^/]+)/?$',               'index.php?pzv_store=$matches[1]',                    'top' );
    add_rewrite_endpoint( 'satici-paneli', EP_ROOT | EP_PAGES );
    $rules = get_option( 'rewrite_rules' );
    if ( empty( $rules )
        || ! isset( $rules['^magaza/([^/]+)/?$'] )
        || ! isset( $rules['^magaza/([^/]+)/page/([0-9]+)/?$'] )
    ) {
        flush_rewrite_rules( false );
    }
} );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'pzv_store';
    return $vars;
} );

add_filter( 'template_include', function ( $template ) {
    if ( ! get_query_var( 'pzv_store' ) ) return $template;
    $theme_tpl = locate_template( 'magaza.php' );
    return $theme_tpl ? $theme_tpl : $template;
} );

add_filter( 'woocommerce_account_menu_items', function ( $items ) {
    if ( ! is_user_logged_in() ) return $items;
    if ( ! PZV_Roles::is_pure_vendor( get_current_user_id() ) ) return $items;
    $new = array();
    foreach ( $items as $k => $v ) {
        $new[ $k ] = $v;
        if ( $k === 'dashboard' ) $new['satici-paneli'] = '🏪 Satıcı Panelim';
    }
    return $new;
} );

add_action( 'woocommerce_account_satici-paneli_endpoint', function () {
    wp_redirect( home_url( '/saticim/' ) );
    exit;
} );


// ══════════════════════════════════════════════
//  SEO — 1) Sitemap: satıcı profil URL'lerini ekle
// ══════════════════════════════════════════════
add_action( 'init', function () {
    // Yoast sitemap index'e "pzv-vendor" isimli özel sitemap ekle
    if ( ! class_exists( 'WPSEO_Sitemaps' ) ) return;
    add_filter( 'wpseo_sitemap_index', function ( $sitemap_index ) {
        $vendors = PZV_Vendor::get_active();
        if ( empty( $vendors ) ) return $sitemap_index;
        $lastmod = date( 'c' );
        $sitemap_index .= '<sitemap>' . "\n";
        $sitemap_index .= '<loc>' . esc_url( home_url( '/pzv-vendor-sitemap.xml' ) ) . '</loc>' . "\n";
        $sitemap_index .= '<lastmod>' . $lastmod . '</lastmod>' . "\n";
        $sitemap_index .= '</sitemap>' . "\n";
        return $sitemap_index;
    } );

    // Satıcı sitemap içeriği: /pzv-vendor-sitemap.xml
    add_filter( 'template_redirect', function () {
        if ( ! isset( $_SERVER['REQUEST_URI'] ) ) return;
        $uri = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
        if ( rtrim( $uri, '/' ) !== '/pzv-vendor-sitemap' && strpos( $uri, 'pzv-vendor-sitemap.xml' ) === false ) return;
        // WP Rocket / Varnish cache bypass
        nocache_headers();
        header( 'Content-Type: application/xml; charset=UTF-8' );
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $vendors = PZV_Vendor::get_active();
        foreach ( $vendors as $vendor ) {
            $url = PZV_Vendor::store_url( $vendor->ID );
            if ( ! $url ) continue;
            echo '<url>' . "\n";
            echo '<loc>' . esc_url( $url ) . '</loc>' . "\n";
            echo '<changefreq>weekly</changefreq>' . "\n";
            echo '<priority>0.7</priority>' . "\n";
            echo '</url>' . "\n";
        }
        echo '</urlset>';
        exit;
    } );
}, 15 );

// WP Rocket: satıcı sitemap URL'ini önbellekten hariç tut
add_filter( 'rocket_cache_reject_uri', function ( $uris ) {
    $uris[] = '/pzv-vendor-sitemap.xml';
    return $uris;
} );


// ══════════════════════════════════════════════
//  SEO — 2) Clone ürünlere canonical URL ekle
//  (orijinal ürüne işaret eder — duplicate content önler)
// ══════════════════════════════════════════════
add_action( 'wp_head', function () {
    if ( ! is_singular( 'product' ) ) return;
    global $post;
    if ( ! $post ) return;
    $original_id = (int) get_post_meta( $post->ID, '_pzv_cloned_from', true );
    if ( ! $original_id ) return;
    $original = get_post( $original_id );
    if ( ! $original || $original->post_status !== 'publish' ) return;
    $canonical = get_permalink( $original_id );
    if ( ! $canonical ) return;
    // Yoast canonical'ını override et
    add_filter( 'wpseo_canonical', function ( $canon ) use ( $canonical ) {
        return $canonical;
    }, 99 );
    // Yoast yoksa manuel ekle
    if ( ! defined( 'WPSEO_VERSION' ) ) {
        echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
    }
}, 1 );


// ══════════════════════════════════════════════
//  SEO — 3) Breadcrumb: satıcı paneli ve mağaza sayfaları
// ══════════════════════════════════════════════
add_filter( 'wpseo_breadcrumb_links', function ( $crumbs ) {
    // /saticim/ sayfası
    if ( is_page( 'saticim' ) ) {
        return array(
            array( 'text' => 'Ana Sayfa', 'url' => home_url( '/' ) ),
            array( 'text' => 'Satıcı Paneli', 'url' => '' ),
        );
    }
    // /magaza/{slug}/ sayfaları
    $store_slug = get_query_var( 'pzv_store' );
    if ( $store_slug ) {
        $vendor_user = get_users( array(
            'meta_key'   => 'pzv_store_slug',
            'meta_value' => sanitize_title( $store_slug ),
            'role'       => PZV_ROLE,
            'number'     => 1,
        ) );
        if ( empty( $vendor_user ) ) {
            // slug bulunamazsa user_login ile dene
            $vendor_user = get_users( array(
                'login'  => sanitize_user( $store_slug ),
                'role'   => PZV_ROLE,
                'number' => 1,
            ) );
        }
        $store_name = ! empty( $vendor_user )
            ? ( get_user_meta( $vendor_user[0]->ID, 'pzv_store_name', true ) ?: $vendor_user[0]->display_name )
            : ucfirst( str_replace( '-', ' ', $store_slug ) );
        return array(
            array( 'text' => 'Ana Sayfa', 'url' => home_url( '/' ) ),
            array( 'text' => 'Satıcılar',  'url' => home_url( '/saticilar/' ) ),
            array( 'text' => $store_name,  'url' => '' ),
        );
    }
    return $crumbs;
}, 10 );


// ══════════════════════════════════════════════
//  SEO — 4) Mağaza sayfaları için Open Graph / title tag
// ══════════════════════════════════════════════
add_filter( 'wpseo_title', function ( $title ) {
    $store_slug = get_query_var( 'pzv_store' );
    if ( ! $store_slug ) return $title;
    $vendor_user = get_users( array(
        'meta_key'   => 'pzv_store_slug',
        'meta_value' => sanitize_title( $store_slug ),
        'role'       => PZV_ROLE,
        'number'     => 1,
    ) );
    if ( empty( $vendor_user ) ) return $title;
    $store_name = get_user_meta( $vendor_user[0]->ID, 'pzv_store_name', true ) ?: $vendor_user[0]->display_name;
    $site_name  = get_bloginfo( 'name' );
    return esc_html( $store_name ) . ' Mağazası | ' . esc_html( $site_name );
}, 10 );

add_filter( 'wpseo_metadesc', function ( $desc ) {
    $store_slug = get_query_var( 'pzv_store' );
    if ( ! $store_slug ) return $desc;
    $vendor_user = get_users( array(
        'meta_key'   => 'pzv_store_slug',
        'meta_value' => sanitize_title( $store_slug ),
        'role'       => PZV_ROLE,
        'number'     => 1,
    ) );
    if ( empty( $vendor_user ) ) return $desc;
    $vid        = $vendor_user[0]->ID;
    $store_name = get_user_meta( $vid, 'pzv_store_name', true ) ?: $vendor_user[0]->display_name;
    $store_desc = get_user_meta( $vid, 'pzv_description', true );
    $site_name  = get_bloginfo( 'name' );
    if ( $store_desc ) {
        return esc_html( mb_substr( $store_desc, 0, 150 ) ) . ' | ' . esc_html( $site_name );
    }
    return esc_html( $store_name ) . ' mağazasının ürünlerini ' . esc_html( $site_name ) . "'te keşfedin.";
}, 10 );


// ══════════════════════════════════════════════
//  SEO — 5) robots.txt'e sitemap direktifi ekle
//  (WordPress robots.txt filtresi ile)
// ══════════════════════════════════════════════
add_filter( 'robots_txt', function ( $output, $public ) {
    if ( '0' === $public ) return $output;
    $sitemap_url = home_url( '/sitemap_index.xml' );
    if ( strpos( $output, 'Sitemap:' ) === false ) {
        $output .= "\nSitemap: " . esc_url( $sitemap_url ) . "\n";
    }
    return $output;
}, 10, 2 );


// ══════════════════════════════════════════════
//  SEO — 6) Mağaza sayfası Open Graph meta etiketleri
// ══════════════════════════════════════════════
add_action( 'wp_head', function () {
    $store_slug = get_query_var( 'pzv_store' );
    if ( ! $store_slug ) return;

    $vendor_user = get_users( array(
        'meta_key'   => 'pzv_store_slug',
        'meta_value' => sanitize_title( $store_slug ),
        'role'       => PZV_ROLE,
        'number'     => 1,
    ) );
    if ( empty( $vendor_user ) ) return;

    $vid        = $vendor_user[0]->ID;
    $vendor     = PZV_Vendor::get( $vid );
    if ( ! $vendor ) return;

    $store_name = $vendor['store_name'];
    $store_url  = PZV_Vendor::store_url( $vid );
    $store_desc = $vendor['description']
        ? mb_substr( $vendor['description'], 0, 155 )
        : $store_name . ' mağazasının tüm ürünlerini ' . get_bloginfo( 'name' ) . "'te keşfedin.";
    $logo_url   = $vendor['logo'] ? wp_get_attachment_image_url( $vendor['logo'], 'large' ) : '';
    $banner_url = $vendor['banner'] ? wp_get_attachment_image_url( $vendor['banner'], 'large' ) : '';
    $og_image   = $banner_url ?: $logo_url;

    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr( $store_name ) . ' | ' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr( $store_desc ) . '">' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $store_url ) . '">' . "\n";
    if ( $og_image ) {
        echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
    }
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr( $store_name ) . '">' . "\n";
    echo '<meta name="twitter:description" content="' . esc_attr( $store_desc ) . '">' . "\n";
    if ( $og_image ) {
        echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '">' . "\n";
    }
}, 5 );


// ══════════════════════════════════════════════
//  SEO — 7) Mağaza sayfası canonical URL
//  /magaza/{slug}/ sayfalarında Yoast canonical'ı doğru URL'e ayarla
// ══════════════════════════════════════════════
add_filter( 'wpseo_canonical', function ( $canonical ) {
    $store_slug = get_query_var( 'pzv_store' );
    if ( ! $store_slug ) return $canonical;
    $vendor_user = get_users( array(
        'meta_key'   => 'pzv_store_slug',
        'meta_value' => sanitize_title( $store_slug ),
        'role'       => PZV_ROLE,
        'number'     => 1,
    ) );
    if ( empty( $vendor_user ) ) return $canonical;
    $store_url = PZV_Vendor::store_url( $vendor_user[0]->ID );
    return $store_url ?: $canonical;
}, 20 );


// ══════════════════════════════════════════════
//  SEO — 8) Mağaza sayfası Schema.org LocalBusiness / Store
// ══════════════════════════════════════════════
add_action( 'wp_head', function () {
    $store_slug = get_query_var( 'pzv_store' );
    if ( ! $store_slug ) return;
    $vendor_user = get_users( array(
        'meta_key'   => 'pzv_store_slug',
        'meta_value' => sanitize_title( $store_slug ),
        'role'       => PZV_ROLE,
        'number'     => 1,
    ) );
    if ( empty( $vendor_user ) ) return;
    $vid    = $vendor_user[0]->ID;
    $vendor = PZV_Vendor::get( $vid );
    if ( ! $vendor ) return;

    $store_url = PZV_Vendor::store_url( $vid );
    $logo_url  = $vendor['logo'] ? wp_get_attachment_image_url( $vendor['logo'], 'large' ) : '';

    $schema = array(
        '@context' => 'https://schema.org',
        '@type'    => 'Store',
        'name'     => $vendor['store_name'],
        'url'      => $store_url,
    );
    if ( $vendor['description'] ) $schema['description'] = $vendor['description'];
    if ( $logo_url )              $schema['image']       = $logo_url;
    if ( $vendor['city'] )        $schema['address']     = array( '@type' => 'PostalAddress', 'addressLocality' => $vendor['city'], 'addressCountry' => 'TR' );
    if ( $vendor['phone'] )       $schema['telephone']   = $vendor['phone'];
    if ( $vendor['working_hours'] ) $schema['openingHours'] = $vendor['working_hours'];

    // Mağazanın ürün sayısı
    $product_count = PZV_Vendor::product_count( $vid, 'publish' );
    if ( $product_count > 0 ) {
        $schema['numberOfItems'] = $product_count;
    }

    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
    echo "\n" . '</script>' . "\n";
}, 7 );


// ══════════════════════════════════════════════
//  SEO — 9) Clone ürünlerde noindex alternatif:
//  Eğer canonical orijinal ürüne işaret ediyorsa
//  Googlebot zaten orijinali tercih eder.
//  Ek olarak: clone ürünlerin sitemap'e girmesini engelle
// ══════════════════════════════════════════════
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', function ( $excluded_ids ) {
    global $wpdb;
    // _pzv_cloned_from meta'sı olan tüm ürünleri sitemap'ten çıkar
    $clone_ids = $wpdb->get_col(
        "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
         WHERE meta_key = '_pzv_cloned_from'
           AND meta_value != ''
           AND meta_value != '0'"
    );
    if ( ! empty( $clone_ids ) ) {
        $excluded_ids = array_merge( $excluded_ids, array_map( 'intval', $clone_ids ) );
    }
    return $excluded_ids;
} );
