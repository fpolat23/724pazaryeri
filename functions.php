<?php
defined('ABSPATH') || exit;

define('PAZARYERI_VERSION', '1.0.0');
define('PAZARYERI_DIR', get_template_directory());
define('PAZARYERI_URI', get_template_directory_uri());

/* ── Theme Setup ── */
function pazaryeri_setup() {
    load_theme_textdomain('724pazaryeri', PAZARYERI_DIR . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', [
        'height'      => 80,
        'width'       => 280,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_theme_support('woocommerce', [
        'thumbnail_image_width' => 480,
        'single_image_width'    => 800,
        'product_grid'          => ['default_rows' => 4, 'min_rows' => 1, 'default_columns' => 5, 'min_columns' => 1, 'max_columns' => 6],
    ]);
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    register_nav_menus([
        'primary'  => __('Ana Menü', '724pazaryeri'),
        'category' => __('Kategori Menüsü', '724pazaryeri'),
        'footer'   => __('Alt Menü', '724pazaryeri'),
    ]);
}
add_action('after_setup_theme', 'pazaryeri_setup');

/* ── Widget Areas ── */
function pazaryeri_widgets_init() {
    $sidebars = [
        ['name' => __('Ana Kenar Çubuğu', '724pazaryeri'), 'id' => 'sidebar-1'],
        ['name' => __('Mağaza Filtresi', '724pazaryeri'),  'id' => 'shop-filters'],
        ['name' => __('Alt Alan 1', '724pazaryeri'),        'id' => 'footer-1'],
        ['name' => __('Alt Alan 2', '724pazaryeri'),        'id' => 'footer-2'],
        ['name' => __('Alt Alan 3', '724pazaryeri'),        'id' => 'footer-3'],
    ];
    foreach ($sidebars as $sb) {
        register_sidebar(array_merge($sb, [
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ]));
    }
}
add_action('widgets_init', 'pazaryeri_widgets_init');

/* ── Enqueue Assets ── */
function pazaryeri_enqueue() {
    wp_enqueue_style('724pazaryeri-style', get_stylesheet_uri(), [], PAZARYERI_VERSION);
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', [], '6.4.0');

    wp_enqueue_script('724pazaryeri-main', PAZARYERI_URI . '/assets/js/main.js', ['jquery'], PAZARYERI_VERSION, true);

    wp_localize_script('724pazaryeri-main', 'pazaryeriData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('pazaryeri_nonce'),
        'cartUrl' => wc_get_cart_url(),
        'i18n'    => [
            'addedToCart'    => __('Sepete eklendi!', '724pazaryeri'),
            'addToWishlist'  => __('Favorilere eklendi!', '724pazaryeri'),
            'removeFromWish' => __('Favorilerden çıkarıldı!', '724pazaryeri'),
        ],
    ]);
}
add_action('wp_enqueue_scripts', 'pazaryeri_enqueue');

/* ── WooCommerce Overrides ── */
remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
remove_action('woocommerce_after_main_content',  'woocommerce_output_content_wrapper_end', 10);
remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar', 10);

add_action('woocommerce_before_main_content', function() {
    echo '<div class="woo-content-wrapper container">';
}, 10);
add_action('woocommerce_after_main_content', function() {
    echo '</div>';
}, 10);

add_filter('woocommerce_product_loop_start', function($loop) {
    return '<div class="product-grid">';
});
add_filter('woocommerce_product_loop_end', function($loop) {
    return '</div>';
});

/* ── Product Card Template ── */
add_filter('woocommerce_loop_add_to_cart_link', function($link, $product) {
    return sprintf(
        '<a href="%s" data-quantity="1" class="product-add-cart ajax_add_to_cart" data-product_id="%d" data-product_sku="%s" aria-label="%s">
            <i class="fas fa-shopping-cart"></i> %s
        </a>',
        esc_url($product->add_to_cart_url()),
        $product->get_id(),
        esc_attr($product->get_sku()),
        esc_attr($product->add_to_cart_description()),
        esc_html($product->add_to_cart_text())
    );
}, 10, 2);

/* ── Custom Excerpt Length ── */
add_filter('excerpt_length', fn() => 20);

/* ── Ajax: Add to Wishlist ── */
function pazaryeri_toggle_wishlist() {
    check_ajax_referer('pazaryeri_nonce', 'nonce');
    $product_id = absint($_POST['product_id'] ?? 0);
    if (!$product_id) wp_send_json_error();

    $wishlist = (array) get_user_meta(get_current_user_id(), '_pazaryeri_wishlist', true);
    $added = false;
    if (in_array($product_id, $wishlist)) {
        $wishlist = array_diff($wishlist, [$product_id]);
    } else {
        $wishlist[] = $product_id;
        $added = true;
    }
    update_user_meta(get_current_user_id(), '_pazaryeri_wishlist', array_values($wishlist));
    wp_send_json_success(['added' => $added, 'count' => count($wishlist)]);
}
add_action('wp_ajax_pazaryeri_toggle_wishlist', 'pazaryeri_toggle_wishlist');

/* ── Ajax: Quick View ── */
function pazaryeri_quick_view() {
    check_ajax_referer('pazaryeri_nonce', 'nonce');
    $product_id = absint($_POST['product_id'] ?? 0);
    $product = wc_get_product($product_id);
    if (!$product) wp_send_json_error();

    ob_start();
    wc_get_template('content-single-product.php');
    wp_send_json_success(['html' => ob_get_clean()]);
}
add_action('wp_ajax_pazaryeri_quick_view', 'pazaryeri_quick_view');
add_action('wp_ajax_nopriv_pazaryeri_quick_view', 'pazaryeri_quick_view');

/* ── Customizer ── */
function pazaryeri_customize_register($wp_customize) {
    $wp_customize->add_panel('pazaryeri_panel', [
        'title'    => __('724 Pazaryeri Ayarları', '724pazaryeri'),
        'priority' => 30,
    ]);

    /* Colors */
    $wp_customize->add_section('pazaryeri_colors', [
        'title' => __('Renkler', '724pazaryeri'),
        'panel' => 'pazaryeri_panel',
    ]);
    $colors = [
        'primary_color' => ['label' => __('Ana Renk', '724pazaryeri'),        'default' => '#FF6000'],
        'secondary_color' => ['label' => __('İkincil Renk', '724pazaryeri'),   'default' => '#1a1a2e'],
        'accent_color'  => ['label' => __('Vurgu Rengi', '724pazaryeri'),      'default' => '#ffd700'],
    ];
    foreach ($colors as $key => $args) {
        $wp_customize->add_setting("pazaryeri_{$key}", ['default' => $args['default'], 'sanitize_callback' => 'sanitize_hex_color', 'transport' => 'postMessage']);
        $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, "pazaryeri_{$key}", [
            'label'   => $args['label'],
            'section' => 'pazaryeri_colors',
        ]));
    }

    /* Hero Slider */
    $wp_customize->add_section('pazaryeri_slider', [
        'title' => __('Ana Sayfa Slider', '724pazaryeri'),
        'panel' => 'pazaryeri_panel',
    ]);
    $wp_customize->add_setting('pazaryeri_slider_autoplay', ['default' => true,  'sanitize_callback' => 'wp_validate_boolean']);
    $wp_customize->add_control('pazaryeri_slider_autoplay', ['type' => 'checkbox', 'label' => __('Otomatik Geçiş', '724pazaryeri'), 'section' => 'pazaryeri_slider']);
    $wp_customize->add_setting('pazaryeri_slider_speed', ['default' => 5000, 'sanitize_callback' => 'absint']);
    $wp_customize->add_control('pazaryeri_slider_speed', ['type' => 'number', 'label' => __('Geçiş Hızı (ms)', '724pazaryeri'), 'section' => 'pazaryeri_slider']);
}
add_action('customize_register', 'pazaryeri_customize_register');

/* ── Dynamic CSS from Customizer ── */
function pazaryeri_customizer_css() {
    $primary   = get_theme_mod('pazaryeri_primary_color',   '#FF6000');
    $secondary = get_theme_mod('pazaryeri_secondary_color', '#1a1a2e');
    $accent    = get_theme_mod('pazaryeri_accent_color',    '#ffd700');
    printf(
        '<style>:root{--primary:%s;--secondary:%s;--accent:%s;--primary-dark:%s;}</style>',
        esc_attr($primary),
        esc_attr($secondary),
        esc_attr($accent),
        esc_attr(pazaryeri_darken($primary, 15))
    );
}
add_action('wp_head', 'pazaryeri_customizer_css');

function pazaryeri_darken($hex, $percent) {
    $hex = ltrim($hex, '#');
    $r = max(0, hexdec(substr($hex,0,2)) - round(255 * $percent / 100));
    $g = max(0, hexdec(substr($hex,2,2)) - round(255 * $percent / 100));
    $b = max(0, hexdec(substr($hex,4,2)) - round(255 * $percent / 100));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/* ── Helper: Get Discount Percentage ── */
function pazaryeri_get_discount($product) {
    if (!$product->is_on_sale()) return 0;
    $regular = (float) $product->get_regular_price();
    $sale    = (float) $product->get_sale_price();
    if ($regular <= 0) return 0;
    return round((($regular - $sale) / $regular) * 100);
}

/* ── Template Tags ── */
function pazaryeri_product_card($product_id = null) {
    global $product;
    if ($product_id) $product = wc_get_product($product_id);
    get_template_part('template-parts/content', 'product-card');
}
