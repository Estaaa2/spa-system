<?php

namespace Tests\Feature\Rbac;

use App\Models\Branch;
use App\Models\Spa;
use App\Models\User;
use App\Services\Navigation\NavBadges;
use App\Services\Navigation\Sidebar;
use App\Services\Permissions\PermissionPresets;
use App\Services\Permissions\PermissionRules as R;
use Database\Seeders\IdentityVerificationTestSeeder;
use Database\Seeders\PayrollTestAccountsSeeder;
use Database\Seeders\PlanVariantTestSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Sidebar & RBAC rework, Phase 6: the regression matrix.
 *
 * Every seeded staff account in the four test spas
 *   - Levictas Test Spa (fake data)  2 branches, Premium (PayrollTestAccountsSeeder, + IdentityVerificationTestSeeder)
 *   - solo                           1 branch,   Premium
 *   - basic                          2 branches, Basic (branch B is over the plan's 1-branch limit, so locked)
 *   - solobasic                      1 branch,   Basic
 * at every branch it can stand on (owner / HR Head / Finance Head switch), with
 * default permissions, then with customized ones (every preset; owner trimmed to
 * the protected minimum; a front-desk role with nothing ticked).
 *
 * Phase 6.3 adds: every active staff member has Attendance & Leave and, on the
 * dashboard, the clock-in card (employee self-service).
 *
 * Pass criteria (plan, Phase 6): no empty group, no single-link group, no link to a
 * page that refuses the user (403/404/5xx), a branch switcher only where switching
 * is possible, a landing page that opens, and badges that sit on a visible link and
 * match the refresh endpoint. Drawer keyboard/screen-reader behaviour is a browser
 * check (Playwright), not part of this test.
 *
 * Set NAV_MATRIX_REPORT=/path/file.md to also write the results table.
 * Takes about 90 seconds; skip it in quick runs with --exclude-group=nav-matrix.
 */
#[Group('nav-matrix')]
class NavRbacPhase6MatrixTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $failures = [];

    /** @var list<array> */
    private array $rows = [];

    /** @var array<string, int> path => status, per account+branch (pages are not shared across users) */
    private array $seen = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 09:00:00');
        $this->withoutVite();
        $this->seed(PayrollTestAccountsSeeder::class);
        $this->seed(IdentityVerificationTestSeeder::class);
        $this->seed(PlanVariantTestSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seedBadgeItems();
    }

    /**
     * One item of every badge type at Branch A of the 2-branch Premium spa (the
     * seeder already holds one pending leave request there), so the matrix checks
     * real counts and not only empty badges.
     */
    private function seedBadgeItems(): void
    {
        $spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->firstOrFail();
        $branchA = $spa->branches()->where('is_main', true)->value('id');
        $owner = User::where('email', 'owner@levictas.test')->firstOrFail();
        $therapist = User::where('email', 'therapist.a1@levictas.test')->firstOrFail();
        $make = function (string $class, array $attributes) {
            $model = new $class();
            $model->forceFill($attributes)->save();

            return $model;
        };
        $booking = fn (array $o) => $make(\App\Models\Booking::class, $o + [
            'spa_id' => $spa->id, 'branch_id' => $branchA, 'treatment' => 'treatment_1', 'status' => 'reserved',
            'payment_status' => 'partially_paid', 'total_amount' => 1000, 'amount_paid' => 200, 'balance_amount' => 800,
            'service_type' => 'in_branch', 'booking_source' => 'online', 'customer_name' => 'Matrix Customer',
            'start_time' => '15:00:00', 'end_time' => '16:00:00',
        ]);

        $booking(['therapist_id' => null, 'appointment_date' => '2026-11-04']);
        $booking(['therapist_id' => null, 'appointment_date' => '2026-11-09']);
        $assigned = $booking(['therapist_id' => $therapist->id, 'appointment_date' => '2026-11-06']);
        $make(\App\Models\ReassignmentRequest::class, ['booking_id' => $assigned->id, 'requested_by' => $therapist->id,
            'old_therapist_id' => $therapist->id, 'reason' => 'Matrix reassignment', 'status' => 'pending']);
        $make(\App\Models\RescheduleRequest::class, ['booking_id' => $assigned->id, 'requested_by' => $owner->id,
            'requested_date' => '2026-11-07', 'requested_time' => '10:00:00', 'reason' => 'Matrix reschedule', 'status' => 'pending']);
        $applicant = $make(\App\Models\Applicant::class, ['spa_id' => $spa->id, 'branch_id' => $branchA,
            'full_name' => 'Matrix Applicant', 'email' => 'matrix.applicant@levictas.test', 'status' => \App\Models\Applicant::STATUS_PENDING]);
        $make(\App\Models\Interview::class, ['applicant_id' => $applicant->id, 'spa_id' => $spa->id, 'branch_id' => $branchA,
            'interviewed_by' => $owner->id, 'interview_date' => '2026-11-04', 'interview_time' => '14:00:00',
            'status' => \App\Models\Interview::STATUS_PENDING]);
        $make(\App\Models\PurchaseRequest::class, ['spa_id' => $spa->id, 'branch_id' => $branchA, 'requested_by' => $owner->id,
            'reason' => 'Matrix purchase request', 'status' => \App\Models\PurchaseRequest::STATUS_PENDING]);
        $make(\App\Models\PayrollRun::class, ['spa_id' => $spa->id, 'run_type' => 'regular', 'cutoff_no' => 1,
            'period_start' => '2026-10-01', 'period_end' => '2026-10-15', 'pay_date' => '2026-10-20', 'config_version' => '1',
            'generated_by' => $owner->id, 'status' => \App\Models\PayrollRun::STATUS_DRAFT]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── The matrix ──────────────────────────────────────────────────────────

    public function test_every_account_with_default_permissions(): void
    {
        foreach ($this->staffAccounts() as $user) {
            $this->auditEverywhere($user, 'default');
        }

        $this->finish('default');
    }

    public function test_every_preset_applied_to_every_branch(): void
    {
        $editable = Permission::pluck('name')->all();

        foreach ($this->spas() as $spa) {
            $owner = User::findOrFail($spa->owner_id);
            $roles = $this->rolesIn($spa);

            foreach ($roles as $role) {
                $default = Role::findByName($role)->permissions->pluck('name')->all();
                foreach (PermissionPresets::forRole($role, $default, $editable) as $preset) {
                    if ($preset['key'] === 'default') {
                        continue;
                    }
                    if (! $this->save($owner, $spa, $role, $preset['permissions'])) {
                        continue;   // role locked on this plan (HR / Finance on Basic)
                    }
                    foreach ($this->accountsOf($spa, $role) as $user) {
                        $this->auditEverywhere($user, 'preset: '.$preset['key']);
                    }
                }
                // Back to the default before the next role, so presets don't stack.
                $this->save($owner, $spa, $role, $default);
            }
        }

        $this->finish('presets');
    }

    public function test_owner_trimmed_to_the_protected_minimum(): void
    {
        foreach ($this->spas() as $spa) {
            $owner = User::findOrFail($spa->owner_id);
            // Nothing ticked: the page adds the protected minimum (+ their bases) back.
            $this->save($owner, $spa, 'owner', []);
            $this->auditEverywhere($owner->fresh(), 'owner minimum');
        }

        $this->finish('owner minimum');
    }

    public function test_roles_with_nothing_ticked_still_land_somewhere(): void
    {
        foreach ($this->spas() as $spa) {
            $owner = User::findOrFail($spa->owner_id);
            foreach (['receptionist', 'therapist', 'manager'] as $role) {
                $this->save($owner, $spa, $role, []);
                foreach ($this->accountsOf($spa, $role) as $user) {
                    $this->auditEverywhere($user, 'nothing ticked');
                }
            }
        }

        $this->finish('nothing ticked');
    }

    public function test_a_locked_branch_keeps_only_pages_that_open(): void
    {
        // Branch B waits for document review: its pages are closed, the Whole spa zone stays.
        $spa = Spa::withoutGlobalScopes()->where('name', PayrollTestAccountsSeeder::SPA_NAME)->firstOrFail();
        $spa->branches()->where('is_main', false)->update(['verification_status' => 'pending']);

        foreach (['owner', 'hr.head', 'finance.head', 'manager.b', 'hr.b', 'therapist.b'] as $account) {
            $this->auditEverywhere(User::where('email', "$account@levictas.test")->firstOrFail(), 'branch B locked');
        }

        $this->finish('locked branch');
    }

    // ── One account ─────────────────────────────────────────────────────────

    private function auditEverywhere(User $user, string $variant): void
    {
        $spa = $user->spa;
        $branchIds = $user->canSwitchBranches()
            ? $spa->branches()->orderByDesc('is_main')->orderBy('id')->pluck('id')->all()
            : [$user->branch_id];

        foreach ($branchIds as $branchId) {
            $this->audit($user, (int) $branchId, $variant);
        }
    }

    private function audit(User $user, int $branchId, string $variant): void
    {
        $this->flushPerRequestState();
        $this->seen = [];
        $spa = $user->spa;
        $branch = Branch::withoutGlobalScopes()->findOrFail($branchId);
        $role = $user->getRoleNames()->first();
        $who = sprintf('[%s] %s @ %s', $variant, $user->email, $branch->name);
        $fail = function (string $msg) use ($who) { $this->failures[] = "$who: $msg"; };

        $session = $user->canSwitchBranches() ? ['current_branch_id' => $branchId] : [];
        $this->actingAs($user)->withSession($session);

        // Landing: where login sends them, and it must open.
        $landing = Sidebar::landingUrl($user);
        $landingPath = parse_url($landing, PHP_URL_PATH) ?: '/';
        $landingStatus = $this->pageStatus($landingPath, $session);
        if ($landingStatus !== 200) {
            $fail("landing $landingPath returned $landingStatus");
        }

        // Render a page that has the sidebar (the landing page itself).
        $html = $this->withSession($session)->get($landingPath)->getContent();
        $xp = $this->xpath($html);

        // 1–2. Groups: never empty, never a single link.
        $groups = 0;
        foreach ($xp->query('//*[starts-with(@id,"nav-section-")]') as $section) {
            $groups++;
            $n = $xp->query('.//a[@href]', $section)->length;
            if ($n < 2) {
                $fail(sprintf('group %s has %d link(s)', substr($section->getAttribute('id'), 12), $n));
            }
        }

        // 3. Every sidebar link opens (no 403/404/5xx).
        $links = [];
        foreach ($xp->query('//aside//a[@href]') as $a) {
            $path = parse_url($a->getAttribute('href'), PHP_URL_PATH);
            if ($path && $path !== '/logout') {
                $links[$path] = true;
            }
        }
        $redirects = [];
        foreach (array_keys($links) as $path) {
            $code = $this->pageStatus($path, $session);
            if ($code >= 400) {
                $fail("link $path returned $code");
            } elseif ($code >= 300) {
                $redirects[] = $path;
            }
        }
        if ($redirects) {
            $fail('links redirect instead of opening: '.implode(', ', $redirects));
        }

        // 3b. Phase 6.3: every active staff member can clock in, whatever their permissions —
        // the nightly attendance:finalize-day job marks them absent otherwise.
        $locked = $spa->hasAccess() && $branch->lockReason() !== null;
        if ($user->hasAttendanceSelfService() && ! $locked) {
            if (! isset($links['/attendance'])) {
                $fail('active staff member has no Attendance & Leave link');
            } elseif ($this->pageStatus('/attendance', $session) !== 200) {
                $fail('Attendance & Leave does not open for an active staff member');
            }
            if ($landingPath === '/dashboard' && ! str_contains($html, route('attendance.clock-in'))
                && ! str_contains($html, route('attendance.clock-out')) && ! str_contains($html, "Today's attendance is complete")) {
                $fail('dashboard has no clock-in card for an active staff member');
            }
        }

        // 4. Switcher only when the user can switch and there are 2+ branches.
        $shouldSwitch = $user->canSwitchBranches() && $spa->branches()->count() >= 2;
        $hasSwitcher = str_contains($html, 'branchesDropdown = !branchesDropdown');   // the switcher's button
        if ($shouldSwitch !== $hasSwitcher) {
            $fail('switcher '.($hasSwitcher ? 'shown' : 'missing'));
        }

        // 5. Badges sit on a visible link, show the same count, and match the refresh endpoint.
        $badges = $locked ? [] : NavBadges::forUser($user, $branchId);
        foreach ($badges as $key => $badge) {
            $node = $xp->query(sprintf('//*[@data-nav-badge="%s"]', $key))->item(0);
            if (! $node) {
                $fail("badge $key has no visible link");
            } elseif ((int) $node->getAttribute('data-count') !== $badge['count']) {
                $fail("badge $key shows {$node->getAttribute('data-count')}, expected {$badge['count']}");
            }
        }
        $json = $this->withSession($session)->getJson(route('nav.badges'))->json('badges') ?? [];
        if (array_map(fn ($b) => $b['count'], $json) != array_map(fn ($b) => $b['count'], $badges)) {
            $fail('refresh endpoint disagrees with the rendered badges');
        }
        foreach ($badges as $key => $badge) {
            if (($problem = $this->badgeMatchesPage($key, $badge, $session)) !== null) {
                $fail("badge $key ({$badge['count']}): $problem");
            }
        }

        $this->rows[] = [
            $variant, $spa->currentPlan(), $spa->branches()->count(), $user->email, $role.($user->staff?->performs_treatments ? ' +tx' : ''),
            $branch->name.($locked ? ' (locked: '.$branch->lockReason().')' : ''), $landingPath, $groups, count($links),
            $hasSwitcher ? 'yes' : 'no', $badges ? implode(' ', array_map(fn ($k, $b) => "$k:{$b['count']}", array_keys($badges), $badges)) : '—',
        ];
    }

    /** Does the page the badge links to show the same thing? null = yes, else what differs. */
    private function badgeMatchesPage(string $key, array $badge, array $session): ?string
    {
        $this->flushPerRequestState();
        $get = fn (string $route) => $this->withSession($session)->get(route($route))->getContent();

        switch ($key) {
            case 'appointments':   // "N to assign" panel + reassignment requests
                preg_match('/(\d+) unassigned/', $badge['label'], $m);
                preg_match('/(\d+) reassignment/', $badge['label'], $r);
                $html = $get('appointments.index');
                if (($m[1] ?? 0) && ! str_contains($html, $m[1].' to assign')) {
                    return "page does not say \"{$m[1]} to assign\"";
                }
                // The page loads its reassignment requests from reassignment.index.
                $listed = count($this->withSession($session)->getJson(route('reassignment.index'))->json() ?? []);
                if ((int) ($r[1] ?? 0) !== $listed) {
                    return "page lists $listed reassignment requests";
                }

                return null;
            case 'schedule':
                return str_contains($get('schedule.index'), $badge['count'].' waiting') ? null : "page does not say \"{$badge['count']} waiting\"";
            case 'attendance':     // the page loads its pending list from leave-requests.index
                $n = count($this->withSession($session)->getJson(route('leave-requests.index'))->json() ?? []);

                return $n === $badge['count'] ? null : "page lists $n";
            case 'applicants':
                return str_contains($get('applications.index'), 'Matrix Applicant') ? null : 'applicant not on the page';
            case 'interviews':
                return str_contains($get('interviews.index'), 'Matrix Applicant') ? null : 'interview not on the page';
            case 'purchase-requests':
                return str_contains($get('procurement.purchase-requests.index'), 'Matrix purchase request') ? null : 'request not on the page';
            case 'pay-runs':
                $n = substr_count($get('payroll.index'), 'data-label="Type"');

                return $n >= $badge['count'] ? null : "page lists $n runs";
        }

        return "no page check for badge $key";
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** HTTP status for a GET, with the decision-support page's MySQL-only HOUR() tolerated on sqlite. */
    private function pageStatus(string $path, array $session): int
    {
        if (isset($this->seen[$path])) {
            return $this->seen[$path];
        }
        $this->flushPerRequestState();
        $response = $this->withSession($session)->get($path);
        $code = $response->getStatusCode();

        if ($code === 500 && DB::getDriverName() === 'sqlite'
            && str_contains((string) $response->exception?->getMessage(), 'no such function: HOUR')) {
            $code = 200;   // MySQL-only SQL; the page opens on the real server.
        }

        return $this->seen[$path] = $code;
    }

    /** Memos that would otherwise leak between requests in one PHP process (as in production, one request = one process). */
    private function flushPerRequestState(): void
    {
        app(\App\Services\Permissions\BranchPermissionCache::class)->flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        auth()->user()?->unsetRelations();
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);

        return new DOMXPath($dom);
    }

    /** Saves a role at every branch through the real Roles page. False when the page refuses the role (plan-locked). */
    private function save(User $owner, Spa $spa, string $role, array $permissions): bool
    {
        $this->flushPerRequestState();
        $hidden = ['view business dashboard', 'view spa profile', 'edit spa profile'];
        $main = $spa->branches()->where('is_main', true)->value('id');

        $response = $this->actingAs($owner)->withSession(['current_branch_id' => $main])
            ->put(route('owner.roles-permissions.update', Role::findByName($role)), [
                'permissions' => array_values(array_diff($permissions, $hidden)),
                'apply_all_branches' => 1,
                'confirm_conflicts' => 1,
            ]);

        if (! session()->has('success')) {
            $this->assertNotSame(500, $response->getStatusCode(), "saving $role at {$spa->name} crashed");

            return false;
        }
        NavBadges::bump((int) $spa->id);

        return true;
    }

    /** @return \Illuminate\Support\Collection<int, Spa> */
    private function spas()
    {
        return Spa::withoutGlobalScopes()
            ->whereIn('name', [PayrollTestAccountsSeeder::SPA_NAME, ...PlanVariantTestSeeder::spaNames()])
            ->orderBy('id')->get();
    }

    /** @return list<string> */
    private function rolesIn(Spa $spa): array
    {
        return User::where('spa_id', $spa->id)->get()
            ->map(fn (User $u) => $u->getRoleNames()->first())
            ->filter(fn ($r) => $r && ! in_array($r, ['customer', 'admin'], true))
            ->unique()->values()->all();
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function accountsOf(Spa $spa, string $role)
    {
        return User::where('spa_id', $spa->id)->role($role)->orderBy('id')->get();
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function staffAccounts()
    {
        return User::whereIn('spa_id', $this->spas()->pluck('id'))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['customer', 'admin']))
            ->orderBy('spa_id')->orderBy('id')->get();
    }

    private function finish(string $name): void
    {
        if ($path = env('NAV_MATRIX_REPORT')) {
            $out = "\n### $name\n\n| Variant | Plan | Branches | Account | Role | Standing on | Lands on | Groups | Links | Switcher | Badges |\n|---|---|---|---|---|---|---|---|---|---|---|\n";
            foreach ($this->rows as $r) {
                $out .= '| '.implode(' | ', array_map('strval', $r))." |\n";
            }
            if ($this->failures) {
                $out .= "\n**Failures**\n\n- ".implode("\n- ", $this->failures)."\n";
            }
            file_put_contents($path, $out, FILE_APPEND);
        }

        $this->assertNotEmpty($this->rows, 'the matrix checked nothing');
        $this->assertSame([], $this->failures, implode("\n", $this->failures));
    }
}
