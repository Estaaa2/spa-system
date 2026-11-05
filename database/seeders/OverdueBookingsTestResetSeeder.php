<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LOCAL TESTING ONLY — gives a fresh copy of the overdue-booking test data:
 * removes it (OverdueBookingsTestRemoveSeeder), then seeds it again
 * (OverdueBookingsTestSeeder). Nothing else in the database changes.
 *
 *   php artisan db:seed --class=OverdueBookingsTestResetSeeder
 */
class OverdueBookingsTestResetSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('OverdueBookingsTestResetSeeder refuses to run in production.');

            return;
        }

        $this->call(OverdueBookingsTestRemoveSeeder::class);

        if (DB::table('users')->where('email', OverdueBookingsTestSeeder::CUSTOMER_EMAIL)->exists()) {
            $this->command?->error('The old overdue-booking test data is still there (see above), so it was not seeded again.');

            return;
        }

        $this->call(OverdueBookingsTestSeeder::class);
    }
}
