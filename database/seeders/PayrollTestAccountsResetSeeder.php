<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LOCAL TESTING ONLY — removes the fake test spa, then seeds it again from the
 * current PayrollTestAccountsSeeder. Other data is not touched.
 *
 *   php artisan db:seed --class=PayrollTestAccountsResetSeeder
 *
 * It re-runs the seeder file as it is NOW. If you edited that file and want the
 * original back, restore it first:  git checkout -- database/seeders/PayrollTestAccountsSeeder.php
 */
class PayrollTestAccountsResetSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('PayrollTestAccountsResetSeeder refuses to run in production.');

            return;
        }

        $this->call(PayrollTestAccountsRemoveSeeder::class);

        if (DB::table('spas')->where('name', PayrollTestAccountsSeeder::SPA_NAME)->exists()) {
            $this->command?->error('The old test spa is still there (see above), so it was not seeded again.');

            return;
        }

        $this->call(PayrollTestAccountsSeeder::class);
    }
}
