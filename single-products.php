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

$seller         = get_userdata($seller_id);
$categories     = get_the_terms($product_id, 'group') ?: [];
$types          = get_the_terms($product_id, 'type-group') ?: [];
$product_rating = mkt_get_product_rating($product_id);
$seller_rating  = mkt_get_seller_rating($seller_id);

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
                    <div class="reviews-grid" id="reviews-grid"><div class="loader-sm"></div></div>
                    <div id="reviews-more-wrap" style="display:none;text-align:center;margin-top:16px">
                        <button class="btn-secondary" id="load-more-reviews">Показать ещё</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <aside class="product-sidebar">
        <div class="card product-buy-card">
            <div class="product-price-block">
                <?php if ($price_sale > 0): ?>
                <span class="price-new"><?= esc_html(mkt_format_price($price_sale)) ?></span>
                <span class="price-old"><?= esc_html(mkt_format_price($price_base)) ?></span>
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

            <?php if (is_user_logged_in()): ?>
            <?php $is_fav = in_array($product_id, mkt_get_favorites(get_current_user_id()), true); ?>
            <button class="btn-fav-product <?= $is_fav ? 'fav-active' : '' ?>"
                    onclick="mktToggleFav(event, <?= $product_id ?>)">
                <svg viewBox="0 0 24 24" width="16" fill="<?= $is_fav ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
                <span id="fav-label"><?= $is_fav ? 'В избранном' : 'В избранное' ?></span>
            </button>
            <?php endif; ?>

            <button class="btn-secondary btn-full copy-btn" data-copy="<?= esc_attr(get_permalink()) ?>" style="margin-top:8px">
                <svg viewBox="0 0 24 24" width="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                Скопировать ссылку
            </button>

            <?php if ($product_rating['count'] > 0): ?>
            <div class="product-buy-rating">
                <?= mkt_stars_html($product_rating['avg'], $product_rating['count']) ?>
            </div>
            <?php endif; ?>

            <div class="escrow-notice">
                <svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Безопасная сделка через Escrow
            </div>
        </div>

        <?php if ($seller): ?>
        <div class="card seller-card">
            <h4>Продавец</h4>
            <a href="<?= esc_url(get_author_posts_url($seller_id)) ?>" class="seller-info seller-info-link">
                <img src="<?= esc_url(mkt_get_avatar_url($seller_id)) ?>" width="48" alt="Аватар продавца" class="seller-avatar">
                <div>
                    <div class="seller-name"><?= esc_html($seller->display_name) ?></div>
                    <div class="seller-meta">Продаж: <?= $seller_sales ?></div>
                    <?php if ($seller_rating['count'] > 0): ?>
                    <div class="seller-meta" style="margin-top:4px"><?= mkt_stars_html($seller_rating['avg'], $seller_rating['count']) ?></div>
                    <?php endif; ?>
                    <?php $online_label = mkt_last_seen_label($seller_id); if ($online_label): ?>
                    <div class="seller-meta online-status <?= mkt_is_online($seller_id) ? 'is-online' : '' ?>" style="margin-top:4px">
                        <span class="online-dot"></span><?= esc_html($online_label) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:auto;flex-shrink:0;color:var(--primary)"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>
        <?php endif; ?>
    </aside>
</div>

<?php
$first_category_slug = !empty($categories) ? $categories[0]->slug : '';
$thumb_url = get_the_post_thumbnail_url($product_id, 'medium') ?: '';
?>
<script>
window.MKT_PRODUCT = {
    id:       <?= (int) $product_id ?>,
    title:    <?= wp_json_encode(get_the_title()) ?>,
    price:    <?= (float) $price ?>,
    thumbnail:<?= wp_json_encode($thumb_url) ?>,
    url:      <?= wp_json_encode(get_permalink()) ?>,
    category: <?= wp_json_encode($first_category_slug) ?>,
    sellerId: <?= (int) $seller_id ?>
};
</script>
<div class="extra-blocks" style="max-width:1100px;margin:0 auto;padding:0 20px 32px">
    <div class="extra-block" id="recently-viewed-section" style="display:none;margin-bottom:32px">
        <h2 class="section-title">Вы смотрели</h2>
        <div class="recently-viewed-scroll">
            <div class="recently-viewed-grid" id="recently-viewed-grid"></div>
        </div>
    </div>

    <div class="extra-block" id="similar-products-section">
        <h2 class="section-title">Похожие товары</h2>
        <div class="products-grid" id="similar-products-grid">
            <div class="loader-sm" style="grid-column:1/-1"></div>
        </div>
    </div>
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
