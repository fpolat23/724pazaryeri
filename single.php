<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header(); ?>
<div class="cw">
  <?php while ( have_posts() ) : the_post(); ?>
    <article <?php post_class(); ?> style="background:#fff;border:1.5px solid var(--border);border-radius:14px;padding:30px;">
      <h1 style="font-family:'Archivo',sans-serif;font-size:26px;margin-bottom:14px;"><?php the_title(); ?></h1>
      <div class="entry-content"><?php the_content(); ?></div>
    </article>
  <?php endwhile; ?>
</div>
<?php get_footer();
