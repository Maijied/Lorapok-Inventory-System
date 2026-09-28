<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\IconController;
use App\Http\Controllers\Tenant\LogoController;
use App\Http\Controllers\Tenant\LogoutController;
use App\Http\Controllers\Tenant\ManifestController;
use App\Http\Middleware\BlockImpersonatedWrites;
use App\Http\Middleware\BlockSuspendedShops;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant routes
|--------------------------------------------------------------------------
|
| A single shop, served from its own subdomain against its own database.
|
| Everything here runs behind InitializeTenancyByDomain, so `tenant()` is
| always populated and every query targets that shop's database.
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    BlockSuspendedShops::class,
    // Applied to the whole group rather than to a list of write routes: a
    // route added later is covered without anyone remembering, whereas a list
    // is correct only until it is forgotten.
    BlockImpersonatedWrites::class,
])->group(function () {

    // The manifest and icon are public: a browser fetches the manifest
    // before anyone signs in, and an installed app needs its icon on the
    // home screen whether or not there is a session.
    Route::get('/manifest.webmanifest', ManifestController::class)->name('tenant.manifest');
    Route::get('/icon-{size}.svg', IconController::class)
        ->whereNumber('size')
        ->name('tenant.icon');

    // The shop's own logo, when it has uploaded one. Same reasoning as
    // the icon: the manifest points at it, and the manifest is read
    // before there is a session to check.
    Route::get('/logo.png', LogoController::class)->name('tenant.logo');

    // Route::livewire() resolves a Livewire single-file component by name;
    // the ⚡ prefix in the filename is not part of the component name.
    Route::livewire('/login', 'tenant.auth.login')
        ->middleware('guest:tenant')
        ->name('tenant.login');

    Route::middleware('auth:tenant')->group(function () {
        Route::livewire('/', 'tenant.dashboard')->name('tenant.dashboard');

        // Sign-out lives in the nav on every page, so it is a route rather
        // than an action on one component. POST because it changes state.
        Route::post('/logout', LogoutController::class)->name('tenant.logout');

        Route::livewire('/pos', 'tenant.pos')->name('tenant.pos');
        Route::livewire('/reports', 'tenant.reports')->name('tenant.reports');

        // The shop's own legal identity. Phase 16 built the domain and the
        // operator review queue; without this a shop's details could only be
        // entered on its behalf.
        Route::livewire('/settings/verification', 'tenant.settings.verification')
            ->name('tenant.verification');

        // `tenants.logo_path` has existed since Phase 1 with nothing
        // writing to it; this is what writes it.
        Route::livewire('/settings/branding', 'tenant.settings.branding')
            ->name('tenant.branding');

        Route::livewire('/products', 'tenant.catalog.products')->name('tenant.products');
        Route::livewire('/products/create', 'tenant.catalog.product-form')->name('tenant.products.create');
        Route::livewire('/products/{product}/edit', 'tenant.catalog.product-form')->name('tenant.products.edit');
    });
});
