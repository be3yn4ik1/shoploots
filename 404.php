<?php
defined('ABSPATH') || exit;
get_header();
?>
<div style="max-width:560px;margin:80px auto;padding:0 20px;text-align:center">
    <div class="card" style="padding:56px 40px">
        <div style="font-size:4rem;margin-bottom:8px">🔍</div>
        <h1 style="font-size:3rem;font-weight:700;color:var(--primary);margin-bottom:8px">404</h1>
        <p style="font-size:1.1rem;font-weight:600;margin-bottom:8px">Страница не найдена</p>
        <p style="color:var(--text-secondary);margin-bottom:28px;font-size:.9rem">
            Страница была удалена, перемещена или такой адрес не существует.
        </p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="btn-primary">На главную</a>
            <a href="<?php echo esc_url(home_url('/catalog/')); ?>" class="btn-secondary">Каталог товаров</a>
        </div>
    </div>
</div>
<?php get_footer(); ?>
