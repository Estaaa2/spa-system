<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    public function index()
    {
        $roles = Role::query()
            ->whereNotIn('name', ['admin', 'customer'])
            ->withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return view('admin.roles-permissions.index', compact('roles'));
    }

    public function edit(Role $role)
    {
        if (strtolower($role->name) === 'admin') {
            return redirect()
                ->route('admin.roles-permissions.index')
                ->with('error', 'Admin role is protected and cannot be edited.');
        }

        $role->load('permissions');

        $permissions = Permission::where('name', '!=', 'view admin dashboard')
        ->orderBy('name')
        ->get();

        $groups = $permissions->groupBy(function ($p) {
            return explode(' ', $p->name)[0]; // view/manage/create/edit/delete
        });

        return view('admin.roles-permissions.edit', compact('role', 'permissions', 'groups'));
    }

    public function update(Request $request, Role $role)
    {
        if (strtolower($role->name) === 'admin') {
            return redirect()
                ->route('admin.roles-permissions.index')
                ->with('error', 'Admin role is protected and cannot be updated.');
        }

        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ✅ Also clear cached permissions for all users with this role
        $role->users->each(function ($user) {
            $user->unsetRelation('roles');
            $user->unsetRelation('permissions');
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()
            ->route('admin.roles-permissions.edit', $role)
            ->with('success', 'Permissions updated successfully.');
    }
}
