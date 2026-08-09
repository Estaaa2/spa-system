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
        // Add ForceJsonResponse to API group
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class, // Add this
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\Cors::class,
        ]);

        $middleware->web(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\Cors::class,
        ]);

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'check-owner-exists' => \App\Http\Middleware\CheckOwnerExists::class,
            'owner-only' => \App\Http\Middleware\OwnerOnly::class,
            'enforce.branch' => \App\Http\Middleware\LockBranchForNonOwner::class,
            'force.password.change' => \App\Http\Middleware\ForcePasswordChange::class,
            'branch.permission' => \App\Http\Middleware\EnsureBranchPermission::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\EnsureCurrentBranch::class,
            \App\Http\Middleware\RefreshPermissionsCache::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/paymongo',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Force JSON response for API exceptions
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
        });

        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
            }
        });

        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'API endpoint not found'], 404);
            }
        });
    })
    ->create();
