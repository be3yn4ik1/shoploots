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
    return (float) mkt_get_system_option('system_commission', 10) / 100;
}

function mkt_format_price(float $amount): string {
    return number_format($amount, 2, '.', ' ') . ' ₽';
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

add_action('after_switch_theme', function () {
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
    ) $charset;");
});
