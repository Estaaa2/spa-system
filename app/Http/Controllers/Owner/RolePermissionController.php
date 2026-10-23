<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchRolePermission;
use App\Models\User;
use App\Services\Payroll\PayrollPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    private array $roleOrder = [
        'owner',
        'manager',
        'receptionist',
        'therapist',
        'hr',
        'finance',
        'hr_head',
        'finance_head',
    ];

    // ── Branch / suite helpers ────────────────────────────────────────────────

    private function getCurrentBranch(): Branch
    {
        $user = auth()->user();

        return Branch::query()
            ->where('id', $user->currentBranchId())
            ->where('spa_id', $user->spa_id)
            ->firstOrFail();
    }

    private function branchSuiteEnabled(): bool
    {
        /*
        * Compatibility method for existing callers.
        * Access is now controlled by spa plan features, not the branch column.
        */
        return (bool) auth()->user()?->spa?->hasFeature('manpower');
    }

    private function workforceEnabled(): bool
    {
        $spa = auth()->user()?->spa;

        return $spa !== null
            && $spa->hasAccess()
            && $spa->hasFeature('manpower');
    }

    private function financeEnabled(): bool
    {
        $spa = auth()->user()?->spa;

        return $spa !== null
            && $spa->hasAccess()
            && $spa->hasFeature('finance');
    }

    private function payrollEnabled(): bool
    {
        $spa = auth()->user()?->spa;

        return $spa !== null
            && $spa->hasAccess()
            && $spa->hasFeature('payroll');
    }

    private function getManageableRoles(): array
    {
        $roles = ['owner', 'manager', 'receptionist', 'therapist'];

        if ($this->workforceEnabled()) {
            $roles[] = 'hr';
            $roles[] = 'hr_head';
        }

        if ($this->financeEnabled()) {
            $roles[] = 'finance';
            $roles[] = 'finance_head';
        }

        return $roles;
    }

    /**
     * The branch whose override rows hold this role's choice for this permission.
     * HR Head / Finance Head work across branches, so all their choices live on the
     * main branch; the owner's payroll choices too (payroll is spa-wide). Everything
     * else is per branch, as before. Mirrors User::permissionBranchId().
     */
    private function overrideBranch(string $roleName, string $permissionName, Branch $current): Branch
    {
        $spaWide = in_array($roleName, PayrollPermissions::HEAD_ROLES, true)
            || ($roleName === 'owner' && PayrollPermissions::isPayrollPermission($permissionName));

        return $spaWide ? ($this->mainBranch() ?? $current) : $current;
    }

    private function mainBranch(): ?Branch
    {
        return auth()->user()?->spa?->mainBranch();
    }

    /** Effective overrides for the role as seen from $branch (main-branch rows where they apply). */
    private function overridesFor(Role $role, Branch $branch, int $spaId): Collection
    {
        $local = BranchRolePermission::query()
            ->where('branch_id', $branch->id)->where('spa_id', $spaId)->where('role_name', $role->name)
            ->pluck('granted', 'permission_name');

        $main = $this->mainBranch();
        if (! $main || (int) $main->id === (int) $branch->id) {
            return $local;
        }

        $mainRows = BranchRolePermission::query()
            ->where('branch_id', $main->id)->where('spa_id', $spaId)->where('role_name', $role->name)
            ->pluck('granted', 'permission_name');

        return $this->editablePermissions()->pluck('name')
            ->mapWithKeys(function ($name) use ($role, $branch, $main, $local, $mainRows) {
                $source = (int) $this->overrideBranch($role->name, $name, $branch)->id === (int) $main->id ? $mainRows : $local;

                return $source->has($name) ? [$name => $source->get($name)] : [];
            });
    }

    private function lockedRoles(): array
    {
        $locked = [];

        if (!$this->workforceEnabled()) {
            $locked[] = [
                'name'   => 'hr',
                'title'  => 'HR and HR Head',
                'reason' => 'Available on the Premium plan.',
            ];
        }

        if (!$this->financeEnabled()) {
            $locked[] = [
                'name'   => 'finance',
                'title'  => 'Finance and Finance Head',
                'reason' => 'Available on the Premium plan.',
            ];
        }

        return $locked;
    }

    // ── Permission visibility ─────────────────────────────────────────────────

    private function shouldHidePermission(string $permissionName): bool
    {
        $name = Str::lower(trim($permissionName));

        $exactHidden = [
            // Admin / platform — never configurable on the business side
            'view admin dashboard',
            'manage admin dashboard',
            'view customer dashboard',
            'edit admin profile',
            'manage system settings',
            'change spa subscriptions',

            // Removed / legacy — superseded by view business dashboard
            'view business dashboard',
            'view owner dashboard',
            'manage owner dashboard',
            'view hr dashboard',
            'view finance dashboard',

            // Owner-only pages that should not be delegatable
            'view spa profile',
            'manage spa profile',
            'edit spa profile',
            'view roles and permissions',
            'manage roles and permissions',
            'view role permissions',
            'manage role permissions',
            'view subscription',
            'manage subscription',
            'view subscription and billing',
            'manage subscription and billing',

            // Deprecated — superseded by the granular 'edit branch general/hours/profile' permissions
            'edit branches',

            // Retired by the payroll RBAC refactor (kept defined, assigned to nobody)
            'edit payroll',
            'delete leave requests',
        ];

        if (in_array($name, $exactHidden, true)) {
            return true;
        }

        if (Str::contains($name, [
            'admin ',
            ' platform',
            'registered spa',
            'registered user',
            'system role',
            'system setting',
        ])) {
            return true;
        }

        return false;
    }

    private function permissionAllowedBySuite(string $permissionName): bool
    {
        $group = $this->resolvePermissionGroup($permissionName);
        $meta  = $this->permissionGroups()[$group] ?? null;

        if (!$meta) {
            return true;
        }

        if (($meta['suite'] ?? null) === 'workforce' && !$this->workforceEnabled()) {
            return false;
        }

        if (($meta['suite'] ?? null) === 'finance' && !$this->financeEnabled()) {
            return false;
        }

        if (($meta['suite'] ?? null) === 'payroll' && !$this->payrollEnabled()) {
            return false;
        }

        return true;
    }

    private function editablePermissions(): Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get()
            ->reject(fn(Permission $p) => $this->shouldHidePermission($p->name))
            ->filter(fn(Permission $p) => $this->permissionAllowedBySuite($p->name))
            ->values();
    }

    // ── Permission labels & descriptions ──────────────────────────────────────

    private function permissionLabel(string $permissionName): string
    {
        $explicit = [
            'view business dashboard'           => 'View Business Dashboard',
            'view dashboard kpis'               => 'Dashboard: KPI Stat Cards',
            'view dashboard revenue'            => 'Dashboard: Revenue & Source Data',
            'view dashboard timeline'           => 'Dashboard: Appointment Timeline',
            'view dashboard therapist status'   => 'Dashboard: Therapist Status Panel',
            'view dashboard alerts'             => 'Dashboard: Operational Alerts',
            'view dashboard booking button'     => 'Dashboard: New Booking Shortcut',
            'view dashboard my today'           => 'Dashboard: My Personal Schedule',
            'edit branch general'               => 'Edit General Information',
            'edit branch hours'                 => 'Edit Operating Hours',
            'edit branch profile'               => 'Edit Public Profile',
            'view payroll'                      => 'View Payroll',
            'manage payroll setup'              => 'Manage Payroll Setup',
            'prepare payroll runs'              => 'Prepare Pay Runs',
            'approve payroll runs'              => 'Approve Pay Runs',
            'release payroll runs'              => 'Finalize & Release Pay Runs',
            'reveal statutory ids'              => 'Reveal Statutory ID Numbers',
        ];

        return $explicit[Str::lower($permissionName)] ?? Str::headline($permissionName);
    }

    private function permissionDescription(string $permissionName): string
    {
        $explicit = [
            'view business dashboard'           => 'Grants access to the business dashboard page for this branch.',
            'view dashboard kpis'               => 'Shows the today, ongoing, pending, reserved, upcoming, and collected count cards.',
            'view dashboard revenue'            => 'Shows the collected revenue card, online vs walk-in source split, and top service breakdown.',
            'view dashboard timeline'           => 'Shows the full branch appointment timeline sorted by start time.',
            'view dashboard therapist status'   => 'Shows each therapist\'s workload bar with ongoing, done, and queued counts.',
            'view dashboard alerts'             => 'Shows late check-in, cancellation, and overloaded therapist warning cards.',
            'view dashboard booking button'     => 'Shows the New Booking shortcut button in the dashboard header.',
            'view dashboard my today'           => 'Shows the therapist\'s own personal schedule widget with only their assigned appointments.',
            'edit branches'                     => '(Deprecated) Superseded by the granular Edit General Information, Edit Operating Hours, and Edit Public Profile permissions. No longer used for access control.',
            'edit branch general'               => 'Allows this role to edit the branch general information, such as the branch name.',
            'edit branch hours'                 => 'Allows this role to edit only the branch operating hours (opening and closing times and closed days), separately from the branch name and public profile.',
            'edit branch profile'               => 'Allows this role to edit the branch public profile listing — photos, description, amenities, contact details, and whether the branch is listed publicly.',
            'view payroll'                      => 'Open pay runs, payslips, payroll setup and staff pay. HR Head, Finance Head and the owner see every branch; other roles see only their own branch\'s staff.',
            'manage payroll setup'              => 'Change pay profiles, recurring pay items, statutory IDs and branch minimum wages. The pay schedule and commission rules for all branches also need HR Head, Finance Head or owner.',
            'prepare payroll runs'              => 'Generate, regenerate and delete draft pay runs, and add or remove adjustments. Generating covers every branch, so only HR Head, Finance Head or the owner can do it; other roles can only adjust their own branch\'s payslips.',
            'approve payroll runs'              => 'Approve a draft pay run or send it back to draft. Needs HR Head, Finance Head or owner. Approving a run you prepared is allowed but shown on the run.',
            'release payroll runs'              => 'Finalize an approved pay run (locks it) and release payslips to staff. Needs HR Head, Finance Head or owner.',
            'reveal statutory ids'              => 'Show full SSS, PhilHealth, Pag-IBIG and TIN numbers. Every reveal is logged.',
        ];

        $lower = Str::lower(trim($permissionName));

        if (isset($explicit[$lower])) {
            return $explicit[$lower];
        }

        $parts  = explode(' ', $lower, 2);
        $action = $parts[0] ?? 'access';
        $target = Str::headline($parts[1] ?? $lower);

        return match ($action) {
            'view'   => "Allows this role to open and view {$target} for the current branch.",
            'create' => "Allows this role to add new {$target} records for the current branch.",
            'edit'   => "Allows this role to update existing {$target} records for the current branch.",
            'delete' => "Allows this role to remove {$target} records for the current branch.",
            'export' => "Allows this role to export {$target} for the current branch.",
            'approve'=> "Allows this role to approve {$target} for the current branch.",
            'manage' => "Allows this role to fully manage {$target} for the current branch.",
            default  => "Controls access to {$target} for the current branch.",
        };
    }

    // ── Permission grouping ───────────────────────────────────────────────────

    private function permissionGroups(): array
    {
        return [
            'dashboard' => [
                'title'       => 'Dashboard',
                'description' => 'Controls access to the business dashboard and its individual widgets — KPI cards, revenue data, appointment timeline, therapist status panel, alerts, booking shortcut, and the therapist personal schedule view.',
                'icon'        => 'fa-solid fa-gauge-high',
                'suite'       => null,
            ],
            'appointments' => [
                'title'       => 'Appointments',
                'description' => 'Book, view, and manage appointments and bookings.',
                'icon'        => 'fa-solid fa-calendar-check',
                'suite'       => null,
            ],
            'schedule' => [
                'title'       => 'Schedule',
                'description' => 'Branch schedules, calendars, and therapist timetable views.',
                'icon'        => 'fa-solid fa-calendar-days',
                'suite'       => null,
            ],
            'attendance_leave' => [
                'title'       => 'Attendance & Leave',
                'description' => 'Manage attendance, leave requests, availability, and workforce presence.',
                'icon'        => 'fa-solid fa-user-clock',
                // Attendance and leave are on every plan, so never locked here.
                'suite'       => null,
            ],
            'hiring' => [
                'title'       => 'Hiring & Recruitment',
                'description' => 'Hiring postings, applicants, applications, interviews, and deployments.',
                'icon'        => 'fa-solid fa-user-plus',
                'suite'       => 'workforce',
            ],
            'services' => [
                'title'       => 'Services & Packages',
                'description' => 'Treatments, services, and bundled packages.',
                'icon'        => 'fa-solid fa-hand-holding-heart',
                'suite'       => null,
            ],
            'staff' => [
                'title'       => 'Staff Accounts',
                'description' => 'Staff records, role assignment, and branch staff setup.',
                'icon'        => 'fa-solid fa-users',
                'suite'       => null,
            ],
            'branches' => [
                'title'       => 'Branches & Listing',
                'description' => 'Branch management and public listing / profile settings.',
                'icon'        => 'fa-solid fa-code-branch',
                'suite'       => null,
            ],
            'insights' => [
                'title'       => 'Insights & Reports',
                'description' => 'Reports, analytics, and decision support access.',
                'icon'        => 'fa-solid fa-chart-pie',
                'suite'       => null,
            ],
            'inventory' => [
                'title'       => 'Inventory',
                'description' => 'Products, stocks, logs, and inventory records.',
                'icon'        => 'fa-solid fa-boxes-stacked',
                'suite'       => null,
            ],
            'procurement' => [
                'title'       => 'Procurement',
                'description' => 'Suppliers, purchase requests, purchase orders, and goods receiving.',
                'icon'        => 'fa-solid fa-truck-ramp-box',
                'suite'       => null,
            ],
            'payroll' => [
                'title'       => 'Payroll',
                'description' => 'Pay runs, payroll setup, staff pay and statutory IDs. HR prepares, Finance approves and releases; the owner views by default.',
                'icon'        => 'fa-solid fa-money-check-dollar',
                'suite'       => 'payroll',
            ],
            'finance' => [
                'title'       => 'Finance',
                'description' => 'Billing, revenue, expenses, and financial records.',
                'icon'        => 'fa-solid fa-wallet',
                'suite'       => 'finance',
            ],
            'account' => [
                'title'       => 'Profile & Account',
                'description' => 'Staff profile and account-related access.',
                'icon'        => 'fa-solid fa-id-badge',
                'suite'       => null,
            ],
            // Catch-all. Any permission the keyword rules below do not recognise
            // lands here so it is still rendered. A permission that is never
            // rendered is never submitted, and update() would treat it as unticked.
            'other' => [
                'title'       => 'Other',
                'description' => 'Permissions that do not belong to a specific module yet.',
                'icon'        => 'fa-solid fa-ellipsis',
                'suite'       => null,
            ],
        ];
    }

    private function resolvePermissionGroup(string $permissionName): string
    {
        $name = Str::lower($permissionName);

        return match (true) {
            // Dashboard must come first — widget permissions contain words like
            // 'revenue' or 'timeline' that would otherwise match other groups.
            Str::contains($name, ['dashboard'])                                          => 'dashboard',

            // Payroll before staff/finance — 'reveal statutory ids' and the payroll
            // permissions would otherwise fall into other groups.
            Str::contains($name, ['payroll', 'statutory'])                               => 'payroll',

            Str::contains($name, ['appointment', 'booking'])                             => 'appointments',
            Str::contains($name, ['schedule'])                                           => 'schedule',
            Str::contains($name, ['attendance', 'availability', 'leave'])                => 'attendance_leave',
            Str::contains($name, ['hiring', 'applicant', 'application', 'interview', 'deployment']) => 'hiring',
            Str::contains($name, ['service', 'package', 'treatment', 'promo'])                   => 'services',
            Str::contains($name, ['staff'])                                              => 'staff',
            Str::contains($name, ['branch', 'listing', 'public profile'])                => 'branches',
            Str::contains($name, ['decision support', 'insight', 'analytics', 'report', 'export reports']) => 'insights',
            // Procurement must come before inventory — 'manage supplier products'
            // contains 'product' and would otherwise land in the inventory group.
            Str::contains($name, ['supplier', 'purchase', 'goods']) => 'procurement',
            Str::contains($name, ['inventory', 'stock', 'product', 'replenishment'])                     => 'inventory',
            Str::contains($name, ['billing', 'vendor bill', 'revenue', 'expense', 'finance']) => 'finance',
            Str::contains($name, ['profile', 'account', 'password'])                    => 'account',
            default                                                                      => 'other',
        };
    }

    // ── Role metadata ─────────────────────────────────────────────────────────

    private function roleMeta(string $roleName): array
    {
        return match (Str::lower($roleName)) {
            'owner'        => ['title' => 'Owner',        'description' => 'Business-level control for this branch.',                               'icon' => 'fa-solid fa-crown'],
            'manager'      => ['title' => 'Manager',      'description' => 'Supervises operations, staff flow, and branch activity.',                'icon' => 'fa-solid fa-user-tie'],
            'receptionist' => ['title' => 'Receptionist', 'description' => 'Handles bookings, front-desk flow, and customer records.',               'icon' => 'fa-solid fa-calendar-check'],
            'therapist'    => ['title' => 'Therapist',    'description' => 'Handles personal schedule, appointments, and service delivery.',         'icon' => 'fa-solid fa-spa'],
            'hr'           => ['title' => 'HR',           'description' => 'Handles hiring, attendance, interviews, and workforce records for this branch.', 'icon' => 'fa-solid fa-users-gear'],
            'finance'      => ['title' => 'Finance',      'description' => 'Handles billing, revenue, and financial monitoring for this branch.',     'icon' => 'fa-solid fa-wallet'],
            'hr_head'      => ['title' => 'HR Head',      'description' => 'Central HR for every branch: prepares payroll and manages people records across branches.', 'icon' => 'fa-solid fa-user-tie'],
            'finance_head' => ['title' => 'Finance Head', 'description' => 'Central finance for every branch: approves and releases payroll, oversees billing and revenue.', 'icon' => 'fa-solid fa-scale-balanced'],
            default        => ['title' => Str::headline($roleName), 'description' => 'Role settings for this branch.', 'icon' => 'fa-solid fa-user-shield'],
        };
    }

    // ── Section builder ───────────────────────────────────────────────────────

    private function ensureRoleIsEditable(Role $role): ?\Illuminate\Http\RedirectResponse
    {
        if (!in_array(Str::lower($role->name), $this->getManageableRoles(), true)) {
            return redirect()
                ->route('owner.roles-permissions.index')
                ->with('error', "The {$role->name} role is currently locked for this branch.");
        }

        return null;
    }

    private function buildEffectivePermissionNames(Role $role, Collection $allowedNames, Collection $branchOverrides): Collection
    {
        $globalPermissions = $role->permissions
            ->pluck('name')
            ->filter(fn($n) => $allowedNames->contains($n))
            ->values();

        return $allowedNames
            ->filter(function ($permissionName) use ($globalPermissions, $branchOverrides) {
                if ($branchOverrides->has($permissionName)) {
                    return (bool) $branchOverrides->get($permissionName);
                }
                return $globalPermissions->contains($permissionName);
            })
            ->values();
    }

    private function buildPermissionSections(Role $role, Branch $branch, int $spaId): array
    {
        $permissions     = $this->editablePermissions();
        $allowedNames    = $permissions->pluck('name')->values();
        $branchOverrides = $this->overridesFor($role, $branch, $spaId);

        $effectivePermissionNames = $this->buildEffectivePermissionNames($role, $allowedNames, $branchOverrides);
        $groupMeta                = $this->permissionGroups();

        $sections = collect($groupMeta)
            ->map(function ($meta, $groupKey) use ($permissions, $effectivePermissionNames, $branchOverrides) {
                $groupPermissions = $permissions
                    ->filter(fn(Permission $p) => $this->resolvePermissionGroup($p->name) === $groupKey)
                    ->values();

                if ($groupPermissions->isEmpty()) {
                    return null;
                }

                $items = $groupPermissions->map(function (Permission $p) use ($effectivePermissionNames, $branchOverrides) {
                    $isChecked    = $effectivePermissionNames->contains($p->name);
                    $isOverridden = $branchOverrides->has($p->name);
                    $overrideVal  = $branchOverrides->get($p->name);

                    return [
                        'name'        => $p->name,
                        'label'       => $this->permissionLabel($p->name),
                        'description' => $this->permissionDescription($p->name),
                        'checked'     => $isChecked,
                        'source'      => !$isOverridden
                            ? 'Default'
                            : ((bool) $overrideVal ? 'Enabled for this branch' : 'Disabled for this branch'),
                        'source_type' => !$isOverridden
                            ? 'default'
                            : ((bool) $overrideVal ? 'enabled' : 'disabled'),
                    ];
                })->values();

                return [
                    'key'            => $groupKey,
                    'title'          => $meta['title'],
                    'description'    => $meta['description'],
                    'icon'           => $meta['icon'],
                    'permissions'    => $items,
                    'selected_count' => $items->where('checked', true)->count(),
                    'total_count'    => $items->count(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'sections'         => $sections,
            'branch_overrides' => $branchOverrides,
            'effective_names'  => $effectivePermissionNames,
            'allowed_names'    => $allowedNames,
            'summary'          => [
                'selected'   => $effectivePermissionNames->count(),
                'available'  => $allowedNames->count(),
                'overridden' => $branchOverrides
                    ->filter(fn($v, $k) => $allowedNames->contains($k))
                    ->count(),
            ],
        ];
    }

    // ── Public actions ────────────────────────────────────────────────────────

    public function index()
    {
        $user   = auth()->user();
        $branch = $this->getCurrentBranch();

        $roles = Role::query()
            ->whereIn('name', $this->getManageableRoles())
            ->with('permissions')
            ->get()
            ->sortBy(fn(Role $r) => array_search(Str::lower($r->name), $this->roleOrder, true))
            ->values()
            ->map(function (Role $role) use ($user, $branch) {
                $allowedNames = $this->editablePermissions()->pluck('name')->values();

                $branchOverrides = $this->overridesFor($role, $branch, $user->spa_id);

                $effectivePermissionNames = $this->buildEffectivePermissionNames($role, $allowedNames, $branchOverrides);
                $meta = $this->roleMeta($role->name);

                $role->branch_users_count         = User::query()
                    ->where('spa_id', $user->spa_id)
                    ->where('branch_id', $branch->id)
                    ->role($role->name)
                    ->count();
                $role->effective_permission_count = $effectivePermissionNames->count();
                $role->override_count             = $branchOverrides
                    ->filter(fn($v, $k) => $allowedNames->contains($k))
                    ->count();
                $role->ui_title       = $meta['title'];
                $role->ui_description = $meta['description'];
                $role->ui_icon        = $meta['icon'];

                return $role;
            });

        return view('owner.roles-permissions.index', [
            'spa'              => $user->spa,
            'branch'           => $branch,
            'roles'            => $roles,
            'lockedRoles'      => $this->lockedRoles(),
            'workforceEnabled' => $this->workforceEnabled(),
            'financeEnabled'   => $this->financeEnabled(),
        ]);
    }

    public function edit(Role $role)
    {
        if ($redirect = $this->ensureRoleIsEditable($role)) {
            return $redirect;
        }

        $user   = auth()->user();
        $branch = $this->getCurrentBranch();

        $role->load('permissions');
        $built = $this->buildPermissionSections($role, $branch, $user->spa_id);

        $lockedSections = collect([
            !$this->workforceEnabled() ? [
                'title'   => 'Workforce',
                'message' => 'Hiring, applicants, interviews, and deployments permissions are available on the Premium plan.',
            ] : null,
            !$this->financeEnabled() ? [
                'title'   => 'Finance',
                'message' => 'Payroll, billing, revenue, and finance permissions are available on the Premium plan.',
            ] : null,
        ])->filter()->values();

        return view('owner.roles-permissions.edit', [
            'role'                 => $role,
            'branch'               => $branch,
            'sections'             => $built['sections'],
            'effectivePermissions' => $built['effective_names']->toArray(),
            'summary'              => $built['summary'],
            'lockedSections'       => $lockedSections,
            'roleMeta'             => $this->roleMeta($role->name),
            'appliesToAllBranches' => in_array($role->name, PayrollPermissions::HEAD_ROLES, true),
            'payrollAllBranches'   => $role->name === 'owner',
        ]);
    }

    public function update(Request $request, Role $role)
    {
        if ($redirect = $this->ensureRoleIsEditable($role)) {
            return $redirect;
        }

        $user   = auth()->user();
        $branch = $this->getCurrentBranch();

        $allowedPermissionNames = $this->editablePermissions()->pluck('name')->values();

        $validated = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($allowedPermissionNames->all())],
        ]);

        $selectedPermissions = collect($validated['permissions'] ?? [])->values();
        $globalPermissions   = $role->permissions->pluck('name')->values();

        // Wipe existing overrides for this role, then re-record only deltas. Each
        // permission is stored on the branch that decides it (overrideBranch): the
        // main branch for HR Head / Finance Head and for the owner's payroll
        // permissions, otherwise the branch being edited.
        foreach ($allowedPermissionNames as $permissionName) {
            $target = $this->overrideBranch($role->name, $permissionName, $branch);

            BranchRolePermission::query()
                ->where('branch_id', $target->id)
                ->where('spa_id', $user->spa_id)
                ->where('role_name', $role->name)
                ->where('permission_name', $permissionName)
                ->delete();

            $globalHas   = $globalPermissions->contains($permissionName);
            $branchWants = $selectedPermissions->contains($permissionName);

            if ($globalHas !== $branchWants) {
                BranchRolePermission::create([
                    'branch_id'       => $target->id,
                    'spa_id'          => $user->spa_id,
                    'role_name'       => $role->name,
                    'permission_name' => $permissionName,
                    'granted'         => $branchWants,
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('owner.roles-permissions.edit', $role)
            ->with('success', in_array($role->name, PayrollPermissions::HEAD_ROLES, true)
                ? $this->roleMeta($role->name)['title'] . ' permissions were updated for every branch.'
                : $this->roleMeta($role->name)['title'] . " permissions were updated for branch: {$branch->name}");
    }
}
