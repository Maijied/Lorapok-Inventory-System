<?php

declare(strict_types=1);

use App\Domain\Sales\CartLine;
use App\Domain\Sales\InvalidSale;
use App\Domain\Sales\ReturnLine;
use App\Domain\Sales\SalesService;
use App\Domain\Stock\InsufficientStock;
use App\Domain\Stock\MovementIntent;
use App\Domain\Stock\StockService;
use App\Enums\DiscountType;
use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Enums\ReturnCondition;
use App\Enums\SaleStatus;
use App\Enums\StockItemStatus;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Location;
use App\Models\Tenant\PaymentMethod;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleItem;
use App\Models\Tenant\Setting;
use App\Models\Tenant\StockItem;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\TaxRate;
use App\Models\Tenant\User;

beforeEach(function () {
    $this->createdTenants = collect();

    $this->tenant = Tenant::create(['name' => 'Karim Mobile', 'slug' => 'karim']);
    $this->tenant->domains()->create(['domain' => 'karim.lorapok.localhost']);
    $this->createdTenants->push($this->tenant);

    tenancy()->initialize($this->tenant);

    // Whole-unit rounding is a shop setting; off by default here so the
    // arithmetic under test is unobscured.
    Setting::put('invoice.round_to_whole', false, 'invoice');

    $this->sales = app(SalesService::class);
    $this->stock = app(StockService::class);
    $this->location = Location::default();

    $this->owner = User::create([
        'name' => 'Owner', 'email' => 'owner@karim.test',
        'password' => 'password', 'is_active' => true,
    ])->syncRoles(['owner']);

    $this->cashier = User::create([
        'name' => 'Cashier', 'email' => 'cashier@karim.test',
        'password' => 'password', 'is_active' => true,
    ])->syncRoles(['cashier']);

    // A cable that lists at 350.00 and cost 200.00.
    $this->variant = sellableVariant('USB-C Cable', 'CBL-1', 35000);
    stockUp($this->variant, 20, 20000);
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

function sellableVariant(
    string $name,
    string $sku,
    int $priceMinor,
    ProductType $type = ProductType::Standard,
    ?int $taxRateId = null,
    ?int $warrantyDays = null,
): ProductVariant {
    $product = Product::create([
        'name' => $name, 'sku' => $sku, 'slug' => strtolower($sku),
        'type' => $type, 'track_stock' => $type->tracksStock(),
        'reorder_level' => 0, 'is_active' => true,
        'tax_rate_id' => $taxRateId, 'warranty_days' => $warrantyDays,
    ]);

    return ProductVariant::create([
        'product_id' => $product->id, 'sku' => $sku,
        'cost_minor' => 0, 'price_minor' => $priceMinor, 'is_active' => true,
    ]);
}

function stockUp(ProductVariant $variant, float $qty, int $unitCostMinor): void
{
    test()->stock->apply(MovementIntent::inbound(
        test()->location, $variant, $qty, MovementType::PurchaseReceipt, $unitCostMinor,
    ));
}

it('prices a sale from the catalogue, not from the client', function () {
    // The old system computed every total in browser JavaScript and saved
    // whatever was posted back, so any price could be set from a form field.
    $sale = $this->sales->sell(
        [new CartLine($this->variant->id, 2, 0, unitPriceMinorOverride: 100)],
        $this->cashier,
        $this->location->id,
    );

    expect($sale->subtotal_minor)->toBe(70000)
        ->and($sale->total_minor)->toBe(70000)
        ->and($sale->items->first()->unit_price_minor)->toBe(35000);
});

it('honours a price override from someone allowed to set prices', function () {
    $sale = $this->sales->sell(
        [new CartLine($this->variant->id, 1, 0, unitPriceMinorOverride: 30000)],
        $this->owner,
        $this->location->id,
    );

    expect($sale->subtotal_minor)->toBe(30000);
});

it('stores a unit price, not a line total', function () {
    // The old column was named selling_price but held the line total, and
    // views divided by quantity to recover a unit price — which blew up when
    // quantity was blank.
    $sale = $this->sales->sell([new CartLine($this->variant->id, 4)], $this->owner, $this->location->id);
    $item = $sale->items->first();

    expect($item->unit_price_minor)->toBe(35000)
        ->and($item->line_total_minor)->toBe(140000);
});

it('takes stock off the shelf when something is sold', function () {
    $this->sales->sell([new CartLine($this->variant->id, 3)], $this->owner, $this->location->id);

    expect($this->stock->onHand($this->location->id, $this->variant->id))->toBe(17.0);
});

it('freezes the cost of goods sold so margin is computable', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 2)], $this->owner, $this->location->id);

    // Bought at 200.00, sells at 350.00.
    expect($sale->cogs_minor)->toBe(40000)
        ->and($sale->grossMarginMinor())->toBe(30000);

    // Restocking cheaper afterwards must not rewrite this sale's margin.
    stockUp($this->variant, 50, 1000);

    expect($sale->fresh()->grossMarginMinor())->toBe(30000);
});

it('refuses to sell more than is in stock', function () {
    expect(fn () => $this->sales->sell(
        [new CartLine($this->variant->id, 999)], $this->owner, $this->location->id,
    ))->toThrow(InsufficientStock::class);

    // Nothing may survive the refusal.
    expect(Sale::count())->toBe(0)
        ->and($this->stock->onHand($this->location->id, $this->variant->id))->toBe(20.0);
});

it('rolls the whole sale back when one line is short', function () {
    $other = sellableVariant('Case', 'CASE-1', 50000);
    stockUp($other, 1, 10000);

    expect(fn () => $this->sales->sell([
        new CartLine($this->variant->id, 2),
        new CartLine($other->id, 5),
    ], $this->owner, $this->location->id))->toThrow(InsufficientStock::class);

    expect(Sale::count())->toBe(0)
        ->and($this->stock->onHand($this->location->id, $this->variant->id))->toBe(20.0)
        ->and($this->stock->onHand($this->location->id, $other->id))->toBe(1.0);
});

it('computes tax per line rather than on the order total', function () {
    $rate = TaxRate::create(['name' => 'VAT 15%', 'rate_bps' => 1500, 'is_active' => true]);
    $taxed = sellableVariant('Taxed item', 'TAX-1', 10000, taxRateId: $rate->id);
    stockUp($taxed, 10, 5000);

    $sale = $this->sales->sell([new CartLine($taxed->id, 3)], $this->owner, $this->location->id);

    // 3 x 100.00 = 300.00, 15% = 45.00
    expect($sale->subtotal_minor)->toBe(30000)
        ->and($sale->tax_minor)->toBe(4500)
        ->and($sale->total_minor)->toBe(34500);
});

it('applies a fixed discount', function () {
    $sale = $this->sales->sell(
        [new CartLine($this->variant->id, 2)],
        $this->owner, $this->location->id,
        discountType: DiscountType::Fixed, discountValue: 10000,
    );

    expect($sale->subtotal_minor)->toBe(70000)
        ->and($sale->discount_minor)->toBe(10000)
        ->and($sale->total_minor)->toBe(60000);
});

it('applies a percentage discount in basis points', function () {
    $sale = $this->sales->sell(
        [new CartLine($this->variant->id, 2)],
        $this->owner, $this->location->id,
        discountType: DiscountType::Percent, discountValue: 1000, // 10%
    );

    expect($sale->discount_minor)->toBe(7000)
        ->and($sale->total_minor)->toBe(63000);
});

it('never lets a discount make the total negative', function () {
    $sale = $this->sales->sell(
        [new CartLine($this->variant->id, 1)],
        $this->owner, $this->location->id,
        discountType: DiscountType::Fixed, discountValue: 999999,
    );

    expect($sale->total_minor)->toBe(0)
        ->and($sale->discount_minor)->toBe(35000);
});

it('keeps the invoice arithmetic balanced', function () {
    $rate = TaxRate::create(['name' => 'VAT 7.5%', 'rate_bps' => 750, 'is_active' => true]);
    $taxed = sellableVariant('Odd price', 'ODD-1', 3333, taxRateId: $rate->id);
    stockUp($taxed, 10, 1000);

    $sale = $this->sales->sell(
        [new CartLine($taxed->id, 3)],
        $this->owner, $this->location->id,
        discountType: DiscountType::Percent, discountValue: 333,
    );

    // subtotal - discount + tax + rounding must equal the total exactly.
    expect($sale->subtotal_minor - $sale->discount_minor + $sale->tax_minor + $sale->rounding_minor)
        ->toBe($sale->total_minor);
});

it('rounds the total to whole currency units when the shop asks for it', function () {
    Setting::put('invoice.round_to_whole', true, 'invoice');
    $odd = sellableVariant('Odd', 'ODD-2', 3333);
    stockUp($odd, 10, 1000);

    $sale = $this->sales->sell([new CartLine($odd->id, 1)], $this->owner, $this->location->id);

    // 33.33 settles at 33.00, with the adjustment recorded.
    expect($sale->total_minor % 100)->toBe(0)
        ->and($sale->subtotal_minor - $sale->discount_minor + $sale->tax_minor + $sale->rounding_minor)
        ->toBe($sale->total_minor);
});

it('records who made the sale', function () {
    // The old sells table had no user_id at all, so no sale was attributable.
    $sale = $this->sales->sell([new CartLine($this->variant->id, 1)], $this->cashier, $this->location->id);

    expect($sale->user_id)->toBe($this->cashier->id);
});

it('snapshots the product name so renaming does not rewrite history', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 1)], $this->owner, $this->location->id);

    $this->variant->product->update(['name' => 'Renamed Later']);

    expect($sale->items->first()->description_snapshot)->toBe('USB-C Cable');
});

it('assigns a walk-in customer when none is given', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 1)], $this->owner, $this->location->id);

    expect($sale->customer->is_walkin)->toBeTrue();
});

it('does not create the same sale twice for one idempotency key', function () {
    // An offline till replaying a queued sale must not double-charge.
    $first = $this->sales->sell(
        [new CartLine($this->variant->id, 2)], $this->owner, $this->location->id,
        idempotencyKey: 'till-1-0001',
    );

    $second = $this->sales->sell(
        [new CartLine($this->variant->id, 2)], $this->owner, $this->location->id,
        idempotencyKey: 'till-1-0001',
    );

    expect($second->id)->toBe($first->id)
        ->and(Sale::count())->toBe(1)
        // Critically, stock is taken once, not twice.
        ->and($this->stock->onHand($this->location->id, $this->variant->id))->toBe(18.0);
});

it('takes partial payment and tracks what is owed', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 2)], $this->owner, $this->location->id);
    $cash = PaymentMethod::where('name', 'Cash')->firstOrFail();

    $this->sales->recordPayment($sale, 30000, $cash->id);
    expect($sale->refresh()->due_minor)->toBe(40000);

    // Split across a second method.
    $bkash = PaymentMethod::where('name', 'bKash')->firstOrFail();
    $this->sales->recordPayment($sale, 40000, $bkash->id);

    expect($sale->refresh()->due_minor)->toBe(0)
        ->and($sale->payments()->count())->toBe(2);
});

it('treats an overpayment as change, not a negative debt', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 1)], $this->owner, $this->location->id);

    $this->sales->recordPayment($sale, 100000);

    expect($sale->refresh()->due_minor)->toBe(0);
});

it('restores stock when a sale is voided', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 5)], $this->owner, $this->location->id);
    expect($this->stock->onHand($this->location->id, $this->variant->id))->toBe(15.0);

    $this->sales->void($sale, $this->owner, 'rang up twice');

    // The old system deleted sales and never restored the stock.
    expect($this->stock->onHand($this->location->id, $this->variant->id))->toBe(20.0)
        ->and($sale->refresh()->status)->toBe(SaleStatus::Void);
});

it('keeps a voided sale in the record', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 1)], $this->owner, $this->location->id);

    $this->sales->void($sale, $this->owner, 'customer changed mind');

    expect(Sale::find($sale->id))->not->toBeNull()
        ->and($sale->fresh()->void_reason)->toBe('customer changed mind')
        ->and($sale->fresh()->voided_by)->toBe($this->owner->id)
        // Reversed by compensating entries, never by editing the originals.
        ->and(StockMovement::where('type', MovementType::SaleReturn)->count())->toBe(1);
});

it('will not void a sale twice', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 1)], $this->owner, $this->location->id);
    $this->sales->void($sale, $this->owner, 'first');

    expect(fn () => $this->sales->void($sale->refresh(), $this->owner, 'again'))
        ->toThrow(InvalidSale::class);

    expect($this->stock->onHand($this->location->id, $this->variant->id))->toBe(20.0);
});

it('will not take payment against a voided sale', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 1)], $this->owner, $this->location->id);
    $this->sales->void($sale, $this->owner, 'void');

    expect(fn () => $this->sales->recordPayment($sale->refresh(), 100))->toThrow(InvalidSale::class);
});

it('returns stock to the shelf', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 5)], $this->owner, $this->location->id);
    $item = $sale->items->first();

    $return = $this->sales->returnItems(
        $sale, [new ReturnLine($item->id, 2)], $this->owner, 'changed mind',
    );

    expect($this->stock->onHand($this->location->id, $this->variant->id))->toBe(17.0)
        ->and($return->refund_total_minor)->toBe(70000);
});

it('refunds what was actually paid, net of discount', function () {
    $sale = $this->sales->sell(
        [new CartLine($this->variant->id, 2)],
        $this->owner, $this->location->id,
        discountType: DiscountType::Percent, discountValue: 1000, // 10% off
    );
    $item = $sale->items->first();

    $return = $this->sales->returnItems($sale, [new ReturnLine($item->id, 1)], $this->owner);

    // The line was discounted at the line level, so the refund follows the
    // line total rather than today's list price.
    expect($return->refund_total_minor)->toBe(35000);
});

it('does not put a defective return back on the shelf', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 3)], $this->owner, $this->location->id);
    $item = $sale->items->first();
    $after = $this->stock->onHand($this->location->id, $this->variant->id);

    $this->sales->returnItems(
        $sale, [new ReturnLine($item->id, 1, ReturnCondition::Defective)], $this->owner,
    );

    // It comes back into the building and is immediately written off, so
    // sellable stock is unchanged.
    expect($this->stock->onHand($this->location->id, $this->variant->id))->toBe($after)
        ->and(StockMovement::where('type', MovementType::WriteOff)->count())->toBe(1);
});

it('refuses to return more than was sold', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 2)], $this->owner, $this->location->id);
    $item = $sale->items->first();

    expect(fn () => $this->sales->returnItems($sale, [new ReturnLine($item->id, 5)], $this->owner))
        ->toThrow(InvalidSale::class);
});

it('refuses to return the same units twice', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 3)], $this->owner, $this->location->id);
    $item = $sale->items->first();

    $this->sales->returnItems($sale, [new ReturnLine($item->id, 2)], $this->owner);

    expect(fn () => $this->sales->returnItems($sale, [new ReturnLine($item->id, 2)], $this->owner))
        ->toThrow(InvalidSale::class);

    expect(SaleItem::find($item->id)->returnableQty())->toBe(1.0);
});

it('will not void a sale that has been returned against', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 3)], $this->owner, $this->location->id);
    $this->sales->returnItems($sale, [new ReturnLine($sale->items->first()->id, 1)], $this->owner);

    // Voiding on top of a return would restore the same stock twice.
    expect(fn () => $this->sales->void($sale->refresh(), $this->owner, 'too late'))
        ->toThrow(InvalidSale::class);
});

it('sells a specific handset and marks it sold', function () {
    $phone = sellableVariant('iPhone 15', 'IP15', 11200000, ProductType::Serialized, warrantyDays: 365);
    $unit = StockItem::create([
        'product_variant_id' => $phone->id, 'location_id' => $this->location->id,
        'imei' => '356938035643809', 'status' => StockItemStatus::InStock,
        'purchase_cost_minor' => 9500000,
    ]);
    stockUp($phone, 1, 9500000);

    $sale = $this->sales->sell(
        [new CartLine($phone->id, 1, stockItemIds: [$unit->id])],
        $this->owner, $this->location->id,
    );

    expect($unit->fresh()->status)->toBe(StockItemStatus::Sold)
        ->and($sale->cogs_minor)->toBe(9500000)
        // Warranty runs from the moment of sale.
        ->and($unit->fresh()->warranty_ends_at)->not->toBeNull()
        ->and($this->stock->onHand($this->location->id, $phone->id))->toBe(0.0);
});

it('requires an IMEI selection for a serialised product', function () {
    $phone = sellableVariant('iPhone 15', 'IP15', 11200000, ProductType::Serialized);
    stockUp($phone, 2, 9500000);

    expect(fn () => $this->sales->sell(
        [new CartLine($phone->id, 2)], $this->owner, $this->location->id,
    ))->toThrow(InvalidSale::class);
});

it('will not sell a handset that is already sold', function () {
    $phone = sellableVariant('iPhone 15', 'IP15', 11200000, ProductType::Serialized);
    $unit = StockItem::create([
        'product_variant_id' => $phone->id, 'location_id' => $this->location->id,
        'imei' => '356938035643809', 'status' => StockItemStatus::InStock,
        'purchase_cost_minor' => 9500000,
    ]);
    stockUp($phone, 2, 9500000);

    $this->sales->sell(
        [new CartLine($phone->id, 1, stockItemIds: [$unit->id])], $this->owner, $this->location->id,
    );

    expect(fn () => $this->sales->sell(
        [new CartLine($phone->id, 1, stockItemIds: [$unit->id])], $this->owner, $this->location->id,
    ))->toThrow(InvalidSale::class);
});

it('puts a returned handset back in stock', function () {
    $phone = sellableVariant('iPhone 15', 'IP15', 11200000, ProductType::Serialized);
    $unit = StockItem::create([
        'product_variant_id' => $phone->id, 'location_id' => $this->location->id,
        'imei' => '356938035643809', 'status' => StockItemStatus::InStock,
        'purchase_cost_minor' => 9500000,
    ]);
    stockUp($phone, 1, 9500000);

    $sale = $this->sales->sell(
        [new CartLine($phone->id, 1, stockItemIds: [$unit->id])], $this->owner, $this->location->id,
    );

    $this->sales->returnItems($sale, [
        new ReturnLine($sale->items->first()->id, 1, ReturnCondition::Resellable, [$unit->id]),
    ], $this->owner);

    // The same physical handset returns, not merely "a unit".
    expect($unit->fresh()->status)->toBe(StockItemStatus::InStock)
        ->and($this->stock->onHand($this->location->id, $phone->id))->toBe(1.0);
});

it('marks a faulty handset defective rather than resellable', function () {
    $phone = sellableVariant('iPhone 15', 'IP15', 11200000, ProductType::Serialized);
    $unit = StockItem::create([
        'product_variant_id' => $phone->id, 'location_id' => $this->location->id,
        'imei' => '356938035643809', 'status' => StockItemStatus::InStock,
        'purchase_cost_minor' => 9500000,
    ]);
    stockUp($phone, 1, 9500000);

    $sale = $this->sales->sell(
        [new CartLine($phone->id, 1, stockItemIds: [$unit->id])], $this->owner, $this->location->id,
    );

    $this->sales->returnItems($sale, [
        new ReturnLine($sale->items->first()->id, 1, ReturnCondition::Defective, [$unit->id]),
    ], $this->owner);

    expect($unit->fresh()->status)->toBe(StockItemStatus::Defective);
});

it('sells a service without touching stock', function () {
    $service = sellableVariant('Screen fitting', 'SVC-1', 15000, ProductType::Service);

    $sale = $this->sales->sell([new CartLine($service->id, 1)], $this->owner, $this->location->id);

    expect($sale->total_minor)->toBe(15000)
        ->and($sale->cogs_minor)->toBe(0)
        ->and(StockMovement::where('product_variant_id', $service->id)->count())->toBe(0);
});

it('refuses to sell an inactive product', function () {
    $this->variant->product->update(['is_active' => false]);

    expect(fn () => $this->sales->sell(
        [new CartLine($this->variant->id, 1)], $this->owner, $this->location->id,
    ))->toThrow(InvalidSale::class);
});

it('rejects an empty cart and a zero quantity', function () {
    expect(fn () => $this->sales->sell([], $this->owner, $this->location->id))->toThrow(InvalidSale::class)
        ->and(fn () => new CartLine($this->variant->id, 0))->toThrow(InvalidSale::class)
        ->and(fn () => new CartLine($this->variant->id, -1))->toThrow(InvalidSale::class);
});

it('tracks what a customer still owes', function () {
    $customer = Customer::create(['name' => 'Rahim', 'phone' => '01700000000']);

    $sale = $this->sales->sell(
        [new CartLine($this->variant->id, 2)], $this->owner, $this->location->id, $customer,
    );
    $this->sales->recordPayment($sale, 20000);

    expect($customer->refresh()->outstandingMinor())->toBe(50000);
});

it('keeps sales separate between shops', function () {
    $this->sales->sell([new CartLine($this->variant->id, 1)], $this->owner, $this->location->id);

    tenancy()->end();

    $other = Tenant::create(['name' => 'Rahim Electronics', 'slug' => 'rahim']);
    $other->domains()->create(['domain' => 'rahim.lorapok.localhost']);
    $this->createdTenants->push($other);

    tenancy()->initialize($other);
    expect(Sale::count())->toBe(0);

    tenancy()->end();
    tenancy()->initialize($this->tenant);
    expect(Sale::count())->toBe(1);
});
