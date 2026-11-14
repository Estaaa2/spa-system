<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Customer chat (Nov 2026) — data only. Adds "reply to customer messages" and
 * gives it to the role defaults of receptionist (who answers customers), owner
 * and manager. Owners can give it to other roles on Roles & Permissions.
 * RolePermissionSeeder only fills new or empty roles, so existing roles get it
 * here; branch overrides are not touched. Safe to re-run.
 */
return new class extends Migration
{
    private const PERMISSION = 'reply to customer messages';
    private const ROLES = ['receptionist', 'owner', 'manager'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        foreach (Role::where('guard_name', 'web')->whereIn('name', self::ROLES)->get() as $role) {
            if (! $role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
