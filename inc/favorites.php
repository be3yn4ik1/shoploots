<?php
defined('ABSPATH') || exit;

function mkt_get_favorites(int $user_id): array {
    $raw     = get_user_meta($user_id, 'mkt_favorites', true);
    $decoded = $raw ? json_decode($raw, true) : [];
    return is_array($decoded) ? array_map('intval', $decoded) : [];
}

add_action('wp_ajax_mkt_toggle_favorite', 'mkt_ajax_toggle_favorite');
function mkt_ajax_toggle_favorite(): void {
    mkt_check_nonce();
    mkt_require_login();

    $user_id    = get_current_user_id();
    $product_id = (int) ($_POST['product_id'] ?? 0);

    if (!$product_id || get_post_type($product_id) !== 'products') {
        wp_send_json_error(['message' => 'Товар не найден.']);
    }

    $favs = mkt_get_favorites($user_id);
    if (in_array($product_id, $favs, true)) {
        $favs   = array_values(array_filter($favs, fn($id) => $id !== $product_id));
        $active = false;
    } else {
        $favs[] = $product_id;
        $active = true;
    }
    update_user_meta($user_id, 'mkt_favorites', wp_json_encode($favs));
    wp_send_json_success(['active' => $active]);
}

add_action('rest_api_init', function () {
    register_rest_route('marketplace/v1', '/favorites', [
        'methods'             => 'GET',
        'callback'            => 'mkt_rest_get_favorites',
        'permission_callback' => 'is_user_logged_in',
    ]);
});

function mkt_rest_get_favorites(WP_REST_Request $req): WP_REST_Response {
    $user_id = get_current_user_id();
    $ids     = mkt_get_favorites($user_id);
    $items   = [];
    foreach ($ids as $id) {
        if (get_post_status($id) === 'publish' && get_post_type($id) === 'products') {
            $items[] = mkt_format_product($id);
        }
    }
    return new WP_REST_Response(['items' => $items], 200);
}
