<?php
defined( 'ABSPATH' ) || exit;

class WC_XML_Migrator_Admin {

	public static function init(): void {
		// Öncelik 99: WooCommerce kendi menüsünü (öncelik 10) eklemiş olur
		add_action( 'admin_menu', [ __CLASS__, 'register_menus' ], 99 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

		// AJAX — arka plan işlemler
		add_action( 'wp_ajax_wc_xml_start_export', [ __CLASS__, 'ajax_start_export' ] );
		add_action( 'wp_ajax_wc_xml_start_import', [ __CLASS__, 'ajax_start_import' ] );
		add_action( 'wp_ajax_wc_xml_job_status',   [ __CLASS__, 'ajax_job_status' ] );
		add_action( 'wp_ajax_wc_xml_jobs_list',    [ __CLASS__, 'ajax_jobs_list' ] );
	}

	public static function register_menus(): void {
		global $menu;

		// WooCommerce menüsü var mı kontrol et
		$parent = 'woocommerce';
		$wc_menu_exists = false;
		if ( is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( isset( $item[2] ) && $item[2] === 'woocommerce' ) {
					$wc_menu_exists = true;
					break;
				}
			}
		}

		if ( $wc_menu_exists ) {
			// WooCommerce altına ekle
			add_submenu_page(
				$parent,
				__( 'XML Göç Aracı', 'wc-xml-migrator' ),
				__( 'XML Göç Aracı', 'wc-xml-migrator' ),
				'manage_options',
				'wc-xml-migrator',
				[ __CLASS__, 'render_page' ]
			);
		} else {
			// Bağımsız üst-düzey menü olarak ekle
			add_menu_page(
				__( 'XML Göç Aracı', 'wc-xml-migrator' ),
				__( 'XML Göç Aracı', 'wc-xml-migrator' ),
				'manage_options',
				'wc-xml-migrator',
				[ __CLASS__, 'render_page' ],
				'dashicons-migrate',
				56
			);
		}
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
				'starting'    => __( 'Başlatılıyor...', 'wc-xml-migrator' ),
				'processing'  => __( 'İşleniyor', 'wc-xml-migrator' ),
				'noFile'      => __( 'Lütfen bir XML dosyası seçin.', 'wc-xml-migrator' ),
				'completed'   => __( 'Tamamlandı', 'wc-xml-migrator' ),
				'failed'      => __( 'Hata oluştu', 'wc-xml-migrator' ),
				'download'    => __( 'XML İndir', 'wc-xml-migrator' ),
				'bgNote'      => __( 'İşlem arka planda devam ediyor. Sayfayı kapatabilirsiniz.', 'wc-xml-migrator' ),
			],
		] );
	}

	// ---------------------------------------------------------------
	// Sayfa render
	// ---------------------------------------------------------------

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
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'jobs', menu_page_url( 'wc-xml-migrator', false ) ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'jobs' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'İşlem Geçmişi', 'wc-xml-migrator' ); ?>
				</a>
			</nav>

			<div class="wc-xml-migrator-content">
				<?php
				if ( $active_tab === 'export' ) {
					self::render_export_tab();
				} elseif ( $active_tab === 'import' ) {
					self::render_import_tab();
				} else {
					self::render_jobs_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	private static function render_export_tab(): void {
		$categories = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ] );
		?>
		<div class="wc-xml-card">
			<h2><?php esc_html_e( 'Ürün Dışa Aktar', 'wc-xml-migrator' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'İşlem arka planda çalışır — tarayıcıyı kapatabilirsiniz. Tamamlandığında bu sayfadan XML dosyasını indirebilirsiniz.', 'wc-xml-migrator' ); ?>
			</p>

			<form id="wc-xml-export-form">
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Ürün Durumu', 'wc-xml-migrator' ); ?></th>
						<td>
							<select name="status">
								<option value="publish"><?php esc_html_e( 'Yayınlanmış', 'wc-xml-migrator' ); ?></option>
								<option value="draft"><?php esc_html_e( 'Taslak', 'wc-xml-migrator' ); ?></option>
								<option value="any"><?php esc_html_e( 'Tümü', 'wc-xml-migrator' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Kategoriler', 'wc-xml-migrator' ); ?></th>
						<td>
							<select name="categories[]" multiple size="6" style="width:300px;">
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( $cat->slug ); ?>">
										<?php echo esc_html( $cat->name ); ?> (<?php echo esc_html( $cat->count ); ?>)
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Boş = tümü. Ctrl/Cmd ile çoklu seçim.', 'wc-xml-migrator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Seçenekler', 'wc-xml-migrator' ); ?></th>
						<td>
							<label><input type="checkbox" name="include_images" value="1" checked> <?php esc_html_e( 'Görsel URL\'leri', 'wc-xml-migrator' ); ?></label><br>
							<label><input type="checkbox" name="include_variations" value="1" checked> <?php esc_html_e( 'Varyasyonlar', 'wc-xml-migrator' ); ?></label><br>
							<label><input type="checkbox" name="include_term_images" value="1" checked> <?php esc_html_e( 'Kategori ve marka görselleri', 'wc-xml-migrator' ); ?></label><br>
							<label><input type="checkbox" name="include_meta" value="1"> <?php esc_html_e( 'Özel meta verileri', 'wc-xml-migrator' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Toplu İşlem Boyutu', 'wc-xml-migrator' ); ?></th>
						<td>
							<input type="number" name="batch_size" value="20" min="1" max="100" style="width:80px;">
							<p class="description"><?php esc_html_e( 'Her arka plan turunda işlenecek ürün sayısı.', 'wc-xml-migrator' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" id="btn-export" class="button button-primary"><?php esc_html_e( 'Dışa Aktarmayı Başlat', 'wc-xml-migrator' ); ?></button>
					<span class="spinner"></span>
				</p>
			</form>

			<div id="export-progress" class="wc-xml-progress-wrap" style="display:none;">
				<div class="wc-xml-bg-note"><?php esc_html_e( 'İşlem arka planda devam ediyor. Sayfayı kapatabilirsiniz.', 'wc-xml-migrator' ); ?></div>
				<div class="wc-xml-progress-bar-outer">
					<div class="wc-xml-progress-bar-inner" style="width:0%"></div>
				</div>
				<div class="wc-xml-progress-text">0 / 0</div>
				<div class="wc-xml-progress-status"></div>
			</div>
		</div>
		<?php
	}

	private static function render_import_tab(): void {
		?>
		<div class="wc-xml-card">
			<h2><?php esc_html_e( 'Ürün İçe Aktar', 'wc-xml-migrator' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'XML dosyası yüklendikten sonra içe aktarma arka planda çalışır — tarayıcıyı kapatabilirsiniz.', 'wc-xml-migrator' ); ?>
			</p>

			<form id="wc-xml-import-form" enctype="multipart/form-data">
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'XML Dosyası', 'wc-xml-migrator' ); ?></th>
						<td>
							<input type="file" name="xml_file" id="xml-file" accept=".xml" required>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Mevcut Ürünler (SKU eşleşmesi)', 'wc-xml-migrator' ); ?></th>
						<td>
							<select name="duplicate_strategy">
								<option value="update"><?php esc_html_e( 'Güncelle', 'wc-xml-migrator' ); ?></option>
								<option value="skip"><?php esc_html_e( 'Atla', 'wc-xml-migrator' ); ?></option>
								<option value="create_new"><?php esc_html_e( 'Yeni olarak oluştur', 'wc-xml-migrator' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Görseller', 'wc-xml-migrator' ); ?></th>
						<td>
							<label><input type="checkbox" name="download_images" value="1" checked> <?php esc_html_e( 'Görselleri indir ve medya kütüphanesine ekle', 'wc-xml-migrator' ); ?></label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" id="btn-import" class="button button-primary"><?php esc_html_e( 'İçe Aktarmayı Başlat', 'wc-xml-migrator' ); ?></button>
					<span class="spinner"></span>
				</p>
			</form>

			<div id="import-progress" class="wc-xml-progress-wrap" style="display:none;">
				<div class="wc-xml-bg-note"><?php esc_html_e( 'İşlem arka planda devam ediyor. Sayfayı kapatabilirsiniz.', 'wc-xml-migrator' ); ?></div>
				<div class="wc-xml-progress-bar-outer">
					<div class="wc-xml-progress-bar-inner" style="width:0%"></div>
				</div>
				<div class="wc-xml-progress-text">0 / 0</div>
				<div class="wc-xml-progress-status"></div>
			</div>
		</div>
		<?php
	}

	private static function render_jobs_tab(): void {
		$jobs = WC_XML_Job_Manager::get_recent( 20 );
		?>
		<div class="wc-xml-card">
			<h2><?php esc_html_e( 'İşlem Geçmişi', 'wc-xml-migrator' ); ?></h2>
			<?php if ( empty( $jobs ) ) : ?>
				<p><?php esc_html_e( 'Henüz hiç işlem başlatılmamış.', 'wc-xml-migrator' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width:50px">#</th>
							<th><?php esc_html_e( 'Tür', 'wc-xml-migrator' ); ?></th>
							<th><?php esc_html_e( 'Durum', 'wc-xml-migrator' ); ?></th>
							<th><?php esc_html_e( 'İlerleme', 'wc-xml-migrator' ); ?></th>
							<th><?php esc_html_e( 'Tarih', 'wc-xml-migrator' ); ?></th>
							<th><?php esc_html_e( 'Dosya', 'wc-xml-migrator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $jobs as $job ) :
							$pct = $job->total_items > 0
								? round( $job->processed / $job->total_items * 100 )
								: ( $job->status === 'completed' ? 100 : 0 );
							$errors = json_decode( $job->errors ?: '[]', true ) ?: [];
						?>
						<tr>
							<td><?php echo esc_html( $job->id ); ?></td>
							<td>
								<span class="wc-xml-type-badge wc-xml-type-<?php echo esc_attr( $job->job_type ); ?>">
									<?php echo $job->job_type === 'export'
										? esc_html__( 'Dışa Aktar', 'wc-xml-migrator' )
										: esc_html__( 'İçe Aktar', 'wc-xml-migrator' ); ?>
								</span>
							</td>
							<td>
								<span class="wc-xml-status-badge wc-xml-status-<?php echo esc_attr( $job->status ); ?>">
									<?php echo esc_html( self::status_label( $job->status ) ); ?>
								</span>
							</td>
							<td>
								<div class="wc-xml-mini-progress">
									<div class="wc-xml-mini-bar" style="width:<?php echo esc_attr( $pct ); ?>%"></div>
								</div>
								<small><?php echo esc_html( $job->processed ); ?> / <?php echo esc_html( $job->total_items ); ?></small>
								<?php if ( ! empty( $errors ) ) : ?>
									<br><small style="color:#dc3232"><?php echo esc_html( count( $errors ) ); ?> hata</small>
								<?php endif; ?>
							</td>
							<td><?php
								// WordPress'in kendi timezone ayarını kullan (Türkiye: Europe/Istanbul)
								try {
									$tz  = new DateTimeZone( wp_timezone_string() );
									$dt  = new DateTime( $job->created_at, new DateTimeZone( 'UTC' ) );
									$dt->setTimezone( $tz );
									echo esc_html( $dt->format( 'd.m.Y H:i' ) );
								} catch ( Exception $e ) {
									echo esc_html( $job->created_at );
								}
							?></td>
							<td>
								<?php if ( $job->file_url ) : ?>
									<a href="<?php echo esc_url( $job->file_url ); ?>" download class="button button-small">
										<?php esc_html_e( 'İndir', 'wc-xml-migrator' ); ?>
									</a>
								<?php else : ?>
									—
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

	private static function status_label( string $status ): string {
		$map = [
			'pending'    => 'Bekliyor',
			'processing' => 'İşleniyor',
			'completed'  => 'Tamamlandı',
			'failed'     => 'Hatalı',
		];
		return $map[ $status ] ?? $status;
	}

	// ---------------------------------------------------------------
	// AJAX handlers
	// ---------------------------------------------------------------

	public static function ajax_start_export(): void {
		check_ajax_referer( 'wc_xml_migrator', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Yetkiniz yok.' ], 403 );
		}

		$options = [
			'status'              => sanitize_text_field( $_POST['status'] ?? 'publish' ),
			'categories'          => array_map( 'sanitize_text_field', (array) ( $_POST['categories'] ?? [] ) ),
			'include_images'      => ! empty( $_POST['include_images'] ),
			'include_variations'  => ! empty( $_POST['include_variations'] ),
			'include_term_images' => ! empty( $_POST['include_term_images'] ),
			'include_meta'        => ! empty( $_POST['include_meta'] ),
			'batch_size'          => min( 100, max( 1, (int) ( $_POST['batch_size'] ?? 20 ) ) ),
		];

		try {
			$job_id = WC_XML_Background_Exporter::start( $options );
			wp_send_json_success( [ 'job_id' => $job_id ] );
		} catch ( Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function ajax_start_import(): void {
		check_ajax_referer( 'wc_xml_migrator', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => 'Yetkiniz yok.' ], 403 );
		}

		if ( empty( $_FILES['xml_file']['tmp_name'] ) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( [ 'message' => 'Dosya yüklenemedi.' ] );
		}

		$file = $_FILES['xml_file'];
		if ( strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) !== 'xml' ) {
			wp_send_json_error( [ 'message' => 'Sadece .xml dosyaları kabul edilir.' ] );
		}

		// Kalıcı konuma taşı
		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . '/wc-xml-migrator';
		wp_mkdir_p( $dir );

		$dest = $dir . '/wc-import-' . wp_generate_password( 8, false ) . '.xml';
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			wp_send_json_error( [ 'message' => 'Dosya kaydedilemedi.' ] );
		}

		$options = [
			'duplicate_strategy' => sanitize_key( $_POST['duplicate_strategy'] ?? 'update' ),
			'download_images'    => ! empty( $_POST['download_images'] ),
		];

		try {
			$job_id = WC_XML_Background_Importer::start( $dest, $options );
			wp_send_json_success( [ 'job_id' => $job_id ] );
		} catch ( Throwable $e ) {
			wp_send_json_error( [ 'message' => $e->getMessage() ] );
		}
	}

	public static function ajax_job_status(): void {
		check_ajax_referer( 'wc_xml_migrator', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [], 403 );
		}

		$job_id = (int) ( $_GET['job_id'] ?? 0 );
		$job    = WC_XML_Job_Manager::get( $job_id );

		if ( ! $job ) {
			wp_send_json_error( [ 'message' => 'İş bulunamadı.' ], 404 );
		}

		$errors = json_decode( $job->errors ?: '[]', true ) ?: [];

		wp_send_json_success( [
			'status'      => $job->status,
			'total'       => (int) $job->total_items,
			'processed'   => (int) $job->processed,
			'percent'     => $job->total_items > 0
				? round( $job->processed / $job->total_items * 100 )
				: ( $job->status === 'completed' ? 100 : 0 ),
			'file_url'    => $job->file_url,
			'errors'      => $errors,
		] );
	}

	public static function ajax_jobs_list(): void {
		check_ajax_referer( 'wc_xml_migrator', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [], 403 );
		}

		$jobs = WC_XML_Job_Manager::get_recent( 5 );
		$data = array_map( function ( $job ) {
			$errors = json_decode( $job->errors ?: '[]', true ) ?: [];
			return [
				'id'        => (int) $job->id,
				'job_type'  => $job->job_type,
				'status'    => $job->status,
				'total'     => (int) $job->total_items,
				'processed' => (int) $job->processed,
				'percent'   => $job->total_items > 0
					? round( $job->processed / $job->total_items * 100 )
					: ( $job->status === 'completed' ? 100 : 0 ),
				'file_url'  => $job->file_url,
				'errors'    => count( $errors ),
			];
		}, $jobs );

		wp_send_json_success( $data );
	}
}
