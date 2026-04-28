<?php
defined('ABSPATH') || exit;

foreach (['roles', 'helpers', 'auth', 'api', 'escrow', 'chat', 'referrals', 'payments'] as $_f) {
    require_once get_template_directory() . "/inc/{$_f}.php";
}

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
});

add_action('after_setup_theme', function () {
    if (!current_user_can('administrator')) show_admin_bar(false);
});

add_action('after_switch_theme', function () {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mkt_chat (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id bigint(20) UNSIGNED NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        message text NOT NULL,
        is_system tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id)
    ) $charset;");
    flush_rewrite_rules();
});

add_action('init', function () {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, 'wp-login.php') !== false && !defined('DOING_AJAX')) {
        wp_redirect(home_url('/auth/'));
        exit;
    }
});

add_filter('login_url', function () {
    return home_url('/auth/');
}, 10, 0);

if (function_exists('acf_add_options_page')) {
    acf_add_options_page([
        'page_title' => 'Настройки системы',
        'menu_title' => 'Настройки маркетплейса',
        'menu_slug'  => 'options-system',
        'capability' => 'manage_options',
        'icon_url'   => 'dashicons-admin-settings',
        'redirect'   => false,
    ]);
}

add_action('wp_enqueue_scripts', function () {
    $v   = '1.0.4';
    $css = get_template_directory_uri() . '/assets/css/';
    $js  = get_template_directory_uri() . '/assets/js/';

    wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap', [], null);
    wp_enqueue_style('mkt-main',      $css . 'main.css',      [], $v);
    wp_enqueue_style('mkt-modals',    $css . 'modals.css',    ['mkt-main'], $v);
    wp_enqueue_style('mkt-auth',      $css . 'auth.css',      ['mkt-main'], $v);
    wp_enqueue_style('mkt-dashboard', $css . 'dashboard.css', ['mkt-main'], $v);

    wp_enqueue_script('mkt-modals',  $js . 'modals.js',  [],            $v, true);
    wp_enqueue_script('mkt-main',    $js . 'main.js',    ['mkt-modals'],$v, true);
    wp_enqueue_script('mkt-auth',    $js . 'auth.js',    ['mkt-main'],  $v, true);
    wp_enqueue_script('mkt-catalog', $js . 'catalog.js', ['mkt-main'],  $v, true);
    wp_enqueue_script('mkt-chat',    $js . 'chat.js',    ['mkt-main'],  $v, true);

    if (is_singular('products')) {
        wp_enqueue_script('mkt-product', $js . 'product.js', ['mkt-main'], $v, true);
    }

    $rc = get_field('recaptcha_site_key', 'option');
    if ($rc) wp_enqueue_script('recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, true);

    wp_localize_script('mkt-main', 'MP', [
        'ajax'         => admin_url('admin-ajax.php'),
        'rest'         => rest_url('marketplace/v1/'),
        'nonce'        => wp_create_nonce('wp_rest'),
        'ajaxNonce'    => wp_create_nonce('marketplace_nonce'),
        'userId'       => get_current_user_id(),
        'loggedIn'     => is_user_logged_in(),
        'recaptchaKey' => $rc ?: '',
        'authUrl'      => home_url('/auth/'),
        'dashUrl'      => home_url('/dashboard/'),
    ]);
});

add_filter('template_include', function ($tpl) {
    if (is_singular('products')) {
        $t = get_template_directory() . '/single-products.php';
        if (file_exists($t)) return $t;
    }
    return $tpl;
});

add_action('acf/save_post', function ($post_id) {
    if (get_post_type($post_id) !== 'orders') return;
    $decision = get_field('arb_decision', $post_id);
    if (!$decision || $decision === 'none') return;
    $status = get_field('order_status', $post_id);
    if ($status !== 'arbitration') return;

    if ($decision === 'refund_buyer') {
        mkt_arbitration_refund($post_id);
    } elseif ($decision === 'release_seller') {
        mkt_arbitration_release($post_id);
    }
}, 20);

add_action('acf/save_post', function ($post_id) {
    if (get_post_type($post_id) !== 'payout') return;
    $status = get_field('payout_status', $post_id);
    if ($status === 'completed') {
        mkt_process_payout_completion($post_id);
    }
}, 20);
