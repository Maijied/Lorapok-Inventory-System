<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Tenant routes live on subdomains, so an unauthenticated visitor must
        // be sent to that shop's own login page rather than the central app's.
        // Central and tenant apps have separate sign-in pages; the host
        // decides which one an unauthenticated visitor is sent to.
        $middleware->redirectGuestsTo(function ($request) {
            $central = in_array($request->getHost(), config('tenancy.central_domains', []), true);

            return $central ? route('central.login') : route('tenant.login');
        });
        $middleware->redirectUsersTo(function ($request) {
            $central = in_array($request->getHost(), config('tenancy.central_domains', []), true);

            return $central ? route('central.shops') : route('tenant.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
