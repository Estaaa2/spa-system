<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LOCAL TESTING ONLY — gives a fresh copy of the 8 identity test accounts:
 * removes them (IdentityVerificationTestRemoveSeeder), then seeds them again
 * (IdentityVerificationTestSeeder). Nothing else in the database changes.
 *
 * The home-visit test bookings (HomeVisitTestSeeder) point at Vera, so if they
 * exist they are reset too: removed first, seeded again at the end.
 *
 *   php artisan db:seed --class=IdentityVerificationTestResetSeeder
 */
class IdentityVerificationTestResetSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('IdentityVerificationTestResetSeeder refuses to run in production.');

            return;
        }

        $hadHomeVisits = DB::table('users')->where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->exists();

        if ($hadHomeVisits) {
            $this->call(HomeVisitTestRemoveSeeder::class);

            if (DB::table('users')->where('email', HomeVisitTestSeeder::CUSTOMER_EMAIL)->exists()) {
                $this->command?->error('The home-visit test data could not be removed (see above), so the identity accounts were not reset.');

                return;
            }
        }

        $this->call(IdentityVerificationTestRemoveSeeder::class);

        if (DB::table('users')->whereIn('email', IdentityVerificationTestSeeder::emails())->exists()) {
            $this->command?->error('The old identity test accounts are still there (see above), so they were not seeded again.');

            return;
        }

        $this->call(IdentityVerificationTestSeeder::class);

        if ($hadHomeVisits) {
            $this->call(HomeVisitTestSeeder::class);
        }
    }
}
