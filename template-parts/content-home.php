<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ─── Gerçek istatistikler (slider için, 10 dk cache) ─── */
$pz_stats = get_transient( 'pazaryeri_home_stats' );
if ( false === $pz_stats ) {
    // Yayınlanmış ürün (ilan) sayısı
    $prod_count = 0;
    $counts = wp_count_posts( 'product' );
    if ( isset( $counts->publish ) ) $prod_count = (int) $counts->publish;

    // Satıcı (Dokan vendor) sayısı
    $seller_count = 0;
    if ( function_exists( 'dokan_get_sellers' ) ) {
        $sellers = dokan_get_sellers( array( 'number' => -1, 'count' => true ) );
        if ( is_array( $sellers ) && isset( $sellers['count'] ) ) $seller_count = (int) $sellers['count'];
    }
    if ( ! $seller_count ) {
        $seller_users = get_users( array( 'role' => 'seller', 'fields' => 'ID', 'number' => 99999 ) );
        $seller_count = is_array( $seller_users ) ? count( $seller_users ) : 0;
    }

    // Kullanıcı (toplam üye) sayısı
    $user_count = 0;
    $uc = count_users();
    if ( isset( $uc['total_users'] ) ) $user_count = (int) $uc['total_users'];

    // Ortalama ürün puanı
    global $wpdb;
    $avg_rating = $wpdb->get_var( "SELECT AVG(meta_value) FROM {$wpdb->postmeta} WHERE meta_key='_wc_average_rating' AND meta_value>0" );
    $avg_rating = $avg_rating ? round( (float) $avg_rating, 1 ) : 0;

    $pz_stats = array(
        'products' => $prod_count,
        'sellers'  => $seller_count,
        'users'    => $user_count,
        'rating'   => $avg_rating,
    );
    set_transient( 'pazaryeri_home_stats', $pz_stats, 10 * MINUTE_IN_SECONDS );
}

// Sayıları okunabilir formatla (1234 → 1.2K, 1500000 → 1.5M)
if ( ! function_exists( 'pazaryeri_fmt_num' ) ) {
    function pazaryeri_fmt_num( $n ) {
        $n = (int) $n;
        if ( $n >= 1000000 ) return rtrim( rtrim( number_format( $n / 1000000, 1 ), '0' ), '.' ) . 'M+';
        if ( $n >= 1000 )    return rtrim( rtrim( number_format( $n / 1000, 1 ), '0' ), '.' ) . 'K+';
        return (string) $n;
    }
}
$pz_n_products = pazaryeri_fmt_num( $pz_stats['products'] );
$pz_n_sellers  = pazaryeri_fmt_num( $pz_stats['sellers'] );
$pz_n_users    = pazaryeri_fmt_num( $pz_stats['users'] );
$pz_n_rating   = $pz_stats['rating'] > 0 ? number_format( $pz_stats['rating'], 1 ) . '★' : 'Yeni';
$pz_n_active   = number_format( $pz_stats['products'], 0, ',', '.' );
?>
<div class="content">

  <!-- HERO ROW: side categories + slider -->
  <div class="hero-row">

    <!-- LEFT VERTICAL CATEGORY MENU -->
    <aside class="vcat" id="vcat" onmouseleave="vcatHide()">
      <?php
        $vcat_tints = array('#fff0e6','#f3e8ff','#e0f2fe','#fef3c7','#dcfce7','#fce7f3','#e0e7ff','#fee2e2','#f0fdfa','#faf5ff');
        $vcat_cols  = array('#ff6a00','#9333ea','#0284c7','#d97706','#16a34a','#db2777','#4f46e5','#dc2626','#0d9488','#a855f7');
        $vcat_emojis = array('elektronik'=>'📱','moda'=>'👗','ev-dekor'=>'🏠','el-yapimi'=>'🎨','kitap-ve-hobi'=>'📚','spor'=>'⚽','kozmetik'=>'💄','otomotiv'=>'🚗','anne-ve-bebek'=>'🍼','muzik'=>'🎵');
        $vcats = get_terms( array(
          'taxonomy'   => 'product_cat',
          'parent'     => 0,
          'hide_empty' => true,
          'number'     => 10,
          'orderby'    => 'name',
          'exclude'    => array( get_option('default_product_cat') ),
        ) );
        if ( ! empty( $vcats ) && ! is_wp_error( $vcats ) ) :
          foreach ( $vcats as $vi => $vc ) :
            $tint = $vcat_tints[ $vi % count($vcat_tints) ];
            $tcol = $vcat_cols[ $vi % count($vcat_cols) ];
            $vc_thumb_id = get_term_meta( $vc->term_id, 'thumbnail_id', true );
            $vc_img = $vc_thumb_id ? wp_get_attachment_image_url( $vc_thumb_id, 'thumbnail' ) : '';
            $vc_emoji = isset( $vcat_emojis[ $vc->slug ] ) ? $vcat_emojis[ $vc->slug ] : '📦';
            // Alt kategoriler
            $vcs_key = 'pz_vcs_' . $vc->term_id;
            $vc_subs = get_transient( $vcs_key );
            if ( $vc_subs === false ) {
              $vc_subs = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>$vc->term_id, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC' ) );
              if ( ! is_wp_error( $vc_subs ) ) set_transient( $vcs_key, $vc_subs, 30 * MINUTE_IN_SECONDS );
            }
            $has_subs = ( ! empty( $vc_subs ) && ! is_wp_error( $vc_subs ) );
      ?>
        <div class="vcat-wrap" onmouseenter="vcatShow('vcfly-<?php echo esc_attr($vc->term_id); ?>')">
          <a class="vcat-item" href="<?php echo esc_url( get_term_link($vc) ); ?>" style="--tint:<?php echo esc_attr($tint); ?>;--tcol:<?php echo esc_attr($tcol); ?>;">
            <span class="vcat-ico"><?php if ( $vc_img ) : ?><img loading="lazy" decoding="async" src="<?php echo esc_url($vc_img); ?>" alt="<?php echo esc_attr($vc->name); ?>"><?php else : ?><span class="vcat-emoji"><?php echo $vc_emoji; ?></span><?php endif; ?></span>
            <span class="vcat-label"><?php echo esc_html($vc->name); ?></span>
            <?php if ( $has_subs ) : ?><span class="vcat-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></span><?php endif; ?>
          </a>
          <?php if ( $has_subs ) : ?>
          <div class="vcat-fly" id="vcfly-<?php echo esc_attr($vc->term_id); ?>">
            <div class="vcat-fly-head"><?php echo esc_html($vc->name); ?></div>
            <div class="vcat-fly-grid">
              <?php foreach ( $vc_subs as $sub ) :
                $sk_key = 'pz_sk_' . $sub->term_id;
                $sub_kids = get_transient( $sk_key );
                if ( $sub_kids === false ) {
                  $sub_kids = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>$sub->term_id, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC' ) );
                  if ( ! is_wp_error( $sub_kids ) ) set_transient( $sk_key, $sub_kids, 30 * MINUTE_IN_SECONDS );
                } ?>
                <div class="vcat-fly-col">
                  <a class="vcat-fly-title" href="<?php echo esc_url( get_term_link($sub) ); ?>"><?php echo esc_html($sub->name); ?></a>
                  <?php if ( ! empty($sub_kids) && ! is_wp_error($sub_kids) ) : ?>
                    <?php foreach ( $sub_kids as $kid ) : ?>
                      <a class="vcat-fly-link" href="<?php echo esc_url( get_term_link($kid) ); ?>"><?php echo esc_html($kid->name); ?></a>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; endif; ?>
    </aside>

    <!-- SLIDER -->
<!-- HERO SLIDER -->
  <div class="slider" id="slider">
    <div class="slides" id="slides">

      <!-- SLIDE 1 -->
      <div class="slide slide-dark">
        <div class="slide-orb orb-a"></div>
        <div class="slide-orb orb-b"></div>
        <div class="slide-inner">
          <div class="slide-text">
            <a class="slide-badge" href="<?php echo esc_url( function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/') ); ?>" style="text-decoration:none;"><span class="pulse"></span> Canlı · <?php echo esc_html( $pz_n_active ); ?> aktif ilan</a>
            <h1 class="slide-h1">Herkes Satabilir,<br><em>Herkes Kazanabilir</em></h1>
            <p class="slide-p">Türkiye'nin en güvenilir kişiden kişiye pazarında mağazanı aç, milyonlara ulaş. Komisyon ilk 3 ay sıfır!</p>
            <div class="slide-btns">
              <a class="sbtn solid" href="<?php echo esc_url( function_exists('pazaryeri_become_seller_url') ? pazaryeri_become_seller_url() : home_url('/magaza-ac/') ); ?>">Satmaya Başla →</a>
              <a class="sbtn ghost" href="/shop/">Keşfet</a>
            </div>
            <div class="slide-stats">
              <div class="sstat"><span class="sstat-n"><?php echo esc_html( $pz_n_users ); ?></span><span class="sstat-l">Kullanıcı</span></div>
              <a class="sstat" href="<?php echo esc_url( function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/') ); ?>"><span class="sstat-n"><?php echo esc_html( $pz_n_products ); ?></span><span class="sstat-l">İlan</span></a>
              <div class="sstat"><span class="sstat-n"><?php echo esc_html( $pz_n_sellers ); ?></span><span class="sstat-l">Satıcı</span></div>
              <div class="sstat"><span class="sstat-n"><?php echo esc_html( $pz_n_rating ); ?></span><span class="sstat-l">Puan</span></div>
            </div>
          </div>
          <div class="slide-art slide-art-wide">
            <svg class="art-svg" viewBox="0 0 320 280" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <linearGradient id="s1roof" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff8c42"/><stop offset="1" stop-color="#ff6a00"/></linearGradient>
                <linearGradient id="s1card" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#f4f2ee"/></linearGradient>
              </defs>
              <!-- back glow -->
              <circle cx="160" cy="130" r="120" fill="rgba(255,255,255,.04)"/>
              <!-- storefront awning -->
              <rect x="60" y="58" width="200" height="22" rx="6" fill="url(#s1roof)"/>
              <path d="M60 80 h200 v10 h-200 z" fill="#fff" opacity=".15"/>
              <g fill="#fff" opacity=".22"><rect x="68" y="80" width="20" height="12"/><rect x="108" y="80" width="20" height="12"/><rect x="148" y="80" width="20" height="12"/><rect x="188" y="80" width="20" height="12"/><rect x="228" y="80" width="20" height="12"/></g>
              <!-- floating product cards -->
              <g class="fc fc1"><rect x="40" y="120" width="78" height="96" rx="10" fill="url(#s1card)"/><circle cx="79" cy="150" r="18" fill="#ffe0b2"/><text x="79" y="157" font-size="18" text-anchor="middle">📷</text><rect x="54" y="178" width="50" height="7" rx="3" fill="#e8e4de"/><rect x="54" y="190" width="34" height="7" rx="3" fill="#ff8c42"/></g>
              <g class="fc fc2"><rect x="124" y="104" width="78" height="104" rx="10" fill="url(#s1card)"/><circle cx="163" cy="138" r="20" fill="#e1bee7"/><text x="163" y="146" font-size="20" text-anchor="middle">🎸</text><rect x="138" y="170" width="50" height="7" rx="3" fill="#e8e4de"/><rect x="138" y="183" width="34" height="7" rx="3" fill="#ff6a00"/></g>
              <g class="fc fc3"><rect x="208" y="120" width="78" height="96" rx="10" fill="url(#s1card)"/><circle cx="247" cy="150" r="18" fill="#b2dfdb"/><text x="247" y="157" font-size="18" text-anchor="middle">🪴</text><rect x="222" y="178" width="50" height="7" rx="3" fill="#e8e4de"/><rect x="222" y="190" width="34" height="7" rx="3" fill="#1a7a4a"/></g>
              <!-- price tags -->
              <g class="tag-pop"><circle cx="280" cy="96" r="20" fill="#ffe08a"/><text x="280" y="101" font-size="11" font-weight="800" text-anchor="middle" fill="#7a5800">%50</text></g>
            </svg>
          </div>
        </div>
      </div>

      <!-- SLIDE 2 — Flash -->
      <div class="slide slide-flash">
        <div class="slide-orb orb-a" style="background:rgba(255,255,255,.18)"></div>
        <div class="slide-orb orb-b" style="background:rgba(255,255,255,.1)"></div>
        <div class="slide-inner">
          <div class="slide-text">
            <div class="slide-badge light">⚡ Sınırlı Süre</div>
            <h1 class="slide-h1">Flash Fırsat<br><em style="color:#fff">%70'e Varan İndirim</em></h1>
            <p class="slide-p" style="color:rgba(255,255,255,.85)">Binlerce üründe sezon sonu indirimi. Stoklar bitmeden kaçırma — her gün yeni fırsatlar!</p>
            <div class="slide-btns">
              <a class="sbtn white" href="/flash-sale/">Fırsatları Gör →</a>
            </div>
            <div class="flash-timer">
              <div class="ft-block"><span id="fh">03</span><small>SAAT</small></div>
              <span class="ft-sep">:</span>
              <div class="ft-block"><span id="fm">47</span><small>DAK</small></div>
              <span class="ft-sep">:</span>
              <div class="ft-block"><span id="fs">22</span><small>SAN</small></div>
            </div>
          </div>
          <div class="slide-art slide-art-wide">
            <svg class="art-svg" viewBox="0 0 320 280" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <linearGradient id="s2bolt" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#ffe08a"/></linearGradient>
                <radialGradient id="s2burst"><stop offset="0" stop-color="rgba(255,255,255,.35)"/><stop offset="1" stop-color="rgba(255,255,255,0)"/></radialGradient>
              </defs>
              <circle cx="160" cy="140" r="120" fill="url(#s2burst)"/>
              <!-- sun rays -->
              <g stroke="#fff" stroke-width="3" stroke-linecap="round" opacity=".35" class="rays">
                <line x1="160" y1="30" x2="160" y2="56"/><line x1="160" y1="224" x2="160" y2="250"/>
                <line x1="50" y1="140" x2="76" y2="140"/><line x1="244" y1="140" x2="270" y2="140"/>
                <line x1="82" y1="62" x2="100" y2="80"/><line x1="220" y1="200" x2="238" y2="218"/>
                <line x1="238" y1="62" x2="220" y2="80"/><line x1="100" y1="200" x2="82" y2="218"/>
              </g>
              <!-- big lightning bolt -->
              <path class="bolt" d="M186 56 L120 152 L156 152 L132 224 L210 120 L168 120 Z" fill="url(#s2bolt)" stroke="#fff" stroke-width="2" stroke-linejoin="round"/>
              <!-- discount badge -->
              <g class="tag-pop"><circle cx="236" cy="92" r="30" fill="#fff"/><text x="236" y="89" font-size="15" font-weight="800" text-anchor="middle" fill="#ff6a00">%70</text><text x="236" y="104" font-size="9" font-weight="700" text-anchor="middle" fill="#ff6a00">İNDİRİM</text></g>
            </svg>
          </div>
        </div>
      </div>

      <!-- SLIDE 3 — El Yapımı -->
      <div class="slide slide-craft">
        <div class="slide-orb orb-a" style="background:rgba(123,31,162,.15)"></div>
        <div class="slide-orb orb-b" style="background:rgba(232,71,10,.12)"></div>
        <div class="slide-inner">
          <div class="slide-text">
            <div class="slide-badge" style="background:rgba(123,31,162,.15);border-color:rgba(123,31,162,.35);color:#c77dff">🎨 Tasarımcı Köşesi</div>
            <h1 class="slide-h1">El Emeği<br><em style="color:#ffb000">Benzersiz Eserler</em></h1>
            <p class="slide-p" style="color:rgba(255,255,255,.78)">Yerel sanatçılardan ve zanaatkârlardan tek parça ürünler. Her biri bir hikâye anlatıyor.</p>
            <div class="slide-btns">
              <a class="sbtn solid" href="/product-category/el-yapimi/">Koleksiyonu Keşfet →</a>
              <a class="sbtn ghost" href="/shop/">Tüm Sanatçılar</a>
            </div>
            <div class="artisan-row">
              <div class="artisan-avatars">
                <span class="aav" style="background:#ffb000">🧶</span>
                <span class="aav" style="background:#c77dff">🎨</span>
                <span class="aav" style="background:#5fd99a">🏺</span>
                <span class="aav" style="background:#ff8c42">💍</span>
              </div>
              <span class="artisan-text">12.000+ bağımsız sanatçı &amp; zanaatkâr</span>
            </div>
          </div>
          <div class="slide-art slide-art-wide">
            <svg class="art-svg" viewBox="0 0 320 280" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <linearGradient id="s3pot" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffb000"/><stop offset="1" stop-color="#c77dff"/></linearGradient>
              </defs>
              <circle cx="160" cy="140" r="118" fill="rgba(255,255,255,.05)"/>
              <!-- artist palette -->
              <g class="float-slow">
                <ellipse cx="120" cy="150" rx="76" ry="58" fill="#fff" opacity=".95"/>
                <ellipse cx="148" cy="158" rx="20" ry="15" fill="#241a2e"/>
                <circle cx="92" cy="120" r="11" fill="#ff6a00"/>
                <circle cx="128" cy="110" r="11" fill="#ffb000"/>
                <circle cx="160" cy="120" r="11" fill="#c77dff"/>
                <circle cx="88" cy="158" r="11" fill="#1a7a4a"/>
                <circle cx="110" cy="180" r="11" fill="#1a4fa0"/>
              </g>
              <!-- paintbrush -->
              <g class="brush"><rect x="196" y="70" width="10" height="90" rx="5" fill="#8a5a2b" transform="rotate(34 201 115)"/><path d="M228 168 l16 10 -6 18 -18 -8 z" fill="#c77dff" transform="rotate(34 230 180)"/></g>
              <!-- pottery vase -->
              <g class="float-slow2"><path d="M214 150 q-18 6 -18 30 q0 28 22 30 q22 -2 22 -30 q0 -24 -18 -30 l0 -14 l-8 0 z" fill="url(#s3pot)"/><ellipse cx="218" cy="150" rx="14" ry="5" fill="#fff" opacity=".4"/></g>
              <!-- sparkles -->
              <g fill="#ffe08a" class="rays"><path d="M60 70 l3 8 8 3 -8 3 -3 8 -3 -8 -8 -3 8 -3 z"/><path d="M250 220 l2 6 6 2 -6 2 -2 6 -2 -6 -6 -2 6 -2 z"/></g>
            </svg>
          </div>
        </div>
      </div>

      <!-- SLIDE 4 — Güvenli Ödeme -->
      <div class="slide slide-trust">
        <div class="slide-orb orb-a" style="background:rgba(26,122,74,.18)"></div>
        <div class="slide-orb orb-b" style="background:rgba(26,79,160,.12)"></div>
        <div class="slide-inner">
          <div class="slide-text">
            <div class="slide-badge" style="background:rgba(26,122,74,.18);border-color:rgba(26,122,74,.4);color:#5fd99a">🛡️ %100 Güvende</div>
            <h1 class="slide-h1">Güvenli Alışveriş<br><em style="color:#5fd99a">Param Cebimde Gibi</em></h1>
            <p class="slide-p" style="color:rgba(255,255,255,.8)">Ödemen, ürünü teslim alıp onaylayana kadar güvenli havuzda bekler. 724Pay güvencesiyle.</p>
            <div class="slide-btns">
              <a class="sbtn solid" href="/guvenli-odeme/">Nasıl Çalışır? →</a>
            </div>
            <div class="trust-row">
              <span class="trust-chip">🔒 256-bit SSL</span>
              <span class="trust-chip">🔄 14 Gün İade</span>
              <span class="trust-chip">⚡ Hızlı Kargo</span>
            </div>
          </div>
          <div class="slide-art slide-art-wide">
            <svg class="art-svg" viewBox="0 0 320 280" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <linearGradient id="s4sh" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5fd99a"/><stop offset="1" stop-color="#1a7a4a"/></linearGradient>
              </defs>
              <circle cx="160" cy="140" r="118" fill="rgba(255,255,255,.05)"/>
              <!-- pulsing rings -->
              <circle class="ring r1" cx="160" cy="138" r="92" fill="none" stroke="#5fd99a" stroke-width="2" opacity=".25"/>
              <circle class="ring r2" cx="160" cy="138" r="70" fill="none" stroke="#5fd99a" stroke-width="2" opacity=".4"/>
              <!-- shield -->
              <g class="float-slow">
                <path d="M160 62 l66 24 v44 c0 48 -34 78 -66 92 c-32 -14 -66 -44 -66 -92 v-44 z" fill="url(#s4sh)"/>
                <path d="M160 62 l66 24 v44 c0 48 -34 78 -66 92 z" fill="#000" opacity=".08"/>
                <!-- lock -->
                <rect x="138" y="132" width="44" height="38" rx="7" fill="#fff"/>
                <path d="M146 132 v-10 a14 14 0 0 1 28 0 v10" fill="none" stroke="#fff" stroke-width="6"/>
                <circle cx="160" cy="148" r="6" fill="#1a7a4a"/>
                <rect x="157" y="150" width="6" height="12" rx="3" fill="#1a7a4a"/>
              </g>
              <!-- check coin -->
              <g class="tag-pop"><circle cx="244" cy="92" r="24" fill="#ffe08a"/><path d="M234 92 l7 7 13 -14" fill="none" stroke="#1a7a4a" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></g>
            </svg>
          </div>
        </div>
      </div>

    </div>

    <!-- arrows -->
    <button class="slider-arrow prev" onclick="slidePrev()" aria-label="Önceki"><svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg></button>
    <button class="slider-arrow next" onclick="slideNext()" aria-label="Sonraki"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>

    <!-- dots + progress -->
    <div class="slider-dots" id="sliderDots">
      <button class="sdot on" onclick="slideGo(0)"><i></i></button>
      <button class="sdot" onclick="slideGo(1)"><i></i></button>
      <button class="sdot" onclick="slideGo(2)"><i></i></button>
      <button class="sdot" onclick="slideGo(3)"><i></i></button>
    </div>
  </div>
  </div><!-- /hero-row -->

  <!-- MOBİL KATEGORİ GRID (sadece mobilde görünür, Image 1 tarzı) -->
  <div class="mobile-cat-grid">
    <?php
      $mcg_key = 'pz_mcg_v1';
      $mcg_cats = get_transient( $mcg_key );
      if ( $mcg_cats === false ) {
        $mcg_cats = get_terms( array( 'taxonomy'=>'product_cat', 'parent'=>0, 'hide_empty'=>true, 'orderby'=>'name', 'order'=>'ASC', 'number'=>12 ) );
        if ( ! is_wp_error( $mcg_cats ) ) set_transient( $mcg_key, $mcg_cats, 30 * MINUTE_IN_SECONDS );
      }
      if ( ! empty( $mcg_cats ) && ! is_wp_error( $mcg_cats ) ) :
        foreach ( $mcg_cats as $mcg ) :
          if ( $mcg->slug === 'uncategorized' ) continue;
          $mcg_thumb_id = get_term_meta( $mcg->term_id, 'thumbnail_id', true );
          $mcg_img = $mcg_thumb_id ? wp_get_attachment_image_url( $mcg_thumb_id, 'thumbnail' ) : '';
    ?>
      <a class="mcg-item" href="<?php echo esc_url( get_term_link( $mcg ) ); ?>">
        <div class="mcg-img">
          <?php if ( $mcg_img ) : ?>
            <img loading="lazy" decoding="async" src="<?php echo esc_url( $mcg_img ); ?>" alt="<?php echo esc_attr( $mcg->name ); ?>" loading="lazy">
          <?php else : ?>
            <span class="mcg-emoji">📦</span>
          <?php endif; ?>
        </div>
        <span class="mcg-name"><?php echo esc_html( $mcg->name ); ?></span>
      </a>
    <?php endforeach; endif; ?>
  </div>

  <!-- KAMPANYA BANNER ŞERİDİ -->
  <div class="promo-strip">
    <a class="promo-card promo-1" href="/flash-sale/">
      <div class="promo-orb po1"></div><div class="promo-orb po2"></div>
      <div class="promo-txt">
        <span class="promo-tag">⚡ FLASH İNDİRİM</span>
        <div class="promo-t">Sezon Sonu Fırsatları</div>
        <div class="promo-s">Seçili ürünlerde %70'e varan indirim</div>
        <span class="promo-btn">Hemen Al →</span>
      </div>
      <div class="promo-emoji">🔥</div>
    </a>
    <a class="promo-card promo-2" href="<?php echo esc_url( function_exists('pazaryeri_become_seller_url') ? pazaryeri_become_seller_url() : home_url('/magaza-ac/') ); ?>">
      <div class="promo-orb po1"></div><div class="promo-orb po2"></div>
      <div class="promo-txt">
        <span class="promo-tag">🏪 SATICI OL</span>
        <div class="promo-t">Komisyon İlk 3 Ay Sıfır</div>
        <div class="promo-s">Mağazanı aç, milyonlara ulaş</div>
        <span class="promo-btn">Mağaza Aç →</span>
      </div>
      <div class="promo-emoji">💰</div>
    </a>
    <a class="promo-card promo-3" href="/product-category/el-yapimi/">
      <div class="promo-orb po1"></div><div class="promo-orb po2"></div>
      <div class="promo-txt">
        <span class="promo-tag">🎨 EL YAPIMI</span>
        <div class="promo-t">Benzersiz Tasarımlar</div>
        <div class="promo-s">Yerel sanatçılardan özel ürünler</div>
        <span class="promo-btn">Keşfet →</span>
      </div>
      <div class="promo-emoji">✨</div>
    </a>
  </div>


  <!-- PRODUCTS (TAB SİSTEMİ) -->
  <div class="sec">
    <div class="sec-head">
      <div class="set">Kategorilere Göre <span>Ürünler</span></div>
      <a class="sea" href="/shop/">Tümünü Gör <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></a>
    </div>

    <!-- KATEGORİ TAB BANDI -->
    <div class="cat-tabs" id="catTabs">
      <button class="cat-tab on" data-cat="all" onclick="switchCatTab(this,'all')">
        <span class="ct-ico">🛍️</span>Tümü
      </button>
      <?php
        // Sekmeler: ana kategorilerden dinamik (görsel varsa görsel, yoksa emoji)
        $ct_emojis = array('elektronik'=>'📱','moda'=>'👗','ev-dekor'=>'🏠','el-yapimi'=>'🎨','kitap-ve-hobi'=>'📚','spor'=>'⚽','kozmetik'=>'💄','otomotiv'=>'🚗','anne-ve-bebek'=>'🍼','muzik'=>'🎵');
        $ct_cats = get_terms( array(
          'taxonomy'   => 'product_cat',
          'parent'     => 0,
          'hide_empty' => true,
          'number'     => 8,
          'orderby'    => 'name',
          'exclude'    => array( get_option('default_product_cat') ),
        ) );
        if ( ! empty( $ct_cats ) && ! is_wp_error( $ct_cats ) ) :
          foreach ( $ct_cats as $ctc ) :
            $ct_thumb_id = get_term_meta( $ctc->term_id, 'thumbnail_id', true );
            $ct_img = $ct_thumb_id ? wp_get_attachment_image_url( $ct_thumb_id, 'thumbnail' ) : '';
            $ct_emoji = isset( $ct_emojis[ $ctc->slug ] ) ? $ct_emojis[ $ctc->slug ] : '📦';
      ?>
        <button class="cat-tab" data-cat="<?php echo esc_attr( $ctc->slug ); ?>" onclick="switchCatTab(this,'<?php echo esc_attr( $ctc->slug ); ?>')">
          <span class="ct-ico"><?php if ( $ct_img ) : ?><img loading="lazy" decoding="async" src="<?php echo esc_url($ct_img); ?>" alt="<?php echo esc_attr($ctc->name); ?>"><?php else : ?><?php echo $ct_emoji; ?><?php endif; ?></span><?php echo esc_html( $ctc->name ); ?>
        </button>
      <?php endforeach; endif; ?>
    </div>

    <!-- ÜRÜN GRID (AJAX ile dolar; ilk yük "Tümü") -->
    <div class="pgrid" id="catProductGrid">
      <?php
        // İlk açılışta rastgele ürünler (Tümü)
        $loop = new WP_Query( array(
          'post_type'      => 'product',
          'posts_per_page' => 12,
          'orderby'        => 'rand',
          'post_status'    => 'publish',
        ) );
        if ( $loop->have_posts() ) :
          while ( $loop->have_posts() ) : $loop->the_post();
            global $product;
            echo bazario_product_card( $product );
          endwhile;
          wp_reset_postdata();
        else :
          echo '<div style="grid-column:1/-1;text-align:center;padding:40px 20px;color:var(--muted)"><div style="font-size:40px;margin-bottom:10px;opacity:.5">📦</div><div style="font-size:15px;font-weight:600;margin-bottom:4px;color:var(--ink)">Bu kategoride henüz ürün yok</div><div style="font-size:13px">Çok yakında yeni ürünler eklenecek!</div></div>';
        endif;
      ?>
    </div>
    <div class="cat-loading" id="catLoading" style="display:none;">Yükleniyor...</div>
  </div>PRODUCTS -->

  <!-- DEALS -->
  <div class="dbanner">
    <div>
      <div class="db-h">Günün <em>Flash Fırsatları</em></div>
      <div class="db-p">Sınırlı stok — bitene kadar bu fiyatlar geçerli</div>
    </div>
    <div class="dbtimer">
      <div class="dbt"><div class="dbt-n" id="dh">03</div><div class="dbt-l">SAAT</div></div>
      <div class="dbt-sep">:</div>
      <div class="dbt"><div class="dbt-n" id="dm">47</div><div class="dbt-l">DAK</div></div>
      <div class="dbt-sep">:</div>
      <div class="dbt"><div class="dbt-n" id="ds">22</div><div class="dbt-l">SAN</div></div>
    </div>
  </div>

  <!-- ═══ KAMPANYA BANNER IZGARASI (Hepsiburada tarzı) ═══ -->
  <div class="hb-promo-grid">
    <a class="hb-promo-card hb-promo-lg" href="<?php echo esc_url( wc_get_page_permalink('shop') ); ?>" style="background:linear-gradient(135deg,#ff6a00,#ff9248);">
      <div class="hb-promo-txt">
        <span class="hb-promo-tag">SÜPER FİYAT</span>
        <div class="hb-promo-title">Teknolojide<br>Dev İndirimler</div>
        <div class="hb-promo-sub">Binlerce üründe %70'e varan fırsatlar</div>
        <span class="hb-promo-btn">Keşfet →</span>
      </div>
      <div class="hb-promo-emoji">🔥</div>
    </a>
    <a class="hb-promo-card" href="<?php echo esc_url( home_url('/nasil-satilir/') ); ?>" style="background:linear-gradient(135deg,#5a2d82,#8e44ad);">
      <div class="hb-promo-txt">
        <span class="hb-promo-tag">SATICI OL</span>
        <div class="hb-promo-title">Mağazanı Aç</div>
        <div class="hb-promo-sub">İlk 3 ay komisyon sıfır</div>
      </div>
      <div class="hb-promo-emoji">🏪</div>
    </a>
    <a class="hb-promo-card" href="<?php echo esc_url( home_url('/product-category/el-yapimi/') ); ?>" style="background:linear-gradient(135deg,#c0392b,#e84393);">
      <div class="hb-promo-txt">
        <span class="hb-promo-tag">EL YAPIMI</span>
        <div class="hb-promo-title">Özel Tasarımlar</div>
        <div class="hb-promo-sub">Yerel sanatçılardan</div>
      </div>
      <div class="hb-promo-emoji">✨</div>
    </a>
  </div>

  <!-- ═══ KATEGORİ VİTRİNLERİ (her ana kategori için dinamik) ═══ -->
  <?php
    // Tüm ana kategorileri çek (Diğer ve Kategori Yok hariç)
    $sct_key = 'pz_sct_v1';
    $showcase_terms = get_transient( $sct_key );
    if ( $showcase_terms === false ) $showcase_terms = get_terms( array(
      'taxonomy'   => 'product_cat',
      'parent'     => 0,
      'hide_empty' => true,
      'orderby'    => 'name',
      'exclude'    => array( get_option('default_product_cat') ),
    ) );
    if ( ! is_wp_error( $showcase_terms ) ) set_transient( $sct_key, $showcase_terms, 30 * MINUTE_IN_SECONDS );
    // İsmi "Diğer" / "Kategori Yok" olanları çıkar
    $skip_names = array( 'diğer', 'diger', 'kategori yok', 'uncategorized', 'genel', 'hediyelik eşya', 'hediyelik esya' );
    if ( ! empty( $showcase_terms ) && ! is_wp_error( $showcase_terms ) ) :
      foreach ( $showcase_terms as $sc_term ) :
        if ( in_array( mb_strtolower( $sc_term->name ), $skip_names, true ) ) continue;
        $sc_q = new WP_Query( array(
          'post_type'      => 'product',
          'posts_per_page' => 6,
          'post_status'    => 'publish',
          'orderby'        => 'rand',
          'tax_query'      => array( array(
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $sc_term->term_id,
          ) ),
        ) );
        if ( $sc_q->have_posts() ) :
          $sc_link = get_term_link( $sc_term );
          if ( is_wp_error( $sc_link ) ) $sc_link = wc_get_page_permalink('shop');
  ?>
    <div class="hb-showcase">
      <div class="hb-showcase-head">
        <div class="hb-showcase-title"><?php echo esc_html( $sc_term->name ); ?></div>
        <a class="hb-showcase-all" href="<?php echo esc_url( $sc_link ); ?>">Tümünü Gör ›</a>
      </div>
      <div class="hb-showcase-row">
        <?php while ( $sc_q->have_posts() ) : $sc_q->the_post(); global $product; echo bazario_product_card( $product ); endwhile; ?>
      </div>
    </div>
  <?php
        endif;
        wp_reset_postdata();
      endforeach;
    endif;
  ?>

  <!-- SELLERS -->
  <div class="sec">
    <div class="sec-head">
      <div class="sec-title">En Çok Tercih Edilen <span>Satıcılar</span></div>
      <a class="sec-all" href="<?php echo esc_url( function_exists('dokan_get_page_url') ? dokan_get_page_url('store_listing') : home_url('/store-listing/') ); ?>" style="text-decoration:none">Tümünü Gör <svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></a>
    </div>
    <div class="sgrid">
      <?php
        // Dokan satıcılarını güvenli şekilde dinamik çek
        $sellers = array();
        if ( function_exists( 'dokan_get_sellers' ) ) {
          $result = dokan_get_sellers( array( 'number' => 4, 'orderby' => 'registered', 'order' => 'DESC' ) );
          if ( ! empty( $result['users'] ) ) {
            $sellers = $result['users'];
          }
        }
        if ( ! empty( $sellers ) && function_exists( 'dokan' ) ) :
          $avatars = array('🏪','🛍️','📦','🎁','🧶','🎧','💎','👜');
          $sidx = 0;
          foreach ( $sellers as $seller ) :
            $vendor_id = $seller->ID;
            $vendor    = dokan()->vendor->get( $vendor_id );
            if ( ! $vendor ) { continue; }
            $shop_name = $vendor->get_shop_name() ? $vendor->get_shop_name() : $seller->display_name;
            $shop_url  = $vendor->get_shop_url();
            $rating    = method_exists( $vendor, 'get_rating' ) ? $vendor->get_rating() : array();
            $rating_val = ( is_array($rating) && isset($rating['rating']) && $rating['rating'] > 0 ) ? (float)$rating['rating'] : 5.0;
            $stars     = str_repeat('★', round($rating_val)) . str_repeat('☆', 5 - round($rating_val));
            $product_count = 0;
            if ( function_exists( 'dokan_get_seller_products' ) ) {
              $sp_list = dokan_get_seller_products( $vendor_id, array( 'post_status' => 'publish' ) );
              if ( is_array( $sp_list ) ) { $product_count = count( $sp_list ); }
              elseif ( is_object( $sp_list ) && isset( $sp_list->posts ) ) { $product_count = count( $sp_list->posts ); }
            }
            $cat = get_user_meta( $vendor_id, 'dokan_store_category', true );
            $avatar = isset($avatars[$sidx]) ? $avatars[$sidx] : '🏪';
            $shop_logo = method_exists( $vendor, 'get_avatar' ) ? $vendor->get_avatar() : '';
            $sidx++;
      ?>
        <div class="scard" data-href="<?php echo esc_url( $shop_url ); ?>" onclick="window.location.href=this.dataset.href" style="cursor:pointer">
          <div class="sc-av" style="background:#fff5f2;overflow:hidden;">
            <?php if ( $shop_logo ) : ?>
              <img loading="lazy" decoding="async" src="<?php echo esc_url( $shop_logo ); ?>" alt="<?php echo esc_attr( $shop_name ); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
            <?php else : ?>
              <?php echo $avatar; ?>
            <?php endif; ?>
          </div>
          <div class="sc-name"><?php echo esc_html( $shop_name ); ?></div>
          <div class="sc-cat"><?php echo esc_html( $cat ? $cat : 'Mağaza' ); ?></div>
          <div class="sc-rt"><span class="sc-stars"><?php echo $stars; ?></span><span class="sc-rn"><?php echo esc_html( number_format($rating_val,1) ); ?></span></div>
          <div class="sc-stats">
            <div><div class="sc-stat-n"><?php echo esc_html( $product_count ); ?></div><div class="sc-stat-l">İlan</div></div>
            <div><div class="sc-stat-n">★</div><div class="sc-stat-l">Satıcı</div></div>
          </div>
          <a class="sc-follow" href="<?php echo esc_url( $shop_url ); ?>" onclick="event.stopPropagation();">Mağazayı Gör</a>
        </div>
      <?php endforeach; else : ?>
        <div style="grid-column:1/-1;text-align:center;padding:40px 20px;color:var(--muted)">
          <div style="font-size:40px;margin-bottom:10px;opacity:.5">🏪</div>
          <div style="font-size:15px;font-weight:600;margin-bottom:4px;color:var(--ink)">Henüz satıcı yok</div>
          <div style="font-size:13px">Dokan eklentisi kurulu değilse veya henüz satıcı kaydı yoksa burada satıcılar görünür.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- TRUST -->
  <div class="tgrid">
    <div class="tcard"><div class="tico">🛡️</div><div><div class="tlabel">Güvenli Ödeme</div><div class="tsub">Teslimata kadar koruma</div></div></div>
    <div class="tcard"><div class="tico">🔄</div><div><div class="tlabel">Kolay İade</div><div class="tsub">14 gün iade garantisi</div></div></div>
    <div class="tcard"><div class="tico">⚡</div><div><div class="tlabel">Hızlı Kargo</div><div class="tsub">1-3 iş günü teslimat</div></div></div>
    <div class="tcard"><div class="tico">💬</div><div><div class="tlabel">7/24 Destek</div><div class="tsub">Her zaman yanınızda</div></div></div>
  </div>

</div>

<!-- FOOTER -->

<!-- ═══ SON GEZİLEN ÜRÜNLER (anasayfa altı) ═══ -->
<?php if ( function_exists( 'pz_recently_viewed_block' ) ) : ?>
  <section style="max-width:1320px;margin:32px auto 0;padding:0 20px;">
    <?php echo pz_recently_viewed_block( '🕐 Son Gezdiğin Ürünler', false ); ?>
  </section>
<?php endif; ?>
