<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
    // The central root now serves the public marketing page. What matters
    // here is unchanged: the TENANT route did not match, so tenancy was never
    // initialised and no query could have reached a shop's database.
    $this->get('http://lorapok.localhost/')->assertOk();

    expect(tenant())->toBeNull();
});

it('keeps every tenant model out of the central database', function () {
    // CLAUDE.md promises "every model gets an isolation test; an architecture
    // test fails the build if one is missing". A per-model checklist would
    // only prove each model was NAMED somewhere. This proves the property
    // itself, generically, for every model that exists now or is added later:
    // a tenant model's table must not exist centrally, so a forgotten scope
    // cannot silently fall back to shared data.
    $tenant = makeTenant('alpha');

    $models = collect(glob(app_path('Models/Tenant/*.php')))
        ->map(fn (string $path): string => 'App\\Models\\Tenant\\'.basename($path, '.php'));

    expect($models)->not->toBeEmpty();

    $leaked = [];
    $misconnected = [];

    foreach ($models as $class) {
        $table = (new $class)->getTable();

        // Present in the shop's own database...
        tenancy()->initialize($tenant);
        $inTenant = Schema::connection('tenant')->hasTable($table);
        $connection = (new $class)->getConnectionName();
        tenancy()->end();

        if (! $inTenant) {
            $misconnected[] = "{$class}: table `{$table}` missing from the tenant schema";
        }

        // ...and absent from the central one.
        //
        // `users` is the deliberate exception and the only one: the central
        // database holds Lorapok operators, each shop database holds that
        // shop's staff. Same table name, different databases, different
        // people — which is exactly why the same email may exist in both
        // without the two ever authenticating across the boundary.
        if ($table !== 'users' && Schema::connection('mysql')->hasTable($table)) {
            $leaked[] = "{$class}: table `{$table}` also exists centrally";
        }

        // A hardcoded connection name would pin the model to one database and
        // defeat tenancy entirely.
        if ($connection !== null && $connection !== 'tenant') {
            $misconnected[] = "{$class}: pinned to connection `{$connection}`";
        }
    }

    expect($leaked)->toBe([], implode("\n", $leaked))
        ->and($misconnected)->toBe([], implode("\n", $misconnected));
});
