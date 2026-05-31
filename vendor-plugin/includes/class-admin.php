<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class PZV_Admin {
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
        // Ürün edit ekranında "Satıcı (Vendor) Seç" alanı
        add_action( 'add_meta_boxes', array( $this, 'product_vendor_metabox' ) );
        add_action( 'save_post_product', array( $this, 'save_product_vendor' ), 20, 2 );
        // Ürün listesinde "Satıcı" kolonu
        add_filter( 'manage_product_posts_columns', array( $this, 'product_column_vendor' ) );
        add_action( 'manage_product_posts_custom_column', array( $this, 'product_column_vendor_content' ), 10, 2 );
        // Vendor için admin kısıtlamaları
        add_action( 'pre_get_posts', array( $this, 'restrict_products_to_vendor' ) );
        add_filter( 'request', array( $this, 'restrict_orders_to_vendor' ) );
        add_action( 'admin_menu', array( $this, 'restrict_vendor_menu' ), 999 );
        // Kategori komisyon alanı
        add_action( 'product_cat_edit_form_fields', array( $this, 'category_commission_field' ), 20, 2 );
        add_action( 'edited_product_cat',           array( $this, 'save_category_commission' ) );
        add_action( 'product_cat_add_form_fields',  array( $this, 'category_commission_add_field' ) );
        add_action( 'created_product_cat',          array( $this, 'save_category_commission' ) );
        // seller_apply CPT'sine "Vendor Yap" butonu
        add_filter( 'post_row_actions', array( $this, 'seller_apply_row_action' ), 10, 2 );
    }

    public function menu() {
        add_menu_page( 'Satıcılar', '🏪 Satıcılar', 'manage_woocommerce', 'pzv-vendors', array( $this, 'page_vendors' ), 'dashicons-store', 25 );
        add_submenu_page( 'pzv-vendors', 'Satıcı Listesi', 'Satıcı Listesi', 'manage_woocommerce', 'pzv-vendors', array( $this, 'page_vendors' ) );
        add_submenu_page( 'pzv-vendors', 'Bekleyen Ödemeler', 'Bekleyen Ödemeler', 'manage_woocommerce', 'pzv-payouts', array( $this, 'page_payouts' ) );
        add_submenu_page( 'pzv-vendors', 'Ayarlar', 'Ayarlar', 'manage_woocommerce', 'pzv-settings', array( $this, 'page_settings' ) );
        if ( PZV_Roles::is_vendor() ) {
            add_menu_page( 'Mağaza Bilgilerim', '🏪 Mağazam', 'read', 'pzv-vendor-info', array( $this, 'page_vendor_info' ), 'dashicons-store', 3 );
        }
    }

    public function enqueue( $hook ) {
        // Tüm admin sayfalarında yükle (hafif dosyalar, problem yok)
        wp_enqueue_style( 'pzv-admin', PZV_URL . 'assets/admin.css', array(), PZV_VERSION );
        wp_enqueue_script( 'pzv-admin', PZV_URL . 'assets/admin.js', array(), PZV_VERSION, true );
        wp_localize_script( 'pzv-admin', 'pzv', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pzv_nonce' ),
        ) );
    }

    public function restrict_products_to_vendor( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) return;
        if ( ! PZV_Roles::is_pure_vendor() ) return;
        global $pagenow;
        if ( $pagenow !== 'edit.php' ) return;
        if ( ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== 'product' ) return;
        $query->set( 'author', get_current_user_id() );
    }

    public function restrict_orders_to_vendor( $vars ) {
        if ( ! is_admin() ) return $vars;
        if ( ! PZV_Roles::is_pure_vendor() ) return $vars;
        if ( ! isset( $_GET['post_type'] ) || $_GET['post_type'] !== 'shop_order' ) return $vars;
        $order_ids = PZV_Vendor::get_order_ids( get_current_user_id() );
        $vars['post__in'] = empty( $order_ids ) ? array( 0 ) : $order_ids;
        return $vars;
    }

    public function restrict_vendor_menu() {
        if ( ! PZV_Roles::is_pure_vendor() ) return;
        global $menu;
        $allowed = array( 'index.php', 'edit.php?post_type=product', 'edit.php?post_type=shop_order', 'upload.php', 'profile.php', 'pzv-vendor-info' );
        foreach ( $menu as $key => $item ) {
            if ( ! isset( $item[2] ) ) continue;
            $is_allowed = false;
            foreach ( $allowed as $a ) {
                if ( strpos( $item[2], $a ) === 0 ) { $is_allowed = true; break; }
            }
            if ( ! $is_allowed ) unset( $menu[ $key ] );
        }
    }

    /** ─── SAYFA: Satıcı Listesi ─── */
    public function page_vendors() {
        $vendors = PZV_Vendor::get_all();
        ?>
        <div class="wrap pzv-wrap">
            <h1>🏪 Satıcılar (<?php echo count( $vendors ); ?>)</h1>
            <p>Onay bekleyen başvurular: <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=seller_apply' ) ); ?>">Satıcı Başvuruları</a></p>
            <table class="wp-list-table widefat striped">
                <thead><tr>
                    <th>ID</th><th>Mağaza</th><th>Ad Soyad</th><th>E-posta</th><th>Şehir</th>
                    <th>Komisyon %</th><th>Toplam Satış</th><th>Bekleyen</th><th>İşlem</th>
                </tr></thead>
                <tbody>
                <?php if ( empty( $vendors ) ) : ?>
                    <tr><td colspan="9">Henüz onaylı satıcı yok.</td></tr>
                <?php else : foreach ( $vendors as $u ) :
                    $v = PZV_Vendor::get( $u->ID ); if ( ! $v ) continue;
                    ?>
                    <tr>
                        <td><?php echo (int) $v['id']; ?></td>
                        <td><strong><?php echo esc_html( $v['store_name'] ); ?></strong></td>
                        <td><?php echo esc_html( $v['display_name'] ); ?></td>
                        <td><a href="mailto:<?php echo esc_attr( $v['email'] ); ?>"><?php echo esc_html( $v['email'] ); ?></a></td>
                        <td><?php echo esc_html( $v['city'] ?: '-' ); ?></td>
                        <td>
                            <input type="number" step="0.5" min="0" max="100" class="pzv-commission-input small-text" data-vendor="<?php echo (int) $v['id']; ?>" value="<?php echo esc_attr( $v['commission_override'] ); ?>" placeholder="Default">
                        </td>
                        <td><?php echo wc_price( PZV_Commission::vendor_total_sales( $u->ID ) ); ?></td>
                        <td><strong><?php echo wc_price( PZV_Commission::vendor_pending( $u->ID ) ); ?></strong></td>
                        <td><a class="button button-small" href="<?php echo esc_url( get_edit_user_link( $v['id'] ) ); ?>">Düzenle</a></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /** ─── SAYFA: Bekleyen Ödemeler ─── */
    public function page_payouts() {
        $payouts = PZV_Commission::pending_payouts();
        ?>
        <div class="wrap pzv-wrap">
            <h1>💰 Bekleyen Ödemeler</h1>
            <p>Bir vendor için "Ödendi" tıkladığınızda o vendor'ın tüm bekleyen komisyon kayıtları kapatılır. Banka havalesini elden yaptıktan sonra işaretleyin.</p>
            <table class="wp-list-table widefat striped">
                <thead><tr><th>Satıcı</th><th>Bekleyen</th><th>Satış Sayısı</th><th>IBAN</th><th>İşlem</th></tr></thead>
                <tbody>
                <?php if ( empty( $payouts ) ) : ?>
                    <tr><td colspan="5">Bekleyen ödeme yok.</td></tr>
                <?php else : foreach ( $payouts as $p ) :
                    $v = PZV_Vendor::get( $p->vendor_id ); if ( ! $v ) continue;
                    ?>
                    <tr data-vendor="<?php echo (int) $p->vendor_id; ?>">
                        <td><strong><?php echo esc_html( $v['store_name'] ); ?></strong><br><small><?php echo esc_html( $v['email'] ); ?></small></td>
                        <td><strong style="color:#d9822b;font-size:16px;"><?php echo wc_price( $p->total ); ?></strong></td>
                        <td><?php echo (int) $p->items; ?></td>
                        <td><code><?php echo esc_html( $v['iban'] ?: '—' ); ?></code></td>
                        <td><button class="button button-primary pzv-mark-paid" data-vendor="<?php echo (int) $p->vendor_id; ?>">✓ Ödendi</button></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /** ─── SAYFA: Ayarlar ─── */
    public function page_settings() {
        if ( isset( $_POST['pzv_save_settings'] ) && check_admin_referer( 'pzv_settings' ) ) {
            update_option( 'pzv_default_commission', floatval( $_POST['default_commission'] ) );
            update_option( 'pzv_min_payout', floatval( $_POST['min_payout'] ) );
            echo '<div class="notice notice-success"><p>Kaydedildi.</p></div>';
        }
        $default = get_option( 'pzv_default_commission', 10 );
        $min_payout = get_option( 'pzv_min_payout', 0 );
        ?>
        <div class="wrap pzv-wrap">
            <h1>⚙️ Ayarlar</h1>
            <form method="post">
                <?php wp_nonce_field( 'pzv_settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label>Varsayılan Komisyon (%)</label></th>
                        <td><input type="number" name="default_commission" step="0.5" min="0" max="100" value="<?php echo esc_attr( $default ); ?>" class="small-text">
                        <p class="description">Kategori veya satıcıya özel oran yoksa bu kullanılır.</p></td>
                    </tr>
                    <tr>
                        <th><label>Minimum Ödeme Tutarı (₺)</label></th>
                        <td><input type="number" name="min_payout" min="0" step="10" value="<?php echo esc_attr( $min_payout ); ?>" class="small-text">
                        <p class="description">0 = sınır yok</p></td>
                    </tr>
                </table>
                <h3>💡 Komisyon Sırası</h3>
                <ol style="background:#fff;padding:14px 30px;border-left:4px solid #2271b1;">
                    <li>Satıcı bazlı özel oran (satıcı listesinde girildiyse)</li>
                    <li>Kategori bazlı oran (Ürünler → Kategoriler içinde girilir, birden fazla varsa en yüksek)</li>
                    <li>Varsayılan oran (yukarıda)</li>
                </ol>
                <p><button type="submit" name="pzv_save_settings" class="button button-primary">💾 Kaydet</button></p>
            </form>
        </div>
        <?php
    }

    /** ─── SAYFA (vendor): Mağaza Bilgilerim ─── */
    public function page_vendor_info() {
        $uid = get_current_user_id();
        if ( ! PZV_Roles::is_vendor( $uid ) ) return;
        if ( isset( $_POST['pzv_save_info'] ) && check_admin_referer( 'pzv_vendor_info' ) ) {
            foreach ( array('store_name','phone','city','address','description','iban') as $f ) {
                update_user_meta( $uid, 'pzv_' . $f, sanitize_text_field( wp_unslash( $_POST[$f] ?? '' ) ) );
            }
            if ( ! empty( $_POST['store_slug'] ) ) {
                update_user_meta( $uid, 'pzv_store_slug', sanitize_title( $_POST['store_slug'] ) );
            }
            echo '<div class="notice notice-success"><p>Kaydedildi.</p></div>';
        }
        $v = PZV_Vendor::get( $uid );
        ?>
        <div class="wrap pzv-wrap">
            <h1>🏪 Mağaza Bilgilerim</h1>
            <form method="post">
                <?php wp_nonce_field( 'pzv_vendor_info' ); ?>
                <table class="form-table">
                    <tr><th>Mağaza Adı</th><td><input type="text" name="store_name" value="<?php echo esc_attr( $v['store_name'] ); ?>" class="regular-text" required></td></tr>
                    <tr><th>Mağaza URL</th><td><code><?php echo esc_html( home_url('/magaza/') ); ?></code><input type="text" name="store_slug" value="<?php echo esc_attr( $v['store_slug'] ); ?>" class="regular-text"><code>/</code></td></tr>
                    <tr><th>Telefon</th><td><input type="tel" name="phone" value="<?php echo esc_attr( $v['phone'] ); ?>" class="regular-text"></td></tr>
                    <tr><th>Şehir</th><td><input type="text" name="city" value="<?php echo esc_attr( $v['city'] ); ?>" class="regular-text"></td></tr>
                    <tr><th>Adres</th><td><textarea name="address" rows="3" class="large-text"><?php echo esc_textarea( $v['address'] ); ?></textarea></td></tr>
                    <tr><th>Açıklama</th><td><textarea name="description" rows="4" class="large-text"><?php echo esc_textarea( $v['description'] ); ?></textarea></td></tr>
                    <tr><th>IBAN</th><td><input type="text" name="iban" value="<?php echo esc_attr( $v['iban'] ); ?>" class="regular-text"></td></tr>
                </table>
                <p><button type="submit" name="pzv_save_info" class="button button-primary">💾 Kaydet</button></p>
            </form>
        </div>
        <?php
    }

    /** Kategori edit formuna komisyon alanı */
    public function category_commission_field( $term ) {
        $rate = get_term_meta( $term->term_id, 'pzv_commission', true );
        ?>
        <tr class="form-field">
            <th><label>Komisyon Oranı (%)</label></th>
            <td><input type="number" name="pzv_commission" step="0.5" min="0" max="100" value="<?php echo esc_attr( $rate ); ?>" class="small-text">
            <p class="description">Bu kategorideki ürünler için komisyon. Boş bırakırsanız varsayılan kullanılır.</p></td>
        </tr>
        <?php
    }

    public function category_commission_add_field() {
        ?>
        <div class="form-field">
            <label>Komisyon Oranı (%)</label>
            <input type="number" name="pzv_commission" step="0.5" min="0" max="100" placeholder="Örn: 10">
            <p>Bu kategori için komisyon oranı.</p>
        </div>
        <?php
    }

    public function save_category_commission( $term_id ) {
        if ( isset( $_POST['pzv_commission'] ) ) {
            update_term_meta( $term_id, 'pzv_commission', sanitize_text_field( $_POST['pzv_commission'] ) );
        }
    }

    /** seller_apply CPT listesindeki satıra "Satıcı Yap" linki ekle */
    public function seller_apply_row_action( $actions, $post ) {
        if ( $post->post_type !== 'seller_apply' ) return $actions;
        if ( ! current_user_can( 'manage_woocommerce' ) ) return $actions;
        if ( get_post_meta( $post->ID, 'pzv_approved', true ) === '1' ) {
            $vid = (int) get_post_meta( $post->ID, 'pzv_vendor_id', true );
            $actions['pzv_approved'] = '<span style="color:#1a7a4a;">✓ Onaylanmış (Vendor ID: ' . $vid . ')</span>';
        } else {
            $url = '#';
            $actions['pzv_approve'] = '<a href="javascript:void(0);" class="pzv-approve-vendor" data-apply="' . (int) $post->ID . '" style="color:#ff6a00;font-weight:600;cursor:pointer;">🚀 Satıcı Yap</a>';
        }
        return $actions;
    }

    /** AJAX: vendor komisyon kaydet */
    public static function ajax_save_commission() {
        check_ajax_referer( 'pzv_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        $vid = (int) $_POST['vendor_id'];
        $rate = sanitize_text_field( $_POST['rate'] ?? '' );
        if ( $rate === '' ) delete_user_meta( $vid, 'pzv_commission_override' );
        else update_user_meta( $vid, 'pzv_commission_override', floatval( $rate ) );
        wp_send_json_success();
    }

    /** AJAX: başvuruyu onayla → vendor yap */
    public static function ajax_approve_vendor() {
        check_ajax_referer( 'pzv_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        $apply_id = (int) $_POST['apply_id'];
        if ( ! $apply_id ) wp_send_json_error( array( 'message' => 'ID eksik' ) );
        $email = get_post_meta( $apply_id, 'email', true );
        $name  = get_post_meta( $apply_id, 'ad_soyad', true );
        $store = get_post_meta( $apply_id, 'magaza_adi', true );
        if ( ! is_email( $email ) ) wp_send_json_error( array( 'message' => 'Geçersiz e-posta' ) );

        $user = get_user_by( 'email', $email );
        if ( ! $user ) {
            $password = wp_generate_password( 12 );
            $username = sanitize_user( strtolower( str_replace( ' ', '.', $name ) ), true );
            if ( username_exists( $username ) ) $username = 'satici_' . wp_generate_password( 6, false );
            $user_id = wp_create_user( $username, $password, $email );
            if ( is_wp_error( $user_id ) ) wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
            wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
            wp_mail( $email, '724PazarYeri - Satıcı hesabınız oluşturuldu',
                "Merhaba {$name},\n\nSatıcı başvurunuz onaylandı.\n\nGiriş Bilgileri:\nKullanıcı adı: {$username}\nŞifre: {$password}\n\nGiriş: " . wp_login_url() . "\n\nSatıcı paneliniz: " . home_url('/saticim/') . "\n\nGiriş yaptıktan sonra şifrenizi değiştirmenizi öneririz." );
        } else {
            $user_id = $user->ID;
        }
        PZV_Roles::make_vendor( $user_id, array(
            'store_name' => $store ?: $name,
            'phone'      => get_post_meta( $apply_id, 'telefon', true ),
            'city'       => get_post_meta( $apply_id, 'sehir', true ),
            'tc_or_tax'  => get_post_meta( $apply_id, 'vergi', true ),
        ) );
        update_post_meta( $apply_id, 'pzv_approved', '1' );
        update_post_meta( $apply_id, 'pzv_vendor_id', $user_id );
        wp_send_json_success( array( 'vendor_id' => $user_id, 'message' => 'Satıcı oluşturuldu' ) );
    }

    /** AJAX: Ödendi işaretle */
    public static function ajax_mark_paid() {
        check_ajax_referer( 'pzv_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error();
        $vid = (int) $_POST['vendor_id'];
        $note = sanitize_text_field( $_POST['note'] ?? '' );
        $rows = PZV_Commission::mark_vendor_paid( $vid, $note );
        $v = PZV_Vendor::get( $vid );
        if ( $v && $v['email'] ) {
            wp_mail( $v['email'], '724PazarYeri - Ödemeniz yapıldı',
                "Merhaba,\n\nBekleyen kazancınız hesabınıza yatırıldı.\n\nNot: {$note}\n\nDetay: " . home_url('/saticim/?tab=earnings') );
        }
        wp_send_json_success( array( 'rows' => $rows ) );
    }

    /**
     * Ürün edit ekranında "Satıcı (Vendor) Seç" meta box'ı
     */
    public function product_vendor_metabox() {
        // Sadece admin / shop_manager görür - vendor kendi ürünü için bunu görmeyecek (zaten kendisi yazar)
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;
        add_meta_box(
            'pzv_product_vendor',
            '🏪 Satıcı (Vendor)',
            array( $this, 'render_product_vendor_metabox' ),
            'product',
            'side',
            'high'
        );
    }

    public function render_product_vendor_metabox( $post ) {
        wp_nonce_field( 'pzv_save_product_vendor', 'pzv_product_vendor_nonce' );
        $current_author = (int) $post->post_author;
        $vendors = PZV_Vendor::get_all();
        // Admin'ler de listede olsun (kendi mağazaları varsa)
        $admins = get_users( array( 'role' => 'administrator', 'fields' => array( 'ID', 'display_name', 'user_email' ) ) );
        ?>
        <p><strong>Bu ürünün satıcısı:</strong></p>
        <select name="pzv_product_vendor" id="pzv_product_vendor" style="width:100%;">
            <?php
            // Mevcut yazar görünür değilse en üste ekle
            $found_in_list = false;
            ?>
            <optgroup label="── Satıcılar ──">
            <?php foreach ( $vendors as $vu ) :
                $v = PZV_Vendor::get( $vu->ID );
                if ( ! $v ) continue;
                $sel = selected( $current_author, $v['id'], false );
                if ( (int) $v['id'] === $current_author ) $found_in_list = true;
                ?>
                <option value="<?php echo (int) $v['id']; ?>" <?php echo $sel; ?>>
                    <?php echo esc_html( $v['store_name'] ); ?> (<?php echo esc_html( $v['display_name'] ); ?>)
                </option>
            <?php endforeach; ?>
            </optgroup>
            <optgroup label="── Yöneticiler ──">
            <?php foreach ( $admins as $a ) :
                $sel = selected( $current_author, $a->ID, false );
                if ( (int) $a->ID === $current_author ) $found_in_list = true;
                $store_name = get_user_meta( $a->ID, 'pzv_store_name', true ) ?: $a->display_name;
                ?>
                <option value="<?php echo (int) $a->ID; ?>" <?php echo $sel; ?>>
                    <?php echo esc_html( $store_name ); ?> (<?php echo esc_html( $a->display_name ); ?> — Admin)
                </option>
            <?php endforeach; ?>
            </optgroup>
            <?php if ( ! $found_in_list && $current_author ) :
                $cu = get_userdata( $current_author );
                ?>
                <option value="<?php echo (int) $current_author; ?>" selected>
                    Mevcut: <?php echo $cu ? esc_html( $cu->display_name ) : 'Kullanıcı #' . $current_author; ?>
                </option>
            <?php endif; ?>
        </select>
        <p class="description" style="margin-top:8px;">Bu ürünü satan kişi/mağaza. Komisyon hesaplaması bu satıcıya yapılır.</p>
        <?php
    }

    public function save_product_vendor( $post_id, $post ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        if ( ! isset( $_POST['pzv_product_vendor_nonce'] ) || ! wp_verify_nonce( $_POST['pzv_product_vendor_nonce'], 'pzv_save_product_vendor' ) ) return;
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;
        if ( ! isset( $_POST['pzv_product_vendor'] ) ) return;

        $new_vendor_id = (int) $_POST['pzv_product_vendor'];
        if ( $new_vendor_id <= 0 ) return;
        if ( $new_vendor_id === (int) $post->post_author ) return; // değişiklik yok

        // post_author güncelle (re-saving olmadan SQL ile direkt - infinite loop önler)
        global $wpdb;
        $wpdb->update( $wpdb->posts,
            array( 'post_author' => $new_vendor_id ),
            array( 'ID' => $post_id ),
            array( '%d' ),
            array( '%d' )
        );
        clean_post_cache( $post_id );
    }

    /**
     * Ürün listesi tablosuna "Satıcı" kolonu
     */
    public function product_column_vendor( $columns ) {
        // 'sku' kolonundan sonra ekle
        $new = array();
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'sku' ) {
                $new['pzv_vendor'] = '🏪 Satıcı';
            }
        }
        if ( ! isset( $new['pzv_vendor'] ) ) {
            $new['pzv_vendor'] = '🏪 Satıcı';
        }
        return $new;
    }

    public function product_column_vendor_content( $column, $post_id ) {
        if ( $column !== 'pzv_vendor' ) return;
        $author_id = (int) get_post_field( 'post_author', $post_id );
        if ( ! $author_id ) { echo '—'; return; }
        $u = get_userdata( $author_id );
        if ( ! $u ) { echo '—'; return; }
        $store = get_user_meta( $author_id, 'pzv_store_name', true ) ?: $u->display_name;
        $is_vendor = PZV_Roles::is_vendor( $author_id );
        $icon = $is_vendor ? '🏪' : '👤';
        echo $icon . ' <a href="' . esc_url( get_edit_user_link( $author_id ) ) . '">' . esc_html( $store ) . '</a>';
    }


    /**
     * Ürün ekle/düzenle ekranına "Satıcı" meta box (sadece admin/shop_manager için)
     */
    public function add_vendor_meta_box() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;
        add_meta_box(
            'pzv_product_vendor',
            '🏪 Satıcı Atama',
            array( $this, 'render_vendor_meta_box' ),
            'product',
            'side',
            'high'
        );
    }

    public function render_vendor_meta_box( $post ) {
        wp_nonce_field( 'pzv_save_vendor', 'pzv_vendor_nonce' );
        $current_author = (int) $post->post_author;
        // Tüm vendor'lar + admin/shop_manager kullanıcılar (kendi mağazaları olabilir)
        $vendors = get_users( array(
            'role__in' => array( PZV_ROLE, 'administrator', 'shop_manager' ),
            'orderby'  => 'display_name',
            'order'    => 'ASC',
            'number'   => 500,
        ) );
        ?>
        <p style="margin:0 0 8px;font-size:12px;color:#646970;">
            Bu ürün hangi satıcıya ait? Komisyonlar buna göre hesaplanır.
        </p>
        <select name="pzv_vendor_id" id="pzv_vendor_id" style="width:100%;">
            <?php foreach ( $vendors as $u ) :
                $store = get_user_meta( $u->ID, 'pzv_store_name', true );
                $label = $store ? $store . ' (' . $u->display_name . ')' : $u->display_name;
                if ( in_array( 'administrator', (array) $u->roles, true ) ) {
                    $label = '👑 ' . $label . ' [Admin]';
                }
                ?>
                <option value="<?php echo (int) $u->ID; ?>" <?php selected( $current_author, $u->ID ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ( $current_author ) :
            $current_user = get_userdata( $current_author );
            $store = get_user_meta( $current_author, 'pzv_store_name', true );
            ?>
            <p style="margin:10px 0 0;font-size:11.5px;color:#646970;">
                Şu an: <strong><?php echo esc_html( $store ?: ( $current_user ? $current_user->display_name : '—' ) ); ?></strong>
            </p>
        <?php endif; ?>
        <?php
    }

    public function save_vendor_meta_box( $post_id, $post ) {
        if ( ! isset( $_POST['pzv_vendor_nonce'] ) || ! wp_verify_nonce( $_POST['pzv_vendor_nonce'], 'pzv_save_vendor' ) ) return;
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
        if ( ! isset( $_POST['pzv_vendor_id'] ) ) return;
        $new_author = (int) $_POST['pzv_vendor_id'];
        if ( $new_author && $new_author !== (int) $post->post_author ) {
            // post_author'u güncelle (infinite loop'a girmemek için unhook)
            remove_action( 'save_post_product', array( $this, 'save_vendor_meta_box' ), 10 );
            wp_update_post( array( 'ID' => $post_id, 'post_author' => $new_author ) );
            add_action( 'save_post_product', array( $this, 'save_vendor_meta_box' ), 10, 2 );
        }
    }

}
