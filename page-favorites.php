<?php
/*
Template Name: Favorites
*/
defined('ABSPATH') || exit;
if (!is_user_logged_in()) { wp_redirect(home_url('/auth/')); exit; }
get_header();
?>
<div style="max-width:1200px;margin:0 auto;padding:24px 20px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px">
        <h1 class="section-title" style="margin-bottom:0">Избранное</h1>
        <a href="<?= home_url('/catalog/') ?>" class="btn-secondary btn-sm">← В каталог</a>
    </div>
    <div class="products-grid" id="favorites-grid">
        <div class="loader-sm" style="grid-column:1/-1"></div>
    </div>
    <p id="favorites-empty" style="display:none;text-align:center;padding:60px 0;color:var(--text-secondary)">
        В избранном пока нет товаров. <a href="<?= home_url('/catalog/') ?>">Перейти в каталог →</a>
    </p>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var grid  = document.getElementById('favorites-grid');
    var empty = document.getElementById('favorites-empty');
    function load() {
        if (typeof mktRest === 'undefined' || typeof renderProductCard === 'undefined') {
            return setTimeout(load, 80);
        }
        mktRest('favorites', 'GET', null, function (data) {
            if (!data.items || !data.items.length) {
                grid.style.display = 'none';
                empty.style.display = '';
                return;
            }
            grid.innerHTML = '';
            data.items.forEach(function (p) {
                grid.insertAdjacentHTML('beforeend', renderProductCard(p));
            });
        });
    }
    load();
});
</script>
<?php get_footer(); ?>
