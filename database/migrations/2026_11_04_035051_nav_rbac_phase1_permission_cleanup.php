<?php

use App\Services\Payroll\PayrollPermissions as P;
use App\Services\Permissions\PermissionRules as R;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sidebar & RBAC rework, Phase 1 — data only, no schema change. Safe to re-run:
 * a second run finds nothing to change.
 *
 * Re-running RolePermissionSeeder is not the upgrade path on an existing database
 * (same reasoning as payroll_rbac_transition). This migration makes the changes:
 *
 * 1. Retired permissions (PermissionRules::retired()) are revoked from every role
 *    and user, and their Roles & Permissions override rows are deleted. The
 *    permission rows themselves stay, so no lingering check can throw.
 * 2. Receptionist default: `edit branch profile` is revoked (answer P8).
 * 3. Global defaults get any missing base permission (answer D8.1).
 * 4. Override rows: for every branch + role, a permission that is in effect
 *    without its base gets the base (a deny row on the base is removed, or a
 *    grant row added). Same rule as saving on Roles & Permissions.
 * 5. Owner protected minimum (answer P9): owner deny rows on those permissions
 *    are removed, and the owner default gets them.
 * 6. Override rows that now equal the role default are deleted (they change
 *    nothing and would otherwise pin a branch to an old default).
 *
 * Every count is printed when run from `php artisan migrate`.
 */
return new class extends Migration
{
    private array $counts = [];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([...R::retired(), ...R::OWNER_PROTECTED] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->retire();
        $this->receptionistDefault();
        $this->ownerDefault();
        $this->defaultsDependencies();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->overrideDependencies();
        $this->ownerOverrides();
        $this->dropRedundantOverrides();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->report();
    }

    /**
     * Not reversed: the retired permissions gated nothing, and restoring
     * dependency-breaking rows would only bring back links to 403 pages.
     */
    public function down(): void
    {
    }

    // ── 1. Retired ──────────────────────────────────────────────────────────
    private function retire(): void
    {
        $ids = Permission::whereIn('name', R::retired())->where('guard_name', 'web')->pluck('id');

        $this->counts['retired: role grants removed'] = DB::table('role_has_permissions')
            ->whereIn('permission_id', $ids)->delete();
        $this->counts['retired: user grants removed'] = DB::table('model_has_permissions')
            ->whereIn('permission_id', $ids)->delete();
        $this->counts['retired: override rows removed'] = DB::table('branch_role_permissions')
            ->whereIn('permission_name', R::retired())->delete();
    }

    // ── 2. Receptionist default ─────────────────────────────────────────────
    private function receptionistDefault(): void
    {
        $role = Role::where('name', 'receptionist')->where('guard_name', 'web')->first();
        $this->counts['receptionist: edit branch profile revoked'] = 0;

        if ($role && $role->hasPermissionTo('edit branch profile')) {
            $role->revokePermissionTo('edit branch profile');
            $this->counts['receptionist: edit branch profile revoked'] = 1;
        }
    }

    // ── 5a. Owner default ───────────────────────────────────────────────────
    private function ownerDefault(): void
    {
        $role = Role::where('name', 'owner')->where('guard_name', 'web')->first();
        $this->counts['owner default: protected permissions added'] = 0;

        if (! $role) {
            return;
        }

        $missing = array_values(array_diff(R::OWNER_PROTECTED, $role->permissions()->pluck('name')->all()));
        if ($missing !== []) {
            $role->givePermissionTo($missing);
            $this->counts['owner default: protected permissions added'] = count($missing);
        }
    }

    // ── 3. Global defaults ──────────────────────────────────────────────────
    private function defaultsDependencies(): void
    {
        $added = 0;

        foreach (Role::query()->where('guard_name', 'web')->with('permissions')->get() as $role) {
            $missing = R::missingBases($role->permissions->pluck('name')->all());
            if ($missing !== []) {
                foreach ($missing as $name) {
                    Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                }
                $role->givePermissionTo($missing);
                $added += count($missing);
            }
        }

        $this->counts['defaults: missing base permissions added'] = $added;
    }

    // ── 4. Override rows ────────────────────────────────────────────────────
    private function overrideDependencies(): void
    {
        $defaults = $this->defaults();
        $deleted = $inserted = 0;

        foreach ($this->overrideGroups() as [$branchId, $spaId, $roleName, $rows]) {
            $effective = $this->effective($defaults[$roleName] ?? [], $rows);

            foreach (R::missingBases($effective) as $base) {
                if (in_array($base, $defaults[$roleName] ?? [], true)) {
                    // In the default, so a deny row is what blocks it.
                    $deleted += DB::table('branch_role_permissions')
                        ->where('branch_id', $branchId)->where('role_name', $roleName)
                        ->where('permission_name', $base)->where('granted', false)->delete();
                } else {
                    DB::table('branch_role_permissions')->updateOrInsert(
                        ['branch_id' => $branchId, 'role_name' => $roleName, 'permission_name' => $base],
                        ['spa_id' => $spaId, 'granted' => true, 'created_at' => now(), 'updated_at' => now()],
                    );
                    $inserted++;
                }
            }
        }

        $this->counts['overrides: base deny rows removed'] = $deleted;
        $this->counts['overrides: base grant rows added'] = $inserted;
    }

    // ── 5b. Owner override rows ─────────────────────────────────────────────
    private function ownerOverrides(): void
    {
        $this->counts['overrides: owner protected deny rows removed'] = DB::table('branch_role_permissions')
            ->where('role_name', 'owner')
            ->whereIn('permission_name', R::OWNER_PROTECTED)
            ->where('granted', false)
            ->delete();
    }

    // ── 6. Redundant rows ───────────────────────────────────────────────────
    private function dropRedundantOverrides(): void
    {
        $defaults = $this->defaults();
        $removed = 0;

        foreach (DB::table('branch_role_permissions')->get(['id', 'role_name', 'permission_name', 'granted']) as $row) {
            $inDefault = in_array($row->permission_name, $defaults[$row->role_name] ?? [], true);
            if ((bool) $row->granted === $inDefault) {
                $removed += DB::table('branch_role_permissions')->where('id', $row->id)->delete();
            }
        }

        $this->counts['overrides: rows equal to the default removed'] = $removed;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** @return array<string, list<string>> role name => default permission names */
    private function defaults(): array
    {
        return Role::query()->where('guard_name', 'web')->with('permissions')->get()
            ->mapWithKeys(fn (Role $r) => [$r->name => $r->permissions->pluck('name')->all()])
            ->all();
    }

    /**
     * Override rows grouped by branch + role. Head roles and the owner's payroll
     * choices sit on the main branch (F5); dependencies never cross from payroll
     * to non-payroll, so each group can be checked on its own.
     *
     * @return list<array{0: int, 1: int, 2: string, 3: \Illuminate\Support\Collection}>
     */
    private function overrideGroups(): array
    {
        return DB::table('branch_role_permissions')->get()
            ->groupBy(fn ($r) => $r->branch_id.'|'.$r->role_name)
            ->map(fn ($rows) => [(int) $rows->first()->branch_id, (int) $rows->first()->spa_id, $rows->first()->role_name, $rows])
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function effective(array $default, $rows): array
    {
        $set = array_fill_keys($default, true);
        foreach ($rows as $row) {
            if ($row->granted) {
                $set[$row->permission_name] = true;
            } else {
                unset($set[$row->permission_name]);
            }
        }

        return array_keys($set);
    }

    private function report(): void
    {
        $total = array_sum($this->counts);

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            fwrite(STDOUT, PHP_EOL);
            foreach ($this->counts as $label => $n) {
                fwrite(STDOUT, sprintf("    %-52s %d\n", $label, $n));
            }
            fwrite(STDOUT, sprintf("    %-52s %d\n", 'TOTAL rows/grants changed', $total));
        }
    }
};
