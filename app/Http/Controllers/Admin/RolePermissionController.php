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
    private array $roleOrder = [
        'owner',
        'manager',
        'receptionist',
        'therapist',
        'hr',
        'finance',
    ];

    private array $editableRoles = [
        'owner',
        'manager',
        'receptionist',
        'therapist',
        'hr',
        'finance',
    ];

    // ── Permission visibility ─────────────────────────────────────────────────

    private function shouldHidePermission(string $permissionName): bool
    {
        $name = Str::lower(trim($permissionName));

        $exactHidden = [
            // Admin / platform — not part of business role templates
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
        ];

        if (in_array($name, $exactHidden, true)) {
            return true;
        }

        // Admin-only platform permissions
        if (Str::contains($name, [
            'registered spa',
            'registered user',
            'platform settings',
            'platform role',
            'platform user',
            'system role',
            'system setting',
            'verify registered',
            'change spa subscription',
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
            ->reject(fn(Permission $p) => $this->shouldHidePermission($p->name))
            ->values();
    }

    // ── Permission labels & descriptions ──────────────────────────────────────

    /**
     * Human-readable labels for permissions whose auto-generated headline
     * would be unclear (e.g. dashboard widget permissions).
     */
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
            'view business dashboard'           => 'Grants access to the business dashboard page.',
            'view dashboard kpis'               => 'Shows the today, ongoing, pending, reserved, upcoming, and collected count cards.',
            'view dashboard revenue'            => 'Shows the collected revenue card, online vs walk-in source split, and top service breakdown.',
            'view dashboard timeline'           => 'Shows the full branch appointment timeline sorted by start time.',
            'view dashboard therapist status'   => 'Shows each therapist\'s workload bar with ongoing, done, and queued counts.',
            'view dashboard alerts'             => 'Shows late check-in, cancellation, and overloaded therapist warning cards.',
            'view dashboard booking button'     => 'Shows the New Booking shortcut button in the dashboard header.',
            'view dashboard my today'           => 'Shows the therapist\'s own personal schedule widget with only their appointments.',
        ];

        $lower = Str::lower(trim($permissionName));

        if (isset($explicit[$lower])) {
            return $explicit[$lower];
        }

        $parts  = explode(' ', $lower, 2);
        $action = $parts[0] ?? 'access';
        $target = Str::headline($parts[1] ?? $lower);

        return match ($action) {
            'view'   => "Lets this default role open and view {$target}.",
            'create' => "Lets this default role add new {$target}.",
            'edit'   => "Lets this default role update existing {$target}.",
            'delete' => "Lets this default role remove {$target}.",
            'export' => "Lets this default role export {$target}.",
            'approve'=> "Lets this default role approve {$target}.",
            'manage' => "Lets this default role fully manage {$target}.",
            default  => "Controls access to {$target}.",
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
            ],
            'appointments' => [
                'title'       => 'Appointments',
                'description' => 'Bookings, appointments, and customer reservation flow.',
                'icon'        => 'fa-solid fa-calendar-check',
            ],
            'schedule' => [
                'title'       => 'Schedule',
                'description' => 'Branch schedules, calendars, and therapist timetable views.',
                'icon'        => 'fa-solid fa-calendar-days',
            ],
            'attendance_leave' => [
                'title'       => 'Attendance & Leave',
                'description' => 'Attendance, leave requests, availability, and workforce presence.',
                'icon'        => 'fa-solid fa-user-clock',
            ],
            'hiring' => [
                'title'       => 'Hiring & Recruitment',
                'description' => 'Hiring postings, applicants, applications, and interview management.',
                'icon'        => 'fa-solid fa-user-plus',
            ],
            'services' => [
                'title'       => 'Services & Packages',
                'description' => 'Treatments, services, and bundled packages.',
                'icon'        => 'fa-solid fa-hand-holding-heart',
            ],
            'staff' => [
                'title'       => 'Staff Accounts',
                'description' => 'Staff records, role assignment, and branch staff setup.',
                'icon'        => 'fa-solid fa-users',
            ],
            'branches' => [
                'title'       => 'Branches & Listing',
                'description' => 'Branch management and public listing / profile settings.',
                'icon'        => 'fa-solid fa-code-branch',
            ],
            'insights' => [
                'title'       => 'Insights & Reports',
                'description' => 'Reports, analytics, and decision support access.',
                'icon'        => 'fa-solid fa-chart-pie',
            ],
            'inventory' => [
                'title'       => 'Inventory',
                'description' => 'Products, stocks, logs, and inventory records.',
                'icon'        => 'fa-solid fa-boxes-stacked',
            ],
            'finance' => [
                'title'       => 'Finance',
                'description' => 'Payroll, billing, revenue, expenses, and financial records.',
                'icon'        => 'fa-solid fa-wallet',
            ],
            'account' => [
                'title'       => 'Profile & Account',
                'description' => 'Staff profile, spa profile, and account-related access.',
                'icon'        => 'fa-solid fa-id-badge',
            ],
        ];
    }

    private function resolvePermissionGroup(string $permissionName): string
    {
        $name = Str::lower($permissionName);

        return match (true) {
            // Dashboard must come first — several widget permissions also contain
            // words like 'revenue' that would otherwise match the finance group.
            Str::contains($name, ['dashboard'])                                    => 'dashboard',

            Str::contains($name, ['appointment', 'booking'])                       => 'appointments',
            Str::contains($name, ['schedule'])                                     => 'schedule',
            Str::contains($name, ['attendance', 'availability', 'leave'])          => 'attendance_leave',
            Str::contains($name, ['hiring', 'applicant', 'application', 'interview', 'deployment']) => 'hiring',
            Str::contains($name, ['service', 'package', 'treatment'])              => 'services',
            Str::contains($name, ['staff'])                                        => 'staff',
            Str::contains($name, ['branch', 'listing', 'public profile'])          => 'branches',
            Str::contains($name, ['decision support', 'insight', 'analytics', 'report', 'export reports']) => 'insights',
            Str::contains($name, ['inventory', 'stock', 'product'])                => 'inventory',
            Str::contains($name, ['payroll', 'billing', 'revenue', 'expense', 'finance']) => 'finance',
            Str::contains($name, ['profile', 'account', 'password', 'spa profile']) => 'account',
            default                                                                 => 'other',
        };
    }

    // ── Role metadata ─────────────────────────────────────────────────────────

    private function roleMeta(string $roleName): array
    {
        return match (Str::lower($roleName)) {
            'owner'        => ['title' => 'Owner',        'description' => 'Default business-owner access across all modules.',             'icon' => 'fa-solid fa-crown'],
            'manager'      => ['title' => 'Manager',      'description' => 'Default branch operations and supervision access.',              'icon' => 'fa-solid fa-user-tie'],
            'receptionist' => ['title' => 'Receptionist', 'description' => 'Default front-desk, booking, and customer handling access.',     'icon' => 'fa-solid fa-calendar-check'],
            'therapist'    => ['title' => 'Therapist',    'description' => 'Default personal schedule, appointment, and service access.',    'icon' => 'fa-solid fa-spa'],
            'hr'           => ['title' => 'HR',           'description' => 'Default workforce, attendance, hiring, and interview access.',   'icon' => 'fa-solid fa-users-gear'],
            'finance'      => ['title' => 'Finance',      'description' => 'Default payroll, billing, and finance-side access.',             'icon' => 'fa-solid fa-wallet'],
            default        => ['title' => Str::headline($roleName), 'description' => 'Default role template.', 'icon' => 'fa-solid fa-user-shield'],
        };
    }

    // ── Section builder ───────────────────────────────────────────────────────

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
                    ->filter(fn(Permission $p) => $this->resolvePermissionGroup($p->name) === $groupKey)
                    ->values();

                if ($groupPermissions->isEmpty()) {
                    return null;
                }

                $items = $groupPermissions->map(function (Permission $p) use ($role) {
                    return [
                        'name'        => $p->name,
                        'label'       => $this->permissionLabel($p->name),
                        'description' => $this->permissionDescription($p->name),
                        'checked'     => $role->permissions->contains('name', $p->name),
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

        $allowedNames = $permissions->pluck('name');

        return [
            'sections' => $sections,
            'summary'  => [
                'selected'  => $role->permissions->pluck('name')->filter(fn($n) => $allowedNames->contains($n))->count(),
                'available' => $permissions->count(),
            ],
        ];
    }

    // ── Public actions ────────────────────────────────────────────────────────

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
            ->sortBy(fn(Role $r) => array_search(Str::lower($r->name), $this->roleOrder, true))
            ->values()
            ->map(function (Role $role) use ($customizedCounts, $totalBranches, $allowedPermissionNames) {
                $meta                        = $this->roleMeta($role->name);
                $customized                  = (int) ($customizedCounts[$role->name] ?? 0);
                $role->ui_title              = $meta['title'];
                $role->ui_description        = $meta['description'];
                $role->ui_icon               = $meta['icon'];
                $role->default_permission_count   = $role->permissions->pluck('name')
                    ->filter(fn($n) => $allowedPermissionNames->contains($n))->count();
                $role->customized_branches_count  = $customized;
                $role->default_branches_count     = max($totalBranches - $customized, 0);
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

        $allowedPermissionNames = $this->editablePermissions()->pluck('name')->values();

        $validated = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($allowedPermissionNames->all())],
        ]);

        $role->syncPermissions(collect($validated['permissions'] ?? [])->values()->all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles-permissions.edit', $role)
            ->with('success', 'Default role permissions updated successfully.');
    }
}