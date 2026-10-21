<?php

/*
|--------------------------------------------------------------------------
| Subscription Rules
|--------------------------------------------------------------------------
|
| Rules shared by the access logic (App\Models\Spa), checkout, the PayMongo
| webhook and App\Services\Subscription\SubscriptionBilling. Plan prices,
| limits and features live in config/plans.php.
|
*/

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

    // Display names for PayMongo source types on receipts and history.
    'payment_method_labels' => [
        'gcash' => 'GCash',
        'paymaya' => 'Maya',
        'card' => 'Card',
        'grab_pay' => 'GrabPay',
        'qrph' => 'QR Ph',
    ],

    // Days before the free trial ends that the owner is emailed.
    'trial_reminder_days' => 3,

    // Admin "Extend trial" tool (for pilot spas and test runs). Only spas
    // that have never paid can be extended, so no paid period is moved.
    'trial_extension_max_days' => 30,        // per extension
    'trial_extension_max_total_days' => 60,  // all extensions of one spa

    // Owner-facing names for the plan feature keys in config/plans.php.
    'feature_labels' => [
        'operations' => 'Appointments, walk-ins and daily operations',
        'services' => 'Treatments, packages and promos',
        'this_branch' => 'Branch settings and operating hours',
        'all_branches' => 'Multi-branch management',
        'spa_profile' => 'Spa profile and business documents',
        'billing_page' => 'Subscription and billing',
        'insights' => 'Decision support and reports',
        'branch_public_listing' => 'Public listing on Levictas',
        'online_reservation' => 'Customer online reservations',
        'featured_listing' => 'Featured placement on Levictas',
        'inventory_basic' => 'Products, stock and inventory logs',
        'inventory_full' => 'Batches, transfers, replenishment and goods receiving',
        'manpower' => 'Hiring, interviews and staff deployment',
        'payroll' => 'Payroll and payslips',
        'finance' => 'Revenue, expenses and vendor bills',
        'procurement' => 'Suppliers, purchase requests and orders',
    ],

    // Shown on every plan: these modules are not plan-gated.
    'always_included' => [
        'Scheduling and staff availability',
        'Attendance and leave requests',
    ],
];
