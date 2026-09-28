<?php

declare(strict_types=1);

use App\Domain\Central\ShopProvisioner;
use App\Domain\Help\HelpRepository;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * In-app help.
 *
 * The test that matters here is the coverage one: a screen added later
 * without help is exactly the kind of gap nobody notices until a shop asks a
 * question the application should have answered.
 */
beforeEach(function () {
    $this->help = app(HelpRepository::class);
});

afterEach(function () {
    tenancy()->end();

    Tenant::query()->get()->each(function (Tenant $tenant) {
        try {
            $tenant->database()->manager()->deleteDatabase($tenant);
        } catch (Throwable) {
            // Already gone.
        }
        Tenant::withoutEvents(fn () => $tenant->delete());
    });
});

it('has help for every screen a person actually uses', function () {
    // The list of exemptions is explicit: sign-in pages, redirects and
    // machine endpoints. Anything else that renders for a human and has no
    // help fails here rather than shipping half-explained.
    $missing = [];

    foreach (Route::getRoutes() as $route) {
        $name = $route->getName();

        if ($name === null
            || ! in_array('GET', $route->methods(), true)
            || in_array($name, HelpRepository::exempt(), true)
            // Marketing pages explain themselves; the handbook is the help.
            || str_starts_with($name, 'marketing.')
            // Routes registered by packages, not screens we wrote. Excluded
            // by prefix rather than listed individually, because the set
            // changes when a dependency updates and a stale list would fail
            // the build for someone else's route.
            || preg_match('/^(horizon|livewire|stancl|storage|sanctum|ignition)\./', $name) === 1
            || $name === 'robots') {
            continue;
        }

        if (! app(HelpRepository::class)->hasHelpFor($name)) {
            $missing[] = $name;
        }
    }

    expect(array_values(array_unique($missing)))->toBe(
        [],
        'These screens have no help. Add a document under resources/docs/ and map it '
        ."in HelpRepository, or list the route as exempt:\n".implode("\n", array_unique($missing))
    );
});

it('points every mapping at a document that exists', function () {
    // A mapping to a missing file renders an empty drawer, which reads as
    // "there is no help" rather than "someone deleted the file".
    $broken = [];

    foreach (HelpRepository::map() as $route => $slug) {
        if ($this->help->pathFor($slug) === null) {
            $broken[] = "{$route} -> resources/docs/{$slug}.md";
        }
    }

    expect($broken)->toBe([], "Help mapped to files that do not exist:\n".implode("\n", $broken));
});

it('refuses a slug that tries to leave the docs directory', function () {
    // The slug reaches this from a URL. Without the guard, `../../.env` is a
    // readable file.
    foreach (['../../.env', 'shop/../../../etc/passwd', '..%2f..%2f.env', '/etc/passwd'] as $slug) {
        expect($this->help->pathFor($slug))->toBeNull("Accepted a traversal slug: {$slug}");
    }
});

it('serves the public handbook', function () {
    $this->get('http://lorapok.localhost/docs')
        ->assertOk()
        ->assertSee('Handbook')
        ->assertSee('Running a shop');
});

it('serves a handbook page', function () {
    $this->get('http://lorapok.localhost/docs/operator/impersonation')
        ->assertOk()
        // The part an operator most needs to have read.
        ->assertSee('Read-only by default');
});

it('404s a handbook page that does not exist', function () {
    $this->get('http://lorapok.localhost/docs/shop/nonsense')->assertNotFound();
});

it('shows the drawer on a shop screen', function () {
    $shop = app(ShopProvisioner::class)->create(
        name: 'Karim Mobile', slug: 'karim',
        ownerName: 'Karim Uddin', ownerEmail: 'owner@karim.test', ownerPassword: 'password12',
    );

    withTenant($shop, function () {
        actingAsTenantUser(Tenant\User::where('email', 'owner@karim.test')->firstOrFail());

        $this->get('http://karim.lorapok.localhost/')
            ->assertOk()
            ->assertSee('Help')
            // Content from shop/overview.md, proving it rendered rather than
            // just that the button exists.
            ->assertSee('Needs reordering');
    });
});

it('does not show the drawer on a sign-in page', function () {
    $shop = app(ShopProvisioner::class)->create(
        name: 'Karim Mobile', slug: 'karim',
        ownerName: 'Karim Uddin', ownerEmail: 'owner@karim.test', ownerPassword: 'password12',
    );

    // Nothing to explain, and a help panel over a login box is noise.
    $this->get('http://karim.lorapok.localhost/login')
        ->assertOk()
        ->assertDontSee('help-prose');
});

it('shows operator help on the operator panel', function () {
    $operator = User::create([
        'name' => 'Operator', 'email' => 'ops@lorapok.test',
        'password' => 'password12', 'is_super_admin' => true, 'is_active' => true,
    ]);

    $this->actingAs($operator, 'web')
        ->get('http://lorapok.localhost/admin/shops')
        ->assertOk()
        // From operator/shops.md — the thing that cannot be undone.
        ->assertSee('cannot be changed afterwards');
});
