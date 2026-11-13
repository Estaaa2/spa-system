<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoCatalog;
use Database\Seeders\Demo\DemoGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DEMO DATA — gives a fresh copy: removes it (DemoDataRemoveSeeder), then seeds
 * it again (DemoDataSeeder). Nothing else in the database changes.
 *
 *   php artisan db:seed --class=DemoDataResetSeeder --force
 *
 * Use it shortly before a presentation: the demo dates (trial days left, grace
 * period, "today's" bookings) are counted from the day the seeder runs.
 * It re-runs the seeder files as they are NOW.
 */
class DemoDataResetSeeder extends Seeder
{
    public function run(): void
    {
        if (! DemoGuard::environmentAllowed($this->command, 'DemoDataResetSeeder')) {
            return;
        }
        if (DemoGuard::password($this->command) === null) {
            return;   // check before removing anything, so the old copy is not lost for nothing
        }

        $this->call(DemoDataRemoveSeeder::class);

        if (DB::table('spas')->whereIn('name', DemoCatalog::spaNames())->exists()
            || DB::table('users')->where('email', 'like', DemoCatalog::emailPattern())->exists()) {
            $this->command?->error('The old demo data is still there (see above), so it was not seeded again.');

            return;
        }

        $this->call(DemoDataSeeder::class);
    }
}
