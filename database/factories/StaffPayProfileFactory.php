<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\StaffPayProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffPayProfile>
 */
class StaffPayProfileFactory extends Factory
{
    protected $model = StaffPayProfile::class;

    public function definition(): array
    {
        return [
            'staff_id'           => Staff::factory(),
            'effective_from'     => now()->startOfYear()->toDateString(),
            'effective_to'       => null,
            'pay_basis'          => StaffPayProfile::BASIS_DAILY,
            // Arbitrary test value — NOT a statutory or regional minimum wage.
            'base_rate'          => fake()->randomFloat(2, 500, 900),
            'commission_enabled' => false,
            'rest_days'          => ['Sunday'],
            'created_by'         => User::factory(),
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn () => [
            'pay_basis' => StaffPayProfile::BASIS_MONTHLY,
            // Arbitrary test value.
            'base_rate' => fake()->randomFloat(2, 15000, 25000),
        ]);
    }

    public function withCommission(): static
    {
        return $this->state(fn () => ['commission_enabled' => true]);
    }

    public function commissionOnly(): static
    {
        return $this->state(fn () => ['base_rate' => 0, 'commission_enabled' => true]);
    }

    public function between($from, $to = null): static
    {
        return $this->state(fn () => [
            'effective_from' => $from,
            'effective_to'   => $to,
        ]);
    }
}
