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
            'owner.onboarding' => \App\Http\Middleware\EnsureOwnerOnboardingState::class,
            'plan' => \App\Http\Middleware\EnsurePlanFeature::class,
            'spa.access' => \App\Http\Middleware\EnsureSpaAccess::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\EnsureCurrentBranch::class,
            \App\Http\Middleware\LockBranchForNonOwner::class,
            \App\Http\Middleware\RefreshPermissionsCache::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/paymongo',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // api/* always answers in JSON, even when an error happens before
        // ForceJsonResponse has set the Accept header (demo safety Phase 1).
        $exceptions->shouldRenderJsonWhen(function (\Illuminate\Http\Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // The CORS catch-all `Route::options('/{any}')` matches every URL, so an
        // unknown page answered 405 ("method not allowed") instead of 404. When
        // OPTIONS is the only allowed method, the page simply doesn't exist.
        $exceptions->map(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e) {
            $allow = strtoupper(trim((string) ($e->getHeaders()['Allow'] ?? '')));

            return $allow === 'OPTIONS'
                ? new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Not Found', $e)
                : $e;
        });

        // 419 on a normal web form (the page sat open too long): go back to
        // the form with what was typed (never passwords or files) and a message,
        // instead of a dead-end page. With no same-site page to go back to,
        // the errors/419 page shows (demo safety Phase 1, Q7 B).
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419 || $request->is('api/*') || $request->expectsJson() || $request->isMethod('GET')) {
                return null;
            }

            $referer = (string) $request->headers->get('referer', '');

            $host = parse_url($referer, PHP_URL_HOST);
            $sameSite = $host === null
                ? str_starts_with($referer, '/') && ! str_starts_with($referer, '//')
                : $host === $request->getHost();

            if ($referer === '' || ! $sameSite) {
                return null;
            }

            return redirect()->to($referer)
                ->withInput(\Illuminate\Support\Arr::except($request->input(), ['_token', '_method', 'password', 'password_confirmation', 'current_password']))
                ->with('error', 'Your session expired while the page was open. Please check the form and submit it again.');
        });

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
