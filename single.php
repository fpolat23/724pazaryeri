<?php get_header(); ?>
<div class="container" style="padding:24px 0;">
  <?php while (have_posts()): the_post(); ?>
  <article <?php post_class('bg-white rounded p-6 shadow'); ?> style="background:#fff;border-radius:8px;padding:32px;box-shadow:0 2px 8px rgba(0,0,0,.1);">
    <h1 style="font-size:28px;font-weight:700;margin-bottom:12px;"><?php the_title(); ?></h1>
    <div style="color:#999;font-size:13px;margin-bottom:20px;"><?php echo get_the_date(); ?> · <?php the_author(); ?></div>
    <?php if (has_post_thumbnail()): ?>
    <div style="margin-bottom:20px;border-radius:8px;overflow:hidden;"><?php the_post_thumbnail('large', ['style'=>'width:100%;height:auto;']); ?></div>
    <?php endif; ?>
    <div class="entry-content" style="line-height:1.8;font-size:15px;"><?php the_content(); ?></div>
  </article>
  <?php endwhile; ?>
</div>
<?php get_footer(); ?>
