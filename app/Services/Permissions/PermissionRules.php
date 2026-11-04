<?php

declare(strict_types=1);

namespace App\Services\Permissions;

use App\Services\Payroll\PayrollPermissions as P;

/**
 * One source for the business-side permission rules (sidebar & RBAC rework,
 * Phase 1). The seeder, the Roles & Permissions save, the data migration and,
 * from Phase 5, the Roles page JavaScript (through @json) all read this class.
 * Never copy these lists by hand.
 *
 * - RETIRED: kept in the `permissions` table (hasPermissionTo() throws on unknown
 *   names), granted to nobody, hidden on both Roles & Permissions pages.
 * - DEPENDENCIES: a permission is only usable together with its base, because the
 *   route that shows the page checks the base. Saving adds the missing bases.
 * - CONFLICT_PAIRS / ROLE_LIMITS: legal but risky combinations. Phase 5 asks for
 *   confirmation when a save adds one; nothing here blocks them.
 * - OWNER_PROTECTED: always granted to the owner role, whatever is submitted.
 */
final class PermissionRules
{
    /** Retired before this rework. */
    public const RETIRED_EARLIER = [
        'view owner dashboard',
        'edit branches',
        'delete leave requests',
        P::LEGACY_EDIT,
    ];

    /** Retired by the sidebar & RBAC rework, Phase 1 (findings §1a, answers P6 and P7). */
    public const RETIRED_PHASE1 = [
        // Sidebar-only duplicates of the inventory route permissions (P6).
        'view product inventory',
        'create product inventory',
        'edit product inventory',
        'delete product inventory',
        'view product logs',
        // Gate nothing: no route, no check (P7).
        'export reports',
        'view finance inventory',
        'edit finance inventory',
        'delete billing',
        'delete applications',
        'delete interviews',
        'edit own profile',
    ];

    /**
     * Dependent => the base permissions it needs (direct bases only; bases()
     * follows chains such as create promos -> view promos -> view services).
     * Each base is the permission on the route that shows the page where the
     * dependent action lives.
     */
    public const DEPENDENCIES = [
        // Appointments
        'edit appointments'                => ['view appointments'],
        'delete appointments'              => ['view appointments'],
        'request appointment reassignment' => ['view appointments'],

        // Attendance & Leave
        'edit attendance'     => ['view attendance'],
        'edit leave requests' => ['view leave requests'],

        // Services, packages, promos (promos live on the Services page)
        'create treatments' => ['view services'],
        'edit treatments'   => ['view services'],
        'delete treatments' => ['view services'],
        'create packages'   => ['view services'],
        'edit packages'     => ['view services'],
        'delete packages'   => ['view services'],
        'view promos'       => ['view services'],
        'create promos'     => ['view promos'],
        'edit promos'       => ['view promos'],
        'delete promos'     => ['view promos'],

        // Staff
        'create staff' => ['view staff'],
        'edit staff'   => ['view staff'],
        'delete staff' => ['view staff'],

        // Hiring
        'create hiring'       => ['view hiring'],
        'edit hiring'         => ['view hiring'],
        'delete hiring'       => ['view hiring'],
        'edit applications'   => ['view applications'],
        'create interviews'   => ['view interviews'],
        'edit interviews'     => ['view interviews'],
        'create deployments'  => ['view deployments'],
        'approve deployments' => ['view deployments'],
        'delete deployments'  => ['view deployments'],

        // Inventory
        'create inventory items'  => ['view inventory'],
        'edit inventory items'    => ['view inventory'],
        'delete inventory items'  => ['view inventory'],
        'create stock transfers'  => ['view stock transfers'],
        'process stock transfers' => ['view stock transfers'],
        'cancel stock transfers'  => ['view stock transfers'],
        'receive goods'           => ['view goods receipts'],

        // Procurement
        'create suppliers'         => ['view suppliers'],
        'edit suppliers'           => ['view suppliers'],
        'manage supplier products' => ['view suppliers'],
        'create purchase requests' => ['view purchase requests'],
        'review purchase requests' => ['view purchase requests'],
        'create purchase orders'   => ['view purchase orders'],
        'manage purchase orders'   => ['view purchase orders'],

        // Finance
        'create billing'      => ['view billing'],
        'edit billing'        => ['view billing'],
        'create vendor bills' => ['view vendor bills'],
        'match vendor bills'  => ['view vendor bills'],
        'edit vendor bills'   => ['view vendor bills'],

        // Payroll (every payroll page is under `view payroll`)
        P::MANAGE_SETUP => [P::VIEW],
        P::PREPARE      => [P::VIEW],
        P::APPROVE      => [P::VIEW],
        P::RELEASE      => [P::VIEW],
        P::REVEAL_IDS   => [P::VIEW],
    ];

    /**
     * Separation-of-duties pairs (findings §14, answer P11). `roles` limits a pair
     * to those roles; null means every role.
     */
    public const CONFLICT_PAIRS = [
        [
            'permissions' => [P::PREPARE, P::APPROVE],
            'roles'       => null,
            'reason'      => 'The same person can prepare a pay run and approve it.',
        ],
        [
            'permissions' => [P::APPROVE, P::RELEASE],
            'roles'       => ['manager', 'receptionist', 'therapist', 'hr', 'finance'],
            'reason'      => 'A branch role can both approve and release pay runs for the whole spa.',
        ],
        [
            'permissions' => ['create vendor bills', 'match vendor bills'],
            'roles'       => null,
            'reason'      => 'The same person can enter a supplier bill and match it for payment.',
        ],
        [
            'permissions' => ['create purchase requests', 'review purchase requests'],
            'roles'       => null,
            'reason'      => 'The same person can request a purchase and approve their own request.',
        ],
        [
            'permissions' => ['create purchase orders', 'receive goods'],
            'roles'       => null,
            'reason'      => 'The same person can order goods and confirm they arrived.',
        ],
    ];

    /** Sensitive areas outside a role's job (findings §14 items 6–8). */
    public const ROLE_LIMITS = [
        [
            'permissions'   => [P::REVEAL_IDS],
            'allowed_roles' => ['owner', 'hr', 'hr_head', 'finance', 'finance_head'],
            'reason'        => 'Shows full SSS, PhilHealth, Pag-IBIG and TIN numbers to a role outside HR and Finance.',
        ],
        [
            'permissions'   => [...P::ALL, 'view revenue', 'view billing', 'create billing', 'edit billing',
                'view vendor bills', 'create vendor bills', 'match vendor bills', 'edit vendor bills'],
            'denied_roles'  => ['receptionist', 'therapist'],
            'reason'        => 'Gives front-desk or treatment staff access to payroll or finance records.',
        ],
        [
            'permissions'   => ['delete staff'],
            'allowed_roles' => ['owner', 'hr', 'hr_head'],
            'reason'        => 'Lets a role outside HR remove staff accounts.',
        ],
    ];

    /** Always granted to the owner role (answer P9). Roles & Permissions, Spa Profile and Subscription are owner-role routes and need nothing here. */
    public const OWNER_PROTECTED = [
        'view branches',
        'edit branch general',
        'edit branch hours',
        'edit branch profile',
        'view staff',
        'create staff',
        'edit staff',
        'view dashboard kpis',
    ];

    /** @return list<string> */
    public static function retired(): array
    {
        return [...self::RETIRED_EARLIER, ...self::RETIRED_PHASE1];
    }

    public static function isRetired(string $permission): bool
    {
        return in_array($permission, self::retired(), true);
    }

    /** Every base the permission needs, following chains. @return list<string> */
    public static function bases(string $permission): array
    {
        $found = [];
        $queue = self::DEPENDENCIES[$permission] ?? [];

        while ($queue !== []) {
            $base = array_shift($queue);
            if (in_array($base, $found, true)) {
                continue;
            }
            $found[] = $base;
            array_push($queue, ...(self::DEPENDENCIES[$base] ?? []));
        }

        return $found;
    }

    /**
     * The set plus every base its permissions need (decision D8.1: ticking
     * "edit X" also grants "view X"). Order: the given permissions first.
     *
     * @param  iterable<string>  $permissions
     * @return list<string>
     */
    public static function withDependencies(iterable $permissions): array
    {
        $out = [];
        foreach ($permissions as $permission) {
            $out[$permission] = true;
            foreach (self::bases($permission) as $base) {
                $out[$base] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Bases withDependencies() would add to this set.
     *
     * @param  iterable<string>  $permissions
     * @return list<string>
     */
    public static function missingBases(iterable $permissions): array
    {
        $given = is_array($permissions) ? $permissions : iterator_to_array($permissions, false);

        return array_values(array_diff(self::withDependencies($given), $given));
    }

    /**
     * Conflicts present in a role's permission set.
     *
     * @param  iterable<string>  $permissions
     * @return list<array{key: string, permissions: list<string>, reason: string}>
     */
    public static function conflictsFor(string $role, iterable $permissions): array
    {
        $set = is_array($permissions) ? $permissions : iterator_to_array($permissions, false);
        $found = [];

        foreach (self::CONFLICT_PAIRS as $i => $pair) {
            if ($pair['roles'] !== null && ! in_array($role, $pair['roles'], true)) {
                continue;
            }
            if (array_diff($pair['permissions'], $set) === []) {
                $found[] = ['key' => "pair:$i", 'permissions' => $pair['permissions'], 'reason' => $pair['reason']];
            }
        }

        foreach (self::ROLE_LIMITS as $i => $limit) {
            $outside = isset($limit['allowed_roles'])
                ? ! in_array($role, $limit['allowed_roles'], true)
                : in_array($role, $limit['denied_roles'] ?? [], true);

            $held = array_values(array_intersect($limit['permissions'], $set));
            if ($outside && $held !== []) {
                $found[] = ['key' => "role:$i", 'permissions' => $held, 'reason' => $limit['reason']];
            }
        }

        return $found;
    }

    /**
     * Conflicts a save would add (answer P10: the confirmation fires only for a
     * conflict the role did not already have).
     *
     * @param  iterable<string>  $before
     * @param  iterable<string>  $after
     * @return list<array{key: string, permissions: list<string>, reason: string}>
     */
    public static function newConflicts(string $role, iterable $before, iterable $after): array
    {
        $had = array_column(self::conflictsFor($role, $before), 'key');

        return array_values(array_filter(
            self::conflictsFor($role, $after),
            fn (array $c) => ! in_array($c['key'], $had, true),
        ));
    }

    /** Payload for the Roles page script (Phase 5, via @json). */
    public static function forJs(): array
    {
        return [
            'dependencies'   => self::DEPENDENCIES,
            'conflictPairs'  => self::CONFLICT_PAIRS,
            'roleLimits'     => self::ROLE_LIMITS,
            'ownerProtected' => self::OWNER_PROTECTED,
        ];
    }
}
