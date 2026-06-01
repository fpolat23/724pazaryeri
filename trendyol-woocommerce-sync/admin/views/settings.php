<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;

$current_user_id = get_current_user_id();
$is_admin        = current_user_can( 'manage_woocommerce' );
$is_vendor       = ! $is_admin && TWS_Vendor_Manager::is_vendor( $current_user_id );
$active_tab      = isset( $_GET['tws_tab'] ) ? sanitize_key( $_GET['tws_tab'] ) : ( $is_vendor ? 'my' : 'import' );
$vendor_config   = TWS_Vendor_Manager::get_vendor_config( $current_user_id );
$wp_cron_off     = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

$intervals = [
    900   => 'Her 15 dakika',
    1800  => 'Her 30 dakika',
    3600  => 'Her saat',
    7200  => 'Her 2 saat',
    21600 => 'Her 6 saat',
    43200 => 'Her 12 saat',
    86400 => 'Günde 1 kez',
];
?>
<div class="wrap tws-wrap">
    <h1>🔄 Trendyol WooCommerce Sync</h1>

    <nav class="tws-tabs">
        <?php if ( $is_vendor ) : ?>
            <a href="?page=trendyol-wc-sync&tws_tab=my"
               class="tws-tab <?php echo $active_tab === 'my' ? 'active' : ''; ?>">⚙ Benim Entegrasyonum</a>
            <a href="?page=trendyol-wc-sync&tws_tab=export"
               class="tws-tab <?php echo $active_tab === 'export' ? 'active' : ''; ?>">⬆ WC → Trendyol</a>
        <?php else : ?>
            <a href="?page=trendyol-wc-sync&tws_tab=vendors"
               class="tws-tab <?php echo $active_tab === 'vendors' ? 'active' : ''; ?>">👥 Vendor Yönetimi</a>
            <a href="?page=trendyol-wc-sync&tws_tab=import"
               class="tws-tab <?php echo $active_tab === 'import' ? 'active' : ''; ?>">⬇ Trendyol → WC</a>
            <a href="?page=trendyol-wc-sync&tws_tab=export"
               class="tws-tab <?php echo $active_tab === 'export' ? 'active' : ''; ?>">⬆ WC → Trendyol</a>
        <?php endif; ?>
    </nav>

<?php

/* ================================================================
   VENDOR: Benim Entegrasyonum
   ================================================================ */
if ( $active_tab === 'my' ) :
    $stats = TWS_Vendor_Manager::get_vendor_stats( $current_user_id );
?>
<div class="tws-grid">

    <div class="tws-card">
        <h2>Trendyol API Bilgilerim</h2>
        <form id="tws-my-config-form">
            <table class="form-table">
                <tr>
                    <th><label for="my_supplier_id">Tedarikçi ID</label></th>
                    <td><input type="text" id="my_supplier_id" name="supplier_id"
                               value="<?php echo esc_attr( $vendor_config['supplier_id'] ); ?>"
                               class="regular-text" placeholder="123456" /></td>
                </tr>
                <tr>
                    <th><label for="my_api_key">API Key</label></th>
                    <td><input type="text" id="my_api_key" name="api_key"
                               value="<?php echo esc_attr( $vendor_config['api_key'] ); ?>"
                               class="regular-text" autocomplete="off" /></td>
                </tr>
                <tr>
                    <th><label for="my_api_secret">API Secret</label></th>
                    <td><input type="password" id="my_api_secret" name="api_secret"
                               value="<?php echo esc_attr( $vendor_config['api_secret'] ); ?>"
                               class="regular-text" autocomplete="off" /></td>
                </tr>
                <tr>
                    <th><label for="my_price_markup">Fiyat Marjı (%)</label></th>
                    <td>
                        <div class="tws-input-group">
                            <input type="number" id="my_price_markup" name="price_markup"
                                   value="<?php echo esc_attr( $vendor_config['price_markup'] ); ?>"
                                   class="small-text" step="0.01" min="-90" max="500" />
                            <span class="tws-input-suffix">%</span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="my_sync_interval">Senkronizasyon Sıklığı</label></th>
                    <td>
                        <select id="my_sync_interval" name="sync_interval">
                            <?php foreach ( $intervals as $secs => $lbl ) : ?>
                                <option value="<?php echo $secs; ?>" <?php selected( $vendor_config['sync_interval'], $secs ); ?>>
                                    <?php echo esc_html( $lbl ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="my_auto_sync">Otomatik Sync</label></th>
                    <td>
                        <label>
                            <input type="checkbox" id="my_auto_sync" name="auto_sync" value="1"
                                   <?php checked( $vendor_config['auto_sync'] ); ?> />
                            Otomatik senkronizasyonu etkinleştir
                        </label>
                    </td>
                </tr>
            </table>
            <input type="hidden" name="vendor_id" value="<?php echo esc_attr( $current_user_id ); ?>" />
            <div class="tws-actions">
                <button type="submit" class="button button-primary">Kaydet</button>
                <button type="button" id="tws-test-btn" class="button button-secondary">Bağlantı Test Et</button>
            </div>
            <div id="tws-test-result" class="tws-notice" style="display:none;"></div>
            <div id="tws-save-result" class="tws-notice" style="display:none;"></div>
        </form>
    </div>

    <div class="tws-card">
        <h2>Senkronizasyon Durumum</h2>
        <div id="tws-engine-badge" class="tws-engine-badge tws-engine-checking">Kontrol ediliyor…</div>
        <div class="tws-status-box" style="margin-top:14px;">
            <div class="tws-stat">
                <span class="tws-stat-label">Son Sync</span>
                <span class="tws-stat-value" id="tws-last-sync" style="font-size:14px;"><?php echo esc_html( $stats['last_sync'] ); ?></span>
            </div>
            <div class="tws-stat">
                <span class="tws-stat-label">Sonraki Sync</span>
                <span class="tws-stat-value" id="tws-next-sync" style="font-size:14px;">—</span>
            </div>
            <div class="tws-stat">
                <span class="tws-stat-label">Ürünlerim</span>
                <span class="tws-stat-value"><?php echo esc_html( $stats['product_count'] ); ?></span>
            </div>
        </div>
        <div id="tws-live-progress" style="display:none; margin:14px 0;">
            <div class="tws-progress-track"><div class="tws-progress-fill" id="tws-progress-fill" style="width:0%"></div></div>
            <p id="tws-progress-text" class="tws-progress-text">İşleniyor…</p>
        </div>
        <div class="tws-actions">
            <button type="button" id="tws-sync-btn" class="button button-primary">Şimdi Senkronize Et</button>
            <span id="tws-sync-spinner" class="spinner" style="float:none; margin-top:0; display:none;"></span>
        </div>
        <div id="tws-sync-result" class="tws-notice" style="display:none;"></div>
    </div>

</div>

<!-- Vendor'ın kendi günlüğü -->
<div class="tws-card tws-card-full">
    <h2>Senkronizasyon Günlüğüm</h2>
    <table class="widefat tws-log-table">
        <thead><tr><th>Tarih / Saat</th><th>Durum</th><th>Mesaj</th></tr></thead>
        <tbody>
            <?php
            $logs = TWS_Sync_Manager::get_log( $current_user_id );
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

<?php

/* ================================================================
   ADMİN: Vendor Yönetimi
   ================================================================ */
elseif ( $active_tab === 'vendors' && $is_admin ) : ?>

<div class="tws-card tws-card-full">
    <h2>Vendor Yönetimi</h2>
    <p class="description">Her vendor'ın Trendyol entegrasyon ayarlarını yönetin. Yeşil rozet = API yapılandırılmış.</p>
    <div style="margin-bottom:12px;">
        <input type="text" id="tws-vendor-search" class="regular-text" placeholder="Ad veya e-posta ile filtrele…" />
    </div>
    <table class="widefat striped tws-vendor-table" id="tws-vendor-table">
        <thead>
            <tr>
                <th>ID</th><th>Ad / E-posta</th><th>Rol</th><th>API Durumu</th>
                <th>Son Sync</th><th>Ürün</th><th>Durum</th><th>İşlem</th>
            </tr>
        </thead>
        <tbody id="tws-vendor-tbody">
            <tr><td colspan="8" class="tws-loading">Yükleniyor…</td></tr>
        </tbody>
    </table>
</div>

<!-- Vendor Düzenle Modal -->
<div id="tws-vendor-modal" class="tws-modal-overlay" style="display:none;">
    <div class="tws-modal" style="width:580px;">
        <button type="button" class="tws-modal-close">✕</button>
        <h2 id="tws-vm-title">Vendor Ayarları</h2>
        <form id="tws-vendor-config-form">
            <table class="form-table">
                <tr>
                    <th><label>Tedarikçi ID</label></th>
                    <td><input type="text" id="vm_supplier_id" name="supplier_id" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label>API Key</label></th>
                    <td><input type="text" id="vm_api_key" name="api_key" class="regular-text" autocomplete="off" /></td>
                </tr>
                <tr>
                    <th><label>API Secret</label></th>
                    <td><input type="password" id="vm_api_secret" name="api_secret" class="regular-text" autocomplete="off" /></td>
                </tr>
                <tr>
                    <th><label>Fiyat Marjı (%)</label></th>
                    <td>
                        <div class="tws-input-group">
                            <input type="number" id="vm_price_markup" name="price_markup" class="small-text" step="0.01" min="-90" max="500" />
                            <span class="tws-input-suffix">%</span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label>Senkronizasyon Sıklığı</label></th>
                    <td>
                        <select id="vm_sync_interval" name="sync_interval">
                            <?php foreach ( $intervals as $secs => $lbl ) : ?>
                                <option value="<?php echo $secs; ?>"><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>Otomatik Sync</label></th>
                    <td><label><input type="checkbox" id="vm_auto_sync" name="auto_sync" value="1" /> Etkin</label></td>
                </tr>
            </table>
            <input type="hidden" id="vm_vendor_id" name="vendor_id" value="" />
            <div id="tws-vm-result" class="tws-notice" style="display:none;"></div>
        </form>
        <div class="tws-modal-footer">
            <button type="button" id="tws-vm-test-btn" class="button button-secondary">Bağlantı Test Et</button>
            <button type="button" id="tws-vm-save-btn" class="button button-primary">Kaydet</button>
            <button type="button" id="tws-vm-sync-btn" class="button">Şimdi Sync Et</button>
            <button type="button" class="button tws-modal-close-btn">Kapat</button>
        </div>
        <!-- Vendor Sync İlerleme -->
        <div id="tws-vm-progress" style="display:none; margin-top:14px;">
            <div class="tws-progress-track"><div class="tws-progress-fill" id="tws-vm-progress-fill" style="width:0%"></div></div>
            <p id="tws-vm-progress-text" class="tws-progress-text">İşleniyor…</p>
        </div>

        <!-- Vendor Günlüğü -->
        <div id="tws-vm-log-section" style="display:none; margin-top:16px;">
            <h3>Son Günlük</h3>
            <table class="widefat tws-log-table" style="font-size:12px;">
                <thead><tr><th>Saat</th><th>Durum</th><th>Mesaj</th></tr></thead>
                <tbody id="tws-vm-log-body"></tbody>
            </table>
        </div>
    </div>
</div>

<?php

/* ================================================================
   ADMİN: İçe Aktarma (Trendyol → WC) + Sistem Cron
   ================================================================ */
elseif ( $active_tab === 'import' && $is_admin ) : ?>

<div class="tws-grid">
    <div class="tws-card">
        <h2>Kendi API Ayarlarım (Admin)</h2>
        <form id="tws-my-config-form">
            <table class="form-table">
                <tr>
                    <th><label for="my_supplier_id">Tedarikçi ID</label></th>
                    <td><input type="text" id="my_supplier_id" name="supplier_id" value="<?php echo esc_attr( $vendor_config['supplier_id'] ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="my_api_key">API Key</label></th>
                    <td><input type="text" id="my_api_key" name="api_key" value="<?php echo esc_attr( $vendor_config['api_key'] ); ?>" class="regular-text" autocomplete="off" /></td>
                </tr>
                <tr>
                    <th><label for="my_api_secret">API Secret</label></th>
                    <td><input type="password" id="my_api_secret" name="api_secret" value="<?php echo esc_attr( $vendor_config['api_secret'] ); ?>" class="regular-text" autocomplete="off" /></td>
                </tr>
                <tr>
                    <th><label for="my_price_markup">Fiyat Marjı (%)</label></th>
                    <td>
                        <div class="tws-input-group">
                            <input type="number" id="my_price_markup" name="price_markup" value="<?php echo esc_attr( $vendor_config['price_markup'] ); ?>" class="small-text" step="0.01" />
                            <span class="tws-input-suffix">%</span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th><label for="my_sync_interval">Senkronizasyon Sıklığı</label></th>
                    <td>
                        <select id="my_sync_interval" name="sync_interval">
                            <?php foreach ( $intervals as $secs => $lbl ) : ?>
                                <option value="<?php echo $secs; ?>" <?php selected( $vendor_config['sync_interval'], $secs ); ?>><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label>Otomatik Sync</label></th>
                    <td><label><input type="checkbox" id="my_auto_sync" name="auto_sync" value="1" <?php checked( $vendor_config['auto_sync'] ); ?> /> Etkin</label></td>
                </tr>
            </table>
            <input type="hidden" name="vendor_id" value="<?php echo esc_attr( $current_user_id ); ?>" />
            <div class="tws-actions">
                <button type="submit" class="button button-primary">Kaydet</button>
                <button type="button" id="tws-test-btn" class="button button-secondary">Bağlantı Test Et</button>
            </div>
            <div id="tws-test-result" class="tws-notice" style="display:none;"></div>
            <div id="tws-save-result" class="tws-notice" style="display:none;"></div>
        </form>
    </div>

    <div class="tws-card">
        <h2>Senkronizasyon Durumu</h2>
        <div id="tws-engine-badge" class="tws-engine-badge tws-engine-checking">Kontrol ediliyor…</div>
        <div class="tws-status-box" style="margin-top:14px;">
            <div class="tws-stat">
                <span class="tws-stat-label">Son Sync</span>
                <span class="tws-stat-value" id="tws-last-sync" style="font-size:14px;"><?php echo esc_html( TWS_Vendor_Manager::get_vendor_stats( $current_user_id )['last_sync'] ); ?></span>
            </div>
            <div class="tws-stat">
                <span class="tws-stat-label">Sonraki Sync</span>
                <span class="tws-stat-value" id="tws-next-sync" style="font-size:14px;">—</span>
            </div>
        </div>
        <div id="tws-live-progress" style="display:none; margin:14px 0;">
            <div class="tws-progress-track"><div class="tws-progress-fill" id="tws-progress-fill" style="width:0%"></div></div>
            <p id="tws-progress-text" class="tws-progress-text">İşleniyor…</p>
        </div>
        <div class="tws-actions">
            <button type="button" id="tws-sync-btn" class="button button-primary">Şimdi Senkronize Et</button>
            <span id="tws-sync-spinner" class="spinner" style="float:none; margin-top:0; display:none;"></span>
        </div>
        <div id="tws-sync-result" class="tws-notice" style="display:none;"></div>
    </div>
</div>

<!-- Sistem Cron -->
<div class="tws-card tws-card-full">
    <h2>⚙ Sistem Cron Kurulumu</h2>
    <p>Action Scheduler'ın ziyaretçisiz çalışması için sunucu cron job'u ekleyin.</p>
    <div class="tws-cron-steps">
        <div class="tws-cron-step">
            <div class="tws-step-num">1</div>
            <div class="tws-step-body">
                <strong><code>wp-config.php</code>'ye ekleyin</strong>
                <div class="tws-code-block">
                    <code>define( 'DISABLE_WP_CRON', true );</code>
                    <?php if ( $wp_cron_off ) : ?>
                        <span class="tws-inline-badge tws-badge-success">✓ Tanımlı</span>
                    <?php else : ?>
                        <span class="tws-inline-badge tws-badge-warning">Henüz eklenmedi</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="tws-cron-step">
            <div class="tws-step-num">2</div>
            <div class="tws-step-body">
                <strong>Crontab komutunu ekleyin (her 5 dakika)</strong>
                <div class="tws-cron-tabs">
                    <button type="button" class="tws-cron-tab-btn active" data-show="curl">cURL</button>
                    <button type="button" class="tws-cron-tab-btn" data-show="wget">wget</button>
                    <button type="button" class="tws-cron-tab-btn" data-show="wpcli">WP-CLI</button>
                </div>
                <div class="tws-code-block" id="tws-cron-curl">
                    <code>*/5 * * * * curl -s "<?php echo esc_url( site_url( '/wp-cron.php?doing_wp_cron' ) ); ?>" > /dev/null 2>&1</code>
                    <button type="button" class="tws-copy-btn" data-target="tws-cron-curl">Kopyala</button>
                </div>
                <div class="tws-code-block" id="tws-cron-wget" style="display:none;">
                    <code>*/5 * * * * wget -q -O - "<?php echo esc_url( site_url( '/wp-cron.php?doing_wp_cron' ) ); ?>" > /dev/null 2>&1</code>
                    <button type="button" class="tws-copy-btn" data-target="tws-cron-wget">Kopyala</button>
                </div>
                <div class="tws-code-block" id="tws-cron-wpcli" style="display:none;">
                    <code>*/5 * * * * cd <?php echo esc_html( ABSPATH ); ?> && wp cron event run --due-now --allow-root > /dev/null 2>&1</code>
                    <button type="button" class="tws-copy-btn" data-target="tws-cron-wpcli">Kopyala</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Kategori Eşleştirme -->
<div class="tws-card tws-card-full">
    <h2>Kategori Eşleştirme</h2>
    <?php $discovered = TWS_Category_Mapper::get_discovered(); ?>
    <?php if ( ! empty( $discovered ) ) : ?>
    <div class="tws-discovered-hint">
        <strong>Keşfedilen kategoriler:</strong>
        <div class="tws-tag-list">
            <?php foreach ( $discovered as $cat ) : ?>
                <span class="tws-tag" data-cat="<?php echo esc_attr( $cat ); ?>"><?php echo esc_html( $cat ); ?></span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <table class="widefat tws-map-table">
        <thead><tr><th style="width:45%">Trendyol</th><th style="width:45%">WooCommerce</th><th></th></tr></thead>
        <tbody id="tws-cat-map-body"></tbody>
    </table>
    <div class="tws-actions" style="margin-top:14px;">
        <button type="button" id="tws-add-map-row" class="button">+ Satır Ekle</button>
        <button type="button" id="tws-save-map-btn" class="button button-primary">Eşleştirmeyi Kaydet</button>
    </div>
    <div id="tws-map-result" class="tws-notice" style="display:none;"></div>
</div>

<!-- Admin kendi günlüğü -->
<div class="tws-card tws-card-full">
    <h2>Senkronizasyon Günlüğüm</h2>
    <table class="widefat tws-log-table">
        <thead><tr><th>Tarih / Saat</th><th>Durum</th><th>Mesaj</th></tr></thead>
        <tbody>
            <?php
            $logs = TWS_Sync_Manager::get_log( $current_user_id );
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

<?php

/* ================================================================
   Export (WC → Trendyol) — ortak tab
   ================================================================ */
elseif ( $active_tab === 'export' ) : ?>

<div class="tws-card tws-card-full">
    <h2>WooCommerce Ürünlerini Trendyol'a Aktar</h2>
    <div class="tws-export-toolbar">
        <input type="text" id="tws-product-search" class="regular-text" placeholder="Ürün adı veya SKU ara…" />
        <button type="button" id="tws-product-search-btn" class="button">Ara</button>
    </div>
    <table class="widefat striped tws-export-table">
        <thead>
            <tr><th style="width:52px"></th><th>Ürün</th><th>SKU</th><th>Fiyat</th><th>Stok</th><th>Trendyol Durumu</th><th>İşlem</th></tr>
        </thead>
        <tbody id="tws-export-tbody"><tr><td colspan="7" class="tws-loading">Yükleniyor…</td></tr></tbody>
    </table>
    <div class="tws-pagination" id="tws-export-pagination"></div>
</div>

<!-- Export Modal -->
<div id="tws-export-modal" class="tws-modal-overlay" style="display:none;">
    <div class="tws-modal">
        <button type="button" class="tws-modal-close">✕</button>
        <h2 id="tws-modal-title">Ürünü Trendyol'a Aktar</h2>
        <table class="form-table tws-modal-form">
            <tr>
                <th>Trendyol Kategorisi <span class="tws-req">*</span></th>
                <td>
                    <div class="tws-ac-wrap">
                        <input type="text" id="tws-cat-search" class="regular-text" placeholder="Kategori adı…" autocomplete="off" />
                        <input type="hidden" id="tws-cat-id" />
                        <div class="tws-ac-dropdown" id="tws-cat-dropdown"></div>
                    </div>
                    <span class="tws-selected-label" id="tws-cat-label"></span>
                </td>
            </tr>
            <tr>
                <th>Marka <span class="tws-req">*</span></th>
                <td>
                    <div class="tws-ac-wrap">
                        <input type="text" id="tws-brand-search" class="regular-text" placeholder="Marka adı…" autocomplete="off" />
                        <input type="hidden" id="tws-brand-id" />
                        <div class="tws-ac-dropdown" id="tws-brand-dropdown"></div>
                    </div>
                    <span class="tws-selected-label" id="tws-brand-label"></span>
                </td>
            </tr>
            <tr>
                <th>Kargo Şirketi <span class="tws-req">*</span></th>
                <td><select id="tws-cargo-id" class="regular-text"><option value="">Yükleniyor…</option></select></td>
            </tr>
            <tr>
                <th>Desi</th>
                <td><input type="number" id="tws-desi" class="small-text" value="1" min="0.1" step="0.1" /></td>
            </tr>
        </table>
        <div id="tws-attributes-section" style="display:none;">
            <h3>Kategori Özellikleri</h3>
            <table class="form-table" id="tws-attributes-table"></table>
        </div>
        <div id="tws-export-modal-result" class="tws-notice" style="display:none;"></div>
        <div class="tws-modal-footer">
            <button type="button" id="tws-do-export" class="button button-primary button-large">Trendyol'a Aktar</button>
            <button type="button" class="button tws-modal-close-btn">İptal</button>
        </div>
    </div>
</div>

<?php endif; ?>
</div>
