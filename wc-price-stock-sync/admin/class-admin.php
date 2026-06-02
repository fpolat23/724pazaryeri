<?php
defined( 'ABSPATH' ) || exit;

class WC_PSS_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		add_action( 'admin_post_wc_pss_export',    [ __CLASS__, 'handle_export' ] );
		add_action( 'wp_ajax_wc_pss_start_update', [ __CLASS__, 'ajax_start_update' ] );
		add_action( 'wp_ajax_wc_pss_job_status',   [ __CLASS__, 'ajax_job_status' ] );
		add_action( 'wp_ajax_wc_pss_cancel_job',   [ __CLASS__, 'ajax_cancel_job' ] );
		add_action( 'wp_ajax_wc_pss_delete_job',   [ __CLASS__, 'ajax_delete_job' ] );
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
			'confirmCancel' => 'Bu işlemi iptal etmek istediğinizden emin misiniz?',
			'confirmDelete' => 'Bu kaydı kalıcı olarak silmek istediğinizden emin misiniz?',
		] );
	}

	// ----------------------------------------------------------------
	// Sayfa render
	// ----------------------------------------------------------------

	public static function render_page(): void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'export';
		?>
		<div class="wrap wc-pss-wrap">
			<h1>🔄 WooCommerce Fiyat &amp; Stok Senkronizasyonu</h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( [ 'export' => 'Dışa Aktar', 'update' => 'Fiyat &amp; Stok Güncelle', 'history' => 'İşlem Geçmişi' ] as $slug => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, menu_page_url( 'wc-pss', false ) ) ); ?>"
				   class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
					<?php echo wp_kses_post( $label ); ?>
				</a>
				<?php endforeach; ?>
			</nav>
			<div class="wc-pss-content">
				<?php
				if ( $tab === 'export' )      self::render_export();
				elseif ( $tab === 'update' )  self::render_update();
				else                          self::render_history();
				?>
			</div>
		</div>
		<?php
	}

	private static function render_export(): void {
		global $wpdb;
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_sku' AND pm.meta_value != ''
			 WHERE p.post_type IN ('product','product_variation') AND p.post_status IN ('publish','private')"
		);
		?>
		<div class="wc-pss-card">
			<h2>Mevcut Fiyat &amp; Stok Verilerini CSV Olarak Dışa Aktar</h2>
			<p class="description">
				Bu CSV dosyasını <strong>kaynak siteden</strong> indirin ve hedef sitenin
				<em>Fiyat &amp; Stok Güncelle</em> sekmesine yükleyerek güncelleme yapabilirsiniz.
			</p>
			<div class="wc-pss-stat">
				Toplam <strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> ürün &amp; varyasyon (SKU'lu)
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wc_pss_export', '_wpnonce_export' ); ?>
				<input type="hidden" name="action" value="wc_pss_export">
				<table class="form-table">
					<tr>
						<th>Dahil Et</th>
						<td>
							<label><input type="checkbox" name="inc_prices" value="1" checked> Fiyat bilgileri (regular_price, sale_price)</label><br>
							<label><input type="checkbox" name="inc_stock"  value="1" checked> Stok bilgileri (stock_quantity, stock_status)</label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary button-large">⬇ CSV İndir</button>
				</p>
			</form>

			<div class="wc-pss-info-box">
				<strong>CSV Sütun Yapısı</strong>
				<p><code>sku, name, regular_price, sale_price, stock_quantity, stock_status, manage_stock</code></p>
				<table class="widefat" style="margin-top:8px">
					<thead><tr><th>Sütun</th><th>Açıklama</th><th>Örnek</th></tr></thead>
					<tbody>
						<tr><td><code>sku</code></td><td>Ürün SKU (eşleştirme için zorunlu)</td><td>URUN-001</td></tr>
						<tr><td><code>regular_price</code></td><td>Normal fiyat</td><td>199.90</td></tr>
						<tr><td><code>sale_price</code></td><td>İndirimli fiyat (boş = indirim kaldır)</td><td>149.90</td></tr>
						<tr><td><code>stock_quantity</code></td><td>Stok adedi</td><td>50</td></tr>
						<tr><td><code>stock_status</code></td><td>Stok durumu</td><td>instock / outofstock / onbackorder</td></tr>
						<tr><td><code>manage_stock</code></td><td>Stok takibi</td><td>yes / no</td></tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	private static function render_update(): void {
		?>
		<div class="wc-pss-card">
			<h2>CSV ile Fiyat &amp; Stok Güncelle</h2>
			<p class="description">
				CSV yüklendikten sonra işlem <strong>arka planda</strong> çalışır — tarayıcıyı kapatabilirsiniz.
				Tamamlandığında <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', menu_page_url( 'wc-pss', false ) ) ); ?>">İşlem Geçmişi</a> sekmesinden sonucu görebilirsiniz.
			</p>

			<form id="wc-pss-update-form" enctype="multipart/form-data">
				<table class="form-table">
					<tr>
						<th>CSV Dosyası</th>
						<td>
							<input type="file" name="csv_file" id="pss-csv-file" accept=".csv,.txt" required>
							<p class="description">İlk satır başlık olmalı. Zorunlu sütun: <code>sku</code></p>
						</td>
					</tr>
					<tr>
						<th>Güncelleme Kapsamı</th>
						<td>
							<label><input type="checkbox" name="update_prices" value="1" checked> <strong>Fiyatları güncelle</strong> (regular_price, sale_price)</label><br>
							<label><input type="checkbox" name="update_stock"  value="1" checked> <strong>Stoğu güncelle</strong> (stock_quantity, stock_status, manage_stock)</label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" id="btn-update" class="button button-primary button-large">▶ Güncellemeyi Başlat</button>
					<span class="spinner"></span>
				</p>
			</form>

			<div id="pss-progress" class="wc-pss-progress-wrap" style="display:none;">
				<div class="wc-pss-bg-note">İşlem arka planda devam ediyor. Sayfayı kapatabilirsiniz.</div>
				<div class="wc-pss-progress-outer"><div class="wc-pss-progress-inner" style="width:0%"></div></div>
				<div class="wc-pss-progress-text">0 / 0</div>
				<div class="wc-pss-progress-status"></div>
			</div>
		</div>
		<?php
	}

	private static function render_history(): void {
		$jobs = WC_PSS_Job_Manager::get_recent( 20 );
		?>
		<div class="wc-pss-card">
			<h2>İşlem Geçmişi</h2>
			<?php if ( empty( $jobs ) ) : ?>
				<p>Henüz hiç güncelleme başlatılmamış.</p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:40px">#</th>
						<th>Durum</th>
						<th>İlerleme</th>
						<th>Güncellendi</th>
						<th>Bulunamadı</th>
						<th>Hata</th>
						<th>Tarih</th>
						<th>İşlemler</th>
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
					<td><?php echo $errors ? '<span style="color:#dc3232">' . esc_html( count( $errors ) ) . ' hata</span>' : '—'; ?></td>
					<td><?php echo esc_html( $date_str ); ?></td>
					<td class="wc-pss-actions">
						<?php if ( $job->status === 'processing' ) : ?>
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
			'pending'    => 'Bekliyor',
			'processing' => 'İşleniyor',
			'completed'  => 'Tamamlandı',
			'failed'     => 'Hatalı',
			'cancelled'  => 'İptal Edildi',
		][ $s ] ?? $s;
	}

	// ----------------------------------------------------------------
	// Handlers
	// ----------------------------------------------------------------

	public static function handle_export(): void {
		check_admin_referer( 'wc_pss_export', '_wpnonce_export' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Yetkiniz yok.' );

		$inc_prices = ! empty( $_POST['inc_prices'] );
		$inc_stock  = ! empty( $_POST['inc_stock'] );
		$filename   = 'fiyat-stok-' . date( 'Ymd-His' ) . '.csv';

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		$output = fopen( 'php://output', 'w' );
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // UTF-8 BOM (Excel için)

		$cols = [ 'sku', 'name' ];
		if ( $inc_prices ) { $cols[] = 'regular_price'; $cols[] = 'sale_price'; }
		if ( $inc_stock  ) { $cols[] = 'stock_quantity'; $cols[] = 'stock_status'; $cols[] = 'manage_stock'; }
		fputcsv( $output, $cols );

		global $wpdb;
		$page = 0;
		$per  = 200;

		while ( true ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.ID, p.post_title AS name,
				    MAX(CASE WHEN pm.meta_key = '_sku'           THEN pm.meta_value END) AS sku,
				    MAX(CASE WHEN pm.meta_key = '_regular_price' THEN pm.meta_value END) AS regular_price,
				    MAX(CASE WHEN pm.meta_key = '_sale_price'    THEN pm.meta_value END) AS sale_price,
				    MAX(CASE WHEN pm.meta_key = '_stock'         THEN pm.meta_value END) AS stock_quantity,
				    MAX(CASE WHEN pm.meta_key = '_stock_status'  THEN pm.meta_value END) AS stock_status,
				    MAX(CASE WHEN pm.meta_key = '_manage_stock'  THEN pm.meta_value END) AS manage_stock
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE p.post_type IN ('product', 'product_variation')
				AND p.post_status IN ('publish', 'private')
				GROUP BY p.ID
				HAVING sku != '' AND sku IS NOT NULL
				ORDER BY p.ID
				LIMIT %d OFFSET %d",
				$per, $page * $per
			) );

			if ( empty( $rows ) ) break;

			foreach ( $rows as $r ) {
				$row = [ $r->sku ?? '', $r->name ?? '' ];
				if ( $inc_prices ) {
					$row[] = $r->regular_price ?? '';
					$row[] = $r->sale_price    ?? '';
				}
				if ( $inc_stock ) {
					$row[] = $r->stock_quantity ?? '';
					$row[] = $r->stock_status   ?? '';
					$row[] = ( $r->manage_stock === 'yes' ) ? 'yes' : 'no';
				}
				fputcsv( $output, $row );
			}

			if ( count( $rows ) < $per ) break;
			$page++;

			if ( ob_get_level() > 0 ) ob_flush();
			flush();
		}

		fclose( $output );
		exit;
	}

	public static function ajax_start_update(): void {
		check_ajax_referer( 'wc_pss', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Yetkiniz yok.' ], 403 );

		if ( empty( $_FILES['csv_file']['tmp_name'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( [ 'message' => 'Dosya yüklenemedi.' ] );
		}

		$file = $_FILES['csv_file'];
		$ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, [ 'csv', 'txt' ], true ) ) {
			wp_send_json_error( [ 'message' => 'Yalnızca .csv veya .txt dosyası kabul edilir.' ] );
		}

		$upload = wp_upload_dir();
		$dir    = $upload['basedir'] . '/wc-pss';
		wp_mkdir_p( $dir );

		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Options -Indexes\n" );
		}

		$dest = $dir . '/update-' . wp_generate_password( 8, false ) . '.csv';
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			wp_send_json_error( [ 'message' => 'Dosya kaydedilemedi.' ] );
		}

		$options = [
			'update_prices' => ! empty( $_POST['update_prices'] ),
			'update_stock'  => ! empty( $_POST['update_stock'] ),
		];

		try {
			$job_id = WC_PSS_Background_Processor::start( $dest, $options );
			wp_send_json_success( [ 'job_id' => $job_id ] );
		} catch ( Throwable $e ) {
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

		if ( in_array( $job->status, [ 'completed', 'cancelled' ], true ) ) {
			wp_send_json_error( [ 'message' => 'Zaten tamamlanmış veya iptal edilmiş.' ] );
		}

		WC_PSS_Job_Manager::update( (int) $job->id, [ 'status' => 'cancelled' ] );
		wp_send_json_success();
	}

	public static function ajax_delete_job(): void {
		check_ajax_referer( 'wc_pss', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

		$job = WC_PSS_Job_Manager::get( (int) ( $_POST['job_id'] ?? 0 ) );
		if ( ! $job ) wp_send_json_error( [ 'message' => 'İş bulunamadı.' ] );

		if ( $job->status === 'processing' ) {
			wp_send_json_error( [ 'message' => 'İşlenmekte olan bir iş silinemez. Önce iptal edin.' ] );
		}

		WC_PSS_Job_Manager::delete( (int) $job->id );
		wp_send_json_success();
	}
}
