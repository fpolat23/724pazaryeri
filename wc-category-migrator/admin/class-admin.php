<?php
defined( 'ABSPATH' ) || exit;

class WC_Cat_Migrator_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );

		// Dışa aktarma (form POST → dosya indirme)
		add_action( 'admin_post_wc_cat_export', [ __CLASS__, 'handle_export' ] );

		// AJAX
		add_action( 'wp_ajax_wc_cat_start_import', [ __CLASS__, 'ajax_start_import' ] );
		add_action( 'wp_ajax_wc_cat_job_status',   [ __CLASS__, 'ajax_job_status' ] );
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
			add_submenu_page( 'woocommerce', 'Kategori Aktarımı', 'Kategori Aktarımı', 'manage_options', 'wc-category-migrator', [ __CLASS__, 'render_page' ] );
		} else {
			add_menu_page( 'Kategori Aktarımı', 'Kategori Aktarımı', 'manage_options', 'wc-category-migrator', [ __CLASS__, 'render_page' ], 'dashicons-category', 57 );
		}
	}

	public static function enqueue( string $hook ): void {
		if ( strpos( $hook, 'wc-category-migrator' ) === false ) return;
		wp_enqueue_style( 'wc-cat-migrator', WC_CAT_MIGRATOR_URL . 'assets/css/admin.css', [], WC_CAT_MIGRATOR_VERSION );
		wp_enqueue_script( 'wc-cat-migrator', WC_CAT_MIGRATOR_URL . 'assets/js/admin.js', [ 'jquery' ], WC_CAT_MIGRATOR_VERSION, true );
		wp_localize_script( 'wc-cat-migrator', 'wcCatMigrator', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wc_cat_migrator' ),
		] );
	}

	// ---------------------------------------------------------------
	// Sayfa render
	// ---------------------------------------------------------------

	public static function render_page(): void {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'export';
		?>
		<div class="wrap wc-cat-wrap">
			<h1>📦 WooCommerce Kategori Aktarımı</h1>
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'export', menu_page_url( 'wc-category-migrator', false ) ) ); ?>"
				   class="nav-tab <?php echo $tab === 'export' ? 'nav-tab-active' : ''; ?>">Dışa Aktar</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'import', menu_page_url( 'wc-category-migrator', false ) ) ); ?>"
				   class="nav-tab <?php echo $tab === 'import' ? 'nav-tab-active' : ''; ?>">İçe Aktar</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'history', menu_page_url( 'wc-category-migrator', false ) ) ); ?>"
				   class="nav-tab <?php echo $tab === 'history' ? 'nav-tab-active' : ''; ?>">Geçmiş</a>
			</nav>
			<div class="wc-cat-content">
				<?php
				if ( $tab === 'export' )       self::render_export();
				elseif ( $tab === 'import' )   self::render_import();
				else                           self::render_history();
				?>
			</div>
		</div>
		<?php
	}

	private static function render_export(): void {
		$total = (int) wp_count_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
		?>
		<div class="wc-cat-card">
			<h2>Kategorileri Dışa Aktar</h2>
			<p class="description">Tüm kategoriler <strong>hiyerarşi sırası korunarak</strong> (üst → alt) XML dosyasına yazılır.</p>
			<div class="wc-cat-stat">Toplam <strong><?php echo esc_html( $total ); ?></strong> kategori mevcut.</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wc_cat_export', '_wpnonce_export' ); ?>
				<input type="hidden" name="action" value="wc_cat_export">
				<table class="form-table">
					<tr>
						<th>Seçenekler</th>
						<td><label><input type="checkbox" name="include_images" value="1" checked> Kategori resimlerini dahil et (URL olarak)</label></td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary button-large">⬇ XML Olarak İndir</button>
				</p>
			</form>
		</div>
		<?php
	}

	private static function render_import(): void {
		?>
		<div class="wc-cat-card">
			<h2>Kategorileri İçe Aktar</h2>
			<p class="description">
				XML yüklendikten sonra işlem <strong>arka planda</strong> çalışır —
				tarayıcıyı kapatabilirsiniz. Tamamlandığında <a href="<?php echo esc_url( add_query_arg( 'tab', 'history', menu_page_url( 'wc-category-migrator', false ) ) ); ?>">Geçmiş</a> sekmesinden sonucu görebilirsiniz.
			</p>

			<form id="wc-cat-import-form" enctype="multipart/form-data">
				<table class="form-table">
					<tr>
						<th>XML Dosyası</th>
						<td><input type="file" name="xml_file" id="cat-xml-file" accept=".xml" required></td>
					</tr>
					<tr>
						<th>Mevcut kategoriler</th>
						<td>
							<label><input type="radio" name="update_existing" value="1" checked> Güncelle</label><br>
							<label><input type="radio" name="update_existing" value="0"> Atla</label>
						</td>
					</tr>
					<tr>
						<th>Resimler</th>
						<td><label><input type="checkbox" name="download_images" value="1" checked> İndir ve medya kütüphanesine ekle</label></td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" id="btn-import" class="button button-primary button-large">⬆ İçe Aktarmayı Başlat</button>
					<span class="spinner"></span>
				</p>
			</form>

			<!-- İlerleme -->
			<div id="cat-import-progress" class="wc-cat-progress-wrap" style="display:none;">
				<div class="wc-cat-bg-note">İşlem arka planda devam ediyor. Sayfayı kapatabilirsiniz.</div>
				<div class="wc-cat-progress-outer"><div class="wc-cat-progress-inner" style="width:0%"></div></div>
				<div class="wc-cat-progress-text">0 / 0</div>
				<div class="wc-cat-progress-status"></div>
			</div>
		</div>
		<?php
	}

	private static function render_history(): void {
		$jobs = WC_Cat_Job_Manager::get_recent( 15 );
		?>
		<div class="wc-cat-card">
			<h2>İşlem Geçmişi</h2>
			<?php if ( empty( $jobs ) ) : ?>
				<p>Henüz hiç içe aktarma başlatılmamış.</p>
			<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:40px">#</th>
						<th>Durum</th>
						<th>İlerleme</th>
						<th>Hata</th>
						<th>Tarih</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $jobs as $job ) :
					$pct    = $job->total > 0 ? round( $job->processed / $job->total * 100 ) : ( $job->status === 'completed' ? 100 : 0 );
					$errors = json_decode( $job->errors ?: '[]', true ) ?: [];
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
					<td><span class="wc-cat-badge wc-cat-badge-<?php echo esc_attr( $job->status ); ?>"><?php echo esc_html( self::status_label( $job->status ) ); ?></span></td>
					<td>
						<div class="wc-cat-mini-outer"><div class="wc-cat-mini-inner" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
						<small><?php echo esc_html( $job->processed ); ?> / <?php echo esc_html( $job->total ); ?></small>
					</td>
					<td><?php echo $errors ? '<span style="color:#dc3232">' . esc_html( count( $errors ) ) . ' hata</span>' : '—'; ?></td>
					<td><?php echo esc_html( $date_str ); ?></td>
				</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function status_label( string $s ): string {
		return [ 'pending' => 'Bekliyor', 'processing' => 'İşleniyor', 'completed' => 'Tamamlandı', 'failed' => 'Hatalı' ][ $s ] ?? $s;
	}

	// ---------------------------------------------------------------
	// Handlers
	// ---------------------------------------------------------------

	public static function handle_export(): void {
		check_admin_referer( 'wc_cat_export', '_wpnonce_export' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Yetkiniz yok.' );

		$exporter = new WC_Cat_Migrator_Exporter( ! empty( $_POST['include_images'] ) );
		$xml      = $exporter->export();
		$filename = 'wc-kategoriler-' . date( 'Ymd-His' ) . '.xml';

		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		echo $xml; // phpcs:ignore
		exit;
	}

	public static function ajax_start_import(): void {
		check_ajax_referer( 'wc_cat_migrator', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Yetkiniz yok.' ], 403 );

		if ( empty( $_FILES['xml_file']['tmp_name'] ) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( [ 'message' => 'Dosya yüklenemedi.' ] );
		}

		$file = $_FILES['xml_file'];
		if ( strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) !== 'xml' ) {
			wp_send_json_error( [ 'message' => 'Yalnızca .xml dosyası kabul edilir.' ] );
		}

		// Kalıcı konuma taşı
		$upload = wp_upload_dir();
		$dir    = $upload['basedir'] . '/wc-cat-migrator';
		wp_mkdir_p( $dir );

		// Dizin listelemesini engelle
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', "Options -Indexes\n" );
		}

		$dest = $dir . '/import-' . wp_generate_password( 8, false ) . '.xml';
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			wp_send_json_error( [ 'message' => 'Dosya kaydedilemedi.' ] );
		}

		$options = [
			'download_images' => ! empty( $_POST['download_images'] ),
			'update_existing' => ( ( $_POST['update_existing'] ?? '1' ) === '1' ),
		];

		try {
			$job_id = WC_Cat_Background_Importer::start( $dest, $options );
			wp_send_json_success( [ 'job_id' => $job_id ] );
		} catch ( Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function ajax_job_status(): void {
		check_ajax_referer( 'wc_cat_migrator', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [], 403 );

		$job = WC_Cat_Job_Manager::get( (int) ( $_GET['job_id'] ?? 0 ) );
		if ( ! $job ) wp_send_json_error( [ 'message' => 'İş bulunamadı.' ], 404 );

		$errors = json_decode( $job->errors ?: '[]', true ) ?: [];
		$pct    = $job->total > 0 ? round( $job->processed / $job->total * 100 ) : ( $job->status === 'completed' ? 100 : 0 );

		wp_send_json_success( [
			'status'    => $job->status,
			'total'     => (int) $job->total,
			'processed' => (int) $job->processed,
			'percent'   => $pct,
			'errors'    => $errors,
		] );
	}
}
