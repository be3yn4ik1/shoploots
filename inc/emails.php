<?php
defined('ABSPATH') || exit;

function mkt_send_email(int $user_id, string $subject, string $body): void {
    $user = get_userdata($user_id);
    if (!$user || !$user->user_email) return;
    $site    = get_bloginfo('name');
    $host    = wp_parse_url(home_url(), PHP_URL_HOST);
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site . ' <noreply@' . $host . '>',
    ];
    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:24px">'
          . '<h2 style="color:#0077ff;margin-bottom:16px">' . esc_html($site) . '</h2>'
          . $body
          . '<hr style="margin:24px 0;border:none;border-top:1px solid #e0e0e0">'
          . '<p style="color:#999;font-size:12px">Это автоматическое письмо. Не отвечайте на него.</p>'
          . '</div>';
    wp_mail($user->user_email, '[' . $site . '] ' . $subject, $html, $headers);
}

function mkt_email_order_created(int $order_id): void {
    $seller_id     = (int) get_field('seller_id', $order_id);
    $product_id    = (int) get_field('offer_id',  $order_id);
    $amount        = (float) get_field('order_amount', $order_id);
    $product_title = $product_id ? get_the_title($product_id) : '—';
    $order_url     = home_url("/orders/?id={$order_id}");
    $body = '<p>Новый заказ на ваш товар <strong>' . esc_html($product_title) . '</strong> '
          . 'на сумму <strong>' . mkt_format_price($amount) . '</strong>.</p>'
          . '<p><a href="' . esc_url($order_url) . '" style="background:#0077ff;color:#fff;padding:10px 20px;border-radius:8px;display:inline-block;text-decoration:none;margin-top:8px">Открыть заказ →</a></p>';
    mkt_send_email($seller_id, 'Новый заказ', $body);
}

function mkt_email_order_completed(int $order_id): void {
    $seller_id     = (int) get_field('seller_id', $order_id);
    $buyer_id      = (int) get_field('buyer_id',  $order_id);
    $amount        = (float) get_field('order_amount', $order_id);
    $seller_gets   = round($amount * (1 - mkt_commission_rate()), 2);
    $product_id    = (int) get_field('offer_id', $order_id);
    $product_title = $product_id ? get_the_title($product_id) : '—';
    $order_url     = home_url("/orders/?id={$order_id}");

    $body = '<p>Сделка по товару <strong>' . esc_html($product_title) . '</strong> завершена. '
          . 'Вам начислено: <strong>' . mkt_format_price($seller_gets) . '</strong>.</p>'
          . '<p><a href="' . esc_url($order_url) . '" style="background:#0077ff;color:#fff;padding:10px 20px;border-radius:8px;display:inline-block;text-decoration:none;margin-top:8px">Открыть заказ →</a></p>';
    mkt_send_email($seller_id, 'Сделка завершена — получите средства', $body);

    $body = '<p>Ваш заказ <strong>' . esc_html($product_title) . '</strong> успешно завершён.</p>'
          . '<p><a href="' . esc_url($order_url) . '" style="background:#0077ff;color:#fff;padding:10px 20px;border-radius:8px;display:inline-block;text-decoration:none;margin-top:8px">Открыть заказ →</a></p>';
    mkt_send_email($buyer_id, 'Заказ завершён', $body);
}

function mkt_email_chat_message(int $order_id, int $sender_id): void {
    $buyer_id     = (int) get_field('buyer_id',  $order_id);
    $seller_id    = (int) get_field('seller_id', $order_id);
    $recipient_id = ($sender_id === $buyer_id) ? $seller_id : $buyer_id;

    // Throttle: не чаще 1 письма каждые 30 мин на одного получателя per order
    $key = 'mkt_chat_email_' . $order_id . '_' . $recipient_id;
    if (get_transient($key)) return;
    set_transient($key, 1, 30 * MINUTE_IN_SECONDS);

    $order_url = home_url("/orders/?id={$order_id}");
    $body = '<p>У вас новое сообщение по заказу. Нажмите кнопку, чтобы ответить.</p>'
          . '<p><a href="' . esc_url($order_url) . '" style="background:#0077ff;color:#fff;padding:10px 20px;border-radius:8px;display:inline-block;text-decoration:none;margin-top:8px">Перейти в чат →</a></p>';
    mkt_send_email($recipient_id, 'Новое сообщение в чате', $body);
}
