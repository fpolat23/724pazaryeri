<?php
defined( 'ABSPATH' ) || exit;

class PZV_Mig_Admin_UI {

	const NONCE = 'pzv_mig';

	public static function init(): void {
		add_action( 'admin_menu',            [ __CLASS__, 'menu' ], 99 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );

		// AJAX
		$actions = [
			'pzv_mig_save_settings',
			'pzv_mig_test_connection',
			'pzv_mig_start_member_import',
			'pzv_mig_member_batch',
			'pzv_mig_start_vendor_import',
			'pzv_mig_vendor_batch',
			'pzv_mig_start_link',
			'pzv_mig_link_batch',
			'pzv_mig_start_comm_import',
			'pzv_mig_comm_batch',
			'pzv_mig_reset',
		];
		foreach ( $actions as $a ) {
			add_action( "wp_ajax_{$a}", [ __CLASS__, "ajax_{$a}" ] );
		}
	}

	public static function menu(): void {
		$parent = class_exists( 'WooCommerce' ) ? 'woocommerce' : null;
		if ( $parent ) {
			add_submenu_page( $parent, 'Vendor Migrator', 'Vendor Migrator', 'manage_options', 'pzv-vendor-migrator', [ __CLASS__, 'render_page' ] );
		} else {
			add_menu_page( 'Vendor Migrator', 'Vendor Migrator', 'manage_options', 'pzv-vendor-migrator', [ __CLASS__, 'render_page' ], 'dashicons-migrate', 60 );
		}
	}

	public static function enqueue( string $hook ): void {
		if ( strpos( $hook, 'pzv-vendor-migrator' ) === false ) return;
		wp_enqueue_style(  'pzv-mig', PZV_MIG_URL . 'assets/css/admin.css', [], PZV_MIG_VERSION );
		wp_enqueue_script( 'pzv-mig', PZV_MIG_URL . 'assets/js/admin.js',   [ 'jquery' ], PZV_MIG_VERSION, true );
		wp_localize_script( 'pzv-mig', 'pzvMig', [
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( self::NONCE ),
			'confirmReset'  => 'Tüm aktarma ilerlemesini sıfırlamak istediğinizden emin misiniz?',
		] );
	}

	// ==============================
	//  Page render
	// ==============================

	public static function render_page(): void {
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		$page_url = menu_page_url( 'pzv-vendor-migrator', false );
		$tabs    = [
			'settings'    => '⚙ Ayarlar',
			'members'     => '👤 Üyeleri Aktar',
			'vendors'     => '👥 Satıcıları Aktar',
			'link'        => '🔗 Ürünleri Bağla',
			'commissions' => '💰 Komisyonları Aktar',
		];
		?>
		<div class="wrap pzv-mig-wrap">
			<h1>⚡ PZV Vendor Migrator</h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $page_url ) ); ?>"
				   class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
				<?php endforeach; ?>
			</nav>
			<div class="pzv-mig-content">
				<?php
				match ( $tab ) {
					'members'     => self::tab_members(),
					'vendors'     => self::tab_vendors(),
					'link'        => self::tab_link(),
					'commissions' => self::tab_commissions(),
					default       => self::tab_settings(),
				};
				?>
			</div>
		</div>
		<?php
	}

	// ---- Settings tab ----

	private static function tab_settings(): void {
		$s = PZV_Mig_State::get_settings();
		?>
		<div class="pzv-mig-card">
			<h2>Kaynak Site Ayarları</h2>
			<p class="description">
				Kaynak siteye de bu eklentiyi yükleyin. Kaynak sitede WordPress yönetici kullanıcısı için
				<strong>Kullanıcılar → Profiliniz → Uygulama Şifreleri</strong> bölümünden bir şifre oluşturun.
			</p>
			<table class="form-table">
				<tr>
					<th>Kaynak Site URL</th>
					<td>
						<input type="url" id="pzv-source-url" class="regular-text" value="<?php echo esc_attr( $s['source_url'] ?? '' ); ?>" placeholder="https://kaynaksite.com">
					</td>
				</tr>
				<tr>
					<th>WP Kullanıcı Adı</th>
					<td>
						<input type="text" id="pzv-username" class="regular-text" value="<?php echo esc_attr( $s['username'] ?? '' ); ?>" placeholder="admin">
					</td>
				</tr>
				<tr>
					<th>Uygulama Şifresi</th>
					<td>
						<input type="password" id="pzv-app-password" class="regular-text" value="<?php echo esc_attr( $s['app_password'] ?? '' ); ?>" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx">
						<p class="description">Kullanıcılar → Profiliniz → Uygulama Şifreleri bölümünden oluşturun.</p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button id="pzv-save-settings" class="button button-primary">Kaydet</button>
				<button id="pzv-test-connection" class="button" style="margin-left:8px">🔍 Bağlantıyı Test Et</button>
				<span class="spinner"></span>
			</p>
			<div id="pzv-settings-msg" class="pzv-msg"></div>
			<div id="pzv-test-result"></div>

			<hr style="margin:24px 0">
			<h3 style="color:#dc3232">Sıfırla</h3>
			<p class="description">Tüm aktarma ilerlemesini sıfırlar (satıcı verileri silinmez).</p>
			<button id="pzv-reset-all" class="button" style="color:#dc3232;border-color:#dc3232">🗑 Tüm İlerlemeyi Sıfırla</button>
		</div>
		<?php
	}

	// ---- Members tab ----

	private static function tab_members(): void {
		$job = PZV_Mig_State::member_job();
		$pct = ( ! empty( $job['total'] ) && $job['total'] > 0 )
			? round( ( $job['processed'] ?? 0 ) / $job['total'] * 100 )
			: 0;
		$is_running   = ( $job['status'] ?? 'idle' ) === 'running';
		$is_completed = ( $job['status'] ?? 'idle' ) === 'completed';
		?>
		<div class="pzv-mig-card">
			<h2>Üyeleri Aktar</h2>
			<p class="description">
				Kaynak sitedeki tüm <code>customer</code> rolü üyeler (faturalandırma ve teslimat adresleri dahil)
				hedef siteye aktarılır. Mevcut üyeler güncellenir, yeni üyeler oluşturulur.
			</p>

			<?php if ( $is_completed ) : ?>
			<div class="pzv-notice success">
				✔ Üye aktarımı tamamlandı — <?php echo esc_html( $job['created'] ?? 0 ); ?> oluşturuldu,
				<?php echo esc_html( $job['updated'] ?? 0 ); ?> güncellendi.
			</div>
			<?php endif; ?>

			<p>
				<button id="pzv-start-members" class="button button-primary button-large" <?php echo $is_running ? 'disabled' : ''; ?>>
					<?php echo $is_completed ? '↺ Yeniden Aktar' : '▶ Üyeleri Aktar'; ?>
				</button>
				<span class="spinner"></span>
			</p>

			<div id="pzv-member-progress" class="pzv-progress-wrap" style="display:<?php echo ( $is_running || $is_completed ) ? 'block' : 'none'; ?>">
				<div class="pzv-progress-outer"><div class="pzv-progress-inner" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
				<div class="pzv-progress-text">
					<?php echo esc_html( ( $job['processed'] ?? 0 ) . ' / ' . ( $job['total'] ?? '?' ) . ' (' . $pct . '%)' ); ?>
				</div>
				<div class="pzv-progress-status <?php echo $is_completed ? 'status-done' : ''; ?>">
					<?php echo $is_completed ? 'Tamamlandı!' : ( $is_running ? 'Aktarılıyor…' : '' ); ?>
				</div>
				<div class="pzv-result-grid" id="pzv-member-results">
					<?php if ( $is_completed || $is_running ) : ?>
					<?php self::result_item( $job['created'] ?? 0, 'Oluşturuldu', 'created' ); ?>
					<?php self::result_item( $job['updated'] ?? 0, 'Güncellendi', 'updated' ); ?>
					<?php self::result_item( count( $job['errors'] ?? [] ), 'Hata', 'errors' ); ?>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $job['errors'] ) ) : ?>
				<div class="pzv-errors">
					<strong>Hatalar:</strong>
					<ul><?php foreach ( array_slice( $job['errors'], 0, 20 ) as $e ) : ?><li><?php echo esc_html( $e ); ?></li><?php endforeach; ?></ul>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// ---- Vendors tab ----

	private static function tab_vendors(): void {
		$job = PZV_Mig_State::vendor_job();
		$pct = ( ! empty( $job['total'] ) && $job['total'] > 0 )
			? round( ( $job['processed'] ?? 0 ) / $job['total'] * 100 )
			: 0;
		$is_running   = ( $job['status'] ?? 'idle' ) === 'running';
		$is_completed = ( $job['status'] ?? 'idle' ) === 'completed';
		?>
		<div class="pzv-mig-card">
			<h2>Satıcıları Aktar</h2>
			<p class="description">
				Kaynak sitedeki tüm <code>pzv_vendor</code> rolü kullanıcıları, profil bilgileri,
				logo/banner görselleri ile birlikte hedef siteye aktarılır.
			</p>

			<?php if ( $is_completed ) : ?>
			<div class="pzv-notice success">
				✔ Satıcı aktarımı tamamlandı — <?php echo esc_html( $job['created'] ?? 0 ); ?> oluşturuldu,
				<?php echo esc_html( $job['updated'] ?? 0 ); ?> güncellendi.
				Şimdi <a href="<?php echo esc_url( add_query_arg( 'tab', 'link', menu_page_url( 'pzv-vendor-migrator', false ) ) ); ?>">Ürünleri Bağla</a> adımına geçin.
			</div>
			<?php endif; ?>

			<p>
				<button id="pzv-start-vendors" class="button button-primary button-large" <?php echo $is_running ? 'disabled' : ''; ?>>
					<?php echo $is_completed ? '↺ Yeniden Aktar' : '▶ Satıcıları Aktar'; ?>
				</button>
				<span class="spinner"></span>
			</p>

			<div id="pzv-vendor-progress" class="pzv-progress-wrap" style="display:<?php echo ( $is_running || $is_completed ) ? 'block' : 'none'; ?>">
				<div class="pzv-progress-outer"><div class="pzv-progress-inner" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
				<div class="pzv-progress-text">
					<?php echo esc_html( ( $job['processed'] ?? 0 ) . ' / ' . ( $job['total'] ?? '?' ) . ' (' . $pct . '%)' ); ?>
				</div>
				<div class="pzv-progress-status <?php echo $is_completed ? 'status-done' : ''; ?>">
					<?php echo $is_completed ? 'Tamamlandı!' : ( $is_running ? 'Aktarılıyor…' : '' ); ?>
				</div>
				<div class="pzv-result-grid" id="pzv-vendor-results">
					<?php if ( $is_completed || $is_running ) : ?>
					<?php self::result_item( $job['created'] ?? 0, 'Oluşturuldu', 'created' ); ?>
					<?php self::result_item( $job['updated'] ?? 0, 'Güncellendi', 'updated' ); ?>
					<?php self::result_item( count( $job['errors'] ?? [] ), 'Hata', 'errors' ); ?>
					<?php endif; ?>
				</div>
				<?php if ( ! empty( $job['errors'] ) ) : ?>
				<div class="pzv-errors">
					<strong>Hatalar:</strong>
					<ul><?php foreach ( array_slice( $job['errors'], 0, 20 ) as $e ) : ?><li><?php echo esc_html( $e ); ?></li><?php endforeach; ?></ul>
				</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	// ---- Link tab ----

	private static function tab_link(): void {
		$vendor_job = PZV_Mig_State::vendor_job();
		$job        = PZV_Mig_State::link_job();
		$ready      = in_array( $vendor_job['status'] ?? 'idle', [ 'completed' ], true );
		$pct        = ( ! empty( $job['vendor_total'] ) && $job['vendor_total'] > 0 )
			? round( ( $job['vendor_index'] ?? 0 ) / $job['vendor_total'] * 100 )
			: 0;
		$is_running   = ( $job['status'] ?? 'idle' ) === 'running';
		$is_completed = ( $job['status'] ?? 'idle' ) === 'completed';
		?>
		<div class="pzv-mig-card">
			<h2>Ürünleri Satıcılara Bağla</h2>
			<p class="description">
				Her satıcının kaynak sitedeki ürünleri (SKU ile eşleştirilerek) hedef sitede ilgili satıcıya atanır.
				Ürün aktarımı (<em>WC REST Migrator</em>) tamamlandıktan sonra bu adımı çalıştırın.
			</p>

			<?php if ( ! $ready ) : ?>
			<p class="pzv-notice warning">⚠ Önce <strong>Satıcıları Aktar</strong> adımını tamamlayın.</p>
			<?php endif; ?>

			<?php if ( $is_completed ) : ?>
			<div class="pzv-notice success">
				✔ Ürün bağlama tamamlandı — <?php echo esc_html( $job['linked'] ?? 0 ); ?> ürün bağlandı,
				<?php echo esc_html( $job['missing'] ?? 0 ); ?> bulunamadı (henüz aktarılmamış olabilir).
			</div>
			<?php endif; ?>

			<p>
				<button id="pzv-start-link" class="button button-primary button-large"
					<?php echo ( ! $ready || $is_running ) ? 'disabled' : ''; ?>>
					<?php echo $is_completed ? '↺ Yeniden Bağla' : '🔗 Ürünleri Bağla'; ?>
				</button>
				<span class="spinner"></span>
			</p>

			<div id="pzv-link-progress" class="pzv-progress-wrap" style="display:<?php echo ( $is_running || $is_completed ) ? 'block' : 'none'; ?>">
				<div class="pzv-progress-outer"><div class="pzv-progress-inner" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
				<div class="pzv-progress-text">
					Satıcı <?php echo esc_html( $job['vendor_index'] ?? 0 ); ?> / <?php echo esc_html( $job['vendor_total'] ?? '?' ); ?>
				</div>
				<div class="pzv-progress-status <?php echo $is_completed ? 'status-done' : ''; ?>">
					<?php echo $is_completed ? 'Tamamlandı!' : ( $is_running ? 'Bağlanıyor…' : '' ); ?>
				</div>
				<div class="pzv-result-grid" id="pzv-link-results">
					<?php if ( $is_completed || $is_running ) : ?>
					<?php self::result_item( $job['linked'] ?? 0, 'Bağlandı', 'created' ); ?>
					<?php self::result_item( $job['missing'] ?? 0, 'Bulunamadı', 'skipped' ); ?>
					<?php self::result_item( count( $job['errors'] ?? [] ), 'Hata', 'errors' ); ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	// ---- Commissions tab ----

	private static function tab_commissions(): void {
		$vendor_job = PZV_Mig_State::vendor_job();
		$job        = PZV_Mig_State::comm_job();
		$ready      = in_array( $vendor_job['status'] ?? 'idle', [ 'completed' ], true );
		$pct        = ( ! empty( $job['total'] ) && $job['total'] > 0 )
			? round( ( $job['processed'] ?? 0 ) / $job['total'] * 100 )
			: 0;
		$is_running   = ( $job['status'] ?? 'idle' ) === 'running';
		$is_completed = ( $job['status'] ?? 'idle' ) === 'completed';
		?>
		<div class="pzv-mig-card">
			<h2>Komisyon Kayıtlarını Aktar</h2>
			<p class="description">
				Satıcıların kazanç geçmişi (<code>pzv_commissions</code> tablosu) aktarılır.
				Sipariş ID'leri kaynak siteden taşınır — referans amaçlıdır.
			</p>

			<?php if ( ! $ready ) : ?>
			<p class="pzv-notice warning">⚠ Önce <strong>Satıcıları Aktar</strong> adımını tamamlayın.</p>
			<?php endif; ?>

			<?php if ( $is_completed ) : ?>
			<div class="pzv-notice success">
				✔ Komisyon aktarımı tamamlandı — <?php echo esc_html( $job['imported'] ?? 0 ); ?> kayıt aktarıldı.
			</div>
			<?php endif; ?>

			<p>
				<button id="pzv-start-comm" class="button button-primary button-large"
					<?php echo ( ! $ready || $is_running ) ? 'disabled' : ''; ?>>
					<?php echo $is_completed ? '↺ Yeniden Aktar' : '💰 Komisyonları Aktar'; ?>
				</button>
				<span class="spinner"></span>
			</p>

			<div id="pzv-comm-progress" class="pzv-progress-wrap" style="display:<?php echo ( $is_running || $is_completed ) ? 'block' : 'none'; ?>">
				<div class="pzv-progress-outer"><div class="pzv-progress-inner" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
				<div class="pzv-progress-text">
					<?php echo esc_html( ( $job['processed'] ?? 0 ) . ' / ' . ( $job['total'] ?? '?' ) . ' (' . $pct . '%)' ); ?>
				</div>
				<div class="pzv-progress-status <?php echo $is_completed ? 'status-done' : ''; ?>">
					<?php echo $is_completed ? 'Tamamlandı!' : ( $is_running ? 'Aktarılıyor…' : '' ); ?>
				</div>
				<div class="pzv-result-grid">
					<?php self::result_item( $job['imported'] ?? 0, 'Aktarıldı', 'created' ); ?>
					<?php self::result_item( count( $job['errors'] ?? [] ), 'Hata', 'errors' ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	// ---- Helper ----

	private static function result_item( int $val, string $lbl, string $cls ): void {
		echo '<div class="pzv-result-item ' . esc_attr( $cls ) . '">'
			. '<span class="pzv-result-val">' . esc_html( $val ) . '</span>'
			. '<span class="pzv-result-lbl">' . esc_html( $lbl ) . '</span>'
			. '</div>';
	}

	// ==============================
	//  AJAX handlers
	// ==============================

	private static function check(): void {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
	}

	public static function ajax_pzv_mig_save_settings(): void {
		self::check();
		PZV_Mig_State::save_settings( [
			'source_url'   => esc_url_raw( $_POST['source_url']   ?? '' ),
			'username'     => sanitize_text_field( $_POST['username']     ?? '' ),
			'app_password' => sanitize_text_field( $_POST['app_password'] ?? '' ),
		] );
		wp_send_json_success( [ 'message' => 'Ayarlar kaydedildi.' ] );
	}

	public static function ajax_pzv_mig_test_connection(): void {
		self::check();
		$client = PZV_Mig_State::make_client();
		if ( ! $client ) wp_send_json_error( [ 'message' => 'Önce ayarları kaydedin.' ] );

		$result = $client->test();
		if ( $result['ok'] ) {
			wp_send_json_success( [ 'message' => $result['message'] ] );
		} else {
			wp_send_json_error( [ 'message' => $result['message'] ] );
		}
	}

	// ---- Member import ----

	public static function ajax_pzv_mig_start_member_import(): void {
		self::check();
		$client = PZV_Mig_State::make_client();
		if ( ! $client ) wp_send_json_error( [ 'message' => 'Ayarlar eksik.' ] );

		$total = $client->get_member_count();
		if ( $total === 0 ) wp_send_json_error( [ 'message' => 'Kaynak sitede üye bulunamadı.' ] );

		PZV_Mig_State::member_job_start( $total );
		wp_send_json_success( [ 'total' => $total ] );
	}

	public static function ajax_pzv_mig_member_batch(): void {
		self::check();
		@set_time_limit( 120 );
		wp_raise_memory_limit( 'admin' );

		$job = PZV_Mig_State::member_job();
		if ( ( $job['status'] ?? '' ) !== 'running' ) {
			wp_send_json_success( self::member_job_response( $job ) );
			return;
		}

		$client = PZV_Mig_State::make_client();
		if ( ! $client ) { PZV_Mig_State::member_job_update( [ 'status' => 'failed' ] ); wp_send_json_error(); }

		$page     = (int) ( $job['current_page'] ?? 0 ) + 1;
		$per_page = 50;

		$members = $client->get_members( $page, $per_page );
		if ( is_wp_error( $members ) ) {
			PZV_Mig_State::member_job_update( [
				'errors' => [ "Sayfa {$page}: " . $members->get_error_message() ],
			] );
			wp_send_json_success( self::member_job_response( PZV_Mig_State::member_job() ) );
			return;
		}

		if ( empty( $members ) ) {
			PZV_Mig_State::member_job_done();
			wp_send_json_success( self::member_job_response( PZV_Mig_State::member_job() ) );
			return;
		}

		$created = 0;
		$updated = 0;
		$errors  = [];

		foreach ( $members as $m ) {
			$result = PZV_Mig_Member_Importer::import_member( (array) $m );
			if ( $result['status'] === 'created' ) $created++;
			elseif ( $result['status'] === 'updated' ) $updated++;
			else $errors[] = ( $m['email'] ?? '?' ) . ': ' . $result['message'];
		}

		$is_last = count( $members ) < $per_page;

		PZV_Mig_State::member_job_update( [
			'current_page' => $page,
			'processed'    => min( ( $job['processed'] ?? 0 ) + count( $members ), $job['total'] ),
			'created'      => ( $job['created'] ?? 0 ) + $created,
			'updated'      => ( $job['updated'] ?? 0 ) + $updated,
			'errors'       => $errors,
			'status'       => $is_last ? 'completed' : 'running',
		] );

		wp_send_json_success( self::member_job_response( PZV_Mig_State::member_job() ) );
	}

	private static function member_job_response( array $job ): array {
		$total     = $job['total']     ?? 0;
		$processed = $job['processed'] ?? 0;
		return [
			'status'    => $job['status'] ?? 'idle',
			'total'     => $total,
			'processed' => $processed,
			'percent'   => $total > 0 ? round( $processed / $total * 100 ) : 0,
			'created'   => $job['created'] ?? 0,
			'updated'   => $job['updated'] ?? 0,
			'errors'    => $job['errors']  ?? [],
		];
	}

	// ---- Vendor import ----

	public static function ajax_pzv_mig_start_vendor_import(): void {
		self::check();
		$client = PZV_Mig_State::make_client();
		if ( ! $client ) wp_send_json_error( [ 'message' => 'Ayarlar eksik.' ] );

		$total = $client->get_vendor_count();
		if ( $total === 0 ) wp_send_json_error( [ 'message' => 'Kaynak sitede satıcı bulunamadı.' ] );

		PZV_Mig_State::vendor_job_start( $total );
		wp_send_json_success( [ 'total' => $total ] );
	}

	public static function ajax_pzv_mig_vendor_batch(): void {
		self::check();
		@set_time_limit( 120 );
		wp_raise_memory_limit( 'admin' );

		$job = PZV_Mig_State::vendor_job();
		if ( ( $job['status'] ?? '' ) !== 'running' ) {
			wp_send_json_success( self::vendor_job_response( $job ) );
			return;
		}

		$client = PZV_Mig_State::make_client();
		if ( ! $client ) { PZV_Mig_State::vendor_job_update( [ 'status' => 'failed' ] ); wp_send_json_error(); }

		$page     = (int) ( $job['current_page'] ?? 0 ) + 1;
		$per_page = 10; // small batches — each vendor may download 2 images

		$vendors = $client->get_vendors( $page, $per_page );
		if ( is_wp_error( $vendors ) ) {
			PZV_Mig_State::vendor_job_update( [
				'errors' => [ "Sayfa {$page}: " . $vendors->get_error_message() ],
			] );
			wp_send_json_success( self::vendor_job_response( PZV_Mig_State::vendor_job() ) );
			return;
		}

		if ( empty( $vendors ) ) {
			PZV_Mig_State::vendor_job_done();
			wp_send_json_success( self::vendor_job_response( PZV_Mig_State::vendor_job() ) );
			return;
		}

		$created      = 0;
		$updated      = 0;
		$errors       = [];
		$new_vendors  = [];

		foreach ( $vendors as $v ) {
			$result = PZV_Mig_Vendor_Importer::import_vendor( $v );
			if ( $result['status'] === 'created' ) {
				$created++;
				$new_vendors[] = [
					'source_id'  => (int) ( $v['id'] ?? 0 ),
					'dest_id'    => $result['user_id'],
					'email'      => $v['email'] ?? '',
					'store_name' => $result['store_name'],
				];
			} elseif ( $result['status'] === 'updated' ) {
				$updated++;
				$new_vendors[] = [
					'source_id'  => (int) ( $v['id'] ?? 0 ),
					'dest_id'    => $result['user_id'],
					'email'      => $v['email'] ?? '',
					'store_name' => $result['store_name'],
				];
			} else {
				$errors[] = ( $result['store_name'] ? "[{$result['store_name']}] " : '' ) . $result['message'];
			}
		}

		$is_last = count( $vendors ) < $per_page;

		PZV_Mig_State::vendor_job_update( [
			'current_page'        => $page,
			'processed'           => min( ( $job['processed'] ?? 0 ) + count( $vendors ), $job['total'] ),
			'created'             => ( $job['created'] ?? 0 ) + $created,
			'updated'             => ( $job['updated'] ?? 0 ) + $updated,
			'errors'              => $errors,
			'vendor_list_append'  => $new_vendors,
			'status'              => $is_last ? 'completed' : 'running',
		] );

		wp_send_json_success( self::vendor_job_response( PZV_Mig_State::vendor_job() ) );
	}

	private static function vendor_job_response( array $job ): array {
		$total     = $job['total']     ?? 0;
		$processed = $job['processed'] ?? 0;
		return [
			'status'    => $job['status'] ?? 'idle',
			'total'     => $total,
			'processed' => $processed,
			'percent'   => $total > 0 ? round( $processed / $total * 100 ) : 0,
			'created'   => $job['created'] ?? 0,
			'updated'   => $job['updated'] ?? 0,
			'errors'    => $job['errors']  ?? [],
		];
	}

	// ---- Product link ----

	public static function ajax_pzv_mig_start_link(): void {
		self::check();
		$vendor_job = PZV_Mig_State::vendor_job();
		$list = $vendor_job['vendor_list'] ?? [];
		if ( empty( $list ) ) wp_send_json_error( [ 'message' => 'Aktarılmış satıcı listesi bulunamadı. Önce satıcıları aktarın.' ] );

		PZV_Mig_State::link_job_start( count( $list ) );
		wp_send_json_success( [ 'vendor_count' => count( $list ) ] );
	}

	public static function ajax_pzv_mig_link_batch(): void {
		self::check();
		@set_time_limit( 120 );

		$job = PZV_Mig_State::link_job();
		if ( ( $job['status'] ?? '' ) !== 'running' ) {
			wp_send_json_success( self::link_job_response( $job ) );
			return;
		}

		$client = PZV_Mig_State::make_client();
		if ( ! $client ) { PZV_Mig_State::link_job_update( [ 'status' => 'failed' ] ); wp_send_json_error(); }

		$vendor_list  = $job['vendor_list']     ?? [];
		$idx          = (int) ( $job['vendor_index']    ?? 0 );
		$sku_page     = (int) ( $job['vendor_sku_page'] ?? 1 );
		$per_page     = 200;

		if ( $idx >= count( $vendor_list ) ) {
			PZV_Mig_State::link_job_done();
			wp_send_json_success( self::link_job_response( PZV_Mig_State::link_job() ) );
			return;
		}

		$vendor     = $vendor_list[ $idx ];
		$source_id  = (int) $vendor['source_id'];
		$dest_id    = (int) $vendor['dest_id'];

		$skus = $client->get_vendor_skus( $source_id, $sku_page, $per_page );
		if ( is_wp_error( $skus ) ) {
			// Skip this page, log error, advance
			PZV_Mig_State::link_job_update( [
				'errors'           => [ "[{$vendor['store_name']}] SKU sayfa {$sku_page}: " . $skus->get_error_message() ],
				'vendor_index'     => $idx + 1,
				'vendor_sku_page'  => 1,
			] );
			wp_send_json_success( self::link_job_response( PZV_Mig_State::link_job() ) );
			return;
		}

		$result = PZV_Mig_Vendor_Importer::link_products( $dest_id, $skus );
		$is_last_sku_page = count( $skus ) < $per_page;

		PZV_Mig_State::link_job_update( [
			'linked'          => ( $job['linked'] ?? 0 ) + $result['linked'],
			'missing'         => ( $job['missing'] ?? 0 ) + $result['missing'],
			'vendor_index'    => $is_last_sku_page ? $idx + 1 : $idx,
			'vendor_sku_page' => $is_last_sku_page ? 1 : $sku_page + 1,
			'status'          => ( $is_last_sku_page && $idx + 1 >= count( $vendor_list ) ) ? 'completed' : 'running',
		] );

		wp_send_json_success( self::link_job_response( PZV_Mig_State::link_job() ) );
	}

	private static function link_job_response( array $job ): array {
		$total = $job['vendor_total'] ?? 0;
		$idx   = $job['vendor_index'] ?? 0;
		return [
			'status'       => $job['status']  ?? 'idle',
			'vendor_index' => $idx,
			'vendor_total' => $total,
			'percent'      => $total > 0 ? round( $idx / $total * 100 ) : 0,
			'linked'       => $job['linked']  ?? 0,
			'missing'      => $job['missing'] ?? 0,
			'errors'       => $job['errors']  ?? [],
		];
	}

	// ---- Commission import ----

	public static function ajax_pzv_mig_start_comm_import(): void {
		self::check();
		$client = PZV_Mig_State::make_client();
		if ( ! $client ) wp_send_json_error( [ 'message' => 'Ayarlar eksik.' ] );

		$total = $client->get_commission_count();
		PZV_Mig_State::comm_job_start( $total );
		wp_send_json_success( [ 'total' => $total ] );
	}

	public static function ajax_pzv_mig_comm_batch(): void {
		self::check();
		@set_time_limit( 120 );

		$job = PZV_Mig_State::comm_job();
		if ( ( $job['status'] ?? '' ) !== 'running' ) {
			wp_send_json_success( self::comm_job_response( $job ) );
			return;
		}

		$client = PZV_Mig_State::make_client();
		if ( ! $client ) { PZV_Mig_State::comm_job_update( [ 'status' => 'failed' ] ); wp_send_json_error(); }

		$page     = (int) ( $job['current_page'] ?? 0 ) + 1;
		$per_page = 200;

		$records = $client->get_commissions( $page, $per_page );
		if ( is_wp_error( $records ) ) {
			PZV_Mig_State::comm_job_update( [ 'errors' => [ "Sayfa {$page}: " . $records->get_error_message() ] ] );
			wp_send_json_success( self::comm_job_response( PZV_Mig_State::comm_job() ) );
			return;
		}

		if ( empty( $records ) ) {
			PZV_Mig_State::comm_job_done();
			wp_send_json_success( self::comm_job_response( PZV_Mig_State::comm_job() ) );
			return;
		}

		$imported = 0;
		$errors   = [];
		foreach ( $records as $rec ) {
			$ok = PZV_Mig_Vendor_Importer::import_commission( (array) $rec );
			if ( $ok ) $imported++; else $errors[] = "Kayıt #{$rec->id} aktarılamadı.";
		}

		$is_last = count( $records ) < $per_page;

		PZV_Mig_State::comm_job_update( [
			'current_page' => $page,
			'processed'    => min( ( $job['processed'] ?? 0 ) + count( $records ), $job['total'] ),
			'imported'     => ( $job['imported'] ?? 0 ) + $imported,
			'errors'       => $errors,
			'status'       => $is_last ? 'completed' : 'running',
		] );

		wp_send_json_success( self::comm_job_response( PZV_Mig_State::comm_job() ) );
	}

	private static function comm_job_response( array $job ): array {
		$total     = $job['total']     ?? 0;
		$processed = $job['processed'] ?? 0;
		return [
			'status'    => $job['status']   ?? 'idle',
			'total'     => $total,
			'processed' => $processed,
			'percent'   => $total > 0 ? round( $processed / $total * 100 ) : 0,
			'imported'  => $job['imported'] ?? 0,
			'errors'    => $job['errors']   ?? [],
		];
	}

	// ---- Reset ----

	public static function ajax_pzv_mig_reset(): void {
		self::check();
		PZV_Mig_State::member_job_reset();
		PZV_Mig_State::vendor_job_reset();
		PZV_Mig_State::link_job_reset();
		PZV_Mig_State::comm_job_reset();
		wp_send_json_success();
	}
}
