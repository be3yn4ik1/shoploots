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
    $v   = '1.0.5';
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
        'catalogUrl'   => home_url('/catalog/'),
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

add_shortcode('mkt_search', function () {
    static $js_rendered = false;

    $popular_cats = get_terms([
        'taxonomy'   => 'group',
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
        'number'     => 6,
    ]);

    ob_start();
    ?>
    <div class="mkt-search-widget" id="mkt-search-widget">
        <div class="mkt-search-bar">
            <svg class="mkt-search-icon" viewBox="0 0 24 24" width="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="mkt-search-input" class="mkt-search-input" placeholder="Поиск товаров..." autocomplete="off">
        </div>
        <div class="mkt-search-dropdown" id="mkt-search-dropdown" style="display:none">
            <div class="mkt-search-section" id="mkt-search-recent-section" style="display:none">
                <div class="mkt-search-section-title">Вы искали</div>
                <div id="mkt-search-recent-list" class="mkt-search-recent-list"></div>
            </div>
            <div class="mkt-search-section" id="mkt-search-results-section" style="display:none">
                <div class="mkt-search-section-title">Товары</div>
                <div id="mkt-search-results" class="mkt-search-results"></div>
            </div>
            <?php if (!is_wp_error($popular_cats) && !empty($popular_cats)): ?>
            <div class="mkt-search-section" id="mkt-search-cats-section">
                <div class="mkt-search-section-title">Популярные категории</div>
                <div class="mkt-search-cats">
                    <?php foreach ($popular_cats as $cat): ?>
                    <a href="<?= esc_url(get_term_link($cat)) ?>" class="mkt-search-cat"><?= esc_html($cat->name) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$js_rendered): $js_rendered = true; ?>
    <script>
    (function () {
        var HIST_KEY = 'mkt_search_history';
        var widget, input, dropdown, recentSec, recentList, resultsSec, resultsDiv, catsSec;
        var timer, lastQ = '', open = false;

        function getHist() { try { return JSON.parse(localStorage.getItem(HIST_KEY) || '[]'); } catch (e) { return []; } }
        function addHist(q) {
            var h = getHist().filter(function (x) { return x !== q; });
            h.unshift(q); h = h.slice(0, 8);
            try { localStorage.setItem(HIST_KEY, JSON.stringify(h)); } catch (e) {}
        }

        function init() {
            widget     = document.getElementById('mkt-search-widget');
            input      = document.getElementById('mkt-search-input');
            dropdown   = document.getElementById('mkt-search-dropdown');
            recentSec  = document.getElementById('mkt-search-recent-section');
            recentList = document.getElementById('mkt-search-recent-list');
            resultsSec = document.getElementById('mkt-search-results-section');
            resultsDiv = document.getElementById('mkt-search-results');
            catsSec    = document.getElementById('mkt-search-cats-section');
            if (!input) return;
            input.addEventListener('focus', function () { showRecent(); openDrop(); });
            input.addEventListener('input', onInput);
            input.addEventListener('keydown', function (e) { if (e.key === 'Enter') doSearch(input.value.trim()); });
            document.addEventListener('click', function (e) { if (!widget.contains(e.target)) closeDrop(); });
        }

        function openDrop()  { dropdown.style.display = ''; open = true; }
        function closeDrop() { dropdown.style.display = 'none'; open = false; }

        function showRecent() {
            var h = getHist();
            if (!h.length) { recentSec.style.display = 'none'; return; }
            recentSec.style.display = '';
            recentList.innerHTML = '';
            h.forEach(function (q) {
                var a = document.createElement('a');
                a.href = '#'; a.className = 'mkt-search-recent-item'; a.textContent = q;
                a.addEventListener('click', function (e) { e.preventDefault(); input.value = q; doSearch(q); });
                recentList.appendChild(a);
            });
        }

        function onInput() {
            clearTimeout(timer);
            var q = input.value.trim();
            if (q.length < 3) {
                resultsSec.style.display = 'none';
                if (catsSec) catsSec.style.display = '';
                showRecent(); if (!open) openDrop(); return;
            }
            if (catsSec) catsSec.style.display = 'none';
            if (!open) openDrop();
            timer = setTimeout(function () { fetchResults(q); }, 300);
        }

        function fetchResults(q) {
            if (q === lastQ) return; lastQ = q;
            resultsSec.style.display = ''; resultsDiv.innerHTML = '<div class="mkt-search-loading">Поиск...</div>';
            var url = (typeof MP !== 'undefined' ? MP.rest : '/wp-json/marketplace/v1/') + 'products?search=' + encodeURIComponent(q) + '&page=1';
            fetch(url, { headers: { 'X-WP-Nonce': typeof MP !== 'undefined' ? MP.nonce : '' }, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (d) { if (d.items) renderResults(d.items.slice(0, 20)); })
                .catch(function () {});
        }

        function renderResults(items) {
            resultsDiv.innerHTML = '';
            if (!items.length) { resultsDiv.innerHTML = '<div class="mkt-search-empty">Ничего не найдено</div>'; return; }
            items.forEach(function (p) {
                var a = document.createElement('a'); a.href = p.url; a.className = 'mkt-search-result-item';
                var thumb = p.thumbnail ? '<img src="' + p.thumbnail + '" alt="" class="mkt-sr-thumb">' : '<div class="mkt-sr-thumb mkt-sr-thumb-placeholder">📦</div>';
                var title = String(p.title || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                a.innerHTML = thumb + '<span class="mkt-sr-title">' + title + '</span><span class="mkt-sr-price">' + parseFloat(p.price || 0).toFixed(0) + ' ₽</span>';
                resultsDiv.appendChild(a);
            });
        }

        function doSearch(q) {
            if (!q) return;
            addHist(q);
            window.location.href = (typeof MP !== 'undefined' && MP.catalogUrl ? MP.catalogUrl : '/catalog/') + '?s=' + encodeURIComponent(q);
        }

        if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
    })();
    </script>
    <?php endif; ?>
    <?php
    return ob_get_clean();
});
