<?php

namespace Database\Factories;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only. ADD ONLY IF YOU DON'T ALREADY HAVE ONE.
 * spa_id / branch_id / user_id are always passed by the tests.
 */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        return [
            'employment_status' => 'active',
            'hire_date'         => '2025-06-01',
        ];
    }
}
