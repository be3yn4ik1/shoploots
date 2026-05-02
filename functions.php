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
    if (!empty($_GET['id'])) {
        $uri_path = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        if (preg_match('#/orders/?$#', $uri_path)) {
            $t = get_template_directory() . '/page-order.php';
            if (file_exists($t)) return $t;
        }
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

add_action('admin_menu', function () {
    global $menu;
    if (!current_user_can('manage_options')) return;
    $count = (int) (new WP_Query([
        'post_type'      => 'orders',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => [['key' => 'order_status', 'value' => 'arbitration']],
    ]))->found_posts;
    if (!$count) return;
    foreach ($menu as $key => $item) {
        if (isset($item[5]) && $item[5] === 'menu-posts-orders') {
            $menu[$key][0] .= ' <span class="awaiting-mod count-' . $count . '"><span class="pending-count">' . $count . '</span></span>';
            break;
        }
    }
});

add_filter('post_class', function ($classes, $class, $post_id) {
    if (get_post_type($post_id) !== 'orders') return $classes;
    if (get_field('order_status', $post_id) === 'arbitration') $classes[] = 'order-arb-row';
    return $classes;
}, 10, 3);

add_action('admin_head', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->id !== 'edit-orders') return;
    echo '<style>.order-arb-row .row-title,.order-arb-row .column-title strong a{color:#e64646!important;font-weight:700!important}</style>';
});

add_filter('post_row_actions', function ($actions, $post) {
    if ($post->post_type !== 'orders') return $actions;
    $actions['open_order'] = '<a href="' . esc_url(home_url('/orders/?id=' . $post->ID)) . '" target="_blank" style="color:#0077ff;font-weight:600">Открыть заказ ↗</a>';
    return $actions;
}, 10, 2);
