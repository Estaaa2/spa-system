<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Spa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only. ADD ONLY IF YOU DON'T ALREADY HAVE ONE.
 * Matches the `branches` schema dump of 2026-09-25 (name and location required).
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'spa_id'                      => SpaFactory::new(),
            'name'                        => $this->faker->city().' Branch',
            'location'                    => $this->faker->address(),
            'is_main'                     => false,
            'has_workforce_finance_suite' => true,
            'min_daily_wage'              => '600.00',
            'wage_order_ref'              => 'RB IVA-22',
            'min_wage_effective_from'     => '2025-10-05',
        ];
    }
}
