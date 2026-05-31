<?php
/**
 * Ana index şablonu (fallback) — 724PazarYeri
 */
if ( ! defined( 'ABSPATH' ) ) exit;
get_header();
?>
<div class="content">
  <?php if ( have_posts() ) : ?>
    <div class="sec">
      <?php while ( have_posts() ) : the_post(); ?>
        <article <?php post_class(); ?> style="background:#fff;border:1.5px solid var(--border);border-radius:12px;padding:24px;margin-bottom:16px;">
          <h2 style="font-family:'Archivo',sans-serif;margin-bottom:10px;"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
          <div class="entry-content"><?php the_excerpt(); ?></div>
        </article>
      <?php endwhile; ?>
      <?php the_posts_pagination(); ?>
    </div>
  <?php else : ?>
    <div class="sec"><p style="text-align:center;padding:40px;color:var(--muted)">İçerik bulunamadı.</p></div>
  <?php endif; ?>
</div>
<?php
get_footer();
