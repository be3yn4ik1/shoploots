<?php
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_menu_page(
        'Реферальная система',
        'Рефералы',
        'manage_options',
        'mkt-referrals',
        'mkt_admin_referrals_page',
        'dashicons-networking',
        30
    );
});

add_action('wp_dashboard_setup', function () {
    wp_add_dashboard_widget(
        'mkt_referrals_widget',
        '🔗 Реферальные выплаты',
        'mkt_dashboard_referrals_widget'
    );
});

function mkt_dashboard_referrals_widget(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'mkt_logs';

    $total_paid = (float) $wpdb->get_var(
        "SELECT SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(data,'$.amount')) AS DECIMAL(10,2)))
         FROM {$table} WHERE type='referral_payout'"
    );
    $total_events = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$table} WHERE type='referral_payout'"
    );
    $total_refs = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='referred_by_id'"
    );

    echo '<div style="display:flex;gap:16px;margin-bottom:16px">';
    foreach ([
        ['label' => 'Рефералов в системе', 'val' => $total_refs],
        ['label' => 'Выплат всего',         'val' => $total_events],
        ['label' => 'Сумма выплат',         'val' => number_format($total_paid, 2, '.', ' ') . ' ₽'],
    ] as $s) {
        echo '<div style="flex:1;background:#f0f7ff;border-radius:8px;padding:12px;text-align:center">';
        echo '<div style="font-size:1.3rem;font-weight:700;color:#0077ff">' . esc_html($s['val']) . '</div>';
        echo '<div style="font-size:.78rem;color:#555;margin-top:2px">' . esc_html($s['label']) . '</div>';
        echo '</div>';
    }
    echo '</div>';

    $rows = $wpdb->get_results(
        "SELECT l.user_id, l.message, l.data, l.created_at
         FROM {$table} l WHERE l.type='referral_payout'
         ORDER BY l.id DESC LIMIT 8"
    ) ?: [];

    if (!$rows) { echo '<p style="color:#999;font-size:.85rem">Выплат пока не было.</p>'; return; }

    echo '<table style="width:100%;border-collapse:collapse;font-size:.82rem">';
    echo '<thead><tr style="color:#888;border-bottom:1px solid #eee">';
    echo '<th style="text-align:left;padding:4px 6px">Получатель</th>';
    echo '<th style="text-align:left;padding:4px 6px">Сумма</th>';
    echo '<th style="text-align:left;padding:4px 6px">От продажи</th>';
    echo '<th style="text-align:left;padding:4px 6px">Уровень</th>';
    echo '<th style="text-align:left;padding:4px 6px">Дата</th>';
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $d           = json_decode($row->data ?? '{}', true) ?: [];
        $amount      = (float) ($d['amount']      ?? 0);
        $from_amount = (float) ($d['from_amount'] ?? 0);
        $pct         = $from_amount > 0 ? round($amount / $from_amount * 100, 1) : 0;
        $level_info  = mkt_referral_level_label($pct);
        $user        = get_userdata((int) $row->user_id);
        $name        = $user ? esc_html($user->display_name) : 'ID ' . $row->user_id;

        echo '<tr style="border-bottom:1px solid #f5f5f5">';
        echo '<td style="padding:5px 6px"><strong>' . $name . '</strong></td>';
        echo '<td style="padding:5px 6px;color:#2e7d32;font-weight:700">+' . number_format($amount, 2, '.', ' ') . ' ₽</td>';
        echo '<td style="padding:5px 6px;color:#555">' . number_format($from_amount, 2, '.', ' ') . ' ₽</td>';
        echo '<td style="padding:5px 6px"><span style="' . $level_info['style'] . '">' . $level_info['label'] . '</span></td>';
        echo '<td style="padding:5px 6px;color:#999">' . wp_date('d.m H:i', strtotime($row->created_at)) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    echo '<div style="margin-top:10px;text-align:right">';
    echo '<a href="' . admin_url('admin.php?page=mkt-referrals') . '" style="font-size:.82rem;color:#0077ff">Подробная статистика →</a>';
    echo '</div>';
}

function mkt_referral_level_label(float $pct): array {
    if ($pct >= 4.5) return ['label' => '🔵 Продавец L1 (5%)',  'style' => 'background:#e3f1ff;color:#0055cc;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:700'];
    if ($pct >= 2.5) return ['label' => '🟣 Продавец L2 (3%)',  'style' => 'background:#f3e5f5;color:#6a1b9a;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:700'];
    if ($pct >= 1.5) return ['label' => '🟠 Продавец L3 (2%)',  'style' => 'background:#fff3e0;color:#e65100;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:700'];
    return             ['label' => '🟢 Покупатель L1 (1%)', 'style' => 'background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:700'];
}

function mkt_admin_referrals_page(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'mkt_logs';
    ?>
    <div class="wrap">
        <h1>🔗 Реферальная система</h1>

        <!-- Схема начислений -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:24px;margin-bottom:24px">
            <h2 style="margin-top:0;font-size:1.1rem">Схема начислений</h2>
            <p style="color:#555;font-size:.875rem;margin-bottom:16px">
                При каждой подтверждённой сделке деньги распределяются так:
            </p>
            <?php
            $example = 1000;
            $items = [
                ['party' => 'продавца', 'level' => 'L1', 'pct' => 5,  'color' => '#0077ff', 'bg' => '#e3f1ff', 'who' => 'Кто пригласил продавца'],
                ['party' => 'продавца', 'level' => 'L2', 'pct' => 3,  'color' => '#6a1b9a', 'bg' => '#f3e5f5', 'who' => 'Кто пригласил его пригласившего'],
                ['party' => 'продавца', 'level' => 'L3', 'pct' => 2,  'color' => '#e65100', 'bg' => '#fff3e0', 'who' => 'Третий уровень цепочки продавца'],
                ['party' => 'покупателя', 'level' => 'L1', 'pct' => 1, 'color' => '#2e7d32', 'bg' => '#e8f5e9', 'who' => 'Кто пригласил покупателя'],
            ];
            ?>
            <div style="display:flex;align-items:stretch;gap:0;flex-wrap:wrap;border:1px solid #e0e0e0;border-radius:10px;overflow:hidden">
                <!-- Левая часть: продажа -->
                <div style="background:#1a1a2e;color:#fff;padding:20px 24px;display:flex;flex-direction:column;justify-content:center;align-items:center;min-width:160px">
                    <div style="font-size:.75rem;opacity:.7;margin-bottom:4px;text-transform:uppercase;letter-spacing:.05em">Сумма продажи</div>
                    <div style="font-size:1.8rem;font-weight:700"><?= number_format($example, 0, '.', ' ') ?> ₽</div>
                    <div style="font-size:.72rem;opacity:.6;margin-top:6px">покупатель → escrow → продавец</div>
                </div>
                <!-- Стрелка -->
                <div style="background:#e8eaed;display:flex;align-items:center;padding:0 4px;font-size:1.4rem;color:#888">▶</div>
                <!-- Правая часть: уровни -->
                <div style="flex:1;display:flex;flex-direction:column;min-width:0">
                    <?php foreach ($items as $i => $item): ?>
                    <div style="display:flex;align-items:center;gap:0;<?= $i > 0 ? 'border-top:1px solid #f0f0f0' : '' ?>">
                        <div style="background:<?= $item['bg'] ?>;padding:12px 16px;min-width:120px;display:flex;flex-direction:column;align-items:center">
                            <span style="font-size:1.2rem;font-weight:800;color:<?= $item['color'] ?>"><?= $item['pct'] ?>%</span>
                            <span style="font-size:.7rem;color:<?= $item['color'] ?>;font-weight:600"><?= $item['level'] ?> <?= $item['party'] === 'покупателя' ? 'покупателя' : 'продавца' ?></span>
                        </div>
                        <div style="padding:12px 16px;color:#444;font-size:.82rem;flex:1">
                            <span style="margin-right:6px;font-size:1rem">→</span>
                            <?= esc_html($item['who']) ?>
                            <strong style="color:<?= $item['color'] ?>; margin-left:6px">+<?= number_format($example * $item['pct'] / 100, 0, '.', ' ') ?> ₽</strong>
                        </div>
                        <div style="padding:12px 16px;color:#888;font-size:.78rem;white-space:nowrap">
                            из <?= $example ?> ₽
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div style="background:#fafafa;border-top:2px solid #e0e0e0;padding:10px 16px;display:flex;justify-content:space-between;align-items:center">
                        <span style="font-size:.8rem;color:#888">Продавцу (после комиссии 12%):</span>
                        <strong style="font-size:1rem;color:#222"><?= number_format($example * 0.88, 0, '.', ' ') ?> ₽</strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Статистика -->
        <?php
        $total_paid = (float) $wpdb->get_var(
            "SELECT SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(data,'$.amount')) AS DECIMAL(10,2)))
             FROM {$table} WHERE type='referral_payout'"
        );
        $total_events = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE type='referral_payout'");
        $total_refs   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key='referred_by_id'");

        $top_earner_row = $wpdb->get_row(
            "SELECT user_id,
                    SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(data,'$.amount')) AS DECIMAL(10,2))) AS earned
             FROM {$table} WHERE type='referral_payout'
             GROUP BY user_id ORDER BY earned DESC LIMIT 1"
        );
        $top_earner = $top_earner_row
            ? (get_userdata((int)$top_earner_row->user_id)->display_name ?? '—') . ' — ' . number_format((float)$top_earner_row->earned, 2, '.', ' ') . ' ₽'
            : '—';
        ?>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
            <?php foreach ([
                ['Рефералов в системе', $total_refs,         '#0077ff'],
                ['Реф. выплат (событий)', $total_events,    '#6a1b9a'],
                ['Всего выплачено',      number_format($total_paid, 2, '.', ' ') . ' ₽', '#2e7d32'],
                ['Топ-реферер',          $top_earner,        '#e65100'],
            ] as $s): ?>
            <div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px">
                <div style="font-size:1.3rem;font-weight:700;color:<?= $s[2] ?>"><?= esc_html($s[1]) ?></div>
                <div style="font-size:.78rem;color:#777;margin-top:4px"><?= esc_html($s[0]) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Последние выплаты -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:24px;margin-bottom:24px">
            <h2 style="margin-top:0;font-size:1.1rem">Последние реферальные выплаты</h2>
            <?php
            $rows = $wpdb->get_results(
                "SELECT l.user_id, l.data, l.created_at
                 FROM {$table} l WHERE l.type='referral_payout'
                 ORDER BY l.id DESC LIMIT 50"
            ) ?: [];
            if (!$rows): ?>
                <p style="color:#999">Выплат пока не было.</p>
            <?php else: ?>
            <table style="width:100%;border-collapse:collapse;font-size:.875rem">
                <thead>
                    <tr style="background:#f9f9f9;border-bottom:2px solid #e0e0e0">
                        <th style="text-align:left;padding:10px 12px">Получатель</th>
                        <th style="text-align:left;padding:10px 12px">Получил</th>
                        <th style="text-align:left;padding:10px 12px">Сумма продажи</th>
                        <th style="text-align:left;padding:10px 12px">Уровень</th>
                        <th style="text-align:left;padding:10px 12px">Кто его пригласил</th>
                        <th style="text-align:left;padding:10px 12px">Дата</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row):
                    $d           = json_decode($row->data ?? '{}', true) ?: [];
                    $amount      = (float) ($d['amount']      ?? 0);
                    $from_amount = (float) ($d['from_amount'] ?? 0);
                    $pct         = $from_amount > 0 ? round($amount / $from_amount * 100, 1) : 0;
                    $level       = mkt_referral_level_label($pct);
                    $uid         = (int) $row->user_id;
                    $user        = get_userdata($uid);
                    $name        = $user ? $user->display_name : 'ID ' . $uid;
                    $invited_by_id = (int) get_user_meta($uid, 'referred_by_id', true);
                    $invited_by    = $invited_by_id ? get_userdata($invited_by_id) : null;
                ?>
                <tr style="border-bottom:1px solid #f0f0f0">
                    <td style="padding:10px 12px">
                        <strong><?= esc_html($name) ?></strong>
                        <?php if ($invited_by): ?>
                        <div style="font-size:.75rem;color:#999;margin-top:2px">
                            приглашён: <?= esc_html($invited_by->display_name) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td style="padding:10px 12px;font-weight:700;color:#2e7d32;font-size:1rem">
                        +<?= number_format($amount, 2, '.', ' ') ?> ₽
                    </td>
                    <td style="padding:10px 12px;color:#555">
                        <?= number_format($from_amount, 2, '.', ' ') ?> ₽
                    </td>
                    <td style="padding:10px 12px">
                        <span style="<?= $level['style'] ?>"><?= $level['label'] ?></span>
                    </td>
                    <td style="padding:10px 12px;color:#555">
                        <?php
                        // Нарисовать цепочку: как получатель связан с продажей
                        $chain_parts = [];
                        $ref_uid = (int) get_user_meta($uid, 'referred_by_id', true);
                        if ($ref_uid) {
                            $ref_user = get_userdata($ref_uid);
                            $ref_name = $ref_user ? $ref_user->display_name : 'ID' . $ref_uid;
                            $chain_parts[] = '<span style="color:#0077ff">' . esc_html($name) . '</span>'
                                           . ' <span style="color:#bbb">←</span> '
                                           . '<span style="color:#555">' . esc_html($ref_name) . ' пригласил</span>';
                        } else {
                            $chain_parts[] = '<span style="color:#bbb;font-size:.8rem">нет данных</span>';
                        }
                        echo implode('<br>', $chain_parts);
                        ?>
                    </td>
                    <td style="padding:10px 12px;color:#999;font-size:.82rem;white-space:nowrap">
                        <?= wp_date('d.m.Y H:i', strtotime($row->created_at)) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Кто кого пригласил -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:24px">
            <h2 style="margin-top:0;font-size:1.1rem">Цепочки приглашений</h2>
            <p style="color:#777;font-size:.82rem;margin-bottom:16px">Показаны пользователи, у которых есть хотя бы 1 реферал</p>
            <?php
            $inviters = $wpdb->get_results(
                "SELECT meta_value AS inviter_id, COUNT(*) AS cnt
                 FROM {$wpdb->usermeta}
                 WHERE meta_key='referred_by_id' AND meta_value != '' AND meta_value != '0'
                 GROUP BY meta_value ORDER BY cnt DESC LIMIT 30"
            ) ?: [];

            if (!$inviters): ?>
                <p style="color:#999">Рефералов пока нет.</p>
            <?php else:
                foreach ($inviters as $inv):
                    $inv_id   = (int) $inv->inviter_id;
                    $inv_user = get_userdata($inv_id);
                    if (!$inv_user) continue;
                    $inv_name = $inv_user->display_name;

                    // Реферальный заработок этого пользователя
                    $earned = (float) $wpdb->get_var($wpdb->prepare(
                        "SELECT SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(data,'$.amount')) AS DECIMAL(10,2)))
                         FROM {$table} WHERE type='referral_payout' AND user_id=%d",
                        $inv_id
                    ));

                    // Кого пригласил
                    $referrals = $wpdb->get_results($wpdb->prepare(
                        "SELECT user_id FROM {$wpdb->usermeta}
                         WHERE meta_key='referred_by_id' AND meta_value=%s LIMIT 20",
                        (string) $inv_id
                    )) ?: [];
            ?>
                <div style="border:1px solid #eee;border-radius:8px;padding:14px 16px;margin-bottom:12px">
                    <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
                        <div style="font-weight:700;font-size:.95rem"><?= esc_html($inv_name) ?></div>
                        <div style="font-size:.78rem;background:#e8f5e9;color:#2e7d32;padding:3px 10px;border-radius:12px;font-weight:700">
                            Заработал: <?= number_format($earned, 2, '.', ' ') ?> ₽
                        </div>
                        <div style="font-size:.78rem;color:#888">Рефералов: <strong><?= (int) $inv->cnt ?></strong></div>
                    </div>
                    <div style="margin-top:10px;display:flex;flex-wrap:wrap;gap:6px;align-items:center">
                        <span style="font-size:.82rem;color:#0077ff;font-weight:600"><?= esc_html($inv_name) ?></span>
                        <span style="color:#bbb;font-size:1rem">→ пригласил →</span>
                        <?php foreach ($referrals as $ref):
                            $ref_user = get_userdata((int) $ref->user_id);
                            if (!$ref_user) continue;
                        ?>
                        <span style="background:#f0f2f5;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:600">
                            <?= esc_html($ref_user->display_name) ?>
                        </span>
                        <?php endforeach; ?>
                        <?php if ((int)$inv->cnt > 20): ?>
                        <span style="color:#999;font-size:.78rem">+ ещё <?= (int)$inv->cnt - 20 ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <?php
}
