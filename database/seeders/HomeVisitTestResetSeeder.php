<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LOCAL TESTING ONLY — gives a fresh copy of the home-visit test data:
 * removes it (HomeVisitTestRemoveSeeder), then seeds it again
 * (HomeVisitTestSeeder). Nothing else in the database changes.
 *
 *   php artisan db:seed --class=HomeVisitTestResetSeeder
 */
class HomeVisitTestResetSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('HomeVisitTestResetSeeder refuses to run in production.');

            return;
        }

        $this->call(HomeVisitTestRemoveSeeder::class);

        if (DB::table('users')->where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->exists()) {
            $this->command?->error('The old home-visit test data is still there (see above), so it was not seeded again.');

            return;
        }

        $this->call(HomeVisitTestSeeder::class);
    }
}
