<?php get_header(); ?>
<div class="container" style="padding:24px 0;">
  <?php while (have_posts()): the_post(); ?>
  <div style="background:#fff;border-radius:8px;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,.1);">
    <h1 style="font-size:28px;font-weight:700;margin-bottom:20px;"><?php the_title(); ?></h1>
    <div style="line-height:1.8;font-size:15px;"><?php the_content(); ?></div>
  </div>
  <?php endwhile; ?>
</div>
<?php get_footer(); ?>
