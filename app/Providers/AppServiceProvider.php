<?php

namespace App\Providers;

use App\Policies\IdentityPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Request-lifetime permission memo (sidebar & RBAC rework Phase 2).
        $this->app->singleton(\App\Services\Permissions\BranchPermissionCache::class);

        // Request/job-lifetime schedule memo (staff scheduling Phase 2).
        $this->app->scoped(\App\Services\Staff\WorkScheduleResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep the schedule memo fresh: per request, and whenever a schedule input changes.
        $flushSchedules = function () {
            if ($this->app->resolved(\App\Services\Staff\WorkScheduleResolver::class)) {
                $this->app->make(\App\Services\Staff\WorkScheduleResolver::class)->flush();
            }
        };
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Foundation\Http\Events\RequestHandled::class, $flushSchedules);
        foreach ([
            \App\Models\StaffWorkSchedule::class, \App\Models\StaffWorkScheduleDay::class,
            \App\Models\StaffScheduleOverride::class, \App\Models\LeaveRequest::class, \App\Models\OperatingHours::class,
        ] as $model) {
            $model::saved($flushSchedules);
            $model::deleted($flushSchedules);
        }

        // Keep the permission memo fresh: per request, and whenever an override
        // or a branch changes through the model.
        $flushPermissionCache = fn () => $this->app->make(\App\Services\Permissions\BranchPermissionCache::class)->flush();
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Foundation\Http\Events\RequestHandled::class, $flushPermissionCache);
        foreach ([\App\Models\BranchRolePermission::class, \App\Models\Branch::class] as $model) {
            $model::saved($flushPermissionCache);
            $model::deleted($flushPermissionCache);
        }

        // Sidebar badges (Phase 4): a change to any source record bumps that spa's
        // badge version, so the 60-second cache never shows a count the user just
        // acted on. Booking-child records reach their spa through the booking.
        $bumpBadges = function ($model) {
            $spaId = $model->spa_id ?? $model->booking?->spa_id ?? null;
            if ($spaId) {
                \App\Services\Navigation\NavBadges::bump((int) $spaId);
            }
        };
        foreach ([
            \App\Models\Booking::class, \App\Models\ReassignmentRequest::class, \App\Models\RescheduleRequest::class,
            \App\Models\LeaveRequest::class, \App\Models\Applicant::class, \App\Models\Interview::class,
            \App\Models\PayrollRun::class, \App\Models\PurchaseRequest::class,
        ] as $model) {
            $model::saved($bumpBadges);
            $model::deleted($bumpBadges);
        }
        // Admin badges (version 0): spa verification and staff identity reviews.
        foreach ([\App\Models\Spa::class, \App\Models\IdentityVerification::class] as $model) {
            $model::saved(fn () => \App\Services\Navigation\NavBadges::bump(0));
            $model::deleted(fn () => \App\Services\Navigation\NavBadges::bump(0));
        }

        Password::defaults(function () {
            return Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols();
        });

        // Staff identity verification (see App\Policies\IdentityPolicy).
        Gate::define('submit-identity-verification', [IdentityPolicy::class, 'submit']);
        Gate::define('review-identity-verifications', [IdentityPolicy::class, 'review']);
        Gate::define('view-profile-photo', [IdentityPolicy::class, 'viewPhoto']);
        // Therapist pages: therapist role or "Performs treatments" (decision Q1b/Q44).
        Gate::define('act-as-treatment-provider', fn (\App\Models\User $user) => $user->isTreatmentProvider());
        // "Can't make it" on an appointment: permission or "Performs treatments" (answer P16).
        Gate::define('request-appointment-reassignment', fn (\App\Models\User $user) => $user->canRequestReassignment());
        // Attendance & Leave page (Phase 6.3): self-service for every active staff
        // member, or one of the attendance / leave permissions (same list as before).
        Gate::define('open-attendance-page', fn (\App\Models\User $user) => $user->hasAttendanceSelfService()
            || collect(\App\Services\Navigation\Sidebar::ATTENDANCE_PERMISSIONS)->contains(fn ($p) => $user->hasBranchPermission($p)));
    }
}
