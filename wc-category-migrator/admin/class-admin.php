<?php
defined( 'ABSPATH' ) || exit;

class WC_Cat_Migrator_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
		add_action( 'admin_post_wc_cat_export', [ __CLASS__, 'handle_export' ] );
		add_action( 'wp_ajax_wc_cat_import', [ __CLASS__, 'handle_import' ] );
	}

	public static function register_menu(): void {
		global $menu;
		$parent = 'woocommerce';
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
			</nav>

			<div class="wc-cat-content">
				<?php $tab === 'export' ? self::render_export() : self::render_import(); ?>
			</div>
		</div>
		<?php
	}

	private static function render_export(): void {
		$total = wp_count_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
		?>
		<div class="wc-cat-card">
			<h2>Kategorileri Dışa Aktar</h2>
			<p class="description">
				Tüm kategoriler, <strong>hiyerarşi sırası korunarak</strong> (üst → alt) XML dosyasına yazılır.
				Kategori resimleri URL olarak dahil edilir; içe aktarmada otomatik indirilir.
			</p>

			<div class="wc-cat-stat">
				Toplam <strong><?php echo esc_html( (int) $total ); ?></strong> kategori mevcut.
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wc_cat_export', '_wpnonce_export' ); ?>
				<input type="hidden" name="action" value="wc_cat_export">

				<table class="form-table">
					<tr>
						<th>Seçenekler</th>
						<td>
							<label><input type="checkbox" name="include_images" value="1" checked>
								Kategori resimlerini dahil et (URL olarak)</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary button-large">
						⬇ XML Olarak İndir
					</button>
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
				Bu araçla dışa aktarılan XML dosyasını yükleyin. Hiyerarşi ve resimler otomatik olarak aktarılır.
			</p>

			<form id="wc-cat-import-form" enctype="multipart/form-data">
				<table class="form-table">
					<tr>
						<th>XML Dosyası</th>
						<td>
							<input type="file" name="xml_file" id="cat-xml-file" accept=".xml" required>
						</td>
					</tr>
					<tr>
						<th>Mevcut kategoriler</th>
						<td>
							<label>
								<input type="radio" name="update_existing" value="1" checked>
								Güncelle (isim, açıklama, üst kategori ve resim)
							</label><br>
							<label>
								<input type="radio" name="update_existing" value="0">
								Atla (sadece yeni kategorileri ekle)
							</label>
						</td>
					</tr>
					<tr>
						<th>Resimler</th>
						<td>
							<label>
								<input type="checkbox" name="download_images" value="1" checked>
								Resimleri indir ve medya kütüphanesine ekle
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" id="btn-import" class="button button-primary button-large">
						⬆ İçe Aktarmayı Başlat
					</button>
					<span class="spinner"></span>
				</p>
			</form>

			<div id="import-result" style="display:none;"></div>
		</div>
		<?php
	}

	// ---------------------------------------------------------------
	// Handlers
	// ---------------------------------------------------------------

	/** Dışa aktarma — doğrudan dosya indirmesi */
	public static function handle_export(): void {
		check_admin_referer( 'wc_cat_export', '_wpnonce_export' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Yetkiniz yok.' );
		}

		$include_images = ! empty( $_POST['include_images'] );
		$exporter = new WC_Cat_Migrator_Exporter( $include_images );
		$xml      = $exporter->export();

		$filename = 'wc-kategoriler-' . date( 'Ymd-His' ) . '.xml';

		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Content-Length: ' . strlen( $xml ) );

		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/** İçe aktarma — AJAX */
	public static function handle_import(): void {
		check_ajax_referer( 'wc_cat_migrator', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Yetkiniz yok.' ], 403 );
		}

		if ( empty( $_FILES['xml_file']['tmp_name'] ) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( [ 'message' => 'Dosya yüklenemedi.' ] );
		}

		$file = $_FILES['xml_file'];
		if ( strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) !== 'xml' ) {
			wp_send_json_error( [ 'message' => 'Yalnızca .xml dosyası kabul edilir.' ] );
		}

		$xml = file_get_contents( $file['tmp_name'] );
		if ( $xml === false ) {
			wp_send_json_error( [ 'message' => 'Dosya okunamadı.' ] );
		}

		set_time_limit( 300 );

		$options = [
			'download_images' => ! empty( $_POST['download_images'] ),
			'update_existing' => ( ( $_POST['update_existing'] ?? '1' ) === '1' ),
		];

		$importer = new WC_Cat_Migrator_Importer( $options );
		$results  = $importer->import( $xml );

		wp_send_json_success( [ 'results' => $results ] );
	}
}
