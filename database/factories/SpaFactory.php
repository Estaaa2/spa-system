<?php

namespace Database\Factories;

use App\Models\Spa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only. ADD ONLY IF YOU DON'T ALREADY HAVE ONE.
 * Matches the `spas` schema dump of 2026-09-25: owner_id and name are required;
 * business_tier / verification_status / payroll settings have DB defaults.
 */
class SpaFactory extends Factory
{
    protected $model = Spa::class;

    public function definition(): array
    {
        return [
            'owner_id' => PayrollUserFactory::new(),
            'name'     => $this->faker->company().' Spa',
        ];
    }
}
