<?php

namespace Database\Factories;

use App\Models\StaffAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Test-only. ADD ONLY IF YOU DON'T ALREADY HAVE ONE. Keys are always passed by the tests. */
class StaffAttendanceFactory extends Factory
{
    protected $model = StaffAttendance::class;

    public function definition(): array
    {
        return [
            'status'      => 'present',
            'time_in'     => '09:00:00',
            'time_out'    => '18:00:00',
            'source'      => 'manual',
            'auto_closed' => false,
        ];
    }
}
