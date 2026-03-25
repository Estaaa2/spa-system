<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    /**
     * Roles editable by platform admins as default templates.
     * These are the defaults that only affect newly initialized spas/branches.
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
     * Permissions that should never appear in this editor.
     */
    private array $excludedPermissions = [
        'view admin dashboard',
        'manage spas',
        'manage users',
        'manage roles',
        'manage settings',
        'view customer dashboard',
    ];

    /**
     * Group permissions by feature/module instead of action word.
     */
    private function permissionGroups($permissions): array
    {
        $map = [
            'Dashboard' => [
                'view owner dashboard',
                'view hr dashboard',
                'view finance dashboard',
            ],

            'Appointments' => [
                'create booking',
                'view appointments',
                'edit appointments',
                'delete appointments',
                'manage appointments',
            ],

            'Schedule' => [
                'view schedule',
                'manage schedule',
            ],

            'Attendance & Leave' => [
                'view attendance',
                'manage attendance',
                'view leave requests',
                'manage leave requests',
            ],

            'Services & Packages' => [
                'view services',
                'create services',
                'edit services',
                'delete services',
                'manage services',
            ],

            'Staff' => [
                'view staff',
                'create staff',
                'edit staff',
                'delete staff',
                'manage staff',
            ],

            'Branches' => [
                'view branches',
                'create branches',
                'edit branches',
                'delete branches',
                'manage branches',
            ],

            'Insights & Reports' => [
                'view decision support',
                'view reports',
            ],

            'Profile & Account' => [
                'edit own profile',
            ],

            'Subscription & Billing' => [
                'view subscription',
                'manage subscription',
            ],

            'Hiring' => [
                'view hiring',
                'manage hiring',
                'view applications',
                'manage applications',
                'view interviews',
                'manage interviews',
            ],

            'Payroll & Finance' => [
                'view payroll',
                'manage payroll',
                'view revenue',
                'manage revenue',
                'view billing',
                'manage billing',
                'view finance inventory',
                'manage finance inventory',
            ],
        ];

        $grouped = [];

        foreach ($map as $group => $names) {
            $items = $permissions->filter(fn ($p) => in_array($p->name, $names))->values();
            if ($items->isNotEmpty()) {
                $grouped[$group] = $items;
            }
        }

        // Catch any remaining permission not explicitly mapped
        $mappedNames = collect($map)->flatten()->all();

        $others = $permissions
            ->filter(fn ($p) => !in_array($p->name, $mappedNames))
            ->values();

        if ($others->isNotEmpty()) {
            $grouped['Other'] = $others;
        }

        return $grouped;
    }

    public function index()
    {
        $roles = Role::query()
            ->whereIn('name', $this->editableRoles)
            ->withCount('users')
            ->with('permissions')
            ->orderByRaw("
                FIELD(name, 'owner', 'manager', 'receptionist', 'therapist', 'hr', 'finance')
            ")
            ->get();

        return view('admin.roles-permissions.index', compact('roles'));
    }

    public function edit(Role $role)
    {
        if (!in_array(strtolower($role->name), $this->editableRoles)) {
            return redirect()
                ->route('admin.roles-permissions.index')
                ->withErrors(['role' => 'This role cannot be edited here.']);
        }

        $role->load('permissions');

        $permissions = Permission::query()
            ->whereNotIn('name', $this->excludedPermissions)
            ->orderBy('name')
            ->get();

        $groups = $this->permissionGroups($permissions);

        return view('admin.roles-permissions.edit', compact('role', 'permissions', 'groups'));
    }

    public function update(Request $request, Role $role)
    {
        if (!in_array(strtolower($role->name), $this->editableRoles)) {
            return redirect()
                ->route('admin.roles-permissions.index')
                ->withErrors(['role' => 'This role cannot be modified here.']);
        }

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $allowedPermissionNames = Permission::query()
            ->whereNotIn('name', $this->excludedPermissions)
            ->pluck('name')
            ->all();

        $selected = collect($validated['permissions'] ?? [])
            ->filter(fn ($name) => in_array($name, $allowedPermissionNames))
            ->values()
            ->all();

        $role->syncPermissions($selected);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles-permissions.edit', $role)
            ->with('success', 'Default role permissions updated successfully. These changes apply to newly initialized spa branches only.');
    }
}