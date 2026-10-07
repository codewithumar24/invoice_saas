<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\PlatformMaintenance::class,
        ]);

        // The cookie-consent choice is written by JavaScript, so it is stored unencrypted
        $middleware->encryptCookies(except: ['cookie_consent']);

        $middleware->alias([
            'tenant' => \App\Http\Middleware\SetTenant::class,
            'onboarded' => \App\Http\Middleware\EnsureOnboarded::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'feature' => \App\Http\Middleware\EnsureFeature::class,
            'locale' => \App\Http\Middleware\SetLocale::class,
            'super-admin' => \App\Http\Middleware\SuperAdmin::class,
            '2fa-setup' => \App\Http\Middleware\RequireTwoFactorSetup::class,
            'portal' => \App\Http\Middleware\PortalAuth::class,
            'api.token' => \App\Http\Middleware\ApiTokenAuth::class,
        ]);

        // Payment and billing webhooks are verified by signature instead of a CSRF token
        // Tenant resolution must run BEFORE route model binding, otherwise bound models
        // would be looked up without a workspace and never be found.
        foreach ([\App\Http\Middleware\SetTenant::class, \App\Http\Middleware\PortalAuth::class, \App\Http\Middleware\ApiTokenAuth::class] as $tenantMiddleware) {
            $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, $tenantMiddleware);
        }

        // Signs out other sessions when the password changes
        $middleware->authenticateSessions();

        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        // Behind a load balancer / reverse proxy: trust forwarded headers (set TRUSTED_PROXIES in production)
        $middleware->trustProxies(at: env('TRUSTED_PROXIES') ? explode(',', env('TRUSTED_PROXIES')) : null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // One response shape for every API / AJAX error. Details stay in the logs, never in the response.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json(['success' => false, 'message' => __('Validation failed.'), 'errors' => $e->errors()], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.', 'errors' => (object) []], 401);
            }

            $status = match (true) {
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 404,
                $e instanceof \Illuminate\Auth\Access\AuthorizationException => 403,
                default => 500,
            };

            $message = match (true) {
                $status === 404 => __('Not found.'),
                $status === 403 => $e->getMessage() ?: __('Forbidden.'),
                $status >= 500 => __('Something went wrong. Please try again.'),
                default => $e->getMessage() ?: __('Request failed.'),
            };

            return response()->json(['success' => false, 'message' => $message, 'errors' => (object) []], $status);
        });
    })->create();
