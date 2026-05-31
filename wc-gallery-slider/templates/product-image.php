<?php
/**
 * WC Gallery Slider — Single Product Image Template
 * Overrides: woocommerce/single-product/product-image.php
 */
defined( 'ABSPATH' ) || exit;

global $product;

$post_thumbnail_id = $product->get_image_id();
$gallery_ids       = $product->get_gallery_image_ids();

// Ana resim + galeri resimleri birleştir
$all_ids = [];
if ( $post_thumbnail_id ) $all_ids[] = (int) $post_thumbnail_id;
foreach ( $gallery_ids as $gid ) $all_ids[] = (int) $gid;

if ( empty( $all_ids ) ) {
	echo wc_placeholder_img( 'woocommerce_single' );
	return;
}

// Her resim için URL'leri hazırla
$images = [];
foreach ( $all_ids as $id ) {
	$thumb  = wp_get_attachment_image_src( $id, 'woocommerce_thumbnail' );
	$medium = wp_get_attachment_image_src( $id, 'woocommerce_single' );
	$full   = wp_get_attachment_url( $id );
	$alt    = get_post_meta( $id, '_wp_attachment_image_alt', true );

	$images[] = [
		'thumb'  => $thumb  ? $thumb[0]  : $full,
		'medium' => $medium ? $medium[0] : $full,
		'full'   => $full,
		'alt'    => $alt ?: get_the_title( $product->get_id() ),
	];
}

$count    = count( $images );
$has_many = $count > 1;
$uid      = 'wcg-' . $product->get_id();
?>

<div class="wc-gallery-wrap" id="<?php echo esc_attr( $uid ); ?>">

	<!-- Ana büyük slider -->
	<div class="swiper wc-gallery-main" role="region" aria-label="<?php esc_attr_e( 'Ürün görselleri', 'wc-gallery-slider' ); ?>">
		<div class="swiper-wrapper">
			<?php foreach ( $images as $img ) : ?>
			<div class="swiper-slide">
				<img src="<?php echo esc_url( $img['medium'] ); ?>"
				     alt="<?php echo esc_attr( $img['alt'] ); ?>"
				     loading="lazy">
			</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $has_many ) : ?>
		<button class="swiper-button-prev" aria-label="<?php esc_attr_e( 'Önceki', 'wc-gallery-slider' ); ?>"></button>
		<button class="swiper-button-next" aria-label="<?php esc_attr_e( 'Sonraki', 'wc-gallery-slider' ); ?>"></button>
		<div class="wc-gallery-counter" aria-live="polite">1 / <?php echo esc_html( $count ); ?></div>
		<?php endif; ?>

		<!-- Otomatik kayma ilerleme çubuğu -->
		<div class="wc-gallery-progress-bar" aria-hidden="true">
			<div class="wc-gallery-progress-inner"></div>
		</div>

		<!-- Zoom ikonu -->
		<div class="wc-gallery-zoom-hint" aria-hidden="true">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
				<line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>
			</svg>
		</div>
	</div>

	<?php if ( $has_many ) : ?>
	<!-- Thumbnail şeridi -->
	<div class="swiper wc-gallery-thumbs" aria-hidden="true">
		<div class="swiper-wrapper">
			<?php foreach ( $images as $img ) : ?>
			<div class="swiper-slide">
				<img src="<?php echo esc_url( $img['thumb'] ); ?>"
				     alt="<?php echo esc_attr( $img['alt'] ); ?>"
				     loading="lazy">
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<!-- Resim verileri (JS için) -->
	<script>
	window.wcGalleryData = window.wcGalleryData || {};
	window.wcGalleryData[<?php echo wp_json_encode( $uid ); ?>] = <?php echo wp_json_encode( $images ); ?>;
	</script>
</div>
