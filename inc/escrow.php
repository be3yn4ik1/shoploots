<?php
defined('ABSPATH') || exit;

add_action('wp_ajax_mkt_buy', 'mkt_ajax_buy');
function mkt_ajax_buy(): void {
    mkt_check_nonce();
    mkt_require_login();

    $product_id = (int) ($_POST['product_id'] ?? 0);
    $buyer_id   = get_current_user_id();

    if (!$product_id || get_post_type($product_id) !== 'products') {
        wp_send_json_error(['message' => 'Товар не найден.']);
    }

    if (get_post_status($product_id) !== 'publish') {
        wp_send_json_error(['message' => 'Товар недоступен.']);
    }

    $seller_id = (int) get_field('product_seller_id', $product_id);
    if (!$seller_id || $seller_id === $buyer_id) {
        wp_send_json_error(['message' => 'Нельзя купить собственный товар.']);
    }

    $price_sale = (float) get_field('price_sell', $product_id);
    $price_base = (float) get_field('price', $product_id);
    $amount     = $price_sale > 0 ? $price_sale : $price_base;

    if ($amount <= 0) {
        wp_send_json_error(['message' => 'Некорректная цена.']);
    }

    $delivery = get_field('delivery_type', $product_id);

    if ($delivery === 'auto') {
        $keys = array_filter(array_map('trim', explode("\n", get_field('secret_data', $product_id) ?? '')));
        if (empty($keys)) {
            wp_send_json_error(['message' => 'Товар закончился. Обратитесь к продавцу.']);
        }
    }

    $balance = mkt_get_balance($buyer_id);
    if ($balance < $amount) {
        wp_send_json_error(['message' => 'Недостаточно средств на балансе.', 'need_deposit' => true]);
    }

    if (!mkt_subtract_balance($buyer_id, $amount)) {
        wp_send_json_error(['message' => 'Ошибка списания средств.']);
    }
    mkt_add_hold($seller_id, $amount);

    $order_id = wp_insert_post([
        'post_type'   => 'orders',
        'post_status' => 'publish',
        'post_title'  => sprintf('Заказ #%s — %s', date('YmdHis'), get_the_title($product_id)),
        'post_author' => $buyer_id,
    ]);

    if (is_wp_error($order_id)) {
        mkt_add_balance($buyer_id, $amount);
        mkt_subtract_hold($seller_id, $amount);
        wp_send_json_error(['message' => 'Ошибка создания заказа.']);
    }

    update_field('offer_id',      $product_id, $order_id);
    update_field('buyer_id',      $buyer_id,   $order_id);
    update_field('seller_id',     $seller_id,  $order_id);
    update_field('order_amount',  $amount,     $order_id);
    update_field('order_status',  'in_progress', $order_id);

    if ($delivery === 'auto') {
        $keys      = array_values($keys);
        $key_given = array_shift($keys);
        $remaining = implode("\n", $keys);
        update_field('secret_data', $remaining, $product_id);
        update_field('delivered_data', $key_given, $order_id);

        mkt_chat_system_message($order_id, "Ключ доставлен автоматически.\nВаш ключ: {$key_given}\n\nПодтвердите получение после проверки.");
        mkt_log('purchase', $buyer_id, 'Покупка: ' . get_the_title($product_id), ['order_id' => $order_id, 'amount' => $amount, 'product' => get_the_title($product_id)]);

        wp_send_json_success([
            'type'     => 'auto',
            'order_id' => $order_id,
            'redirect' => home_url("/orders/?id={$order_id}"),
            'message'  => 'Ключ выдан! Проверьте заказ и подтвердите получение.',
        ]);
    }

    mkt_chat_system_message($order_id, 'Сделка создана. Продавец должен передать товар.');
    mkt_log('purchase', $buyer_id, 'Покупка: ' . get_the_title($product_id), ['order_id' => $order_id, 'amount' => $amount, 'product' => get_the_title($product_id)]);

    wp_send_json_success([
        'type'     => 'manual',
        'order_id' => $order_id,
        'redirect' => home_url("/orders/?id={$order_id}"),
        'message'  => 'Заказ создан. Перейдите в чат с продавцом.',
    ]);
}

add_action('wp_ajax_mkt_confirm_order', 'mkt_ajax_confirm_order');
function mkt_ajax_confirm_order(): void {
    mkt_check_nonce();
    mkt_require_login();

    $order_id = (int) ($_POST['order_id'] ?? 0);
    $buyer_id = get_current_user_id();

    if (!$order_id) wp_send_json_error(['message' => 'Заказ не найден.']);

    $stored_buyer = (int) get_field('buyer_id', $order_id);
    if ($stored_buyer !== $buyer_id) wp_send_json_error(['message' => 'Нет доступа.']);

    $status = get_field('order_status', $order_id);
    if ($status !== 'in_progress') wp_send_json_error(['message' => 'Нельзя подтвердить заказ в текущем статусе.']);

    $seller_id = (int) get_field('seller_id', $order_id);
    $amount    = (float) get_field('order_amount', $order_id);
    $commission = round($amount * mkt_commission_rate(), 2);
    $seller_gets = round($amount - $commission, 2);

    $product_id = (int) get_field('offer_id', $order_id);
    $product_title = $product_id ? get_the_title($product_id) : '';
    mkt_subtract_hold($seller_id, $amount);
    mkt_add_balance($seller_id, $seller_gets);
    update_field('order_status', 'completed', $order_id);
    mkt_execute_referral_payouts($buyer_id,  $amount);
    mkt_execute_referral_payouts($seller_id, $amount);
    if ($product_id) {
        update_field('total_sales_count', (int) get_field('total_sales_count', $product_id) + 1, $product_id);
    }
    mkt_chat_system_message($order_id, 'Покупатель подтвердил получение. Средства переведены продавцу.');
    mkt_log('order_confirmed', $buyer_id, 'Покупка подтверждена: ' . $product_title, ['order_id' => $order_id, 'amount' => $amount]);
    mkt_log('sale_completed',  $seller_id, 'Продажа завершена: '  . $product_title, ['order_id' => $order_id, 'amount' => $seller_gets]);

    wp_send_json_success(['message' => 'Сделка завершена. Средства переведены продавцу.']);
}

add_action('wp_ajax_mkt_open_arbitration', 'mkt_ajax_open_arbitration');
function mkt_ajax_open_arbitration(): void {
    mkt_check_nonce();
    mkt_require_login();

    $order_id = (int) ($_POST['order_id'] ?? 0);
    $reason   = sanitize_textarea_field($_POST['reason'] ?? '');
    $user_id  = get_current_user_id();

    if (!$order_id || !$reason) wp_send_json_error(['message' => 'Укажите причину спора.']);

    $buyer_id  = (int) get_field('buyer_id',  $order_id);
    $seller_id = (int) get_field('seller_id', $order_id);

    if ($user_id !== $buyer_id && $user_id !== $seller_id && !mkt_is_admin($user_id)) {
        wp_send_json_error(['message' => 'Нет доступа.']);
    }

    $status = get_field('order_status', $order_id);
    if (!in_array($status, ['in_progress', 'paid'])) {
        wp_send_json_error(['message' => 'Арбитраж невозможен в текущем статусе.']);
    }

    update_field('order_status', 'arbitration', $order_id);
    update_field('arb_reason', $reason, $order_id);
    update_field('arb_decision', 'none', $order_id);
    update_user_meta($order_id, '_arb_opened_by', $user_id);
    update_post_meta($order_id, '_arb_opened_at', current_time('mysql'));

    mkt_chat_system_message($order_id, "Открыт арбитраж. Причина: {$reason}");
    mkt_log('arbitration_open', $user_id, 'Арбитраж открыт', ['order_id' => $order_id]);

    wp_send_json_success(['message' => 'Арбитраж открыт. Администратор рассмотрит спор.']);
}

function mkt_arbitration_refund(int $order_id): void {
    $status = get_field('order_status', $order_id);
    if ($status !== 'arbitration') return;

    $buyer_id  = (int) get_field('buyer_id',  $order_id);
    $seller_id = (int) get_field('seller_id', $order_id);
    $amount    = (float) get_field('order_amount', $order_id);

    mkt_subtract_hold($seller_id, $amount);
    mkt_add_balance($buyer_id, $amount);
    update_field('order_status', 'canceled', $order_id);
    mkt_chat_system_message($order_id, 'Арбитраж завершён. Средства возвращены покупателю.');
    mkt_log('arbitration_refund', $buyer_id, 'Возврат средств по арбитражу', ['order_id' => $order_id, 'amount' => $amount]);
}

function mkt_arbitration_release(int $order_id): void {
    $status = get_field('order_status', $order_id);
    if ($status !== 'arbitration') return;

    $buyer_id    = (int) get_field('buyer_id',  $order_id);
    $seller_id   = (int) get_field('seller_id', $order_id);
    $amount      = (float) get_field('order_amount', $order_id);
    $commission  = round($amount * mkt_commission_rate(), 2);
    $seller_gets = round($amount - $commission, 2);

    mkt_subtract_hold($seller_id, $amount);
    mkt_add_balance($seller_id, $seller_gets);
    update_field('order_status', 'completed', $order_id);
    mkt_execute_referral_payouts($buyer_id,  $amount);
    mkt_execute_referral_payouts($seller_id, $amount);
    mkt_chat_system_message($order_id, 'Арбитраж завершён. Средства переведены продавцу.');
    mkt_log('arbitration_release', $seller_id, 'Выплата по арбитражу', ['order_id' => $order_id, 'amount' => $seller_gets]);
}

add_action('wp_ajax_mkt_request_payout', 'mkt_ajax_request_payout');
function mkt_ajax_request_payout(): void {
    mkt_check_nonce();
    mkt_require_login();

    $user_id  = get_current_user_id();
    $amount   = (float) ($_POST['amount'] ?? 0);
    $card     = sanitize_text_field($_POST['card'] ?? '');

    $min = (float) mkt_get_system_option('min_withdrawal', 100);
    $max = (float) mkt_get_system_option('max_withdrawal', 50000);

    if ($amount < $min) wp_send_json_error(['message' => "Минимальная сумма вывода: {$min} ₽"]);
    if ($amount > $max) wp_send_json_error(['message' => "Максимальная сумма вывода: {$max} ₽"]);
    if (!$card) wp_send_json_error(['message' => 'Укажите реквизиты.']);

    $balance = mkt_get_balance($user_id);
    if ($balance < $amount) wp_send_json_error(['message' => 'Недостаточно средств.']);

    if (!mkt_subtract_balance($user_id, $amount)) {
        wp_send_json_error(['message' => 'Ошибка списания.']);
    }

    $payout_id = wp_insert_post([
        'post_type'   => 'payout',
        'post_status' => 'publish',
        'post_title'  => sprintf('Вывод %s ₽ — %s', $amount, get_userdata($user_id)->display_name),
        'post_author' => $user_id,
    ]);

    if (is_wp_error($payout_id)) {
        mkt_add_balance($user_id, $amount);
        wp_send_json_error(['message' => 'Ошибка создания заявки.']);
    }

    update_field('payout_amount',  $amount,    $payout_id);
    update_field('payout_method',  $card,      $payout_id);
    update_field('payout_status',  'pending',  $payout_id);
    update_post_meta($payout_id, '_payout_user_id',      $user_id);
    update_post_meta($payout_id, '_payout_available_at', date('Y-m-d H:i:s', strtotime('+48 hours')));
    mkt_log('payout_request', $user_id, 'Заявка на вывод средств', ['amount' => $amount, 'method' => $card, 'payout_id' => $payout_id]);

    wp_send_json_success(['message' => 'Заявка на вывод создана. Ожидайте 48 часов.']);
}

add_action('wp_ajax_mkt_admin_confirm_order', 'mkt_ajax_admin_confirm_order');
function mkt_ajax_admin_confirm_order(): void {
    mkt_check_nonce();
    mkt_require_login();
    $user_id  = get_current_user_id();
    if (!mkt_is_admin($user_id)) wp_send_json_error(['message' => 'Нет доступа.']);
    $order_id = (int) ($_POST['order_id'] ?? 0);
    if (!$order_id) wp_send_json_error(['message' => 'Заказ не найден.']);
    $status = get_field('order_status', $order_id);
    if ($status !== 'arbitration') wp_send_json_error(['message' => 'Можно подтвердить только заказ в арбитраже.']);
    mkt_arbitration_release($order_id);
    wp_send_json_success(['message' => 'Заказ подтверждён. Средства переведены продавцу.']);
}

add_action('wp_ajax_mkt_admin_cancel_order', 'mkt_ajax_admin_cancel_order');
function mkt_ajax_admin_cancel_order(): void {
    mkt_check_nonce();
    mkt_require_login();
    $user_id  = get_current_user_id();
    if (!mkt_is_admin($user_id)) wp_send_json_error(['message' => 'Нет доступа.']);
    $order_id = (int) ($_POST['order_id'] ?? 0);
    if (!$order_id) wp_send_json_error(['message' => 'Заказ не найден.']);
    $status = get_field('order_status', $order_id);
    if ($status !== 'arbitration') wp_send_json_error(['message' => 'Можно отменить только заказ в арбитраже.']);
    mkt_arbitration_refund($order_id);
    wp_send_json_success(['message' => 'Заказ отменён. Средства возвращены покупателю.']);
}

function mkt_process_payout_completion(int $payout_id): void {
    $user_id   = (int) get_post_meta($payout_id, '_payout_user_id', true);
    $amount    = (float) get_field('payout_amount', $payout_id);
    $processed = get_post_meta($payout_id, '_payout_processed', true);
    if ($processed || !$user_id || !$amount) return;
    update_post_meta($payout_id, '_payout_processed', 1);
    mkt_log('payout_completed', $user_id, 'Выплата произведена', ['payout_id' => $payout_id, 'amount' => $amount]);
}
