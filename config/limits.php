<?php

/*
|--------------------------------------------------------------------------
| Input limits for business-side forms
|--------------------------------------------------------------------------
|
| One place for the upper (and some lower) bounds that the server checks and
| that the modals copy into their min/max attributes. Without an upper bound
| a huge number reaches MySQL, which rejects it as "out of range" and the user
| sees a 500 page instead of a validation message.
|
| Stock quantities already use 20,000 in InventoryController,
| StockTransferController and TreatmentRecipeController; `quantity` below
| matches that so every quantity field in the system has the same ceiling.
|
*/

return [

    // Any single money amount entered by staff: expense requests, product
    // retail price / acquisition cost, stock-in unit cost, supplier and
    // vendor-bill unit cost.
    'money' => 1000000,

    // Treatment price. Research (Nov 2026): neighbourhood spas list short
    // services from ₱150 (10-min foot massage) and promo massages at ₱199;
    // Manila luxury hotel spas go up to about ₱9,300 for a single 150-minute
    // treatment. The ceiling leaves headroom above that.
    'treatment_price' => ['min' => 100, 'max' => 15000],

    // Package price: a bundle of treatments. Luxury multi-treatment packages
    // reach about ₱12,900, and a package can hold several treatments, so the
    // ceiling is higher than a single treatment's.
    'package_price' => ['min' => 100, 'max' => 50000],

    // A fixed-amount promo can never take off more than the most expensive
    // thing it could apply to.
    'promo_fixed_discount' => 50000,

    // Quantities of stock (ordered, received, billed, requested).
    'quantity' => 20000,

    // Supplier lead time.
    'lead_time_days' => 365,

    // Free-text description / notes fields stored in TEXT columns.
    'long_text' => 2000,

];
