<?php
defined( 'ABSPATH' ) || exit;

class WC_PSS_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		add_action( 'wp_ajax_wc_pss_save_source',   [ __CLASS__, 'ajax_save_source' ] );
		add_action( 'wp_ajax_wc_pss_delete_source', [ __CLASS__, 'ajax_delete_source' ] );
		add_action( 'wp_ajax_wc_pss_test_source',   [ __CLASS__, 'ajax_test_source' ] );
		add_action( 'wp_ajax_wc_pss_start_sync',    [ __CLASS__, 'ajax_start_sync' ] );
		add_action( 'wp_ajax_wc_pss_job_status',    [ __CLASS__, 'ajax_job_status' ] );
		add_action( 'wp_ajax_wc_pss_cancel_job',    [ __CLASS__, 'ajax_cancel_job' ] );
		add_action( 'wp_ajax_wc_pss_delete_job',    [ __CLASS__, 'ajax_delete_job' ] );
	}

	public static function register_menu(): void {
		global $menu;
		$wc_exists = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && $item[2] === 'woocommerce' ) { $wc_exists = true; break; }
			}
		}
		if ( $wc_exists ) {
			add_submenu_page( 'woocommerce', 'Fiyat & Stok Sync', 'Fiyat & Stok Sync', 'manage_options', 'wc-pss', [ __CLASS__, 'render_page' ] );
		} else {
			add_menu_page( 'Fiyat & Stok Senkronizasyonu', 'Fiyat & Stok Sync', 'manage_options', 'wc-pss', [ __CLASS__, 'render_page' ], 'dashicons-update-alt', 58 );
		}
	}

	public static function enqueue( string $hook ): void {
		if ( strpos( $hook, 'wc-pss' ) === false ) return;
		wp_enqueue_style( 'wc-pss', WC_PSS_URL . 'assets/css/admin.css', [], WC_PSS_VERSION );
		wp_enqueue_script( 'wc-pss', WC_PSS_URL . 'assets/js/admin.js', [ 'jquery' ], WC_PSS_VERSION, true );
		wp_localize_script( 'wc-pss', 'wcPss', [
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'wc_pss' ),
			'confirmDelete' => 'Bu kaydı kalıcı olarak silmek istediğinizden emin misiniz?',
			'confirmCancel' => 'Bu işlemi iptal etmek istediğinizden emin misiniz?',
		] );
	}

	// ----------------------------------------------------------------
	// Page render
	// ----------------------------------------------------------------

	public static function render_page(): void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'sources';
		$page_url = menu_page_url( 'wc-pss', false );
		?>
		<div class="wrap wc-pss-wrap">
			<h1>🔄 WooCommerce Fiyat &amp; Stok Senkronizasyonu</h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( [ 'sources' => 'Kaynak Siteler', 'sync' => 'Senkronizasyon', 'history' => 'İşlem Geçmişi' ] as $slug => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $page_url ) ); ?>"
				   class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
				<?php endforeach; ?>
			</nav>
			<div class="wc-pss-content">
				<?php
				if ( $tab === 'sources' )      self::render_sources();
				elseif ( $tab === 'sync' )     self::render_sync();
				else                           self::render_history();
				?>
			</div>
		</div>
		<?php
	}

	// ----------------------------------------------------------------
	// Sources tab
	// ----------------------------------------------------------------

	private static function render_sources(): void {
		$sources  = WC_PSS_Source_Manager::all();
		$edit_id  = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : '';
		$edit_src = $edit_id === 'new' ? [] : ( $edit_id ? ( WC_PSS_Source_Manager::get( $edit_id ) ?? [] ) : null );
		?>
		<div class="wc-pss-card">
			<div style="display:flex;justify-content:space-between;align-items:center;">
				<h2 style="margin:0">Kaynak Siteler</h2>
				<?php if ( $edit_src === null ) : ?>
				<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'sources', 'edit' => 'new' ], menu_page_url( 'wc-pss', false ) ) ); ?>"
				   class="button button-primary">+ Kaynak Ekle</a>
				<?php endif; ?>
			</div>

			<?php if ( $edit_src !== null ) : ?>
			<!-- Add/Edit form -->
			<form id="wc-pss-source-form" style="margin-top:20px">
				<input type="hidden" name="id" value="<?php echo esc_attr( $edit_src['id'] ?? '' ); ?>">
				<table class="form-table">
					<tr>
						<th>Kaynak Adı <span class="description">(zorunlu)</span></th>
						<td><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr( $edit_src['name'] ?? '' ); ?>" placeholder="Tedarikçi A"></td>
					</tr>
					<tr>
						<th>Site URL'si</th>
						<td><input type="url" name="base_url" class="regular-text" required value="<?php echo esc_attr( $edit_src['base_url'] ?? '' ); ?>" placeholder="https://tedarikci.com"></td>
					</tr>
					<tr>
						<th>Giriş Sayfası URL</th>
						<td>
							<input type="url" name="login_url" class="regular-text" value="<?php echo esc_attr( $edit_src['login_url'] ?? '' ); ?>" placeholder="https://tedarikci.com/giris">
							<p class="description">Boş bırakılırsa Site URL + /my-account veya /login denenir.</p>
						</td>
					</tr>
					<tr>
						<th>Kullanıcı Adı (Form Alanı)</th>
						<td><input type="text" name="user_field" class="small-text" value="<?php echo esc_attr( $edit_src['user_field'] ?? 'username' ); ?>" placeholder="username"></td>
					</tr>
					<tr>
						<th>Şifre (Form Alanı)</th>
						<td><input type="text" name="pass_field" class="small-text" value="<?php echo esc_attr( $edit_src['pass_field'] ?? 'password' ); ?>" placeholder="password"></td>
					</tr>
					<tr>
						<th>Ekstra POST Alanları</th>
						<td>
							<textarea name="extra_fields" rows="3" class="regular-text" placeholder="kriter=email&#10;lang=tr"><?php echo esc_textarea( $edit_src['extra_fields'] ?? '' ); ?></textarea>
							<p class="description">Giriş formuna eklenecek ek alanlar (her satıra <code>alan=değer</code>). Var olan alanları geçersiz kılar. Örn: <code>kriter=email</code></p>
						</td>
					</tr>
					<tr>
						<th>Kullanıcı Adı (Değer)</th>
						<td><input type="text" name="username" class="regular-text" value="<?php echo esc_attr( $edit_src['username'] ?? '' ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th>Şifre (Değer)</th>
						<td>
							<input type="password" name="password" class="regular-text" value="" autocomplete="new-password" placeholder="<?php echo ! empty( $edit_src['password_enc'] ) ? '(kayıtlı — değiştirmek için girin)' : ''; ?>">
							<p class="description">Şifre WordPress veritabanında şifrelenmiş olarak saklanır.</p>
						</td>
					</tr>
					<tr>
						<th>Ürün URL Keşfi</th>
						<td>
							<label><input type="radio" name="discovery" value="sitemap" <?php checked( ( $edit_src['discovery'] ?? 'sitemap' ) === 'sitemap' ); ?>> Sitemap (otomatik — önerilen)</label><br>
							<label><input type="radio" name="discovery" value="crawl" <?php checked( ( $edit_src['discovery'] ?? '' ) === 'crawl' ); ?>> Sayfa Crawl (sitemap yoksa)</label>
						</td>
					</tr>
					<tr class="wc-pss-crawl-row" style="<?php echo ( ( $edit_src['discovery'] ?? 'sitemap' ) !== 'crawl' ) ? 'display:none' : ''; ?>">
						<th>Crawl Başlangıç URL</th>
						<td>
							<input type="url" name="crawl_url" class="regular-text" value="<?php echo esc_attr( $edit_src['crawl_url'] ?? '' ); ?>" placeholder="https://tedarikci.com/urunler">
							<p class="description">Ürünlerin listelendiği sayfa (sayfalama takip edilir).</p>
						</td>
					</tr>
					<tr class="wc-pss-crawl-row" style="<?php echo ( ( $edit_src['discovery'] ?? 'sitemap' ) !== 'crawl' ) ? 'display:none' : ''; ?>">
						<th>Ürün URL Deseni</th>
						<td>
							<input type="text" name="url_pattern" class="regular-text" value="<?php echo esc_attr( $edit_src['url_pattern'] ?? '/urun/' ); ?>" placeholder="/urun/">
							<p class="description">URL'de bu metin geçen sayfalar ürün sayfası sayılır (ör. <code>/urun/</code>, <code>/product/</code>).</p>
						</td>
					</tr>
				</table>

				<details style="margin-top:8px;">
					<summary style="cursor:pointer;font-weight:600;">Gelişmiş: CSS Seçiciler (isteğe bağlı)</summary>
					<p class="description" style="margin:8px 0">Sayfada JSON-LD schema yoksa ve otomatik algılama çalışmıyorsa, bu alanlarla hangi elementin fiyat/stok/SKU içerdiğini belirtin.<br>
					Örnekler: <code>.product-price</code>, <code>#sku-val</code>, <code>span.price ins</code></p>
					<table class="form-table" style="margin-top:0">
						<tr><th>SKU Seçici</th><td><input type="text" name="sku_sel" class="regular-text" value="<?php echo esc_attr( $edit_src['sku_sel'] ?? '' ); ?>"></td></tr>
						<tr><th>Güncel Fiyat Seçici</th><td><input type="text" name="price_sel" class="regular-text" value="<?php echo esc_attr( $edit_src['price_sel'] ?? '' ); ?>"></td></tr>
						<tr><th>Asıl Fiyat Seçici <small>(indirimli varsa)</small></th><td><input type="text" name="reg_price_sel" class="regular-text" value="<?php echo esc_attr( $edit_src['reg_price_sel'] ?? '' ); ?>"></td></tr>
						<tr><th>Stok Seçici</th><td><input type="text" name="stock_sel" class="regular-text" value="<?php echo esc_attr( $edit_src['stock_sel'] ?? '' ); ?>"></td></tr>
					</table>
				</details>

				<div class="wc-pss-rules-section">
					<h3>Fiyat Artış Kuralları <span class="description" style="font-size:13px;font-weight:400">(isteğe bağlı)</span></h3>
					<p class="description">Kaynak sitedeki fiyat aralığına göre % artış uygula. Aynı oran indirimli fiyata da uygulanır. Boş bırakılırsa fiyatlar olduğu gibi aktarılır.</p>
					<table class="wc-pss-rules-table">
						<thead>
							<tr>
								<th>Min Fiyat (₺)</th>
								<th>Max Fiyat (₺)</th>
								<th>Artış (%)</th>
								<th></th>
							</tr>
						</thead>
						<tbody id="pss-rules-body">
						<?php
						$existing_rules = $edit_src['price_rules'] ?? [];
						foreach ( $existing_rules as $ri => $rule ) :
						?>
						<tr>
							<td><input type="number" name="price_rules[<?php echo $ri; ?>][min]" value="<?php echo esc_attr( $rule['min'] ?? '0' ); ?>" min="0" step="0.01" class="small-text" placeholder="0"></td>
							<td><input type="number" name="price_rules[<?php echo $ri; ?>][max]" value="<?php echo esc_attr( $rule['max'] ?? '' ); ?>" min="0" step="0.01" class="small-text" placeholder="∞"></td>
							<td><input type="number" name="price_rules[<?php echo $ri; ?>][pct]" value="<?php echo esc_attr( $rule['pct'] ?? '' ); ?>" min="-100" max="10000" step="0.1" class="small-text"> %</td>
							<td><button type="button" class="button button-small js-remove-rule">✕</button></td>
						</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<button type="button" id="add-price-rule" class="button" style="margin-top:8px;">+ Kural Ekle</button>
					<p class="description" style="margin-top:6px;">Max fiyat boş = üst sınır yok. Kurallar yukarıdan aşağıya kontrol edilir, ilk eşleşen uygulanır.</p>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary button-large">💾 Kaydet</button>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'sources', menu_page_url( 'wc-pss', false ) ) ); ?>" class="button">İptal</a>
					<span class="spinner"></span>
				</p>
				<div id="pss-source-msg"></div>
			</form>

			<?php else : ?>
			<!-- Sources list -->
			<?php if ( empty( $sources ) ) : ?>
			<p style="margin-top:16px">Henüz kaynak site eklenmemiş. <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'sources', 'edit' => 'new' ], menu_page_url( 'wc-pss', false ) ) ); ?>">İlk kaynağı ekleyin →</a></p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped" style="margin-top:16px">
				<thead>
					<tr><th>Ad</th><th>URL</th><th>Giriş</th><th>Keşif</th><th>İşlemler</th></tr>
				</thead>
				<tbody>
				<?php foreach ( $sources as $src ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $src['name'] ?? '' ); ?></strong></td>
					<td><?php echo esc_html( $src['base_url'] ?? '' ); ?></td>
					<td><?php echo ! empty( $src['username'] ) ? esc_html( $src['username'] ) : '<span style="color:#888">—</span>'; ?></td>
					<td><?php echo ( $src['discovery'] ?? '' ) === 'crawl' ? 'Crawl' : 'Sitemap'; ?></td>
					<td class="wc-pss-actions">
						<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'sources', 'edit' => $src['id'] ], menu_page_url( 'wc-pss', false ) ) ); ?>" class="button button-small">✏ Düzenle</a>
						<button class="button button-small js-pss-test-source" data-id="<?php echo esc_attr( $src['id'] ); ?>">🔍 Test Et</button>
						<button class="button button-small js-pss-del-source" data-id="<?php echo esc_attr( $src['id'] ); ?>" style="color:#dc3232">🗑 Sil</button>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
			<div id="pss-test-result" style="display:none;margin-top:16px;"></div>
			<?php endif; ?>
		</div>
		<?php
	}

	// ----------------------------------------------------------------
	// Sync tab
	// ----------------------------------------------------------------

	private static function render_sync(): void {
		$sources = WC_PSS_Source_Manager::all();
		?>
		<div class="wc-pss-card">
			<h2>Senkronizasyon Başlat</h2>

			<?php if ( empty( $sources ) ) : ?>
			<p>Önce <a href="<?php echo esc_url( add_query_arg( 'tab', 'sources', menu_page_url( 'wc-pss', false ) ) ); ?>">Kaynak Siteler</a> sekmesinden en az bir kaynak ekleyin.</p>
			<?php else : ?>
			<p class="description">
				Seçilen kaynak sitenin ürün sayfaları arka planda tek tek çekilir. Tarayıcıyı kapatabilirsiniz.<br>
				Tamamlanınca <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', menu_page_url( 'wc-pss', false ) ) ); ?>">İşlem Geçmişi</a>'nden sonucu görebilirsiniz.
			</p>

			<form id="wc-pss-sync-form" style="margin-top:16px">
				<table class="form-table">
					<tr>
						<th>Kaynak Site</th>
						<td>
							<select name="source_id" id="pss-source-select" required>
								<option value="">— Seçin —</option>
								<?php foreach ( $sources as $src ) : ?>
								<option value="<?php echo esc_attr( $src['id'] ); ?>"><?php echo esc_html( $src['name'] . ' (' . $src['base_url'] . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th>Eşleştirme</th>
						<td>
							<label><input type="checkbox" name="match_by_name" value="1"> SKU bulunamazsa <strong>ürün adıyla</strong> da eşleştir</label>
							<p class="description">Varsayılan: yalnızca SKU ile eşleştirme.</p>
						</td>
					</tr>
					<tr>
						<th>Güncelleme Kapsamı</th>
						<td>
							<label><input type="checkbox" name="update_prices" value="1" checked> <strong>Fiyatları güncelle</strong></label><br>
							<label><input type="checkbox" name="update_stock"  value="1" checked> <strong>Stoğu güncelle</strong></label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" id="btn-sync" class="button button-primary button-large">▶ Senkronizasyonu Başlat</button>
					<span class="spinner"></span>
				</p>
			</form>

			<div id="pss-progress" class="wc-pss-progress-wrap" style="display:none;">
				<div class="wc-pss-bg-note">İşlem arka planda devam ediyor — sayfayı kapatabilirsiniz.</div>
				<div class="wc-pss-progress-outer"><div class="wc-pss-progress-inner" style="width:0%"></div></div>
				<div class="wc-pss-progress-text">Başlatılıyor…</div>
				<div class="wc-pss-progress-status"></div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// ----------------------------------------------------------------
	// History tab
	// ----------------------------------------------------------------

	private static function render_history(): void {
		$jobs = WC_PSS_Job_Manager::get_recent( 30 );
		?>
		<div class="wc-pss-card">
			<h2>İşlem Geçmişi</h2>
			<?php if ( empty( $jobs ) ) : ?>
				<p>Henüz senkronizasyon başlatılmamış.</p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:36px">#</th>
						<th>Kaynak</th>
						<th>Durum</th>
						<th>İlerleme</th>
						<th>Güncellendi</th>
						<th>Bulunamadı</th>
						<th>Hata</th>
						<th>Tarih</th>
						<th>İşlem</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $jobs as $job ) :
					$pct      = $job->total > 0 ? round( $job->processed / $job->total * 100 ) : ( $job->status === 'completed' ? 100 : 0 );
					$results  = json_decode( $job->results ?: '{}', true ) ?: [];
					$errors   = json_decode( $job->errors  ?: '[]', true ) ?: [];
					$options  = json_decode( $job->options ?: '{}', true ) ?: [];
					$src_name = '';
					if ( ! empty( $options['source_id'] ) ) {
						$src = WC_PSS_Source_Manager::get( $options['source_id'] );
						$src_name = $src ? $src['name'] : '(silindi)';
					}
					try {
						$tz = new DateTimeZone( wp_timezone_string() );
						$dt = new DateTime( $job->created_at, new DateTimeZone( 'UTC' ) );
						$dt->setTimezone( $tz );
						$date_str = $dt->format( 'd.m.Y H:i' );
					} catch ( Exception $e ) {
						$date_str = $job->created_at;
					}
				?>
				<tr>
					<td><?php echo esc_html( $job->id ); ?></td>
					<td><?php echo esc_html( $src_name ); ?></td>
					<td>
						<span class="wc-pss-badge wc-pss-badge-<?php echo esc_attr( $job->status ); ?>">
							<?php echo esc_html( self::status_label( $job->status ) ); ?>
						</span>
					</td>
					<td>
						<div class="wc-pss-mini-outer"><div class="wc-pss-mini-inner" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
						<small><?php echo esc_html( $job->processed ); ?> / <?php echo esc_html( $job->total ); ?></small>
					</td>
					<td><strong style="color:#1e6f3e"><?php echo esc_html( $results['updated']   ?? 0 ); ?></strong></td>
					<td><span style="color:#856404"><?php echo esc_html( $results['not_found'] ?? 0 ); ?></span></td>
					<td>
						<?php if ( $errors ) : ?>
							<span style="color:#dc3232"><?php echo esc_html( count( $errors ) ); ?> hata</span>
							<?php if ( $job->status === 'failed' && ! empty( $errors[0] ) ) : ?>
							<br><small style="color:#dc3232;word-break:break-word;max-width:220px;display:block"><?php echo esc_html( mb_substr( $errors[0], 0, 120 ) ); ?></small>
							<?php endif; ?>
						<?php else : ?>—<?php endif; ?>
					</td>
					<td><?php echo esc_html( $date_str ); ?></td>
					<td class="wc-pss-actions">
						<?php if ( in_array( $job->status, [ 'discovering', 'processing' ], true ) ) : ?>
							<button class="button button-small js-pss-cancel" data-job-id="<?php echo esc_attr( $job->id ); ?>" style="color:#dc3232">✕ İptal</button>
						<?php else : ?>
							<button class="button button-small js-pss-delete" data-job-id="<?php echo esc_attr( $job->id ); ?>">🗑 Sil</button>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function status_label( string $s ): string {
		return [
			'discovering' => 'URL Keşfi',
			'pending'     => 'Bekliyor',
			'processing'  => 'İşleniyor',
			'completed'   => 'Tamamlandı',
			'failed'      => 'Hatalı',
			'cancelled'   => 'İptal Edildi',
		][ $s ] ?? $s;
	}

	// ----------------------------------------------------------------
	// AJAX: Sources
	// ----------------------------------------------------------------

	public static function ajax_save_source(): void {
		check_ajax_referer( 'wc_pss', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

		$raw = $_POST;
		$data = [
			'id'          => preg_replace( '/[^a-zA-Z0-9_-]/', '', $raw['id'] ?? '' ),
			'name'        => sanitize_text_field( $raw['name'] ?? '' ),
			'base_url'    => esc_url_raw( $raw['base_url'] ?? '' ),
			'login_url'   => esc_url_raw( $raw['login_url'] ?? '' ),
			'user_field'  => sanitize_key( $raw['user_field'] ?? 'username' ),
			'pass_field'  => sanitize_key( $raw['pass_field'] ?? 'password' ),
			'username'    => sanitize_text_field( $raw['username'] ?? '' ),
			'discovery'   => in_array( $raw['discovery'] ?? '', [ 'sitemap', 'crawl' ] ) ? $raw['discovery'] : 'sitemap',
			'crawl_url'   => esc_url_raw( $raw['crawl_url'] ?? '' ),
			'url_pattern' => sanitize_text_field( $raw['url_pattern'] ?? '/urun/' ),
			'sku_sel'     => sanitize_text_field( $raw['sku_sel']     ?? '' ),
			'price_sel'   => sanitize_text_field( $raw['price_sel']   ?? '' ),
			'reg_price_sel' => sanitize_text_field( $raw['reg_price_sel'] ?? '' ),
			'stock_sel'   => sanitize_text_field( $raw['stock_sel']   ?? '' ),
			'extra_fields' => sanitize_textarea_field( $raw['extra_fields'] ?? '' ),
		];

		if ( $data['name'] === '' || $data['base_url'] === '' ) {
			wp_send_json_error( [ 'message' => 'Ad ve Site URL zorunludur.' ] );
		}

		// Handle password
		$password = $raw['password'] ?? '';
		if ( $password !== '' ) {
			$data['password_enc'] = WC_PSS_Source_Manager::encrypt( $password );
		} elseif ( $data['id'] ) {
			$existing = WC_PSS_Source_Manager::get( $data['id'] );
			if ( $existing ) $data['password_enc'] = $existing['password_enc'] ?? '';
		}

		// Handle price rules
		$price_rules = [];
		if ( ! empty( $raw['price_rules'] ) && is_array( $raw['price_rules'] ) ) {
			foreach ( $raw['price_rules'] as $rule ) {
				$pct = (float) ( $rule['pct'] ?? 0 );
				if ( $pct == 0 ) continue;
				$price_rules[] = [
					'min' => max( 0, (float) ( $rule['min'] ?? 0 ) ),
					'max' => ( isset( $rule['max'] ) && $rule['max'] !== '' ) ? (float) $rule['max'] : '',
					'pct' => $pct,
				];
			}
		}
		$data['price_rules'] = $price_rules;

		$id = WC_PSS_Source_Manager::save( $data );
		wp_send_json_success( [ 'id' => $id ] );
	}

	public static function ajax_delete_source(): void {
		check_ajax_referer( 'wc_pss', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );
		$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', $_POST['id'] ?? '' );
		if ( ! $id ) wp_send_json_error( [ 'message' => 'ID gerekli.' ] );
		WC_PSS_Source_Manager::delete( $id );
		wp_send_json_success();
	}

	/**
	 * Synchronous connection test — runs inline (no background), returns step-by-step log.
	 * Used for diagnosing login / URL discovery issues.
	 */
	public static function ajax_test_source(): void {
		check_ajax_referer( 'wc_pss', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

		$id     = preg_replace( '/[^a-zA-Z0-9_-]/', '', $_POST['source_id'] ?? '' );
		$source = WC_PSS_Source_Manager::get_with_pass( $id );
		if ( ! $source ) wp_send_json_error( [ 'message' => 'Kaynak bulunamadı.' ] );

		$log    = [];
		$ok     = true;
		$client = new WC_PSS_Http_Client();

		// cURL availability
		$log[] = function_exists( 'curl_init' ) ? '✓ cURL mevcut' : '⚠ cURL yok — wp_remote_get fallback kullanılacak (cookie oturumu olmayabilir)';

		// Login
		if ( ! empty( $source['username'] ) ) {
			$login_url  = $source['login_url'] ?: $source['base_url'];
			$user_field = $source['user_field'] ?: 'username';
			$pass_field = $source['pass_field'] ?: 'password';
			$log[] = 'Giriş deneniyor: ' . $login_url;
			$log[] = '  Kullanıcı alanı: "' . $user_field . '"  |  Şifre alanı: "' . $pass_field . '"';

			// Inspect login page form (reuse what login() will fetch — avoid double fetch)
			$login_page = $client->get( $login_url );
			if ( $login_page ) {
				$has_form = preg_match( '/<form[^>]+/i', $login_page );
				if ( ! $has_form ) {
					$log[] = '⚠ Giriş sayfasında HTML <form> bulunamadı — site JavaScript (AJAX) ile giriş yapıyor olabilir.';
				} else {
					preg_match_all( '/<input([^>]*)\/?>/i', $login_page, $inp_m );
					$inp_summary = [];
					foreach ( $inp_m[1] as $attrs ) {
						$itype = 'text';
						if ( preg_match( '/\btype=["\']([^"\']+)["\']/i', $attrs, $it ) ) $itype = strtolower( $it[1] );
						if ( in_array( $itype, [ 'submit', 'button', 'image', 'reset' ], true ) ) continue;
						$iname = $ival = '';
						if ( preg_match( '/\bname=["\']([^"\']+)["\']/i', $attrs, $in ) ) $iname = $in[1];
						if ( preg_match( '/\bvalue=["\']([^"\']*)["\']/', $attrs, $iv ) ) $ival = $iv[1];
						if ( $iname ) $inp_summary[] = '"' . $iname . '"[' . $itype . ']' . ( $ival !== '' ? '="' . htmlspecialchars_decode( $ival ) . '"' : '' );
					}
					if ( $inp_summary ) {
						$log[] = '  Form alanları: ' . implode( ', ', $inp_summary );
					}
					if ( preg_match( '/<form[^>]+action=["\']([^"\']+)["\']/i', $login_page, $fa_m ) ) {
						$log[] = '  Form action: ' . html_entity_decode( $fa_m[1] );
					}
					// Warn if user_field not in form
					if ( ! preg_match( '/name=["\']' . preg_quote( $user_field, '/' ) . '["\']/', $login_page ) ) {
						$log[] = '  ⚠ "' . $user_field . '" alanı formda bulunamadı — "Kullanıcı Alanı" adını kontrol edin.';
					}
				}
			}

			// Pass pre-fetched page to login so it doesn't fetch again
			$login_ok = $client->login( $source, $login_page );
			if ( ! $login_ok ) {
				$log[] = '✗ Giriş isteği başarısız — URL erişilemiyor veya HTTP hatası';
				$ok = false;
			} else {
				// Verify login by checking if home/base URL now has logged-in content
				$base    = rtrim( $source['base_url'], '/' );
				$chk     = $client->get_info( $base );
				$chk_url = $chk['url'];
				$is_login_page = $chk_url && (
					str_contains( $chk_url, 'giris' ) ||
					str_contains( $chk_url, 'login' ) ||
					str_contains( $chk_url, 'signin' )
				);
				if ( $is_login_page ) {
					$log[] = '✗ Giriş başarısız — kimlik bilgileri yanlış veya alan adları hatalı';
					$log[] = '  Yönlendirilen URL: ' . $chk_url;
					$log[] = '  → Kullanıcı adı/şifre ile "Kullanıcı Alanı" ve "Şifre Alanı" isimlerini kontrol edin.';
					$ok    = false;
				} else {
					$log[] = '✓ Giriş HTTP akışı tamamlandı';
					if ( $chk_url && $chk_url !== $base && $chk_url !== $base . '/' ) {
						$log[] = '  Yönlendirilen URL: ' . $chk_url;
					}
				}
			}
		} else {
			$log[] = 'ℹ Giriş bilgisi girilmemiş — anonim erişim';
		}

		// URL discovery
		$log[] = 'Ürün URL keşfi başlıyor (' . ( $source['discovery'] === 'crawl' ? 'Crawl' : 'Sitemap' ) . ' modu)…';
		$urls = WC_PSS_Scraper::discover_urls( $source, $client );

		if ( empty( $urls ) ) {
			$log[] = '✗ Ürün URL\'i bulunamadı.';
			if ( ( $source['discovery'] ?? 'sitemap' ) !== 'crawl' ) {
				$log[] = '  Denendi: /product-sitemap.xml, /wp-sitemap.xml, /wp-sitemap-posts-product-*.xml, /sitemap_index.xml';
				$log[] = '  → Çözüm: Kaynak ayarlarında keşif yöntemini "Sayfa Crawl" seçin.';
				$log[] = '  → Crawl URL: Giriş yaptıktan sonra ürünlerin listelendiği sayfa.';
				$log[] = '  → URL Deseni: URL\'de geçen ürün tanımlayıcı (ör. /urun/, /product/).';
			} else {
				$log[] = '  Crawl başlangıç URL: ' . ( $source['crawl_url'] ?: '(girilmemiş)' );
				$log[] = '  URL deseni: ' . ( $source['url_pattern'] ?: '(girilmemiş)' );
				$log[] = '  → Crawl URL\'nin doğru olduğundan ve giriş gerektiren bir sayfaysa oturumun açıldığından emin olun.';
			}
			$ok = false;
		} else {
			$log[] = '✓ ' . count( $urls ) . ' ürün URL\'i bulundu.';
			$log[] = '  İlk 3: ' . implode( ' | ', array_slice( $urls, 0, 3 ) );

			// Try parsing first product
			$first    = $urls[0];
			$log[]    = 'İlk ürün parse ediliyor: ' . $first;
			$raw      = $client->get_info( $first );
			$log[]    = '  HTTP durum kodu: ' . $raw['code'] . '  |  Yönlendirilen URL: ' . ( $raw['url'] ?: '—' );
			$is_login = $raw['url'] && (
				str_contains( $raw['url'], 'giris' ) ||
				str_contains( $raw['url'], 'login' ) ||
				str_contains( $raw['url'], 'signin' )
			);
			if ( $is_login ) {
				$log[] = '✗ Ürün sayfası giriş sayfasına yönlendirdi — oturum geçersiz.';
				$log[] = '  → Giriş bilgileri ve alan adlarını kontrol edin.';
				$ok    = false;
			} elseif ( $raw['body'] && preg_match( '/register\s+to\s+see\s+price|giri[sş]\s+yap[a-z]*\s+fiyat|üye\s+ol[a-z]*\s+fiyat|login\s+to\s+see/i', $raw['body'] ) ) {
				$log[] = '✗ Ürün sayfası "fiyatları görmek için giriş yapın" mesajı içeriyor — giriş başarısız.';
				$log[] = '  Olası nedenler:';
				$log[] = '    1) Kullanıcı adı veya şifre yanlış';
				$log[] = '    2) Giriş formu JavaScript (AJAX) tabanlı — plugin klasik HTML form submit destekler';
				$log[] = '    3) "Kullanıcı Alanı" veya "Şifre Alanı" isimleri yanlış (yukarıdaki "Form alan adları" satırına bakın)';
				$ok    = false;
			} elseif ( $raw['code'] === 0 || $raw['body'] === null ) {
				$log[] = '✗ Ürün sayfasına erişilemiyor (HTTP ' . $raw['code'] . ')';
				$ok    = false;
			} else {
				try {
					$data = WC_PSS_Scraper::scrape_product( $first, $source, $client );
					if ( $data ) {
						$log[] = '✓ Parse başarılı';
						$log[] = '  SKU: '   . ( $data['sku']           ?: '(boş — SKU bulunamadı, CSS seçici ekleyin)' );
						$log[] = '  Ad: '    . ( $data['name']          ?: '(boş)' );
						$log[] = '  Fiyat: ' . ( $data['regular_price'] ?: '(boş — fiyat bulunamadı, CSS seçici ekleyin)' );
						$log[] = '  Stok: '  . ( $data['stock_status']  ?: '(boş)' );
						if ( ! $data['sku'] || ! $data['regular_price'] ) {
							$log[] = '  ⚠ Eksik alanlar için "Gelişmiş CSS Seçicileri" bölümünden özel seçici girin.';
						}
					} else {
						$log[] = '✗ Sayfa erişilebilir ancak ürün verisi parse edilemedi.';
						$log[] = '  Sayfa başlığı: ' . ( preg_match( '/<title[^>]*>([^<]+)<\/title>/i', $raw['body'], $tm ) ? trim( $tm[1] ) : '(bulunamadı)' );
						$log[] = '  → JSON-LD şeması yok. "Gelişmiş CSS Seçicileri" bölümünden SKU, fiyat ve stok seçicilerini girin.';
						// Extract HTML snippets around price/SKU/stock keywords to help identify selectors
						$html    = $raw['body'];
						$kw_hits = [];
						foreach ( [ 'fiyat', 'price', 'stok', 'stock', 'sku', 'urun-kodu', 'urun_kodu' ] as $kw ) {
							if ( preg_match( '/(.{0,120}' . preg_quote( $kw, '/' ) . '.{0,120})/is', $html, $km ) ) {
								$snippet = preg_replace( '/\s+/', ' ', strip_tags( $km[1] ) );
								if ( strlen( trim( $snippet ) ) > 5 ) {
									$kw_hits[ $kw ] = trim( $snippet );
								}
							}
						}
						if ( $kw_hits ) {
							$log[] = '  --- Sayfada bulunan anahtar kelime bağlamları (seçici bulmak için) ---';
							foreach ( $kw_hits as $kw => $snip ) {
								$log[] = '  [' . $kw . '] ' . mb_substr( $snip, 0, 200 );
							}
						}
						// Also show class/id attributes near price-looking values
						if ( preg_match_all( '/class=["\']([^"\']*(?:price|fiyat|stok|stock|sku)[^"\']*)["\'][^>]*>([^<]{1,60})/i', $html, $attrs ) ) {
							$log[] = '  --- Fiyat/stok/SKU içeren sınıf adları ---';
							$shown = [];
							foreach ( $attrs[1] as $ci => $cls ) {
								$key = trim( $cls );
								if ( isset( $shown[ $key ] ) ) continue;
								$shown[ $key ] = true;
								$val = trim( strip_tags( $attrs[2][ $ci ] ) );
								if ( $val ) $log[] = '  .' . str_replace( ' ', '.', $key ) . ' → "' . mb_substr( $val, 0, 80 ) . '"';
							}
						}
						// Search for Turkish Lira price patterns (e.g. 125,00 or ₺125)
						if ( preg_match_all( '/([\d]{1,6}[.,]\d{2})\s*(?:TL|₺)|(?:TL|₺)\s*([\d]{1,6}[.,]\d{2})/u', $html, $pm, PREG_OFFSET_CAPTURE ) ) {
							$prices_found = [];
							foreach ( $pm[0] as $match ) {
								$prices_found[] = trim( $match[0] );
							}
							$prices_found = array_unique( array_slice( $prices_found, 0, 5 ) );
							$log[] = '  --- Sayfada bulunan fiyat desenleri ---';
							$log[] = '  ' . implode( ' | ', $prices_found );
							// Show HTML context around the first found price
							$first_price_raw = $pm[0][0][0];
							$first_offset    = $pm[0][0][1];
							$ctx_start = max( 0, $first_offset - 300 );
							$ctx       = substr( $html, $ctx_start, 700 );
							// Extract tag/class surrounding the price
							if ( preg_match_all( '/<([a-z][a-z0-9]*)[^>]*class=["\']([^"\']+)["\'][^>]*>(?:[^<]{0,80}' . preg_quote( trim( $first_price_raw ), '/' ) . '|[^<]{0,80})<\/\1>/i', $ctx, $tag_m ) ) {
								$log[] = '  --- İlk fiyat değerini içeren elementler ---';
								$shown_tags = [];
								foreach ( $tag_m[0] as $i => $tag_html ) {
									$cls = $tag_m[2][ $i ];
									if ( in_array( $cls, $shown_tags, true ) ) continue;
									$shown_tags[] = $cls;
									$tag = $tag_m[1][ $i ];
									$log[] = '  <' . $tag . ' class="' . $cls . '"> → CSS seçici: ' . $tag . '.' . str_replace( ' ', '.', trim( $cls ) );
								}
							}
							if ( empty( $shown_tags ?? [] ) ) {
								// Fallback: show raw context stripped
								$stripped = mb_substr( preg_replace( '/\s+/', ' ', strip_tags( $ctx ) ), 0, 300 );
								$log[] = '  Ham bağlam (strip_tags): ' . $stripped;
							}
							$log[] = '  → "Gelişmiş CSS Seçicileri" bölümüne "Fiyat Seçici" olarak üstteki seçiciyi girin.';
						} else {
							$log[] = '  ⚠ Sayfada TL/₺ formatında fiyat bulunamadı — fiyatlar JavaScript ile yükleniyor olabilir.';
							$log[] = '  Tarayıcıda F12 → Network sekmesini açıp sayfayı yenileyin. XHR/Fetch isteklerinde fiyat döndüren API endpoint\'ini arayın.';
						}
						// Check for itemprop attributes
						if ( preg_match_all( '/itemprop=["\']([^"\']+)["\']/i', $html, $ipm ) ) {
							$iprops = array_unique( $ipm[1] );
							$log[]  = '  Sayfadaki itemprop değerleri: ' . implode( ', ', array_slice( $iprops, 0, 10 ) );
						}
						$ok = false;
					}
				} catch ( \Throwable $e ) {
					$log[] = '✗ Parse hatası: ' . $e->getMessage();
					$ok    = false;
				}
			}
		}

		wp_send_json_success( [ 'log' => $log, 'ok' => $ok ] );
	}

	// ----------------------------------------------------------------
	// AJAX: Sync
	// ----------------------------------------------------------------

	public static function ajax_start_sync(): void {
		check_ajax_referer( 'wc_pss', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Yetkiniz yok.' ], 403 );

		$source_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', $_POST['source_id'] ?? '' );
		if ( ! $source_id || ! WC_PSS_Source_Manager::get( $source_id ) ) {
			wp_send_json_error( [ 'message' => 'Geçerli bir kaynak seçin.' ] );
		}

		$source  = WC_PSS_Source_Manager::get( $source_id );
		$options = [
			'update_prices' => ! empty( $_POST['update_prices'] ),
			'update_stock'  => ! empty( $_POST['update_stock'] ),
			'match_by_name' => ! empty( $_POST['match_by_name'] ),
			'price_rules'   => $source['price_rules'] ?? [],
		];

		try {
			$job_id = WC_PSS_Background_Processor::start( $source_id, $options );
			wp_send_json_success( [ 'job_id' => $job_id ] );
		} catch ( \Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function ajax_job_status(): void {
		check_ajax_referer( 'wc_pss', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

		$job = WC_PSS_Job_Manager::get( (int) ( $_GET['job_id'] ?? 0 ) );
		if ( ! $job ) wp_send_json_error( [ 'message' => 'İş bulunamadı.' ], 404 );

		$results = json_decode( $job->results ?: '{}', true ) ?: [];
		$errors  = json_decode( $job->errors  ?: '[]', true ) ?: [];
		$pct     = $job->total > 0 ? round( $job->processed / $job->total * 100 ) : ( $job->status === 'completed' ? 100 : 0 );

		wp_send_json_success( [
			'status'    => $job->status,
			'total'     => (int) $job->total,
			'processed' => (int) $job->processed,
			'percent'   => $pct,
			'updated'   => (int) ( $results['updated']   ?? 0 ),
			'not_found' => (int) ( $results['not_found'] ?? 0 ),
			'skipped'   => (int) ( $results['skipped']   ?? 0 ),
			'errors'    => $errors,
		] );
	}

	public static function ajax_cancel_job(): void {
		check_ajax_referer( 'wc_pss', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );
		$job = WC_PSS_Job_Manager::get( (int) ( $_POST['job_id'] ?? 0 ) );
		if ( ! $job ) wp_send_json_error( [ 'message' => 'İş bulunamadı.' ] );
		if ( in_array( $job->status, [ 'completed', 'cancelled', 'failed' ], true ) ) {
			wp_send_json_error( [ 'message' => 'Bu iş iptal edilemez.' ] );
		}
		WC_PSS_Job_Manager::update( (int) $job->id, [ 'status' => 'cancelled' ] );
		wp_send_json_success();
	}

	public static function ajax_delete_job(): void {
		check_ajax_referer( 'wc_pss', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );
		$job = WC_PSS_Job_Manager::get( (int) ( $_POST['job_id'] ?? 0 ) );
		if ( ! $job ) wp_send_json_error( [ 'message' => 'İş bulunamadı.' ] );
		if ( in_array( $job->status, [ 'discovering', 'processing' ], true ) ) {
			wp_send_json_error( [ 'message' => 'Aktif iş silinemez. Önce iptal edin.' ] );
		}
		WC_PSS_Job_Manager::delete( (int) $job->id );
		wp_send_json_success();
	}
}
