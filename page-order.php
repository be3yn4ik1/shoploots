<?php
/*
Template Name: Order Page
*/
defined('ABSPATH') || exit;
if (!is_user_logged_in()) { wp_redirect(home_url('/auth/')); exit; }

$order_id = (int) ($_GET['id'] ?? 0);
$user_id  = get_current_user_id();

if (!$order_id || get_post_type($order_id) !== 'orders') {
    wp_redirect(home_url('/dashboard/')); exit;
}
if (!mkt_can_access_chat($order_id, $user_id)) {
    wp_redirect(home_url('/dashboard/')); exit;
}

$buyer_id   = (int) get_field('buyer_id',      $order_id);
$seller_id  = (int) get_field('seller_id',     $order_id);
$product_id = (int) get_field('offer_id',      $order_id);
$status     = get_field('order_status',        $order_id);
$amount     = (float) get_field('order_amount', $order_id);
$delivered  = get_field('delivered_data',      $order_id) ?? '';
$reason     = get_field('arb_reason',          $order_id) ?? '';
$is_buyer   = $user_id === $buyer_id;
$is_seller  = $user_id === $seller_id;
$is_admin   = mkt_is_admin($user_id);
$buyer_u    = get_userdata($buyer_id);
$seller_u   = get_userdata($seller_id);
$review_data = get_field('otzyv', $order_id);
$has_review  = !empty($review_data['tekst_otzyva']);
$closed      = in_array($status, ['completed', 'canceled']);

global $wpdb;
$last_msg_id = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT MAX(id) FROM {$wpdb->prefix}mkt_chat WHERE order_id = %d",
    $order_id
));
if ($last_msg_id) {
    update_user_meta($user_id, "_mkt_last_read_{$order_id}", $last_msg_id);
}

$status_map = [
    'created'     => ['label' => 'Создан',     'class' => 'status-created'],
    'paid'        => ['label' => 'Оплачен',     'class' => 'status-paid'],
    'in_progress' => ['label' => 'В процессе', 'class' => 'status-progress'],
    'completed'   => ['label' => 'Завершён',    'class' => 'status-done'],
    'arbitration' => ['label' => 'Арбитраж',    'class' => 'status-arb'],
    'canceled'    => ['label' => 'Отменён',     'class' => 'status-cancel'],
];
$s = $status_map[$status] ?? $status_map['created'];

get_header();
?>
<div class="order-page-wrap">

    <div class="order-breadcrumb">
        <a href="<?= esc_url(home_url('/dashboard/')) ?>">Кабинет</a>
        <svg viewBox="0 0 24 24" width="14" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        Заказ #<?= $order_id ?>
    </div>

    <div class="card order-header-card">
        <div class="order-header-top">
            <div>
                <h1 class="order-title">Заказ #<?= $order_id ?></h1>
                <?php if ($product_id): ?>
                <a href="<?= esc_url(get_permalink($product_id)) ?>" style="font-size:.875rem;color:var(--text-secondary);margin-top:4px;display:block">
                    <?= esc_html(get_the_title($product_id)) ?>
                </a>
                <?php endif; ?>
            </div>
            <span class="order-status-badge <?= esc_attr($s['class']) ?>"><?= esc_html($s['label']) ?></span>
        </div>
        <div class="order-header-meta">
            <span><strong><?= esc_html(mkt_format_price($amount)) ?></strong></span>
            <span><?= esc_html(get_the_date('d.m.Y H:i', $order_id)) ?></span>
            <div class="order-users">
                <div class="user-mini">
                    <img src="<?= esc_url(mkt_get_avatar_url($buyer_id)) ?>" width="20" alt="" style="border-radius:50%;object-fit:cover">
                    <?= esc_html($buyer_u ? $buyer_u->display_name : '—') ?>
                </div>
                <svg viewBox="0 0 24 24" width="14" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                <div class="user-mini">
                    <img src="<?= esc_url(mkt_get_avatar_url($seller_id)) ?>" width="20" alt="" style="border-radius:50%;object-fit:cover">
                    <?= esc_html($seller_u ? $seller_u->display_name : '—') ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_buyer && $delivered): ?>
    <div class="card order-key-card">
        <div class="key-card-label">
            <svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
            Ваш ключ / данные товара
        </div>
        <div class="key-box"><?= esc_html($delivered) ?></div>
        <button class="btn-sm btn-secondary copy-btn" data-copy="<?= esc_attr($delivered) ?>">Скопировать</button>
    </div>
    <?php endif; ?>

    <div class="card order-chat-card">
        <div class="order-chat-header">
            <svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Чат по сделке
        </div>
        <div class="chat-messages" id="chat-messages" data-order="<?= $order_id ?>" data-user="<?= $user_id ?>">
            <div class="loader-sm"></div>
        </div>
        <?php if (!$closed): ?>
        <div class="chat-input-wrap">
            <textarea class="chat-input" id="chat-input" placeholder="Напишите сообщение..." rows="1"></textarea>
            <button class="btn-primary chat-send-btn-icon" id="chat-send-btn" title="Отправить">
                <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </div>
        <?php else: ?>
        <div class="chat-closed-notice">
            <svg viewBox="0 0 24 24" width="15" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Чат закрыт — сделка завершена
        </div>
        <?php endif; ?>
    </div>

    <?php if ($is_buyer && $status === 'in_progress'): ?>
    <div class="order-actions-bar">
        <button class="btn-primary btn-action" id="confirm-order-btn" data-order="<?= $order_id ?>">
            <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Подтвердить получение
        </button>
        <button class="btn-secondary btn-action" data-modal="modal-arbitration">
            <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Позвать администратора
        </button>
    </div>
    <?php endif; ?>

    <?php if ($is_seller && $status === 'in_progress'): ?>
    <div class="order-actions-bar">
        <button class="btn-secondary btn-action" data-modal="modal-arbitration">
            <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Позвать администратора
        </button>
    </div>
    <?php endif; ?>

    <?php if ($is_admin && $status === 'arbitration'): ?>
    <div class="order-actions-bar">
        <button class="btn-primary btn-action" id="admin-confirm-btn" data-order="<?= $order_id ?>">
            <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Подтвердить заказ (продавцу)
        </button>
        <button class="btn-danger btn-action" id="admin-cancel-btn" data-order="<?= $order_id ?>">
            <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Отменить заказ (возврат)
        </button>
    </div>
    <?php endif; ?>

    <?php if ($reason && $status === 'arbitration'): ?>
    <div class="card arb-notice-card">
        <svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Арбитраж на рассмотрении. Причина: <strong><?= esc_html($reason) ?></strong>
    </div>
    <?php endif; ?>

    <?php if ($is_buyer && $closed && !$has_review): ?>
    <div class="card order-review-card">
        <div class="review-form-title">Оставить отзыв о товаре</div>
        <div class="star-rating" id="star-rating">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <span class="star <?= $i <= 5 ? 'active' : '' ?>" data-val="<?= $i ?>">★</span>
            <?php endfor; ?>
        </div>
        <div class="form-group" style="margin-top:12px;margin-bottom:12px">
            <textarea id="review-text" rows="3" placeholder="Ваш отзыв о товаре и продавце..."></textarea>
        </div>
        <div class="form-error" id="review-error"></div>
        <button class="btn-primary" id="submit-review-btn" data-order="<?= $order_id ?>">Отправить отзыв</button>
    </div>
    <?php endif; ?>

    <?php if ($is_buyer && $has_review): ?>
    <div class="card order-review-done">
        <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Вы уже оставили отзыв на этот заказ
    </div>
    <?php endif; ?>

</div>

<?php if (!$closed): ?>
<div class="modal-overlay" id="modal-arbitration">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Позвать администратора</h3>
            <button class="modal-close" data-close="modal-arbitration">×</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom:16px;color:var(--text-secondary);font-size:.875rem">Опишите проблему. Администратор присоединится к чату и примет решение.</p>
            <div class="form-group">
                <label>Причина спора</label>
                <textarea id="arb-reason" rows="4" placeholder="Подробно опишите проблему..."></textarea>
            </div>
            <div class="form-error" id="arb-error"></div>
            <button class="btn-danger btn-full" id="open-arb-btn" data-order="<?= $order_id ?>">Открыть арбитраж</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php get_footer(); ?>
