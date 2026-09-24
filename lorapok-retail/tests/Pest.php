<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

// RefreshDatabase covers the CENTRAL database only. Tenant databases are real,
// separately-created schemas and must be dropped by whoever created them —
// see the afterEach in TenancyIsolationTest. Relying on RefreshDatabase alone
// leaks tenant schemas between tests and produces the worst kind of flake.
uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Run a closure inside a tenant's context and always return to central,
 * even if the closure throws.
 */
function withTenant(Tenant $tenant, Closure $callback): mixed
{
    tenancy()->initialize($tenant);

    try {
        return $callback($tenant);
    } finally {
        tenancy()->end();
    }
}
