<?php
defined('ABSPATH') || exit;

function mkt_get_referral_chain(int $user_id, int $max = 6): array {
    $chain = [];
    $current = $user_id;
    for ($i = 0; $i < $max; $i++) {
        $ref_id = (int) get_field('referred_by_id', "user_{$current}");
        if (!$ref_id || $ref_id === $user_id) break;
        $chain[] = $ref_id;
        $current = $ref_id;
    }
    return $chain;
}

function mkt_calculate_referral_distribution(int $buyer_id, float $amount): array {
    $levels = [
        1 => (float) mkt_get_system_option('ref_l1', 5),
        2 => (float) mkt_get_system_option('ref_l2', 1),
        3 => (float) mkt_get_system_option('ref_l3', 1),
        4 => (float) mkt_get_system_option('ref_l4', 1),
        5 => (float) mkt_get_system_option('ref_l5', 0.5),
        6 => (float) mkt_get_system_option('ref_l6', 0.5),
    ];

    $chain       = mkt_get_referral_chain($buyer_id, 6);
    $payouts     = [];
    $total_paid  = 0;

    foreach ($chain as $idx => $ref_id) {
        $level   = $idx + 1;
        $pct     = $levels[$level] ?? 0;
        if ($pct <= 0) continue;
        $reward  = round($amount * $pct / 100, 2);
        $payouts[] = ['user_id' => $ref_id, 'amount' => $reward, 'level' => $level];
        $total_paid += $reward;
    }

    $invite_code  = get_user_meta($buyer_id, '_invite_code_used', true);
    $promo_owner  = $invite_code ? mkt_find_user_by_ref_code($invite_code) : null;
    $promo_reward = 0;
    if ($promo_owner) {
        $promo_reward = round($amount * 0.01, 2);
        $payouts[]    = ['user_id' => $promo_owner, 'amount' => $promo_reward, 'level' => 0, 'type' => 'promo'];
        $total_paid  += $promo_reward;
    }

    return ['payouts' => $payouts, 'site_keeps' => round($amount - $total_paid, 2)];
}

function mkt_execute_referral_payouts(int $buyer_id, float $amount): void {
    $dist = mkt_calculate_referral_distribution($buyer_id, $amount);
    foreach ($dist['payouts'] as $p) {
        mkt_add_balance((int) $p['user_id'], (float) $p['amount']);
        mkt_log('referral_payout', $p['user_id'], "Реф. выплата ур.{$p['level']}", [
            'buyer_id' => $buyer_id,
            'amount'   => $p['amount'],
        ]);
    }
}
