<?php
defined('ABSPATH') || exit;

/* ============================================================
 *  FreeKassa SCI — приём платежей (пополнение баланса)
 * ============================================================ */

function mkt_freekassa_payment_url(int $user_id, float $amount, string $currency = 'RUB'): string {
    $merchant_id = mkt_get_system_option('fk_merchant_id', '');
    $secret1     = mkt_get_system_option('fk_secret_word', '');
    $order_id    = 'dep_' . $user_id . '_' . time();

    update_user_meta($user_id, '_fk_pending_order_' . $order_id, $amount);

    $sign = md5("{$merchant_id}:{$amount}:{$secret1}:{$currency}:{$order_id}");

    return add_query_arg([
        'm'        => $merchant_id,
        'oa'       => $amount,
        'o'        => $order_id,
        's'        => $sign,
        'currency' => $currency,
        'em'       => get_userdata($user_id)->user_email,
        'lang'     => 'ru',
    ], 'https://pay.fk.money/');
}

add_action('wp_ajax_mkt_get_deposit_url', 'mkt_ajax_get_deposit_url');
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

    $sign_calc = md5("{$merchant_id}:{$amount}:{$secret2}:{$order_id}");
    if (!hash_equals(strtolower($sign_calc), strtolower($sign_recv))) {
        http_response_code(400);
        echo 'NO';
        exit;
    }

    $user_id = 0;
    if (preg_match('/^dep_(\d+)_/', $order_id, $m)) {
        $user_id = (int) $m[1];
    }
    if (!$user_id) { echo 'YES'; exit; }

    $done_key = '_fk_done_' . $order_id;
    if (!add_user_meta($user_id, $done_key, 1, true)) {
        echo 'YES';
        exit;
    }

    $meta_key = '_fk_pending_order_' . $order_id;
    $expected = get_user_meta($user_id, $meta_key, true);
    if (!$expected) { echo 'YES'; exit; }

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

/* ============================================================
 *  FK Wallet API — автоматические выплаты
 *  Документация: https://fkwallet.io/api-docs
 * ============================================================ */

function mkt_fkwallet_api_call(string $endpoint, array $data = []): array {
    $public_key  = (string) mkt_get_system_option('fkw_public_key', '');
    $private_key = (string) mkt_get_system_option('fkw_private_key', '');
    if (!$public_key || !$private_key) {
        return ['ok' => false, 'error' => 'FK Wallet API не настроен'];
    }

    $url  = 'https://api.fkwallet.io/v1/' . $public_key . '/' . ltrim($endpoint, '/');
    $body = $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : '';
    $sign = $body ? hash('sha256', $body . $private_key) : hash('sha256', $private_key);

    $args = [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $sign,
        ],
        'timeout' => 20,
    ];

    if ($body) {
        $args['method'] = 'POST';
        $args['body']   = $body;
        $resp = wp_remote_post($url, $args);
    } else {
        $resp = wp_remote_get($url, $args);
    }

    if (is_wp_error($resp)) {
        return ['ok' => false, 'error' => $resp->get_error_message()];
    }

    $code  = wp_remote_retrieve_response_code($resp);
    $raw   = wp_remote_retrieve_body($resp);
    $json  = json_decode($raw, true);
    $inner = $json['data'] ?? $json;

    if ($code !== 200 || ($inner['status'] ?? '') !== 'ok') {
        $msg = $inner['message'] ?? $inner['description'] ?? "HTTP {$code}";
        return ['ok' => false, 'error' => $msg, 'raw' => $raw];
    }

    return ['ok' => true, 'data' => $inner['data'] ?? []];
}

function mkt_fkwallet_create_withdrawal(int $payout_id): array {
    $amount  = (float) get_field('payout_amount', $payout_id);
    $account = (string) get_field('payout_method', $payout_id);
    $ps_id   = 6; // RU_CARD
    $cur_id  = 1; // RUB

    $payload = [
        'amount'            => $amount,
        'currency_id'       => $cur_id,
        'payment_system_id' => $ps_id,
        'fee_from_balance'  => 1,
        'account'           => $account,
        'description'       => 'Выплата #' . $payout_id,
        'order_id'          => $payout_id,
        'idempotence_key'   => 'payout_' . $payout_id,
    ];

    return mkt_fkwallet_api_call('withdrawal', $payload);
}

function mkt_fkwallet_send_payout(int $payout_id): void {
    if (get_post_meta($payout_id, '_fkw_sent', true)) return;
    update_post_meta($payout_id, '_fkw_sent', 1);

    $result = mkt_fkwallet_create_withdrawal($payout_id);

    if (!$result['ok']) {
        update_field('payout_status', 'pending', $payout_id);
        delete_post_meta($payout_id, '_fkw_sent');
        $error = $result['error'] ?? 'неизвестная ошибка';
        set_transient('mkt_fkw_admin_notice_' . get_current_user_id(),
            'FK Wallet: ошибка отправки выплаты #' . $payout_id . ' — ' . $error, 120);
        mkt_log('payout_api_error', 0, 'FK Wallet API ошибка', [
            'payout_id' => $payout_id,
            'error'     => $error,
        ]);
        return;
    }

    $fkw_id = $result['data']['id'] ?? '';
    update_post_meta($payout_id, '_fkw_withdrawal_id', $fkw_id);
    mkt_log('payout_sent', 0, 'Выплата отправлена в FK Wallet', [
        'payout_id' => $payout_id,
        'fkw_id'    => $fkw_id,
    ]);
}

function mkt_handle_payout_rejection(int $payout_id): void {
    if (get_post_meta($payout_id, '_payout_refunded', true)) return;
    $user_id = (int) get_post_meta($payout_id, '_payout_user_id', true);
    $amount  = (float) get_field('payout_amount', $payout_id);
    if (!$user_id || !$amount) return;
    update_post_meta($payout_id, '_payout_refunded', 1);
    mkt_add_balance($user_id, $amount);
    mkt_log('payout_rejected', $user_id, 'Выплата отклонена, средства возвращены', [
        'payout_id' => $payout_id,
        'amount'    => $amount,
    ]);
}

add_action('init', function () {
    if ((string) ($_GET['mkt_action'] ?? '') !== 'fkwallet_callback') return;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

    $private = (string) mkt_get_system_option('fkw_private_key', '');
    $request = $_POST;
    $sign_recv = sanitize_text_field($request['sign'] ?? '');
    unset($request['sign']);
    ksort($request);
    $sign_calc = hash('sha256', implode('|', $request) . $private);

    if (!$private || !hash_equals($sign_calc, $sign_recv)) {
        http_response_code(400);
        echo 'Wrong sign';
        exit;
    }

    $payout_id = (int) ($request['order_id'] ?? 0);
    $status    = (int) ($request['status'] ?? 0);

    if (!$payout_id || get_post_type($payout_id) !== 'payout') {
        echo 'OK';
        exit;
    }
    if (get_post_meta($payout_id, '_fkw_done', true)) {
        echo 'OK';
        exit;
    }

    if ($status === 1) {
        update_post_meta($payout_id, '_fkw_done', 1);
        update_field('payout_status', 'completed', $payout_id);
        mkt_process_payout_completion($payout_id);
    } elseif (in_array($status, [8, 9, 10], true)) {
        update_post_meta($payout_id, '_fkw_done', 1);
        mkt_handle_payout_rejection($payout_id);
        update_field('payout_status', 'rejected', $payout_id);
    }

    echo 'OK';
    exit;
});

add_action('admin_notices', function () {
    $key   = 'mkt_fkw_admin_notice_' . get_current_user_id();
    $msg   = get_transient($key);
    if (!$msg) return;
    delete_transient($key);
    echo '<div class="notice notice-error"><p>' . esc_html($msg) . '</p></div>';
});
