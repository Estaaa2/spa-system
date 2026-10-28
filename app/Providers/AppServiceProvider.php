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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
    }
}
