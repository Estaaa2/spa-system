<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchRolePermission;
use App\Models\User;
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
        return (bool) ($this->getCurrentBranch()->has_workforce_finance_suite ?? false);
    }

    private function workforceEnabled(): bool
    {
        return $this->branchSuiteEnabled();
    }

    private function financeEnabled(): bool
    {
        return $this->branchSuiteEnabled();
    }

    private function getManageableRoles(): array
    {
        $roles = ['owner', 'manager', 'receptionist', 'therapist'];

        if ($this->workforceEnabled()) {
            $roles[] = 'hr';
        }

        if ($this->financeEnabled()) {
            $roles[] = 'finance';
        }

        return $roles;
    }

    private function lockedRoles(): array
    {
        $locked = [];

        if (!$this->workforceEnabled()) {
            $locked[] = [
                'name'   => 'hr',
                'title'  => 'HR',
                'reason' => 'Locked until the Workforce & Finance Suite is enabled for this branch.',
            ];
        }

        if (!$this->financeEnabled()) {
            $locked[] = [
                'name'   => 'finance',
                'title'  => 'Finance',
                'reason' => 'Locked until the Workforce & Finance Suite is enabled for this branch.',
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
                'suite'       => 'workforce',
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
            'finance' => [
                'title'       => 'Finance',
                'description' => 'Payroll, billing, revenue, expenses, and financial records.',
                'icon'        => 'fa-solid fa-wallet',
                'suite'       => 'finance',
            ],
            'account' => [
                'title'       => 'Profile & Account',
                'description' => 'Staff profile and account-related access.',
                'icon'        => 'fa-solid fa-id-badge',
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

            Str::contains($name, ['appointment', 'booking'])                             => 'appointments',
            Str::contains($name, ['schedule'])                                           => 'schedule',
            Str::contains($name, ['attendance', 'availability', 'leave'])                => 'attendance_leave',
            Str::contains($name, ['hiring', 'applicant', 'application', 'interview', 'deployment']) => 'hiring',
            Str::contains($name, ['service', 'package', 'treatment'])                   => 'services',
            Str::contains($name, ['staff'])                                              => 'staff',
            Str::contains($name, ['branch', 'listing', 'public profile'])                => 'branches',
            Str::contains($name, ['decision support', 'insight', 'analytics', 'report', 'export reports']) => 'insights',
            Str::contains($name, ['inventory', 'stock', 'product'])                     => 'inventory',
            Str::contains($name, ['payroll', 'billing', 'revenue', 'expense', 'finance']) => 'finance',
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
            'hr'           => ['title' => 'HR',           'description' => 'Handles hiring, attendance, interviews, and workforce records.',          'icon' => 'fa-solid fa-users-gear'],
            'finance'      => ['title' => 'Finance',      'description' => 'Handles payroll, billing, revenue, and financial monitoring.',            'icon' => 'fa-solid fa-wallet'],
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
        $branchOverrides = BranchRolePermission::query()
            ->where('branch_id', $branch->id)
            ->where('spa_id', $spaId)
            ->where('role_name', $role->name)
            ->pluck('granted', 'permission_name');

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

                $branchOverrides = BranchRolePermission::query()
                    ->where('branch_id', $branch->id)
                    ->where('spa_id', $user->spa_id)
                    ->where('role_name', $role->name)
                    ->pluck('granted', 'permission_name');

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
                'message' => 'Attendance, leave, hiring, applicants, interviews, and deployments are locked until the Workforce & Finance Suite is enabled for this branch.',
            ] : null,
            !$this->financeEnabled() ? [
                'title'   => 'Finance',
                'message' => 'Payroll, billing, revenue, and finance permissions are locked until the Workforce & Finance Suite is enabled for this branch.',
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

        // Wipe existing branch overrides for this role, then re-record only deltas
        BranchRolePermission::query()
            ->where('branch_id', $branch->id)
            ->where('spa_id', $user->spa_id)
            ->where('role_name', $role->name)
            ->whereIn('permission_name', $allowedPermissionNames)
            ->delete();

        foreach ($allowedPermissionNames as $permissionName) {
            $globalHas   = $globalPermissions->contains($permissionName);
            $branchWants = $selectedPermissions->contains($permissionName);

            if ($globalHas !== $branchWants) {
                BranchRolePermission::create([
                    'branch_id'       => $branch->id,
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
            ->with('success', "{$role->name} permissions were updated for branch: {$branch->name}");
    }
}