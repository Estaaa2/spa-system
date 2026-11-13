<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CommissionRule;
use App\Models\Spa;
use App\Models\StaffPayProfile;
use App\Models\User;
use App\Services\Navigation\NavBadges;
use Database\Seeders\Demo\DemoBuilder;
use Database\Seeders\Demo\DemoCatalog;
use Database\Seeders\Demo\DemoGuard;
use Database\Seeders\Demo\DemoHistory;
use Database\Seeders\Demo\DemoOperations;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * DEMO DATA — 12 fake spas that together show every situation a spa can be in,
 * plus demo customers. Made for the thesis defense and for showing the platform
 * to others; it may run on the production server (see below).
 *
 *   php artisan db:seed --class=DemoDataSeeder --force          (once)
 *   php artisan db:seed --class=DemoDataResetSeeder --force     (fresh copy: remove, then seed again)
 *   php artisan db:seed --class=DemoDataRemoveSeeder --force    (remove only the demo data)
 *
 * Needs in .env (then `php artisan config:clear`):
 *   DEMO_SEED_PASSWORD=...           shared password of every demo account (never commit it)
 *   DEMO_SEED_ALLOW_PRODUCTION=true  only on the production server, only while seeding
 *
 * Two showcase spas have about 3 months of history (bookings, ratings and, on
 * Premium, attendance, leave, payroll, inventory, procurement, hiring and
 * expenses). The other ten each show one situation: on a free trial, trial
 * ended, grace period, plan expired, never started a trial, verification
 * pending / rejected, just registered, no verified therapists, Basic at its
 * limit. See DemoCatalog::SPAS.
 *
 * Dates are relative to the day it runs (the trial "ends in 18 days" from the
 * day it was seeded). Re-run the Reset seeder shortly before a presentation.
 *
 * Every demo email ends in @levictas-demo.test, every demo spa name ends in
 * "(Demo)", and every demo file is under storage/.../demo-data/. Nothing else is
 * touched. Payments are fake (method "test", not live, no PayMongo).
 */
class DemoDataSeeder extends Seeder
{
    private DemoBuilder $b;

    private ?User $admin;

    /** @var list<User> */
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

        if (Spa::withoutGlobalScopes()->withTrashed()->whereIn('name', DemoCatalog::spaNames())->exists()
            || User::withTrashed()->where('email', 'like', DemoCatalog::emailPattern())->exists()) {
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
                $messages = array_merge($messages, $this->serenity());
                $this->hilot();
                $this->lakbay();
                $this->tahimik();
                $this->banayad();
                $this->ginhawa();
                $this->bagong();
                $this->kalinaw();
                $this->payapa();
                $this->unang();
                $this->luntian();
                $this->kalma();
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
        foreach (Spa::withoutGlobalScopes()->whereIn('name', DemoCatalog::spaNames())->pluck('id') as $spaId) {
            NavBadges::bump((int) $spaId);
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

    // ── Customers ──────────────────────────────────────────────────────────

    private function makeCustomers(): void
    {
        $cities = ['Imus', 'Bacoor', 'Dasmariñas', 'General Trias', 'Kawit', 'Tagaytay', 'Silang', 'Tanza'];

        $this->regular = $this->b->user('maria.customer', 'customer', null, null, [
            'first_name' => 'Maria', 'last_name' => 'Dizon', 'address' => 'Blk 4 Lot 12, Anabu II-D, Imus, Cavite',
        ], $this->today->copy()->subDays(120));
        $this->b->account('Customers', 'Customer (regular)', $this->regular, 'Many past visits at Serenity, ratings, an upcoming booking with a reschedule request');

        $new = $this->b->user('new.customer', 'customer', null, null, [
            'first_name' => 'Paolo', 'last_name' => 'Lim', 'address' => 'Salitran II, Dasmariñas, Cavite',
        ], $this->today->copy()->subDay());
        $this->b->account('Customers', 'Customer (brand new)', $new, 'Registered yesterday, no bookings yet');

        for ($i = 1; $i <= 40; $i++) {
            $local = 'guest'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $this->customers[] = $this->b->user($local, 'customer', null, null, [
                'address' => 'Sample St., '.$cities[$i % count($cities)].', Cavite',
            ], $this->today->copy()->subDays(100 + $i));
        }
        $this->b->account('Customers', 'Customer (×40)', $this->customers[0], 'guest01 … guest40 — the online customers in the booking history');
    }

    // ── Showcase 1: Premium, 3 branches, full history ──────────────────────

    private function serenity(): array
    {
        $key = 'serenity';
        $from = $this->today->copy()->subDays(90);
        $registered = $from->copy()->subDays(20);
        [$name, , $defs] = DemoCatalog::SPAS[$key];
        $group = 'Serenity (Premium showcase)';

        $owner = $this->b->user("{$key}.owner", 'owner', null, null, ['first_name' => 'Celeste', 'last_name' => 'Aguinaldo'], $registered);
        $spa = $this->b->spa($owner, DemoCatalog::spaName($key), [
            'business_tier' => 'premium', 'verification_status' => 'verified',
            'verified_at' => $registered->copy()->addDays(2), 'verified_by' => $this->admin?->id,
            'trial_used' => true, 'trial_plan' => 'premium',
            'trial_started_at' => $registered->copy()->addDays(3), 'trial_ends_at' => $registered->copy()->addDays(3)->addMonth(),
            'payroll_first_cutoff_day' => 15, 'payroll_pay_day_offset' => 5,
            'created_at' => $registered, 'updated_at' => $registered,
        ]);
        $this->b->paidMonths($spa, 'premium', $registered->copy()->addDays(3)->addMonth(), $this->today->copy()->addDays(5));
        $this->b->account($group, 'Owner', $owner, 'Sees all 3 branches, Insights/DSS, payroll, inventory, billing');

        $hours = [[10, 21], [10, 21], [11, 22]];
        $branches = [];
        foreach (array_slice($defs, 0, 3) as $i => $def) {
            $branches[] = $this->b->branch($spa, $key, $def, $i === 0, 'verified', true, sprintf('%02d:00', $hours[$i][0]), sprintf('%02d:00', $hours[$i][1]),
                [], $registered, true);
        }
        foreach ($branches as $branch) {
            $branch->forceFill(['min_daily_wage' => '600.00', 'wage_order_ref' => 'Sample amount for the demo — not a wage order',
                'min_wage_effective_from' => $this->today->copy()->startOfYear()->toDateString()])->save();
        }
        $silang = $this->b->branch($spa, $key, $defs[3], false, 'pending', false, '10:00', '20:00', [], $this->today->copy()->subDays(4), true);
        $this->b->documents($spa, $branches, 'verified', $registered->copy()->addDay());
        $this->b->documents($spa, [$silang], 'pending', $this->today->copy()->subDays(3), [], false);
        $this->b->services($spa, $silang, ['swedish', 'hilot', 'foot', 'back']);

        $services = [
            $this->b->services($spa, $branches[0], array_keys(DemoCatalog::TREATMENTS), ['renew', 'heritage', 'pamper']),
            $this->b->services($spa, $branches[1], ['swedish', 'hilot', 'shiatsu', 'ventosa', 'foot', 'aroma', 'back', 'scrub'], ['renew', 'heritage'], 0.95),
            $this->b->services($spa, $branches[2], ['swedish', 'hilot', 'shiatsu', 'hotstone', 'ventosa', 'foot', 'aroma', 'scrub', 'back'], ['renew', 'heritage', 'pamper']),
        ];

        $hired = $registered->copy()->subMonths(8);
        $hrHead = $this->member($spa, $branches[0], "{$key}.hrhead", 'hr_head', $hired, $group, 'HR Head (all branches): attendance, leave, hiring, payroll preparation');
        $finHead = $this->member($spa, $branches[0], "{$key}.financehead", 'finance_head', $hired, $group, 'Finance Head (all branches): approves payroll, vendor bills, expenses');

        $plans = [];
        $managers = $receptions = [];
        $volumes = [7.0, 5.5, 5.0];
        $branchLabels = ['imus', 'bacoor', 'dasma'];
        $therapistCount = [4, 3, 3];
        $hiredStaffUser = null;
        $pendingTherapist = null;
        $staffRows = [$hrHead, $finHead];

        foreach ($branches as $i => $branch) {
            $tag = $branchLabels[$i];
            $staff = [];
            $manager = $this->member($spa, $branch, "{$key}.manager.{$tag}", 'manager', $hired, $group, "Branch manager, {$branch->name}");
            $reception = $this->member($spa, $branch, "{$key}.reception.{$tag}", 'receptionist', $hired, $group,
                $i === 2 ? "Receptionist who also does treatments, {$branch->name}" : "Receptionist, {$branch->name}", $i === 2);
            $staff[] = $manager;
            $staff[] = $reception;
            if ($i === 0) {
                $staff[] = $this->member($spa, $branch, "{$key}.hr.imus", 'hr', $hired, $group, 'Branch HR, Imus');
                $staff[] = $this->member($spa, $branch, "{$key}.finance.imus", 'finance', $hired, $group, 'Branch Finance, Imus');
            }

            $therapists = $i === 2 ? [$reception['user']] : [];
            $quality = [];
            for ($t = 1; $t <= $therapistCount[$i]; $t++) {
                $isPending = $i === 1 && $t === 3;
                $hiredOn = ($i === 0 && $t === 4) ? $this->today->copy()->subDays(95) : $hired;
                $note = $isPending ? "Therapist, {$branch->name} — ID waiting for admin review (can't do home service yet)" : "Therapist, {$branch->name}";
                $m = $this->member($spa, $branch, "{$key}.therapist.{$tag}{$t}", 'therapist', $hiredOn, $group, $note, true, $isPending ? 'pending' : 'approved');
                $staff[] = $m;
                $therapists[] = $m['user'];
                $quality[$m['user']->id] = [4.7, 4.4, 4.8, 3.9][($t + $i) % 4];
                if ($i === 0 && $t === 4) {
                    $hiredStaffUser = $m['user'];
                }
            }
            if ($i === 2) {
                $this->b->identity($reception['user'], $spa, 'approved', $hired->copy()->addDays(2));
                $quality[$reception['user']->id] = 4.3;
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

        $this->payProfiles($spa, $staffRows, $from, $owner);

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

        $count = count($bookings);
        array_unshift($messages, "Serenity: {$count} bookings over 90 days + 7 days ahead");

        return $messages;
    }

    // ── Showcase 2: Basic, 2 branches ──────────────────────────────────────

    private function hilot(): void
    {
        $key = 'hilot';
        $from = $this->today->copy()->subDays(90);
        $registered = $from->copy()->subDays(10);
        $group = 'Hilot Haven (Basic showcase)';
        [, , $defs] = DemoCatalog::SPAS[$key];

        $owner = $this->b->user("{$key}.owner", 'owner', null, null, ['first_name' => 'Rodel', 'last_name' => 'Mercado'], $registered);
        $spa = $this->b->spa($owner, DemoCatalog::spaName($key), $this->verifiedSpa($registered, 'basic'));
        $this->b->paidMonths($spa, 'basic', $registered->copy()->addDays(2)->addMonth(), $this->today->copy()->addDays(9));
        $this->b->account($group, 'Owner', $owner, 'Basic plan: bookings, schedule, Insights/DSS — no HR, payroll or inventory');

        $branches = [];
        foreach ($defs as $i => $def) {
            $branches[] = $this->b->branch($spa, $key, $def, $i === 0, 'verified', true, '09:00', '21:00', [], $registered);
        }
        $this->b->documents($spa, $branches, 'verified', $registered->copy()->addDay());

        $hired = $registered->copy()->subMonths(6);
        $plans = [];
        foreach ($branches as $i => $branch) {
            $tag = $i === 0 ? 'gentri' : 'kawit';
            $services = $this->b->services($spa, $branch, ['swedish', 'hilot', 'ventosa', 'foot', 'back', 'prenatal'], ['heritage'], 0.9);
            $manager = $this->member($spa, $branch, "{$key}.manager.{$tag}", 'manager', $hired, $group, "Branch manager, {$branch->name}");
            $reception = $this->member($spa, $branch, "{$key}.reception.{$tag}", 'receptionist', $hired, $group,
                $i === 1 ? "Receptionist who also does treatments, {$branch->name}" : "Receptionist, {$branch->name}", $i === 1, $i === 1 ? 'approved' : 'none');
            $staff = [$manager, $reception];
            $therapists = $i === 1 ? [$reception['user']] : [];
            $quality = $i === 1 ? [$reception['user']->id => 4.2] : [];
            for ($t = 1; $t <= 2; $t++) {
                $m = $this->member($spa, $branch, "{$key}.therapist.{$tag}{$t}", 'therapist', $hired, $group, "Therapist, {$branch->name}", true, 'approved');
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

    // ── The ten situations ─────────────────────────────────────────────────

    /** On the free trial (full Premium access), 12 days in, 18 days left. */
    private function lakbay(): void
    {
        $start = $this->today->copy()->subDays(12)->setTime(10, 0);
        $spa = $this->smallSpa('lakbay', 'On free trial', $start->copy()->subDay(), [
            'trial_used' => true, 'trial_plan' => 'premium', 'trial_started_at' => $start, 'trial_ends_at' => $start->copy()->addMonth(),
        ], 'Free trial with 18 days left: everything unlocked; the billing page invites them to pick a plan');
        $this->smallTeam($spa, 'lakbay', ['manager', 'receptionist', 'therapist', 'therapist'], 'On free trial', $start, $start, null, 2.5);
    }

    /** Trial ended 8 days ago and nobody paid: the spa is locked (billing page only), data kept. */
    private function tahimik(): void
    {
        $start = $this->today->copy()->subDays(38)->setTime(9, 0);
        $end = $start->copy()->addMonth();
        $spa = $this->smallSpa('tahimik', 'Trial ended, not paid', $start->copy()->subDay(), [
            'trial_used' => true, 'trial_plan' => 'premium', 'trial_started_at' => $start, 'trial_ends_at' => $end,
            'trial_reminder_sent_at' => $end->copy()->subDays(3),
        ], 'LOCKED: trial ended 8 days ago. Only the billing page opens; staff are blocked; the spa is off the public list');
        $this->smallTeam($spa, 'tahimik', ['receptionist', 'therapist', 'therapist'], 'Trial ended, not paid', $start, $start, $end, 2.0);
    }

    /** Basic period ended 6 hours ago: still working for about 2½ more days (3-day grace). */
    private function banayad(): void
    {
        $registered = $this->today->copy()->subDays(80);
        $trialStart = $registered->copy()->addDays(2);
        $spa = $this->smallSpa('banayad', 'Grace period', $registered, [
            'business_tier' => 'basic', 'trial_used' => true, 'trial_plan' => 'premium',
            'trial_started_at' => $trialStart, 'trial_ends_at' => $trialStart->copy()->addMonth(),
        ], 'Plan ended a few hours ago; 3-day grace period — still works, with a "renew now" warning. After the grace days it locks');
        $lapsedAt = now()->subHours(6);
        $this->b->paidPeriod($spa, 'basic', $lapsedAt->copy()->subMonthNoOverflow(), $lapsedAt->copy(), 'new');
        $this->smallTeam($spa, 'banayad', ['receptionist', 'therapist', 'therapist'], 'Grace period', $registered, $this->today->copy()->subDays(30), null, 2.5);
    }

    /** Premium ended 20 days ago (grace over): locked, data kept, can renew anytime. */
    private function ginhawa(): void
    {
        $registered = $this->today->copy()->subDays(112);
        $trialStart = $registered->copy()->addDays(2);
        $spa = $this->smallSpa('ginhawa', 'Plan expired', $registered, [
            'trial_used' => true, 'trial_plan' => 'premium', 'trial_started_at' => $trialStart, 'trial_ends_at' => $trialStart->copy()->addMonth(),
        ], 'LOCKED: Premium ended 20 days ago (grace over). All data is kept; paying again unlocks everything');
        $paidFrom = $trialStart->copy()->addMonth();
        $this->b->paidPeriod($spa, 'premium', $paidFrom->copy(), $paidFrom->copy()->addMonth(), 'new');
        $this->b->paidPeriod($spa, 'premium', $paidFrom->copy()->addMonth(), $this->today->copy()->subDays(20)->setTime(14, 0), 'renewal');
        $this->smallTeam($spa, 'ginhawa', ['manager', 'receptionist', 'therapist', 'therapist'], 'Plan expired', $registered,
            $this->today->copy()->subDays(75), $this->today->copy()->subDays(21), 3.0);
    }

    /** Verified 2 days ago, never started the free trial. */
    private function bagong(): void
    {
        $registered = $this->today->copy()->subDays(4)->setTime(15, 0);
        $spa = $this->smallSpa('bagong', 'Verified, no trial yet', $registered, [],
            'Approved by admin but has not started the free trial: sees the "Start your free trial" screen', false);
        $this->b->services($spa, $spa->branches()->first(), ['swedish', 'hilot', 'foot', 'back']);
    }

    /** Uploaded documents yesterday; waiting for the admin. */
    private function kalinaw(): void
    {
        $at = $this->today->copy()->subDay()->setTime(16, 20);
        $this->unverifiedSpa('kalinaw', 'pending', $at, null,
            'Documents sent yesterday, waiting for admin review. Admin can approve or reject it under Registered Spas');
    }

    /** Admin rejected the documents 5 days ago. */
    private function payapa(): void
    {
        $at = $this->today->copy()->subDays(6)->setTime(11, 0);
        $this->unverifiedSpa('payapa', 'rejected', $at,
            'The Government ID is blurry and the name cannot be read. Please upload a clear photo of the ID and submit again.',
            'Rejected by admin (blurry ID). Sees the reason and can upload a new ID and resubmit');
    }

    /** Finished sign-up and setup, has not uploaded any document. */
    private function unang(): void
    {
        $this->unverifiedSpa('unang', 'unverified', now()->subHours(3), null,
            'Just signed up today: spa and branch set up, no documents uploaded yet');
    }

    /** Basic, active, but no therapist has a verified ID: no home service. */
    private function luntian(): void
    {
        $registered = $this->today->copy()->subDays(50);
        $trialStart = $registered->copy()->addDays(2);
        $spa = $this->smallSpa('luntian', 'No verified therapists', $registered, [
            'business_tier' => 'basic', 'trial_used' => true, 'trial_plan' => 'premium',
            'trial_started_at' => $trialStart, 'trial_ends_at' => $trialStart->copy()->addMonth(),
        ], 'Open for in-branch bookings, but no therapist has a verified ID, so customers cannot book home service');
        $this->b->paidPeriod($spa, 'basic', $trialStart->copy()->addMonth(), $trialStart->copy()->addMonths(2), 'new');
        $this->smallTeam($spa, 'luntian', ['receptionist', 'therapist', 'therapist', 'therapist'], 'No verified therapists',
            $registered, $this->today->copy()->subDays(18), null, 2.0, ['pending', 'rejected', 'none']);
    }

    /** Basic with 3 branches and 10 staff: cannot add more. */
    private function kalma(): void
    {
        $key = 'kalma';
        $registered = $this->today->copy()->subDays(60);
        $trialStart = $registered->copy()->addDays(2);
        $group = 'Kalma (Basic at limit)';
        [, , $defs] = DemoCatalog::SPAS[$key];

        $owner = $this->b->user("{$key}.owner", 'owner', null, null, [], $registered);
        $spa = $this->b->spa($owner, DemoCatalog::spaName($key), $this->verifiedSpa($registered, 'basic'));
        $this->b->paidMonths($spa, 'basic', $trialStart->copy()->addMonth(), $this->today->copy()->addDays(10));
        $this->b->account($group, 'Owner', $owner, 'Basic limit reached (3 of 3 branches, 10 of 10 staff): adding another shows the upgrade prompt');

        $plans = [];
        $hired = $registered->copy()->subMonths(2);
        foreach ($defs as $i => $def) {
            $branch = $this->b->branch($spa, $key, $def, $i === 0, 'verified', true, '10:00', '20:00', [], $registered);
            if ($i === 0) {
                $this->b->documents($spa, [$branch], 'verified', $registered->copy()->addDay());
            } else {
                $this->b->documents($spa, [$branch], 'verified', $registered->copy()->addDays(3), [], false);
            }
            $tag = ['cavite', 'amadeo', 'alfonso'][$i];
            $roles = $i === 0 ? ['manager', 'receptionist', 'therapist', 'therapist'] : ['manager', 'receptionist', 'therapist'];
            $staff = [];
            $therapists = [];
            $tx = 0;
            foreach ($roles as $role) {
                $isTx = $role === 'therapist';
                $m = $this->member($spa, $branch, "{$key}.{$role}.{$tag}".($isTx ? ++$tx : ''), $role, $hired, $group,
                    $this->roleLabel($role).', '.$branch->name, $isTx, $isTx ? 'approved' : 'none');
                $staff[] = $m;
                if ($isTx) {
                    $therapists[] = $m['user'];
                }
            }
            $plans[] = ['branch' => $branch, 'services' => $this->b->services($spa, $branch, ['swedish', 'foot', 'back', 'hilot']),
                'therapists' => $therapists, 'staff' => $staff, 'reception' => $staff[1]['user'], 'manager' => $staff[0]['user'],
                'volume' => 1.6, 'open' => 10, 'close' => 20, 'quality' => []];
        }
        (new DemoHistory($this->b, $this->customers, 7007))->run($spa, $plans, $this->today->copy()->subDays(25));
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /** Attributes of a verified spa that went through the free trial (3 days after sign-up). */
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

    /** A verified one-branch spa with its owner and documents. */
    private function smallSpa(string $key, string $group, Carbon $registered, array $attributes, string $ownerNote, bool $listed = true): Spa
    {
        [, , $defs] = DemoCatalog::SPAS[$key];

        $owner = $this->b->user("{$key}.owner", 'owner', null, null, [], $registered);
        $spa = $this->b->spa($owner, DemoCatalog::spaName($key), $attributes + [
            'business_tier' => 'basic', 'verification_status' => 'verified',
            'verified_at' => $registered->copy()->addDays(2), 'verified_by' => $this->admin?->id,
            'created_at' => $registered, 'updated_at' => $registered,
        ]);
        $branch = $this->b->branch($spa, $key, $defs[0], true, 'verified', $listed, '10:00', '20:00', [], $registered);
        $this->b->documents($spa, [$branch], 'verified', $registered->copy()->addDay());
        $this->b->account($group, 'Owner', $owner, $ownerNote);

        return $spa;
    }

    /**
     * Staff and (optionally) a short booking history for a one-branch spa.
     *
     * @param list<string> $roles
     * @param list<string> $therapistIds identity state per therapist, in order (default: all approved)
     */
    private function smallTeam(Spa $spa, string $key, array $roles, string $group, Carbon $hired, Carbon $from, ?Carbon $until, float $volume, array $therapistIds = []): void
    {
        $branch = $spa->branches()->first();
        $services = $this->b->services($spa, $branch, ['swedish', 'hilot', 'foot', 'back', 'ventosa']);
        $staff = [];
        $therapists = [];
        $n = 0;
        foreach ($roles as $role) {
            $isTx = $role === 'therapist';
            $idState = $isTx ? ($therapistIds[$n] ?? 'approved') : 'none';
            $suffix = $isTx ? ++$n : '';
            $note = ucfirst($role).($isTx ? match ($idState) {
                'pending' => ' — ID waiting for admin review', 'rejected' => ' — ID rejected (blurry), must resubmit',
                'none' => ' — never submitted an ID (no photo)', default => '',
            } : '');
            $m = $this->member($spa, $branch, "{$key}.{$role}{$suffix}", $role, $hired->copy()->subMonths(3), $group, $note, $isTx, $idState);
            $staff[] = $m;
            if ($isTx) {
                $therapists[] = $m['user'];
            }
        }

        if ($volume > 0) {
            $reception = collect($staff)->firstWhere('role', 'receptionist')['user'] ?? null;
            $manager = collect($staff)->firstWhere('role', 'manager')['user'] ?? null;
            (new DemoHistory($this->b, $this->customers, crc32($key)))->run($spa, [[
                'branch' => $branch, 'services' => $services, 'therapists' => $therapists, 'staff' => $staff,
                'reception' => $reception, 'manager' => $manager, 'volume' => $volume, 'open' => 10, 'close' => 20, 'quality' => [],
            ]], $from, $until ? ['until' => $until] : []);
        }
    }

    /** A spa whose documents are pending / rejected, or that has none yet. */
    private function unverifiedSpa(string $key, string $state, Carbon $at, ?string $remarks, string $note): void
    {
        [, , $defs] = DemoCatalog::SPAS[$key];
        $registered = $at->copy()->subHours(2);
        $owner = $this->b->user("{$key}.owner", 'owner', null, null, [], $registered);
        $spa = $this->b->spa($owner, DemoCatalog::spaName($key), [
            'business_tier' => 'basic', 'verification_status' => $state, 'verification_remarks' => $remarks,
            'created_at' => $registered, 'updated_at' => $at,
        ]);
        $branch = $this->b->branch($spa, $key, $defs[0], true, $state === 'rejected' ? 'rejected' : 'unverified', false, '10:00', '20:00', [], $registered);
        if ($state === 'rejected') {
            $branch->forceFill(['verification_remarks' => $remarks])->save();
        }
        $owner->forceFill(['branch_id' => $branch->id])->saveQuietly();
        if ($state !== 'unverified') {
            $this->b->documents($spa, [$branch], $state, $at, $state === 'rejected'
                ? ['government_id' => 'Blurry photo: the name and ID number cannot be read.'] : []);
        }
        $this->b->account(DemoCatalog::SPAS[$key][0], 'Owner', $owner, $note);
    }

    /**
     * One staff member: account, role, staff row, optional photo + identity check.
     *
     * @param 'approved'|'pending'|'rejected'|'none' $identity
     * @return array{user: User, staff: \App\Models\Staff, role: string}
     */
    private function member(Spa $spa, Branch $branch, string $local, string $role, Carbon $hired, string $group, string $note,
        bool $treats = false, string $identity = 'none'): array
    {
        $user = $this->b->user($local, $role, $spa, $branch, [], $hired);
        $staff = $this->b->staff($user, $spa, $branch, $treats && $role !== 'therapist', $hired);
        if ($identity !== 'none') {
            $this->b->identity($user, $spa, $identity, $hired->copy()->addDays(3)->max($this->today->copy()->subDays(160)));
            if ($identity === 'pending') {
                DB::table('identity_verifications')->where('user_id', $user->id)->update([
                    'submitted_at' => now()->subHours(20), 'consent_given_at' => now()->subHours(20), 'created_at' => now()->subHours(20),
                ]);
            }
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

    /** Maria's next visit (in 3 days) and her request to move it. */
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
            'customer_name' => 'Maria Dizon', 'customer_email' => $this->regular->email, 'customer_phone' => $this->regular->phone,
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
