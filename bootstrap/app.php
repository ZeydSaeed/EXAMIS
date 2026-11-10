<?php

use App\Enums\Portal;
use App\Exceptions\DomainRuleViolation;
use App\Http\Controllers\Public\LivenessController;
use App\Http\Controllers\Public\ReadinessController;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RejectNullBytes;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SelectPortal;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        // Health checks answer on every host, outside the web group: no
        // session, cookie or CSRF token for a probe.
        then: function (): void {
            Route::get('up', LivenessController::class)->name('health.live');
            Route::get('health/ready', ReadinessController::class)->name('health.ready');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SecurityHeaders sits outside RejectNullBytes so that its 400 gets
        // the headers too.
        $middleware->prepend([SelectPortal::class, AssignRequestId::class, SecurityHeaders::class, RejectNullBytes::class]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            SetLocale::class,
            EnsureAccountIsActive::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request): string => route(Portal::forRequest($request) === Portal::Staff ? 'staff.login' : 'apply.terms'));
        $middleware->redirectUsersTo(fn (Request $request): string => route(Portal::forRequest($request) === Portal::Staff ? 'admin.home' : 'dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->dontReport(DomainRuleViolation::class);

        $exceptions->render(function (DomainRuleViolation $exception, Request $request) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => [$exception->field => [$exception->getMessage()]],
                    ...$exception->context,
                ], $exception->status);
            }

            return back()
                ->withErrors([$exception->field => $exception->getMessage()])
                ->with('domain_context', $exception->context);
        });
    })->create();
