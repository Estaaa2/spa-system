<?php

namespace Tests\Fixtures;

use App\Models\JobPosting;
use Illuminate\Database\Seeder;

/**
 * TEST FIXTURE (tests/Fixtures, not shipped: autoload-dev only) — removes the job-opening test data (JobOpeningsTestRemoveSeeder),
 * then seeds it again (JobOpeningsTestSeeder). Nothing else changes.
 *
 *   php artisan db:seed --class="Tests\Fixtures\JobOpeningsTestResetSeeder"
 */
class JobOpeningsTestResetSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('JobOpeningsTestResetSeeder refuses to run in production.');

            return;
        }

        $this->call(JobOpeningsTestRemoveSeeder::class);

        if (JobPosting::withTrashed()->where('title', 'like', '%'.JobOpeningsTestSeeder::TITLE_SUFFIX)->exists()) {
            $this->command?->error('The old job-opening test data is still there (see above), so it was not seeded again.');

            return;
        }

        $this->call(JobOpeningsTestSeeder::class);
    }
}
