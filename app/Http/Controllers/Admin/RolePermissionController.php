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
        $roles = Role::withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        return view('admin.roles-permissions.index', compact('roles'));
    }

    public function edit(Role $role)
    {
        $role->load('permissions');

        $permissions = Permission::orderBy('name')->get();

        $groups = $permissions->groupBy(function ($p) {
            return explode(' ', $p->name)[0]; // view/manage/create/edit/delete
        });

        return view('admin.roles-permissions.edit', compact('role', 'permissions', 'groups'));
    }

    public function update(Request $request, Role $role)
    {
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()
            ->route('roles-permissions.edit', $role)
            ->with('success', 'Permissions updated successfully.');

    }
}
