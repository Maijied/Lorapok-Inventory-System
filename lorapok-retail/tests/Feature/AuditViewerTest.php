<?php

declare(strict_types=1);

use App\Domain\Central\ShopProvisioner;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The audit trail, finally readable.
 *
 * `central_audit_logs` has been written to since Phase 1 and read by nobody.
 * A record only counts as an audit if someone can actually go and look.
 */
beforeEach(function () {
    $this->operator = User::create([
        'name' => 'Lorapok Operator', 'email' => 'ops@lorapok.test',
        'password' => 'password12', 'is_super_admin' => true, 'is_active' => true,
    ]);

    $this->shop = app(ShopProvisioner::class)->create(
        name: 'Karim Mobile', slug: 'karim',
        ownerName: 'Karim Uddin', ownerEmail: 'owner@karim.test', ownerPassword: 'password12',
        actor: $this->operator,
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

it('is closed to anyone not signed in', function () {
    $this->get('http://lorapok.localhost/admin/audit')
        ->assertRedirect(route('central.login'));
});

it('shows what an operator did, and who they are', function () {
    $this->actingAs($this->operator, 'web')
        ->get('http://lorapok.localhost/admin/audit')
        ->assertOk()
        ->assertSee('shop.created')
        ->assertSee('Karim Mobile')
        ->assertSee('Lorapok Operator');
});

it('surfaces the reason rather than burying it in the payload', function () {
    // On a suspension or an impersonation, the reason IS the record. Leaving
    // it inside a JSON blob nobody expands defeats the point of requiring it.
    app(ShopProvisioner::class)->suspend($this->shop, 'unpaid for 60 days', $this->operator);

    $this->actingAs($this->operator, 'web')
        ->get('http://lorapok.localhost/admin/audit')
        ->assertOk()
        ->assertSee('unpaid for 60 days');
});

it('filters by shop', function () {
    $nasir = app(ShopProvisioner::class)->create(
        name: 'Nasir Telecom', slug: 'nasir',
        ownerName: 'Nasir', ownerEmail: 'owner@nasir.test', ownerPassword: 'password12',
        actor: $this->operator,
    );

    // Asserted on the reason rather than the shop name: the filter dropdown
    // lists every shop, so a name is on the page whether it is filtered in or
    // not. The reason only appears on that shop's own entry.
    app(ShopProvisioner::class)->suspend($nasir, 'nasir specific reason', $this->operator);
    app(ShopProvisioner::class)->suspend($this->shop, 'karim specific reason', $this->operator);

    $this->actingAs($this->operator, 'web')
        ->get('http://lorapok.localhost/admin/audit?shop=karim')
        ->assertOk()
        ->assertSee('karim specific reason')
        ->assertDontSee('nasir specific reason');
});

it('filters by what kind of action it was', function () {
    app(ShopProvisioner::class)->suspend($this->shop, 'unpaid for 60 days', $this->operator);

    $this->actingAs($this->operator, 'web')
        ->get('http://lorapok.localhost/admin/audit?action=shop')
        ->assertOk()
        ->assertSee('shop.suspended');
});

it('keeps the history of a shop that no longer exists', function () {
    // Entries outlive the shops they describe. If deleting a shop took its
    // audit trail with it, the one action most worth being able to review
    // would be the one that erased the evidence.
    $slug = $this->shop->slug;
    $tenantId = $this->shop->id;

    // destroy() demands the slug back as confirmation — deleting a shop drops
    // its entire database, so it is deliberately hard to do by accident.
    app(ShopProvisioner::class)->destroy($this->shop, $slug, $this->operator);

    expect(DB::table('central_audit_logs')->where('tenant_id', $tenantId)->count())
        ->toBeGreaterThan(0);

    $this->actingAs($this->operator, 'web')
        ->get('http://lorapok.localhost/admin/audit')
        ->assertOk()
        // The shop's name is gone, so the entry says so rather than
        // rendering a blank where a name used to be.
        ->assertSee('deleted shop');

    expect($slug)->toBe('karim');
});
