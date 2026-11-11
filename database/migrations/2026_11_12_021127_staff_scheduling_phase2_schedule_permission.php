<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Staff scheduling Phase 2 — data only, no schema change. Safe to re-run.
 *
 * Adds the "edit staff schedules" permission (Phase 2, Q6 A) and gives it to the
 * role DEFAULTS of owner, manager, HR and HR Head — but only where that role
 * default currently has "edit staff", so a role the owner already limited keeps
 * its limits. RolePermissionSeeder only fills new or empty roles, which is why
 * existing roles get it here. Branch overrides saved on Roles & Permissions are
 * not touched.
 */
return new class extends Migration
{
    private const ROLES = ['owner', 'manager', 'hr', 'hr_head'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'edit staff schedules', 'guard_name' => 'web']);
        $editStaff = Permission::where(['name' => 'edit staff', 'guard_name' => 'web'])->first();
        $granted = 0;

        foreach (Role::where('guard_name', 'web')->whereIn('name', self::ROLES)->get() as $role) {
            $hasEditStaff = $editStaff && $role->hasPermissionTo($editStaff);
            if (($hasEditStaff || $role->name === 'owner') && ! $role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
                $granted++;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            fwrite(STDOUT, PHP_EOL);
            fwrite(STDOUT, sprintf("    %-52s %d\n", 'role defaults: edit staff schedules granted', $granted));
        }
    }

    /** Not reversed: the Staff page's schedule editing would disappear for these roles. */
    public function down(): void
    {
    }
};
