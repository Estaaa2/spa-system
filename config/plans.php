<?php

return [
    'basic' => [
        'name' => 'Basic',
        'monthly_price' => 799,
        'yearly_price' => 7990,
        'max_branches' => 3,
        'max_staff' => 10,
        'features' => [
            'operations',
            'services',
            'this_branch',
            'all_branches',
            'spa_profile',
            'billing_page',
            'insights',
            'branch_public_listing',
            'online_reservation',
        ],
    ],

    'premium' => [
        'name' => 'Premium',
        'monthly_price' => 1499,
        'yearly_price' => 14990,
        'max_branches' => 10,
        'max_staff' => 30,
        'features' => [
            'operations',
            'services',
            'this_branch',
            'all_branches',
            'spa_profile',
            'billing_page',
            'insights',
            'branch_public_listing',
            'featured_listing',
            'online_reservation',
            'inventory_basic',
            'inventory_full',
            'manpower',
            'payroll',
            'finance',
            'procurement',
        ],
    ],

    'expired' => [
        'name' => 'Expired',
        'monthly_price' => 0,
        'yearly_price' => 0,
        'max_branches' => 0,
        'max_staff' => 0,
        'features' => [],
    ],
];