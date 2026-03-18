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
     * Base roles — HR & Finance added dynamically for Professional owners
     */
    private array $baseRoles = ['manager', 'therapist', 'receptionist', 'customer'];

    /**
     * Get manageable roles based on spa tier
     */
    private function getManageableRoles(): array
    {
        $spa = auth()->user()->spa;

        $roles = $this->baseRoles;

        if ($spa->isProfessional()) {
            $roles = array_merge($roles, ['hr', 'finance']);
        }

        return $roles;
    }

    public function index()
    {
        $spa            = auth()->user()->spa;
        $manageableRoles = $this->getManageableRoles();

        $roles = Role::query()
            ->whereIn('name', $manageableRoles) // ✅ uses dynamic list
            ->withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return view('owner.roles-permissions.index', compact('roles', 'spa'));
    }

    public function edit(Role $role)
    {
        $manageableRoles = $this->getManageableRoles();

        // ✅ Guard uses dynamic list
        if (! in_array(strtolower($role->name), $manageableRoles)) {
            return redirect()
                ->route('owner.roles-permissions.index')
                ->with('error', 'You are not allowed to edit this role.');
        }

        $role->load('permissions');

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

        $groups = $permissions->groupBy(function ($p) {
            return explode(' ', $p->name)[0];
        });

        return view('owner.roles-permissions.edit', compact('role', 'permissions', 'groups'));
    }

    public function update(Request $request, Role $role)
    {
        $manageableRoles = $this->getManageableRoles();

        // ✅ Guard uses dynamic list
        if (! in_array(strtolower($role->name), $manageableRoles)) {
            return redirect()
                ->route('owner.roles-permissions.index')
                ->with('error', 'You are not allowed to modify this role.');
        }

        $validated = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        // Clear Spatie cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Clear per-user cache for affected users
        $role->users->each(function ($user) {
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
        });

        return redirect()
            ->route('owner.roles-permissions.edit', $role)
            ->with('success', 'Permissions updated successfully.');
    }
}
