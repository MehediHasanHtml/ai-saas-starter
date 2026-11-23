<?php

use App\Exceptions\QuotaExceededException;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureTenantRole;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'tenant.role' => EnsureTenantRole::class,
            'super-admin' => EnsureSuperAdmin::class,
        ]);

        // Resolve the tenant right after authentication and BEFORE
        //  - rate limiting, so the "ai" limiter can key on the organization and
        //    scale with its plan, and
        //  - route-model binding, so tenant-scoped models (Document,
        //    Conversation, ...) are looked up inside the current organization
        //    only. Cross-tenant ids become 404s.
        $middleware->prependToPriorityList(
            before: ThrottleRequests::class,
            prepend: ResolveTenant::class,
        );

        // API-only app: never redirect guests to a login page, return 401.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');

        // Stripe webhooks are signed, not CSRF-protected.
        $middleware->validateCsrfTokens(except: ['stripe/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (QuotaExceededException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'quota_exceeded',
                'limit' => $e->limit,
                'upgrade_url' => '/dashboard/billing',
            ], 402);
        });
    })->create();
