<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LOCAL TESTING ONLY — gives a fresh copy of the plan-variant test spas: removes
 * them (PlanVariantTestRemoveSeeder), then seeds them again (PlanVariantTestSeeder).
 * Nothing else in the database changes.
 *
 *   php artisan db:seed --class=PlanVariantTestResetSeeder
 *
 * It re-runs the seeder file as it is NOW. If you edited that file and want the
 * original back, restore it first:  git checkout -- database/seeders/PlanVariantTestSeeder.php
 */
class PlanVariantTestResetSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('PlanVariantTestResetSeeder refuses to run in production.');

            return;
        }

        $this->call(PlanVariantTestRemoveSeeder::class);

        if (DB::table('spas')->whereIn('name', PlanVariantTestSeeder::spaNames())->exists()) {
            $this->command?->error('The old plan-variant test spas are still there (see above), so they were not seeded again.');

            return;
        }

        $this->call(PlanVariantTestSeeder::class);
    }
}
