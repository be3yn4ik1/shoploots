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

function mkt_execute_referral_payouts(int $buyer_id, int $seller_id, float $amount, int $order_id = 0): void {
    $payouts = [];

    // Seller's referral chain: 5% → 3% → 2%
    $seller_rates  = [5.0, 3.0, 2.0];
    $seller_labels = ['seller_l1', 'seller_l2', 'seller_l3'];
    foreach (mkt_get_referral_chain($seller_id, 3) as $depth => $uid) {
        if (!isset($seller_rates[$depth])) break;
        $reward = round($amount * $seller_rates[$depth] / 100, 2);
        if ($reward > 0) {
            $payouts[$uid] = [
                'amount' => ($payouts[$uid]['amount'] ?? 0.0) + $reward,
                'role'   => $payouts[$uid]['role'] ?? $seller_labels[$depth],
            ];
        }
    }

    // Buyer's direct inviter: 1%
    $buyer_chain = mkt_get_referral_chain($buyer_id, 1);
    if (!empty($buyer_chain)) {
        $uid    = $buyer_chain[0];
        $reward = round($amount * 1.0 / 100, 2);
        if ($reward > 0) {
            $payouts[$uid] = [
                'amount' => ($payouts[$uid]['amount'] ?? 0.0) + $reward,
                'role'   => $payouts[$uid]['role'] ?? 'buyer_l1',
            ];
        }
    }

    foreach ($payouts as $uid => $info) {
        mkt_add_balance((int) $uid, (float) $info['amount']);
        mkt_log('referral_payout', (int) $uid, 'Реферальный бонус', [
            'amount'      => $info['amount'],
            'from_amount' => $amount,
            'order_id'    => $order_id,
            'buyer_id'    => $buyer_id,
            'seller_id'   => $seller_id,
            'role'        => $info['role'],
        ]);
    }
}
