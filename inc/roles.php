<?php
defined('ABSPATH') || exit;

add_action('init', function () {
    if (!get_role('buyer')) {
        add_role('buyer', 'Пользователь', [
            'read'          => true,
            'upload_files'  => true,
            'publish_posts' => true,
            'edit_posts'    => true,
            'delete_posts'  => false,
        ]);
    } else {
        $r = get_role('buyer');
        foreach (['publish_posts', 'edit_posts'] as $cap) {
            if (!$r->has_cap($cap)) $r->add_cap($cap);
        }
    }
});

function mkt_get_role(int $user_id): string {
    $u = get_userdata($user_id);
    if (!$u) return '';
    if (in_array('administrator', $u->roles)) return 'admin';
    return 'buyer';
}

function mkt_is_seller(int $user_id = 0): bool {
    $uid = $user_id ?: get_current_user_id();
    return $uid > 0 && !mkt_is_admin($uid);
}

function mkt_is_buyer(int $user_id = 0): bool {
    $uid = $user_id ?: get_current_user_id();
    return $uid > 0 && !mkt_is_admin($uid);
}

function mkt_is_admin(int $user_id = 0): bool {
    $uid = $user_id ?: get_current_user_id();
    return mkt_get_role($uid) === 'admin';
}

function mkt_role_label(int $user_id = 0): string {
    $map = ['admin' => 'Администратор', 'buyer' => 'Пользователь'];
    return $map[mkt_get_role($user_id ?: get_current_user_id())] ?? 'Пользователь';
}
