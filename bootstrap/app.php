<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'check-owner-exists' => \App\Http\Middleware\CheckOwnerExists::class,
            'owner-only' => \App\Http\Middleware\OwnerOnly::class,
            'enforce.branch' => \App\Http\Middleware\LockBranchForNonOwner::class,
            'force.password.change' => \App\Http\Middleware\ForcePasswordChange::class,
        ]);
    })
    ->withMiddleware(function ($middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\EnsureCurrentBranch::class,
            \App\Http\Middleware\RefreshPermissionsCache::class, 
        ]);
        $middleware->validateCsrfTokens(except: [
        'paymongo/webhook', // Add the path here (no leading slash)
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
