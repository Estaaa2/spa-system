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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep the permission memo fresh: per request, and whenever an override
        // or a branch changes through the model.
        $flushPermissionCache = fn () => $this->app->make(\App\Services\Permissions\BranchPermissionCache::class)->flush();
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Foundation\Http\Events\RequestHandled::class, $flushPermissionCache);
        foreach ([\App\Models\BranchRolePermission::class, \App\Models\Branch::class] as $model) {
            $model::saved($flushPermissionCache);
            $model::deleted($flushPermissionCache);
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
    }
}
