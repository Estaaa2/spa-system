<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Models\Booking;
use App\Models\DssRecommendation;
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

            // Open = pending and the booking hasn't ended (lifecycle Phase 3, D6).
            // One query for the count and the "!" part (sidebar query budget).
            $tomorrow = today()->addDay()->toDateString();
            $r = ReassignmentRequest::query()
                ->open()
                ->join('bookings', 'bookings.id', '=', 'reassignment_requests.booking_id')
                ->where('bookings.spa_id', $spaId)
                ->where('bookings.branch_id', $branchId)
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN DATE(bookings.appointment_date) <= ? THEN 1 ELSE 0 END) as soon', [$tomorrow])
                ->first();

            $reassignments = (int) ($r->total ?? 0);
            // D6: "!" when the affected booking is today or tomorrow.
            $reassignSoon = (int) ($r->soon ?? 0);

            // Needs Checkout list: still open after the end time (booking lifecycle
            // fix, Phase 2, D3). Same query as BookingController::adminIndex.
            $overdue = Booking::query()
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->overdue();

            $o = $overdue
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN DATE(appointment_date) < ? THEN 1 ELSE 0 END) as earlier', [today()->toDateString()])
                ->first();

            $overdueCount   = (int) ($o->total ?? 0);
            $overdueEarlier = (int) ($o->earlier ?? 0);

            $total = $unassignedCount + $reassignments + $overdueCount;
            $parts = array_filter([
                $overdueCount ? "$overdueCount to check out".($overdueEarlier ? " ($overdueEarlier from earlier days)" : '') : null,
                $unassignedCount ? "$unassignedCount unassigned".($startingToday ? " ($startingToday starting today)" : '') : null,
                $reassignments ? "$reassignments reassignment ".($reassignments === 1 ? 'request' : 'requests').($reassignSoon ? " ($reassignSoon today or tomorrow)" : '') : null,
            ]);
            // "!" for an unassigned booking that starts today (answer P14), or a booking
            // nobody closed from an earlier day (lifecycle Phase 2, Q3-A).
            self::put($out, 'appointments', $total, implode(', ', $parts), $startingToday > 0 || $overdueEarlier > 0 || $reassignSoon > 0);

            // Schedule page: reschedule requests are reviewed there.
            // Counted per booking, like the "Reschedule Requests" list above the calendar
            // (ScheduleController, Phase 4.1). A booking has at most one pending request.
            // Open only: past the original start a request is expired (lifecycle Phase 3, D5).
            $rescheduleBase = Booking::query()
                ->where('spa_id', $spaId)
                ->where('branch_id', $branchId)
                ->whereHas('rescheduleRequests', fn ($q) => $q->open());

            $rs = $rescheduleBase
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN DATE(appointment_date) <= ? THEN 1 ELSE 0 END) as soon', [$tomorrow])
                ->first();

            $reschedules = (int) ($rs->total ?? 0);
            // Q2-A: "!" when the original appointment is today or tomorrow (same rule as D6).
            $rescheduleSoon = (int) ($rs->soon ?? 0);

            self::put(
                $out,
                'schedule',
                $reschedules,
                $reschedules.' reschedule '.($reschedules === 1 ? 'request' : 'requests').' waiting'.($rescheduleSoon ? " ($rescheduleSoon today or tomorrow)" : ''),
                $rescheduleSoon > 0
            );
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

        // Decision Support: open recommendations for this branch (+ spa-level ones for the
        // owner). Same gate as the page: permission + plan feature (DSS revamp, F7).
        // Stored by dss:generate, so this is one COUNT (Q5 B).
        if ($branchId && $spa?->hasFeature('insights') && $can('view decision support')) {
            $d = DssRecommendation::query()
                ->visibleTo($user, $branchId)
                ->open()
                ->selectRaw("COUNT(*) as total, SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high")
                ->first();
            $total = (int) ($d->total ?? 0);
            $high = (int) ($d->high ?? 0);
            self::put($out, 'decision-support', $total,
                $total.' '.($total === 1 ? 'decision' : 'decisions').' waiting'.($high ? " ($high high priority)" : ''),
                $high > 0);
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
