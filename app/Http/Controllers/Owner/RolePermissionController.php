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

    private function getCurrentBranch(): Branch
    {
        $user = auth()->user();

        return Branch::query()
            ->where('id', $user->currentBranchId())
            ->where('spa_id', $user->spa_id)
            ->firstOrFail();
    }

    /**
     * Flexible suite checker so this controller still works
     * even if your Spa model uses a slightly different method/column name.
     */
    private function suiteEnabled(string $suite): bool
    {
        $spa = auth()->user()->spa;

        return match ($suite) {
            'workforce' => (bool) (
                (method_exists($spa, 'hasWorkforceSuite') && $spa->hasWorkforceSuite()) ||
                (method_exists($spa, 'workforceSuiteEnabled') && $spa->workforceSuiteEnabled()) ||
                data_get($spa, 'workforce_suite_enabled') ||
                data_get($spa, 'workforce_enabled') ||
                (method_exists($spa, 'isProfessional') && $spa->isProfessional())
            ),

            'finance' => (bool) (
                (method_exists($spa, 'hasFinanceSuite') && $spa->hasFinanceSuite()) ||
                (method_exists($spa, 'financeSuiteEnabled') && $spa->financeSuiteEnabled()) ||
                data_get($spa, 'finance_suite_enabled') ||
                data_get($spa, 'finance_enabled') ||
                (method_exists($spa, 'isProfessional') && $spa->isProfessional())
            ),

            default => false,
        };
    }

    private function workforceEnabled(): bool
    {
        return $this->suiteEnabled('workforce');
    }

    private function financeEnabled(): bool
    {
        return $this->suiteEnabled('finance');
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
                'name' => 'hr',
                'title' => 'HR',
                'reason' => 'Locked until the Workforce suite is enabled for this spa.',
            ];
        }

        if (!$this->financeEnabled()) {
            $locked[] = [
                'name' => 'finance',
                'title' => 'Finance',
                'reason' => 'Locked until the Finance suite is enabled for this spa.',
            ];
        }

        return $locked;
    }

    private function roleMeta(string $roleName): array
    {
        return match (Str::lower($roleName)) {
            'owner' => [
                'title' => 'Owner',
                'description' => 'Business-level control for this branch.',
                'icon' => 'fa-solid fa-crown',
            ],
            'manager' => [
                'title' => 'Manager',
                'description' => 'Supervises operations, staff flow, and branch activity.',
                'icon' => 'fa-solid fa-user-tie',
            ],
            'receptionist' => [
                'title' => 'Receptionist',
                'description' => 'Handles bookings, front-desk flow, and customer records.',
                'icon' => 'fa-solid fa-calendar-check',
            ],
            'therapist' => [
                'title' => 'Therapist',
                'description' => 'Handles schedules, appointments, and service delivery.',
                'icon' => 'fa-solid fa-spa',
            ],
            'hr' => [
                'title' => 'HR',
                'description' => 'Handles hiring, attendance, interviews, and workforce records.',
                'icon' => 'fa-solid fa-users-gear',
            ],
            'finance' => [
                'title' => 'Finance',
                'description' => 'Handles payroll, billing, revenue, and financial monitoring.',
                'icon' => 'fa-solid fa-wallet',
            ],
            default => [
                'title' => Str::headline($roleName),
                'description' => 'Role settings for this branch.',
                'icon' => 'fa-solid fa-user-shield',
            ],
        };
    }

    private function shouldHidePermission(string $permissionName): bool
    {
        $name = Str::lower(trim($permissionName));

        $exactHidden = [
            // admin/platform only
            'view admin dashboard',
            'manage admin dashboard',
            'view registered users',
            'manage registered users',
            'view registered spas',
            'manage registered spas',
            'manage spas',
            'manage users',
            'manage roles',
            'manage settings',

            // owner-only business pages that should not be delegated here
            'view owner dashboard',
            'manage owner dashboard',
            'view spa profile',
            'manage spa profile',
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

        if (Str::contains($name, ['admin ', ' platform', 'registered spa', 'registered user'])) {
            return true;
        }

        return false;
    }

    private function permissionGroups(): array
    {
        return [
            'dashboard' => [
                'title' => 'Dashboard',
                'description' => 'Access the branch summary and overview page.',
                'icon' => 'fa-solid fa-chart-line',
                'suite' => null,
            ],
            'appointments' => [
                'title' => 'Appointments',
                'description' => 'Book, view, and manage appointments and bookings.',
                'icon' => 'fa-solid fa-calendar-check',
                'suite' => null,
            ],
            'schedule' => [
                'title' => 'Schedule',
                'description' => 'Access branch schedules and therapist timetable views.',
                'icon' => 'fa-solid fa-calendar-days',
                'suite' => null,
            ],
            'attendance_leave' => [
                'title' => 'Attendance & Leave',
                'description' => 'Manage attendance, availability, leave requests, and workforce presence.',
                'icon' => 'fa-solid fa-user-clock',
                'suite' => 'workforce',
            ],
            'hiring' => [
                'title' => 'Hiring',
                'description' => 'Manage hiring flow, applications, applicants, and interviews.',
                'icon' => 'fa-solid fa-user-plus',
                'suite' => 'workforce',
            ],
            'services' => [
                'title' => 'Services & Packages',
                'description' => 'Manage treatments, services, and packages.',
                'icon' => 'fa-solid fa-hand-holding-heart',
                'suite' => null,
            ],
            'staff' => [
                'title' => 'Staff Accounts',
                'description' => 'Manage staff records, role assignment, and branch staff setup.',
                'icon' => 'fa-solid fa-users',
                'suite' => null,
            ],
            'branches' => [
                'title' => 'Branches & Public Listing',
                'description' => 'Manage branches and public listing/profile related settings.',
                'icon' => 'fa-solid fa-code-branch',
                'suite' => null,
            ],
            'insights' => [
                'title' => 'Insights & Reports',
                'description' => 'Access analytics, decision support, and reports.',
                'icon' => 'fa-solid fa-chart-pie',
                'suite' => null,
            ],
            'inventory' => [
                'title' => 'Inventory',
                'description' => 'Manage products, stocks, logs, and inventory movement.',
                'icon' => 'fa-solid fa-boxes-stacked',
                'suite' => null,
            ],
            'finance' => [
                'title' => 'Finance',
                'description' => 'Manage payroll, billing, revenue, expenses, and finance records.',
                'icon' => 'fa-solid fa-wallet',
                'suite' => 'finance',
            ],
            'account' => [
                'title' => 'Profile & Account',
                'description' => 'Allow staff to manage their own profile and account details.',
                'icon' => 'fa-solid fa-id-badge',
                'suite' => null,
            ],
            'other' => [
                'title' => 'Other',
                'description' => 'Permissions that do not match a main feature group.',
                'icon' => 'fa-solid fa-ellipsis',
                'suite' => null,
            ],
        ];
    }

    private function resolvePermissionGroup(string $permissionName): string
    {
        $name = Str::lower($permissionName);

        return match (true) {
            Str::contains($name, ['dashboard']) => 'dashboard',

            Str::contains($name, ['appointment', 'booking']) => 'appointments',

            Str::contains($name, ['schedule']) => 'schedule',

            Str::contains($name, ['attendance', 'availability', 'leave']) => 'attendance_leave',

            Str::contains($name, ['hiring', 'applicant', 'application', 'interview']) => 'hiring',

            Str::contains($name, ['service', 'package', 'treatment']) => 'services',

            Str::contains($name, ['staff']) => 'staff',

            Str::contains($name, ['branch', 'listing', 'public profile', 'branch profile']) => 'branches',

            Str::contains($name, ['decision support', 'insight', 'analytics', 'report']) => 'insights',

            Str::contains($name, ['inventory', 'stock', 'product log', 'product inventory', 'product']) => 'inventory',

            Str::contains($name, ['payroll', 'billing', 'revenue', 'expense', 'finance']) => 'finance',

            Str::contains($name, ['profile', 'account', 'password']) => 'account',

            default => 'other',
        };
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
            ->reject(fn (Permission $permission) => $this->shouldHidePermission($permission->name))
            ->filter(fn (Permission $permission) => $this->permissionAllowedBySuite($permission->name))
            ->values();
    }

    private function permissionDescription(string $permissionName): string
    {
        $name = Str::lower(trim($permissionName));
        $parts = explode(' ', $name, 2);

        $action = $parts[0] ?? 'access';
        $target = $parts[1] ?? $name;
        $targetLabel = Str::headline($target);

        return match ($action) {
            'view'   => "Allows this role to open and view {$targetLabel} for the current branch.",
            'create' => "Allows this role to add new {$targetLabel} records for the current branch.",
            'edit'   => "Allows this role to update existing {$targetLabel} records for the current branch.",
            'delete' => "Allows this role to remove {$targetLabel} records for the current branch.",
            'manage' => "Allows this role to fully manage {$targetLabel} for the current branch.",
            default  => "Controls access to {$targetLabel} for the current branch.",
        };
    }

    private function ensureRoleIsEditable(Role $role): ?\Illuminate\Http\RedirectResponse
    {
        $roleName = Str::lower($role->name);

        if (!in_array($roleName, $this->getManageableRoles(), true)) {
            return redirect()
                ->route('owner.roles-permissions.index')
                ->with('error', "The {$role->name} role is currently locked for this branch.");
        }

        return null;
    }

    private function buildEffectivePermissionNames(Role $role, Collection $allowedPermissionNames, Collection $branchOverrides): Collection
    {
        $globalPermissions = $role->permissions
            ->pluck('name')
            ->filter(fn ($name) => $allowedPermissionNames->contains($name))
            ->values();

        return $allowedPermissionNames
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
        $permissions         = $this->editablePermissions();
        $allowedNames        = $permissions->pluck('name')->values();
        $branchOverrides     = BranchRolePermission::query()
            ->where('branch_id', $branch->id)
            ->where('spa_id', $spaId)
            ->where('role_name', $role->name)
            ->pluck('granted', 'permission_name');

        $effectivePermissionNames = $this->buildEffectivePermissionNames($role, $allowedNames, $branchOverrides);
        $groupMeta                = $this->permissionGroups();

        $sections = collect($groupMeta)
            ->map(function ($meta, $groupKey) use ($permissions, $effectivePermissionNames, $branchOverrides) {
                $groupPermissions = $permissions
                    ->filter(fn (Permission $permission) => $this->resolvePermissionGroup($permission->name) === $groupKey)
                    ->values();

                if ($groupPermissions->isEmpty()) {
                    return null;
                }

                $items = $groupPermissions->map(function (Permission $permission) use ($effectivePermissionNames, $branchOverrides) {
                    $isChecked    = $effectivePermissionNames->contains($permission->name);
                    $isOverridden = $branchOverrides->has($permission->name);
                    $overrideVal  = $branchOverrides->get($permission->name);

                    return [
                        'name'        => $permission->name,
                        'label'       => Str::headline($permission->name),
                        'description' => $this->permissionDescription($permission->name),
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
            'sections'            => $sections,
            'branch_overrides'    => $branchOverrides,
            'effective_names'     => $effectivePermissionNames,
            'allowed_names'       => $allowedNames,
            'summary'             => [
                'selected'   => $effectivePermissionNames->count(),
                'available'  => $allowedNames->count(),
                'overridden' => $branchOverrides
                    ->filter(fn ($value, $key) => $allowedNames->contains($key))
                    ->count(),
            ],
        ];
    }

    public function index()
    {
        $user   = auth()->user();
        $spa    = $user->spa;
        $branch = $this->getCurrentBranch();

        $roles = Role::query()
            ->whereIn('name', $this->getManageableRoles())
            ->with('permissions')
            ->get()
            ->sortBy(fn (Role $role) => array_search(Str::lower($role->name), $this->roleOrder, true))
            ->values()
            ->map(function (Role $role) use ($user, $branch) {
                $allowedNames = $this->editablePermissions()->pluck('name')->values();

                $branchOverrides = BranchRolePermission::query()
                    ->where('branch_id', $branch->id)
                    ->where('spa_id', $user->spa_id)
                    ->where('role_name', $role->name)
                    ->pluck('granted', 'permission_name');

                $effectivePermissionNames = $this->buildEffectivePermissionNames(
                    $role,
                    $allowedNames,
                    $branchOverrides
                );

                $meta = $this->roleMeta($role->name);

                $role->branch_users_count = User::query()
                    ->where('spa_id', $user->spa_id)
                    ->where('branch_id', $branch->id)
                    ->role($role->name)
                    ->count();

                $role->effective_permission_count = $effectivePermissionNames->count();
                $role->override_count             = $branchOverrides
                    ->filter(fn ($value, $key) => $allowedNames->contains($key))
                    ->count();

                $role->ui_title       = $meta['title'];
                $role->ui_description = $meta['description'];
                $role->ui_icon        = $meta['icon'];

                return $role;
            });

        return view('owner.roles-permissions.index', [
            'spa'             => $spa,
            'branch'          => $branch,
            'roles'           => $roles,
            'lockedRoles'     => $this->lockedRoles(),
            'workforceEnabled'=> $this->workforceEnabled(),
            'financeEnabled'  => $this->financeEnabled(),
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
                'title' => 'Workforce',
                'message' => 'Attendance, leave, hiring, applicants, and interviews are locked until the Workforce suite is enabled.',
            ] : null,
            !$this->financeEnabled() ? [
                'title' => 'Finance',
                'message' => 'Payroll, billing, revenue, and finance permissions are locked until the Finance suite is enabled.',
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

        BranchRolePermission::query()
            ->where('branch_id', $branch->id)
            ->where('spa_id', $user->spa_id)
            ->where('role_name', $role->name)
            ->whereIn('permission_name', $allowedPermissionNames)
            ->delete();

        foreach ($allowedPermissionNames as $permissionName) {
            $globalHas  = $globalPermissions->contains($permissionName);
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