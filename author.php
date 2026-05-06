<?php
defined('ABSPATH') || exit;

$seller = get_queried_object();
if (!$seller instanceof WP_User) { wp_redirect(home_url('/catalog/')); exit; }

$seller_id = $seller->ID;
if (mkt_is_admin($seller_id)) { wp_redirect(home_url('/catalog/')); exit; }

$avatar = mkt_get_avatar_url($seller_id);
$orders_q = new WP_Query([
    'post_type'   => 'orders',
    'post_status' => 'publish',
    'meta_query'  => [
        ['key' => 'seller_id',    'value' => $seller_id],
        ['key' => 'order_status', 'value' => 'completed'],
    ],
    'posts_per_page' => 1,
    'fields'         => 'ids',
]);
$total_sales = $orders_q->found_posts;

$reviews_q = new WP_Query([
    'post_type'   => 'orders',
    'post_status' => 'publish',
    'meta_query'  => [
        ['key' => 'seller_id',   'value' => $seller_id],
        ['key' => 'order_status','value' => 'completed'],
        ['key' => 'tekst_otzyva','value' => '','compare' => '!='],
    ],
    'posts_per_page' => 1,
    'fields'         => 'ids',
]);
$total_reviews = $reviews_q->found_posts;
$seller_rating = mkt_get_seller_rating($seller_id);

get_header();
?>
<div style="max-width:1100px;margin:0 auto;padding:24px 20px">

    <div class="card" style="display:flex;gap:24px;align-items:flex-start;padding:28px;margin-bottom:24px">
        <img src="<?= esc_url($avatar) ?>" width="88" height="88"
             style="border-radius:50%;object-fit:cover;border:3px solid var(--border);flex-shrink:0" alt="">
        <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px">
                <h1 style="font-size:1.5rem;font-weight:700;margin:0"><?= esc_html($seller->display_name) ?></h1>
                <span class="role-badge role-buyer">Пользователь</span>
            </div>
            <div style="display:flex;gap:24px;flex-wrap:wrap;font-size:.875rem;color:var(--text-secondary)">
                <span>На сайте с <strong><?= mysql2date('d.m.Y', $seller->user_registered) ?></strong></span>
                <span>Продаж: <strong><?= $total_sales ?></strong></span>
                <span>Отзывов: <strong><?= $total_reviews ?></strong><?php if ($seller_rating['count'] > 0): ?> <?= mkt_stars_html($seller_rating['avg'], $seller_rating['count']) ?><?php endif; ?></span>
                <?php $online_label = mkt_last_seen_label($seller_id); if ($online_label): ?>
                <span class="online-status <?= mkt_is_online($seller_id) ? 'is-online' : '' ?>">
                    <span class="online-dot"></span><?= esc_html($online_label) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="seller-content">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
            <h2 style="font-size:1.15rem;font-weight:700;margin:0">Товары продавца</h2>
            <span class="catalog-count" id="seller-product-count"></span>
        </div>
        <div class="products-grid" id="seller-products-grid"
             data-seller="<?= $seller_id ?>">
            <div class="loader-sm" style="grid-column:1/-1"></div>
        </div>
        <div class="pagination" id="seller-pagination"></div>
    </div>
</div>
<?php get_footer(); ?>
