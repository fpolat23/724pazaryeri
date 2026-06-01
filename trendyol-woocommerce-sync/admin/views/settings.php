<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;

$vendor_roles = [ 'seller', 'vendor', 'wcfm_vendor', 'wc_product_vendors_admin_vendor', 'administrator' ];
$vendor_users = get_users( [ 'role__in' => $vendor_roles, 'orderby' => 'display_name', 'number' => 200 ] );
$saved_vendor = (int) get_option( 'tws_vendor_id', 0 );
$saved_markup = get_option( 'tws_price_markup', 0 );

$active_tab = isset( $_GET['tws_tab'] ) ? sanitize_key( $_GET['tws_tab'] ) : 'import';
?>
<div class="wrap tws-wrap">
    <h1>🔄 Trendyol WooCommerce Sync</h1>

    <!-- Sekmeler -->
    <nav class="tws-tabs">
        <a href="?page=trendyol-wc-sync&tws_tab=import"
           class="tws-tab <?php echo $active_tab === 'import' ? 'active' : ''; ?>">
            ⬇ Trendyol → WooCommerce
        </a>
        <a href="?page=trendyol-wc-sync&tws_tab=export"
           class="tws-tab <?php echo $active_tab === 'export' ? 'active' : ''; ?>">
            ⬆ WooCommerce → Trendyol
        </a>
    </nav>

    <?php if ( $active_tab === 'import' ) : ?>

    <!-- ============================================================ -->
    <!-- SEKME 1: İçe Aktarma (Trendyol → WooCommerce) -->
    <!-- ============================================================ -->
    <div class="tws-grid">
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
                    <tr>
                        <th><label for="tws_price_markup">Fiyat Marjı (%)</label></th>
                        <td>
                            <div class="tws-input-group">
                                <input type="number" id="tws_price_markup" name="tws_price_markup"
                                       value="<?php echo esc_attr( $saved_markup ); ?>"
                                       class="small-text" step="0.01" min="-90" max="500" />
                                <span class="tws-input-suffix">%</span>
                            </div>
                            <p class="description"><code>20</code> → +%20 &nbsp;|&nbsp; <code>-10</code> → -%10 &nbsp;|&nbsp; <code>0</code> → değişmez</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="tws_vendor_id">Vendor (Satıcı)</label></th>
                        <td>
                            <?php if ( ! empty( $vendor_users ) ) : ?>
                                <select id="tws_vendor_id" name="tws_vendor_id">
                                    <option value="0">— Atama yapma —</option>
                                    <?php foreach ( $vendor_users as $user ) : ?>
                                        <option value="<?php echo esc_attr( $user->ID ); ?>"
                                            <?php selected( $saved_vendor, $user->ID ); ?>>
                                            <?php echo esc_html( $user->display_name . ' (' . implode( ', ', $user->roles ) . ')' ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else : ?>
                                <input type="number" id="tws_vendor_id" name="tws_vendor_id"
                                       value="<?php echo esc_attr( $saved_vendor ?: '' ); ?>"
                                       class="small-text" placeholder="Kullanıcı ID" />
                            <?php endif; ?>
                            <p class="description">Dokan, WC Vendors ve WCFM desteklenir.</p>
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

        <div class="tws-card">
            <h2>Senkronizasyon</h2>
            <div class="tws-status-box">
                <div class="tws-stat">
                    <span class="tws-stat-label">Son Senkronizasyon</span>
                    <span class="tws-stat-value" id="tws-last-sync"><?php echo esc_html( get_option( 'tws_last_sync', '—' ) ); ?></span>
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
        <p class="description">Her Trendyol kategorisi için hangi WooCommerce kategorisinin kullanılacağını belirleyin. Eşleştirilmeyenler otomatik oluşturulur.</p>
        <?php $discovered = TWS_Category_Mapper::get_discovered(); ?>
        <?php if ( ! empty( $discovered ) ) : ?>
        <div class="tws-discovered-hint">
            <strong>Keşfedilen Trendyol kategorileri:</strong>
            <div class="tws-tag-list">
                <?php foreach ( $discovered as $cat ) : ?>
                    <span class="tws-tag" data-cat="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></span>
                <?php endforeach; ?>
            </div>
            <p class="description">Etikete tıklayarak tabloya ekleyebilirsiniz.</p>
        </div>
        <?php endif; ?>
        <table class="widefat tws-map-table">
            <thead><tr><th style="width:45%">Trendyol</th><th style="width:45%">WooCommerce</th><th style="width:10%"></th></tr></thead>
            <tbody id="tws-cat-map-body"></tbody>
        </table>
        <div class="tws-actions" style="margin-top:14px;">
            <button type="button" id="tws-add-map-row" class="button">+ Satır Ekle</button>
            <button type="button" id="tws-save-map-btn" class="button button-primary">Eşleştirmeyi Kaydet</button>
        </div>
        <div id="tws-map-result" class="tws-notice" style="display:none;"></div>
    </div>

    <!-- Log -->
    <div class="tws-card tws-card-full">
        <h2>Senkronizasyon Günlüğü</h2>
        <table class="widefat tws-log-table">
            <thead><tr><th>Tarih / Saat</th><th>Durum</th><th>Mesaj</th></tr></thead>
            <tbody>
                <?php
                $logs = get_option( TWS_Sync_Manager::LOG_OPTION, [] );
                if ( empty( $logs ) ) {
                    echo '<tr><td colspan="3">Henüz kayıt yok.</td></tr>';
                } else {
                    foreach ( $logs as $log ) {
                        printf(
                            '<tr><td>%s</td><td><span class="tws-badge tws-badge-%s">%s</span></td><td>%s</td></tr>',
                            esc_html( $log['time'] ), esc_attr( $log['level'] ),
                            esc_html( strtoupper( $log['level'] ) ), esc_html( $log['message'] )
                        );
                    }
                }
                ?>
            </tbody>
        </table>
    </div>

    <?php else : ?>

    <!-- ============================================================ -->
    <!-- SEKME 2: Dışa Aktarma (WooCommerce → Trendyol) -->
    <!-- ============================================================ -->
    <div class="tws-card tws-card-full">
        <h2>WooCommerce Ürünlerini Trendyol'a Aktar</h2>
        <p class="description">Ürün seçin, Trendyol kategori/marka/özelliklerini yapılandırın ve aktarın.</p>

        <div class="tws-export-toolbar">
            <input type="text" id="tws-product-search" class="regular-text" placeholder="Ürün adı veya SKU ara…" />
            <button type="button" id="tws-product-search-btn" class="button">Ara</button>
        </div>

        <table class="widefat striped tws-export-table" id="tws-export-table">
            <thead>
                <tr>
                    <th style="width:52px"></th>
                    <th>Ürün</th>
                    <th>SKU</th>
                    <th>Fiyat</th>
                    <th>Stok</th>
                    <th>Trendyol Durumu</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody id="tws-export-tbody">
                <tr><td colspan="7" class="tws-loading">Ürünler yükleniyor…</td></tr>
            </tbody>
        </table>

        <div class="tws-pagination" id="tws-export-pagination"></div>
    </div>

    <!-- Export Modal -->
    <div id="tws-export-modal" class="tws-modal-overlay" style="display:none;">
        <div class="tws-modal">
            <button type="button" class="tws-modal-close">✕</button>
            <h2 id="tws-modal-title">Ürünü Trendyol'a Aktar</h2>

            <div id="tws-modal-body">
                <table class="form-table tws-modal-form">
                    <!-- Kategori -->
                    <tr>
                        <th><label>Trendyol Kategorisi <span class="tws-req">*</span></label></th>
                        <td>
                            <div class="tws-ac-wrap">
                                <input type="text" id="tws-cat-search" class="regular-text" placeholder="Kategori adı girin…" autocomplete="off" />
                                <input type="hidden" id="tws-cat-id" />
                                <div class="tws-ac-dropdown" id="tws-cat-dropdown"></div>
                            </div>
                            <span class="tws-selected-label" id="tws-cat-label"></span>
                        </td>
                    </tr>
                    <!-- Marka -->
                    <tr>
                        <th><label>Marka <span class="tws-req">*</span></label></th>
                        <td>
                            <div class="tws-ac-wrap">
                                <input type="text" id="tws-brand-search" class="regular-text" placeholder="Marka adı girin…" autocomplete="off" />
                                <input type="hidden" id="tws-brand-id" />
                                <div class="tws-ac-dropdown" id="tws-brand-dropdown"></div>
                            </div>
                            <span class="tws-selected-label" id="tws-brand-label"></span>
                        </td>
                    </tr>
                    <!-- Kargo -->
                    <tr>
                        <th><label>Kargo Şirketi <span class="tws-req">*</span></label></th>
                        <td>
                            <select id="tws-cargo-id" class="regular-text">
                                <option value="">Yükleniyor…</option>
                            </select>
                        </td>
                    </tr>
                    <!-- Desi -->
                    <tr>
                        <th><label>Boyutsal Ağırlık (Desi)</label></th>
                        <td>
                            <input type="number" id="tws-desi" class="small-text" value="1" min="0.1" step="0.1" />
                        </td>
                    </tr>
                </table>

                <!-- Kategori Özellikleri -->
                <div id="tws-attributes-section" style="display:none;">
                    <h3>Kategori Özellikleri</h3>
                    <table class="form-table" id="tws-attributes-table"></table>
                </div>

                <div id="tws-export-modal-result" class="tws-notice" style="display:none;"></div>
            </div>

            <div class="tws-modal-footer">
                <button type="button" id="tws-do-export" class="button button-primary button-large">Trendyol'a Aktar</button>
                <button type="button" class="button tws-modal-close-btn">İptal</button>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>
