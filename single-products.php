<?php
defined('ABSPATH') || exit;
$product_id = get_the_ID();
$price_sale = (float) get_field('price_sell',         $product_id);
$price_base = (float) get_field('price',              $product_id);
$delivery   = get_field('delivery_type',              $product_id);
$desc       = get_field('opisanie',                   $product_id);
$how_to     = get_field('sposob_polucheniya',         $product_id);
$seller_id  = (int) get_field('product_seller_id',   $product_id);
$price      = $price_sale > 0 ? $price_sale : $price_base;

$keys_count = 0;
if ($delivery === 'auto') {
    $data       = get_field('secret_data', $product_id) ?? '';
    $keys_count = count(array_filter(array_map('trim', explode("\n", $data))));
}

$seller     = get_userdata($seller_id);
$categories = get_the_terms($product_id, 'group') ?: [];
$types      = get_the_terms($product_id, 'type-group') ?: [];

$seller_orders = new WP_Query([
    'post_type'   => 'orders',
    'post_status' => 'publish',
    'meta_query'  => [
        ['key' => 'seller_id',    'value' => $seller_id],
        ['key' => 'order_status', 'value' => 'completed'],
    ],
    'posts_per_page' => 1,
    'fields'         => 'ids',
]);
$seller_sales = $seller_orders->found_posts;

get_header();
?>
<div class="product-layout">
    <div class="product-main">
        <div class="card product-card">
            <?php if (has_post_thumbnail()): ?>
            <div class="product-thumbnail">
                <?= get_the_post_thumbnail($product_id, 'large', ['class' => 'product-img']) ?>
            </div>
            <?php endif; ?>

            <div class="product-body">
                <div class="product-breadcrumbs">
                    <a href="<?= home_url('/catalog/') ?>">Каталог</a>
                    <?php foreach ($categories as $cat): ?>
                    <span>›</span>
                    <a href="<?= esc_url(get_term_link($cat)) ?>"><?= esc_html($cat->name) ?></a>
                    <?php endforeach; ?>
                </div>

                <h1 class="product-title"><?= esc_html(get_the_title()) ?></h1>

                <div class="product-tags">
                    <?php foreach ($types as $t): ?>
                    <span class="tag"><?= esc_html($t->name) ?></span>
                    <?php endforeach; ?>
                    <span class="tag tag-delivery <?= $delivery === 'auto' ? 'tag-auto' : 'tag-manual' ?>">
                        <?= $delivery === 'auto' ? '⚡ Автовыдача' : '👤 Ручная выдача' ?>
                    </span>
                    <?php if ($delivery === 'auto' && $keys_count > 0): ?>
                    <span class="tag tag-stock">В наличии: <?= $keys_count ?> шт</span>
                    <?php elseif ($delivery === 'auto' && $keys_count === 0): ?>
                    <span class="tag tag-out">Нет в наличии</span>
                    <?php endif; ?>
                </div>

                <?php if ($desc): ?>
                <div class="product-desc">
                    <h3>Описание</h3>
                    <p><?= nl2br(esc_html($desc)) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($how_to): ?>
                <div class="product-howto">
                    <h3>Способ получения</h3>
                    <p><?= nl2br(esc_html($how_to)) ?></p>
                </div>
                <?php endif; ?>

                <div class="product-reviews" id="product-reviews" data-product="<?= $product_id ?>">
                    <h3>Отзывы</h3>
                    <div id="reviews-list"><div class="loader-sm"></div></div>
                </div>
            </div>
        </div>
    </div>

    <aside class="product-sidebar">
        <div class="card product-buy-card">
            <div class="product-price-block">
                <?php if ($price_sale > 0): ?>
                <span class="price-old"><?= esc_html(mkt_format_price($price_base)) ?></span>
                <span class="price-new"><?= esc_html(mkt_format_price($price_sale)) ?></span>
                <span class="price-discount">-<?= round((1 - $price_sale / $price_base) * 100) ?>%</span>
                <?php else: ?>
                <span class="price-main"><?= esc_html(mkt_format_price($price_base)) ?></span>
                <?php endif; ?>
            </div>

            <?php if (is_user_logged_in() && get_current_user_id() !== $seller_id): ?>
                <?php if ($delivery === 'auto' && $keys_count === 0): ?>
                <button class="btn-primary btn-full" disabled>Нет в наличии</button>
                <?php else: ?>
                <button class="btn-primary btn-full" id="buy-btn" data-product="<?= $product_id ?>" data-price="<?= esc_attr($price) ?>">
                    Купить за <?= esc_html(mkt_format_price($price)) ?>
                </button>
                <?php endif; ?>
            <?php elseif (!is_user_logged_in()): ?>
            <a href="<?= home_url('/auth/') ?>" class="btn-primary btn-full">Войти чтобы купить</a>
            <?php endif; ?>

            <div class="escrow-notice">
                <svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Безопасная сделка через Escrow
            </div>
        </div>

        <?php if ($seller): ?>
        <div class="card seller-card">
            <h4>Продавец</h4>
            <div class="seller-info">
                <img src="<?= esc_url(mkt_get_avatar_url($seller_id)) ?>" width="48" alt="Аватар продавца" class="seller-avatar">
                <div>
                    <div class="seller-name"><?= esc_html($seller->display_name) ?></div>
                    <div class="seller-meta">Продаж: <?= $seller_sales ?></div>
                    <div class="seller-meta">На сайте с <?= mysql2date('d.m.Y', $seller->user_registered) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </aside>
</div>

<div class="modal-overlay" id="modal-buy-confirm">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Подтверждение покупки</h3>
            <button class="modal-close" data-close="modal-buy-confirm">×</button>
        </div>
        <div class="modal-body">
            <p>Товар: <strong id="confirm-product-name"><?= esc_html(get_the_title()) ?></strong></p>
            <p>Сумма: <strong id="confirm-price"><?= esc_html(mkt_format_price($price)) ?></strong></p>
            <p>Средства будут заморожены на Escrow до подтверждения получения товара.</p>
            <div class="modal-actions">
                <button class="btn-secondary" data-close="modal-buy-confirm">Отмена</button>
                <button class="btn-primary" id="confirm-buy-btn" data-product="<?= $product_id ?>">Купить</button>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
