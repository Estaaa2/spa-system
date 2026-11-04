<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\CommissionRule;
use App\Models\LeaveRequest;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffPayProfile;
use App\Models\Treatment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * LOCAL TESTING ONLY — fake spa, two branches and one account per role for the
 * Phase 7 localhost test guide (payroll, attendance & leave, branch switcher).
 *
 *   php artisan migrate:fresh
 *   php artisan db:seed --class=PayrollTestAccountsSeeder
 *
 * Every name, email (@levictas.test — a reserved test domain that never delivers)
 * and amount is invented. Wage amounts are SAMPLE values, not a wage order.
 * No statutory ID numbers are seeded. Refuses to run in production or when the
 * test spa already exists. Not called by DatabaseSeeder.
 */
class PayrollTestAccountsSeeder extends Seeder
{
    public const PASSWORD = 'Levictas-Test1!';
    public const SPA_NAME = 'Levictas Test Spa (fake data)';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('PayrollTestAccountsSeeder refuses to run in production.');

            return;
        }
        if (Spa::withoutGlobalScopes()->where('name', self::SPA_NAME)->exists()) {
            $this->command?->warn('The test spa already exists — run php artisan migrate:fresh first for a clean copy.');

            return;
        }
        if (! Role::where('name', 'owner')->exists()) {
            $this->call(RolePermissionSeeder::class);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () {
            $this->seedWorld();
        });

        $this->command?->info('Test accounts created. Password for every account: '.self::PASSWORD);
        $this->command?->table(['Role', 'Email', 'Branch'], $this->accountTable);
    }

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $accountTable = [];

    private function seedWorld(): void
    {
        $today = Carbon::today();
        $password = Hash::make(self::PASSWORD);

        // ── Owner, spa, branches ───────────────────────────────────────────
        $owner = $this->make(User::class, [
            'first_name' => 'Olivia', 'last_name' => 'Owner (test)', 'email' => 'owner@levictas.test',
            'password' => $password, 'status' => 'active', 'is_owner' => true, 'email_verified_at' => now(),
        ]);

        $spa = $this->make(Spa::class, [
            'owner_id' => $owner->id, 'name' => self::SPA_NAME, 'business_tier' => 'premium',
            'verification_status' => 'verified', 'verified_at' => now(),
            // Premium trial: payroll needs Premium (config/plans.php).
            'trial_used' => true, 'trial_plan' => 'premium',
            'trial_started_at' => now()->subDays(3), 'trial_ends_at' => now()->addDays(60),
            'payroll_first_cutoff_day' => 15, 'payroll_pay_day_offset' => 5,
        ]);

        $branchA = $this->make(Branch::class, [
            'spa_id' => $spa->id, 'name' => 'Test Branch A', 'location' => 'Sample address A, Cavite',
            'is_main' => true, 'verification_status' => 'verified', 'verified_at' => now(),
            'has_workforce_finance_suite' => true,
            'min_daily_wage' => '600.00', 'wage_order_ref' => 'Sample amount — not a wage order',
            'min_wage_effective_from' => $today->copy()->startOfYear()->toDateString(),
        ]);
        // Branch B has no minimum wage yet: its staff are left out of payroll until it is set
        // (tested in the guide: "Missing rate").
        $branchB = $this->make(Branch::class, [
            'spa_id' => $spa->id, 'name' => 'Test Branch B', 'location' => 'Sample address B, Cavite',
            'is_main' => false, 'verification_status' => 'verified', 'verified_at' => now(),
            'has_workforce_finance_suite' => true,
        ]);

        $owner->forceFill(['spa_id' => $spa->id, 'branch_id' => $branchA->id])->save();
        $owner->assignRole('owner');
        $this->accountTable[] = ['Owner', $owner->email, 'Test Branch A (switches branches)'];

        // ── One account per role ───────────────────────────────────────────
        $people = [
            // key            role            first name  last name       branch    label
            ['hr_head',      'hr_head',      'Hana',     'HR Head',      $branchA, 'HR Head (spa-wide)'],
            ['finance_head', 'finance_head', 'Felix',    'Finance Head', $branchA, 'Finance Head (spa-wide)'],
            ['manager_a',    'manager',      'Marco',    'Manager A',    $branchA, 'Branch Manager'],
            ['hr_a',         'hr',           'Hazel',    'HR A',         $branchA, 'Branch HR'],
            ['finance_a',    'finance',      'Fiona',    'Finance A',    $branchA, 'Branch Finance'],
            ['reception_a',  'receptionist', 'Rina',     'Reception A',  $branchA, 'Receptionist'],
            ['therapist_a1', 'therapist',    'Tess',     'Therapist A1', $branchA, 'Therapist'],
            ['therapist_a2', 'therapist',    'Theo',     'Therapist A2', $branchA, 'Therapist'],
            ['manager_b',    'manager',      'Mila',     'Manager B',    $branchB, 'Branch Manager'],
            ['hr_b',         'hr',           'Hugo',     'HR B',         $branchB, 'Branch HR'],
            ['therapist_b',  'therapist',    'Tina',     'Therapist B',  $branchB, 'Therapist'],
        ];

        $u = [];
        $s = [];
        foreach ($people as [$key, $role, $first, $last, $branch, $label]) {
            $email = str_replace('_', '.', $key).'@levictas.test';
            $user = $this->make(User::class, [
                'first_name' => $first, 'last_name' => $last.' (test)', 'email' => $email,
                'password' => $password, 'status' => 'active', 'email_verified_at' => now(),
                'spa_id' => $spa->id, 'branch_id' => $branch->id,
            ]);
            $user->assignRole($role);
            $u[$key] = $user;
            $s[$key] = $this->make(Staff::class, [
                'user_id' => $user->id, 'spa_id' => $spa->id, 'branch_id' => $branch->id,
                'employment_status' => 'active', 'hire_date' => $today->copy()->subYear()->toDateString(),
            ]);
            $this->accountTable[] = [$label, $email, $branch->name];
        }

        // ── Pay profiles (sample rates). Manager B has none on purpose. ────
        $from = $today->copy()->subMonthsNoOverflow(3)->startOfMonth()->toDateString();
        $profiles = [
            'hr_head'      => ['monthly', '30000.00', false, ['Sunday']],
            'finance_head' => ['monthly', '30000.00', false, ['Sunday']],
            'manager_a'    => ['monthly', '22000.00', false, ['Sunday']],
            'hr_a'         => ['monthly', '20000.00', false, ['Sunday']],
            'finance_a'    => ['monthly', '20000.00', false, ['Sunday']],
            'reception_a'  => ['daily',   '600.00',   false, ['Sunday']],
            'therapist_a1' => ['daily',   '600.00',   true,  ['Sunday']],
            'therapist_a2' => ['daily',   '550.00',   true,  ['Saturday', 'Sunday']],   // below minimum → top-up
            'hr_b'         => ['monthly', '20000.00', false, ['Sunday']],
            'therapist_b'  => ['daily',   '650.00',   true,  ['Monday']],
        ];
        foreach ($profiles as $key => [$basis, $rate, $commission, $rest]) {
            $this->make(StaffPayProfile::class, [
                'staff_id' => $s[$key]->id, 'effective_from' => $from, 'effective_to' => null,
                'pay_basis' => $basis, 'base_rate' => $rate, 'commission_enabled' => $commission,
                'rest_days' => $rest, 'created_by' => $owner->id,
            ]);
        }

        // ── Services and commission rules ──────────────────────────────────
        $swedish = $this->make(Treatment::class, [
            'spa_id' => $spa->id, 'branch_id' => $branchA->id, 'name' => 'Swedish Massage (test)',
            'duration' => 60, 'price' => '1200.00', 'service_type' => 'in_branch_and_home', 'description' => 'Fake test service',
        ]);
        $hotStone = $this->make(Treatment::class, [
            'spa_id' => $spa->id, 'branch_id' => $branchB->id, 'name' => 'Hot Stone Massage (test)',
            'duration' => 90, 'price' => '1500.00', 'service_type' => 'in_branch_only', 'description' => 'Fake test service',
        ]);
        $this->make(CommissionRule::class, [
            'spa_id' => $spa->id, 'branch_id' => null, 'target_type' => CommissionRule::TARGET_DEFAULT, 'target_id' => null,
            'method' => CommissionRule::METHOD_PERCENT, 'value' => '10.0000', 'effective_from' => $from, 'created_by' => $owner->id,
        ]);
        $this->make(CommissionRule::class, [
            'spa_id' => $spa->id, 'branch_id' => $branchA->id, 'target_type' => CommissionRule::TARGET_TREATMENT, 'target_id' => $swedish->id,
            'method' => CommissionRule::METHOD_FLAT, 'value' => '150.0000', 'effective_from' => $from, 'created_by' => $owner->id,
        ]);

        // ── Attendance: last month + this month up to yesterday ────────────
        $start = $today->copy()->subMonthNoOverflow()->startOfMonth();
        // Everyone with a pay profile clocks in, so the run only shows the warnings the guide expects.
        $workers = [
            'hr_head' => $branchA, 'finance_head' => $branchA, 'manager_a' => $branchA, 'hr_a' => $branchA,
            'finance_a' => $branchA, 'reception_a' => $branchA, 'therapist_a1' => $branchA,
            'therapist_a2' => $branchA, 'hr_b' => $branchB, 'therapist_b' => $branchB,
        ];
        for ($d = $start->copy(); $d->lt($today); $d->addDay()) {
            foreach ($workers as $key => $branch) {
                if (in_array($d->format('l'), $profiles[$key][3], true)) {
                    continue;
                }
                $late = $key === 'therapist_a2' && $d->day % 9 === 0;
                $this->make(StaffAttendance::class, [
                    'staff_id' => $s[$key]->id, 'spa_id' => $spa->id, 'branch_id' => $branch->id,
                    'date' => $d->toDateString(), 'status' => $late ? 'late' : 'present',
                    'time_in' => $late ? '09:40:00' : '09:00:00', 'time_out' => '18:00:00',
                    'source' => 'manual', 'marked_by' => $u['hr_a']->id, 'auto_closed' => false,
                ]);
            }
        }

        // ── Completed, paid bookings (commission) ──────────────────────────
        $bookingDay = fn (int $day) => $start->copy()->addDays($day)->toDateString();
        $bookings = [
            [$u['therapist_a1'], $branchA, 'treatment_'.$swedish->id, '1200.00', $bookingDay(2)],
            [$u['therapist_a1'], $branchA, 'treatment_'.$swedish->id, '1200.00', $bookingDay(9)],
            [$u['therapist_a2'], $branchA, 'treatment_'.$swedish->id, '1000.00', $bookingDay(17)],
            [$u['therapist_b'],  $branchB, 'treatment_'.$hotStone->id, '1500.00', $bookingDay(5)],
        ];
        foreach ($bookings as $i => [$therapist, $branch, $service, $total, $date]) {
            $this->make(Booking::class, [
                'spa_id' => $spa->id, 'branch_id' => $branch->id, 'created_by_user_id' => $u['reception_a']->id,
                'therapist_id' => $therapist->id, 'treatment' => $service,
                'status' => 'completed', 'payment_status' => 'paid',
                'amount_paid' => $total, 'total_amount' => $total, 'balance_amount' => '0.00',
                'service_type' => 'in_branch', 'booking_source' => 'walk_in',
                'customer_name' => 'Test Customer '.($i + 1), 'customer_phone' => '09170000000',
                'customer_email' => 'customer'.($i + 1).'@levictas.test',
                'appointment_date' => $date, 'start_time' => '10:00:00', 'end_time' => '11:00:00',
            ]);
        }

        // ── One pending leave request to review ────────────────────────────
        $this->make(LeaveRequest::class, [
            'user_id' => $u['therapist_a2']->id, 'spa_id' => $spa->id, 'branch_id' => $branchA->id,
            'leave_type' => 'vacation', 'start_date' => $today->copy()->addWeek()->toDateString(),
            'end_date' => $today->copy()->addWeek()->addDay()->toDateString(),
            'reason' => 'Test leave request (fake)', 'status' => 'pending',
        ]);
    }

    /**
     * Creates a row with explicit values, bypassing $fillable and global scopes, so the
     * seeder does not depend on how each model guards its attributes.
     *
     * @template T of Model
     * @param  class-string<T>  $class
     * @return T
     */
    private function make(string $class, array $attributes): Model
    {
        $model = new $class();
        $model->forceFill($attributes)->save();

        return $model;
    }
}
