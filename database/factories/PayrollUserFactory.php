<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Test-only user factory for the payroll tests, matching the Levictas `users`
 * schema (first_name / last_name — no `name` column). Named PayrollUserFactory
 * so it never clashes with your existing UserFactory.
 */
class PayrollUserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name'  => $this->faker->lastName(),
            'email'      => $this->faker->unique()->safeEmail(),
            'password'   => Hash::make('password'),
            'status'     => 'active',
        ];
    }
}
