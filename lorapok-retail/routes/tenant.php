<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\IconController;
use App\Http\Controllers\Tenant\ManifestController;
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
])->group(function () {

    // The manifest and icon are public: a browser fetches the manifest
    // before anyone signs in, and an installed app needs its icon on the
    // home screen whether or not there is a session.
    Route::get('/manifest.webmanifest', ManifestController::class)->name('tenant.manifest');
    Route::get('/icon-{size}.svg', IconController::class)
        ->whereNumber('size')
        ->name('tenant.icon');

    // Route::livewire() resolves a Livewire single-file component by name;
    // the ⚡ prefix in the filename is not part of the component name.
    Route::livewire('/login', 'tenant.auth.login')
        ->middleware('guest:tenant')
        ->name('tenant.login');

    Route::middleware('auth:tenant')->group(function () {
        Route::livewire('/', 'tenant.dashboard')->name('tenant.dashboard');

        Route::livewire('/pos', 'tenant.pos')->name('tenant.pos');
        Route::livewire('/reports', 'tenant.reports')->name('tenant.reports');

        Route::livewire('/products', 'tenant.catalog.products')->name('tenant.products');
        Route::livewire('/products/create', 'tenant.catalog.product-form')->name('tenant.products.create');
        Route::livewire('/products/{product}/edit', 'tenant.catalog.product-form')->name('tenant.products.edit');
    });
});
