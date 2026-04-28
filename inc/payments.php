<?php
defined('ABSPATH') || exit;

function mkt_freekassa_payment_url(int $user_id, float $amount, string $currency = 'RUB'): string {
    $merchant_id = mkt_get_system_option('fk_merchant_id', '');
    $secret1     = mkt_get_system_option('fk_secret_word', '');
    $order_id    = 'dep_' . $user_id . '_' . time();

    update_user_meta($user_id, '_fk_pending_order_' . $order_id, $amount);

    $sign = md5("{$merchant_id}:{$amount}:{$secret1}:{$currency}:{$order_id}");

    return add_query_arg([
        'm'  => $merchant_id,
        'oa' => $amount,
        'o'  => $order_id,
        's'  => $sign,
        'currency' => $currency,
        'em' => get_userdata($user_id)->user_email,
        'us' => $user_id,
    ], 'https://pay.freekassa.com/');
}

add_action('wp_ajax_mkt_get_deposit_url', 'mkt_ajax_get_deposit_url');
add_action('wp_ajax_nopriv_mkt_get_deposit_url', 'mkt_ajax_get_deposit_url');
function mkt_ajax_get_deposit_url(): void {
    mkt_check_nonce();
    mkt_require_login();

    $amount  = (float) ($_POST['amount'] ?? 0);
    $user_id = get_current_user_id();
    $min     = (float) mkt_get_system_option('min_deposit', 50);

    if ($amount < $min) wp_send_json_error(['message' => "Минимальное пополнение: {$min} ₽"]);

    $url = mkt_freekassa_payment_url($user_id, $amount);
    wp_send_json_success(['url' => $url]);
}

add_action('init', function () {
    if ((string) ($_GET['mkt_action'] ?? '') !== 'freekassa_callback') return;

    $merchant_id = mkt_get_system_option('fk_merchant_id', '');
    $secret2     = mkt_get_system_option('fk_secret_word2', '');

    $amount    = (float) ($_POST['AMOUNT'] ?? ($_GET['AMOUNT'] ?? 0));
    $order_id  = sanitize_text_field($_POST['MERCHANT_ORDER_ID'] ?? ($_GET['MERCHANT_ORDER_ID'] ?? ''));
    $sign_recv = sanitize_text_field($_POST['SIGN'] ?? ($_GET['SIGN'] ?? ''));
    $user_id   = (int) ($_POST['us'] ?? ($_GET['us'] ?? 0));

    $sign_calc = md5("{$merchant_id}:{$amount}:{$secret2}:{$order_id}");
    if (!hash_equals($sign_calc, $sign_recv)) {
        http_response_code(400);
        echo 'NO';
        exit;
    }

    $meta_key = '_fk_pending_order_' . $order_id;
    $expected = get_user_meta($user_id, $meta_key, true);
    if (!$expected) {
        echo 'YES';
        exit;
    }

    delete_user_meta($user_id, $meta_key);
    mkt_add_balance($user_id, $amount);
    mkt_log('deposit', $user_id, 'Пополнение через FreeKassa', ['order_id' => $order_id, 'amount' => $amount]);

    echo 'YES';
    exit;
});

add_action('wp_ajax_mkt_save_card', 'mkt_ajax_save_card');
function mkt_ajax_save_card(): void {
    mkt_check_nonce();
    mkt_require_login();
    $card = sanitize_text_field($_POST['card'] ?? '');
    if (!$card) wp_send_json_error(['message' => 'Введите реквизиты.']);
    update_field('withdrawal_card', $card, 'user_' . get_current_user_id());
    wp_send_json_success(['message' => 'Реквизиты сохранены.']);
}
