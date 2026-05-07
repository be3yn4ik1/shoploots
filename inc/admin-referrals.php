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
        'Реферальные выплаты',
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
    if ($pct >= 4.5) return ['label' => 'Продавец L1 (5%)',  'style' => 'background:#e3f1ff;color:#0055cc;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:700'];
    if ($pct >= 2.5) return ['label' => 'Продавец L2 (3%)',  'style' => 'background:#f3e5f5;color:#6a1b9a;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:700'];
    if ($pct >= 1.5) return ['label' => 'Продавец L3 (2%)',  'style' => 'background:#fff3e0;color:#e65100;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:700'];
    return             ['label' => 'Покупатель L1 (1%)', 'style' => 'background:#e8f5e9;color:#2e7d32;padding:2px 8px;border-radius:12px;font-size:.75rem;font-weight:700'];
}

function mkt_admin_referrals_page(): void {
    global $wpdb;
    $table = $wpdb->prefix . 'mkt_logs';
    ?>
    <div class="wrap">
        <h1>Реферальная система</h1>

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

        <!-- Покупки и начисления -->
        <div style="background:#fff;border:1px solid #ddd;border-radius:10px;padding:24px;margin-bottom:24px">
            <h2 style="margin-top:0;font-size:1.1rem">Покупки и реферальные начисления</h2>
            <p style="color:#777;font-size:.82rem;margin-bottom:20px">
                Каждая подтверждённая сделка → кто получил реферальный бонус и сколько
            </p>
            <?php
            $confirmed = $wpdb->get_results(
                "SELECT user_id, data, created_at FROM {$table}
                 WHERE type='order_confirmed' ORDER BY id DESC LIMIT 30"
            ) ?: [];

            if (!$confirmed): ?>
                <p style="color:#999">Подтверждённых сделок пока нет.</p>
            <?php else:
                foreach ($confirmed as $event):
                    $ed         = json_decode($event->data ?? '{}', true) ?: [];
                    $order_id   = (int) ($ed['order_id'] ?? 0);
                    $sale_amount = (float) ($ed['amount'] ?? 0);
                    if (!$order_id) continue;
                    if (!get_post($order_id)) continue;

                    $buyer_id  = (int) get_field('buyer_id',  $order_id);
                    $seller_id = (int) get_field('seller_id', $order_id);
                    $product_id = (int) get_field('offer_id', $order_id);
                    $buyer  = get_userdata($buyer_id);
                    $seller = get_userdata($seller_id);
                    if (!$buyer || !$seller) continue;
                    $product_title = $product_id ? get_the_title($product_id) : '—';
                    $seller_gets   = round($sale_amount * 0.88, 2);
                    $order_url     = home_url("/orders/?id={$order_id}");

                    // Реф. выплаты для этого заказа
                    $ref_payouts = $wpdb->get_results($wpdb->prepare(
                        "SELECT user_id, data FROM {$table}
                         WHERE type='referral_payout'
                           AND JSON_EXTRACT(data,'$.order_id') = %d
                         ORDER BY id ASC",
                        $order_id
                    )) ?: [];

                    $role_map = [
                        'seller_l1' => ['label' => 'Продавец L1 (5%)',   'color' => '#0077ff', 'bg' => '#e3f1ff'],
                        'seller_l2' => ['label' => 'Продавец L2 (3%)',   'color' => '#6a1b9a', 'bg' => '#f3e5f5'],
                        'seller_l3' => ['label' => 'Продавец L3 (2%)',   'color' => '#e65100', 'bg' => '#fff3e0'],
                        'buyer_l1'  => ['label' => 'Покупатель L1 (1%)', 'color' => '#2e7d32', 'bg' => '#e8f5e9'],
                    ];
            ?>
                <div style="border:1px solid #e8eaed;border-radius:10px;margin-bottom:16px;overflow:hidden">

                    <!-- Шапка: покупка -->
                    <div style="background:#f8f9fb;padding:14px 18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;border-bottom:1px solid #e8eaed">
                        <div style="flex:1;min-width:0">
                            <div style="font-weight:700;font-size:.9rem">
                                <a href="<?= esc_url($order_url) ?>" target="_blank" style="color:#0077ff;text-decoration:none">
                                    Заказ #<?= $order_id ?>
                                </a>
                                &nbsp;·&nbsp;
                                <?= esc_html($product_title) ?>
                            </div>
                            <div style="font-size:.78rem;color:#888;margin-top:3px;display:flex;gap:16px;flex-wrap:wrap">
                                <span>Покупатель: <strong style="color:#333"><?= $buyer  ? esc_html($buyer->display_name)  : '—' ?></strong></span>
                                <span>Продавец: <strong style="color:#333"><?= $seller ? esc_html($seller->display_name) : '—' ?></strong></span>
                                <span><?= wp_date('d.m.Y H:i', strtotime($event->created_at)) ?></span>
                            </div>
                        </div>
                        <div style="text-align:right;white-space:nowrap">
                            <div style="font-size:1.1rem;font-weight:800;color:#222"><?= number_format($sale_amount, 2, '.', ' ') ?> ₽</div>
                            <div style="font-size:.72rem;color:#888">сумма сделки</div>
                        </div>
                    </div>

                    <!-- Тело: кто что получил -->
                    <div style="padding:0 18px">

                        <?php if (empty($ref_payouts)): ?>
                        <div style="padding:12px 0;color:#bbb;font-size:.82rem;font-style:italic">
                            Реферальных выплат по этой сделке нет (никто не был приглашён по реферальной ссылке)
                        </div>
                        <?php else:
                            $total_ref = 0;
                            $last_idx  = count($ref_payouts) - 1;
                            foreach ($ref_payouts as $ri => $rp):
                                $rd       = json_decode($rp->data ?? '{}', true) ?: [];
                                $r_amount = (float) ($rd['amount'] ?? 0);
                                $r_role   = $rd['role'] ?? '';
                                $total_ref += $r_amount;
                                $uid      = (int) $rp->user_id;
                                $ruser    = get_userdata($uid);
                                $rname    = $ruser ? $ruser->display_name : 'ID ' . $uid;
                                $is_last  = ($ri === $last_idx);
                                $info     = $role_map[$r_role] ?? null;

                                // Если роль не сохранена (старые записи) — угадываем по %
                                if (!$info) {
                                    $pct  = ($rd['from_amount'] ?? 0) > 0 ? round($r_amount / $rd['from_amount'] * 100, 1) : 0;
                                    $info = mkt_referral_level_label($pct);
                                }

                                // Кто является источником для этого получателя
                                if (in_array($r_role, ['buyer_l1', ''])) {
                                    $source_id   = $buyer_id;
                                    $source_label = 'пригласил покупателя';
                                } else {
                                    $source_id   = $seller_id;
                                    $source_label = 'пригласил продавца';
                                }
                                $source = get_userdata($source_id);
                        ?>
                        <div style="display:flex;align-items:center;gap:0;padding:11px 0;<?= !$is_last ? 'border-bottom:1px solid #f5f5f5' : '' ?>">
                            <div style="color:#ccc;font-size:.9rem;width:20px;flex-shrink:0;user-select:none">
                                <?= $is_last ? '└' : '├' ?>
                            </div>
                            <div style="color:#0077ff;font-weight:700;font-size:1rem;width:16px;flex-shrink:0">→</div>
                            <div style="margin-left:10px;flex:1;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                <strong style="font-size:.9rem"><?= esc_html($rname) ?></strong>
                                <?php if ($source): ?>
                                <span style="font-size:.75rem;color:#999">(<?= esc_html($source_label) ?>: <?= esc_html($source->display_name) ?>)</span>
                                <?php endif; ?>
                            </div>
                            <div style="margin-left:auto;display:flex;align-items:center;gap:10px;flex-shrink:0">
                                <?php
                                $badge_style = isset($info['bg'])
                                    ? 'background:' . $info['bg'] . ';color:' . $info['color'] . ';padding:2px 10px;border-radius:12px;font-size:.75rem;font-weight:700;white-space:nowrap'
                                    : ($info['style'] ?? '');
                                ?>
                                <span style="<?= $badge_style ?>"><?= esc_html($info['label']) ?></span>
                                <strong style="color:#2e7d32;font-size:1rem;min-width:70px;text-align:right">+<?= number_format($r_amount, 2, '.', ' ') ?> ₽</strong>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                    </div>

                    <!-- Итог -->
                    <div style="background:#fafafa;border-top:1px solid #e8eaed;padding:10px 18px;display:flex;gap:24px;flex-wrap:wrap;font-size:.8rem">
                        <span>Продавцу: <strong style="color:#222"><?= number_format($seller_gets, 2, '.', ' ') ?> ₽</strong></span>
                        <?php if (!empty($ref_payouts)): ?>
                        <span>Реф. выплаты: <strong style="color:#0077ff"><?= number_format($total_ref ?? 0, 2, '.', ' ') ?> ₽</strong></span>
                        <?php endif; ?>
                        <span style="color:#888">Платформа (1%): <strong><?= number_format($sale_amount * 0.01, 2, '.', ' ') ?> ₽</strong></span>
                    </div>

                </div>
            <?php endforeach; endif; ?>
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
