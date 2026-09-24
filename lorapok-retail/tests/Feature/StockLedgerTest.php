<?php

declare(strict_types=1);

use App\Domain\Stock\InsufficientStock;
use App\Domain\Stock\InvalidMovement;
use App\Domain\Stock\MovementIntent;
use App\Domain\Stock\StockService;
use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Enums\StockItemStatus;
use App\Models\Tenant;
use App\Models\Tenant\Location;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\StockItem;
use App\Models\Tenant\StockLevel;
use App\Models\Tenant\StockMovement;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->createdTenants = collect();

    $this->tenant = Tenant::create(['name' => 'Karim Mobile', 'slug' => 'karim']);
    $this->tenant->domains()->create(['domain' => 'karim.lorapok.localhost']);
    $this->createdTenants->push($this->tenant);

    tenancy()->initialize($this->tenant);

    $this->service = app(StockService::class);
    $this->location = Location::default();

    $product = Product::create([
        'name' => 'USB-C Cable', 'sku' => 'CBL-1', 'slug' => 'cbl-1',
        'type' => ProductType::Standard, 'track_stock' => true,
        'reorder_level' => 0, 'is_active' => true,
    ]);

    $this->variant = ProductVariant::create([
        'product_id' => $product->id, 'sku' => 'CBL-1',
        'cost_minor' => 0, 'price_minor' => 35000, 'is_active' => true,
    ]);
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

function receive(float $qty, int $unitCostMinor): StockMovement
{
    return test()->service->apply(MovementIntent::inbound(
        test()->location, test()->variant, $qty, MovementType::PurchaseReceipt, $unitCostMinor,
    ));
}

function sell(float $qty): StockMovement
{
    return test()->service->apply(MovementIntent::outbound(
        test()->location, test()->variant, $qty, MovementType::Sale,
    ));
}

function level(): StockLevel
{
    return StockLevel::where('product_variant_id', test()->variant->id)->firstOrFail();
}

it('records an inbound movement and raises the level', function () {
    receive(10, 18000);

    expect((float) level()->quantity)->toBe(10.0)
        ->and(StockMovement::count())->toBe(1)
        ->and((float) StockMovement::first()->quantity)->toBe(10.0);
});

it('stores outbound movements as negative quantities', function () {
    receive(10, 18000);
    $sale = sell(3);

    // Signed quantities mean the balance is simply the sum of the ledger.
    expect((float) $sale->quantity)->toBe(-3.0)
        ->and((float) level()->quantity)->toBe(7.0);
});

it('keeps the cached level equal to the sum of the ledger', function () {
    receive(10, 18000);
    receive(5, 22000);
    sell(4);
    sell(1);

    $ledger = $this->service->quantityFromLedger($this->location->id, $this->variant->id);

    expect($ledger)->toBe(10.0)
        ->and($this->service->onHand($this->location->id, $this->variant->id))->toBe($ledger);
});

it('keeps level and ledger in step across many random movements', function () {
    // Property test: whatever sequence of valid movements occurs, the cache
    // must still equal the ledger. This is the invariant the whole design
    // rests on.
    $expected = 0.0;

    foreach (range(1, 60) as $ignored) {
        if ($expected >= 5 && random_int(0, 1) === 1) {
            $qty = random_int(1, (int) min($expected, 5));
            sell($qty);
            $expected -= $qty;
        } else {
            $qty = random_int(1, 10);
            receive($qty, random_int(100, 50000));
            $expected += $qty;
        }
    }

    expect($this->service->quantityFromLedger($this->location->id, $this->variant->id))->toBe($expected)
        ->and((float) level()->quantity)->toBe($expected);
});

it('computes a weighted average cost', function () {
    receive(10, 18000);   // 10 @ 180.00
    receive(10, 22000);   // 10 @ 220.00

    // (10*180 + 10*220) / 20 = 200.00
    expect(level()->avg_cost_minor)->toBe(20000);
});

it('freezes the cost of goods sold onto the outbound movement', function () {
    receive(10, 18000);
    receive(10, 22000);
    $sale = sell(5);

    // Receiving cheaper stock afterwards must not rewrite what this sale cost.
    receive(100, 1000);

    expect($sale->fresh()->unit_cost_minor)->toBe(20000)
        ->and(level()->avg_cost_minor)->not->toBe(20000);
});

it('does not let an outbound movement change the average cost', function () {
    receive(10, 18000);
    $before = level()->avg_cost_minor;

    sell(5);

    // Selling consumes stock; it must not alter what the remainder cost.
    expect(level()->avg_cost_minor)->toBe($before);
});

it('refuses to overdraw stock', function () {
    receive(5, 18000);

    expect(fn () => sell(6))->toThrow(InsufficientStock::class);
});

it('writes nothing when a movement is refused', function () {
    receive(5, 18000);
    $movements = StockMovement::count();
    $quantity = (float) level()->quantity;

    try {
        sell(999);
    } catch (InsufficientStock) {
        // expected
    }

    // The old system inserted the sale line first and checked stock after,
    // leaving a half-applied sale behind.
    expect(StockMovement::count())->toBe($movements)
        ->and((float) level()->quantity)->toBe($quantity);
});

it('rolls back every line when one line of a batch is short', function () {
    receive(5, 18000);

    $intents = [
        MovementIntent::outbound($this->location, $this->variant, 2, MovementType::Sale),
        MovementIntent::outbound($this->location, $this->variant, 99, MovementType::Sale),
    ];

    expect(fn () => $this->service->applyMany($intents))->toThrow(InsufficientStock::class);

    // The first line must not survive the failure of the second.
    expect((float) level()->quantity)->toBe(5.0)
        ->and(StockMovement::where('type', MovementType::Sale)->count())->toBe(0);
});

it('restores stock when a sale is returned', function () {
    receive(10, 18000);
    sell(4);

    $this->service->apply(MovementIntent::inbound(
        $this->location, $this->variant, 4, MovementType::SaleReturn, 18000,
    ));

    // A return adds a new positive movement; it never edits the sale.
    expect((float) level()->quantity)->toBe(10.0)
        ->and(StockMovement::count())->toBe(3);
});

it('refuses to edit or delete a movement', function () {
    receive(10, 18000);
    $movement = StockMovement::firstOrFail();

    expect(fn () => $movement->update(['quantity' => 999]))->toThrow(LogicException::class)
        ->and(fn () => $movement->delete())->toThrow(LogicException::class)
        ->and((float) StockMovement::firstOrFail()->quantity)->toBe(10.0);
});

it('rejects nonsensical movement intents', function () {
    expect(fn () => MovementIntent::inbound($this->location, $this->variant, 0, MovementType::PurchaseReceipt, 100))
        ->toThrow(InvalidMovement::class)
        ->and(fn () => MovementIntent::inbound($this->location, $this->variant, -5, MovementType::PurchaseReceipt, 100))
        ->toThrow(InvalidMovement::class)
        // A sale is not an inbound type.
        ->and(fn () => MovementIntent::inbound($this->location, $this->variant, 5, MovementType::Sale, 100))
        ->toThrow(InvalidMovement::class)
        // A purchase receipt is not an outbound type.
        ->and(fn () => MovementIntent::outbound($this->location, $this->variant, 5, MovementType::PurchaseReceipt))
        ->toThrow(InvalidMovement::class);
});

it('enforces the IMEI state machine', function () {
    $item = StockItem::create([
        'product_variant_id' => $this->variant->id,
        'location_id' => $this->location->id,
        'imei' => '356938035643809',
        'status' => StockItemStatus::InStock,
        'purchase_cost_minor' => 95000_00,
    ]);

    $item->transitionTo(StockItemStatus::Sold);
    expect($item->fresh()->status)->toBe(StockItemStatus::Sold);

    // A sold handset cannot be sold again, and cannot jump straight back to
    // stock without being returned first.
    expect(fn () => $item->transitionTo(StockItemStatus::InStock))
        ->toThrow(DomainException::class);

    $item->transitionTo(StockItemStatus::Returned);
    $item->transitionTo(StockItemStatus::InStock);

    expect($item->fresh()->status)->toBe(StockItemStatus::InStock)
        // Every transition is recorded, so a handset's life is reconstructable.
        ->and($item->events()->count())->toBe(3);
});

it('will not resurrect a written-off unit', function () {
    $item = StockItem::create([
        'product_variant_id' => $this->variant->id,
        'location_id' => $this->location->id,
        'serial' => Str::random(12),
        'status' => StockItemStatus::InStock,
        'purchase_cost_minor' => 1000,
    ]);

    $item->transitionTo(StockItemStatus::WrittenOff);

    expect(fn () => $item->transitionTo(StockItemStatus::InStock))
        ->toThrow(DomainException::class);
});

it('keeps stock separate between shops', function () {
    receive(10, 18000);

    tenancy()->end();

    $other = Tenant::create(['name' => 'Rahim Electronics', 'slug' => 'rahim']);
    $other->domains()->create(['domain' => 'rahim.lorapok.localhost']);
    $this->createdTenants->push($other);

    tenancy()->initialize($other);
    expect(StockMovement::count())->toBe(0)
        ->and(StockLevel::count())->toBe(0);

    tenancy()->end();
    tenancy()->initialize($this->tenant);
    expect(StockMovement::count())->toBe(1);
});
