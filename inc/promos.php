<?php
defined('ABSPATH') || exit;

add_action('init', function () {
    register_post_type('promo_code', [
        'labels'       => [
            'name'          => 'Промокоды',
            'singular_name' => 'Промокод',
            'add_new_item'  => 'Добавить промокод',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'supports'     => ['title'],
        'menu_icon'    => 'dashicons-tickets-alt',
    ]);
});

add_action('wp_ajax_mkt_apply_promo', 'mkt_ajax_apply_promo');
function mkt_ajax_apply_promo(): void {
    mkt_check_nonce();
    mkt_require_login();

    $user_id = get_current_user_id();
    $code    = strtoupper(sanitize_text_field($_POST['code'] ?? ''));

    if (!$code) wp_send_json_error(['message' => 'Введите промокод.']);

    // Rate limit: 5 попыток в минуту
    $rate_key = 'mkt_promo_rate_' . $user_id;
    $attempts = (int) get_transient($rate_key);
    if ($attempts >= 5) wp_send_json_error(['message' => 'Слишком много попыток. Подождите минуту.']);
    set_transient($rate_key, $attempts + 1, MINUTE_IN_SECONDS);

    $promos = get_posts([
        'post_type'              => 'promo_code',
        'post_status'            => 'publish',
        'title'                  => $code,
        'numberposts'            => 1,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ]);

    if (empty($promos)) wp_send_json_error(['message' => 'Промокод не найден.']);

    $promo_id = $promos[0]->ID;
    $amount   = (float) get_field('promo_amount',     $promo_id);
    $limit    = (int)   get_field('promo_limit',      $promo_id);
    $used     = (int)   get_field('promo_used_count', $promo_id);

    if ($amount <= 0) wp_send_json_error(['message' => 'Промокод недействителен.']);
    if ($limit > 0 && $used >= $limit) wp_send_json_error(['message' => 'Лимит промокода исчерпан.']);

    // Проверка: один юзер — один раз
    $used_by_raw = get_post_meta($promo_id, '_promo_used_by', true) ?: '';
    $used_by_arr = $used_by_raw ? explode(',', $used_by_raw) : [];
    if (in_array((string) $user_id, $used_by_arr, true)) {
        wp_send_json_error(['message' => 'Вы уже использовали этот промокод.']);
    }

    update_field('promo_used_count', $used + 1, $promo_id);
    $used_by_arr[] = (string) $user_id;
    update_post_meta($promo_id, '_promo_used_by', implode(',', $used_by_arr));

    mkt_add_balance($user_id, $amount);
    mkt_log('promo_applied', $user_id, 'Промокод: ' . $code, ['amount' => $amount, 'promo_id' => $promo_id]);

    wp_send_json_success([
        'message' => 'Промокод применён! Начислено: ' . mkt_format_price($amount),
        'amount'  => $amount,
    ]);
}
