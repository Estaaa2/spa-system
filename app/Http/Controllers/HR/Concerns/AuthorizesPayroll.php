<?php

namespace App\Http\Controllers\HR\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Payroll authorization shared by the payroll controllers. Routes already check the
 * permission (branch.permission); these helpers repeat it as a second line of
 * defence and add the branch scope:
 *
 *  - Spa-wide roles (owner, HR Head, Finance Head) act on every branch.
 *  - Other roles see and change only their own branch's staff and payslips.
 *  - Spa-wide actions need a spa-wide role as well as the permission.
 *
 * Out-of-scope records answer 404, so the response never confirms they exist.
 */
trait AuthorizesPayroll
{
    protected function payrollUser(): User
    {
        /** @var User $u */
        $u = Auth::user();

        return $u;
    }

    protected function canPayroll(string $permission): bool
    {
        return (bool) Auth::user()?->hasBranchPermission($permission);
    }

    /** null = every branch; otherwise the only branch this user may see. */
    protected function payrollScope(): ?int
    {
        return $this->payrollUser()->payrollBranchScope();
    }

    protected function isSpaWidePayroll(): bool
    {
        return $this->payrollScope() === null;
    }

    /** Holds the permission AND works across branches. */
    protected function canSpaWide(string $permission): bool
    {
        return $this->isSpaWidePayroll() && $this->canPayroll($permission);
    }

    protected function authorizePayroll(string $permission): void
    {
        abort_unless($this->canPayroll($permission), 403);
    }

    protected function authorizeSpaWide(string $permission): void
    {
        $this->authorizePayroll($permission);
        abort_unless(
            $this->isSpaWidePayroll(),
            403,
            'This covers every branch, so it needs the HR Head, the Finance Head or the owner.'
        );
    }

    protected function abortUnlessBranchInScope(?int $branchId): void
    {
        $scope = $this->payrollScope();

        if ($scope !== null) {
            abort_unless((int) $branchId === $scope, 404);
        }
    }

    /**
     * Branch filter for a read: spa-wide users keep their own choice (null = all),
     * branch-scoped users are always limited to their branch.
     */
    protected function scopedBranchFilter(?int $requested): ?int
    {
        return $this->payrollScope() ?? $requested;
    }
}
