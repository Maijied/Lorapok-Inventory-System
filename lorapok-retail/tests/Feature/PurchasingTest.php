<?php

declare(strict_types=1);

use App\Domain\Purchasing\InvalidReceipt;
use App\Domain\Purchasing\PurchasingService;
use App\Domain\Purchasing\ReceiptLine;
use App\Domain\Stock\StockService;
use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Enums\PurchaseStatus;
use App\Models\Tenant;
use App\Models\Tenant\GoodsReceipt;
use App\Models\Tenant\Location;
use App\Models\Tenant\PaymentMethod;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\PurchaseItem;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\StockItem;
use App\Models\Tenant\StockLevel;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Vendor;

beforeEach(function () {
    $this->createdTenants = collect();

    $this->tenant = Tenant::create(['name' => 'Karim Mobile', 'slug' => 'karim']);
    $this->tenant->domains()->create(['domain' => 'karim.lorapok.localhost']);
    $this->createdTenants->push($this->tenant);

    tenancy()->initialize($this->tenant);

    $this->purchasing = app(PurchasingService::class);
    $this->stock = app(StockService::class);
    $this->location = Location::default();
    $this->vendor = Vendor::create(['name' => 'Dhaka Wholesale', 'is_active' => true]);

    $this->variant = makeVariant('Cable', 'CBL-1');
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

function makeVariant(string $name, string $sku, ProductType $type = ProductType::Standard): ProductVariant
{
    $product = Product::create([
        'name' => $name, 'sku' => $sku, 'slug' => strtolower($sku),
        'type' => $type, 'track_stock' => true, 'reorder_level' => 0, 'is_active' => true,
    ]);

    return ProductVariant::create([
        'product_id' => $product->id, 'sku' => $sku,
        'cost_minor' => 0, 'price_minor' => 1000, 'is_active' => true,
    ]);
}

function makeOrder(ProductVariant $variant, float $qty, int $unitCostMinor): array
{
    $order = PurchaseOrder::create([
        'number' => test()->purchasing->nextNumber('PO', PurchaseOrder::class),
        'vendor_id' => test()->vendor->id,
        'location_id' => test()->location->id,
        'currency' => 'BDT',
    ]);

    $item = PurchaseItem::create([
        'purchase_order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'qty_ordered' => $qty,
        'unit_cost_minor' => $unitCostMinor,
    ]);

    return [$order, $item];
}

it('does not touch stock when an order is placed', function () {
    [$order] = makeOrder($this->variant, 10, 19000);

    $this->purchasing->place($order);

    // An order is an intention. Only a delivery puts stock on the shelf.
    expect($order->refresh()->status)->toBe(PurchaseStatus::Ordered)
        ->and($this->stock->onHand($this->location->id, $this->variant->id))->toBe(0.0)
        ->and(StockMovement::count())->toBe(0);
});

it('computes order totals from its lines', function () {
    [$order] = makeOrder($this->variant, 10, 19000);

    $this->purchasing->place($order);

    // 10 x 190.00 = 1900.00
    expect($order->refresh()->total_minor)->toBe(190000)
        ->and($order->due_minor)->toBe(190000);
});

it('refuses to place an order with no lines', function () {
    $order = PurchaseOrder::create([
        'number' => 'PO-EMPTY',
        'vendor_id' => $this->vendor->id,
        'location_id' => $this->location->id,
    ]);

    expect(fn () => $this->purchasing->place($order))->toThrow(InvalidReceipt::class);
});

it('adds stock only when goods are received', function () {
    [$order, $item] = makeOrder($this->variant, 10, 19000);
    $this->purchasing->place($order);

    $this->purchasing->receive($order, [new ReceiptLine($item->id, 10, 19000)]);

    expect($this->stock->onHand($this->location->id, $this->variant->id))->toBe(10.0)
        ->and(StockMovement::where('type', MovementType::PurchaseReceipt)->count())->toBe(1)
        ->and($order->refresh()->status)->toBe(PurchaseStatus::Received);
});

it('supports partial delivery without special cases', function () {
    [$order, $item] = makeOrder($this->variant, 10, 19000);
    $this->purchasing->place($order);

    $this->purchasing->receive($order, [new ReceiptLine($item->id, 4, 19000)]);

    expect($order->refresh()->status)->toBe(PurchaseStatus::Partial)
        ->and((float) $item->refresh()->qty_received)->toBe(4.0)
        ->and($item->outstandingQty())->toBe(6.0)
        ->and($this->stock->onHand($this->location->id, $this->variant->id))->toBe(4.0);

    $this->purchasing->receive($order, [new ReceiptLine($item->id, 6, 19000)]);

    expect($order->refresh()->status)->toBe(PurchaseStatus::Received)
        ->and($this->stock->onHand($this->location->id, $this->variant->id))->toBe(10.0)
        ->and(GoodsReceipt::count())->toBe(2);
});

it('refuses to receive more than was ordered', function () {
    [$order, $item] = makeOrder($this->variant, 10, 19000);
    $this->purchasing->place($order);
    $this->purchasing->receive($order, [new ReceiptLine($item->id, 4, 19000)]);

    // Over-receiving is usually a miscount. Silently absorbing it would
    // corrupt both the stock figure and what the vendor is owed.
    expect(fn () => $this->purchasing->receive($order, [new ReceiptLine($item->id, 7, 19000)]))
        ->toThrow(InvalidReceipt::class);

    expect($this->stock->onHand($this->location->id, $this->variant->id))->toBe(4.0);
});

it('uses the real delivered cost, not the ordered cost', function () {
    [$order, $item] = makeOrder($this->variant, 10, 19000);
    $this->purchasing->place($order);

    // The vendor raised the price between order and delivery.
    $this->purchasing->receive($order, [new ReceiptLine($item->id, 10, 21000)]);

    $movement = StockMovement::where('type', MovementType::PurchaseReceipt)->firstOrFail();

    expect($movement->unit_cost_minor)->toBe(21000);
});

it('falls back to the ordered cost when a receipt gives none', function () {
    [$order, $item] = makeOrder($this->variant, 5, 19000);
    $this->purchasing->place($order);

    $this->purchasing->receive($order, [new ReceiptLine($item->id, 5)]);

    expect(StockMovement::firstOrFail()->unit_cost_minor)->toBe(19000);
});

it('blends deliveries at different prices into a weighted average', function () {
    [$order, $item] = makeOrder($this->variant, 20, 19000);
    $this->purchasing->place($order);

    $this->purchasing->receive($order, [new ReceiptLine($item->id, 10, 18000)]);
    $this->purchasing->receive($order, [new ReceiptLine($item->id, 10, 22000)]);

    $level = StockLevel::where('product_variant_id', $this->variant->id)->firstOrFail();

    // (10*180 + 10*220) / 20 = 200.00
    expect($level->avg_cost_minor)->toBe(20000);
});

it('will not receive against a draft or cancelled order', function () {
    [$draft, $draftItem] = makeOrder($this->variant, 5, 19000);

    expect(fn () => $this->purchasing->receive($draft, [new ReceiptLine($draftItem->id, 1)]))
        ->toThrow(InvalidReceipt::class);

    [$cancelled, $cancelledItem] = makeOrder($this->variant, 5, 19000);
    $this->purchasing->place($cancelled);
    $this->purchasing->cancel($cancelled);

    expect(fn () => $this->purchasing->receive($cancelled->refresh(), [new ReceiptLine($cancelledItem->id, 1)]))
        ->toThrow(InvalidReceipt::class);
});

it('will not cancel an order that has already been received', function () {
    [$order, $item] = makeOrder($this->variant, 10, 19000);
    $this->purchasing->place($order);
    $this->purchasing->receive($order, [new ReceiptLine($item->id, 4, 19000)]);

    // The stock exists and the vendor is owed for it; returning is the
    // correct route, not cancelling.
    expect(fn () => $this->purchasing->cancel($order->refresh()))->toThrow(InvalidReceipt::class);
});

it('rejects a line that belongs to a different order', function () {
    [$orderA] = makeOrder($this->variant, 5, 19000);
    [$orderB, $itemB] = makeOrder($this->variant, 5, 19000);

    $this->purchasing->place($orderA);
    $this->purchasing->place($orderB);

    expect(fn () => $this->purchasing->receive($orderA, [new ReceiptLine($itemB->id, 1)]))
        ->toThrow(InvalidReceipt::class);
});

it('creates one tracked unit per IMEI for a serialised product', function () {
    $phone = makeVariant('iPhone 15', 'IP15', ProductType::Serialized);
    [$order, $item] = makeOrder($phone, 2, 9500000);
    $this->purchasing->place($order);

    $this->purchasing->receive($order, [
        new ReceiptLine($item->id, 2, 9500000, ['356938035643809', '356938035643810']),
    ]);

    expect(StockItem::inStock()->where('product_variant_id', $phone->id)->count())->toBe(2)
        // One movement per unit, so the ledger and the unit count agree.
        ->and(StockMovement::where('product_variant_id', $phone->id)->count())->toBe(2)
        ->and($this->stock->onHand($this->location->id, $phone->id))->toBe(2.0)
        ->and(StockItem::where('imei', '356938035643809')->first()->purchase_cost_minor)->toBe(9500000);
});

it('requires exactly one IMEI per unit received', function () {
    $phone = makeVariant('iPhone 15', 'IP15', ProductType::Serialized);
    [$order, $item] = makeOrder($phone, 3, 9500000);
    $this->purchasing->place($order);

    // Two IMEIs for three handsets means one is untracked.
    expect(fn () => $this->purchasing->receive($order, [
        new ReceiptLine($item->id, 3, 9500000, ['111111111111111', '222222222222222']),
    ]))->toThrow(InvalidReceipt::class);

    expect(StockItem::count())->toBe(0)
        ->and($this->stock->onHand($this->location->id, $phone->id))->toBe(0.0);
});

it('refuses an IMEI that is already in stock', function () {
    $phone = makeVariant('iPhone 15', 'IP15', ProductType::Serialized);
    [$first, $firstItem] = makeOrder($phone, 1, 9500000);
    $this->purchasing->place($first);
    $this->purchasing->receive($first, [new ReceiptLine($firstItem->id, 1, 9500000, ['356938035643809'])]);

    // A second delivery claiming the same handset is a data-entry error.
    [$second, $secondItem] = makeOrder($phone, 1, 9500000);
    $this->purchasing->place($second);

    expect(fn () => $this->purchasing->receive($second, [
        new ReceiptLine($secondItem->id, 1, 9500000, ['356938035643809']),
    ]))->toThrow(InvalidReceipt::class);

    expect(StockItem::count())->toBe(1);
});

it('refuses the same IMEI twice in one delivery', function () {
    $phone = makeVariant('iPhone 15', 'IP15', ProductType::Serialized);
    [$order, $item] = makeOrder($phone, 2, 9500000);
    $this->purchasing->place($order);

    expect(fn () => $this->purchasing->receive($order, [
        new ReceiptLine($item->id, 2, 9500000, ['356938035643809', '356938035643809']),
    ]))->toThrow(InvalidReceipt::class);

    expect(StockItem::count())->toBe(0);
});

it('records partial payments and tracks what is still owed', function () {
    [$order, $item] = makeOrder($this->variant, 10, 19000);
    $this->purchasing->place($order);
    $this->purchasing->receive($order, [new ReceiptLine($item->id, 10, 19000)]);

    $cash = PaymentMethod::where('name', 'Cash')->firstOrFail();

    $this->purchasing->recordPayment($order, 100000, $cash->id);
    expect($order->refresh()->paid_minor)->toBe(100000)
        ->and($order->due_minor)->toBe(90000)
        ->and($order->isFullyPaid())->toBeFalse();

    $this->purchasing->recordPayment($order, 90000, $cash->id);
    expect($order->refresh()->due_minor)->toBe(0)
        ->and($order->isFullyPaid())->toBeTrue()
        ->and($order->payments()->count())->toBe(2);
});

it('never reports a negative amount owed', function () {
    [$order] = makeOrder($this->variant, 1, 10000);
    $this->purchasing->place($order);

    // An overpayment is a credit to be handled, not a negative debt.
    $this->purchasing->recordPayment($order, 50000);

    expect($order->refresh()->due_minor)->toBe(0);
});

it('rejects a payment of zero or less', function () {
    [$order] = makeOrder($this->variant, 1, 10000);
    $this->purchasing->place($order);

    expect(fn () => $this->purchasing->recordPayment($order, 0))->toThrow(InvalidReceipt::class)
        ->and(fn () => $this->purchasing->recordPayment($order, -500))->toThrow(InvalidReceipt::class);
});

it('rejects a receipt line with no quantity', function () {
    expect(fn () => new ReceiptLine(1, 0))->toThrow(InvalidReceipt::class)
        ->and(fn () => new ReceiptLine(1, -3))->toThrow(InvalidReceipt::class);
});

it('totals what a vendor is owed across orders', function () {
    [$first, $firstItem] = makeOrder($this->variant, 10, 19000);
    [$second, $secondItem] = makeOrder($this->variant, 5, 19000);

    $this->purchasing->place($first);
    $this->purchasing->place($second);
    $this->purchasing->recordPayment($first, 50000);

    // 190000 - 50000 owed on the first, 95000 on the second.
    expect($this->vendor->refresh()->outstandingMinor())->toBe(235000);
});

it('keeps purchasing separate between shops', function () {
    [$order, $item] = makeOrder($this->variant, 5, 19000);
    $this->purchasing->place($order);
    $this->purchasing->receive($order, [new ReceiptLine($item->id, 5, 19000)]);

    tenancy()->end();

    $other = Tenant::create(['name' => 'Rahim Electronics', 'slug' => 'rahim']);
    $other->domains()->create(['domain' => 'rahim.lorapok.localhost']);
    $this->createdTenants->push($other);

    tenancy()->initialize($other);
    expect(PurchaseOrder::count())->toBe(0)
        ->and(Vendor::count())->toBe(0)
        ->and(GoodsReceipt::count())->toBe(0);

    tenancy()->end();
    tenancy()->initialize($this->tenant);
    expect(PurchaseOrder::count())->toBe(1);
});
