<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\StaffRecurringItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffRecurringItem>
 */
class StaffRecurringItemFactory extends Factory
{
    protected $model = StaffRecurringItem::class;

    public function definition(): array
    {
        return [
            'staff_id'          => Staff::factory(),
            'kind'              => StaffRecurringItem::KIND_EARNING,
            'component_code'    => 'ALLOWANCE',
            'label'             => 'Transportation allowance',
            'amount'            => fake()->randomFloat(2, 100, 1000), // arbitrary test value
            'frequency'         => StaffRecurringItem::FREQ_PER_CUTOFF,
            'start_date'        => now()->startOfYear()->toDateString(),
            'end_date'          => null,
            'authorization_ref' => null,
            'is_active'         => true,
            'created_by'        => User::factory(),
        ];
    }

    /** Fixed loan deduction with an end date (no balance tracking — CUT). */
    public function loan(): static
    {
        return $this->state(fn () => [
            'kind'              => StaffRecurringItem::KIND_DEDUCTION,
            'component_code'    => 'LOAN_DEDUCTION',
            'label'             => 'Cash advance',
            'end_date'          => now()->addMonths(6)->toDateString(),
            'authorization_ref' => 'AUTH-' . fake()->unique()->numerify('#####'),
        ]);
    }

    public function otherDeduction(): static
    {
        return $this->state(fn () => [
            'kind'              => StaffRecurringItem::KIND_DEDUCTION,
            'component_code'    => 'OTHER_DEDUCTION',
            'label'             => 'Uniform',
            'authorization_ref' => 'AUTH-' . fake()->unique()->numerify('#####'),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
