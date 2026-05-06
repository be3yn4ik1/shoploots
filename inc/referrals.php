<?php
defined('ABSPATH') || exit;

// Hardcoded referral rates (no ACF needed):
//   Seller chain  L1=5%, L2=3%, L3=2%  → 10% total
//   Buyer inviter L1=1%
//   Commission must be ≥11% to break even; default is 12% (+1% platform).

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

function mkt_execute_referral_payouts(int $buyer_id, int $seller_id, float $amount): void {
    $payouts = [];

    // Seller's referral chain: 5% → 3% → 2%
    $seller_rates = [5.0, 3.0, 2.0];
    foreach (mkt_get_referral_chain($seller_id, 3) as $depth => $uid) {
        if (!isset($seller_rates[$depth])) break;
        $reward = round($amount * $seller_rates[$depth] / 100, 2);
        if ($reward > 0) $payouts[$uid] = ($payouts[$uid] ?? 0.0) + $reward;
    }

    // Buyer's direct inviter: 1%
    $buyer_chain = mkt_get_referral_chain($buyer_id, 1);
    if (!empty($buyer_chain)) {
        $uid    = $buyer_chain[0];
        $reward = round($amount * 1.0 / 100, 2);
        if ($reward > 0) $payouts[$uid] = ($payouts[$uid] ?? 0.0) + $reward;
    }

    foreach ($payouts as $uid => $reward) {
        mkt_add_balance((int) $uid, (float) $reward);
        mkt_log('referral_payout', (int) $uid, 'Реферальный бонус', [
            'amount'      => $reward,
            'from_amount' => $amount,
        ]);
    }
}
