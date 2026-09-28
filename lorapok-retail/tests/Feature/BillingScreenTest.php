<?php

declare(strict_types=1);

use App\Domain\Billing\BillingService;
use App\Domain\Central\ShopProvisioner;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

/**
 * The operator billing screen.
 *
 * Phase 15 shipped the whole billing domain — the state machine, the gateway
 * abstraction, eleven tests — and no way to reach any of it. An operator
 * could not issue an invoice or mark one paid.
 */
beforeEach(function () {
    $this->operator = User::create([
        'name' => 'Operator', 'email' => 'ops@lorapok.test',
        'password' => 'password12', 'is_super_admin' => true, 'is_active' => true,
    ]);

    $this->plan = Plan::create([
        'name' => 'Counter', 'slug' => 'counter', 'price_minor' => 150000, 'trial_days' => 14,
    ]);

    $this->shop = app(ShopProvisioner::class)->create(
        name: 'Karim Mobile', slug: 'karim',
        ownerName: 'Karim Uddin', ownerEmail: 'owner@karim.test', ownerPassword: 'password12',
    );

    $this->actingAs($this->operator, 'web');
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
    auth('web')->logout();

    $this->get('http://lorapok.localhost/admin/billing')->assertRedirect(route('central.login'));
});

it('puts a shop on a plan', function () {
    Livewire::test('central.billing')
        ->set('shop', 'karim')
        ->set('planSlug', 'counter')
        ->call('subscribe');

    expect($this->shop->fresh()->id)->not->toBeNull();

    $subscription = Subscription::where('tenant_id', $this->shop->id)->sole();

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing);
});

it('issues an invoice', function () {
    app(BillingService::class)->subscribe($this->shop, $this->plan, $this->operator);

    Livewire::test('central.billing')
        ->set('shop', 'karim')
        ->call('issue');

    expect(SubscriptionInvoice::where('tenant_id', $this->shop->id)->count())->toBe(1);
});

it('records the payment reference when marking an invoice paid', function () {
    $subscription = app(BillingService::class)->subscribe($this->shop, $this->plan, $this->operator);
    $invoice = app(BillingService::class)->issueInvoice($subscription, $this->operator);

    Livewire::test('central.billing')
        ->set('shop', 'karim')
        ->set('paymentReference', 'BKH-8891-2231')
        ->call('markPaid', $invoice->id);

    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        // A disputed payment has to be traceable to what the shop gave us.
        ->and($invoice->gateway_ref)->toBe('BKH-8891-2231');
});

it('only offers transitions the state machine allows', function () {
    $subscription = app(BillingService::class)->subscribe($this->shop, $this->plan, $this->operator);
    app(BillingService::class)->transition($subscription, SubscriptionStatus::Cancelled, $this->operator);

    // Cancelled is terminal, so no transition is offered — asserted on the
    // wire:click handlers rather than on label text, which also appears in
    // the explanatory copy.
    Livewire::test('central.billing')
        ->set('shop', 'karim')
        ->assertDontSee('wire:click="move(', escape: false)
        ->assertSee('This subscription has ended');
});

it('shows why the state machine refused something', function () {
    $subscription = app(BillingService::class)->subscribe($this->shop, $this->plan, $this->operator);
    app(BillingService::class)->transition($subscription, SubscriptionStatus::Cancelled, $this->operator);

    // Reached directly rather than through a button. The message is worth
    // reading, so it is shown rather than swallowed.
    Livewire::test('central.billing')
        ->set('shop', 'karim')
        ->call('move', 'active')
        ->assertSee('cannot go from cancelled to active');
});

it('voids an invoice with its reason, and keeps it visible', function () {
    $subscription = app(BillingService::class)->subscribe($this->shop, $this->plan, $this->operator);
    $invoice = app(BillingService::class)->issueInvoice($subscription, $this->operator);

    Livewire::test('central.billing')
        ->set('shop', 'karim')
        ->set('voiding', $invoice->id)
        ->set('voidReason', 'Issued against the wrong plan')
        ->call('void')
        // Still listed, with the reason. An invoice that can disappear is not
        // a record of anything.
        ->assertSee($invoice->number)
        ->assertSee('Issued against the wrong plan');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Void);
});

it('refuses to void an invoice that was already paid', function () {
    $subscription = app(BillingService::class)->subscribe($this->shop, $this->plan, $this->operator);
    $invoice = app(BillingService::class)->issueInvoice($subscription, $this->operator);
    app(BillingService::class)->settle($invoice, app(BillingService::class)->declarePayment($invoice), $this->operator);

    Livewire::test('central.billing')
        ->set('shop', 'karim')
        ->set('voiding', $invoice->id)
        ->set('voidReason', 'Changed my mind')
        ->call('void')
        // Voiding a paid invoice would erase the record of money received.
        ->assertSee('Refund it instead');
});

it('surfaces overdue invoices without having to check shop by shop', function () {
    $subscription = app(BillingService::class)->subscribe($this->shop, $this->plan, $this->operator);
    $invoice = app(BillingService::class)->issueInvoice($subscription, $this->operator);
    $invoice->forceFill(['due_at' => now()->subDays(20)])->save();

    // An overdue invoice nobody looks at is an invoice that never gets chased.
    Livewire::test('central.billing')
        ->assertSee('Overdue')
        ->assertSee('Karim Mobile');
});
