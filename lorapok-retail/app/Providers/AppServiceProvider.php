<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\InitializeTenancyIfTenantDomain;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerTenantAwareLivewireRoute();
    }

    /**
     * Livewire registers its update endpoint once, globally, outside our
     * tenant route group. A component rendered on a shop subdomain therefore
     * posts its actions to a route with no tenancy initialised, and every
     * query silently hits the central database instead of the shop's.
     *
     * Re-registering the endpoint with tenancy middleware fixes that for
     * tenant pages while leaving it working on the central domain.
     */
    private function registerTenantAwareLivewireRoute(): void
    {
        Livewire::setUpdateRoute(function ($handle) {
            return Route::post('/livewire/update', $handle)
                ->middleware(['web', InitializeTenancyIfTenantDomain::class]);
        });
    }
}
