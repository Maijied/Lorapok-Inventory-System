<?php

declare(strict_types=1);

use App\Domain\Sales\InvalidSale;
use App\Domain\Sales\RegisterService;
use App\Domain\Stock\MovementIntent;
use App\Domain\Stock\StockService;
use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Enums\StockItemStatus;
use App\Models\Tenant;
use App\Models\Tenant\CashRegister;
use App\Models\Tenant\Location;
use App\Models\Tenant\PaymentMethod;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\RegisterSession;
use App\Models\Tenant\Sale;
use App\Models\Tenant\Setting;
use App\Models\Tenant\StockItem;
use App\Models\Tenant\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->createdTenants = collect();

    $this->tenant = Tenant::create(['name' => 'Karim Mobile', 'slug' => 'karim']);
    $this->tenant->domains()->create(['domain' => 'karim.lorapok.localhost']);
    $this->createdTenants->push($this->tenant);

    tenancy()->initialize($this->tenant);
    Setting::put('invoice.round_to_whole', false, 'invoice');

    $this->stock = app(StockService::class);
    $this->registers = app(RegisterService::class);
    $this->location = Location::default();
    $this->register = CashRegister::where('is_active', true)->firstOrFail();

    $this->owner = User::create([
        'name' => 'Owner', 'email' => 'owner@karim.test',
        'password' => 'password', 'is_active' => true,
    ])->syncRoles(['owner']);

    $this->cable = posVariant('USB-C Cable', 'CBL-1', 35000);
    $this->cable->barcodes()->create(['barcode' => '8991234567890', 'type' => 'ean13']);
    posStock($this->cable, 20, 20000);
});

afterEach(function () {
    tenancy()->end();

    $this->createdTenants->each(function (Tenant $tenant) {
        try {
            $tenant->database()->manager()->deleteDatabase($tenant);
        } catch (Throwable) {
            // Already gone.
        }
        Tenant::withoutEvents(fn () => $tenant->delete());
    });
});

function posVariant(string $name, string $sku, int $price, ProductType $type = ProductType::Standard): ProductVariant
{
    $product = Product::create([
        'name' => $name, 'sku' => $sku, 'slug' => strtolower($sku),
        'type' => $type, 'track_stock' => $type->tracksStock(),
        'reorder_level' => 0, 'is_active' => true,
    ]);

    return ProductVariant::create([
        'product_id' => $product->id, 'sku' => $sku,
        'cost_minor' => 0, 'price_minor' => $price, 'is_active' => true,
    ]);
}

function posStock(ProductVariant $variant, float $qty, int $cost): void
{
    test()->stock->apply(MovementIntent::inbound(
        test()->location, $variant, $qty, MovementType::PurchaseReceipt, $cost,
    ));
}

it('opens a shift with a counted float', function () {
    actingAsTenantUser($this->owner);

    Livewire::test('tenant.pos')
        ->set('openingFloat', '500')
        ->call('openShift')
        ->assertHasNoErrors();

    $session = RegisterSession::firstOrFail();

    expect($session->opening_float_minor)->toBe(50000)
        ->and($session->isOpen())->toBeTrue()
        ->and($session->expectedCashMinor())->toBe(50000);
});

it('refuses a second shift on the same till', function () {
    // Two cashiers sharing one drawer makes any variance unattributable.
    actingAsTenantUser($this->owner);
    $this->registers->open($this->register, $this->owner, 0);

    Livewire::test('tenant.pos')
        ->set('openingFloat', '0')
        ->call('openShift')
        ->assertHasErrors('openingFloat');

    expect(RegisterSession::count())->toBe(1);
});

it('adds a product by barcode', function () {
    actingAsTenantUser($this->owner);

    Livewire::test('tenant.pos')
        ->set('scan', '8991234567890')
        ->call('addScanned')
        ->assertHasNoErrors()
        ->assertSee('USB-C Cable')
        // The box clears so the next scan goes straight in.
        ->assertSet('scan', '');
});

it('adds a product by SKU', function () {
    actingAsTenantUser($this->owner);

    Livewire::test('tenant.pos')
        ->set('scan', 'CBL-1')
        ->call('addScanned')
        ->assertHasNoErrors()
        ->assertSee('USB-C Cable');
});

it('reports an unknown code instead of failing silently', function () {
    actingAsTenantUser($this->owner);

    Livewire::test('tenant.pos')
        ->set('scan', 'NOT-A-REAL-CODE')
        ->call('addScanned')
        ->assertHasErrors('scan');
});

it('merges a repeated scan into one line', function () {
    actingAsTenantUser($this->owner);

    $component = Livewire::test('tenant.pos');

    foreach (range(1, 3) as $ignored) {
        $component->set('scan', 'CBL-1')->call('addScanned');
    }

    $cart = $component->get('cart');

    expect($cart)->toHaveCount(1)
        ->and($cart[0]['qty'])->toBe(3.0);
});

it('selects the exact handset when an IMEI is scanned', function () {
    actingAsTenantUser($this->owner);

    $phone = posVariant('iPhone 15', 'IP15', 11200000, ProductType::Serialized);
    $unit = StockItem::create([
        'product_variant_id' => $phone->id, 'location_id' => $this->location->id,
        'imei' => '356938035643809', 'status' => StockItemStatus::InStock,
        'purchase_cost_minor' => 9500000,
    ]);
    posStock($phone, 1, 9500000);

    $cart = Livewire::test('tenant.pos')
        ->set('scan', '356938035643809')
        ->call('addScanned')
        ->get('cart');

    // Scanning an IMEI both picks the product and chooses the unit.
    expect($cart[0]['stock_item_ids'])->toBe([$unit->id])
        ->and($cart[0]['qty'])->toBe(1);
});

it('will not scan the same handset onto the cart twice', function () {
    actingAsTenantUser($this->owner);

    $phone = posVariant('iPhone 15', 'IP15', 11200000, ProductType::Serialized);
    StockItem::create([
        'product_variant_id' => $phone->id, 'location_id' => $this->location->id,
        'imei' => '356938035643809', 'status' => StockItemStatus::InStock,
        'purchase_cost_minor' => 9500000,
    ]);
    posStock($phone, 1, 9500000);

    $component = Livewire::test('tenant.pos');
    $component->set('scan', '356938035643809')->call('addScanned');
    $component->set('scan', '356938035643809')->call('addScanned');

    expect($component->get('cart')[0]['qty'])->toBe(1);
});

it('completes a sale and takes stock off the shelf', function () {
    actingAsTenantUser($this->owner);
    $this->registers->open($this->register, $this->owner, 0);

    Livewire::test('tenant.pos')
        ->set('scan', 'CBL-1')->call('addScanned')
        ->set('scan', 'CBL-1')->call('addScanned')
        ->call('checkout')
        ->assertHasNoErrors();

    $sale = Sale::firstOrFail();

    expect($sale->subtotal_minor)->toBe(70000)
        ->and($this->stock->onHand($this->location->id, $this->cable->id))->toBe(18.0);
});

it('empties the cart after a completed sale', function () {
    actingAsTenantUser($this->owner);

    $component = Livewire::test('tenant.pos')
        ->set('scan', 'CBL-1')->call('addScanned')
        ->call('checkout');

    expect($component->get('cart'))->toBe([]);
});

it('records cash taken into the drawer', function () {
    actingAsTenantUser($this->owner);
    $session = $this->registers->open($this->register, $this->owner, 10000);
    $cash = PaymentMethod::where('name', 'Cash')->firstOrFail();

    Livewire::test('tenant.pos')
        ->set('scan', 'CBL-1')->call('addScanned')
        ->set('paymentMethodId', $cash->id)
        ->set('tender', '350')
        ->call('checkout')
        ->assertHasNoErrors();

    // Float 100.00 plus 350.00 taken.
    expect($session->refresh()->expectedCashMinor())->toBe(45000);
});

it('does not put a card payment in the cash drawer', function () {
    actingAsTenantUser($this->owner);
    $session = $this->registers->open($this->register, $this->owner, 10000);
    $card = PaymentMethod::where('name', 'Card')->firstOrFail();

    Livewire::test('tenant.pos')
        ->set('scan', 'CBL-1')->call('addScanned')
        ->set('paymentMethodId', $card->id)
        ->set('tender', '350')
        ->call('checkout');

    // Counting card takings at close would manufacture a phantom shortfall.
    expect($session->refresh()->expectedCashMinor())->toBe(10000);
});

it('attributes a sale to the open shift', function () {
    actingAsTenantUser($this->owner);
    $session = $this->registers->open($this->register, $this->owner, 0);

    Livewire::test('tenant.pos')
        ->set('scan', 'CBL-1')->call('addScanned')
        ->call('checkout');

    expect(Sale::firstOrFail()->register_session_id)->toBe($session->id);
});

it('leaves a sale unpaid when no amount is tendered', function () {
    actingAsTenantUser($this->owner);

    Livewire::test('tenant.pos')
        ->set('scan', 'CBL-1')->call('addScanned')
        ->call('checkout');

    $sale = Sale::firstOrFail();

    expect($sale->paid_minor)->toBe(0)
        ->and($sale->due_minor)->toBe(35000);
});

it('shows a usable message when stock runs out', function () {
    actingAsTenantUser($this->owner);

    $component = Livewire::test('tenant.pos')->set('scan', 'CBL-1')->call('addScanned');
    $component->call('updateQty', 0, 999);

    // The old system threw an uncaught exception here and showed a 500 page.
    $component->call('checkout')->assertHasErrors('cart');

    expect(Sale::count())->toBe(0)
        ->and($this->stock->onHand($this->location->id, $this->cable->id))->toBe(20.0);
});

it('refuses to check out an empty cart', function () {
    actingAsTenantUser($this->owner);

    Livewire::test('tenant.pos')->call('checkout')->assertHasErrors('cart');
});

it('removes and clears lines', function () {
    actingAsTenantUser($this->owner);

    $component = Livewire::test('tenant.pos')
        ->set('scan', 'CBL-1')->call('addScanned')
        ->call('removeLine', 0);

    expect($component->get('cart'))->toBe([]);
});

it('treats a zero quantity as removing the line', function () {
    actingAsTenantUser($this->owner);

    $component = Livewire::test('tenant.pos')
        ->set('scan', 'CBL-1')->call('addScanned')
        ->call('updateQty', 0, 0);

    expect($component->get('cart'))->toBe([]);
});

it('closes a shift and records the variance', function () {
    actingAsTenantUser($this->owner);
    $session = $this->registers->open($this->register, $this->owner, 10000);
    $cash = PaymentMethod::where('name', 'Cash')->firstOrFail();

    Livewire::test('tenant.pos')
        ->set('scan', 'CBL-1')->call('addScanned')
        ->set('paymentMethodId', $cash->id)
        ->set('tender', '350')
        ->call('checkout');

    // Expected 450.00 but only 440.00 was counted: a 10.00 shortfall.
    $closed = $this->registers->close($session->refresh(), $this->owner, 44000);

    expect($closed->expected_cash_minor)->toBe(45000)
        ->and($closed->counted_cash_minor)->toBe(44000)
        ->and($closed->variance_minor)->toBe(-1000)
        ->and($closed->isOpen())->toBeFalse();
});

it('will not close a shift twice', function () {
    $session = $this->registers->open($this->register, $this->owner, 0);
    $this->registers->close($session, $this->owner, 0);

    expect(fn () => $this->registers->close($session->refresh(), $this->owner, 0))
        ->toThrow(InvalidSale::class);
});

it('will not pay out more than the drawer holds', function () {
    $session = $this->registers->open($this->register, $this->owner, 5000);

    expect(fn () => $this->registers->payOut($session, 10000, 'supplier'))
        ->toThrow(InvalidSale::class);

    $this->registers->payOut($session, 3000, 'supplier');
    expect($session->refresh()->expectedCashMinor())->toBe(2000);
});

it('keeps a cashier away from voiding sales', function () {
    $cashier = User::create([
        'name' => 'Cashier', 'email' => 'cashier@karim.test',
        'password' => 'password', 'is_active' => true,
    ])->syncRoles(['cashier']);

    actingAsTenantUser($cashier);

    // Ring up a real sale so the policy is exercised against a real record
    // rather than a factory stub.
    Livewire::test('tenant.pos')
        ->set('scan', 'CBL-1')->call('addScanned')
        ->call('checkout')
        ->assertHasNoErrors();

    $sale = Sale::firstOrFail();

    // A cashier may ring up a sale but not reverse one: voiding is a lever
    // for hiding a till shortfall.
    expect($cashier->can('create', Sale::class))->toBeTrue()
        ->and($cashier->can('void', $sale))->toBeFalse()
        ->and($this->owner->can('void', $sale))->toBeTrue();
});
