<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sidebar & RBAC rework, Phase 6.3 — data only, no schema change. Safe to re-run.
 *
 * Finance and Finance Head had no way to file their own leave (and, before
 * Phase 6.3, no way to reach Attendance & Leave to clock in, so the nightly
 * attendance:finalize-day job marked them absent every day). Every other staff
 * role already has "create leave requests" by default.
 *
 * This gives the two role DEFAULTS that permission when they don't have it.
 * Branch overrides saved on Roles & Permissions are not touched, so an owner
 * who turned leave filing off for a role at a branch keeps that choice.
 * Clocking in itself needs no permission any more (User::hasAttendanceSelfService).
 */
return new class extends Migration
{
    private const ROLES = ['finance', 'finance_head'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $perm = Permission::firstOrCreate(['name' => 'create leave requests', 'guard_name' => 'web']);
        $granted = 0;

        foreach (Role::where('guard_name', 'web')->whereIn('name', self::ROLES)->get() as $role) {
            if (! $role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
                $granted++;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            fwrite(STDOUT, PHP_EOL);
            fwrite(STDOUT, sprintf("    %-52s %d\n", 'role defaults: create leave requests granted', $granted));
        }
    }

    /** Not reversed: removing it would again leave these staff unable to file leave. */
    public function down(): void
    {
    }
};
