<?php

use App\Services\Permissions\PermissionRules as R;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sidebar & RBAC rework, Phase 5.1 — data only, no schema change. Safe to re-run.
 *
 * Some permissions can't work for some roles: the app refuses them there
 * (PermissionRules::UNAVAILABLE — e.g. creating or deleting branches is owner
 * only; approving or releasing pay runs needs a role that covers every branch).
 * The Roles pages now lock them. This removes any grant of them that was saved
 * before, from role defaults and from Roles & Permissions overrides, so the data
 * matches the page. It changes nothing anyone could actually do.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $revoked = 0;
        $overrides = 0;

        foreach (R::UNAVAILABLE as $permission => $rule) {
            $perm = Permission::where('name', $permission)->where('guard_name', 'web')->first();

            foreach (Role::where('guard_name', 'web')->whereNotIn('name', $rule['roles'])->get() as $role) {
                if ($perm && $role->hasPermissionTo($perm)) {
                    $role->revokePermissionTo($perm);
                    $revoked++;
                }
            }

            $overrides += DB::table('branch_role_permissions')
                ->where('permission_name', $permission)
                ->whereNotIn('role_name', $rule['roles'])
                ->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            fwrite(STDOUT, PHP_EOL);
            fwrite(STDOUT, sprintf("    %-52s %d\n", 'role defaults: unavailable permissions revoked', $revoked));
            fwrite(STDOUT, sprintf("    %-52s %d\n", 'overrides: unavailable permission rows removed', $overrides));
        }
    }

    /** Not reversed: the removed grants did nothing (the app refused them). */
    public function down(): void
    {
    }
};
