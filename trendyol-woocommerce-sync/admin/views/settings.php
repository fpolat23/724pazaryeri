<?php defined( 'ABSPATH' ) || exit; ?>

<div class="wrap tws-wrap">
    <h1>
        <span class="tws-logo">🔄</span>
        Trendyol WooCommerce Sync
    </h1>

    <div class="tws-grid">
        <!-- Sol: Ayarlar -->
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
                            <p class="description">Trendyol Entegrasyon Paneli'ndeki Tedarikçi (Supplier) ID'niz.</p>
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
                                $intervals = [
                                    'hourly'     => 'Her saat',
                                    'twicedaily' => 'Günde 2 kez',
                                    'daily'      => 'Günde 1 kez',
                                ];
                                $current = get_option( 'tws_sync_interval', 'hourly' );
                                foreach ( $intervals as $value => $label ) {
                                    printf(
                                        '<option value="%s"%s>%s</option>',
                                        esc_attr( $value ),
                                        selected( $current, $value, false ),
                                        esc_html( $label )
                                    );
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>

                <div class="tws-actions">
                    <?php submit_button( 'Kaydet', 'primary', 'submit', false ); ?>
                    <button type="button" id="tws-test-btn" class="button button-secondary">
                        Bağlantıyı Test Et
                    </button>
                </div>
                <div id="tws-test-result" class="tws-notice" style="display:none;"></div>
            </form>
        </div>

        <!-- Sağ: Durum & Manuel Sync -->
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
                    <span class="tws-stat-label">Toplam Aktarılan Ürün</span>
                    <span class="tws-stat-value">
                        <?php
                        echo (int) $wpdb->get_var(
                            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_trendyol_product_id'"
                        );
                        ?>
                    </span>
                </div>
            </div>

            <div class="tws-actions">
                <button type="button" id="tws-sync-btn" class="button button-primary button-hero">
                    Manuel Senkronize Et
                </button>
            </div>

            <div id="tws-sync-progress" style="display:none;">
                <div class="tws-progress-bar"><div class="tws-progress-fill"></div></div>
                <p id="tws-sync-message">Trendyol'dan ürünler alınıyor…</p>
            </div>

            <div id="tws-sync-result" class="tws-notice" style="display:none;"></div>
        </div>
    </div>

    <!-- Log -->
    <div class="tws-card tws-card-full">
        <h2>Senkronizasyon Günlüğü</h2>
        <table class="widefat tws-log-table">
            <thead>
                <tr>
                    <th>Tarih / Saat</th>
                    <th>Durum</th>
                    <th>Mesaj</th>
                </tr>
            </thead>
            <tbody id="tws-log-body">
                <?php
                $logs = get_option( TWS_Sync_Manager::LOG_OPTION, [] );
                if ( empty( $logs ) ) {
                    echo '<tr><td colspan="3">Henüz kayıt yok.</td></tr>';
                } else {
                    foreach ( $logs as $log ) {
                        $badge_class = 'tws-badge-' . esc_attr( $log['level'] );
                        printf(
                            '<tr><td>%s</td><td><span class="tws-badge %s">%s</span></td><td>%s</td></tr>',
                            esc_html( $log['time'] ),
                            $badge_class,
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
