<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
    register_rest_route('marketplace/v1', '/products', [
        'methods'             => 'GET',
        'callback'            => 'mkt_rest_get_products',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('marketplace/v1', '/my-products', [
        'methods'             => 'GET',
        'callback'            => 'mkt_rest_get_my_products',
        'permission_callback' => 'is_user_logged_in',
    ]);

    register_rest_route('marketplace/v1', '/product', [
        'methods'             => 'POST',
        'callback'            => 'mkt_rest_create_product',
        'permission_callback' => 'is_user_logged_in',
    ]);

    register_rest_route('marketplace/v1', '/product/(?P<id>\d+)', [
        [
            'methods'             => 'PUT',
            'callback'            => 'mkt_rest_update_product',
            'permission_callback' => 'is_user_logged_in',
        ],
        [
            'methods'             => 'DELETE',
            'callback'            => 'mkt_rest_delete_product',
            'permission_callback' => 'is_user_logged_in',
        ],
    ]);

    register_rest_route('marketplace/v1', '/orders', [
        'methods'             => 'GET',
        'callback'            => 'mkt_rest_get_orders',
        'permission_callback' => 'is_user_logged_in',
    ]);

    register_rest_route('marketplace/v1', '/order/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'mkt_rest_get_order',
        'permission_callback' => 'is_user_logged_in',
    ]);

    register_rest_route('marketplace/v1', '/reviews/(?P<product_id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'mkt_rest_get_reviews',
        'permission_callback' => '__return_true',
        'args'                => [
            'product_id' => ['required' => true, 'sanitize_callback' => 'absint'],
            'page'       => ['default' => 1, 'sanitize_callback' => 'absint'],
        ],
    ]);

    register_rest_route('marketplace/v1', '/seller/(?P<seller_id>\d+)/products', [
        'methods'             => 'GET',
        'callback'            => 'mkt_rest_get_seller_products',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('marketplace/v1', '/stats', [
        'methods'             => 'GET',
        'callback'            => 'mkt_rest_get_stats',
        'permission_callback' => 'is_user_logged_in',
    ]);
});

function mkt_rest_get_products(WP_REST_Request $req): WP_REST_Response {
    $category = sanitize_text_field($req->get_param('category') ?? '');
    $type     = sanitize_text_field($req->get_param('type') ?? '');
    $search   = sanitize_text_field($req->get_param('search') ?? '');
    $page     = max(1, (int) ($req->get_param('page') ?? 1));
    $per_page = 20;

    $args = [
        'post_type'      => 'products',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'meta_query'     => [['key' => 'is_active', 'value' => '1']],
    ];

    if ($search) $args['s'] = $search;
    $tax = [];
    if ($category) $tax[] = ['taxonomy' => 'group',      'field' => 'slug', 'terms' => $category];
    if ($type)     $tax[] = ['taxonomy' => 'type-group', 'field' => 'slug', 'terms' => $type];
    if ($tax)      $args['tax_query'] = array_merge([['relation' => 'AND']], $tax);

    $query = new WP_Query($args);
    $items = [];

    foreach ($query->posts as $post) {
        $items[] = mkt_format_product($post->ID);
    }

    return new WP_REST_Response([
        'items'       => $items,
        'total'       => $query->found_posts,
        'total_pages' => $query->max_num_pages,
        'page'        => $page,
    ], 200);
}

function mkt_format_product(int $product_id): array {
    $price_sale = (float) get_field('price_sell', $product_id);
    $price_base = (float) get_field('price', $product_id);
    $delivery   = get_field('delivery_type', $product_id);
    $seller_id  = (int) get_field('product_seller_id', $product_id);
    $keys_count = 0;
    if ($delivery === 'auto') {
        $data = get_field('secret_data', $product_id) ?? '';
        $keys_count = count(array_filter(array_map('trim', explode("\n", $data))));
    }
    $thumb = get_the_post_thumbnail_url($product_id, 'medium') ?: '';

    return [
        'id'           => $product_id,
        'title'        => get_the_title($product_id),
        'price'        => $price_sale > 0 ? $price_sale : $price_base,
        'price_base'   => $price_base,
        'price_sale'   => $price_sale,
        'delivery'     => $delivery,
        'keys_count'   => $keys_count,
        'description'  => esc_html(get_field('opisanie', $product_id) ?? ''),
        'how_to'       => nl2br(esc_html(get_field('sposob_polucheniya', $product_id) ?? '')),
        'seller_id'    => $seller_id,
        'seller_name'  => $seller_id ? get_userdata($seller_id)->display_name : '',
        'seller_avatar'=> $seller_id ? mkt_get_avatar_url($seller_id) : '',
        'thumbnail'    => esc_url($thumb),
        'url'          => get_permalink($product_id),
        'categories'   => wp_get_post_terms($product_id, 'group', ['fields' => 'names']),
        'types'        => wp_get_post_terms($product_id, 'type-group', ['fields' => 'names']),
        'in_stock'     => $delivery !== 'auto' || $keys_count > 0,
    ];
}

function mkt_rest_get_my_products(WP_REST_Request $req): WP_REST_Response {
    $user_id = get_current_user_id();
    $query   = new WP_Query([
        'post_type'   => 'products',
        'post_status' => ['publish', 'draft'],
        'meta_query'  => [['key' => 'product_seller_id', 'value' => $user_id]],
        'posts_per_page' => 50,
    ]);
    $items = [];
    foreach ($query->posts as $post) {
        $item          = mkt_format_product($post->ID);
        $item['status'] = $post->post_status;
        $items[]       = $item;
    }
    return new WP_REST_Response(['items' => $items], 200);
}

function mkt_rest_create_product(WP_REST_Request $req): WP_REST_Response {
    $user_id = get_current_user_id();
    if (!mkt_is_seller($user_id)) {
        return new WP_REST_Response(['error' => 'Только продавцы могут создавать товары.'], 403);
    }

    $title    = sanitize_text_field($req->get_param('title') ?? '');
    $price    = (float) ($req->get_param('price') ?? 0);
    $delivery = sanitize_text_field($req->get_param('delivery') ?? 'manual');

    if (!$title || $price <= 0) {
        return new WP_REST_Response(['error' => 'Укажите название и цену.'], 400);
    }
    if (!in_array($delivery, ['auto', 'manual'])) {
        return new WP_REST_Response(['error' => 'Некорректный тип выдачи.'], 400);
    }

    $post_id = wp_insert_post([
        'post_type'   => 'products',
        'post_status' => 'publish',
        'post_title'  => $title,
        'post_author' => $user_id,
    ]);

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(['error' => $post_id->get_error_message()], 500);
    }

    update_field('price',              $price,    $post_id);
    update_field('price_sell',         (float) ($req->get_param('price_sale') ?? 0), $post_id);
    update_field('delivery_type',      $delivery, $post_id);
    update_field('opisanie',           sanitize_textarea_field($req->get_param('description') ?? ''), $post_id);
    update_field('sposob_polucheniya', sanitize_textarea_field($req->get_param('how_to') ?? ''), $post_id);
    update_field('product_seller_id',  $user_id,  $post_id);
    update_field('is_active',          1,         $post_id);

    if ($delivery === 'auto') {
        $keys = sanitize_textarea_field($req->get_param('keys') ?? '');
        update_field('secret_data', $keys, $post_id);
    }

    $category = (int) ($req->get_param('category') ?? 0);
    $type     = (int) ($req->get_param('type') ?? 0);
    if ($category) wp_set_post_terms($post_id, [$category], 'group');
    if ($type)     wp_set_post_terms($post_id, [$type], 'type-group');

    return new WP_REST_Response(['id' => $post_id, 'message' => 'Товар создан.'], 201);
}

function mkt_rest_update_product(WP_REST_Request $req): WP_REST_Response {
    $user_id    = get_current_user_id();
    $product_id = (int) $req->get_param('id');
    $seller_id  = (int) get_field('product_seller_id', $product_id);

    if ($seller_id !== $user_id && !mkt_is_admin($user_id)) {
        return new WP_REST_Response(['error' => 'Нет доступа.'], 403);
    }

    if ($req->get_param('title')) {
        wp_update_post(['ID' => $product_id, 'post_title' => sanitize_text_field($req->get_param('title'))]);
    }

    $fields = ['price' => 'price', 'price_sale' => 'price_sell', 'description' => 'opisanie', 'how_to' => 'sposob_polucheniya'];
    foreach ($fields as $param => $acf) {
        $val = $req->get_param($param);
        if ($val !== null) update_field($acf, sanitize_textarea_field((string)$val), $product_id);
    }

    if ($req->get_param('keys') !== null) {
        update_field('secret_data', sanitize_textarea_field($req->get_param('keys')), $product_id);
    }

    if ($req->get_param('is_active') !== null) {
        update_field('is_active', (int) $req->get_param('is_active'), $product_id);
    }

    $status = $req->get_param('status');
    if ($status && in_array($status, ['publish', 'draft'])) {
        wp_update_post(['ID' => $product_id, 'post_status' => $status]);
    }

    return new WP_REST_Response(['message' => 'Товар обновлён.'], 200);
}

function mkt_rest_delete_product(WP_REST_Request $req): WP_REST_Response {
    $user_id    = get_current_user_id();
    $product_id = (int) $req->get_param('id');
    $seller_id  = (int) get_field('product_seller_id', $product_id);

    if ($seller_id !== $user_id && !mkt_is_admin($user_id)) {
        return new WP_REST_Response(['error' => 'Нет доступа.'], 403);
    }

    $active_orders = new WP_Query([
        'post_type'   => 'orders',
        'post_status' => 'publish',
        'meta_query'  => [
            ['key' => 'offer_id',     'value' => $product_id],
            ['key' => 'order_status', 'value' => ['in_progress', 'arbitration'], 'compare' => 'IN'],
        ],
        'posts_per_page' => 1,
    ]);

    if ($active_orders->have_posts()) {
        return new WP_REST_Response(['error' => 'Нельзя удалить товар с активными сделками.'], 400);
    }

    wp_trash_post($product_id);
    return new WP_REST_Response(['message' => 'Товар удалён.'], 200);
}

function mkt_rest_get_orders(WP_REST_Request $req): WP_REST_Response {
    $user_id = get_current_user_id();
    $role    = mkt_get_role($user_id);

    $meta_key = $role === 'seller' ? 'seller_id' : 'buyer_id';
    $query    = new WP_Query([
        'post_type'      => 'orders',
        'post_status'    => 'publish',
        'meta_query'     => [['key' => $meta_key, 'value' => $user_id]],
        'posts_per_page' => 20,
        'paged'          => max(1, (int) ($req->get_param('page') ?? 1)),
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    $items = [];
    foreach ($query->posts as $post) {
        $items[] = mkt_format_order($post->ID);
    }

    return new WP_REST_Response(['items' => $items, 'total' => $query->found_posts], 200);
}

function mkt_format_order(int $order_id): array {
    $product_id = (int) get_field('offer_id',     $order_id);
    $buyer_id   = (int) get_field('buyer_id',     $order_id);
    $seller_id  = (int) get_field('seller_id',    $order_id);
    $status     = get_field('order_status',       $order_id);
    $amount     = (float) get_field('order_amount', $order_id);

    $delivered = '';
    $cur_user  = get_current_user_id();
    if ($cur_user === $buyer_id && in_array($status, ['completed', 'canceled'])) {
        $delivered = get_field('delivered_data', $order_id) ?? '';
    }

    global $wpdb;
    $chat_table   = $wpdb->prefix . 'mkt_chat';
    $last_read    = (int) get_user_meta($cur_user, "_mkt_last_read_{$order_id}", true);
    $unread_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$chat_table} WHERE order_id = %d AND id > %d AND user_id != %d AND user_id != 0",
        $order_id, $last_read, $cur_user
    ));

    return [
        'id'            => $order_id,
        'product_id'    => $product_id,
        'product_title' => $product_id ? get_the_title($product_id) : '',
        'buyer_id'      => $buyer_id,
        'buyer_name'    => $buyer_id ? get_userdata($buyer_id)->display_name : '',
        'buyer_avatar'  => $buyer_id ? mkt_get_avatar_url($buyer_id) : '',
        'seller_id'     => $seller_id,
        'seller_name'   => $seller_id ? get_userdata($seller_id)->display_name : '',
        'seller_avatar' => $seller_id ? mkt_get_avatar_url($seller_id) : '',
        'status'        => $status,
        'amount'        => $amount,
        'amount_fmt'    => mkt_format_price($amount),
        'delivered'     => esc_html($delivered),
        'date'          => get_the_date('d.m.Y H:i', $order_id),
        'url'           => home_url("/orders/?id={$order_id}"),
        'unread_count'  => $unread_count,
    ];
}

function mkt_rest_get_order(WP_REST_Request $req): WP_REST_Response {
    $order_id = (int) $req->get_param('id');
    $user_id  = get_current_user_id();

    if (!mkt_can_access_chat($order_id, $user_id)) {
        return new WP_REST_Response(['error' => 'Нет доступа.'], 403);
    }

    return new WP_REST_Response(mkt_format_order($order_id), 200);
}

function mkt_rest_get_stats(WP_REST_Request $req): WP_REST_Response {
    $user_id = get_current_user_id();
    $role    = mkt_get_role($user_id);
    $balance = mkt_get_balance($user_id);
    $hold    = mkt_get_hold($user_id);

    $data = ['balance' => $balance, 'hold' => $hold, 'role' => $role];

    if ($role === 'seller') {
        $orders = new WP_Query([
            'post_type'   => 'orders',
            'post_status' => 'publish',
            'meta_query'  => [
                ['key' => 'seller_id',    'value' => $user_id],
                ['key' => 'order_status', 'value' => 'completed'],
            ],
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        $total_earned = 0;
        foreach ($orders->posts as $oid) {
            $total_earned += (float) get_field('order_amount', $oid) * (1 - mkt_commission_rate());
        }
        $data['total_sales']  = $orders->found_posts;
        $data['total_earned'] = round($total_earned, 2);
    } else {
        $orders = new WP_Query([
            'post_type'   => 'orders',
            'post_status' => 'publish',
            'meta_query'  => [['key' => 'buyer_id', 'value' => $user_id]],
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        $data['total_orders'] = $orders->found_posts;
    }

    return new WP_REST_Response($data, 200);
}

add_action('wp_ajax_mkt_get_categories', 'mkt_ajax_get_categories');
add_action('wp_ajax_nopriv_mkt_get_categories', 'mkt_ajax_get_categories');
function mkt_ajax_get_categories(): void {
    $cats  = get_terms(['taxonomy' => 'group',      'hide_empty' => false]);
    $types = get_terms(['taxonomy' => 'type-group', 'hide_empty' => false]);
    wp_send_json_success([
        'categories' => array_map(fn($t) => ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug], is_array($cats) ? $cats : []),
        'types'      => array_map(fn($t) => ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug], is_array($types) ? $types : []),
    ]);
}

function mkt_rest_get_reviews(WP_REST_Request $req): WP_REST_Response {
    $product_id = (int) $req->get_param('product_id');
    $page       = max(1, (int) $req->get_param('page'));
    $per_page   = 4;

    $query = new WP_Query([
        'post_type'      => 'orders',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => 'offer_id',     'value' => $product_id, 'compare' => '='],
            ['key' => 'order_status', 'value' => 'completed'],
            ['key' => 'tekst_otzyva', 'value' => '',          'compare' => '!='],
        ],
    ]);

    $reviews = [];
    foreach ($query->posts as $post) {
        $review = get_field('otzyv', $post->ID);
        if (empty($review['tekst_otzyva'])) continue;
        $buyer_id = (int) get_field('buyer_id', $post->ID);
        $reviews[] = [
            'id'     => $post->ID,
            'text'   => esc_html($review['tekst_otzyva']),
            'rating' => min(5, max(1, (int) ($review['oczenka'] ?? 5))),
            'buyer'  => $buyer_id ? esc_html(get_userdata($buyer_id)->display_name) : 'Покупатель',
            'avatar' => $buyer_id ? mkt_get_avatar_url($buyer_id) : '',
            'date'   => get_the_date('d.m.Y', $post->ID),
        ];
    }

    return new WP_REST_Response([
        'reviews'     => $reviews,
        'total'       => $query->found_posts,
        'total_pages' => $query->max_num_pages,
        'page'        => $page,
    ], 200);
}

function mkt_rest_get_seller_products(WP_REST_Request $req): WP_REST_Response {
    $seller_id = (int) $req->get_param('seller_id');
    $page      = max(1, (int) ($req->get_param('page') ?? 1));

    $query = new WP_Query([
        'post_type'      => 'products',
        'post_status'    => 'publish',
        'posts_per_page' => 20,
        'paged'          => $page,
        'meta_query'     => [
            ['key' => 'product_seller_id', 'value' => $seller_id],
            ['key' => 'is_active',         'value' => '1'],
        ],
    ]);

    $items = [];
    foreach ($query->posts as $post) {
        $items[] = mkt_format_product($post->ID);
    }

    return new WP_REST_Response([
        'items'       => $items,
        'total'       => $query->found_posts,
        'total_pages' => $query->max_num_pages,
        'page'        => $page,
    ], 200);
}

add_action('wp_ajax_mkt_create_product', 'mkt_ajax_create_product');
function mkt_ajax_create_product(): void {
    check_ajax_referer('marketplace_nonce', 'nonce');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Не авторизован.']);

    $user_id  = get_current_user_id();
    if (!mkt_is_seller($user_id)) {
        wp_send_json_error(['message' => 'Только продавцы могут создавать товары.']);
    }

    $title      = sanitize_text_field($_POST['title']       ?? '');
    $price      = (float) ($_POST['price']                  ?? 0);
    $price_sale = (float) ($_POST['price_sale']             ?? 0);
    $delivery   = sanitize_text_field($_POST['delivery']    ?? 'manual');
    $desc       = sanitize_textarea_field($_POST['description'] ?? '');
    $how_to     = sanitize_textarea_field($_POST['how_to']  ?? '');
    $keys       = sanitize_textarea_field($_POST['keys']    ?? '');
    $category   = (int) ($_POST['category']                 ?? 0);
    $type       = (int) ($_POST['type']                     ?? 0);

    if (!$title || $price <= 0) wp_send_json_error(['message' => 'Укажите название и цену.']);
    if (!in_array($delivery, ['auto', 'manual'])) wp_send_json_error(['message' => 'Некорректный тип выдачи.']);
    if ($delivery === 'auto' && !trim($keys)) wp_send_json_error(['message' => 'Для автовыдачи необходимо добавить ключи.']);
    if (!$desc)   wp_send_json_error(['message' => 'Добавьте описание товара.']);
    if (!$how_to) wp_send_json_error(['message' => 'Укажите способ получения.']);
    if (!$category || !$type) wp_send_json_error(['message' => 'Выберите категорию и тип товара.']);
    if (empty($_FILES['product_image']['tmp_name'])) wp_send_json_error(['message' => 'Загрузите изображение товара.']);

    $allowed_types = ['image/png', 'image/webp', 'image/jpeg', 'image/jpg'];
    $mime = mime_content_type($_FILES['product_image']['tmp_name']);
    if (!in_array($mime, $allowed_types)) {
        wp_send_json_error(['message' => 'Допустимые форматы: PNG, JPG, WEBP.']);
    }

    $post_id = wp_insert_post([
        'post_type'   => 'products',
        'post_status' => 'publish',
        'post_title'  => $title,
        'post_author' => $user_id,
    ]);

    if (is_wp_error($post_id)) wp_send_json_error(['message' => $post_id->get_error_message()]);

    update_field('price',              $price,      $post_id);
    update_field('price_sell',         $price_sale, $post_id);
    update_field('delivery_type',      $delivery,   $post_id);
    update_field('opisanie',           $desc,       $post_id);
    update_field('sposob_polucheniya', $how_to,     $post_id);
    update_field('product_seller_id',  $user_id,    $post_id);
    update_field('is_active',          1,           $post_id);
    if ($delivery === 'auto') update_field('secret_data', $keys, $post_id);

    if ($category) wp_set_post_terms($post_id, [$category], 'group');
    if ($type)     wp_set_post_terms($post_id, [$type],     'type-group');

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $att = media_handle_upload('product_image', $post_id);
    if (!is_wp_error($att)) set_post_thumbnail($post_id, $att);

    wp_send_json_success(['id' => $post_id, 'message' => 'Товар создан.']);
}

add_action('wp_ajax_mkt_submit_review', 'mkt_ajax_submit_review');
function mkt_ajax_submit_review(): void {
    mkt_check_nonce();
    mkt_require_login();

    $order_id = (int) ($_POST['order_id'] ?? 0);
    $rating   = min(5, max(1, (int) ($_POST['rating']  ?? 5)));
    $text     = sanitize_textarea_field($_POST['text'] ?? '');
    $user_id  = get_current_user_id();

    if (!$order_id || !$text) wp_send_json_error(['message' => 'Напишите текст отзыва.']);

    $buyer_id = (int) get_field('buyer_id', $order_id);
    if ($buyer_id !== $user_id) wp_send_json_error(['message' => 'Только покупатель может оставить отзыв.']);

    $status = get_field('order_status', $order_id);
    if (!in_array($status, ['completed', 'canceled'])) {
        wp_send_json_error(['message' => 'Нельзя оставить отзыв для активной сделки.']);
    }

    $existing = get_field('otzyv', $order_id);
    if (!empty($existing['tekst_otzyva'])) {
        wp_send_json_error(['message' => 'Вы уже оставили отзыв на этот заказ.']);
    }

    update_field('otzyv', ['tekst_otzyva' => $text, 'oczenka' => $rating], $order_id);
    update_post_meta($order_id, 'tekst_otzyva', $text);

    wp_send_json_success(['message' => 'Отзыв отправлен. Спасибо!']);
}

add_action('wp_ajax_mkt_mark_read', 'mkt_ajax_mark_read');
function mkt_ajax_mark_read(): void {
    check_ajax_referer('marketplace_nonce', 'nonce');
    $order_id = (int) ($_POST['order_id'] ?? 0);
    $msg_id   = (int) ($_POST['msg_id']   ?? 0);
    $user_id  = get_current_user_id();
    if ($order_id && $msg_id && $user_id) {
        $current = (int) get_user_meta($user_id, "_mkt_last_read_{$order_id}", true);
        if ($msg_id > $current) {
            update_user_meta($user_id, "_mkt_last_read_{$order_id}", $msg_id);
        }
    }
    wp_send_json_success();
}

add_action('wp_ajax_nopriv_mkt_check_invite', 'mkt_ajax_check_invite');
function mkt_ajax_check_invite(): void {
    $code = strtoupper(sanitize_text_field($_POST['code'] ?? ''));
    $uid  = mkt_find_user_by_ref_code($code);
    if ($uid) {
        $u = get_userdata($uid);
        wp_send_json_success(['valid' => true, 'name' => esc_html($u->display_name)]);
    } else {
        wp_send_json_success(['valid' => false]);
    }
}
