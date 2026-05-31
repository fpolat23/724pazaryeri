<?php
/**
 * 724PazarYeri — Türkçe & Modern Üye Paneli (My Account)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ──────────────────────────────────────────────
   1) Hesap menüsü sekmelerini Türkçeleştir + yeniden sırala
────────────────────────────────────────────── */
add_filter( 'woocommerce_account_menu_items', function ( $items ) {
    $new = array();
    $new['dashboard']       = 'Panelim';
    $new['orders']          = 'Siparişlerim';
    $new['downloads']       = 'İndirmelerim';
    $new['edit-address']    = 'Adreslerim';
    $new['edit-account']    = 'Hesap Bilgilerim';
    // varsa ek sekmeler korunur
    if ( isset( $items['customer-logout'] ) ) {
        $new['customer-logout'] = 'Çıkış Yap';
    }
    return $new;
}, 99 );

/* Sekme ikonları (CSS ::before ile menüye ikon eklemek için body class) */
add_filter( 'woocommerce_account_menu_item_classes', function ( $classes, $endpoint ) {
    $classes[] = 'pz-acc-' . $endpoint;
    return $classes;
}, 10, 2 );


/* ──────────────────────────────────────────────
   2) Panel (dashboard) içeriğini modern Türkçe karşılama ile değiştir
────────────────────────────────────────────── */
remove_action( 'woocommerce_account_dashboard', 'woocommerce_account_dashboard' );
add_action( 'woocommerce_account_dashboard', function () {
    $user = wp_get_current_user();
    $name = $user->first_name ? $user->first_name : $user->display_name;

    // istatistikler
    $order_count = wc_get_customer_order_count( $user->ID );
    $args = array( 'customer_id' => $user->ID, 'status' => array( 'wc-processing', 'wc-on-hold' ), 'return' => 'ids', 'limit' => -1 );
    $active_orders = count( wc_get_orders( $args ) );
    ?>
    <div class="pz-acc-welcome">
        <div class="pz-acc-welcome-txt">
            <div class="pz-acc-hi">Merhaba, <strong><?php echo esc_html( $name ); ?></strong> 👋</div>
            <div class="pz-acc-sub">Hesap panelinden siparişlerini takip edeb, adreslerini yönetebilir ve bilgilerini güncelleyebilirsin.</div>
        </div>
        <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="pz-acc-shop-btn">🛍️ Alışverişe Başla</a>
    </div>

    <div class="pz-acc-stats">
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" class="pz-acc-stat">
            <div class="pz-acc-stat-ico" style="background:#fff0e6">📦</div>
            <div><div class="pz-acc-stat-n"><?php echo esc_html( $order_count ); ?></div><div class="pz-acc-stat-l">Toplam Sipariş</div></div>
        </a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" class="pz-acc-stat">
            <div class="pz-acc-stat-ico" style="background:#e0f2fe">🚚</div>
            <div><div class="pz-acc-stat-n"><?php echo esc_html( $active_orders ); ?></div><div class="pz-acc-stat-l">Aktif Sipariş</div></div>
        </a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>" class="pz-acc-stat">
            <div class="pz-acc-stat-ico" style="background:#dcfce7">📍</div>
            <div><div class="pz-acc-stat-n">Adres</div><div class="pz-acc-stat-l">Adreslerimi Yönet</div></div>
        </a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>" class="pz-acc-stat">
            <div class="pz-acc-stat-ico" style="background:#f3e8ff">⚙️</div>
            <div><div class="pz-acc-stat-n">Profil</div><div class="pz-acc-stat-l">Bilgilerimi Düzenle</div></div>
        </a>
    </div>

    <div class="pz-acc-cards">
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>" class="pz-acc-card">
            <div class="pz-acc-card-ico">📦</div>
            <div class="pz-acc-card-t">Siparişlerim</div>
            <div class="pz-acc-card-s">Geçmiş ve aktif siparişlerini görüntüle, kargo takibi yap, fatura indir.</div>
            <span class="pz-acc-card-link">Siparişlere Git →</span>
        </a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-address' ) ); ?>" class="pz-acc-card">
            <div class="pz-acc-card-ico">📍</div>
            <div class="pz-acc-card-t">Adreslerim</div>
            <div class="pz-acc-card-s">Teslimat ve fatura adreslerini ekle, düzenle. Hızlı teslimat için adresini güncel tut.</div>
            <span class="pz-acc-card-link">Adresleri Yönet →</span>
        </a>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'edit-account' ) ); ?>" class="pz-acc-card">
            <div class="pz-acc-card-ico">🔒</div>
            <div class="pz-acc-card-t">Hesap Güvenliği</div>
            <div class="pz-acc-card-s">Ad, e-posta ve şifreni güncelle. Hesabını güvende tutmak için güçlü bir şifre kullan.</div>
            <span class="pz-acc-card-link">Bilgilerimi Düzenle →</span>
        </a>
    </div>

    <?php
    // PZV Vendor sistemi varsa, vendor olan kullanıcılara "Mağazam" göster
    $pz_is_vendor = false;
    if ( class_exists( 'PZV_Roles' ) && method_exists( 'PZV_Roles', 'is_vendor' ) ) {
        $pz_is_vendor = PZV_Roles::is_vendor( get_current_user_id() );
    }
    if ( $pz_is_vendor ) : ?>
    <a href="<?php echo esc_url( home_url('/saticim/') ); ?>" class="pz-acc-seller-cta pz-acc-store-cta" style="text-decoration:none;color:inherit;">
        <div class="pz-acc-seller-ico">🏪</div>
        <div class="pz-acc-seller-txt">
            <div class="pz-acc-seller-t">Mağaza Panelime Git</div>
            <div class="pz-acc-seller-s">Ürünlerini, siparişlerini ve kazançlarını yönet. <strong>Satıcı panelin seni bekliyor.</strong></div>
        </div>
        <span class="pz-acc-seller-btn">Panele Git →</span>
    </a>
    <?php else : ?>
    <div class="pz-acc-seller-cta">
        <div class="pz-acc-seller-ico">🚀</div>
        <div class="pz-acc-seller-txt">
            <div class="pz-acc-seller-t">Sen de Satıcı Ol, Kazanmaya Başla!</div>
            <div class="pz-acc-seller-s">Türkiye'nin en güvenilir kişiden kişiye pazarında mağazanı aç. <strong>İlk 3 ay komisyon sıfır!</strong></div>
        </div>
        <a href="<?php echo esc_url( home_url( '/satici-ol/' ) ); ?>" class="pz-acc-seller-btn">Hemen Başvur →</a>
    </div>
    <?php endif; ?>

    <div class="pz-acc-help">
        <div class="pz-acc-help-ico">💬</div>
        <div class="pz-acc-help-txt">
            <div class="pz-acc-help-t">Yardıma mı ihtiyacın var?</div>
            <div class="pz-acc-help-s">Sipariş, iade veya ödeme ile ilgili tüm sorularında destek ekibimiz 7/24 yanında.</div>
        </div>
        <a href="<?php echo esc_url( home_url( '/iletisim/' ) ); ?>" class="pz-acc-help-btn">Destek Al →</a>
    </div>
    <?php
} );


/* ──────────────────────────────────────────────
   3) Hesap sayfası başlıkları/metinleri Türkçeleştir
────────────────────────────────────────────── */
add_filter( 'gettext', function ( $translated, $text, $domain ) {
    if ( $domain !== 'woocommerce' ) return $translated;
    $tr = array(
        'No order has been made yet.'                      => 'Henüz hiç sipariş vermediniz.',
        'Browse products'                                  => 'Ürünlere Göz At',
        'Hello %1$s (not %1$s? %2$s)'                      => 'Merhaba %1$s (siz değil misiniz? %2$s)',
        'From your account dashboard you can view your recent orders, manage your shipping and billing addresses, and edit your password and account details.' => 'Hesap panelinden son siparişlerinizi görüntüleyebilir, teslimat ve fatura adreslerinizi yönetebilir, şifrenizi ve hesap bilgilerinizi düzenleyebilirsiniz.',
        'Recent orders'                                    => 'Son Siparişler',
        'Order'                                            => 'Sipariş',
        'Date'                                             => 'Tarih',
        'Status'                                           => 'Durum',
        'Total'                                            => 'Toplam',
        'Actions'                                          => 'İşlemler',
        'View'                                             => 'Görüntüle',
        'Pay'                                              => 'Öde',
        'Cancel'                                           => 'İptal Et',
        'Addresses'                                        => 'Adreslerim',
        'Billing address'                                  => 'Fatura Adresi',
        'Shipping address'                                 => 'Teslimat Adresi',
        'Edit'                                             => 'Düzenle',
        'Add'                                              => 'Ekle',
        'The following addresses will be used on the checkout page by default.' => 'Aşağıdaki adresler ödeme sayfasında varsayılan olarak kullanılır.',
        'Account details'                                  => 'Hesap Bilgileri',
        'First name'                                       => 'Ad',
        'Last name'                                        => 'Soyad',
        'Display name'                                     => 'Görünen Ad',
        'Email address'                                    => 'E-posta Adresi',
        'Password change'                                  => 'Şifre Değişikliği',
        'Current password (leave blank to leave unchanged)'=> 'Mevcut Şifre (değiştirmek istemiyorsanız boş bırakın)',
        'New password (leave blank to leave unchanged)'    => 'Yeni Şifre (değiştirmek istemiyorsanız boş bırakın)',
        'Confirm new password'                             => 'Yeni Şifreyi Onayla',
        'Save changes'                                     => 'Değişiklikleri Kaydet',
        'Save address'                                     => 'Adresi Kaydet',
        'Login'                                            => 'Giriş Yap',
        'Register'                                         => 'Üye Ol',
        'Username or email address'                        => 'Kullanıcı Adı veya E-posta',
        'Password'                                         => 'Şifre',
        'Remember me'                                      => 'Beni Hatırla',
        'Lost your password?'                              => 'Şifreni mi unuttun?',
        'Log out'                                          => 'Çıkış Yap',
        'Logout'                                           => 'Çıkış Yap',
        'Downloads'                                        => 'İndirmelerim',
        'No downloads available yet.'                      => 'Henüz indirilebilir ürün yok.',
        'Country / Region'                                 => 'Ülke / Bölge',
        'Town / City'                                      => 'İl / Şehir',
        'Postcode / ZIP'                                   => 'Posta Kodu',
        'Phone'                                            => 'Telefon',
        'Company name'                                     => 'Firma Adı',
        'Street address'                                   => 'Açık Adres',
        // Genel site / sepet / mağaza metinleri
        'Add to cart'                                      => 'Sepete Ekle',
        'View cart'                                         => 'Sepeti Görüntüle',
        'Read more'                                         => 'Detaylar',
        'Select options'                                    => 'Seçenekler',
        '%s has been added to your cart.'                   => '%s sepete eklendi.',
        '&ldquo;%s&rdquo; has been added to your cart.'     => '&ldquo;%s&rdquo; sepete eklendi.',
        'Sale!'                                             => 'İndirim!',
        'Out of stock'                                      => 'Tükendi',
        'In stock'                                          => 'Stokta',
        'Related products'                                  => 'Benzer Ürünler',
        'You may also like&hellip;'                         => 'Bunları da beğenebilirsiniz…',
        'Description'                                        => 'Açıklama',
        'Reviews'                                            => 'Değerlendirmeler',
        'Additional information'                             => 'Ek Bilgiler',
        'Quantity'                                           => 'Adet',
        'Proceed to checkout'                                => 'Ödemeye Geç',
        'Cart totals'                                        => 'Sepet Toplamı',
        'Subtotal'                                           => 'Ara Toplam',
        'Coupon:'                                            => 'Kupon:',
        'Apply coupon'                                       => 'Kuponu Uygula',
        'Update cart'                                        => 'Sepeti Güncelle',
        'Product'                                            => 'Ürün',
        'Price'                                              => 'Fiyat',
        'Remove this item'                                   => 'Ürünü Kaldır',
        'Your cart is currently empty.'                      => 'Sepetiniz şu anda boş.',
        'Return to shop'                                     => 'Mağazaya Dön',
        'Billing details'                                    => 'Fatura Bilgileri',
        'Ship to a different address?'                       => 'Farklı bir adrese mi göndereceksiniz?',
        'Your order'                                         => 'Siparişiniz',
        'Place order'                                        => 'Siparişi Tamamla',
        'Have a coupon?'                                     => 'Kupon kodunuz var mı?',
        'Click here to enter your code'                      => 'Kodunuzu girmek için tıklayın',
        'Order received'                                     => 'Sipariş Alındı',
        'Thank you. Your order has been received.'           => 'Teşekkürler. Siparişiniz alındı.',
        'Shop'                                               => 'Mağaza',
        'Search'                                             => 'Ara',
        'Search results for: %s'                             => 'Arama sonuçları: %s',
        // Adres & hesap ek metinleri
        'You have not set up this type of address yet.'    => 'Bu adres türünü henüz tanımlamadınız.',
        'No saved addresses found.'                         => 'Kayıtlı adres bulunamadı.',
        'Add new address'                                   => 'Yeni Adres Ekle',
        'Edit address'                                      => 'Adresi Düzenle',
        'Update'                                            => 'Güncelle',
        'Address updated successfully.'                     => 'Adres başarıyla güncellendi.',
        'Account details changed successfully.'             => 'Hesap bilgileri başarıyla güncellendi.',
        'Your password has been reset.'                     => 'Şifreniz sıfırlandı.',
        'Welcome back!'                                     => 'Tekrar hoş geldiniz!',
        'Hello %1$s'                                        => 'Merhaba %1$s',
        'Make a payment'                                    => 'Ödeme Yap',
        'There are currently no items in your cart.'        => 'Sepetinizde şu anda ürün yok.',
        'Order number:'                                     => 'Sipariş Numarası:',
        'Order date:'                                       => 'Sipariş Tarihi:',
        'Payment method:'                                   => 'Ödeme Yöntemi:',
        'Sign in'                                           => 'Giriş Yap',
        'Create an account'                                 => 'Hesap Oluştur',
        'A password reset email has been sent to the email address on file for your account, but may take several minutes to show up in your inbox.' => 'Hesabınıza kayıtlı e-posta adresine şifre sıfırlama bağlantısı gönderildi. Gelen kutunuza ulaşması birkaç dakika sürebilir.',
        'Enter your username or email address and we will send you a link to reset your password.' => 'Kullanıcı adınızı veya e-posta adresinizi girin, şifre sıfırlama bağlantısını size gönderelim.',
        'Reset password'                                    => 'Şifreyi Sıfırla',
        'Register'                                          => 'Üye Ol',
        'Required fields are marked %s'                     => 'Zorunlu alanlar %s ile işaretlidir',
        'Showing all %d results'                            => 'Tüm %d sonuç gösteriliyor',
        'Showing the single result'                         => 'Tek sonuç gösteriliyor',
        'Showing %1$d&ndash;%2$d of %3$d results'           => '%3$d sonuçtan %1$d&ndash;%2$d arası gösteriliyor',
        'Default sorting'                                   => 'Varsayılan sıralama',
        'Sort by popularity'                                => 'Popülerliğe göre',
        'Sort by latest'                                    => 'En yeniler',
        'Sort by price: low to high'                        => 'Fiyat: düşükten yükseğe',
        'Sort by price: high to low'                        => 'Fiyat: yüksekten düşüğe',
        'Filter'                                            => 'Filtrele',
        'Update address'                                    => 'Adresi Güncelle',
        'Save'                                              => 'Kaydet',
        'Continue'                                          => 'Devam Et',
        'Free!'                                             => 'Ücretsiz!',
        'Free shipping'                                     => 'Ücretsiz Kargo',
        'Shipping'                                          => 'Kargo',
        'Estimated delivery'                                => 'Tahmini Teslimat',
        'Note'                                              => 'Not',
        'Order notes'                                       => 'Sipariş Notları',
        'Notes about your order, e.g. special notes for delivery.' => 'Siparişinizle ilgili notlar (örn. teslimat için özel notlar).',
        // Adres formu alanları
        'Country / Region'                                  => 'Ülke / Bölge',
        'First name'                                        => 'Ad',
        'Last name'                                         => 'Soyad',
        'Company name (optional)'                           => 'Firma Adı (isteğe bağlı)',
        'Street address'                                    => 'Açık Adres',
        'House number and street name'                      => 'Mahalle, sokak ve numara',
        'Apartment, suite, unit, etc. (optional)'           => 'Daire, kat, bina vb. (isteğe bağlı)',
        'Apartment, suite, unit, etc.'                      => 'Daire, kat, bina vb.',
        'Town / City'                                       => 'İlçe / Şehir',
        'District'                                          => 'İlçe',
        'State / County'                                    => 'İl',
        'Province'                                          => 'İl',
        'Postcode / ZIP'                                    => 'Posta Kodu',
        'Phone'                                             => 'Telefon',
        'Email address'                                     => 'E-posta Adresi',
        'Billing address'                                   => 'Fatura Adresi',
        'Shipping address'                                  => 'Teslimat Adresi',
        'Your billing address'                              => 'Fatura adresiniz',
        'Your shipping address'                             => 'Teslimat adresiniz',
        'Set up your billing address.'                      => 'Fatura adresinizi tanımlayın.',
        'Set up your shipping address.'                     => 'Teslimat adresinizi tanımlayın.',
        'This will be how your name will be displayed in the account section and in reviews' => 'Adınız hesap bölümünde ve değerlendirmelerde bu şekilde görünecektir',
        'Password change (optional)'                        => 'Şifre Değişikliği (isteğe bağlı)',
        'Leave the current password fields empty to keep your current password.' => 'Mevcut şifrenizi korumak için şifre alanlarını boş bırakın.',
        'Required fields'                                   => 'Zorunlu alanlar',
        'optional'                                          => 'isteğe bağlı',
        'Select a country / region&hellip;'                 => 'Ülke / bölge seçin…',
        'Select an option&hellip;'                          => 'Seçiniz…',
        'Order again'                                        => 'Tekrar Sipariş Ver',
        'Track'                                              => 'Takip Et',
        'My account'                                        => 'Hesabım',
        'Dashboard'                                          => 'Panelim',
        'Orders'                                             => 'Siparişlerim',
        'Addresses'                                          => 'Adreslerim',
        'Account details'                                    => 'Hesap Bilgilerim',
        'Logout'                                             => 'Çıkış Yap',
        'Log out'                                            => 'Çıkış Yap',
    );
    return isset( $tr[ $text ] ) ? $tr[ $text ] : $translated;
}, 20, 3 );


/* ──────────────────────────────────────────────
   4) Adres formu alan etiketlerini doğrudan Türkçeleştir
   (gettext'in kaçırdığı alanlar için kesin çözüm)
────────────────────────────────────────────── */
add_filter( 'woocommerce_default_address_fields', function ( $fields ) {
    $labels = array(
        'first_name' => 'Ad',
        'last_name'  => 'Soyad',
        'company'    => 'Firma Adı (isteğe bağlı)',
        'country'    => 'Ülke / Bölge',
        'address_1'  => 'Açık Adres',
        'address_2'  => 'Daire, kat, bina vb. (isteğe bağlı)',
        'city'       => 'İlçe / Şehir',
        'state'      => 'İl',
        'postcode'   => 'Posta Kodu',
    );
    $placeholders = array(
        'first_name' => 'Adınız',
        'last_name'  => 'Soyadınız',
        'address_1'  => 'Mahalle, sokak ve numara',
        'address_2'  => 'Daire, kat, bina (isteğe bağlı)',
        'city'       => 'İlçe / şehir',
        'postcode'   => 'Posta kodu',
    );
    foreach ( $labels as $key => $label ) {
        if ( isset( $fields[ $key ] ) ) {
            $fields[ $key ]['label'] = $label;
        }
    }
    foreach ( $placeholders as $key => $ph ) {
        if ( isset( $fields[ $key ] ) ) {
            $fields[ $key ]['placeholder'] = $ph;
        }
    }
    return $fields;
}, 99 );

// Fatura/teslimat telefon & e-posta alanları
add_filter( 'woocommerce_billing_fields', function ( $fields ) {
    if ( isset( $fields['billing_phone'] ) )   $fields['billing_phone']['label'] = 'Telefon';
    if ( isset( $fields['billing_email'] ) )   $fields['billing_email']['label'] = 'E-posta Adresi';
    return $fields;
}, 99 );
