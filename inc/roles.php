<?php
defined('ABSPATH') || exit;

add_action('init', function () {
    if (!get_role('buyer')) {
        add_role('buyer', 'Покупатель', [
            'read'         => true,
            'upload_files' => true,
        ]);
    }
    if (!get_role('seller')) {
        add_role('seller', 'Продавец', [
            'read'              => true,
            'upload_files'      => true,
            'publish_posts'     => true,
            'edit_posts'        => true,
            'delete_posts'      => false,
        ]);
    }
});

function mkt_get_role(int $user_id): string {
    $u = get_userdata($user_id);
    if (!$u) return '';
    if (in_array('administrator', $u->roles)) return 'admin';
    $acf = get_field('marketplace_role', "user_{$user_id}");
    if ($acf) return $acf;
    if (in_array('seller', $u->roles)) return 'seller';
    return 'buyer';
}

function mkt_is_seller(int $user_id = 0): bool {
    $uid = $user_id ?: get_current_user_id();
    return mkt_get_role($uid) === 'seller';
}

function mkt_is_buyer(int $user_id = 0): bool {
    $uid = $user_id ?: get_current_user_id();
    return mkt_get_role($uid) === 'buyer';
}

function mkt_is_admin(int $user_id = 0): bool {
    $uid = $user_id ?: get_current_user_id();
    return mkt_get_role($uid) === 'admin';
}

function mkt_role_label(int $user_id = 0): string {
    $map = ['admin' => 'Администратор', 'seller' => 'Продавец', 'buyer' => 'Покупатель'];
    return $map[mkt_get_role($user_id ?: get_current_user_id())] ?? 'Покупатель';
}
