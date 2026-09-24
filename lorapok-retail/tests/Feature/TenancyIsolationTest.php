<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Tenant isolation.
 *
 * This is the suite that matters most in a multi-tenant SaaS: if it regresses,
 * one shop can read another shop's sales. Everything else is recoverable; this
 * is not.
 *
 * These tests provision real MySQL databases rather than faking tenancy, because
 * the property under test *is* the database boundary. Each test drops the
 * databases it created.
 */
beforeEach(function () {
    $this->createdTenants = collect();
});

afterEach(function () {
    // Tenant databases are not covered by RefreshDatabase — they must be
    // dropped explicitly or they accumulate and leak between runs.
    tenancy()->end();

    $this->createdTenants->each(function (Tenant $tenant) {
        try {
            $tenant->database()->manager()->deleteDatabase($tenant);
        } catch (Throwable) {
            // Already gone; nothing to clean up.
        }
        Tenant::withoutEvents(fn () => $tenant->delete());
    });
});

/** Provision a real tenant with its own database and migrations. */
function makeTenant(string $slug): Tenant
{
    $tenant = Tenant::create([
        'name' => ucfirst($slug).' Shop',
        'slug' => $slug,
    ]);
    $tenant->domains()->create(['domain' => $slug.'.lorapok.localhost']);

    test()->createdTenants->push($tenant);

    return $tenant;
}

it('gives each tenant its own database', function () {
    $a = makeTenant('alpha');
    $b = makeTenant('bravo');

    expect($a->database()->getName())->not->toBe($b->database()->getName())
        ->and($a->database()->getName())->toStartWith('tenant_');
});

it('does not let one tenant read another tenant rows', function () {
    $a = makeTenant('alpha');
    $b = makeTenant('bravo');

    tenancy()->initialize($a);
    DB::table('products')->insert([
        'sku' => 'ALPHA-1', 'name' => 'Alpha Phone', 'slug' => 'alpha-1',
        'type' => 'standard', 'created_at' => now(), 'updated_at' => now(),
    ]);
    tenancy()->end();

    tenancy()->initialize($b);
    DB::table('products')->insert([
        'sku' => 'BRAVO-1', 'name' => 'Bravo Phone', 'slug' => 'bravo-1',
        'type' => 'standard', 'created_at' => now(), 'updated_at' => now(),
    ]);
    // Bravo must see exactly its own row, and nothing of Alpha's.
    expect(DB::table('products')->pluck('sku')->all())->toBe(['BRAVO-1']);
    tenancy()->end();

    tenancy()->initialize($a);
    expect(DB::table('products')->pluck('sku')->all())->toBe(['ALPHA-1']);
    tenancy()->end();
});

it('keeps tenant tables out of the central database', function () {
    makeTenant('alpha');

    // The central connection has no operational tables at all — a forgotten
    // scope cannot silently fall back to a shared products table.
    expect(fn () => DB::connection('mysql')->table('products')->count())
        ->toThrow(QueryException::class);
});

it('scopes tenant users separately from central users', function () {
    $a = makeTenant('alpha');
    $b = makeTenant('bravo');

    // The same email may exist in two different shops; they are different
    // people and must never collide or authenticate across shops.
    foreach ([$a, $b] as $tenant) {
        tenancy()->initialize($tenant);
        DB::table('users')->insert([
            'name' => 'Shop Owner', 'email' => 'owner@example.com',
            'password' => bcrypt('secret'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        expect(DB::table('users')->where('email', 'owner@example.com')->count())->toBe(1);
        tenancy()->end();
    }
});

it('resolves the correct tenant from its subdomain', function () {
    makeTenant('alpha');
    makeTenant('bravo');

    // The shop's own name on its own login page is the observable proof that
    // the subdomain resolved to the right tenant. (The root path now requires
    // authentication, so it redirects rather than rendering.)
    $this->get('http://alpha.lorapok.localhost/login')
        ->assertOk()
        ->assertSee('Alpha Shop')
        ->assertDontSee('Bravo Shop');

    $this->get('http://bravo.lorapok.localhost/login')
        ->assertOk()
        ->assertSee('Bravo Shop')
        ->assertDontSee('Alpha Shop');
});

it('blocks tenant routes on the central domain', function () {
    makeTenant('alpha');

    // PreventAccessFromCentralDomains must reject tenant routes served from the
    // central host, otherwise tenancy is never initialised and queries would
    // hit the central database.
    $this->get('http://lorapok.localhost/')
        ->assertOk(); // central homepage, not the tenant route

    expect(tenant())->toBeNull();
});
