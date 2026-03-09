<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    /**
     * Roles the owner is allowed to manage.
     * Owner & Admin are intentionally excluded.
     */
    private array $manageableRoles = ['manager', 'therapist', 'receptionist', 'customer'];

    public function index()
    {
        $roles = Role::query()
            ->whereIn('name', $this->manageableRoles)
            ->withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return view('owner.roles-permissions.index', compact('roles'));
    }

    public function edit(Role $role)
    {
        // Guard: owner cannot edit owner/admin roles
        if (! in_array(strtolower($role->name), $this->manageableRoles)) {
            return redirect()
                ->route('owner.roles-permissions.index')
                ->with('error', 'You are not allowed to edit this role.');
        }

        $role->load('permissions');

        // Exclude system-level & owner-only permissions from the UI
        $excludedPermissions = [
            'view admin dashboard',
            'view owner dashboard',
            'manage spas',
            'manage users',
            'manage roles',
            'manage settings',
        ];

        $permissions = Permission::whereNotIn('name', $excludedPermissions)
            ->orderBy('name')
            ->get();

        // Group by first word: view / manage / create / edit / delete
        $groups = $permissions->groupBy(function ($p) {
            return explode(' ', $p->name)[0];
        });

        return view('owner.roles-permissions.edit', compact('role', 'permissions', 'groups'));
    }

    public function update(Request $request, Role $role)
    {
        // Guard: owner cannot update owner/admin roles
        if (! in_array(strtolower($role->name), $this->manageableRoles)) {
            return redirect()
                ->route('owner.roles-permissions.index')
                ->with('error', 'You are not allowed to modify this role.');
        }

        $validated = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()
            ->route('owner.roles-permissions.edit', $role)
            ->with('success', 'Permissions updated successfully.');
    }
}
