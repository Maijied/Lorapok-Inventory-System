<?php

use App\Http\Controllers\Central\LogoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central routes
|--------------------------------------------------------------------------
|
| Lorapok Retail itself: marketing, sign-up and the super-admin panel.
|
| These are bound to the central domains explicitly. Tenant routes in
| routes/tenant.php also claim '/', and without a domain constraint the tenant
| route matches first on every host — PreventAccessFromCentralDomains then
| 404s the central homepage. Binding here keeps the two apps off each other's
| hostnames.
|
| Only the FIRST central domain carries the route names. `central_domains`
| holds three entries in development (the root domain, 127.0.0.1, localhost),
| and naming the routes on each one registers three routes called `home`.
| Uncached that silently works — the last registration wins. `route:cache`
| refuses it outright, so the production image could not be built at all until
| this was fixed, and nobody would have noticed before the first deploy.
|
| The unnamed copies still match requests; only URL generation is anchored to
| the canonical domain, which is what you want in a link anyway.
|
*/

$domains = array_values(config('tenancy.central_domains'));

foreach ($domains as $index => $domain) {
    $canonical = $index === 0;

    Route::domain($domain)->group(function () use ($canonical) {
        $name = fn (Illuminate\Routing\Route $route, string $name) => $canonical
            ? $route->name($name)
            : $route;

        $name(
            Route::get('/', fn () => redirect()->route('central.shops')),
            'home',
        );

        $name(
            Route::livewire('/admin/login', 'central.auth.login')->middleware('guest:web'),
            'central.login',
        );

        Route::middleware('auth:web')->group(function () use ($name) {
            $name(
                Route::livewire('/admin/shops', 'central.shops'),
                'central.shops',
            );

            $name(
                Route::post('/admin/logout', LogoutController::class),
                'central.logout',
            );
        });
    });
}
