<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Billing\ManualGateway;
use App\Domain\Billing\PaymentGateway;
use App\Http\Middleware\BlockImpersonatedWrites;
use App\Http\Middleware\InitializeTenancyIfTenantDomain;
use App\Models\Tenant\Product;
use App\Models\Tenant\Sale;
use App\Policies\ProductPolicy;
use App\Policies\SalePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * Which gateway takes money.
         *
         * Manual today, and not as a placeholder: Stripe does not operate in
         * Bangladesh, so a shop pays by bKash, Nagad or bank transfer and an
         * operator confirms it. Binding it here rather than newing it up in
         * BillingService is what lets bKash drop in later by changing one
         * line, without touching how invoicing works.
         */
        $this->app->bind(PaymentGateway::class, ManualGateway::class);

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
        Gate::policy(Sale::class, SalePolicy::class);
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
                ->middleware([
                    'web',
                    InitializeTenancyIfTenantDomain::class,
                    // Attached here rather than in routes/tenant.php because
                    // this endpoint is registered globally and is not in that
                    // group — so a read-only impersonation session would have
                    // been unrestricted through the one route that carries
                    // virtually every write in the application.
                    BlockImpersonatedWrites::class,
                ]);
        });
    }
}
