<?php

use App\Services\Payroll\PayrollPermissions as P;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Payroll / attendance RBAC refactor — data only, no schema change. Idempotent.
 *
 * Re-running RolePermissionSeeder is NOT the upgrade path on an existing database:
 * its syncPermissions() would wipe any change an admin made to the global roles.
 * This migration makes targeted changes instead.
 *
 * 1. Creates the five new payroll permissions and the HR Head / Finance Head roles.
 * 2. Global roles: `edit payroll` is revoked everywhere. Branch HR gets manage setup +
 *    reveal; any other non-owner role that still held `edit payroll` (an admin
 *    customisation) gets all five so it loses nothing. The owner keeps only
 *    `view payroll` (adviser: owners view payroll by default). Finance gets view.
 *    `delete leave requests` (gates no action) is revoked from every role.
 * 3. Roles & Permissions overrides (branch_role_permissions): every `edit payroll`
 *    row becomes the same choice on the five new permissions (owners' explicit
 *    choices are kept); `delete leave requests` rows are removed.
 *
 * Both retired permission rows stay in `permissions` so no lingering
 * hasPermissionTo() call can throw PermissionDoesNotExist.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([...P::ALL, P::LEGACY_EDIT, 'delete leave requests'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        // The head roles' lists also contain permissions created by the seeder long ago;
        // make sure they exist on a database that was seeded before they were added.
        foreach ([...RolePermissionSeeder::HR_HEAD_PERMISSIONS, ...RolePermissionSeeder::FINANCE_HEAD_PERMISSIONS] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // ── 1. Head roles (only filled when new, so admin edits survive a re-run) ──
        foreach ([
            P::ROLE_HR_HEAD      => RolePermissionSeeder::HR_HEAD_PERMISSIONS,
            P::ROLE_FINANCE_HEAD => RolePermissionSeeder::FINANCE_HEAD_PERMISSIONS,
        ] as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            if ($role->permissions()->count() === 0) {
                $role->givePermissionTo($perms);
            }
        }

        // ── 2. Global roles ──
        foreach (Role::query()->with('permissions')->get() as $role) {
            $had = $role->permissions->pluck('name');

            if ($had->contains(P::LEGACY_EDIT)) {
                $role->revokePermissionTo(P::LEGACY_EDIT);

                $add = match ($role->name) {
                    'owner'  => [],
                    'hr'     => [P::MANAGE_SETUP, P::REVEAL_IDS],
                    default  => in_array($role->name, P::HEAD_ROLES, true) ? [] : P::FROM_LEGACY_EDIT,
                };
                if ($add !== []) {
                    $role->givePermissionTo($add);
                }
                if (! $had->contains(P::VIEW)) {
                    $role->givePermissionTo(P::VIEW);
                }
            }

            if ($role->name === 'finance' && ! $had->contains(P::VIEW)) {
                $role->givePermissionTo(P::VIEW);
            }

            if ($had->contains('delete leave requests')) {
                $role->revokePermissionTo('delete leave requests');
            }
        }

        // ── 3. Branch overrides ──
        $legacy = DB::table('branch_role_permissions')->where('permission_name', P::LEGACY_EDIT)->get();
        foreach ($legacy as $row) {
            foreach (P::FROM_LEGACY_EDIT as $perm) {
                DB::table('branch_role_permissions')->updateOrInsert(
                    ['branch_id' => $row->branch_id, 'role_name' => $row->role_name, 'permission_name' => $perm],
                    ['spa_id' => $row->spa_id, 'granted' => $row->granted, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }
        DB::table('branch_role_permissions')->whereIn('permission_name', [P::LEGACY_EDIT, 'delete leave requests'])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Not reversed automatically: the old single `edit payroll` cannot express the
     * split, and guessing would hand approval rights back to people who never had
     * them on purpose. Restore from a database backup if a rollback is needed.
     */
    public function down(): void
    {
    }
};
