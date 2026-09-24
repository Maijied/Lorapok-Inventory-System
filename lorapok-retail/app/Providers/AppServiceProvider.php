<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\InitializeTenancyIfTenantDomain;
use App\Models\Tenant\Product;
use App\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;
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
        $this->registerPolicies();
    }

    /**
     * Registered explicitly: policy auto-discovery expects
     * App\Policies\Tenant\ProductPolicy for App\Models\Tenant\Product, and
     * silently falls back to "denied" when it does not find one.
     */
    private function registerPolicies(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
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
