<?php

namespace Database\Seeders;

use App\Http\Controllers\Insights\DecisionSupportController;
use App\Models\Branch;
use App\Models\CommissionRule;
use App\Models\DssRecommendation;
use App\Models\Spa;
use App\Models\Staff;
use App\Models\StaffPayProfile;
use App\Models\User;
use App\Services\DecisionSupport\RecommendationEngine;
use App\Services\Navigation\NavBadges;
use Database\Seeders\Demo\DemoBuilder;
use Database\Seeders\Demo\DemoCatalog;
use Database\Seeders\Demo\DemoGuard;
use Database\Seeders\Demo\DemoHistory;
use Database\Seeders\Demo\DemoOperations;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * DEMO DATA — four demo spas and their customers, for the thesis defense and
 * for showing the platform. It may run on the production server (see below).
 *
 *   php artisan db:seed --class=DemoDataSeeder --force          (once)
 *   php artisan db:seed --class=DemoDataResetSeeder --force     (fresh copy: remove, then seed again)
 *   php artisan db:seed --class=DemoDataRemoveSeeder --force    (remove only the demo data)
 *
 * Needs in .env (then `php artisan config:clear`):
 *   DEMO_SEED_PASSWORD=...           shared password of every demo account (never commit it)
 *   DEMO_SEED_ALLOW_PRODUCTION=true  only on the production server, only while seeding
 *
 *   Serenity Wellness Spa  Premium, 3 branches, ~6½ months of tidy day-to-day data:
 *                          bookings, ratings, attendance, leave, payroll, inventory,
 *                          purchasing, hiring, expenses, promos. Shaped so every
 *                          Decision Support rule shows on its own: Bacoor has been
 *                          fully booked for 3 months (hire, staffing, open another
 *                          branch), Dasmariñas has light days, Imus's Hot Stone
 *                          bookings dropped (promo).
 *   Hilot Haven Spa        Basic, 2 branches, 3 months of bookings and ratings.
 *   Ginhawa Body & Soul    Premium ended 20 days ago, never renewed: locked, data kept.
 *   Kalinaw Wellness Spa   Documents sent, waiting for admin review.
 *
 * Dates are counted from the day it runs. Names look real but are invented;
 * every email domain ends in ".test" (never delivers), every spa name ends in
 * "(Demo)", every demo image is under storage/.../demo-data/. Plan payments are
 * fake (method "test", not live, no PayMongo).
 */
class DemoDataSeeder extends Seeder
{
    private DemoBuilder $b;

    private ?User $admin;

    /** @var list<User> customers who book online (not the brand-new one) */
    private array $customers = [];

    private User $regular;

    private Carbon $today;

    public function run(): void
    {
        if (! DemoGuard::environmentAllowed($this->command, 'DemoDataSeeder')) {
            return;
        }
        if (($password = DemoGuard::password($this->command)) === null) {
            return;
        }

        $staffEmail = fn ($q) => $q->where(function ($q) {
            foreach (DemoCatalog::staffEmailPatterns() as $pattern) {
                $q->orWhere('email', 'like', $pattern);
            }
        });
        if (Spa::withoutGlobalScopes()->withTrashed()->whereIn('name', DemoCatalog::spaNames())->exists()
            || User::withTrashed()->where($staffEmail)->exists()
            || User::withTrashed()->whereIn('email', DemoCatalog::customerEmails())->exists()) {
            $this->command?->warn('Demo data already exists. For a fresh copy run: php artisan db:seed --class=DemoDataResetSeeder --force');

            return;
        }

        if (! Role::where('name', 'owner')->exists()) {
            $this->call(RolePermissionSeeder::class);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::role('admin')->orderBy('id')->first();
        $this->b = new DemoBuilder(Hash::make($password), $this->admin);
        $this->today = Carbon::today();

        $started = microtime(true);
        $messages = [];

        try {
            DB::transaction(function () use (&$messages) {
                $this->makeCustomers();
                $messages = $this->serenity();
                $this->hilot();
                $this->ginhawa();
                $this->kalinaw();
            });
        } catch (Throwable $e) {
            Carbon::setTestNow();
            // Nothing was saved in the database; take back the images too.
            Storage::disk('local')->deleteDirectory(DemoCatalog::FILE_DIR);
            Storage::disk('public')->deleteDirectory(DemoCatalog::FILE_DIR);
            $this->command?->error('Demo data was NOT created (nothing was saved): '.$e->getMessage());
            $this->command?->line('  at '.$e->getFile().':'.$e->getLine());

            throw $e;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (Spa::withoutGlobalScopes()->whereIn('name', DemoCatalog::spaNames())->get() as $spa) {
            NavBadges::bump((int) $spa->id);
            if ($spa->hasAccess()) {
                $this->syncDecisionSupport($spa);
            }
        }
        if (class_exists(\App\Services\Hiring\HiringStatus::class)) {
            app(\App\Services\Hiring\HiringStatus::class)->flush();
        }

        foreach ($messages as $message) {
            $this->command?->line('  '.$message);
        }
        $this->command?->info(sprintf('Demo data created in %.0f s. Every account uses the password from DEMO_SEED_PASSWORD.', microtime(true) - $started));
        $this->command?->table(['Spa / group', 'Role', 'Email', 'Note'], $this->b->accounts());
    }

    /** Store today's Decision Support recommendations, as the nightly job would. */
    private function syncDecisionSupport(Spa $spa): void
    {
        $open = app(RecommendationEngine::class)->sync($spa);
        Cache::forever(DecisionSupportController::syncKey((int) $spa->id), now()->toIso8601String());

        $rules = DssRecommendation::where('spa_id', $spa->id)->open()->pluck('rule_key')->countBy()->map(fn ($n, $r) => "{$r}×{$n}")->implode(', ');
        $this->command?->line("  Decision Support, {$spa->name}: {$open} open ({$rules})");
    }

    // ── Customers ──────────────────────────────────────────────────────────

    private function makeCustomers(): void
    {
        $cities = ['Imus', 'Bacoor', 'Dasmariñas', 'General Trias', 'Kawit', 'Tagaytay', 'Silang', 'Tanza'];
        $people = DemoCatalog::customers();

        $this->regular = $this->b->user($people[0], 'customer', null, null,
            ['address' => 'Blk 4 Lot 12, Anabu II-D, Imus, Cavite'], $this->today->copy()->subDays(230));
        $this->b->account('Customers', 'Customer (regular)', $this->regular, 'Many past visits at Serenity, ratings, an upcoming booking with a reschedule request');

        $new = $this->b->user($people[1], 'customer', null, null,
            ['address' => 'Salitran II, Dasmariñas, Cavite'], $this->today->copy()->subDay());
        $this->b->account('Customers', 'Customer (brand new)', $new, 'Registered yesterday, no bookings yet');

        foreach (array_slice($people, 2) as $i => $person) {
            $this->customers[] = $this->b->user($person, 'customer', null, null, [
                'address' => 'Sample St., '.$cities[$i % count($cities)].', Cavite',
            ], $this->today->copy()->subDays(210 - $i));
        }
        $this->b->account('Customers', 'Customer (×'.count($this->customers).')', $this->customers[0], 'The other online customers in the booking history (all @mail.test)');
    }

    // ── Serenity: Premium, 3 branches, clean data, every DSS rule ──────────

    private function serenity(): array
    {
        $key = 'serenity';
        $domain = DemoCatalog::domain($key);
        // Six complete months before last month: what the "open another branch" rule needs.
        $from = $this->today->copy()->startOfMonth()->subMonths(6);
        $busySince = $this->today->copy()->startOfMonth()->subMonths(3);
        $registered = $from->copy()->subDays(20);
        [, , , $defs] = DemoCatalog::SPAS[$key];
        $group = 'Serenity (Premium)';

        $owner = $this->b->user($this->b->nextStaff($domain), 'owner', null, null, [], $registered);
        $spa = $this->b->spa($owner, DemoCatalog::spaName($key), [
            'business_tier' => 'premium', 'verification_status' => 'verified',
            'verified_at' => $registered->copy()->addDays(2), 'verified_by' => $this->admin?->id,
            'trial_used' => true, 'trial_plan' => 'premium',
            'trial_started_at' => $registered->copy()->addDays(3), 'trial_ends_at' => $registered->copy()->addDays(3)->addMonth(),
            'payroll_first_cutoff_day' => 15, 'payroll_pay_day_offset' => 5,
            'created_at' => $registered, 'updated_at' => $registered,
        ]);
        $this->b->paidMonths($spa, 'premium', $registered->copy()->addDays(3)->addMonth(), $this->today->copy()->addDays(5));
        $this->b->account($group, 'Owner', $owner, 'All 3 branches, Decision Support, Reports, payroll, inventory, billing');

        $hours = [[10, 21], [10, 21], [11, 22]];
        $branches = [];
        foreach ($defs as $i => $def) {
            $branches[] = $this->b->branch($spa, $key, $def, $i === 0, 'verified', true, sprintf('%02d:00', $hours[$i][0]),
                sprintf('%02d:00', $hours[$i][1]), [], $registered, true);
        }
        foreach ($branches as $branch) {
            $branch->forceFill(['min_daily_wage' => '600.00', 'wage_order_ref' => 'Sample amount for the demo — not a wage order',
                'min_wage_effective_from' => $this->today->copy()->startOfYear()->toDateString()])->save();
        }
        $this->b->documents($spa, $branches, 'verified', $registered->copy()->addDay());

        $services = [
            $this->b->services($spa, $branches[0], array_keys(DemoCatalog::TREATMENTS), ['renew', 'heritage', 'pamper']),
            $this->b->services($spa, $branches[1], ['swedish', 'hilot', 'shiatsu', 'ventosa', 'foot', 'aroma', 'back', 'scrub'], ['renew', 'heritage'], 0.95),
            $this->b->services($spa, $branches[2], ['swedish', 'hilot', 'shiatsu', 'hotstone', 'ventosa', 'foot', 'aroma', 'scrub', 'back'], ['renew', 'heritage', 'pamper']),
        ];

        $hired = $registered->copy()->subMonths(8);
        $hrHead = $this->member($spa, $branches[0], $domain, 'hr_head', $hired, $group, 'HR Head (all branches): attendance, leave, hiring, prepares payroll');
        $finHead = $this->member($spa, $branches[0], $domain, 'finance_head', $hired, $group, 'Finance Head (all branches): approves payroll, bills, expenses');

        $plans = [];
        $managers = $receptions = [];
        $volumes = [7.0, 6.0, 3.5];
        $therapistCount = [4, 3, 3];
        $hiredStaffUser = null;
        $staffRows = [$hrHead, $finHead];

        foreach ($branches as $i => $branch) {
            $staff = [];
            $manager = $this->member($spa, $branch, $domain, 'manager', $hired, $group, "Branch manager, {$branch->name}");
            $reception = $this->member($spa, $branch, $domain, 'receptionist', $hired, $group,
                $i === 2 ? "Receptionist who also does treatments, {$branch->name}" : "Receptionist, {$branch->name}", $i === 2, $i === 2 ? 'approved' : 'none');
            $staff[] = $manager;
            $staff[] = $reception;
            if ($i === 0) {
                $staff[] = $this->member($spa, $branch, $domain, 'hr', $hired, $group, 'Branch HR, Imus');
                $staff[] = $this->member($spa, $branch, $domain, 'finance', $hired, $group, 'Branch Finance, Imus');
            }

            $therapists = $i === 2 ? [$reception['user']] : [];
            $quality = $i === 2 ? [$reception['user']->id => 4.3] : [];
            for ($t = 1; $t <= $therapistCount[$i]; $t++) {
                $hiredOn = ($i === 0 && $t === 4) ? $this->today->copy()->subDays(150) : $hired;
                $m = $this->member($spa, $branch, $domain, 'therapist', $hiredOn, $group,
                    "Therapist, {$branch->name}".($i === 0 && $t === 4 ? ' (hired through Job Openings)' : ''), true, 'approved');
                $staff[] = $m;
                $therapists[] = $m['user'];
                $quality[$m['user']->id] = [4.7, 4.4, 4.8, 4.1][($t + $i) % 4];
                if ($i === 0 && $t === 4) {
                    $hiredStaffUser = $m['user'];
                }
            }

            $managers[$branch->id] = $manager['user'];
            $receptions[$branch->id] = $reception['user'];
            $staffRows = array_merge($staffRows, $staff);
            $plans[] = [
                'branch' => $branch, 'services' => $services[$i], 'therapists' => $therapists,
                'staff' => $i === 0 ? array_merge([$hrHead, $finHead], $staff) : $staff,
                'reception' => $reception['user'], 'manager' => $manager['user'],
                'volume' => $volumes[$i], 'open' => $hours[$i][0], 'close' => $hours[$i][1], 'quality' => $quality,
            ];
        }

        // Decision Support shape: Bacoor fully booked for the last 3 months; Imus's
        // Hot Stone bookings fell over the last 4 weeks (the stone heater was out).
        $plans[1]['fill'] = ['from' => $busySince, 'ratio' => 1.0];
        $plans[0]['demand'] = ['hotstone' => [$this->today->copy()->subDays(28), 0.2]];

        $this->payProfiles($spa, $staffRows, $from, $owner);
        $this->statutoryIds($staffRows);

        $history = new DemoHistory($this->b, $this->customers, 2026);
        $bookings = $history->run($spa, $plans, $from, ['attendance' => true, 'schedules' => true, 'leave' => true, 'regular' => $this->regular]);
        $this->regularCustomerExtras($spa, $plans[0]);

        $ops = new DemoOperations($this->b);
        $ops->inventory($spa, $plans, $bookings, $from, [
            'owner' => $owner, 'finance' => $finHead['user'], 'managers' => $managers, 'receptions' => $receptions,
        ]);
        $messages = $ops->payroll($spa, $from, $hrHead['user'], $finHead['user'], $owner);
        $ops->hiring($spa, $branches, $hrHead['user'], $managers[$branches[1]->id], $hiredStaffUser);
        $ops->expenses($spa, $branches, $managers, $finHead['user']);
        $ops->promos($spa, $branches[0], $services[0]['treatments']);

        array_unshift($messages, sprintf('Serenity: %d bookings since %s', count($bookings), $from->toFormattedDateString()));

        return $messages;
    }

    // ── Hilot Haven: Basic, 2 branches ─────────────────────────────────────

    private function hilot(): void
    {
        $key = 'hilot';
        $domain = DemoCatalog::domain($key);
        $from = $this->today->copy()->subDays(90);
        $registered = $from->copy()->subDays(10);
        $group = 'Hilot Haven (Basic)';
        [, , , $defs] = DemoCatalog::SPAS[$key];

        $owner = $this->b->user($this->b->nextStaff($domain), 'owner', null, null, [], $registered);
        $spa = $this->b->spa($owner, DemoCatalog::spaName($key), $this->verifiedSpa($registered, 'basic'));
        $this->b->paidMonths($spa, 'basic', $registered->copy()->addDays(2)->addMonth(), $this->today->copy()->addDays(9));
        $this->b->account($group, 'Owner', $owner, 'Basic plan: bookings, schedule, Reports, Decision Support — no HR, payroll or inventory');

        $branches = [];
        foreach ($defs as $i => $def) {
            $branches[] = $this->b->branch($spa, $key, $def, $i === 0, 'verified', true, '09:00', '21:00', [], $registered);
        }
        $this->b->documents($spa, $branches, 'verified', $registered->copy()->addDay());

        $hired = $registered->copy()->subMonths(6);
        $plans = [];
        foreach ($branches as $i => $branch) {
            $services = $this->b->services($spa, $branch, ['swedish', 'hilot', 'ventosa', 'foot', 'back', 'prenatal'], ['heritage'], 0.9);
            $manager = $this->member($spa, $branch, $domain, 'manager', $hired, $group, "Branch manager, {$branch->name}");
            $reception = $this->member($spa, $branch, $domain, 'receptionist', $hired, $group,
                $i === 1 ? "Receptionist who also does treatments, {$branch->name}" : "Receptionist, {$branch->name}", $i === 1, $i === 1 ? 'approved' : 'none');
            $staff = [$manager, $reception];
            $therapists = $i === 1 ? [$reception['user']] : [];
            $quality = $i === 1 ? [$reception['user']->id => 4.2] : [];
            for ($t = 1; $t <= 2; $t++) {
                $m = $this->member($spa, $branch, $domain, 'therapist', $hired, $group, "Therapist, {$branch->name}", true, 'approved');
                $staff[] = $m;
                $therapists[] = $m['user'];
                $quality[$m['user']->id] = $t === 1 ? 4.8 : 4.1;
            }
            $plans[] = ['branch' => $branch, 'services' => $services, 'therapists' => $therapists, 'staff' => $staff,
                'reception' => $reception['user'], 'manager' => $manager['user'], 'volume' => $i === 0 ? 4.5 : 3.0,
                'open' => 9, 'close' => 21, 'quality' => $quality];
        }

        (new DemoHistory($this->b, $this->customers, 4242))->run($spa, $plans, $from);
    }

    // ── Ginhawa: Premium ended 20 days ago → locked ────────────────────────

    private function ginhawa(): void
    {
        $key = 'ginhawa';
        $domain = DemoCatalog::domain($key);
        $registered = $this->today->copy()->subDays(112);
        $trialStart = $registered->copy()->addDays(2);
        $group = 'Ginhawa (plan expired)';
        [, , , $defs] = DemoCatalog::SPAS[$key];

        $owner = $this->b->user($this->b->nextStaff($domain), 'owner', null, null, [], $registered);
        $spa = $this->b->spa($owner, DemoCatalog::spaName($key), [
            'business_tier' => 'premium', 'verification_status' => 'verified',
            'verified_at' => $registered->copy()->addDays(2), 'verified_by' => $this->admin?->id,
            'trial_used' => true, 'trial_plan' => 'premium', 'trial_started_at' => $trialStart, 'trial_ends_at' => $trialStart->copy()->addMonth(),
            'created_at' => $registered, 'updated_at' => $registered,
        ]);
        $paidFrom = $trialStart->copy()->addMonth();
        $this->b->paidPeriod($spa, 'premium', $paidFrom->copy(), $paidFrom->copy()->addMonth(), 'new');
        $this->b->paidPeriod($spa, 'premium', $paidFrom->copy()->addMonth(), $this->today->copy()->subDays(20)->setTime(14, 0), 'renewal');
        $this->b->account($group, 'Owner', $owner, 'LOCKED: Premium ended 20 days ago and was not renewed. Only billing opens; data is kept');

        $branch = $this->b->branch($spa, $key, $defs[0], true, 'verified', true, '10:00', '20:00', [], $registered);
        $this->b->documents($spa, [$branch], 'verified', $registered->copy()->addDay());

        $services = $this->b->services($spa, $branch, ['swedish', 'hilot', 'foot', 'back', 'ventosa']);
        $staff = [];
        $therapists = [];
        foreach (['manager', 'receptionist', 'therapist', 'therapist'] as $role) {
            $isTx = $role === 'therapist';
            $m = $this->member($spa, $branch, $domain, $role, $registered->copy()->subMonths(3), $group,
                $this->roleLabel($role).($isTx ? ' (sees the "spa is locked" page)' : ''), $isTx, $isTx ? 'approved' : 'none');
            $staff[] = $m;
            if ($isTx) {
                $therapists[] = $m['user'];
            }
        }
        (new DemoHistory($this->b, $this->customers, 9191))->run($spa, [[
            'branch' => $branch, 'services' => $services, 'therapists' => $therapists, 'staff' => $staff,
            'reception' => $staff[1]['user'], 'manager' => $staff[0]['user'], 'volume' => 3.0, 'open' => 10, 'close' => 20, 'quality' => [],
        ]], $this->today->copy()->subDays(75), ['until' => $this->today->copy()->subDays(21)]);
    }

    // ── Kalinaw: documents sent yesterday, waiting for the admin ───────────

    private function kalinaw(): void
    {
        $key = 'kalinaw';
        [, , , $defs] = DemoCatalog::SPAS[$key];
        $at = $this->today->copy()->subDay()->setTime(16, 20);
        $registered = $at->copy()->subHours(2);

        $owner = $this->b->user($this->b->nextStaff(DemoCatalog::domain($key)), 'owner', null, null, [], $registered);
        $spa = $this->b->spa($owner, DemoCatalog::spaName($key), [
            'business_tier' => 'basic', 'verification_status' => 'pending',
            'created_at' => $registered, 'updated_at' => $at,
        ]);
        $branch = $this->b->branch($spa, $key, $defs[0], true, 'unverified', false, '10:00', '20:00', [], $registered);
        $this->b->documents($spa, [$branch], 'pending', $at);
        $this->b->account('Kalinaw (waiting for review)', 'Owner', $owner,
            'Documents sent yesterday. Cannot log in until approved; show it from the admin side (Registered Spas)');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** Attributes of a verified spa that went through the free trial (2 days after sign-up). */
    private function verifiedSpa(Carbon $registered, string $tier): array
    {
        $trialStart = $registered->copy()->addDays(2);

        return [
            'business_tier' => $tier, 'verification_status' => 'verified',
            'verified_at' => $registered->copy()->addDays(2), 'verified_by' => $this->admin?->id,
            'trial_used' => true, 'trial_plan' => 'premium', 'trial_started_at' => $trialStart,
            'trial_ends_at' => $trialStart->copy()->addMonth(),
            'created_at' => $registered, 'updated_at' => $registered,
        ];
    }

    /**
     * One staff member: account (next real-sounding name at the spa's domain),
     * role, staff row, optional photo + identity check.
     *
     * @param 'approved'|'pending'|'rejected'|'none' $identity
     * @return array{user: User, staff: Staff, role: string}
     */
    private function member(Spa $spa, Branch $branch, string $domain, string $role, Carbon $hired, string $group, string $note,
        bool $treats = false, string $identity = 'none'): array
    {
        $user = $this->b->user($this->b->nextStaff($domain), $role, $spa, $branch, [], $hired);
        $staff = $this->b->staff($user, $spa, $branch, $treats && $role !== 'therapist', $hired);
        if ($identity !== 'none') {
            $this->b->identity($user, $spa, $identity, $hired->copy()->addDays(3)->max($this->today->copy()->subDays(220)));
        }
        $this->b->account($group, $this->roleLabel($role), $user, $note);

        return ['user' => $user, 'staff' => $staff, 'role' => $role];
    }

    private function roleLabel(string $role): string
    {
        return [
            'hr_head' => 'HR Head', 'finance_head' => 'Finance Head', 'manager' => 'Manager', 'hr' => 'HR',
            'finance' => 'Finance', 'receptionist' => 'Receptionist', 'therapist' => 'Therapist',
        ][$role] ?? ucfirst($role);
    }

    /** Sample pay rates (not a wage order) and commission rules, from the start of the history. */
    private function payProfiles(Spa $spa, array $members, Carbon $from, User $owner): void
    {
        $rates = [
            'hr_head' => ['monthly', '30000.00', false], 'finance_head' => ['monthly', '30000.00', false],
            'manager' => ['monthly', '24000.00', false], 'hr' => ['monthly', '20000.00', false], 'finance' => ['monthly', '20000.00', false],
            'receptionist' => ['daily', '620.00', false], 'therapist' => ['daily', '600.00', true],
        ];
        foreach ($members as $m) {
            [$basis, $rate, $commission] = $rates[$m['role']];
            if ($m['role'] === 'receptionist' && $m['staff']->performs_treatments) {
                $commission = true;
            }
            StaffPayProfile::create([
                'staff_id' => $m['staff']->id, 'effective_from' => $from->toDateString(), 'effective_to' => null,
                'pay_basis' => $basis, 'base_rate' => $rate, 'commission_enabled' => $commission,
                'rest_days' => in_array($m['role'], ['therapist', 'receptionist'], true) ? [] : ['Sunday'],
                'created_by' => $owner->id,
            ]);
        }

        CommissionRule::create([
            'spa_id' => $spa->id, 'branch_id' => null, 'target_type' => CommissionRule::TARGET_DEFAULT, 'target_id' => null,
            'method' => CommissionRule::METHOD_PERCENT, 'value' => '10.0000', 'effective_from' => $from->toDateString(), 'created_by' => $owner->id,
        ]);
        $hotStone = DB::table('treatments')->where('spa_id', $spa->id)->where('name', DemoCatalog::TREATMENTS['hotstone'][0])->pluck('id');
        foreach ($hotStone as $id) {
            CommissionRule::create([
                'spa_id' => $spa->id, 'branch_id' => null, 'target_type' => CommissionRule::TARGET_TREATMENT, 'target_id' => $id,
                'method' => CommissionRule::METHOD_FLAT, 'value' => '150.0000', 'effective_from' => $from->toDateString(), 'created_by' => $owner->id,
            ]);
        }
    }

    /**
     * Government numbers on file, so payroll runs are clean. Every number starts
     * with "00", a prefix that is never issued, so none can belong to a real person.
     */
    private function statutoryIds(array $members): void
    {
        foreach ($members as $n => $m) {
            $seq = str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT);
            $m['staff']->forceFill([
                'tin' => '00012'.$seq,                // 9 digits
                'sss_no' => '000012'.$seq,            // 10 digits
                'philhealth_no' => '00000012'.$seq,   // 12 digits
                'pagibig_no' => '00000034'.$seq,      // 12 digits
            ])->save();
        }
    }

    /** The regular customer's next visit (in 3 days) and her request to move it. */
    private function regularCustomerExtras(Spa $spa, array $plan): void
    {
        $treatment = $plan['services']['treatments']['aroma'];
        $date = $this->today->copy()->addDays(3);
        $created = now()->subDays(2);
        $price = (float) $treatment->price;

        // A therapist who is free 10:00–11:30 that day (or none: staff assign one later).
        $taken = DB::table('bookings')->where('branch_id', $plan['branch']->id)->where('appointment_date', $date->toDateString())
            ->where('start_time', '<', '11:40:00')->where('end_time', '>', '09:50:00')->pluck('therapist_id')->filter()->all();
        $therapist = collect($plan['therapists'])->first(fn (User $u) => $u->identity_verified_at && ! in_array($u->id, $taken));

        $id = DB::table('bookings')->insertGetId([
            'spa_id' => $spa->id, 'branch_id' => $plan['branch']->id, 'customer_user_id' => $this->regular->id,
            'booking_source' => 'online', 'status' => 'reserved', 'payment_status' => 'partially_paid',
            'amount_paid' => round($price * 0.2, 2), 'total_amount' => $price, 'balance_amount' => round($price * 0.8, 2),
            'service_type' => 'in_branch', 'treatment' => 'treatment_'.$treatment->id,
            'customer_name' => $this->regular->first_name.' '.$this->regular->last_name,
            'customer_email' => $this->regular->email, 'customer_phone' => $this->regular->phone,
            'appointment_date' => $date->toDateString(), 'start_time' => '10:00:00', 'end_time' => '11:30:00',
            'therapist_id' => $therapist?->id, 'created_at' => $created, 'updated_at' => $created,
        ]);

        DB::table('reschedule_requests')->insert([
            'booking_id' => $id, 'requested_by' => $this->regular->id,
            'requested_date' => $date->copy()->addDay()->toDateString(), 'requested_time' => '15:00:00',
            'reason' => 'May biglaang lakad po kami sa umaga. Puwede po bang kinabukasan ng 3 PM?', 'status' => 'pending',
            'created_at' => now()->subHours(5), 'updated_at' => now()->subHours(5),
        ]);
    }
}
