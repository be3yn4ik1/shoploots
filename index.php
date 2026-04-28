<?php
defined('ABSPATH') || exit;
get_header();
?>
<div style="max-width:1200px;margin:0 auto;padding:24px 20px">
    <?php if (have_posts()): while (have_posts()): the_post(); ?>
        <div class="card"><h1><?php the_title(); ?></h1><div><?php the_content(); ?></div></div>
    <?php endwhile; else: ?>
        <div class="card" style="text-align:center;padding:60px">
            <h2>Добро пожаловать в маркетплейс</h2>
            <p style="color:var(--text-secondary);margin-top:12px">Здесь вы найдёте цифровые товары и аккаунты</p>
            <a href="<?php echo esc_url(home_url('/catalog/')); ?>" class="btn-primary" style="margin-top:20px;display:inline-flex">Перейти в каталог</a>
        </div>
    <?php endif; ?>
</div>
<?php get_footer(); ?>
