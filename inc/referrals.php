<?php
defined('ABSPATH') || exit;

function mkt_get_referral_chain(int $user_id, int $max = 20): array {
    $chain   = [];
    $current = $user_id;
    for ($i = 0; $i < $max; $i++) {
        $ref_id = (int) get_field('referred_by_id', "user_{$current}");
        if (!$ref_id || $ref_id === $user_id) break;
        $chain[] = $ref_id;
        $current = $ref_id;
    }
    return $chain;
}

/**
 * Pushing system: always 5 active payout slots.
 *  Slot 1 = direct referrer (index 0), always included at ref_l1 rate.
 *  Slots 2-5 = the last 4 referrers in the chain (indexes n-4 … n-1),
 *              skipping index 0 if it falls in that window.
 *
 * Examples (0-based indexes):
 *   5-deep chain → pay [0,1,2,3,4]
 *   6-deep chain → pay [0,2,3,4,5]  (index 1 pushed out)
 *  10-deep chain → pay [0,6,7,8,9]
 */
function mkt_calculate_referral_distribution(int $origin_user_id, float $amount): array {
    $rates = [
        1 => (float) mkt_get_system_option('ref_l1', 5),
        2 => (float) mkt_get_system_option('ref_l2', 1),
        3 => (float) mkt_get_system_option('ref_l3', 1),
        4 => (float) mkt_get_system_option('ref_l4', 1),
        5 => (float) mkt_get_system_option('ref_l5', 1),
    ];

    $chain = mkt_get_referral_chain($origin_user_id, 20);
    $n     = count($chain);

    if ($n === 0) {
        return ['payouts' => [], 'site_keeps' => $amount];
    }

    $payouts     = [];
    $total_paid  = 0;

    // Slot 1: direct referrer (always index 0)
    if ($rates[1] > 0) {
        $reward      = round($amount * $rates[1] / 100, 2);
        $payouts[]   = ['user_id' => $chain[0], 'amount' => $reward, 'level' => 1];
        $total_paid += $reward;
    }

    // Slots 2-5: last 4 entries of the chain, excluding index 0
    $tail_start = max(1, $n - 4);
    $slot       = 2;
    for ($i = $tail_start; $i < $n; $i++) {
        if ($i === 0) { $slot++; continue; } // shouldn't happen, but guard
        $rate = $rates[$slot] ?? 1.0;
        if ($rate > 0) {
            $reward      = round($amount * $rate / 100, 2);
            $payouts[]   = ['user_id' => $chain[$i], 'amount' => $reward, 'level' => $slot];
            $total_paid += $reward;
        }
        $slot++;
    }

    // Promo code owner (1% of purchase amount)
    $invite_code  = get_user_meta($origin_user_id, '_invite_code_used', true);
    $promo_owner  = $invite_code ? mkt_find_user_by_ref_code($invite_code) : null;
    if ($promo_owner) {
        $promo_reward = round($amount * 0.01, 2);
        $payouts[]    = ['user_id' => $promo_owner, 'amount' => $promo_reward, 'level' => 0, 'type' => 'promo'];
        $total_paid  += $promo_reward;
    }

    return ['payouts' => $payouts, 'site_keeps' => round($amount - $total_paid, 2)];
}

function mkt_execute_referral_payouts(int $origin_user_id, float $amount): void {
    $dist = mkt_calculate_referral_distribution($origin_user_id, $amount);
    foreach ($dist['payouts'] as $p) {
        mkt_add_balance((int) $p['user_id'], (float) $p['amount']);
        $level_label = $p['level'] > 0 ? "ур.{$p['level']}" : 'промокод';
        mkt_log('referral_payout', (int) $p['user_id'], "Реф. бонус {$level_label}", [
            'origin_user_id' => $origin_user_id,
            'amount'         => $p['amount'],
            'level'          => $p['level'],
        ]);
    }
}
