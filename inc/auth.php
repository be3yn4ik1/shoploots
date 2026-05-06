<?php
defined('ABSPATH') || exit;

function mkt_verify_recaptcha(string $token): bool {
    $secret = mkt_get_system_option('recaptcha_secret_key', '');
    if (!$secret) return true;
    $res = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
        'body' => ['secret' => $secret, 'response' => $token],
    ]);
    if (is_wp_error($res)) return false;
    $data = json_decode(wp_remote_retrieve_body($res), true);
    return !empty($data['success']);
}

add_action('wp_ajax_nopriv_mkt_register', 'mkt_ajax_register');
function mkt_ajax_register(): void {
    check_ajax_referer('marketplace_nonce', 'nonce');

    $email    = sanitize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $invite   = strtoupper(sanitize_text_field($_POST['invite'] ?? ''));
    $captcha  = sanitize_text_field($_POST['captcha'] ?? '');
    $name     = sanitize_text_field($_POST['name'] ?? '');

    if (!$email || !is_email($email)) {
        wp_send_json_error(['message' => 'Введите корректный email.']);
    }
    if (strlen($password) < 6) {
        wp_send_json_error(['message' => 'Пароль должен быть не менее 6 символов.']);
    }
    if (!mkt_verify_recaptcha($captcha)) {
        wp_send_json_error(['message' => 'Пройдите проверку reCAPTCHA.']);
    }

    $invite_required = mkt_get_system_option('invite_required', true);
    $referrer_id = null;
    if ($invite) {
        $referrer_id = mkt_find_user_by_ref_code($invite);
        if (!$referrer_id) {
            wp_send_json_error(['message' => 'Недействительный инвайт-код.']);
        }
    } elseif ($invite_required) {
        wp_send_json_error(['message' => 'Инвайт-код обязателен для регистрации.']);
    }

    if (email_exists($email)) {
        wp_send_json_error(['message' => 'Этот email уже зарегистрирован.']);
    }

    $user_id = wp_create_user($email, $password, $email);
    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => $user_id->get_error_message()]);
    }

    $u = new WP_User($user_id);
    $u->set_role('buyer');

    if ($name) {
        wp_update_user(['ID' => $user_id, 'display_name' => $name]);
    }

    update_field('marketplace_role', 'buyer', "user_{$user_id}");
    update_field('balance', 0, "user_{$user_id}");
    update_field('hold_balance', 0, "user_{$user_id}");

    $ref_code = mkt_generate_ref_code();
    update_field('ref_code', $ref_code, "user_{$user_id}");

    if ($referrer_id) {
        update_field('referred_by_id', $referrer_id, "user_{$user_id}");
        update_user_meta($user_id, '_invite_code_used', $invite);
    }

    $bonus = (float) mkt_get_system_option('registration_bonus', 0);
    if ($bonus > 0) mkt_add_balance($user_id, $bonus);

    wp_set_auth_cookie($user_id, true);
    wp_send_json_success(['redirect' => home_url('/dashboard/')]);
}

add_action('wp_ajax_nopriv_mkt_login', 'mkt_ajax_login');
function mkt_ajax_login(): void {
    check_ajax_referer('marketplace_nonce', 'nonce');

    $email    = sanitize_email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        wp_send_json_error(['message' => 'Заполните все поля.']);
    }

    $ip          = preg_replace('/[^a-f0-9:.]/i', '', $_SERVER['REMOTE_ADDR'] ?? '');
    $attempt_key = 'mkt_lf_' . md5($ip . '|' . $email);
    $attempts    = (int) get_transient($attempt_key);
    if ($attempts >= 4) {
        wp_send_json_error(['message' => 'Слишком много попыток входа. Подождите 15 минут.']);
    }

    $user = get_user_by('email', $email);
    if (!$user || !wp_check_password($password, $user->user_pass, $user->ID)) {
        set_transient($attempt_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
        wp_send_json_error(['message' => 'Неверный email или пароль.']);
    }

    if (get_field('is_banned', 'user_' . $user->ID)) {
        $reason = get_field('ban_reason', 'user_' . $user->ID) ?: '';
        wp_send_json_error(['message' => 'Аккаунт заблокирован.' . ($reason ? ' ' . esc_html($reason) : '')]);
    }

    delete_transient($attempt_key);
    wp_set_auth_cookie($user->ID, true);
    wp_send_json_success(['redirect' => home_url('/dashboard/')]);
}

add_action('wp_ajax_mkt_logout', 'mkt_ajax_logout');
function mkt_ajax_logout(): void {
    check_ajax_referer('marketplace_nonce', 'nonce');
    wp_logout();
    wp_send_json_success(['redirect' => home_url('/auth/')]);
}

add_action('wp_ajax_mkt_update_profile', 'mkt_ajax_update_profile');
function mkt_ajax_update_profile(): void {
    mkt_check_nonce();
    mkt_require_login();

    $user_id  = get_current_user_id();
    $name     = sanitize_text_field($_POST['name'] ?? '');
    $email    = sanitize_email($_POST['email'] ?? '');
    $pass     = $_POST['password'] ?? '';
    $telegram = sanitize_text_field($_POST['telegram'] ?? '');

    $update = ['ID' => $user_id];
    if ($name) $update['display_name'] = $name;

    if ($email && $email !== wp_get_current_user()->user_email) {
        if (!is_email($email)) wp_send_json_error(['message' => 'Некорректный email.']);
        if (email_exists($email)) wp_send_json_error(['message' => 'Email уже используется.']);
        $update['user_email'] = $email;
    }

    if ($pass) {
        if (strlen($pass) < 6) wp_send_json_error(['message' => 'Пароль не менее 6 символов.']);
        $update['user_pass'] = $pass;
    }

    $r = wp_update_user($update);
    if (is_wp_error($r)) wp_send_json_error(['message' => $r->get_error_message()]);

    if (!empty($_FILES['avatar']['tmp_name'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $att = media_handle_upload('avatar', 0);
        if (!is_wp_error($att)) {
            update_field('user_avatar', $att, "user_{$user_id}");
        }
    }

    if ($telegram !== '') {
        update_field('telegram', ltrim($telegram, '@'), "user_{$user_id}");
    }

    if ($pass) {
        wp_set_auth_cookie($user_id, true);
    }

    wp_send_json_success(['message' => 'Профиль обновлён.']);
}
