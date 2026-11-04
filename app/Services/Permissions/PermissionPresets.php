<?php

declare(strict_types=1);

namespace App\Services\Permissions;

use App\Services\Payroll\PayrollPermissions as P;

/**
 * Starting points for the Roles & Permissions page (sidebar & RBAC rework
 * Phase 5, RP4). Code-defined, like Fresha's "templated permission roles" that
 * owners edit rather than build from scratch. A preset only ticks boxes on the
 * page — nothing is saved until the owner presses Save, and the save runs the
 * same dependency, protected-minimum and conflict rules as any other save.
 *
 * Each preset is the role's current default (the global role permissions, so
 * it follows the Admin Roles page) plus `add` and minus `remove`.
 *
 * "Does treatments" is not a preset: it's the per-staff "Performs treatments"
 * switch on the Staff page (decision D7 / Q1).
 */
final class PermissionPresets
{
    private const PRESETS = [
        'owner' => [
            'owner-runs-payroll' => [
                'label'       => 'Owner runs payroll',
                'description' => 'For a spa without an HR Head or Finance Head: the owner prepares, approves and releases pay runs. Payroll permissions apply to every branch.',
                'add'         => [P::MANAGE_SETUP, P::PREPARE, P::APPROVE, P::RELEASE, P::REVEAL_IDS],
                'remove'      => [],
            ],
        ],
        'manager' => [
            'manager-operations-only' => [
                'label'       => 'Operations only',
                'description' => 'Bookings, schedule, staff and attendance. No stock, purchasing or reports.',
                'add'         => [],
                'remove'      => [
                    'view inventory', 'view inventory logs', 'create inventory items', 'edit inventory items',
                    'view replenishment', 'view stock transfers', 'create stock transfers', 'process stock transfers',
                    'cancel stock transfers', 'view suppliers', 'create suppliers', 'edit suppliers', 'manage supplier products',
                    'view purchase requests', 'create purchase requests', 'review purchase requests',
                    'view goods receipts', 'receive goods', 'view purchase orders', 'create purchase orders',
                    'manage purchase orders', 'view reports', 'view decision support',
                ],
            ],
        ],
        'receptionist' => [
            'receptionist-front-desk-lead' => [
                'label'       => 'Front desk lead',
                'description' => 'Also reviews leave requests and sees branch reports.',
                'add'         => ['edit leave requests', 'view reports'],
                'remove'      => [],
            ],
        ],
        'therapist' => [
            'therapist-books-own' => [
                'label'       => 'Therapist who books',
                'description' => 'Can also take bookings at the branch (walk-ins, phone calls).',
                'add'         => ['book appointments', 'view dashboard booking button'],
                'remove'      => [],
            ],
        ],
        'hr' => [
            'hr-no-payroll' => [
                'label'       => 'HR without payroll',
                'description' => 'Hiring, staff, attendance and leave, but no payroll access.',
                'add'         => [],
                'remove'      => [P::VIEW, P::MANAGE_SETUP, P::REVEAL_IDS],
            ],
        ],
        'finance' => [
            'finance-purchasing' => [
                'label'       => 'Finance also handles purchasing',
                'description' => 'Adds suppliers, purchase requests, purchase orders and goods receipts (view and review).',
                'add'         => ['view suppliers', 'view purchase requests', 'review purchase requests', 'view purchase orders', 'view goods receipts'],
                'remove'      => [],
            ],
        ],
    ];

    /**
     * Presets for a role, each a complete permission list (with dependencies),
     * restricted to the permissions the page can show.
     *
     * @param  list<string>  $roleDefault  the role's global permissions
     * @param  list<string>  $editable     permission names shown on the page
     * @return list<array{key: string, label: string, description: string, permissions: list<string>}>
     */
    public static function forRole(string $role, array $roleDefault, array $editable): array
    {
        $editable = PermissionRules::withoutUnavailable($role, $editable);   // Phase 5.1: never tick a locked box
        $default = array_values(array_intersect($roleDefault, $editable));

        $presets = [[
            'key'         => 'default',
            'label'       => 'Default for this role',
            'description' => 'The standard permissions every new spa starts with.',
            'permissions' => $default,
        ]];

        foreach (self::PRESETS[$role] ?? [] as $key => $preset) {
            $perms = array_diff(array_unique([...$roleDefault, ...$preset['add']]), $preset['remove']);
            $perms = PermissionRules::withDependencies($perms);

            $shown = array_values(array_intersect($perms, $editable));

            // A preset that changes nothing visible (e.g. "Owner runs payroll" on Basic,
            // where payroll permissions aren't on the page) would only confuse: skip it.
            if (array_diff($shown, $default) === [] && array_diff($default, $shown) === []) {
                continue;
            }

            $presets[] = [
                'key'         => $key,
                'label'       => $preset['label'],
                'description' => $preset['description'],
                'permissions' => $shown,
            ];
        }

        return $presets;
    }
}
