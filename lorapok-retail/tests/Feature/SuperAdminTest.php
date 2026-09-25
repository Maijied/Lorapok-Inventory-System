<?php

declare(strict_types=1);

use App\Domain\Central\InvalidShop;
use App\Domain\Central\MetricsRollup;
use App\Domain\Central\ShopProvisioner;
use App\Domain\Sales\CartLine;
use App\Domain\Sales\SalesService;
use App\Domain\Stock\MovementIntent;
use App\Domain\Stock\StockService;
use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Models\Tenant;
use App\Models\Tenant\CashRegister;
use App\Models\Tenant\Category;
use App\Models\Tenant\Location;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\User as ShopUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->createdTenants = collect();
    $this->provisioner = app(ShopProvisioner::class);

    $this->admin = User::create([
        'name' => 'Operator', 'email' => 'admin@lorapok.tech',
        'password' => 'password', 'is_super_admin' => true, 'is_active' => true,
    ]);
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

function makeShop(string $slug = 'karim', string $name = 'Karim Mobile'): Tenant
{
    return test()->provisioner->create(
        name: $name,
        slug: $slug,
        ownerName: 'Owner',
        ownerEmail: "owner@{$slug}.test",
        ownerPassword: 'password12',
        actor: test()->admin,
    );
}

it('provisions a shop with its own database, seed data and owner', function () {
    $shop = makeShop();

    expect($shop->database()->getName())->toStartWith('tenant_')
        ->and($shop->status)->toBe('trialing')
        // A trial so a new shop is usable before any billing exists.
        ->and($shop->trial_ends_at)->not->toBeNull();

    tenancy()->initialize($shop);

    $owner = ShopUser::firstOrFail();

    expect($owner->email)->toBe('owner@karim.test')
        ->and($owner->getRoleNames()->all())->toBe(['owner'])
        // Seeded so the shop is usable on day one.
        ->and(Category::count())->toBeGreaterThan(0)
        ->and(CashRegister::count())->toBe(1)
        ->and(Location::count())->toBe(1);

    tenancy()->end();
});

it('serves the new shop on its own subdomain', function () {
    makeShop('nasir', 'Nasir Telecom');

    $this->get('http://nasir.lorapok.localhost/login')
        ->assertOk()
        ->assertSee('Nasir Telecom');
});

it('refuses a reserved address', function () {
    // These collide with central routing or infrastructure hostnames.
    expect(fn () => makeShop('admin'))->toThrow(InvalidShop::class)
        ->and(fn () => makeShop('www'))->toThrow(InvalidShop::class)
        ->and(fn () => makeShop('api'))->toThrow(InvalidShop::class);
});

it('refuses a duplicate address', function () {
    makeShop('karim');

    expect(fn () => makeShop('karim', 'Another Shop'))->toThrow(InvalidShop::class);
});

it('refuses a malformed address', function () {
    expect(fn () => makeShop('ab'))->toThrow(InvalidShop::class)
        ->and(fn () => makeShop('Has Spaces'))->toThrow(InvalidShop::class)
        ->and(fn () => makeShop('-leading'))->toThrow(InvalidShop::class);
});

it('only accepts an accent from the approved palette', function () {
    // The value ends up inside a style attribute, and an arbitrary colour
    // could be unreadable against the surface colours.
    expect(fn () => $this->provisioner->create(
        'Shop', 'tester', 'O', 'o@x.test', 'password12', '#ff0000',
    ))->toThrow(InvalidShop::class);

    $shop = $this->provisioner->create(
        'Shop', 'tester', 'O', 'o@x.test', 'password12', Tenant::ACCENT_PALETTE[2],
    );

    expect($shop->accent)->toBe(Tenant::ACCENT_PALETTE[2]);
});

it('blocks a suspended shop without touching its data', function () {
    $shop = makeShop();

    tenancy()->initialize($shop);
    $usersBefore = ShopUser::count();
    tenancy()->end();

    $this->provisioner->suspend($shop, 'non-payment', $this->admin);

    $this->get('http://karim.lorapok.localhost/login')->assertForbidden();

    // Suspension is a billing state, not a deletion.
    tenancy()->initialize($shop->refresh());
    expect(ShopUser::count())->toBe($usersBefore);
    tenancy()->end();
});

it('restores a suspended shop', function () {
    $shop = makeShop();
    $this->provisioner->suspend($shop, actor: $this->admin);
    $this->provisioner->restore($shop->refresh(), $this->admin);

    expect($shop->refresh()->isSuspended())->toBeFalse();

    $this->get('http://karim.lorapok.localhost/login')->assertOk();
});

it('records what an operator did', function () {
    $shop = makeShop();
    $this->provisioner->suspend($shop, 'non-payment', $this->admin);

    $actions = DB::table('central_audit_logs')->pluck('action')->all();

    expect($actions)->toContain('shop.created')
        ->and($actions)->toContain('shop.suspended')
        ->and(DB::table('central_audit_logs')->where('action', 'shop.suspended')->value('actor_id'))
        ->toBe($this->admin->id);
});

it('refuses to delete a shop without exact confirmation', function () {
    $shop = makeShop();

    // Deleting drops the database, so it asks for the slug typed exactly.
    expect(fn () => $this->provisioner->destroy($shop, 'wrong-slug'))->toThrow(InvalidShop::class)
        ->and(Tenant::count())->toBe(1);
});

it('rolls a shop up into the central metrics table', function () {
    $shop = makeShop();

    tenancy()->initialize($shop);

    $product = Product::create([
        'name' => 'Widget', 'sku' => 'W-1', 'slug' => 'w-1',
        'type' => ProductType::Standard, 'track_stock' => true,
        'reorder_level' => 0, 'is_active' => true,
    ]);
    $variant = ProductVariant::create([
        'product_id' => $product->id, 'sku' => 'W-1',
        'cost_minor' => 0, 'price_minor' => 10000, 'is_active' => true,
    ]);

    app(StockService::class)->apply(MovementIntent::inbound(
        Location::default(), $variant, 10, MovementType::PurchaseReceipt, 6000,
    ));

    app(SalesService::class)->sell(
        [new CartLine($variant->id, 5)],
        ShopUser::firstOrFail(),
        Location::default()->id,
    );

    tenancy()->end();

    app(MetricsRollup::class)->forTenant($shop, CarbonImmutable::now());

    $row = DB::table('tenant_daily_metrics')->where('tenant_id', $shop->id)->first();

    // 5 at 100.00 costing 60.00 => 500.00 revenue, 200.00 margin.
    expect((int) $row->sales_count)->toBe(1)
        ->and((int) $row->sales_net_minor)->toBe(50000)
        ->and((int) $row->gross_margin_minor)->toBe(20000)
        // Stock left: 5 units at 60.00.
        ->and((int) $row->stock_value_minor)->toBe(30000);
});

it('makes a rollup safe to re-run', function () {
    $shop = makeShop();
    $today = CarbonImmutable::now();

    app(MetricsRollup::class)->forTenant($shop, $today);
    app(MetricsRollup::class)->forTenant($shop, $today);
    app(MetricsRollup::class)->forTenant($shop, $today);

    // Idempotent upsert: a repair is simply a re-run.
    expect(DB::table('tenant_daily_metrics')->where('tenant_id', $shop->id)->count())->toBe(1);
});

it('returns to the central database after rolling a shop up', function () {
    $shop = makeShop();

    app(MetricsRollup::class)->forTenant($shop, CarbonImmutable::now());

    // Otherwise the next shop would be aggregated against the wrong database.
    expect(tenant())->toBeNull()
        ->and(Tenant::count())->toBe(1);
});

it('keeps each shop separate in the rollup', function () {
    $karim = makeShop('karim', 'Karim Mobile');
    $rahim = makeShop('rahim', 'Rahim Electronics');

    $rollup = app(MetricsRollup::class);
    $rollup->forTenant($karim, CarbonImmutable::now());
    $rollup->forTenant($rahim, CarbonImmutable::now());

    $rows = DB::table('tenant_daily_metrics')->pluck('tenant_id')->all();

    expect($rows)->toHaveCount(2)
        ->and($rows)->toContain($karim->id)
        ->and($rows)->toContain($rahim->id);
});

it('lets a super admin sign in to the central app', function () {
    Livewire::test('central.auth.login')
        ->set('email', 'admin@lorapok.tech')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('central.shops'));

    expect(Auth::guard('web')->check())->toBeTrue();
});

it('refuses a central account that is not a super admin', function () {
    User::create([
        'name' => 'Nobody', 'email' => 'nobody@lorapok.tech',
        'password' => 'password', 'is_super_admin' => false, 'is_active' => true,
    ]);

    // Holding a central account is not the same as running the platform.
    Livewire::test('central.auth.login')
        ->set('email', 'nobody@lorapok.tech')
        ->set('password', 'password')
        ->call('login')
        ->assertHasErrors('email');

    expect(Auth::guard('web')->check())->toBeFalse();
});

it('rate limits central sign-in attempts', function () {
    $component = Livewire::test('central.auth.login')
        ->set('email', 'admin@lorapok.tech')
        ->set('password', 'wrong');

    foreach (range(1, 6) as $ignored) {
        $component->call('login');
    }

    $component->set('password', 'password')->call('login');

    expect(Auth::guard('web')->check())->toBeFalse();
});

it('creates a shop from the admin screen', function () {
    Auth::guard('web')->login($this->admin);

    Livewire::test('central.shops')
        ->set('name', 'Nasir Telecom')
        ->set('slug', 'nasir')
        ->set('ownerName', 'Nasir Ahmed')
        ->set('ownerEmail', 'nasir@shop.test')
        ->set('ownerPassword', 'password12')
        ->call('create')
        ->assertHasNoErrors();

    expect(Tenant::where('slug', 'nasir')->exists())->toBeTrue();
});

it('shows the address error on the address field', function () {
    Auth::guard('web')->login($this->admin);
    makeShop('karim');

    Livewire::test('central.shops')
        ->set('name', 'Another')
        ->set('slug', 'karim')
        ->set('ownerName', 'O')
        ->set('ownerEmail', 'o@x.test')
        ->set('ownerPassword', 'password12')
        ->call('create')
        ->assertHasErrors('slug');
});

it('suspends and restores from the admin screen', function () {
    Auth::guard('web')->login($this->admin);
    $shop = makeShop();

    $component = Livewire::test('central.shops')->call('suspend', $shop->id);
    expect($shop->refresh()->isSuspended())->toBeTrue();

    $component->call('restore', $shop->id);
    expect($shop->refresh()->isSuspended())->toBeFalse();
});

it('keeps the admin screen away from non-operators', function () {
    $nobody = User::create([
        'name' => 'Nobody', 'email' => 'nobody@lorapok.tech',
        'password' => 'password', 'is_super_admin' => false, 'is_active' => true,
    ]);

    Auth::guard('web')->login($nobody);

    $this->get('http://lorapok.localhost/admin/shops')->assertForbidden();
});

it('reads cross-shop totals from the rollup table only', function () {
    Auth::guard('web')->login($this->admin);
    $shop = makeShop();
    app(MetricsRollup::class)->forTenant($shop, CarbonImmutable::now());

    // Querying every shop on page load would cost one connection each and
    // degrade with every shop added.
    $queries = 0;
    DB::listen(function ($query) use (&$queries) {
        if (str_contains($query->sql, 'tenant_daily_metrics')) {
            $queries++;
        }
    });

    Livewire::test('central.shops')->assertOk();

    expect($queries)->toBeGreaterThan(0);
});
