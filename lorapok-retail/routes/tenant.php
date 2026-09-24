<?php

declare(strict_types=1);

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
])->group(function () {

    // Route::livewire() resolves a Livewire single-file component by name;
    // the ⚡ prefix in the filename is not part of the component name.
    Route::livewire('/login', 'tenant.auth.login')
        ->middleware('guest:tenant')
        ->name('tenant.login');

    Route::middleware('auth:tenant')->group(function () {
        Route::livewire('/', 'tenant.dashboard')->name('tenant.dashboard');
    });
});
