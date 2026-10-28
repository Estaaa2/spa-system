<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LOCAL TESTING ONLY — gives a fresh copy of the 8 identity test accounts:
 * removes them (IdentityVerificationTestRemoveSeeder), then seeds them again
 * (IdentityVerificationTestSeeder). Nothing else in the database changes.
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

        $this->call(IdentityVerificationTestRemoveSeeder::class);

        if (DB::table('users')->whereIn('email', IdentityVerificationTestSeeder::emails())->exists()) {
            $this->command?->error('The old identity test accounts are still there (see above), so they were not seeded again.');

            return;
        }

        $this->call(IdentityVerificationTestSeeder::class);
    }
}
