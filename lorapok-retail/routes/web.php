<?php

use App\Http\Controllers\Central\LogoutController;
use App\Http\Controllers\Marketing\LeadController;
use App\Http\Controllers\Marketing\PageController;
use App\Http\Controllers\Marketing\RobotsController;
use App\Http\Controllers\Marketing\SitemapController;
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

        // The public site. `/` used to redirect into the operator panel, so an
        // anonymous visitor to the root domain hit a login wall — there was no
        // way to learn what this product is without already having an account.
        $name(Route::get('/', [PageController::class, 'home']), 'marketing.home');
        $name(Route::get('/features', [PageController::class, 'features']), 'marketing.features');
        $name(Route::get('/pricing', [PageController::class, 'pricing']), 'marketing.pricing');
        $name(Route::get('/legal/privacy', [PageController::class, 'privacy']), 'marketing.privacy');
        $name(Route::get('/legal/terms', [PageController::class, 'terms']), 'marketing.terms');

        $name(Route::get('/contact', [LeadController::class, 'contact']), 'marketing.contact');
        $name(Route::get('/start', [LeadController::class, 'apply']), 'marketing.apply');
        $name(
            Route::get('/thanks/{kind}', [LeadController::class, 'thanks'])
                ->whereIn('kind', ['contact', 'apply']),
            'marketing.thanks',
        );

        // Both forms are unauthenticated and on the open internet. The throttle
        // is per IP and deliberately tight: these are submitted once, not
        // repeatedly, so anything reaching the limit is not a customer.
        Route::middleware('throttle:6,1')->group(function () use ($name) {
            $name(Route::post('/contact', [LeadController::class, 'storeContact']), 'marketing.contact.store');
            $name(Route::post('/start', [LeadController::class, 'storeApplication']), 'marketing.apply.store');
        });

        $name(Route::get('/sitemap.xml', SitemapController::class), 'marketing.sitemap');

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

            // Written to since Phase 1 and read by nobody until now. A record
            // only counts as an audit if someone can go and look at it.
            $name(
                Route::livewire('/admin/audit', 'central.audit'),
                'central.audit',
            );
        });
    });
}

/*
|--------------------------------------------------------------------------
| robots.txt
|--------------------------------------------------------------------------
|
| Outside the central-domain loop, because it has to answer on shop subdomains
| too — and there it says the opposite. The shipped static file was the Laravel
| stub (`Disallow:` with nothing after it), which permits crawling everything,
| including the operator panel and every shop's sign-in page.
|
*/
Route::get('/robots.txt', RobotsController::class)->name('robots');
