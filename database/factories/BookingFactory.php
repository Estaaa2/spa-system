<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only. ADD ONLY IF YOU DON'T ALREADY HAVE ONE.
 * spa_id / branch_id / therapist_id / created_by_user_id / treatment are passed by the tests.
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'status'           => 'completed',
            'payment_status'   => 'paid',
            'amount_paid'      => '1000.00',
            'total_amount'     => '1000.00',
            'balance_amount'   => '0.00',
            'service_type'     => 'in_branch',
            'booking_source'   => 'walk_in',
            'customer_name'    => $this->faker->name(),
            'customer_phone'   => '09170000000',
            'customer_email'   => $this->faker->safeEmail(),
            'customer_address' => null,
            'appointment_date' => '2026-08-18',
            'start_time'       => '10:00:00',
            'end_time'         => '11:00:00',
        ];
    }
}
