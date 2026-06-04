<?php
defined( 'ABSPATH' ) || exit;

class WC_RM_Admin_Menu {

	const OPTION_SOURCES = 'wc_rm_sources';

	public static function init(): void {
		add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		add_action( 'wp_ajax_wc_rm_save_source',     [ __CLASS__, 'ajax_save_source' ] );
		add_action( 'wp_ajax_wc_rm_delete_source',   [ __CLASS__, 'ajax_delete_source' ] );
		add_action( 'wp_ajax_wc_rm_test_connection', [ __CLASS__, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_wc_rm_start_import',    [ __CLASS__, 'ajax_start_import' ] );
		add_action( 'wp_ajax_wc_rm_job_status',      [ __CLASS__, 'ajax_job_status' ] );
		add_action( 'wp_ajax_wc_rm_delete_job',      [ __CLASS__, 'ajax_delete_job' ] );
		add_action( 'wp_ajax_wc_rm_resume_job',       [ __CLASS__, 'ajax_resume_job' ] );
		add_action( 'wp_ajax_wc_rm_import_batch',     [ __CLASS__, 'ajax_import_batch' ] );
	}

	public static function register_menu(): void {
		global $menu;
		$wc = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && $item[2] === 'woocommerce' ) { $wc = true; break; }
			}
		}
		if ( $wc ) {
			add_submenu_page( 'woocommerce', 'REST API Migrator', 'REST API Migrator', 'manage_options', 'wc-rest-migrator', [ __CLASS__, 'render_page' ] );
		} else {
			add_menu_page( 'REST API Migrator', 'REST API Migrator', 'manage_options', 'wc-rest-migrator', [ __CLASS__, 'render_page' ], 'dashicons-migrate', 59 );
		}
	}

	public static function enqueue( string $hook ): void {
		if ( strpos( $hook, 'wc-rest-migrator' ) === false ) return;
		wp_enqueue_style(  'wc-rm', WC_REST_MIGRATOR_URL . 'assets/css/admin.css', [], WC_REST_MIGRATOR_VERSION );
		wp_enqueue_script( 'wc-rm', WC_REST_MIGRATOR_URL . 'assets/js/admin.js', [ 'jquery' ], WC_REST_MIGRATOR_VERSION, true );
		$resume_job_id = isset( $_GET['tab'], $_GET['resume'] ) && $_GET['tab'] === 'import'
			? (int) $_GET['resume']
			: 0;
		wp_localize_script( 'wc-rm', 'wcRm', [
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'wc_rm' ),
			'confirmDelete' => 'Bu kaydı silmek istediğinizden emin misiniz?',
			'resumeJobId'   => $resume_job_id,
		] );
	}

	// ---- Page render ----

	public static function render_page(): void {
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'sources';
		$page_url = menu_page_url( 'wc-rest-migrator', false );
		?>
		<div class="wrap wc-rm-wrap">
			<h1>⚡ WooCommerce REST API Migrator</h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( [ 'sources' => 'Kaynak Siteler', 'import' => 'İçe Aktar', 'history' => 'Geçmiş' ] as $slug => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $page_url ) ); ?>"
				   class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
				<?php endforeach; ?>
			</nav>
			<div class="wc-rm-content">
				<?php
				if ( $tab === 'import' )       self::render_import();
				elseif ( $tab === 'history' )  self::render_history();
				else                           self::render_sources();
				?>
			</div>
		</div>
		<?php
	}

	// ---- Sources tab ----

	private static function render_sources(): void {
		$sources = self::get_sources();
		$edit_id = isset( $_GET['edit'] ) ? sanitize_key( $_GET['edit'] ) : '';
		$edit_src = null;
		if ( $edit_id === 'new' ) {
			$edit_src = [];
		} elseif ( $edit_id ) {
			foreach ( $sources as $s ) {
				if ( $s['id'] === $edit_id ) { $edit_src = $s; break; }
			}
		}
		$page_url = menu_page_url( 'wc-rest-migrator', false );
		?>
		<div class="wc-rm-card">
			<div style="display:flex;justify-content:space-between;align-items:center;">
				<h2 style="margin:0">Kaynak Siteler</h2>
				<?php if ( $edit_src === null ) : ?>
				<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'sources', 'edit' => 'new' ], $page_url ) ); ?>"
				   class="button button-primary">+ Kaynak Ekle</a>
				<?php endif; ?>
			</div>

			<?php if ( $edit_src !== null ) : ?>
			<form id="wc-rm-source-form" style="margin-top:20px">
				<input type="hidden" name="id" value="<?php echo esc_attr( $edit_src['id'] ?? '' ); ?>">
				<table class="form-table">
					<tr>
						<th>Kaynak Adı <span class="description">(zorunlu)</span></th>
						<td><input type="text" name="name" class="regular-text" required value="<?php echo esc_attr( $edit_src['name'] ?? '' ); ?>" placeholder="Tedarikçi Site"></td>
					</tr>
					<tr>
						<th>Kaynak Site URL</th>
						<td>
							<input type="url" name="source_url" class="regular-text" required value="<?php echo esc_attr( $edit_src['source_url'] ?? '' ); ?>" placeholder="https://tedarikci.com">
							<p class="description">WooCommerce REST API erişimi açık olmalı.</p>
						</td>
					</tr>
					<tr>
						<th>Consumer Key</th>
						<td>
							<input type="text" name="consumer_key" class="regular-text" required value="<?php echo esc_attr( $edit_src['consumer_key'] ?? '' ); ?>" placeholder="ck_xxxxxxxxxxxxxxxx">
							<p class="description">Kaynak sitede WooCommerce → Ayarlar → Gelişmiş → REST API'dan oluşturun (Okuma yetkisi yeterli).</p>
						</td>
					</tr>
					<tr>
						<th>Consumer Secret</th>
						<td><input type="password" name="consumer_secret" class="regular-text" required value="<?php echo esc_attr( $edit_src['consumer_secret'] ?? '' ); ?>" placeholder="cs_xxxxxxxxxxxxxxxx"></td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary">Kaydet</button>
					<a href="<?php echo esc_url( add_query_arg( 'tab', 'sources', $page_url ) ); ?>" class="button">İptal</a>
					<span class="spinner"></span>
				</p>
				<p id="wc-rm-source-msg" class="wc-rm-msg"></p>
			</form>

			<?php elseif ( empty( $sources ) ) : ?>
			<p style="margin-top:16px">Henüz kaynak site eklenmedi. <a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'sources', 'edit' => 'new' ], $page_url ) ); ?>">İlk kaynağı ekleyin →</a></p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped" style="margin-top:16px">
				<thead>
					<tr><th>Ad</th><th>URL</th><th>Consumer Key</th><th>İşlem</th></tr>
				</thead>
				<tbody>
				<?php foreach ( $sources as $src ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $src['name'] ); ?></strong></td>
					<td><?php echo esc_html( $src['source_url'] ); ?></td>
					<td><code><?php echo esc_html( substr( $src['consumer_key'], 0, 12 ) . '…' ); ?></code></td>
					<td class="wc-rm-actions">
						<button class="button button-small js-rm-test" data-id="<?php echo esc_attr( $src['id'] ); ?>">🔍 Test</button>
						<a href="<?php echo esc_url( add_query_arg( [ 'tab' => 'sources', 'edit' => $src['id'] ], $page_url ) ); ?>" class="button button-small">✏ Düzenle</a>
						<button class="button button-small js-rm-del-source" data-id="<?php echo esc_attr( $src['id'] ); ?>" style="color:#dc3232">🗑 Sil</button>
					</td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<div id="wc-rm-test-result"></div>
			<?php endif; ?>
		</div>
		<?php
	}

	// ---- Import tab ----

	private static function render_import(): void {
		$sources       = self::get_sources();
		$resume_job_id = isset( $_GET['resume'] ) ? (int) $_GET['resume'] : 0;
		?>
		<div class="wc-rm-card">
			<h2>İçe Aktarma Başlat</h2>
			<?php if ( empty( $sources ) ) : ?>
			<p>Önce <a href="<?php echo esc_url( add_query_arg( 'tab', 'sources', menu_page_url( 'wc-rest-migrator', false ) ) ); ?>">Kaynak Siteler</a> sekmesinden en az bir kaynak ekleyin.</p>
			<?php else : ?>
			<p class="description">
				Seçilen kaynak sitenin ürünleri REST API aracılığıyla içe aktarılır.<br>
				Sayfayı açık tutun — aktarma tarayıcı üzerinden yürütülür.
			</p>
			<form id="wc-rm-import-form" style="margin-top:16px">
				<table class="form-table">
					<tr>
						<th>Kaynak Site</th>
						<td>
							<select name="source_id" required>
								<option value="">— Seçin —</option>
								<?php foreach ( $sources as $src ) : ?>
								<option value="<?php echo esc_attr( $src['id'] ); ?>"><?php echo esc_html( $src['name'] . ' (' . $src['source_url'] . ')' ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th>Mevcut Ürün</th>
						<td>
							<select name="duplicate_strategy">
								<option value="update">Güncelle (SKU eşleşirse üzerine yaz)</option>
								<option value="skip">Atla (SKU varsa geç)</option>
								<option value="create_new">Her zaman yeni oluştur</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>Ürün Durumu</th>
						<td>
							<select name="status">
								<option value="any">Tümü (publish + draft + private)</option>
								<option value="publish">Yalnızca yayındakiler</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>Görseller</th>
						<td>
							<label><input type="checkbox" name="download_images" value="1" checked> Ürün görsellerini indir ve medya kütüphanesine ekle</label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" id="btn-import" class="button button-primary button-large">⚡ Aktarmayı Başlat</button>
					<span class="spinner"></span>
				</p>
			</form>

			<div id="wc-rm-progress" class="wc-rm-progress-wrap" style="display:<?php echo $resume_job_id ? 'block' : 'none'; ?>">
				<div class="wc-rm-bg-note">Aktarma devam ediyor — bu sayfayı açık tutun.</div>
				<div class="wc-rm-progress-outer"><div class="wc-rm-progress-inner" style="width:0%"></div></div>
				<div class="wc-rm-progress-text">Başlatılıyor…</div>
				<div class="wc-rm-progress-status"></div>
				<div id="wc-rm-live-items"></div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// ---- History tab ----

	private static function render_history(): void {
		$jobs = WC_RM_Job_Manager::get_recent( 30 );
		?>
		<div class="wc-rm-card">
			<h2>Aktarma Geçmişi</h2>
			<?php if ( empty( $jobs ) ) : ?>
			<p>Henüz aktarma başlatılmamış.</p>
			<?php else : ?>
			<div class="wc-rm-table-wrap">
			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th style="width:32px">#</th>
						<th class="col-source">Kaynak</th>
						<th>Durum</th>
						<th>İlerleme</th>
						<th>Oluşturuldu</th>
						<th>Güncellendi</th>
						<th>Hata</th>
						<th>Tarih</th>
						<th>İşlem</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $jobs as $job ) :
					$pct     = $job->total > 0 ? round( $job->processed / $job->total * 100 ) : ( $job->status === 'completed' ? 100 : 0 );
					$results = json_decode( $job->results ?: '{}', true ) ?: [];
					$errors  = json_decode( $job->errors  ?: '[]', true ) ?: [];
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
					<td class="col-source" title="<?php echo esc_attr( $job->source_url ); ?>"><?php echo esc_html( $job->source_url ?: '—' ); ?></td>
					<td>
						<span class="wc-rm-badge wc-rm-badge-<?php echo esc_attr( $job->status ); ?>">
							<?php echo esc_html( self::status_label( $job->status ) ); ?>
						</span>
					</td>
					<td>
						<div class="wc-rm-mini-outer"><div class="wc-rm-mini-inner" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
						<small><?php echo esc_html( $job->processed ); ?> / <?php echo esc_html( $job->total ); ?>
						<?php if ( $job->status === 'processing' && ! empty( $results['current_page'] ) ) : ?>
							&nbsp;<span style="color:#856404">(sayfa <?php echo esc_html( $results['current_page'] ); ?>)</span>
						<?php endif; ?></small>
					</td>
					<td><strong style="color:#1e6f3e"><?php echo esc_html( $results['created'] ?? 0 ); ?></strong></td>
					<td><?php echo esc_html( $results['updated'] ?? 0 ); ?></td>
					<td>
						<?php if ( ! empty( $errors ) ) :?>
						<span style="color:#dc3232"><?php echo esc_html( count( $errors ) ); ?></span>
						<?php if ( $job->status === 'failed' && ! empty( $errors[0] ) ) : ?>
						<br><small style="color:#dc3232;word-break:break-word;max-width:200px;display:block"><?php echo esc_html( mb_substr( $errors[0], 0, 100 ) ); ?></small>
						<?php endif; ?>
						<?php else : echo '—'; endif; ?>
					</td>
					<td><?php echo esc_html( $date_str ); ?></td>
					<td class="wc-rm-actions">
						<?php if ( $job->status === 'processing' ) :
							$cur_page = $results['current_page'] ?? 0;
							$resume_url = add_query_arg( [ 'tab' => 'import', 'resume' => $job->id ], menu_page_url( 'wc-rest-migrator', false ) );
						?>
						<a href="<?php echo esc_url( $resume_url ); ?>"
							class="button button-small button-primary"
							title="<?php echo esc_attr( $cur_page ? "Sayfa {$cur_page} sonrasından devam et" : 'Devam ettir' ); ?>">
							▶ Devam Et
						</a>
						<?php endif; ?>
						<button class="button button-small js-rm-delete-job" data-job-id="<?php echo esc_attr( $job->id ); ?>">🗑 Sil</button>
					</td>
				</tr>
				<?php if ( ! empty( $results['imported_items'] ) ) : ?>
				<tr class="wc-rm-items-row">
					<td colspan="9" style="padding:0 8px 8px 36px;background:#f9f9f9">
						<details>
							<summary style="cursor:pointer;padding:6px 0;color:#1e6f3e;font-weight:600">
								Aktarılan ürünler (<?php echo esc_html( count( $results['imported_items'] ) ); ?>)
							</summary>
							<table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:6px">
								<thead><tr style="background:#e8f5e9">
									<th style="padding:3px 8px;text-align:left">SKU</th>
									<th style="padding:3px 8px;text-align:left">Ürün Adı</th>
									<th style="padding:3px 8px;text-align:left">Durum</th>
								</tr></thead>
								<tbody>
								<?php foreach ( $results['imported_items'] as $item ) : ?>
								<tr style="border-bottom:1px solid #f0f0f0">
									<td style="padding:2px 8px;font-family:monospace"><?php echo esc_html( $item['sku'] ?? '—' ); ?></td>
									<td style="padding:2px 8px"><?php echo esc_html( $item['name'] ?? '—' ); ?></td>
									<td style="padding:2px 8px;color:<?php echo ( $item['status'] ?? '' ) === 'created' ? '#1e6f3e' : '#856404'; ?>">
										<?php echo esc_html( ( $item['status'] ?? '' ) === 'created' ? 'Oluşturuldu' : 'Güncellendi' ); ?>
									</td>
								</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</details>
					</td>
				</tr>
				<?php endif; ?>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div><!-- .wc-rm-table-wrap -->
			<?php endif; ?>
		</div>
		<?php
	}

	// ---- AJAX: save source ----

	public static function ajax_save_source(): void {
		check_ajax_referer( 'wc_rm', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Yetki yok.' ] );

		$id             = sanitize_key( $_POST['id'] ?? '' );
		$name           = sanitize_text_field( $_POST['name'] ?? '' );
		$source_url     = esc_url_raw( $_POST['source_url'] ?? '' );
		$consumer_key   = sanitize_text_field( $_POST['consumer_key'] ?? '' );
		$consumer_secret= sanitize_text_field( $_POST['consumer_secret'] ?? '' );

		if ( ! $name || ! $source_url || ! $consumer_key || ! $consumer_secret ) {
			wp_send_json_error( [ 'message' => 'Tüm alanlar zorunludur.' ] );
		}

		$sources = self::get_sources();
		$new_entry = [
			'id'              => $id ?: wp_generate_uuid4(),
			'name'            => $name,
			'source_url'      => $source_url,
			'consumer_key'    => $consumer_key,
			'consumer_secret' => $consumer_secret,
		];

		$found = false;
		foreach ( $sources as &$s ) {
			if ( $s['id'] === $new_entry['id'] ) { $s = $new_entry; $found = true; break; }
		}
		unset( $s );
		if ( ! $found ) $sources[] = $new_entry;

		update_option( self::OPTION_SOURCES, $sources );
		wp_send_json_success( [ 'message' => 'Kayıt edildi.' ] );
	}

	// ---- AJAX: delete source ----

	public static function ajax_delete_source(): void {
		check_ajax_referer( 'wc_rm', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		$id      = sanitize_key( $_POST['id'] ?? '' );
		$sources = array_filter( self::get_sources(), fn( $s ) => $s['id'] !== $id );
		update_option( self::OPTION_SOURCES, array_values( $sources ) );
		wp_send_json_success();
	}

	// ---- AJAX: test connection ----

	public static function ajax_test_connection(): void {
		check_ajax_referer( 'wc_rm', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		$src = self::find_source( sanitize_key( $_POST['source_id'] ?? '' ) );
		if ( ! $src ) wp_send_json_error( [ 'message' => 'Kaynak bulunamadı.' ] );

		$client = new WC_RM_Api_Client( $src['source_url'], $src['consumer_key'], $src['consumer_secret'] );
		$result = $client->test();

		if ( $result['ok'] ) {
			wp_send_json_success( [ 'message' => $result['message'], 'total' => $result['total'] ] );
		} else {
			wp_send_json_error( [ 'message' => $result['message'] ] );
		}
	}

	// ---- AJAX: start import ----
	// Discover phase runs synchronously here so we immediately know the total
	// and can begin AJAX-driven batch processing without WP-Cron dependency.

	public static function ajax_start_import(): void {
		check_ajax_referer( 'wc_rm', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		$src = self::find_source( sanitize_key( $_POST['source_id'] ?? '' ) );
		if ( ! $src ) wp_send_json_error( [ 'message' => 'Kaynak bulunamadı.' ] );

		$options = [
			'source_url'         => $src['source_url'],
			'consumer_key'       => $src['consumer_key'],
			'consumer_secret'    => $src['consumer_secret'],
			'duplicate_strategy' => sanitize_key( $_POST['duplicate_strategy'] ?? 'update' ),
			'download_images'    => ! empty( $_POST['download_images'] ),
			'status'             => sanitize_key( $_POST['status'] ?? 'any' ),
		];

		try {
			// Run discover synchronously — avoids WP-Cron dependency for job creation
			$client = new WC_RM_Api_Client(
				$options['source_url'],
				$options['consumer_key'],
				$options['consumer_secret']
			);
			$test = $client->test();
			if ( ! $test['ok'] ) {
				wp_send_json_error( [ 'message' => 'Bağlantı hatası: ' . $test['message'] ] );
				return;
			}
			$total = (int) $test['total'];
			if ( $total === 0 ) {
				wp_send_json_error( [ 'message' => 'Kaynak sitede ürün bulunamadı.' ] );
				return;
			}

			$job_id = WC_RM_Job_Manager::create( [
				'status'     => 'processing',
				'source_url' => $options['source_url'],
				'options'    => $options,
				'total'      => $total,
			] );

			// Schedule watchdog as AS backup (in case browser closes mid-import)
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action(
					time() + WC_RM_Background_Processor::WATCHDOG_INT,
					WC_RM_Background_Processor::HOOK_WATCHDOG,
					[ 'job_id' => $job_id ],
					WC_RM_Background_Processor::GROUP
				);
			}

			wp_send_json_success( [ 'job_id' => $job_id, 'total' => $total ] );

		} catch ( Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	// ---- AJAX: import batch (AJAX-driven, no WP-Cron needed) ----
	// Processes one page of products and returns updated job status.
	// JS calls this endpoint repeatedly until the job completes.

	public static function ajax_import_batch(): void {
		check_ajax_referer( 'wc_rm', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		$job_id = (int) ( $_GET['job_id'] ?? 0 );
		$job    = WC_RM_Job_Manager::get( $job_id );
		if ( ! $job ) wp_send_json_error( [ 'message' => 'İş bulunamadı.' ] );

		if ( $job->status === 'processing' ) {
			@set_time_limit( 120 );
			wp_raise_memory_limit( 'admin' );

			$results  = json_decode( $job->results ?: '{}', true ) ?: [];
			$last_hb  = (int) ( $results['last_heartbeat'] ?? 0 );
			$cur_page = (int) ( $results['current_page']   ?? 0 );

			// Prevent concurrent execution: if heartbeat was updated in the last 5 seconds,
			// another request is already processing — return status without doing work.
			if ( $last_hb === 0 || ( time() - $last_hb ) >= 5 ) {
				$next_page = $cur_page > 0 ? $cur_page + 1 : 1;
				// AJAX-driven: don't enqueue next AS action, JS handles continuation
				WC_RM_Background_Processor::import_page( $job_id, $next_page, false );
			}

			$job = WC_RM_Job_Manager::get( $job_id ); // re-fetch after processing
		}

		$results = json_decode( $job->results ?: '{}', true ) ?: [];
		$errors  = json_decode( $job->errors  ?: '[]', true ) ?: [];
		$pct     = $job->total > 0
			? round( $job->processed / $job->total * 100 )
			: ( $job->status === 'completed' ? 100 : 0 );

		wp_send_json_success( [
			'status'         => $job->status,
			'total'          => $job->total,
			'processed'      => $job->processed,
			'percent'        => $pct,
			'created'        => $results['created']        ?? 0,
			'updated'        => $results['updated']        ?? 0,
			'skipped'        => $results['skipped']        ?? 0,
			'errors_count'   => $results['errors_count']   ?? 0,
			'current_page'   => $results['current_page']   ?? 0,
			'last_heartbeat' => $results['last_heartbeat'] ?? 0,
			'errors'         => $errors,
			'imported_items' => $results['imported_items'] ?? [],
		] );
	}

	// ---- AJAX: job status ----

	public static function ajax_job_status(): void {
		check_ajax_referer( 'wc_rm', 'nonce' );

		$job = WC_RM_Job_Manager::get( (int) ( $_GET['job_id'] ?? 0 ) );
		if ( ! $job ) wp_send_json_error( [ 'message' => 'İş bulunamadı.' ] );

		$results = json_decode( $job->results ?: '{}', true ) ?: [];
		$errors  = json_decode( $job->errors  ?: '[]', true ) ?: [];
		$pct     = $job->total > 0 ? round( $job->processed / $job->total * 100 ) : ( $job->status === 'completed' ? 100 : 0 );

		wp_send_json_success( [
			'status'         => $job->status,
			'total'          => $job->total,
			'processed'      => $job->processed,
			'percent'        => $pct,
			'created'        => $results['created']        ?? 0,
			'updated'        => $results['updated']        ?? 0,
			'skipped'        => $results['skipped']        ?? 0,
			'errors_count'   => $results['errors_count']   ?? 0,
			'current_page'   => $results['current_page']   ?? 0,
			'last_heartbeat' => $results['last_heartbeat'] ?? 0,
			'errors'         => $errors,
			'imported_items' => $results['imported_items'] ?? [],
		] );
	}

	// ---- AJAX: delete job ----

	public static function ajax_delete_job(): void {
		check_ajax_referer( 'wc_rm', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
		WC_RM_Job_Manager::delete( (int) ( $_POST['job_id'] ?? 0 ) );
		wp_send_json_success();
	}

	// ---- AJAX: resume stuck job ----

	public static function ajax_resume_job(): void {
		check_ajax_referer( 'wc_rm', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();

		$job_id = (int) ( $_POST['job_id'] ?? 0 );
		$ok     = WC_RM_Background_Processor::resume( $job_id );

		if ( $ok ) {
			$job     = WC_RM_Job_Manager::get( $job_id );
			$results = json_decode( $job ? $job->results : '{}', true ) ?: [];
			wp_send_json_success( [
				'message' => 'İçe aktarma sayfa ' . ( ( $results['current_page'] ?? 0 ) + 1 ) . "'ten devam ediyor.",
			] );
		} else {
			wp_send_json_error( [ 'message' => 'İş bulunamadı, işlenmiyor veya zaten devam ediyor.' ] );
		}
	}

	// ---- Helpers ----

	private static function get_sources(): array {
		return (array) get_option( self::OPTION_SOURCES, [] );
	}

	private static function find_source( string $id ): ?array {
		foreach ( self::get_sources() as $s ) {
			if ( $s['id'] === $id ) return $s;
		}
		return null;
	}

	private static function status_label( string $s ): string {
		return [
			'discovering' => 'Keşfediliyor',
			'processing'  => 'İşleniyor',
			'completed'   => 'Tamamlandı',
			'failed'      => 'Hata',
			'cancelled'   => 'İptal',
			'pending'     => 'Bekliyor',
		][ $s ] ?? $s;
	}
}
