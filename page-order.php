<?php
/*
Template Name: Order Page
*/
defined('ABSPATH') || exit;
if (!is_user_logged_in()) { wp_redirect(home_url('/auth/')); exit; }

$order_id  = (int) ($_GET['id'] ?? 0);
$user_id   = get_current_user_id();

if (!$order_id || !mkt_can_access_chat($order_id, $user_id)) {
    wp_redirect(home_url('/dashboard/'));
    exit;
}

$buyer_id  = (int) get_field('buyer_id',     $order_id);
$seller_id = (int) get_field('seller_id',    $order_id);
$product_id= (int) get_field('offer_id',     $order_id);
$status    = get_field('order_status',       $order_id);
$amount    = (float) get_field('order_amount', $order_id);
$delivered = get_field('delivered_data',     $order_id) ?? '';
$reason    = get_field('arb_reason',         $order_id) ?? '';
$is_buyer  = $user_id === $buyer_id;
$is_seller = $user_id === $seller_id;
$is_admin  = mkt_is_admin($user_id);
$buyer_u   = get_userdata($buyer_id);
$seller_u  = get_userdata($seller_id);

$status_map = [
    'created'     => ['label' => 'Создан',       'class' => 'status-created'],
    'paid'        => ['label' => 'Оплачен',       'class' => 'status-paid'],
    'in_progress' => ['label' => 'В процессе',   'class' => 'status-progress'],
    'completed'   => ['label' => 'Завершён',      'class' => 'status-done'],
    'arbitration' => ['label' => 'Арбитраж',      'class' => 'status-arb'],
    'canceled'    => ['label' => 'Отменён',       'class' => 'status-cancel'],
];
$s = $status_map[$status] ?? $status_map['created'];

get_header();
?>
<div class="order-layout">
    <div class="order-sidebar">
        <div class="card order-info-card">
            <h3>Заказ #<?= $order_id ?></h3>
            <div class="order-status-badge <?= esc_attr($s['class']) ?>"><?= esc_html($s['label']) ?></div>

            <div class="order-detail-row">
                <span>Товар</span>
                <a href="<?= esc_url(get_permalink($product_id)) ?>"><?= esc_html(get_the_title($product_id)) ?></a>
            </div>
            <div class="order-detail-row">
                <span>Сумма</span>
                <strong><?= esc_html(mkt_format_price($amount)) ?></strong>
            </div>
            <div class="order-detail-row">
                <span>Дата</span>
                <span><?= esc_html(get_the_date('d.m.Y H:i', $order_id)) ?></span>
            </div>
            <div class="order-detail-row">
                <span>Покупатель</span>
                <div class="user-mini">
                    <img src="<?= esc_url(mkt_get_avatar_url($buyer_id)) ?>" width="24" alt="">
                    <?= esc_html($buyer_u ? $buyer_u->display_name : '—') ?>
                </div>
            </div>
            <div class="order-detail-row">
                <span>Продавец</span>
                <div class="user-mini">
                    <img src="<?= esc_url(mkt_get_avatar_url($seller_id)) ?>" width="24" alt="">
                    <?= esc_html($seller_u ? $seller_u->display_name : '—') ?>
                </div>
            </div>

            <?php if ($is_buyer && $delivered && $status === 'completed'): ?>
            <div class="delivered-data">
                <div class="delivered-label">Ваш товар / ключ:</div>
                <div class="key-box"><?= esc_html($delivered) ?></div>
                <button class="btn-sm btn-secondary copy-btn" data-copy="<?= esc_attr($delivered) ?>">Скопировать</button>
            </div>
            <?php endif; ?>

            <?php if ($is_buyer && $status === 'in_progress'): ?>
            <div class="order-actions">
                <button class="btn-primary btn-full" id="confirm-order-btn" data-order="<?= $order_id ?>">
                    ✅ Подтвердить получение
                </button>
                <button class="btn-danger btn-full" data-modal="modal-arbitration">
                    ⚖️ Вызвать арбитраж
                </button>
            </div>
            <?php endif; ?>

            <?php if ($is_seller && $status === 'in_progress'): ?>
            <div class="order-actions">
                <button class="btn-danger btn-full" data-modal="modal-arbitration">
                    ⚖️ Вызвать арбитраж
                </button>
            </div>
            <?php endif; ?>

            <?php if ($status === 'arbitration'): ?>
            <div class="arb-notice">
                <svg viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Арбитраж на рассмотрении. Причина: <?= esc_html($reason) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="order-chat-wrap">
        <div class="chat-header">
            <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Чат по сделке
        </div>

        <div class="chat-messages" id="chat-messages" data-order="<?= $order_id ?>" data-user="<?= $user_id ?>">
            <div class="loader-sm"></div>
        </div>

        <?php if (!in_array($status, ['completed', 'canceled'])): ?>
        <div class="chat-input-wrap">
            <textarea class="chat-input" id="chat-input" placeholder="Напишите сообщение..." rows="2"></textarea>
            <button class="btn-primary" id="chat-send-btn">
                <svg viewBox="0 0 24 24" width="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </div>
        <?php else: ?>
        <div class="chat-closed">Чат закрыт — сделка завершена.</div>
        <?php endif; ?>
    </div>
</div>

<?php if (!in_array($status, ['completed', 'canceled'])): ?>
<div class="modal-overlay" id="modal-arbitration">
    <div class="modal-box">
        <div class="modal-header">
            <h3>⚖️ Открыть арбитраж</h3>
            <button class="modal-close" data-close="modal-arbitration">×</button>
        </div>
        <div class="modal-body">
            <p>Опишите причину спора. Администратор рассмотрит ситуацию и примет решение.</p>
            <div class="form-group">
                <label>Причина</label>
                <textarea id="arb-reason" rows="4" placeholder="Подробно опишите проблему..." required></textarea>
            </div>
            <div class="form-error" id="arb-error"></div>
            <button class="btn-danger btn-full" id="open-arb-btn" data-order="<?= $order_id ?>">Открыть арбитраж</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php get_footer(); ?>
