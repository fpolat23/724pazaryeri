<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;

// Vendor kullanıcı listesi (Dokan, WC Vendors, WCFM, admin)
$vendor_roles  = [ 'seller', 'vendor', 'wcfm_vendor', 'wc_product_vendors_admin_vendor', 'administrator' ];
$vendor_users  = get_users( [ 'role__in' => $vendor_roles, 'orderby' => 'display_name', 'number' => 200 ] );
$saved_vendor  = (int) get_option( 'tws_vendor_id', 0 );
$saved_markup  = get_option( 'tws_price_markup', 0 );
?>
<div class="wrap tws-wrap">
    <h1>🔄 Trendyol WooCommerce Sync</h1>

    <div class="tws-grid">
        <!-- API Ayarları -->
        <div class="tws-card">
            <h2>API Ayarları</h2>
            <form method="post" action="options.php">
                <?php settings_fields( 'tws_settings' ); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="tws_supplier_id">Tedarikçi ID</label></th>
                        <td>
                            <input type="text" id="tws_supplier_id" name="tws_supplier_id"
                                   value="<?php echo esc_attr( get_option( 'tws_supplier_id' ) ); ?>"
                                   class="regular-text" placeholder="123456" />
                            <p class="description">Trendyol Entegrasyon Paneli → Kullanıcı Bilgileri.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tws_api_key">API Key</label></th>
                        <td>
                            <input type="text" id="tws_api_key" name="tws_api_key"
                                   value="<?php echo esc_attr( get_option( 'tws_api_key' ) ); ?>"
                                   class="regular-text" autocomplete="off" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tws_api_secret">API Secret</label></th>
                        <td>
                            <input type="password" id="tws_api_secret" name="tws_api_secret"
                                   value="<?php echo esc_attr( get_option( 'tws_api_secret' ) ); ?>"
                                   class="regular-text" autocomplete="off" />
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tws_sync_interval">Otomatik Senkronizasyon</label></th>
                        <td>
                            <select id="tws_sync_interval" name="tws_sync_interval">
                                <?php
                                $intervals = [ 'hourly' => 'Her saat', 'twicedaily' => 'Günde 2 kez', 'daily' => 'Günde 1 kez' ];
                                $current   = get_option( 'tws_sync_interval', 'hourly' );
                                foreach ( $intervals as $val => $lbl ) {
                                    printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $current, $val, false ), esc_html( $lbl ) );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>

                    <!-- Fiyat Marjı -->
                    <tr>
                        <th><label for="tws_price_markup">Fiyat Marjı (%)</label></th>
                        <td>
                            <div class="tws-input-group">
                                <input type="number" id="tws_price_markup" name="tws_price_markup"
                                       value="<?php echo esc_attr( $saved_markup ); ?>"
                                       class="small-text" step="0.01" min="-90" max="500" />
                                <span class="tws-input-suffix">%</span>
                            </div>
                            <p class="description">
                                Trendyol fiyatına uygulanacak artış/azalış yüzdesi.<br>
                                Örn: <code>20</code> → fiyatlar %20 artırılır &nbsp;|&nbsp; <code>-10</code> → %10 indirimli aktarılır &nbsp;|&nbsp; <code>0</code> → değişmez.
                            </p>
                        </td>
                    </tr>

                    <!-- Vendor Atama -->
                    <tr>
                        <th><label for="tws_vendor_id">Vendor (Satıcı)</label></th>
                        <td>
                            <?php if ( ! empty( $vendor_users ) ) : ?>
                                <select id="tws_vendor_id" name="tws_vendor_id">
                                    <option value="0">— Atama yapma —</option>
                                    <?php foreach ( $vendor_users as $user ) :
                                        $roles_str = implode( ', ', array_map( 'ucfirst', $user->roles ) );
                                    ?>
                                        <option value="<?php echo esc_attr( $user->ID ); ?>"
                                            <?php selected( $saved_vendor, $user->ID ); ?>>
                                            <?php echo esc_html( $user->display_name . ' (' . $roles_str . ')' ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else : ?>
                                <input type="number" id="tws_vendor_id" name="tws_vendor_id"
                                       value="<?php echo esc_attr( $saved_vendor ?: '' ); ?>"
                                       class="small-text" placeholder="Kullanıcı ID" />
                            <?php endif; ?>
                            <p class="description">Aktarılan ürünler bu satıcıya atanır. Dokan, WC Vendors ve WCFM desteklenir.</p>
                        </td>
                    </tr>
                </table>

                <div class="tws-actions">
                    <?php submit_button( 'Kaydet', 'primary', 'submit', false ); ?>
                    <button type="button" id="tws-test-btn" class="button button-secondary">Bağlantıyı Test Et</button>
                </div>
                <div id="tws-test-result" class="tws-notice" style="display:none;"></div>
            </form>
        </div>

        <!-- Senkronizasyon Paneli -->
        <div class="tws-card">
            <h2>Senkronizasyon</h2>
            <div class="tws-status-box">
                <div class="tws-stat">
                    <span class="tws-stat-label">Son Senkronizasyon</span>
                    <span class="tws-stat-value" id="tws-last-sync">
                        <?php echo esc_html( get_option( 'tws_last_sync', '—' ) ); ?>
                    </span>
                </div>
                <div class="tws-stat">
                    <span class="tws-stat-label">Aktarılan Ürün</span>
                    <span class="tws-stat-value">
                        <?php echo (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_trendyol_product_id'" ); ?>
                    </span>
                </div>
            </div>
            <div class="tws-actions">
                <button type="button" id="tws-sync-btn" class="button button-primary button-hero">Manuel Senkronize Et</button>
            </div>
            <div id="tws-sync-progress" style="display:none;">
                <div class="tws-progress-bar"><div class="tws-progress-fill"></div></div>
                <p id="tws-sync-message">Trendyol'dan ürünler alınıyor…</p>
            </div>
            <div id="tws-sync-result" class="tws-notice" style="display:none;"></div>
        </div>
    </div>

    <!-- Kategori Eşleştirme -->
    <div class="tws-card tws-card-full">
        <h2>Kategori Eşleştirme</h2>
        <p class="description">
            Her Trendyol kategorisi için hangi WooCommerce kategorisinin kullanılacağını belirleyin.
            Eşleştirilmeyen kategoriler otomatik olarak oluşturulur.
        </p>

        <?php
        $discovered = TWS_Category_Mapper::get_discovered();
        if ( ! empty( $discovered ) ) :
        ?>
        <div class="tws-discovered-hint">
            <strong>Trendyol'da keşfedilen kategoriler:</strong>
            <div class="tws-tag-list">
                <?php foreach ( $discovered as $cat ) : ?>
                    <span class="tws-tag" data-cat="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></span>
                <?php endforeach; ?>
            </div>
            <p class="description">Bir etikete tıklayarak tabloya ekleyebilirsiniz.</p>
        </div>
        <?php endif; ?>

        <table class="widefat tws-map-table" id="tws-cat-map-table">
            <thead>
                <tr>
                    <th style="width:45%">Trendyol Kategorisi</th>
                    <th style="width:45%">WooCommerce Kategorisi</th>
                    <th style="width:10%"></th>
                </tr>
            </thead>
            <tbody id="tws-cat-map-body">
                <!-- JS tarafından doldurulur -->
            </tbody>
        </table>

        <div class="tws-actions" style="margin-top:14px;">
            <button type="button" id="tws-add-map-row" class="button button-secondary">+ Satır Ekle</button>
            <button type="button" id="tws-save-map-btn" class="button button-primary">Eşleştirmeyi Kaydet</button>
        </div>
        <div id="tws-map-result" class="tws-notice" style="display:none;"></div>
    </div>

    <!-- Log -->
    <div class="tws-card tws-card-full">
        <h2>Senkronizasyon Günlüğü</h2>
        <table class="widefat tws-log-table">
            <thead>
                <tr><th>Tarih / Saat</th><th>Durum</th><th>Mesaj</th></tr>
            </thead>
            <tbody id="tws-log-body">
                <?php
                $logs = get_option( TWS_Sync_Manager::LOG_OPTION, [] );
                if ( empty( $logs ) ) {
                    echo '<tr><td colspan="3">Henüz kayıt yok.</td></tr>';
                } else {
                    foreach ( $logs as $log ) {
                        printf(
                            '<tr><td>%s</td><td><span class="tws-badge tws-badge-%s">%s</span></td><td>%s</td></tr>',
                            esc_html( $log['time'] ),
                            esc_attr( $log['level'] ),
                            esc_html( strtoupper( $log['level'] ) ),
                            esc_html( $log['message'] )
                        );
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
