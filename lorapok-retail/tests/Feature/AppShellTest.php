<?php

declare(strict_types=1);

use App\Domain\Central\ShopProvisioner;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\Tenant\User;
use App\Models\User as CentralUser;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/**
 * The application shell.
 *
 * Until now both layouts were a bare <main>: no sidebar, no topbar, and the
 * shop dashboard linked to nothing at all, so a cashier could only reach the
 * till by typing its URL. The operator panel had no way to sign out.
 *
 * These tests pin the two properties that matter — that every user can reach
 * the screens their role allows, and that the nav never offers one they
 * cannot.
 */
beforeEach(function () {
    $this->shop = app(ShopProvisioner::class)->create(
        name: 'Karim Mobile',
        slug: 'karim',
        ownerName: 'Karim Uddin',
        ownerEmail: 'owner@karim.test',
        ownerPassword: 'password12',
    );
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

/** Sign in as a shop user holding one role, and fetch the dashboard. */
function shellAs(Role $role): TestResponse
{
    return withTenant(test()->shop, function () use ($role) {
        // `shell-` prefixed so this never collides with owner@karim.test,
        // which ShopProvisioner already created for the shop.
        $user = User::create([
            'name' => $role->label(),
            'email' => 'shell-'.strtolower($role->value).'@karim.test',
            'password' => 'password12',
            'is_active' => true,
        ])->syncRoles([$role->value]);

        actingAsTenantUser($user);

        return test()->get('http://karim.lorapok.localhost/');
    });
}

it('gives an owner a link to every screen they can use', function () {
    $response = shellAs(Role::Owner)->assertOk();

    // The dashboard used to be a dead end. These are the four screens that
    // exist today; a nav item for one that does not exist is the bug that
    // killed the old welcome page.
    $response->assertSee('Overview')
        ->assertSee('Sell')
        ->assertSee('Products')
        ->assertSee('Reports')
        ->assertSee('href="http://karim.lorapok.localhost/pos"', escape: false)
        ->assertSee('href="http://karim.lorapok.localhost/reports"', escape: false);
});

it('does not offer a cashier the reports they cannot open', function () {
    // A cashier deliberately has neither VIEW_REPORTS nor VIEW_MARGIN: those
    // are the levers used to hide a till shortfall. Offering the link and
    // then 403ing teaches staff that the app is broken, not that they lack
    // permission.
    $response = shellAs(Role::Cashier)->assertOk();

    $response->assertSee('Sell')
        ->assertDontSee('href="http://karim.lorapok.localhost/reports"', escape: false);
});

it('keeps cost and margin off a cashier dashboard', function () {
    // Stock value is computed at average COST. On an ungated dashboard it
    // would leak exactly the figure VIEW_MARGIN exists to protect.
    shellAs(Role::Cashier)
        ->assertOk()
        ->assertDontSee('Stock value')
        ->assertDontSee('Today’s margin');
});

it('shows an owner the figures a cashier is denied', function () {
    shellAs(Role::Owner)
        ->assertOk()
        ->assertSee('Stock value')
        ->assertSee('Today’s margin');
});

it('gives a stock keeper the catalogue but not the till', function () {
    // Receives and counts stock; no access to money.
    $response = shellAs(Role::StockKeeper)->assertOk();

    $response->assertSee('Products')
        ->assertDontSee('href="http://karim.lorapok.localhost/pos"', escape: false);
});

it('puts the skip link before the navigation in tab order', function () {
    $html = shellAs(Role::Owner)->getContent();

    // Keyboard users must be able to jump the sidebar on every page, which
    // only works if the skip link comes first in the document.
    expect(strpos($html, 'skip-link'))->toBeLessThan(strpos($html, 'aria-label="Main"'));
});

it('marks the current page for assistive technology', function () {
    shellAs(Role::Owner)->assertSee('aria-current="page"', escape: false);
});

it('signs a shop user out with a POST, never a link', function () {
    // A sign-out reachable by GET can be triggered by any image or prefetch
    // on the page, logging a cashier out mid-sale.
    $route = Route::getRoutes()->getByName('tenant.logout');

    expect($route->methods())->toContain('POST')
        ->and($route->methods())->not->toContain('GET');

    withTenant($this->shop, function () {
        actingAsTenantUser(User::where('email', 'owner@karim.test')->firstOrFail());

        test()->post('http://karim.lorapok.localhost/logout')
            ->assertRedirect(route('tenant.login'));

        expect(auth('tenant')->check())->toBeFalse();
    });
});

it('shows no navigation to someone who is not signed in', function () {
    // The sign-in page must not leak the shape of the application.
    $this->get('http://karim.lorapok.localhost/login')
        ->assertOk()
        ->assertDontSee('aria-label="Main"', escape: false)
        ->assertSee('id="main-content"', escape: false);
});

it('lets an operator sign out of the central panel', function () {
    // The operator panel shipped with no sign-out at all: the only way out
    // was to clear cookies.
    $operator = CentralUser::create([
        'name' => 'Operator',
        'email' => 'ops@lorapok.test',
        'password' => 'password12',
        'is_super_admin' => true,
        'is_active' => true,
    ]);

    $this->actingAs($operator, 'web')
        ->get('http://lorapok.localhost/admin/shops')
        ->assertOk()
        ->assertSee('Sign out');

    $this->actingAs($operator, 'web')
        ->post('http://lorapok.localhost/admin/logout')
        ->assertRedirect(route('central.login'));

    expect(auth('web')->check())->toBeFalse();
});
