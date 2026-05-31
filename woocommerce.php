<?php
/**
 * WooCommerce ana sarmalayıcı (shop, kategori arşivleri vb.)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<div class="cw">
  <?php woocommerce_content(); ?>
</div>
<?php
get_footer();
