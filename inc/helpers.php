<?php
defined('ABSPATH') || exit;

function mkt_get_balance(int $user_id): float {
    return (float) (get_field('balance', "user_{$user_id}") ?: 0);
}

function mkt_get_hold(int $user_id): float {
    return (float) (get_field('hold_balance', "user_{$user_id}") ?: 0);
}

function mkt_add_balance(int $user_id, float $amount): bool {
    if ($amount <= 0) return false;
    $cur = mkt_get_balance($user_id);
    update_field('balance', round($cur + $amount, 2), "user_{$user_id}");
    return true;
}

function mkt_subtract_balance(int $user_id, float $amount): bool {
    if ($amount <= 0) return false;
    $cur = mkt_get_balance($user_id);
    if ($cur < $amount) return false;
    update_field('balance', round($cur - $amount, 2), "user_{$user_id}");
    return true;
}

function mkt_add_hold(int $user_id, float $amount): void {
    $cur = mkt_get_hold($user_id);
    update_field('hold_balance', round($cur + $amount, 2), "user_{$user_id}");
}

function mkt_subtract_hold(int $user_id, float $amount): void {
    $cur = mkt_get_hold($user_id);
    update_field('hold_balance', max(0, round($cur - $amount, 2)), "user_{$user_id}");
}

function mkt_check_nonce(string $action = 'marketplace_nonce'): void {
    if (!check_ajax_referer($action, 'nonce', false)) {
        wp_send_json_error(['message' => 'Ошибка безопасности. Обновите страницу.'], 403);
    }
}

function mkt_require_login(): void {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Необходима авторизация.'], 401);
    }
}

function mkt_generate_ref_code(): string {
    do {
        $code = strtoupper(wp_generate_password(10, false));
        $exists = get_users(['meta_key' => 'ref_code', 'meta_value' => $code, 'number' => 1, 'fields' => 'ids']);
    } while (!empty($exists));
    return $code;
}

function mkt_find_user_by_ref_code(string $code): ?int {
    $users = get_users([
        'meta_key'   => 'ref_code',
        'meta_value' => strtoupper($code),
        'number'     => 1,
        'fields'     => 'ids',
    ]);
    return !empty($users) ? (int) $users[0] : null;
}

function mkt_get_avatar_url(int $user_id): string {
    $img_id = get_field('user_avatar', "user_{$user_id}");
    if ($img_id) {
        $src = wp_get_attachment_image_url($img_id, 'thumbnail');
        if ($src) return esc_url($src);
    }
    return esc_url(get_avatar_url($user_id, ['size' => 80]));
}

function mkt_get_system_option(string $key, $default = null) {
    $val = get_field($key, 'option');
    return ($val !== null && $val !== '') ? $val : $default;
}

function mkt_commission_rate(): float {
    // 12% hardcoded: 10% seller chain + 1% buyer inviter + 1% platform profit
    return 0.12;
}

function mkt_format_price(float $amount): string {
    return number_format($amount, 2, '.', ' ') . ' ₽';
}

function mkt_get_product_rating(int $product_id): array {
    global $wpdb;
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) as cnt, ROUND(AVG(CAST(pm3.meta_value AS DECIMAL(3,1))), 1) as avg_rating
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} pm1 ON pm1.post_id = p.ID AND pm1.meta_key = 'offer_id' AND pm1.meta_value = %s
         JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = p.ID AND pm2.meta_key = 'tekst_otzyva' AND pm2.meta_value != ''
         JOIN {$wpdb->postmeta} pm3 ON pm3.post_id = p.ID AND pm3.meta_key = 'oczenka'
         WHERE p.post_type = 'orders' AND p.post_status = 'publish'",
        (string) $product_id
    ));
    $count = (int) ($result->cnt ?? 0);
    return ['count' => $count, 'avg' => $count > 0 ? (float) $result->avg_rating : 0.0];
}

function mkt_get_seller_rating(int $seller_id): array {
    global $wpdb;
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) as cnt, ROUND(AVG(CAST(pm3.meta_value AS DECIMAL(3,1))), 1) as avg_rating
         FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} pm1 ON pm1.post_id = p.ID AND pm1.meta_key = 'seller_id' AND pm1.meta_value = %s
         JOIN {$wpdb->postmeta} pm2 ON pm2.post_id = p.ID AND pm2.meta_key = 'tekst_otzyva' AND pm2.meta_value != ''
         JOIN {$wpdb->postmeta} pm3 ON pm3.post_id = p.ID AND pm3.meta_key = 'oczenka'
         WHERE p.post_type = 'orders' AND p.post_status = 'publish'",
        (string) $seller_id
    ));
    $count = (int) ($result->cnt ?? 0);
    return ['count' => $count, 'avg' => $count > 0 ? (float) $result->avg_rating : 0.0];
}

function mkt_stars_html(float $avg, int $count): string {
    if (!$count) return '';
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $color = $i <= round($avg) ? '#0077ff' : '#d1d5db';
        $stars .= '<span style="color:' . $color . '">★</span>';
    }
    return '<span class="star-display">' . $stars . ' <span class="star-display-meta">' . number_format($avg, 1) . ' (' . $count . ')</span></span>';
}

function mkt_is_online(int $user_id): bool {
    $last = (int) get_user_meta($user_id, 'last_seen', true);
    return $last && (time() - $last) < 5 * MINUTE_IN_SECONDS;
}

function mkt_last_seen_label(int $user_id): string {
    $last = (int) get_user_meta($user_id, 'last_seen', true);
    if (!$last) return '';
    $diff = time() - $last;
    if ($diff < 300)   return 'Онлайн';
    if ($diff < 3600)  return 'Был(а) ' . round($diff / 60) . ' мин. назад';
    if ($diff < 86400) return 'Был(а) ' . round($diff / 3600) . ' ч. назад';
    return 'Был(а) ' . round($diff / 86400) . ' д. назад';
}

function mkt_log(string $type, int $user_id, string $message, array $data = []): void {
    global $wpdb;
    $wpdb->insert("{$wpdb->prefix}mkt_logs", [
        'type'       => $type,
        'user_id'    => $user_id,
        'message'    => $message,
        'data'       => wp_json_encode($data),
        'created_at' => current_time('mysql'),
    ]);
}

function mkt_create_logs_table(): void {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mkt_logs (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        type varchar(50) NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
        message text NOT NULL,
        data text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY type (type)
    ) {$charset};");
}

add_action('after_switch_theme', 'mkt_create_logs_table');

// Создать таблицу если её нет (например, тема была активна до добавления этого кода)
add_action('init', function () {
    if (get_option('mkt_logs_table_v1')) return;
    mkt_create_logs_table();
    update_option('mkt_logs_table_v1', '1');
});
