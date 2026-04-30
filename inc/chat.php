<?php
defined('ABSPATH') || exit;

function mkt_chat_system_message(int $order_id, string $message): void {
    global $wpdb;
    $wpdb->insert("{$wpdb->prefix}mkt_chat", [
        'order_id'   => $order_id,
        'user_id'    => 0,
        'message'    => $message,
        'is_system'  => 1,
        'created_at' => current_time('mysql'),
    ]);
}

function mkt_can_access_chat(int $order_id, int $user_id): bool {
    if (mkt_is_admin($user_id)) return true;
    $buyer_id  = (int) get_field('buyer_id',  $order_id);
    $seller_id = (int) get_field('seller_id', $order_id);
    return $user_id === $buyer_id || $user_id === $seller_id;
}

add_action('rest_api_init', function () {
    register_rest_route('marketplace/v1', '/chat/(?P<order_id>\d+)', [
        [
            'methods'             => 'GET',
            'callback'            => 'mkt_rest_get_messages',
            'permission_callback' => 'mkt_rest_chat_permission',
            'args'                => [
                'order_id' => ['required' => true, 'sanitize_callback' => 'absint'],
                'since'    => ['default' => 0, 'sanitize_callback' => 'absint'],
            ],
        ],
        [
            'methods'             => 'POST',
            'callback'            => 'mkt_rest_send_message',
            'permission_callback' => 'mkt_rest_chat_permission',
        ],
    ]);
});

function mkt_rest_chat_permission(WP_REST_Request $req): bool|WP_Error {
    if (!is_user_logged_in()) return new WP_Error('unauthorized', 'Необходима авторизация.', ['status' => 401]);
    $order_id = (int) $req->get_param('order_id');
    if (!mkt_can_access_chat($order_id, get_current_user_id())) {
        return new WP_Error('forbidden', 'Нет доступа.', ['status' => 403]);
    }
    return true;
}

function mkt_rest_get_messages(WP_REST_Request $req): WP_REST_Response {
    global $wpdb;
    $order_id = (int) $req->get_param('order_id');
    $since    = (int) $req->get_param('since');

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}mkt_chat WHERE order_id = %d AND id > %d ORDER BY id ASC LIMIT 50",
        $order_id, $since
    ));

    $messages = [];
    foreach ($rows as $row) {
        $uid = (int) $row->user_id;
        $messages[] = [
            'id'         => (int) $row->id,
            'user_id'    => $uid,
            'message'    => esc_html($row->message),
            'is_system'  => (bool) $row->is_system,
            'is_admin'   => $uid > 0 && mkt_is_admin($uid),
            'avatar'     => $uid ? mkt_get_avatar_url($uid) : '',
            'name'       => $uid ? get_userdata($uid)->display_name : 'Система',
            'time'       => wp_date('H:i', strtotime($row->created_at)),
            'created_at' => $row->created_at,
        ];
    }

    return new WP_REST_Response(['messages' => $messages], 200);
}

function mkt_rest_send_message(WP_REST_Request $req): WP_REST_Response {
    global $wpdb;
    $order_id = (int) $req->get_param('order_id');
    $message  = sanitize_textarea_field($req->get_param('message') ?? '');
    $user_id  = get_current_user_id();

    if (!$message) return new WP_REST_Response(['error' => 'Пустое сообщение.'], 400);
    if (mb_strlen($message) > 2000) return new WP_REST_Response(['error' => 'Сообщение слишком длинное.'], 400);

    $status = get_field('order_status', $order_id);
    if (in_array($status, ['completed', 'canceled'])) {
        return new WP_REST_Response(['error' => 'Сделка завершена.'], 400);
    }

    $wpdb->insert("{$wpdb->prefix}mkt_chat", [
        'order_id'   => $order_id,
        'user_id'    => $user_id,
        'message'    => $message,
        'is_system'  => 0,
        'created_at' => current_time('mysql'),
    ]);

    $msg_id = $wpdb->insert_id;
    return new WP_REST_Response([
        'id'        => $msg_id,
        'user_id'   => $user_id,
        'message'   => esc_html($message),
        'is_system' => false,
        'avatar'    => mkt_get_avatar_url($user_id),
        'name'      => get_userdata($user_id)->display_name,
        'time'      => current_time('H:i'),
    ], 201);
}
