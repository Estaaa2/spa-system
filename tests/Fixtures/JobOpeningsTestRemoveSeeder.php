<?php

namespace Tests\Fixtures;

use App\Models\Spa;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * TEST FIXTURE (tests/Fixtures, not shipped: autoload-dev only) — removes what JobOpeningsTestSeeder made, and nothing else:
 * job postings of the test spa whose title ends in " (test)" (also soft-deleted
 * ones), the applicants linked to them and those applicants' interviews.
 *
 * Stops without removing anything if one of those applicants was hired (a staff
 * account exists), because deleting the applicant would leave that account
 * behind. Use PayrollTestAccountsResetSeeder to reset everything instead.
 *
 *   php artisan db:seed --class="Tests\Fixtures\JobOpeningsTestRemoveSeeder"
 */
class JobOpeningsTestRemoveSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('JobOpeningsTestRemoveSeeder refuses to run in production.');

            return;
        }

        $spaId = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->value('id');
        $postingIds = $spaId
            ? DB::table('job_postings')->where('spa_id', $spaId)->where('title', 'like', '%'.JobOpeningsTestSeeder::TITLE_SUFFIX)->pluck('id')->all()
            : [];

        if ($postingIds === []) {
            $this->command?->info('No job-opening test data found — nothing to remove.');

            return;
        }

        $applicantIds = DB::table('applicants')->whereIn('job_posting_id', $postingIds)->pluck('id')->all();

        $hired = DB::table('staff')->whereIn('applicant_id', $applicantIds)->pluck('id')->all();
        if ($hired !== []) {
            $this->command?->error('Nothing was removed. Applicants of the test postings were hired (staff IDs: '.implode(', ', $hired).').');
            $this->command?->line('Reset all test data with PayrollTestAccountsResetSeeder instead.');

            return;
        }

        DB::transaction(function () use ($postingIds, $applicantIds) {
            DB::table('interviews')->whereIn('applicant_id', $applicantIds)->delete();
            DB::table('applicants')->whereIn('id', $applicantIds)->delete();
            DB::table('job_postings')->whereIn('id', $postingIds)->delete();
        });

        $this->command?->info('Removed '.count($postingIds).' test job postings and '.count($applicantIds).' applicants.');
    }
}
