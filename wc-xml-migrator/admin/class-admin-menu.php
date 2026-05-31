<?php
defined( 'ABSPATH' ) || exit;

class WC_XML_Migrator_Admin {

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_ajax_wc_xml_export', [ __CLASS__, 'ajax_export' ] );
		add_action( 'wp_ajax_wc_xml_import', [ __CLASS__, 'ajax_import' ] );
	}

	public static function register_menus(): void {
		$hook = add_submenu_page(
			'woocommerce',
			__( 'XML Göç Aracı', 'wc-xml-migrator' ),
			__( 'XML Göç Aracı', 'wc-xml-migrator' ),
			'manage_woocommerce',
			'wc-xml-migrator',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'wc-xml-migrator' ) === false ) return;

		wp_enqueue_style(
			'wc-xml-migrator',
			WC_XML_MIGRATOR_URL . 'assets/css/admin.css',
			[],
			WC_XML_MIGRATOR_VERSION
		);

		wp_enqueue_script(
			'wc-xml-migrator',
			WC_XML_MIGRATOR_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			WC_XML_MIGRATOR_VERSION,
			true
		);

		wp_localize_script( 'wc-xml-migrator', 'wcXmlMigrator', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'wc_xml_migrator' ),
			'i18n'    => [
				'exporting'  => __( 'Dışa aktarılıyor...', 'wc-xml-migrator' ),
				'importing'  => __( 'İçe aktarılıyor...', 'wc-xml-migrator' ),
				'noFile'     => __( 'Lütfen bir XML dosyası seçin.', 'wc-xml-migrator' ),
				'success'    => __( 'İşlem tamamlandı.', 'wc-xml-migrator' ),
			],
		] );
	}

	public static function render_page(): void {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'export';
		?>
		<div class="wrap wc-xml-migrator-wrap">
			<h1><?php esc_html_e( 'WooCommerce XML Göç Aracı', 'wc-xml-migrator' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'export', menu_page_url( 'wc-xml-migrator', false ) ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Dışa Aktar', 'wc-xml-migrator' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'import', menu_page_url( 'wc-xml-migrator', false ) ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'import' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'İçe Aktar', 'wc-xml-migrator' ); ?>
				</a>
			</nav>

			<div class="wc-xml-migrator-content">
				<?php
				if ( $active_tab === 'export' ) {
					self::render_export_tab();
				} else {
					self::render_import_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	private static function render_export_tab(): void {
		$categories = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'orderby'    => 'name',
		] );
		?>
		<div class="wc-xml-card">
			<h2><?php esc_html_e( 'Ürün Dışa Aktar', 'wc-xml-migrator' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'WooCommerce ürünlerinizi XML formatında dışa aktarın. İndirilen XML dosyasını başka bir WooCommerce sitenize aktarabilirsiniz.', 'wc-xml-migrator' ); ?>
			</p>

			<form id="wc-xml-export-form">
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Ürün Durumu', 'wc-xml-migrator' ); ?></th>
						<td>
							<select name="status" id="export-status">
								<option value="publish"><?php esc_html_e( 'Yayınlanmış', 'wc-xml-migrator' ); ?></option>
								<option value="draft"><?php esc_html_e( 'Taslak', 'wc-xml-migrator' ); ?></option>
								<option value="any"><?php esc_html_e( 'Tümü', 'wc-xml-migrator' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Kategoriler', 'wc-xml-migrator' ); ?></th>
						<td>
							<select name="categories[]" id="export-categories" multiple size="6" style="width:300px;">
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat->slug ); ?>">
										<?php echo esc_html( $cat->name ); ?> (<?php echo esc_html( $cat->count ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Boş bırakılırsa tüm kategoriler aktarılır. Ctrl/Cmd ile birden fazla seçebilirsiniz.', 'wc-xml-migrator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Seçenekler', 'wc-xml-migrator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="include_images" value="1" checked>
								<?php esc_html_e( 'Görsel URL\'lerini dahil et', 'wc-xml-migrator' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="include_variations" value="1" checked>
								<?php esc_html_e( 'Varyasyonları dahil et', 'wc-xml-migrator' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="include_meta" value="1">
								<?php esc_html_e( 'Özel meta verileri dahil et', 'wc-xml-migrator' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Toplu İşlem Boyutu', 'wc-xml-migrator' ); ?></th>
						<td>
							<input type="number" name="batch_size" value="50" min="1" max="500" style="width:80px;">
							<p class="description"><?php esc_html_e( 'Büyük mağazalarda bellek sorununu önlemek için düşük tutun.', 'wc-xml-migrator' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" id="btn-export" class="button button-primary">
						<?php esc_html_e( 'XML Olarak Dışa Aktar', 'wc-xml-migrator' ); ?>
					</button>
					<span class="spinner"></span>
				</p>
			</form>

			<div id="export-result" class="wc-xml-notice" style="display:none;"></div>
		</div>
		<?php
	}

	private static function render_import_tab(): void {
		?>
		<div class="wc-xml-card">
			<h2><?php esc_html_e( 'Ürün İçe Aktar', 'wc-xml-migrator' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Bu araç ile dışa aktarılan XML dosyalarını içe aktarabilirsiniz. Görseller otomatik olarak indirilir.', 'wc-xml-migrator' ); ?>
			</p>

			<form id="wc-xml-import-form" enctype="multipart/form-data">
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'XML Dosyası', 'wc-xml-migrator' ); ?></th>
						<td>
							<input type="file" name="xml_file" id="xml-file" accept=".xml" required>
							<p class="description"><?php esc_html_e( 'Dışa aktarılan .xml dosyasını seçin.', 'wc-xml-migrator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Mevcut Ürünler (SKU eşleşmesi)', 'wc-xml-migrator' ); ?></th>
						<td>
							<select name="duplicate_strategy" id="duplicate-strategy">
								<option value="update"><?php esc_html_e( 'Güncelle', 'wc-xml-migrator' ); ?></option>
								<option value="skip"><?php esc_html_e( 'Atla', 'wc-xml-migrator' ); ?></option>
								<option value="create_new"><?php esc_html_e( 'Yeni olarak oluştur', 'wc-xml-migrator' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Seçenekler', 'wc-xml-migrator' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="download_images" value="1" checked>
								<?php esc_html_e( 'Görselleri indir ve medya kütüphanesine ekle', 'wc-xml-migrator' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" id="btn-import" class="button button-primary">
						<?php esc_html_e( 'XML\'den İçe Aktar', 'wc-xml-migrator' ); ?>
					</button>
					<span class="spinner"></span>
				</p>
			</form>

			<div id="import-result" class="wc-xml-notice" style="display:none;"></div>
		</div>
		<?php
	}

	/**
	 * AJAX: Dışa aktar
	 */
	public static function ajax_export(): void {
		check_ajax_referer( 'wc_xml_migrator', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Yetkiniz yok.', 'wc-xml-migrator' ) ], 403 );
		}

		$options = [
			'status'             => sanitize_text_field( $_POST['status'] ?? 'publish' ),
			'categories'         => array_map( 'sanitize_text_field', (array) ( $_POST['categories'] ?? [] ) ),
			'include_images'     => ! empty( $_POST['include_images'] ),
			'include_variations' => ! empty( $_POST['include_variations'] ),
			'include_meta'       => ! empty( $_POST['include_meta'] ),
			'batch_size'         => min( 500, max( 1, (int) ( $_POST['batch_size'] ?? 50 ) ) ),
		];

		try {
			$exporter = new WC_XML_Exporter( $options );
			$xml      = $exporter->export();

			$filename = 'wc-products-' . date( 'Ymd-His' ) . '.xml';
			$upload   = wp_upload_bits( $filename, null, $xml );

			if ( $upload['error'] ) {
				wp_send_json_error( [ 'message' => $upload['error'] ] );
			}

			wp_send_json_success( [
				'message'   => __( 'Dışa aktarma tamamlandı.', 'wc-xml-migrator' ),
				'file_url'  => $upload['url'],
				'file_name' => $filename,
			] );
		} catch ( Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	/**
	 * AJAX: İçe aktar
	 */
	public static function ajax_import(): void {
		check_ajax_referer( 'wc_xml_migrator', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Yetkiniz yok.', 'wc-xml-migrator' ) ], 403 );
		}

		if ( empty( $_FILES['xml_file']['tmp_name'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Dosya yüklenemedi.', 'wc-xml-migrator' ) ] );
		}

		$file = $_FILES['xml_file'];

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( [ 'message' => __( 'Dosya yükleme hatası: ', 'wc-xml-migrator' ) . $file['error'] ] );
		}

		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $ext !== 'xml' ) {
			wp_send_json_error( [ 'message' => __( 'Sadece .xml dosyaları kabul edilir.', 'wc-xml-migrator' ) ] );
		}

		$xml_content = file_get_contents( $file['tmp_name'] );
		if ( $xml_content === false ) {
			wp_send_json_error( [ 'message' => __( 'Dosya okunamadı.', 'wc-xml-migrator' ) ] );
		}

		$options = [
			'duplicate_strategy' => sanitize_key( $_POST['duplicate_strategy'] ?? 'update' ),
			'download_images'    => ! empty( $_POST['download_images'] ),
		];

		try {
			// Büyük import için zaman sınırını artır
			set_time_limit( 600 );

			$importer = new WC_XML_Importer( $options );
			$results  = $importer->import( $xml_content );

			$message = sprintf(
				__( 'Tamamlandı: %d oluşturuldu, %d güncellendi, %d atlandı.', 'wc-xml-migrator' ),
				$results['created'],
				$results['updated'],
				$results['skipped']
			);

			wp_send_json_success( [
				'message' => $message,
				'results' => $results,
			] );
		} catch ( Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}
}
