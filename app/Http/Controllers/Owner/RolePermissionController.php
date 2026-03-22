<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\BranchRolePermission;
use App\Models\Branch;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionController extends Controller
{
    private array $baseRoles = ['manager', 'therapist', 'receptionist', 'owner'];

    private function getManageableRoles(): array
    {
        $spa = auth()->user()->spa;
        $roles = $this->baseRoles;

        if ($spa->isProfessional()) {
            $roles = array_merge($roles, ['hr', 'finance']);
        }

        return $roles;
    }

    private function getCurrentBranch()
    {
        $user = auth()->user();
        $branchId = $user->currentBranchId();
        return Branch::where('id', $branchId)
            ->where('spa_id', $user->spa_id)
            ->firstOrFail();
    }

    public function index()
    {
        $user            = auth()->user();
        $spa             = $user->spa;
        $branch          = $this->getCurrentBranch();
        $manageableRoles = $this->getManageableRoles();

        $roles = Role::query()
            ->whereIn('name', $manageableRoles)
            ->withCount('users')
            ->with('permissions')
            ->orderBy('name')
            ->get();

        // Attach effective permission count per role (global + branch overrides)
        $roles->each(function ($role) use ($branch, $user) {
            $branchOverrides = BranchRolePermission::where('branch_id', $branch->id)
                ->where('role_name', $role->name)
                ->where('spa_id', $user->spa_id)
                ->pluck('granted', 'permission_name');

            $globalPermissions = $role->permissions->pluck('name');

            $effectiveCount = collect($globalPermissions)
                ->merge($branchOverrides->filter()->keys())  // add granted overrides
                ->diff($branchOverrides->reject()->keys())   // remove revoked overrides
                ->unique()
                ->count();

            $role->effective_permission_count = $effectiveCount;
        });

        return view('owner.roles-permissions.index', compact('roles', 'spa', 'branch'));
    }

    public function edit(Role $role)
    {
        $manageableRoles = $this->getManageableRoles();

        if (!in_array(strtolower($role->name), $manageableRoles)) {
            return redirect()
                ->route('owner.roles-permissions.index')
                ->with('error', 'You are not allowed to edit this role.');
        }

        $user   = auth()->user();
        $branch = $this->getCurrentBranch();

        $role->load('permissions');

        // Always excluded (admin/system level)
        $excludedPermissions = [
            'view admin dashboard',
            'view owner dashboard',
            'manage spas',
            'manage users',
            'manage roles',
            'manage settings',
        ];

        // HR & Finance permissions — only visible on Professional plan
        $hrAndFinancePermissions = [
            'view hr dashboard',
            'view hiring',
            'manage hiring',
            'view applications',
            'manage applications',
            'view interviews',
            'manage interviews',
            'view attendance',
            'manage attendance',
            'view payroll',
            'manage payroll',
            'view finance dashboard',
            'view revenue',
            'manage revenue',
            'view billing',
            'manage billing',
            'view finance inventory',
            'manage finance inventory',
        ];

        if (!$user->spa->isProfessional()) {
            $excludedPermissions = array_merge($excludedPermissions, $hrAndFinancePermissions);
        }

        $permissions = Permission::whereNotIn('name', $excludedPermissions)
            ->orderBy('name')
            ->get();

        // Get branch-level overrides for this role
        $branchOverrides = BranchRolePermission::where('branch_id', $branch->id)
            ->where('role_name', $role->name)
            ->where('spa_id', $user->spa_id)
            ->pluck('granted', 'permission_name');

        // Build effective permissions:
        // Start from global role permissions, then apply branch overrides
        $globalPermissions    = $role->permissions->pluck('name')->toArray();
        $effectivePermissions = collect($globalPermissions)
            ->merge($branchOverrides->filter()->keys())
            ->diff($branchOverrides->reject()->keys())
            ->unique()
            ->values()
            ->toArray();

        $groups = $permissions->groupBy(function ($p) {
            $parts = explode(' ', $p->name);
            return ucfirst($parts[1] ?? $parts[0]);
        });

        return view('owner.roles-permissions.edit', compact(
            'role', 'permissions', 'groups',
            'branch', 'branchOverrides', 'effectivePermissions'
        ));
    }

    public function update(Request $request, Role $role)
    {
        $manageableRoles = $this->getManageableRoles();

        if (!in_array(strtolower($role->name), $manageableRoles)) {
            return redirect()
                ->route('owner.roles-permissions.index')
                ->with('error', 'You are not allowed to modify this role.');
        }

        $validated = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user     = auth()->user();
        $branch   = $this->getCurrentBranch();
        $selected = collect($validated['permissions'] ?? []);

        $excludedPermissions = [
            'view admin dashboard', 'view owner dashboard',
            'manage spas', 'manage users', 'manage roles', 'manage settings',
        ];

        $allPermissions = Permission::whereNotIn('name', $excludedPermissions)
            ->pluck('name');

        // Save branch-level overrides (not touching global role)
        $globalPermissions = $role->permissions->pluck('name');

        // Delete existing overrides for this branch+role
        BranchRolePermission::where('branch_id', $branch->id)
            ->where('role_name', $role->name)
            ->where('spa_id', $user->spa_id)
            ->delete();

        // Only store overrides where branch differs from global
        foreach ($allPermissions as $permName) {
            $globalHas  = $globalPermissions->contains($permName);
            $branchWants = $selected->contains($permName);

            // Only store if it's different from global default
            if ($globalHas !== $branchWants) {
                BranchRolePermission::create([
                    'branch_id'       => $branch->id,
                    'spa_id'          => $user->spa_id,
                    'role_name'       => $role->name,
                    'permission_name' => $permName,
                    'granted'         => $branchWants,
                ]);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return redirect()
            ->route('owner.roles-permissions.edit', $role)
            ->with('success', "Permissions for {$role->name} updated for branch: {$branch->name}");
    }
}
