<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Models\Booking;
use App\Models\IdentityVerification;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\PurchaseRequest;
use App\Models\ReassignmentRequest;
use App\Models\Spa;
use App\Models\User;
use App\Services\Payroll\PayrollPermissions as P;
use App\Services\RecruitmentWorkflowService;
use Illuminate\Support\Facades\Cache;

/**
 * Sidebar notification badges (sidebar & RBAC rework Phase 4, N3).
 *
 * Computed counts from existing tables (D3) — no notification tables. Each
 * count uses the same filters as the page the link opens, so the number on the
 * badge is the number the user finds there. Each is gated by the permission
 * that lets the user act on it, and scoped to the current branch (payroll runs
 * and spa-wide recruitment access follow their own spa-wide rules).
 *
 * Cached for 60 seconds per user + branch (D3). The cache key also carries a
 * per-spa version that bumps whenever a source record is saved or deleted
 * (AppServiceProvider), so acting on an item updates the badge at once.
 *
 * Shape: link key => ['count' => int, 'label' => string, 'urgent' => bool]
 * (link keys are the ones in Sidebar / AdminSidebar). Zero counts are omitted.
 */
final class NavBadges
{
    public const TTL_SECONDS = 60;

    /** Business sidebar badges for this user at this branch. */
    public static function forUser(User $user, ?int $branchId): array
    {
        if (! $user->spa_id) {
            return [];
        }

        $key = sprintf('nav-badges:%d:%d:%d', $user->id, $branchId ?? 0, self::version((int) $user->spa_id));

        return Cache::remember($key, self::TTL_SECONDS, fn () => self::compute($user, $branchId));
    }

    /** Admin sidebar badges (platform-wide, same cache rules). */
    public static function forAdmin(User $user): array
    {
        $key = sprintf('nav-badges-admin:%d:%d', $user->id, self::version(0));

        return Cache::remember($key, self::TTL_SECONDS, function () use ($user) {
            $out = [];

            // Same condition RegisteredSpaController::index() uses for "Pending Verification".
            if ($user->can('view registered spas')) {
                $n = Spa::where('verification_status', 'pending')->count();
                self::put($out, 'registered-spas', $n, $n.' '.($n === 1 ? 'spa' : 'spas').' pending verification');
            }
            if ($user->can('verify staff identity')) {
                $n = IdentityVerification::pending()->count();
                self::put($out, 'id-reviews', $n, $n.' waiting for review');
            }

            return $out;
        });
    }

    /** Invalidate every cached badge set for one spa (0 = admin badges). */
    public static function bump(int $spaId): void
    {
        $key = 'nav-badges-version:'.$spaId;
        Cache::forever($key, ((int) Cache::get($key, 0)) + 1);
    }

    private static function version(int $spaId): int
    {
        return (int) Cache::get('nav-badges-version:'.$spaId, 0);
    }

    // ── Business counts ─────────────────────────────────────────────────────

    private static function compute(User $user, ?int $branchId): array
    {
        $out = [];
        $spaId = (int) $user->spa_id;
        $spa = $user->spa;
        $can = fn (string $p) => $user->hasBranchPermission($p);

        // Appointments page: the "unassigned" panel and the reassignment-requests
        // modal (BookingController::adminIndex, ReassignmentRequestController::index).
        if ($branchId && $can('edit appointments')) {
            $unassigned = Booking::query()
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->whereNull('therapist_id')
                ->whereIn('status', ['reserved', 'pending'])
                ->whereDate('appointment_date', '>=', today())
                ->notEnded();   // an ended one is counted under "to check out" instead

            $unassignedCount = (clone $unassigned)->count();
            $startingToday   = (clone $unassigned)->whereDate('appointment_date', today())->count();

            $reassignments = ReassignmentRequest::query()
                ->whereHas('booking', fn ($q) => $q->where('spa_id', $spaId)->where('branch_id', $branchId))
                ->pending()
                ->count();

            // Needs Checkout list: still open after the end time (booking lifecycle
            // fix, Phase 2, D3). Same query as BookingController::adminIndex.
            $overdue = Booking::query()
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->overdue();

            $overdueCount   = (clone $overdue)->count();
            $overdueEarlier = (clone $overdue)->whereDate('appointment_date', '<', today())->count();

            $total = $unassignedCount + $reassignments + $overdueCount;
            $parts = array_filter([
                $overdueCount ? "$overdueCount to check out".($overdueEarlier ? " ($overdueEarlier from earlier days)" : '') : null,
                $unassignedCount ? "$unassignedCount unassigned".($startingToday ? " ($startingToday starting today)" : '') : null,
                $reassignments ? "$reassignments reassignment ".($reassignments === 1 ? 'request' : 'requests') : null,
            ]);
            // "!" for an unassigned booking that starts today (answer P14), or a booking
            // nobody closed from an earlier day (lifecycle Phase 2, Q3-A).
            self::put($out, 'appointments', $total, implode(', ', $parts), $startingToday > 0 || $overdueEarlier > 0);

            // Schedule page: reschedule requests are reviewed there.
            // Counted per booking, like the "Reschedule Requests" list above the calendar
            // (ScheduleController, Phase 4.1). A booking has at most one pending request.
            $reschedules = Booking::query()
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->whereHas('rescheduleRequests', fn ($q) => $q->pending())
                ->count();
            self::put($out, 'schedule', $reschedules, $reschedules.' reschedule '.($reschedules === 1 ? 'request' : 'requests').' waiting');
        }

        // Attendance & Leave: the pending-leave section (LeaveRequestController::index).
        if ($branchId && $can('edit leave requests')) {
            $n = LeaveRequest::query()
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->pending()
                ->where('user_id', '!=', $user->id)   // nobody reviews their own leave
                ->count();
            self::put($out, 'attendance', $n, $n.' leave '.($n === 1 ? 'request' : 'requests').' to review');
        }

        // Recruitment pages use RecruitmentWorkflowService's own scope (spa-wide for owner / HR / HR Head).
        if ($spa?->hasFeature('manpower') && ($branchId || $user->hasAnyRole(['owner', 'hr', 'hr_head']))) {
            $recruitment = app(RecruitmentWorkflowService::class);

            if ($can('view applications')) {
                $n = $recruitment->applicantsFor($user)->where('status', \App\Models\Applicant::STATUS_PENDING)->count();
                self::put($out, 'applicants', $n, $n.' new '.($n === 1 ? 'applicant' : 'applicants'));
            }
            if ($can('view interviews')) {
                $n = $recruitment->interviewsFor($user)
                    ->where('status', \App\Models\Interview::STATUS_PENDING)
                    ->whereDate('interview_date', today())
                    ->count();
                self::put($out, 'interviews', $n, $n.' '.($n === 1 ? 'interview' : 'interviews').' today');
            }
        }

        // Pay Runs: approving and finalizing/releasing need a spa-wide role as well as
        // the permission (PayrollPermissions scope rule), and cover the whole spa.
        if ($spa?->hasFeature('payroll') && $user->isSpaWide() && $can(P::VIEW)) {
            $toApprove = $can(P::APPROVE)
                ? PayrollRun::where('spa_id', $spaId)->where('status', PayrollRun::STATUS_DRAFT)->count() : 0;
            $toRelease = $can(P::RELEASE)
                ? PayrollRun::where('spa_id', $spaId)->whereIn('status', [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_FINALIZED])->count() : 0;

            $parts = array_filter([
                $toApprove ? "$toApprove to approve" : null,
                $toRelease ? "$toRelease to finalize or release" : null,
            ]);
            self::put($out, 'pay-runs', $toApprove + $toRelease, 'Pay runs: '.implode(', ', $parts));
        }

        // Purchase Requests: pending review (PurchaseRequestController::index scope).
        if ($branchId && $spa?->hasFeature('procurement') && $can('review purchase requests')) {
            $n = PurchaseRequest::where('spa_id', $spaId)->where('branch_id', $branchId)
                ->where('status', PurchaseRequest::STATUS_PENDING)->count();
            self::put($out, 'purchase-requests', $n, $n.' purchase '.($n === 1 ? 'request' : 'requests').' to review');
        }

        return $out;
    }

    private static function put(array &$out, string $key, int $count, string $label, bool $urgent = false): void
    {
        if ($count > 0) {
            $out[$key] = ['count' => $count, 'label' => $label, 'urgent' => $urgent];
        }
    }
}
