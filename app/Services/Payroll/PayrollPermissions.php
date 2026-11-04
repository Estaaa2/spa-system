<?php

declare(strict_types=1);

namespace App\Services\Payroll;

/**
 * Payroll permission names and role scope — one source for routes, controllers,
 * the Roles & Permissions page, the seeder and the transition migration.
 *
 * Role matrix (default; every permission is grantable on Roles & Permissions):
 *   HR Head        view, manage setup, prepare, reveal          — all branches
 *   Finance Head   view, approve, release, reveal               — all branches
 *   Branch HR      view, manage setup, reveal                   — own branch only
 *   Branch Finance view                                         — own branch only
 *   Owner          view                                         — all branches
 *
 * Scope rule: spa-wide items (pay schedule, "All branches" commission rules, and
 * generating / approving / finalizing / releasing a run, which always covers every
 * branch) need a spa-wide role as well as the permission. A branch-scoped role that
 * is granted `prepare payroll runs` can only add or remove adjustments on its own
 * branch's payslips.
 */
final class PayrollPermissions
{
    public const VIEW         = 'view payroll';
    public const MANAGE_SETUP = 'manage payroll setup';
    public const PREPARE      = 'prepare payroll runs';
    public const APPROVE      = 'approve payroll runs';
    public const RELEASE      = 'release payroll runs';
    public const REVEAL_IDS   = 'reveal statutory ids';

    /** Retired by the RBAC refactor. Kept defined (hasPermissionTo throws on unknown names), assigned to nobody, hidden on Roles & Permissions. */
    public const LEGACY_EDIT = 'edit payroll';

    public const ALL = [
        self::VIEW, self::MANAGE_SETUP, self::PREPARE, self::APPROVE, self::RELEASE, self::REVEAL_IDS,
    ];

    /** The five permissions an `edit payroll` holder had in practice. */
    public const FROM_LEGACY_EDIT = [
        self::MANAGE_SETUP, self::PREPARE, self::APPROVE, self::RELEASE, self::REVEAL_IDS,
    ];

    public const ROLE_HR_HEAD      = 'hr_head';
    public const ROLE_FINANCE_HEAD = 'finance_head';

    /** Central roles: work across branches through the branch switcher. */
    public const HEAD_ROLES = [self::ROLE_HR_HEAD, self::ROLE_FINANCE_HEAD];

    /** Roles that see and act on payroll for every branch. */
    public const SPA_WIDE_ROLES = ['owner', self::ROLE_HR_HEAD, self::ROLE_FINANCE_HEAD];

    public const DEFAULTS = [
        'owner'                 => [self::VIEW],
        self::ROLE_HR_HEAD      => [self::VIEW, self::MANAGE_SETUP, self::PREPARE, self::REVEAL_IDS],
        self::ROLE_FINANCE_HEAD => [self::VIEW, self::APPROVE, self::RELEASE, self::REVEAL_IDS],
        'hr'                    => [self::VIEW, self::MANAGE_SETUP, self::REVEAL_IDS],
        'finance'               => [self::VIEW],
    ];

    public const ROLE_LABELS = [
        'owner'                 => 'Owner',
        'manager'               => 'Manager',
        'receptionist'          => 'Receptionist',
        'therapist'             => 'Therapist',
        'hr'                    => 'HR',
        'finance'               => 'Finance',
        self::ROLE_HR_HEAD      => 'HR Head',
        self::ROLE_FINANCE_HEAD => 'Finance Head',
    ];

    public static function isPayrollPermission(string $permission): bool
    {
        return in_array($permission, self::ALL, true) || $permission === self::LEGACY_EDIT;
    }

    public static function roleLabel(?string $role): string
    {
        return $role === null ? 'No role' : (self::ROLE_LABELS[$role] ?? ucfirst(str_replace('_', ' ', $role)));
    }
}
