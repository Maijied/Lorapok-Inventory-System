<?php

declare(strict_types=1);

use App\Domain\Reporting\DateRange;
use App\Domain\Reporting\ReportService;
use App\Domain\Sales\CartLine;
use App\Domain\Sales\ReturnLine;
use App\Domain\Sales\SalesService;
use App\Domain\Stock\MovementIntent;
use App\Domain\Stock\StockService;
use App\Enums\DiscountType;
use App\Enums\MovementType;
use App\Enums\ProductType;
use App\Models\Tenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Location;
use App\Models\Tenant\PaymentMethod;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Sale;
use App\Models\Tenant\Setting;
use App\Models\Tenant\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->createdTenants = collect();

    $this->tenant = Tenant::create(['name' => 'Karim Mobile', 'slug' => 'karim']);
    $this->tenant->domains()->create(['domain' => 'karim.lorapok.localhost']);
    $this->createdTenants->push($this->tenant);

    tenancy()->initialize($this->tenant);
    Setting::put('invoice.round_to_whole', false, 'invoice');

    $this->reports = app(ReportService::class);
    $this->sales = app(SalesService::class);
    $this->stock = app(StockService::class);
    $this->location = Location::default();

    $this->owner = User::create([
        'name' => 'Owner', 'email' => 'owner@karim.test',
        'password' => 'password', 'is_active' => true,
    ])->syncRoles(['owner']);

    // Sells at 100.00, cost 60.00 — so every unit earns exactly 40.00.
    $this->variant = reportVariant('Widget', 'W-1', 10000);
    reportStock($this->variant, 100, 6000);

    $this->range = DateRange::last(30, 'UTC');
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

function reportVariant(string $name, string $sku, int $price, ProductType $type = ProductType::Standard): ProductVariant
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

function reportStock(ProductVariant $variant, float $qty, int $cost): void
{
    test()->stock->apply(MovementIntent::inbound(
        test()->location, $variant, $qty, MovementType::PurchaseReceipt, $cost,
    ));
}

it('reports nothing for a period with no trade', function () {
    $summary = $this->reports->summary($this->range);

    expect($summary['sales_count'])->toBe(0)
        ->and($summary['net_minor'])->toBe(0)
        ->and($summary['margin_minor'])->toBe(0)
        // A period with no sales must not divide by zero.
        ->and($summary['margin_pct'])->toBe(0.0);
});

it('computes revenue, cost and margin', function () {
    // 5 units at 100.00, costing 60.00 each.
    $this->sales->sell([new CartLine($this->variant->id, 5)], $this->owner, $this->location->id);

    $summary = $this->reports->summary($this->range);

    expect($summary['sales_count'])->toBe(1)
        ->and($summary['gross_minor'])->toBe(50000)
        ->and($summary['cogs_minor'])->toBe(30000)
        ->and($summary['margin_minor'])->toBe(20000)
        ->and($summary['margin_pct'])->toBe(40.0);
});

it('subtracts discounts from revenue', function () {
    $this->sales->sell(
        [new CartLine($this->variant->id, 5)],
        $this->owner, $this->location->id,
        discountType: DiscountType::Fixed, discountValue: 10000,
    );

    $summary = $this->reports->summary($this->range);

    // 500.00 sold, 100.00 off, 300.00 cost => 100.00 margin.
    expect($summary['net_minor'])->toBe(40000)
        ->and($summary['margin_minor'])->toBe(10000);
});

it('subtracts refunds from revenue', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 5)], $this->owner, $this->location->id);

    $this->sales->returnItems($sale, [new ReturnLine($sale->items->first()->id, 2)], $this->owner);

    $summary = $this->reports->summary($this->range);

    // Revenue is what the shop kept: 500.00 less the 200.00 refunded.
    expect($summary['returns_count'])->toBe(1)
        ->and($summary['returns_minor'])->toBe(20000)
        ->and($summary['net_minor'])->toBe(30000);
});

it('excludes voided sales entirely', function () {
    $keep = $this->sales->sell([new CartLine($this->variant->id, 2)], $this->owner, $this->location->id);
    $void = $this->sales->sell([new CartLine($this->variant->id, 3)], $this->owner, $this->location->id);

    $this->sales->void($void, $this->owner, 'rang up twice');

    $summary = $this->reports->summary($this->range);

    // Otherwise a day's takings could be inflated by ringing up and reversing.
    expect($summary['sales_count'])->toBe(1)
        ->and($summary['gross_minor'])->toBe(20000);
});

it('keeps margin stable when stock is later bought cheaper', function () {
    $this->sales->sell([new CartLine($this->variant->id, 5)], $this->owner, $this->location->id);
    $before = $this->reports->summary($this->range)['margin_minor'];

    // A cheaper delivery must not retrospectively change what past sales earned.
    reportStock($this->variant, 500, 100);

    expect($this->reports->summary($this->range)['margin_minor'])->toBe($before);
});

it('tracks what was collected and what is still owed', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 5)], $this->owner, $this->location->id);
    $this->sales->recordPayment($sale, 20000);

    $summary = $this->reports->summary($this->range);

    expect($summary['collected_minor'])->toBe(20000)
        ->and($summary['outstanding_minor'])->toBe(30000);
});

it('ranks products by revenue with their real margin', function () {
    $cheap = reportVariant('Cheap', 'C-1', 1000);
    reportStock($cheap, 100, 500);

    $this->sales->sell([
        new CartLine($this->variant->id, 3),
        new CartLine($cheap->id, 10),
    ], $this->owner, $this->location->id);

    $top = $this->reports->topProducts($this->range);

    expect($top)->toHaveCount(2)
        // 300.00 beats 100.00.
        ->and($top->first()->name)->toBe('Widget')
        ->and((int) $top->first()->revenue_minor)->toBe(30000)
        ->and((int) $top->first()->margin_minor)->toBe(12000);
});

it('values stock at cost, not at retail', function () {
    // 100 units bought at 60.00 each.
    $valuation = $this->reports->stockValuation();

    expect($valuation['units'])->toBe(100.0)
        // The money tied up is 6,000.00 — not the 10,000.00 it would fetch.
        ->and($valuation['value_minor'])->toBe(600000);
});

it('lists products at or below their reorder level', function () {
    $low = reportVariant('Nearly out', 'LOW-1', 5000);
    $low->product->update(['reorder_level' => 10]);
    reportStock($low, 8, 2000);

    $rows = $this->reports->lowStock();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->name)->toBe('Nearly out')
        ->and((float) $rows->first()->on_hand)->toBe(8.0);
});

it('does not flag a service as out of stock', function () {
    $service = reportVariant('Repair', 'SVC-1', 5000, ProductType::Service);
    $service->product->update(['reorder_level' => 5]);

    // A service has no stock and would otherwise appear permanently low.
    expect($this->reports->lowStock())->toHaveCount(0);
});

it('lists customers who owe money', function () {
    $customer = Customer::create(['name' => 'Rahim', 'phone' => '01700000000']);

    $sale = $this->sales->sell(
        [new CartLine($this->variant->id, 5)], $this->owner, $this->location->id, $customer,
    );
    $this->sales->recordPayment($sale, 10000);

    $dues = $this->reports->customerDues();

    expect($dues)->toHaveCount(1)
        ->and($dues->first()->name)->toBe('Rahim')
        ->and((int) $dues->first()->due_minor)->toBe(40000);
});

it('splits takings by payment method', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 5)], $this->owner, $this->location->id);

    $cash = PaymentMethod::where('name', 'Cash')->firstOrFail();
    $bkash = PaymentMethod::where('name', 'bKash')->firstOrFail();

    $this->sales->recordPayment($sale, 20000, $cash->id);
    $this->sales->recordPayment($sale, 30000, $bkash->id);

    $rows = $this->reports->takingsByMethod($this->range)->keyBy('method');

    expect((int) $rows['bKash']->amount_minor)->toBe(30000)
        ->and((int) $rows['Cash']->amount_minor)->toBe(20000);
});

it('groups takings by day', function () {
    $this->sales->sell([new CartLine($this->variant->id, 2)], $this->owner, $this->location->id);
    $this->sales->sell([new CartLine($this->variant->id, 3)], $this->owner, $this->location->id);

    $daily = $this->reports->dailyTakings($this->range);

    expect($daily)->toHaveCount(1)
        ->and((int) $daily->first()->sales_count)->toBe(2)
        ->and((int) $daily->first()->total_minor)->toBe(50000);
});

it('excludes trade outside the reporting window', function () {
    $sale = $this->sales->sell([new CartLine($this->variant->id, 5)], $this->owner, $this->location->id);

    // Backdate it beyond the window.
    $sale->forceFill(['sold_at' => now()->subDays(90)])->save();

    expect($this->reports->summary($this->range)['sales_count'])->toBe(0);
});

it('rejects a range that ends before it starts', function () {
    expect(fn () => DateRange::of('2026-01-31', '2026-01-01'))->toThrow(DomainException::class);
});

it('shows margin to an owner', function () {
    actingAsTenantUser($this->owner);
    $this->sales->sell([new CartLine($this->variant->id, 5)], $this->owner, $this->location->id);

    Livewire::test('tenant.reports')
        ->assertSee('Gross margin')
        ->assertSee('Stock value');
});

it('hides margin from a cashier', function () {
    $cashier = User::create([
        'name' => 'Cashier', 'email' => 'cashier@karim.test',
        'password' => 'password', 'is_active' => true,
    ])->syncRoles(['cashier']);

    // A cashier has neither view_reports nor view_margin.
    expect($cashier->can('viewReports', Sale::class))->toBeFalse();
});

it('shows reports to an accountant but is explicit that margin is visible', function () {
    $accountant = User::create([
        'name' => 'Accountant', 'email' => 'acc@karim.test',
        'password' => 'password', 'is_active' => true,
    ])->syncRoles(['accountant']);

    actingAsTenantUser($accountant);

    // An accountant reads everything financial by design.
    Livewire::test('tenant.reports')
        ->assertOk()
        ->assertSee('Gross margin');
});

it('keeps reporting separate between shops', function () {
    $this->sales->sell([new CartLine($this->variant->id, 5)], $this->owner, $this->location->id);

    tenancy()->end();

    $other = Tenant::create(['name' => 'Rahim Electronics', 'slug' => 'rahim']);
    $other->domains()->create(['domain' => 'rahim.lorapok.localhost']);
    $this->createdTenants->push($other);

    tenancy()->initialize($other);
    expect(app(ReportService::class)->summary($this->range)['sales_count'])->toBe(0);

    tenancy()->end();
    tenancy()->initialize($this->tenant);
    expect($this->reports->summary($this->range)['sales_count'])->toBe(1);
});
