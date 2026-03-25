<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchRolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    /**
     * Editable business role templates.
     */
    private array $roleOrder = [
        'owner',
        'manager',
        'receptionist',
        'therapist',
        'hr',
        'finance',
    ];

    /**
     * Roles editable from the admin defaults page.
     */
    private array $editableRoles = [
        'owner',
        'manager',
        'receptionist',
        'therapist',
        'hr',
        'finance',
    ];

    /**
     * Hide platform-only and non-business permissions.
     */
    private function shouldHidePermission(string $permissionName): bool
    {
        $name = Str::lower(trim($permissionName));

        $exactHidden = [
            'view admin dashboard',
            'manage admin dashboard',
            'manage spas',
            'manage users',
            'manage roles',
            'manage settings',
            'view customer dashboard',
        ];

        if (in_array($name, $exactHidden, true)) {
            return true;
        }

        if (Str::contains($name, [
            'registered spa',
            'registered user',
            'platform settings',
            'platform role',
            'platform user',
        ])) {
            return true;
        }

        return false;
    }

    private function editablePermissions(): Collection
    {
        return Permission::query()
            ->orderBy('name')
            ->get()
            ->reject(fn (Permission $permission) => $this->shouldHidePermission($permission->name))
            ->values();
    }

    private function roleMeta(string $roleName): array
    {
        return match (Str::lower($roleName)) {
            'owner' => [
                'title' => 'Owner',
                'description' => 'Default business-owner access for spa branches.',
                'icon' => 'fa-solid fa-crown',
            ],
            'manager' => [
                'title' => 'Manager',
                'description' => 'Default branch operations and supervision access.',
                'icon' => 'fa-solid fa-user-tie',
            ],
            'receptionist' => [
                'title' => 'Receptionist',
                'description' => 'Default front-desk, booking, and customer handling access.',
                'icon' => 'fa-solid fa-calendar-check',
            ],
            'therapist' => [
                'title' => 'Therapist',
                'description' => 'Default schedule, appointment, and treatment-side access.',
                'icon' => 'fa-solid fa-spa',
            ],
            'hr' => [
                'title' => 'HR',
                'description' => 'Default workforce, attendance, hiring, and interview access.',
                'icon' => 'fa-solid fa-users-gear',
            ],
            'finance' => [
                'title' => 'Finance',
                'description' => 'Default payroll, billing, and finance-side access.',
                'icon' => 'fa-solid fa-wallet',
            ],
            default => [
                'title' => Str::headline($roleName),
                'description' => 'Default role template.',
                'icon' => 'fa-solid fa-user-shield',
            ],
        };
    }

    private function permissionGroups(): array
    {
        return [
            'dashboard' => [
                'title' => 'Dashboard',
                'description' => 'Default dashboard access for this role.',
                'icon' => 'fa-solid fa-chart-line',
            ],
            'appointments' => [
                'title' => 'Appointments',
                'description' => 'Bookings, appointments, and customer reservation flow.',
                'icon' => 'fa-solid fa-calendar-check',
            ],
            'schedule' => [
                'title' => 'Schedule',
                'description' => 'Schedules, calendars, and branch timetable views.',
                'icon' => 'fa-solid fa-calendar-days',
            ],
            'attendance_leave' => [
                'title' => 'Attendance & Leave',
                'description' => 'Attendance, leave, availability, and workforce presence.',
                'icon' => 'fa-solid fa-user-clock',
            ],
            'hiring' => [
                'title' => 'Hiring',
                'description' => 'Hiring, applicants, applications, and interviews.',
                'icon' => 'fa-solid fa-user-plus',
            ],
            'services' => [
                'title' => 'Services & Packages',
                'description' => 'Treatments, services, and packages.',
                'icon' => 'fa-solid fa-hand-holding-heart',
            ],
            'staff' => [
                'title' => 'Staff Accounts',
                'description' => 'Staff records, setup, and account management.',
                'icon' => 'fa-solid fa-users',
            ],
            'branches' => [
                'title' => 'Branches & Listing',
                'description' => 'Branch management and public listing/profile access.',
                'icon' => 'fa-solid fa-code-branch',
            ],
            'insights' => [
                'title' => 'Insights & Reports',
                'description' => 'Reports, analytics, and decision support.',
                'icon' => 'fa-solid fa-chart-pie',
            ],
            'inventory' => [
                'title' => 'Inventory',
                'description' => 'Products, stocks, logs, and inventory records.',
                'icon' => 'fa-solid fa-boxes-stacked',
            ],
            'finance' => [
                'title' => 'Finance',
                'description' => 'Payroll, billing, revenue, and financial monitoring.',
                'icon' => 'fa-solid fa-wallet',
            ],
            'account' => [
                'title' => 'Profile & Account',
                'description' => 'Own profile, account settings, and password-related access.',
                'icon' => 'fa-solid fa-id-badge',
            ],
            'owner_tools' => [
                'title' => 'Owner Tools',
                'description' => 'Spa profile, subscription, and owner-level business setup pages.',
                'icon' => 'fa-solid fa-briefcase',
            ],
            'other' => [
                'title' => 'Other',
                'description' => 'Permissions that do not match a main feature group.',
                'icon' => 'fa-solid fa-ellipsis',
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

            Str::contains($name, ['subscription', 'spa profile', 'roles and permissions', 'role permissions']) => 'owner_tools',

            Str::contains($name, ['profile', 'account', 'password']) => 'account',

            default => 'other',
        };
    }

    private function permissionDescription(string $permissionName): string
    {
        $name = Str::lower(trim($permissionName));
        $parts = explode(' ', $name, 2);

        $action = $parts[0] ?? 'access';
        $target = $parts[1] ?? $name;
        $targetLabel = Str::headline($target);

        return match ($action) {
            'view'   => "Lets this default role open and view {$targetLabel}.",
            'create' => "Lets this default role add new {$targetLabel}.",
            'edit'   => "Lets this default role update existing {$targetLabel}.",
            'delete' => "Lets this default role remove {$targetLabel}.",
            'manage' => "Lets this default role fully manage {$targetLabel}.",
            default  => "Controls access to {$targetLabel}.",
        };
    }

    private function ensureRoleIsEditable(Role $role): ?\Illuminate\Http\RedirectResponse
    {
        if (!in_array(Str::lower($role->name), $this->editableRoles, true)) {
            return redirect()
                ->route('admin.roles-permissions.index')
                ->withErrors(['role' => 'This role cannot be edited here.']);
        }

        return null;
    }

    private function buildPermissionSections(Role $role): array
    {
        $permissions = $this->editablePermissions();
        $groupMeta   = $this->permissionGroups();

        $sections = collect($groupMeta)
            ->map(function ($meta, $groupKey) use ($permissions, $role) {
                $groupPermissions = $permissions
                    ->filter(fn (Permission $permission) => $this->resolvePermissionGroup($permission->name) === $groupKey)
                    ->values();

                if ($groupPermissions->isEmpty()) {
                    return null;
                }

                $items = $groupPermissions->map(function (Permission $permission) use ($role) {
                    $checked = $role->permissions->contains('name', $permission->name);

                    return [
                        'name'        => $permission->name,
                        'label'       => Str::headline($permission->name),
                        'description' => $this->permissionDescription($permission->name),
                        'checked'     => $checked,
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
            'sections' => $sections,
            'summary'  => [
                'selected'  => $role->permissions
                    ->pluck('name')
                    ->filter(fn ($name) => $permissions->pluck('name')->contains($name))
                    ->count(),
                'available' => $permissions->count(),
            ],
        ];
    }

    public function index()
    {
        $totalBranches = Branch::count();

        $customizedCounts = BranchRolePermission::query()
            ->selectRaw('role_name, COUNT(DISTINCT branch_id) as customized_count')
            ->groupBy('role_name')
            ->pluck('customized_count', 'role_name');

        $allowedPermissionNames = $this->editablePermissions()->pluck('name');

        $roles = Role::query()
            ->whereIn('name', $this->editableRoles)
            ->withCount('users')
            ->with('permissions')
            ->get()
            ->sortBy(fn (Role $role) => array_search(Str::lower($role->name), $this->roleOrder, true))
            ->values()
            ->map(function (Role $role) use ($customizedCounts, $totalBranches, $allowedPermissionNames) {
                $meta = $this->roleMeta($role->name);

                $customizedBranchesCount = (int) ($customizedCounts[$role->name] ?? 0);

                $role->ui_title                = $meta['title'];
                $role->ui_description          = $meta['description'];
                $role->ui_icon                 = $meta['icon'];
                $role->default_permission_count = $role->permissions
                    ->pluck('name')
                    ->filter(fn ($name) => $allowedPermissionNames->contains($name))
                    ->count();
                $role->customized_branches_count = $customizedBranchesCount;
                $role->default_branches_count    = max($totalBranches - $customizedBranchesCount, 0);

                return $role;
            });

        return view('admin.roles-permissions.index', compact('roles', 'totalBranches'));
    }

    public function edit(Role $role)
    {
        if ($redirect = $this->ensureRoleIsEditable($role)) {
            return $redirect;
        }

        $role->load('permissions');

        $built = $this->buildPermissionSections($role);

        $customizedBranchesCount = BranchRolePermission::query()
            ->where('role_name', $role->name)
            ->distinct('branch_id')
            ->count('branch_id');

        $totalBranches = Branch::count();

        return view('admin.roles-permissions.edit', [
            'role'                    => $role,
            'sections'                => $built['sections'],
            'summary'                 => $built['summary'],
            'roleMeta'                => $this->roleMeta($role->name),
            'totalBranches'           => $totalBranches,
            'customizedBranchesCount' => $customizedBranchesCount,
            'defaultBranchesCount'    => max($totalBranches - $customizedBranchesCount, 0),
        ]);
    }

    public function update(Request $request, Role $role)
    {
        if ($redirect = $this->ensureRoleIsEditable($role)) {
            return $redirect;
        }

        $allowedPermissionNames = $this->editablePermissions()
            ->pluck('name')
            ->values();

        $validated = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($allowedPermissionNames->all())],
        ]);

        $selected = collect($validated['permissions'] ?? [])
            ->values()
            ->all();

        $role->syncPermissions($selected);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles-permissions.edit', $role)
            ->with('success', 'Default role permissions updated successfully.');
    }
}