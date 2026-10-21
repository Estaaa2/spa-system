<?php

return [
    // Plans an owner can buy. Order = display order, lowest first.
    'purchasable_plans' => ['basic', 'premium'],

    // The free trial gives full access, so it runs on this plan.
    'trial_plan' => 'premium',

    // One free trial per spa (one owner = one spa).
    'trial_months' => 1,

    // Days of continued access after a paid period ends.
    'grace_days' => 3,

    // How many paid periods may be queued after the current one. 1 lets an
    // owner pay the next period early but stops accidental double payment.
    'max_scheduled_periods' => 1,

    // Smallest amount sent to PayMongo (PHP). PayMongo's documented PHP
    // minimum is 1.00.
    'minimum_charge' => 1,

    // Unpaid checkout rows older than this are marked expired by
    // `subscriptions:expire-pending`. A late payment is still honoured.
    'pending_expiry_hours' => 24,

    // PayMongo Checkout methods offered for plan payments. All four are
    // active by default on an activated PayMongo account. QR Ph and
    // BillEase must be requested from PayMongo before adding them here.
    'payment_methods' => ['gcash', 'paymaya', 'card', 'grab_pay'],
];
