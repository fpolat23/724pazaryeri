<?php
if ( ! defined( 'ABSPATH' ) ) exit;
get_header(); ?>
<div class="cw">
  <div class="sec">
    <div class="sec-head"><div class="set"><?php the_archive_title(); ?></div></div>
    <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
      <article <?php post_class(); ?> style="background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:20px;margin-bottom:14px;">
        <h2 style="font-family:'Archivo',sans-serif;"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
        <div class="entry-content"><?php the_excerpt(); ?></div>
      </article>
    <?php endwhile; the_posts_pagination(); else : ?>
      <p style="text-align:center;padding:40px;color:var(--muted)">İçerik bulunamadı.</p>
    <?php endif; ?>
  </div>
</div>
<?php get_footer();
