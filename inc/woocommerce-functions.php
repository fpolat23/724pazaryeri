<?php
if ( ! defined( 'ABSPATH' ) ) exit;



/* ──────────────────────────────────────────────
   2) Tek bir ürün kartı HTML'i üreten fonksiyon
   (hem ilk yükte hem AJAX'ta kullanılır)
────────────────────────────────────────────── */
function bazario_product_card( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return '';
    }
    $id        = $product->get_id();
    $permalink = get_permalink( $id );
    $title     = $product->get_name();
    $img       = $product->get_image( 'woocommerce_thumbnail' );

    // fiyat
    $price_html = $product->get_price_html();
    $regular    = (float) $product->get_regular_price();
    $sale       = (float) $product->get_price();
    $discount   = ( $regular > 0 && $product->is_on_sale() ) ? round( ( ( $regular - $sale ) / $regular ) * 100 ) : 0;

    // puan
    $avg   = $product->get_average_rating();
    $count = $product->get_review_count();
    $stars = str_repeat( '★', round( $avg ) ) . str_repeat( '☆', 5 - round( $avg ) );

    // satıcı (Dokan varsa)
    $seller_name = '';
    $seller_url  = '';
    if ( function_exists( 'dokan_get_vendor_by_product' ) ) {
        $vendor = dokan_get_vendor_by_product( $id );
        if ( $vendor ) {
            $seller_name = $vendor->get_shop_name();
            $seller_url  = $vendor->get_shop_url();
        }
    }
    if ( ! $seller_name ) {
        $author      = get_post_field( 'post_author', $id );
        $seller_name = get_the_author_meta( 'display_name', $author );
    }
    $initials = mb_strtoupper( mb_substr( $seller_name, 0, 2 ) );

    // şehir (özel alan: _seller_city — yoksa boş)
    $city = get_post_meta( $id, '_seller_city', true );

    // rozet
    $badge = '';
    if ( $discount > 0 ) {
        $badge = '<span class="pbadge-disc">%' . esc_html( $discount ) . '<br><small>indirim</small></span>';
    } elseif ( $product->is_featured() ) {
        $badge = '<span class="pbadge hot">POPÜLER</span>';
    } elseif ( ( time() - get_post_time( 'U', false, $id ) ) < WEEK_IN_SECONDS ) {
        $badge = '<span class="pbadge new">YENİ</span>';
    }

    // ücretsiz kargo rozeti (resim üstü)
    $freeship_badge = ( $product->is_in_stock() && $sale >= 1500 )
        ? '<span class="pbadge-freeship"><svg viewBox="0 0 24 24" width="11" height="11" fill="currentColor" style="flex-shrink:0"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zm-.5 1.5 1.96 2.5H17V9.5h2.5zM6 18c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm11 0c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1z"/></svg> Ücretsiz<br>Kargo</span>'
        : '';

    // karşılaştırma verisi
    $comp_img  = wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' );
    $comp_data = esc_attr( wp_json_encode( array(
        'id'       => $id,
        'url'      => $permalink,
        'title'    => $title,
        'img'      => $comp_img,
        'price'    => $sale,
        'regular'  => $regular,
        'discount' => $discount,
        'rating'   => round( (float) $avg, 1 ),
        'reviews'  => (int) $count,
        'inStock'  => $product->is_in_stock(),
        'freeShip' => ( $product->is_in_stock() && $sale >= 1500 ),
        'seller'   => $seller_name,
    ) ) );

    ob_start(); ?>
    <div class="pcard" data-product-id="<?php echo esc_attr( $id ); ?>" data-href="<?php echo esc_url( $permalink ); ?>" data-pzcomp="<?php echo $comp_data; ?>" style="cursor:pointer">
        <a class="pimg" href="<?php echo esc_url( $permalink ); ?>" style="background:#fff;display:flex;align-items:center;justify-content:center;text-decoration:none;">
            <?php echo $badge; ?>
            <?php echo $freeship_badge; ?>
            <?php echo $img; ?>
            <div class="p-wish" onclick="event.stopPropagation();event.preventDefault();toggleWish(this)">🤍</div>
        </a>
        <div class="pbody">
            <div class="p-seller">
                <div class="p-av"><?php echo esc_html( $initials ); ?></div>
                <span class="p-sname"><?php echo esc_html( $seller_name ); ?></span>
                <span class="p-verified">✓</span>
            </div>
            <div class="pname"><?php echo esc_html( $title ); ?></div>
            <?php if ( $count > 0 ) : ?>
            <div class="prating"><span class="pstars"><?php echo $stars; ?></span><span class="prnum"><?php echo esc_html( number_format((float)$avg,1) ); ?> (<?php echo esc_html( $count ); ?>)</span></div>
            <?php else : ?>
            <div class="prating prating-empty"><span class="pstars">☆☆☆☆☆</span><span class="prnum">Henüz yok</span></div>
            <?php endif; ?>
            <div class="pprow">
                <div class="pprice-wrap">
                  <?php if ( $discount > 0 ) : ?><span class="pold"><?php echo wc_price( $regular ); ?></span><?php endif; ?>
                  <span class="pprice"><?php echo wc_price( $sale ); ?></span>
                </div>
                <?php if ( $discount > 0 ) : ?><span class="psave">%<?php echo esc_html( $discount ); ?></span><?php endif; ?>
            </div>
            <?php if ( $product->is_in_stock() && $sale >= 1500 ) : ?>
            <div class="pship pship-free-row">🚚 <span class="pship-free-lbl">Ücretsiz Kargo</span></div>
            <?php else : ?>
            <div class="pship"<?php echo $product->is_in_stock() ? ' data-pship="1"' : ''; ?>>🚚 <span class="pship-txt"><?php echo $product->is_in_stock() ? 'Kargo bilgisi hesaplanıyor…' : 'Stok Bekleniyor'; ?></span></div>
            <?php endif; ?>
            <div class="pfoot">
                <?php if ( $city ) : ?>
                <div class="ploc"><svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg><?php echo esc_html( $city ); ?></div>
                <?php else : ?><div class="ploc"></div><?php endif; ?>
                <?php if ( $product->is_type('variable') ) : ?>
                  <a class="pcart" href="<?php echo esc_url( $permalink ); ?>" onclick="event.stopPropagation();">Seçenekler</a>
                <?php elseif ( $product->is_in_stock() ) : ?>
                  <a class="pcart" href="?add-to-cart=<?php echo esc_attr( $id ); ?>" onclick="event.stopPropagation();" rel="nofollow">+ Sepet</a>
                <?php else : ?>
                  <a class="pcart pcart-out" href="<?php echo esc_url( $permalink ); ?>" onclick="event.stopPropagation();">Tükendi</a>
                <?php endif; ?>
            </div>
            <button class="pcomp-btn" onclick="event.stopPropagation();pzToggleComp(this)">
              <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 8L22 12L18 16M6 8L2 12L6 16M14 4L10 20"/></svg>
              <span class="pcomp-lbl">Karşılaştır</span>
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


/* ──────────────────────────────────────────────
   3) AJAX handler: kategoriye göre RANDOM ürün
────────────────────────────────────────────── */
function bazario_filter_products() {
    $category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : 'all';

    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => 12,
        'orderby'        => 'rand',          // RASTGELE
        'post_status'    => 'publish',
    );

    if ( $category && $category !== 'all' ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $category,
            ),
        );
    }

    $loop = new WP_Query( $args );

    if ( $loop->have_posts() ) {
        while ( $loop->have_posts() ) {
            $loop->the_post();
            global $product;
            echo bazario_product_card( $product );
        }
        wp_reset_postdata();
    } else {
        echo '<p style="grid-column:1/-1;text-align:center;color:#8a8680;padding:30px">Bu kategoride ürün bulunamadı.</p>';
    }

    wp_die(); // AJAX yanıtını sonlandır
}
add_action( 'wp_ajax_bazario_filter_products', 'bazario_filter_products' );        // giriş yapmış
add_action( 'wp_ajax_nopriv_bazario_filter_products', 'bazario_filter_products' ); // ziyaretçi


/* ──────────────────────────────────────────────
   4) Sol menü flyout banner'ları için kategori meta
   (Kategori düzenleme ekranına "banner" alanı eklenebilir;
    burada basit bir kategori-banner eşlemesi kullanıyoruz)
────────────────────────────────────────────── */
function bazario_category_banner( $slug ) {
    // Kategori başına banner görseli + metin (medya kütüphanesi ID'si veya URL)
    $banners = array(
        'elektronik'    => array('img'=>'/wp-content/uploads/banners/elektronik.jpg','title'=>'Teknoloji Fırsatları','sub'=>'%40\'a varan indirim'),
        'moda'          => array('img'=>'/wp-content/uploads/banners/moda.jpg','title'=>'Yeni Sezon Moda','sub'=>'Tarzını yansıt'),
        'ev-dekor'      => array('img'=>'/wp-content/uploads/banners/ev.jpg','title'=>'Evini Yenile','sub'=>'Dekorasyonda büyük indirim'),
        'el-yapimi'     => array('img'=>'/wp-content/uploads/banners/elyapimi.jpg','title'=>'El Emeği Eserler','sub'=>'Tek parça, özel tasarım'),
        'kitap-ve-hobi' => array('img'=>'/wp-content/uploads/banners/kitap.jpg','title'=>'Kitap & Hobi','sub'=>'Keşfetmeye başla'),
        'spor'          => array('img'=>'/wp-content/uploads/banners/spor.jpg','title'=>'Spor Sezonu','sub'=>'Formda kal'),
        'kozmetik'      => array('img'=>'/wp-content/uploads/banners/kozmetik.jpg','title'=>'Güzellik & Bakım','sub'=>'Kendine iyi bak'),
        'otomotiv'      => array('img'=>'/wp-content/uploads/banners/otomotiv.jpg','title'=>'Otomotiv Dünyası','sub'=>'Aracına en iyisi'),
        'anne-ve-bebek' => array('img'=>'/wp-content/uploads/banners/bebek.jpg','title'=>'Anne & Bebek','sub'=>'Minikler için en iyisi'),
        'muzik'         => array('img'=>'/wp-content/uploads/banners/muzik.jpg','title'=>'Müzik Tutkusu','sub'=>'Enstrümanlar & ekipman'),
    );
    return isset( $banners[ $slug ] ) ? $banners[ $slug ] : null;
}


/* ──────────────────────────────────────────────
   5) Sepete ekleyince mini sepet sayacını AJAX ile güncelle
   (sayfa yenilemeden header rozetini günceller)
────────────────────────────────────────────── */
function bazario_add_to_cart_fragments( $fragments ) {
    $count = WC()->cart->get_cart_contents_count();
    ob_start();
    ?><span class="cbadge" id="hCartCount"><?php echo esc_html( $count ); ?></span><?php
    $fragments['#hCartCount'] = ob_get_clean();
    return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'bazario_add_to_cart_fragments' );


/* ──────────────────────────────────────────────
   "Hemen Al" — sepete ekledikten sonra ödemeye yönlendir
────────────────────────────────────────────── */
add_filter( 'woocommerce_add_to_cart_redirect', function ( $url ) {
    if ( isset( $_REQUEST['hb_buy_now'] ) && $_REQUEST['hb_buy_now'] === '1' ) {
        return wc_get_checkout_url();
    }
    return $url;
} );


/* ──────────────────────────────────────────────
   Shop/Kategori filtreleri: fiyat, puan, kargo
────────────────────────────────────────────── */
add_action( 'woocommerce_product_query', function ( $q ) {
    if ( is_admin() ) return;

    $meta_query = $q->get( 'meta_query' ) ?: array();

    // Fiyat aralığı
    $min = isset( $_GET['min_price'] ) && $_GET['min_price'] !== '' ? floatval( $_GET['min_price'] ) : null;
    $max = isset( $_GET['max_price'] ) && $_GET['max_price'] !== '' ? floatval( $_GET['max_price'] ) : null;
    if ( $min !== null || $max !== null ) {
        $price_q = array( 'key' => '_price', 'type' => 'NUMERIC' );
        if ( $min !== null && $max !== null ) {
            $price_q['value']   = array( $min, $max );
            $price_q['compare'] = 'BETWEEN';
        } elseif ( $min !== null ) {
            $price_q['value']   = $min;
            $price_q['compare'] = '>=';
        } else {
            $price_q['value']   = $max;
            $price_q['compare'] = '<=';
        }
        $meta_query[] = $price_q;
    }

    // Puan filtresi
    if ( isset( $_GET['min_rating'] ) && $_GET['min_rating'] !== '' ) {
        $meta_query[] = array(
            'key'     => '_wc_average_rating',
            'value'   => floatval( $_GET['min_rating'] ),
            'compare' => '>=',
            'type'    => 'DECIMAL',
        );
    }

    if ( ! empty( $meta_query ) ) {
        $q->set( 'meta_query', $meta_query );
    }

    // Özellik filtreleri (renk, beden, vb.) — filter_xxx parametreleri
    $tax_query = $q->get( 'tax_query' ) ?: array();
    $attr_taxes = wc_get_attribute_taxonomies();
    if ( ! empty( $attr_taxes ) ) {
        foreach ( $attr_taxes as $at ) {
            $key = 'filter_' . $at->attribute_name;
            if ( ! empty( $_GET[ $key ] ) ) {
                $vals = array_map( 'sanitize_title', explode( ',', wp_unslash( $_GET[ $key ] ) ) );
                $tax_query[] = array(
                    'taxonomy' => wc_attribute_taxonomy_name( $at->attribute_name ),
                    'field'    => 'slug',
                    'terms'    => $vals,
                    'operator' => 'IN',
                );
            }
        }
    }
    if ( ! empty( $tax_query ) ) {
        $q->set( 'tax_query', $tax_query );
    }

    // Kategori/arşiv ürünleri RANDOM gelsin (kullanıcı özel sıralama seçmediyse)
    // orderby seçenekleri WooCommerce sıralama dropdown'undan gelir (?orderby=...)
    $user_orderby = isset( $_GET['orderby'] ) ? wc_clean( wp_unslash( $_GET['orderby'] ) ) : '';
    if ( empty( $user_orderby ) || $user_orderby === 'menu_order' ) {
        // Sayfa başına tutarlı bir random tohum (aynı oturumda sayfalama bozulmasın)
        $q->set( 'orderby', 'rand' );
    }
} );

// Random sıralamada her sayfa yüklemesinde tutarlı tohum kullan (sayfalama için)
add_filter( 'posts_orderby', function ( $orderby, $q ) {
    if ( is_admin() || ! $q->is_main_query() ) return $orderby;
    if ( ( is_shop() || is_product_category() || is_product_tag() || is_tax('product_brand') ) ) {
        $user_orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : '';
        if ( empty( $user_orderby ) || $user_orderby === 'menu_order' ) {
            // Günlük tohum: aynı gün içinde sıralama tutarlı (sayfalar arası tekrar/atlama olmaz)
            $seed = isset( $_GET['rseed'] ) ? absint( $_GET['rseed'] ) : intval( date('Ymd') );
            return 'RAND(' . $seed . ')';
        }
    }
    return $orderby;
}, 10, 2 );

// Shop sayfasında sayfa başına ürün sayısı (Hepsiburada gibi bol)
add_filter( 'loop_shop_per_page', function () { return 24; }, 20 );


/* ──────────────────────────────────────────────
   Menü kategorileri: "Diğer" kategorisini gizle + boş/stoksuz gizle
────────────────────────────────────────────── */
// "Diğer" / "Genel" gibi kategorileri menülerden çıkar (ID'lerini bul)
function pazaryeri_hidden_cat_ids() {
    $hidden = array();
    $skip_names = array( 'diğer', 'diger', 'kategori yok', 'uncategorized', 'genel' );
    $all = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'all' ) );
    if ( ! empty( $all ) && ! is_wp_error( $all ) ) {
        foreach ( $all as $t ) {
            if ( in_array( mb_strtolower( $t->name ), $skip_names, true ) ) {
                $hidden[] = $t->term_id;
            }
        }
    }
    // varsayılan kategori de gizli
    $def = get_option( 'default_product_cat' );
    if ( $def ) $hidden[] = (int) $def;
    return array_unique( $hidden );
}

// Tüm product_cat sorgularına otomatik uygula: "Diğer"i + boş kategorileri hariç tut
add_filter( 'get_terms_args', function ( $args, $taxonomies ) {
    if ( is_admin() ) return $args;
    if ( ! in_array( 'product_cat', (array) $taxonomies, true ) ) return $args;
    // Sonsuz döngüyü önle
    static $running = false;
    if ( $running ) return $args;
    $running = true;
    $hidden = pazaryeri_hidden_cat_ids();
    $running = false;
    if ( ! empty( $hidden ) ) {
        $existing = isset( $args['exclude'] ) ? (array) $args['exclude'] : array();
        $args['exclude'] = array_unique( array_merge( $existing, $hidden ) );
    }
    // Ürün sayısı 0 olanları kesin gizle (frontend menülerde)
    $args['hide_empty'] = true;
    return $args;
}, 10, 2 );

// Term listesinden ürün sayısı (kendi + alt kategoriler dahil) 0 olanları temizle
add_filter( 'get_terms', function ( $terms, $taxonomies, $query_vars ) {
    if ( is_admin() ) return $terms;
    if ( ! in_array( 'product_cat', (array) $taxonomies, true ) ) return $terms;
    if ( empty( $terms ) ) return $terms;
    // fields=ids veya names ise dokunma (sadece term nesnelerinde filtrele)
    $filtered = array();
    foreach ( $terms as $t ) {
        if ( ! is_object( $t ) || ! isset( $t->count ) ) { $filtered[] = $t; continue; }
        // Kendi ürün sayısı > 0 ise göster
        if ( $t->count > 0 ) { $filtered[] = $t; continue; }
        // Alt kategorilerinde ürün var mı kontrol et
        $has_in_children = false;
        $children = get_term_children( $t->term_id, 'product_cat' );
        if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
            foreach ( $children as $child_id ) {
                $child = get_term( $child_id, 'product_cat' );
                if ( $child && ! is_wp_error( $child ) && $child->count > 0 ) {
                    $has_in_children = true;
                    break;
                }
            }
        }
        if ( $has_in_children ) $filtered[] = $t;
    }
    return $filtered;
}, 10, 3 );


/* ──────────────────────────────────────────────
   HIZLI filtre yardımcıları (tek SQL ile terim toplama)
   N+1 sorgu problemini önler — kategori filtreleme hızlanır
────────────────────────────────────────────── */
// Bir kategorideki (+ alt kategoriler) yayında ürün ID'lerini cache'li getir
function pazaryeri_cat_product_ids( $term_id ) {
    static $cache = array();
    if ( isset( $cache[ $term_id ] ) ) return $cache[ $term_id ];

    $term_ids = array_merge( array( $term_id ), get_term_children( $term_id, 'product_cat' ) );
    $term_ids = array_map( 'intval', $term_ids );

    $q = new WP_Query( array(
        'post_type'              => 'product',
        'post_status'            => 'publish',
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'tax_query'              => array( array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $term_ids,
        ) ),
    ) );
    $ids = $q->have_posts() ? $q->posts : array();
    wp_reset_postdata();
    $cache[ $term_id ] = $ids;
    return $ids;
}

// Verilen ürün ID'lerinin belirli taksonomideki terim ID'lerini TEK SQL ile getir
function pazaryeri_terms_for_products( $product_ids, $taxonomy ) {
    global $wpdb;
    if ( empty( $product_ids ) ) return array();
    $ids_in = implode( ',', array_map( 'intval', $product_ids ) );
    $tax = esc_sql( $taxonomy );
    $sql = "SELECT DISTINCT tt.term_id
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE tt.taxonomy = %s
              AND tr.object_id IN ($ids_in)";
    $results = $wpdb->get_col( $wpdb->prepare( $sql, $tax ) );
    return array_map( 'intval', $results );
}


/* ──────────────────────────────────────────────
   Ürün Soru & Cevap sistemi (dinamik)
────────────────────────────────────────────── */
// Soru gönderme AJAX (giriş yapmış + misafir)
add_action( 'wp_ajax_pz_submit_question', 'pz_handle_question' );
add_action( 'wp_ajax_nopriv_pz_submit_question', 'pz_handle_question' );
function pz_handle_question() {
    check_ajax_referer( 'pazaryeri_nonce', 'nonce' );

    $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    $name       = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
    $question   = isset( $_POST['question'] ) ? sanitize_textarea_field( wp_unslash( $_POST['question'] ) ) : '';

    if ( ! $product_id || get_post_type( $product_id ) !== 'product' ) {
        wp_send_json_error( array( 'message' => 'Geçersiz ürün.' ) );
    }
    if ( mb_strlen( $question ) < 5 ) {
        wp_send_json_error( array( 'message' => 'Lütfen en az 5 karakterlik bir soru yazın.' ) );
    }
    if ( empty( $name ) ) $name = 'Misafir';

    // Giriş yapmışsa kullanıcı bilgisi
    $user_id = get_current_user_id();
    $email   = '';
    if ( $user_id ) {
        $u = wp_get_current_user();
        if ( empty( $name ) || $name === 'Misafir' ) $name = $u->display_name;
        $email = $u->user_email;
    }

    $comment_data = array(
        'comment_post_ID'      => $product_id,
        'comment_author'       => $name,
        'comment_author_email' => $email,
        'comment_content'      => $question,
        'comment_type'         => 'pz_question',
        'comment_approved'     => 0, // moderasyon: yönetici onaylayana kadar bekler
        'user_id'              => $user_id,
    );
    $cid = wp_insert_comment( $comment_data );

    if ( $cid ) {
        wp_send_json_success( array( 'message' => 'Sorunuz alındı! Onaylandıktan sonra yayınlanacak ve yanıtlanacaktır.' ) );
    } else {
        wp_send_json_error( array( 'message' => 'Soru gönderilemedi, lütfen tekrar deneyin.' ) );
    }
}

// Soru-cevap yorumlarını WooCommerce yorum sayısından ve normal yorum akışından ayır
add_filter( 'comments_clauses', function ( $clauses, $query ) {
    if ( is_admin() ) return $clauses;
    // Ürün yorum (değerlendirme) sorgularında pz_question'ları hariç tut
    if ( ! empty( $query->query_vars['type'] ) && $query->query_vars['type'] === 'pz_question' ) {
        return $clauses; // soru sorgusu — dokunma
    }
    return $clauses;
}, 10, 2 );

// Admin'de soru-cevap yönetimi: yorum satırına cevap alanı (basit meta box yerine hızlı çözüm)
// Yönetici, yorumu onaylar + cevabı comment meta 'pz_answer' olarak ekler.
// Admin yorum düzenleme ekranında cevap alanı:
add_action( 'add_meta_boxes_comment', function () {
    add_meta_box( 'pz_answer_box', 'Soruya Cevap (724PazarYeri)', 'pz_answer_meta_box', 'comment', 'normal', 'high' );
} );
function pz_answer_meta_box( $comment ) {
    if ( $comment->comment_type !== 'pz_question' ) {
        echo '<p>Bu yorum bir ürün sorusu değil.</p>';
        return;
    }
    $answer = get_comment_meta( $comment->comment_ID, 'pz_answer', true );
    wp_nonce_field( 'pz_answer_save', 'pz_answer_nonce' );
    echo '<p><label for="pz_answer">Müşteriye gösterilecek cevap:</label></p>';
    echo '<textarea name="pz_answer" id="pz_answer" rows="4" style="width:100%;">' . esc_textarea( $answer ) . '</textarea>';
    echo '<p style="color:#666;font-size:12px;">Cevabı yazıp yorumu onayladığınızda ürün sayfasında görünür.</p>';
}
add_action( 'edit_comment', function ( $comment_id ) {
    if ( ! isset( $_POST['pz_answer_nonce'] ) || ! wp_verify_nonce( $_POST['pz_answer_nonce'], 'pz_answer_save' ) ) return;
    if ( isset( $_POST['pz_answer'] ) ) {
        update_comment_meta( $comment_id, 'pz_answer', sanitize_textarea_field( wp_unslash( $_POST['pz_answer'] ) ) );
    }
} );


/* ──────────────────────────────────────────────
   Satıcı/Yönetici soruya cevap verme (ürün sayfasından AJAX)
────────────────────────────────────────────── */
// Bir kullanıcının bu ürünün sorularını cevaplama yetkisi var mı?
function pz_can_answer_product( $product_id ) {
    if ( current_user_can( 'manage_options' ) ) return true; // yönetici
    if ( current_user_can( 'manage_woocommerce' ) ) return true; // mağaza yöneticisi
    $user_id = get_current_user_id();
    if ( ! $user_id ) return false;
    // Ürünün yazarı (Dokan satıcısı genelde post_author olur)
    $author = (int) get_post_field( 'post_author', $product_id );
    if ( $author === (int) $user_id ) return true;
    // Dokan: ürünün satıcısı mı?
    if ( function_exists( 'dokan_get_vendor_by_product' ) ) {
        $vendor = dokan_get_vendor_by_product( $product_id );
        if ( $vendor && (int) $vendor->get_id() === (int) $user_id ) return true;
    }
    return false;
}

add_action( 'wp_ajax_pz_submit_answer', 'pz_handle_answer' );
function pz_handle_answer() {
    check_ajax_referer( 'pazaryeri_nonce', 'nonce' );

    $comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
    $answer     = isset( $_POST['answer'] ) ? sanitize_textarea_field( wp_unslash( $_POST['answer'] ) ) : '';

    $comment = get_comment( $comment_id );
    if ( ! $comment || $comment->comment_type !== 'pz_question' ) {
        wp_send_json_error( array( 'message' => 'Geçersiz soru.' ) );
    }
    $product_id = (int) $comment->comment_post_ID;

    // Yetki kontrolü
    if ( ! pz_can_answer_product( $product_id ) ) {
        wp_send_json_error( array( 'message' => 'Bu soruyu yanıtlama yetkiniz yok.' ) );
    }
    if ( mb_strlen( $answer ) < 2 ) {
        wp_send_json_error( array( 'message' => 'Lütfen bir cevap yazın.' ) );
    }

    update_comment_meta( $comment_id, 'pz_answer', $answer );
    // Cevaplayan bilgisi
    $u = wp_get_current_user();
    update_comment_meta( $comment_id, 'pz_answered_by', $u->display_name );
    update_comment_meta( $comment_id, 'pz_answered_date', current_time( 'mysql' ) );

    wp_send_json_success( array(
        'message'      => 'Cevabınız kaydedildi.',
        'answer'       => $answer,
        'answered_by'  => $u->display_name,
    ) );
}


/* ──────────────────────────────────────────────
   Dokan: tema içindeki özel store.php şablonunu kullan
────────────────────────────────────────────── */
// Dokan'a tema klasöründeki şablonu önceliklendirmesini söyle
add_filter( 'dokan_get_template_part', function ( $template, $slug, $name ) {
    if ( $slug === 'store' || ( $slug === 'global' && $name === 'store' ) ) {
        $theme_tpl = PAZARYERI_DIR . '/dokan/store.php';
        if ( file_exists( $theme_tpl ) ) return $theme_tpl;
    }
    return $template;
}, 10, 3 );

// Dokan'ın template arama yoluna tema dokan/ klasörünü ekle
add_filter( 'dokan_set_template_path', function ( $template_path, $template, $args ) {
    return $template_path;
}, 10, 3 );


/* ──────────────────────────────────────────────
   Satıcı ol / Mağaza aç linki — Dokan'ın doğru sayfasına yönlendir
────────────────────────────────────────────── */
function pazaryeri_become_seller_url() {
    // 1) Dokan satıcı kayıt sayfası ayarı
    if ( function_exists( 'dokan_get_page_url' ) ) {
        // Giriş yapmış ve satıcıysa → satıcı paneli
        if ( is_user_logged_in() && function_exists( 'dokan_is_user_seller' ) && dokan_is_user_seller( get_current_user_id() ) ) {
            $dash = dokan_get_page_url( 'dashboard', 'dokan' );
            if ( $dash ) return $dash;
        }
        // Satıcı kayıt sayfası (Dokan ayarlarında tanımlı)
        $reg = dokan_get_page_url( 'myaccount', 'woocommerce' );
        if ( $reg ) return add_query_arg( 'action', 'register', $reg );
    }
    // 2) WooCommerce hesap sayfası (kayıt için)
    if ( function_exists( 'wc_get_page_permalink' ) ) {
        $my = wc_get_page_permalink( 'myaccount' );
        if ( $my ) return $my;
    }
    // 3) Fallback
    return home_url( '/magaza-ac/' );
}


/* ───────────────────────────────────────────────
   AJAX: Son gezilen ürünleri kart olarak döndür
   POST: ids[] (ürün ID dizisi), nonce
─────────────────────────────────────────────── */
add_action( 'wp_ajax_pz_recent_products',        'pz_ajax_recent_products' );
add_action( 'wp_ajax_nopriv_pz_recent_products', 'pz_ajax_recent_products' );
function pz_ajax_recent_products() {
    check_ajax_referer( 'pazaryeri_nonce', 'nonce' );
    $ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
    $ids = array_filter( array_unique( $ids ) );
    $ids = array_slice( $ids, 0, 12 );
    if ( empty( $ids ) ) {
        wp_send_json_success( array( 'html' => '' ) );
    }
    $q = new WP_Query( array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => count( $ids ),
        'post__in'       => $ids,
        'orderby'        => 'post__in',
        'ignore_sticky_posts' => true,
    ) );
    $html = '';
    if ( $q->have_posts() ) {
        while ( $q->have_posts() ) {
            $q->the_post();
            global $product;
            $html .= bazario_product_card( $product );
        }
        wp_reset_postdata();
    }
    wp_send_json_success( array( 'html' => $html, 'count' => $q->found_posts ) );
}


/* ───────────────────────────────────────────────
   Son Gezilen Ürünler — yeniden kullanılabilir HTML şablonu
─────────────────────────────────────────────── */
function pz_recently_viewed_block( $title = '🕐 Son Gezdiğin Ürünler', $exclude_current = false ) {
    $cur_id = ( $exclude_current && is_product() ) ? get_the_ID() : 0;
    ob_start();
    ?>
    <div class="cw" id="recentlyViewedWrap_<?php echo wp_unique_id(); ?>" data-rv-wrap="1" data-rv-exclude="<?php echo esc_attr( $cur_id ); ?>" style="display:none;">
      <div class="recent-products">
        <div class="recent-products-head">
          <h2 class="recent-products-title"><?php echo esc_html( $title ); ?></h2>
          <button type="button" class="recent-clear" onclick="pzClearRecent()" aria-label="Temizle">Temizle</button>
        </div>
        <div class="recent-products-row" data-rv-row="1"></div>
      </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'pazaryeri_recent', function( $atts ){
    $a = shortcode_atts( array( 'title' => '🕐 Son Gezdiğin Ürünler', 'exclude_current' => '0' ), $atts );
    return pz_recently_viewed_block( $a['title'], $a['exclude_current'] === '1' );
} );


/* ───────────────────────────────────────────────
   AKILLI ARAMA — AJAX endpoint
   Ürün + Kategori + Marka + Satıcı + Eş anlamlı + Yazım hatası
─────────────────────────────────────────────── */

// Eş anlamlı sözlük (Türkçe e-ticaret)
function pz_ai_synonyms() {
    return array(
        'ayakabı'=>'ayakkabı','ayakkbı'=>'ayakkabı','ayakabi'=>'ayakkabı',
        'mont'=>'ceket','kaban'=>'ceket','palto'=>'ceket',
        'tişört'=>'tshirt','tisort'=>'tshirt','t-shirt'=>'tshirt',
        'pantalon'=>'pantolon','pantolun'=>'pantolon',
        'cüzdan'=>'cüzdan','cuzdan'=>'cüzdan',
        'telefon'=>'cep telefonu','cep'=>'cep telefonu','akıllı telefon'=>'cep telefonu',
        'bilgisayar'=>'laptop notebook bilgisayar','dizüstü'=>'laptop','notebok'=>'notebook',
        'kulaklık'=>'kulaklik headphone','kulaklik'=>'kulaklık',
        'çanta'=>'çanta canta','canta'=>'çanta',
        'gözlük'=>'gözlük gozluk','gozluk'=>'gözlük',
        'kitap'=>'kitap roman','spor'=>'spor fitness',
        'kozmetik'=>'kozmetik makyaj','makyaj'=>'makyaj kozmetik',
        'mutfak'=>'mutfak ev','ev'=>'ev dekorasyon',
        'parfüm'=>'parfüm parfum','parfum'=>'parfüm',
        'saat'=>'saat kol saati','kol saati'=>'saat',
    );
}

// Türkçe karakter normalize + lowercase
function pz_ai_normalize( $s ) {
    $s = mb_strtolower( $s, 'UTF-8' );
    $tr = array('ı'=>'i','ğ'=>'g','ü'=>'u','ş'=>'s','ö'=>'o','ç'=>'c','İ'=>'i','Ğ'=>'g','Ü'=>'u','Ş'=>'s','Ö'=>'o','Ç'=>'c');
    return strtr( $s, $tr );
}

add_action( 'wp_ajax_pz_ai_search',        'pz_ajax_ai_search' );
add_action( 'wp_ajax_nopriv_pz_ai_search', 'pz_ajax_ai_search' );
function pz_ajax_ai_search() {
    check_ajax_referer( 'pazaryeri_nonce', 'nonce' );
    $q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
    $q = trim( $q );
    if ( mb_strlen( $q ) < 2 ) {
        // Boş veya çok kısa — popüler aramalar dön
        wp_send_json_success( array(
            'products' => array(),
            'categories' => array(),
            'brands' => array(),
            'popular' => pz_ai_popular_searches(),
        ) );
    }

    // Eş anlamlı genişletme + yazım hatası
    $syn = pz_ai_synonyms();
    $q_norm = pz_ai_normalize( $q );
    $q_expanded = $q;
    foreach ( $syn as $bad => $good ) {
        if ( pz_ai_normalize( $bad ) === $q_norm ) {
            $q_expanded = $good;
            break;
        }
    }

    // Cache key (5 dakika)
    $cache_key = 'pz_ai_' . md5( $q_norm );
    $cached = get_transient( $cache_key );
    if ( $cached !== false ) {
        wp_send_json_success( $cached );
    }

    $results = array(
        'products' => array(),
        'categories' => array(),
        'brands' => array(),
        'popular' => array(),
        'query' => $q,
        'expanded' => $q_expanded,
    );

    // 1. ÜRÜNLER — başlık + içerik + sku ara
    $product_q = new WP_Query( array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 6,
        's'              => $q_expanded,
        'no_found_rows'  => true,
        'update_post_term_cache' => false,
    ) );
    if ( $product_q->have_posts() ) {
        while ( $product_q->have_posts() ) {
            $product_q->the_post();
            $p = wc_get_product( get_the_ID() );
            if ( ! $p ) continue;
            $img_id = $p->get_image_id();
            $img = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );
            $results['products'][] = array(
                'id'    => $p->get_id(),
                'title' => $p->get_name(),
                'url'   => get_permalink( $p->get_id() ),
                'img'   => $img,
                'price' => $p->get_price_html(),
            );
        }
        wp_reset_postdata();
    }

    // 2. KATEGORİLER — name LIKE
    global $wpdb;
    $like = '%' . $wpdb->esc_like( $q_expanded ) . '%';
    $cat_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT t.term_id, t.name, t.slug
         FROM {$wpdb->terms} t
         INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
         WHERE tt.taxonomy = 'product_cat' AND t.name LIKE %s AND tt.count > 0
         ORDER BY tt.count DESC LIMIT 5",
        $like
    ) );
    if ( $cat_rows ) {
        foreach ( $cat_rows as $cat ) {
            $term_obj = get_term( $cat->term_id, 'product_cat' );
            if ( ! $term_obj || is_wp_error( $term_obj ) ) continue;
            $results['categories'][] = array(
                'name' => $cat->name,
                'url'  => get_term_link( $term_obj ),
            );
        }
    }

    // 3. MARKALAR — varsa product_brand taxonomy
    if ( taxonomy_exists( 'product_brand' ) ) {
        $brand_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.term_id, t.name FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             WHERE tt.taxonomy = 'product_brand' AND t.name LIKE %s AND tt.count > 0
             ORDER BY tt.count DESC LIMIT 4",
            $like
        ) );
        if ( $brand_rows ) {
            foreach ( $brand_rows as $br ) {
                $tm = get_term( $br->term_id, 'product_brand' );
                if ( $tm && ! is_wp_error( $tm ) ) {
                    $results['brands'][] = array( 'name' => $br->name, 'url' => get_term_link( $tm ) );
                }
            }
        }
    }

    // Cache 5 dakika
    set_transient( $cache_key, $results, 5 * MINUTE_IN_SECONDS );

    wp_send_json_success( $results );
}

// Popüler aramalar (statik — sonra dinamik yapılabilir)
function pz_ai_popular_searches() {
    return array(
        'Ayakkabı','Çanta','T-shirt','Telefon','Kulaklık','Saat','Parfüm','Kozmetik','Elbise','Pantolon'
    );
}
