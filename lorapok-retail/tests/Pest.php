<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\Tenant\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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
 * Sign in as a shop user for the duration of a test.
 *
 * Auth::guard('tenant')->login() alone is not enough: Gate resolves the user
 * from the DEFAULT guard, so every authorisation check would see a guest and
 * every page would render 403. In a real request the `auth:tenant` middleware
 * calls shouldUse() for us; tests have to do it explicitly.
 */
function actingAsTenantUser(User $user): User
{
    Auth::guard('tenant')->login($user);
    Auth::shouldUse('tenant');

    return $user;
}

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
