<?php

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
*/

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', function () {
            return view('welcome');
        })->name('home');
    });
}
