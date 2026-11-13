<?php

namespace Tests\Fixtures;

use App\Models\Applicant;
use App\Models\Branch;
use App\Models\JobPosting;
use App\Models\Spa;
use App\Models\StaffWorkSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * TEST FIXTURE (tests/Fixtures, not shipped: autoload-dev only) — job openings for staff scheduling & hiring Phase 5.
 * Needs PayrollTestAccountsSeeder first (uses its spa, Test Branch A and owner).
 *
 *   php artisan db:seed --class="Tests\Fixtures\JobOpeningsTestSeeder"
 *   php artisan db:seed --class="Tests\Fixtures\JobOpeningsTestRemoveSeeder"   (remove only)
 *   php artisan db:seed --class="Tests\Fixtures\JobOpeningsTestResetSeeder"    (remove, then seed again)
 *
 * Makes, on the main branch: an open "Massage Therapist (test)" posting (2
 * openings, daily pay shown, Wednesday 12–8 PM, Sunday rest), an open "Front
 * Desk (test)" posting (monthly pay hidden, deadline in 14 days, Saturday and
 * Sunday rest) and a draft "Branch Manager (test)" posting, plus two pending
 * applicants (job.applicant1/2@levictas.test) on the therapist posting.
 */
class JobOpeningsTestSeeder extends Seeder
{
    public const TITLE_SUFFIX = ' (test)';
    public const APPLICANT_EMAIL_PREFIX = 'job.applicant';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('JobOpeningsTestSeeder refuses to run in production.');

            return;
        }

        $spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->first();
        $owner = User::where('email', 'owner@levictas.test')->first();
        if (! $spa || ! $owner) {
            $this->command?->error('Run PayrollTestAccountsSeeder first.');

            return;
        }
        $branch = Branch::withoutGlobalScopes()->where('spa_id', $spa->id)->where('is_main', true)->firstOrFail();

        if (JobPosting::withTrashed()->where('spa_id', $spa->id)->where('title', 'like', '%'.self::TITLE_SUFFIX)->exists()) {
            $this->command?->warn('Job-opening test data already exists. Use JobOpeningsTestResetSeeder for a fresh copy.');

            return;
        }

        $schedule = function (array $rest, array $custom = []): array {
            $out = [];
            foreach (StaffWorkSchedule::WEEKDAYS as $d) {
                $out[$d] = in_array($d, $rest, true) ? ['mode' => 'rest']
                    : (isset($custom[$d]) ? ['mode' => 'custom', 'start' => $custom[$d][0], 'end' => $custom[$d][1]] : ['mode' => 'branch']);
            }

            return $out;
        };

        $base = ['spa_id' => $spa->id, 'branch_id' => $branch->id, 'created_by' => $owner->id];

        $therapist = JobPosting::create($base + [
            'title' => 'Massage Therapist'.self::TITLE_SUFFIX, 'role' => 'therapist', 'openings' => 2,
            'employment_type' => 'full_time', 'status' => 'open',
            'schedule' => $schedule(['Sunday'], ['Wednesday' => ['12:00', '20:00']]),
            'pay_basis' => 'daily', 'pay_min' => 600, 'pay_max' => 750, 'show_pay' => true,
            'description' => 'Swedish, shiatsu and hot-stone massage for walk-in and booked guests.',
            'requirements' => 'TESDA Massage Therapy NC II. Fake data for testing.',
        ]);

        JobPosting::create($base + [
            'title' => 'Front Desk'.self::TITLE_SUFFIX, 'role' => 'receptionist', 'openings' => 1,
            'employment_type' => 'part_time', 'status' => 'open', 'deadline' => now()->addDays(14)->toDateString(),
            'schedule' => $schedule(['Saturday', 'Sunday']),
            'pay_basis' => 'monthly', 'pay_min' => 15000, 'pay_max' => 18000, 'show_pay' => false,
            'description' => 'Greets guests, takes bookings and handles payments. Fake data for testing.',
        ]);

        JobPosting::create($base + [
            'title' => 'Branch Manager'.self::TITLE_SUFFIX, 'role' => 'manager', 'openings' => 1,
            'employment_type' => 'full_time', 'status' => 'draft', 'schedule' => $schedule(['Monday']),
        ]);

        foreach ([1 => ['Liza Applicant', true, null], 2 => ['Paolo Applicant', false, 'Cannot work Wednesdays']] as $i => [$name, $fit, $note]) {
            Applicant::create([
                'spa_id' => $spa->id, 'branch_id' => $branch->id, 'job_posting_id' => $therapist->id,
                'position_applied' => 'therapist', 'full_name' => $name.' (test)',
                'email' => self::APPLICANT_EMAIL_PREFIX.$i.'@levictas.test', 'phone' => '0917000000'.$i,
                'address' => 'Imus, Cavite', 'education' => 'vocational',
                'schedule_fit' => $fit, 'schedule_fit_note' => $note,
                'source' => 'website', 'status' => Applicant::STATUS_PENDING,
            ]);
        }

        $this->command?->info('Job-opening test data seeded on '.$branch->name.': 2 open postings, 1 draft, 2 applicants.');
    }
}
