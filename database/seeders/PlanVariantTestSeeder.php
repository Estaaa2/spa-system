<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\Treatment;
use App\Models\User;
use App\Services\Payroll\PayrollPermissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * LOCAL TESTING ONLY — three extra fake spas for the sidebar & RBAC rework test
 * matrix (answer P18): the cases PayrollTestAccountsSeeder (2 branches, Premium)
 * does not cover.
 *
 *   php artisan db:seed --class=PlanVariantTestSeeder          (once)
 *   php artisan db:seed --class=PlanVariantTestResetSeeder     (fresh copy of only these spas)
 *   php artisan db:seed --class=PlanVariantTestRemoveSeeder    (remove only these spas)
 *
 *   solo       1 branch,   Premium (trial)   every business role + receptionist who does treatments
 *   basic      2 branches, Basic (paid)      owner, manager, receptionist (+ treatments), therapist
 *   solobasic  1 branch,   Basic (paid)      owner, manager, receptionist (+ treatments), therapist
 *
 * HR and Finance roles are Premium-only, so the Basic spas have none.
 * Emails: <spa key>.<account>@levictas.test (a reserved test domain that never
 * delivers), password PayrollTestAccountsSeeder::PASSWORD. The Basic plan is a
 * fake paid period (payment_method "test", no PayMongo). Every name is invented.
 * Independent of the other test seeders. Refuses production, and stops if any of
 * the three spas already exists (use the Reset seeder for a fresh copy).
 */
class PlanVariantTestSeeder extends Seeder
{
    /** key => [spa name, plan, number of branches] */
    public const SPAS = [
        'solo'      => ['Levictas Test Spa — 1 branch, Premium (fake data)', 'premium', 1],
        'basic'     => ['Levictas Test Spa — 2 branches, Basic (fake data)', 'basic', 2],
        'solobasic' => ['Levictas Test Spa — 1 branch, Basic (fake data)', 'basic', 1],
    ];

    /** Branch name prefix per spa. */
    private const BRANCH_PREFIX = ['solo' => 'Solo', 'basic' => 'Basic', 'solobasic' => 'Solo Basic'];

    /** account => [role, first name, last name, performs treatments, premium only, branch index] */
    private const PEOPLE = [
        'manager'      => ['manager',      'Mara',  'Manager',               false, false, 0],
        'reception'    => ['receptionist', 'Remy',  'Reception',             false, false, 0],
        'reception.tx' => ['receptionist', 'Rosa',  'Reception Treatments',  true,  false, 0],
        'therapist'    => ['therapist',    'Tala',  'Therapist',             false, false, 0],
        'hr'           => ['hr',           'Hiro',  'HR',                    false, true,  0],
        'finance'      => ['finance',      'Fe',    'Finance',               false, true,  0],
        'hr.head'      => ['hr_head',      'Hans',  'HR Head',               false, true,  0],
        'finance.head' => ['finance_head', 'Faye',  'Finance Head',          false, true,  0],
        'manager.b'    => ['manager',      'Mon',   'Manager B',             false, false, 1],
        'therapist.b'  => ['therapist',    'Teo',   'Therapist B',           false, false, 1],
    ];

    /** Every spa name, for the Remove seeder. @return list<string> */
    public static function spaNames(): array
    {
        return array_column(self::SPAS, 0);
    }

    /** @var list<array{0: string, 1: string, 2: string, 3: string}> */
    private array $accountTable = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('PlanVariantTestSeeder refuses to run in production.');

            return;
        }

        if (Spa::withoutGlobalScopes()->whereIn('name', self::spaNames())->exists()) {
            $this->command?->warn('The plan-variant test spas already exist. For a fresh copy of only this data, run: php artisan db:seed --class=PlanVariantTestResetSeeder');

            return;
        }

        if (! Role::where('name', 'owner')->exists()) {
            $this->call(RolePermissionSeeder::class);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function () {
            $password = Hash::make(PayrollTestAccountsSeeder::PASSWORD);
            foreach (self::SPAS as $key => [$name, $plan, $branchCount]) {
                $this->seedSpa($key, $name, $plan, $branchCount, $password);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info('Plan-variant test spas created. Password for every account: '.PayrollTestAccountsSeeder::PASSWORD);
        $this->command?->table(['Spa', 'Role', 'Email', 'Branch'], $this->accountTable);
    }

    private function seedSpa(string $key, string $name, string $plan, int $branchCount, string $password): void
    {
        $today = Carbon::today();

        $owner = $this->make(User::class, [
            'first_name' => 'Owen', 'last_name' => self::BRANCH_PREFIX[$key].' Owner (test)', 'email' => "{$key}.owner@levictas.test",
            'password' => $password, 'status' => 'active', 'is_owner' => true, 'email_verified_at' => now(),
        ]);

        $isPremium = $plan === 'premium';

        $spa = $this->make(Spa::class, [
            'owner_id' => $owner->id, 'name' => $name, 'business_tier' => $plan,
            'verification_status' => 'verified', 'verified_at' => now(),
            'trial_used' => true,
            // Premium: a running trial (every trial is Premium). Basic: the trial is
            // over and a paid Basic period is running (added below).
            'trial_started_at' => $isPremium ? now()->subDays(3) : now()->subDays(40),
            'trial_ends_at'    => $isPremium ? now()->addDays(60) : now()->subDays(10),
        ]);

        if (! $isPremium) {
            $this->make(Subscription::class, [
                'spa_id' => $spa->id, 'business_tier' => 'basic', 'kind' => 'new',
                'amount' => config('plans.basic.monthly_price', 799), 'credit_amount' => 0,
                'billing_cycle' => 'monthly', 'payment_method' => 'test', 'livemode' => false,
                'payment_status' => 'paid', 'status' => 'active', 'paid_at' => now()->subDays(10),
                'starts_at' => now()->subDays(10), 'expires_at' => now()->addDays(50),
            ]);
        }

        $branches = [];
        for ($i = 0; $i < $branchCount; $i++) {
            $letter = chr(65 + $i);
            $branches[$i] = $this->make(Branch::class, [
                'spa_id' => $spa->id, 'name' => self::BRANCH_PREFIX[$key]." Branch {$letter}",
                'location' => "Sample address {$letter}, Cavite",
                'is_main' => $i === 0, 'verification_status' => 'verified', 'verified_at' => now(),
                'has_workforce_finance_suite' => $isPremium,
            ]);

            // One service per branch, so Services and Book are not empty.
            $this->make(Treatment::class, [
                'spa_id' => $spa->id, 'branch_id' => $branches[$i]->id, 'name' => 'Test Massage '.$letter.' (test)',
                'duration' => 60, 'price' => '1000.00', 'service_type' => 'in_branch_only', 'description' => 'Fake test service',
            ]);
        }

        $owner->forceFill(['spa_id' => $spa->id, 'branch_id' => $branches[0]->id])->save();
        $owner->assignRole('owner');
        $this->accountTable[] = [$key, 'Owner', $owner->email, $branches[0]->name];

        foreach (self::PEOPLE as $account => [$role, $first, $last, $performs, $premiumOnly, $branchIndex]) {
            if (($premiumOnly && ! $isPremium) || ! isset($branches[$branchIndex])) {
                continue;
            }

            $branch = $branches[$branchIndex];
            $user = $this->make(User::class, [
                'first_name' => $first, 'last_name' => $last.' (test)', 'email' => "{$key}.{$account}@levictas.test",
                'password' => $password, 'status' => 'active', 'email_verified_at' => now(),
                'spa_id' => $spa->id, 'branch_id' => $branch->id,
            ]);
            $user->assignRole($role);

            $this->make(Staff::class, [
                'user_id' => $user->id, 'spa_id' => $spa->id, 'branch_id' => $branch->id,
                'employment_status' => 'active', 'hire_date' => $today->copy()->subYear()->toDateString(),
                'performs_treatments' => $performs,
            ]);

            $label = $performs ? 'Receptionist + does treatments' : PayrollPermissions::roleLabel($role);
            $this->accountTable[] = [$key, $label, $user->email, $branch->name];
        }
    }

    /**
     * Creates a row with explicit values, bypassing $fillable and global scopes
     * (same helper as PayrollTestAccountsSeeder).
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
