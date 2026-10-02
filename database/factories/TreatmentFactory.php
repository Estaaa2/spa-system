<?php

namespace Database\Factories;

use App\Models\Treatment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Test-only. ADD ONLY IF YOU DON'T ALREADY HAVE ONE. spa_id / branch_id passed by the tests. */
class TreatmentFactory extends Factory
{
    protected $model = Treatment::class;

    public function definition(): array
    {
        return [
            'name'         => 'Swedish Massage',
            'duration'     => 60,
            'price'        => '1500.00',
            'service_type' => 'in_branch_and_home',
            'description'  => 'Test treatment',
        ];
    }
}
