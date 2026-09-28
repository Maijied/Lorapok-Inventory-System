<?php

declare(strict_types=1);

use App\Domain\Billing\BillingService;
use App\Domain\Billing\InvalidTransition;
use App\Domain\Central\ShopProvisioner;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\PaymentAttempt;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->billing = app(BillingService::class);

    $this->plan = Plan::create([
        'name' => 'Counter', 'slug' => 'counter',
        'price_minor' => 150000, 'trial_days' => 14,
    ]);

    $this->shop = app(ShopProvisioner::class)->create(
        name: 'Karim Mobile', slug: 'karim',
        ownerName: 'Karim Uddin', ownerEmail: 'owner@karim.test', ownerPassword: 'password12',
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

it('starts a shop on a trial', function () {
    $subscription = $this->billing->subscribe($this->shop, $this->plan);

    expect($subscription->status)->toBe(SubscriptionStatus::Trialing)
        ->and($subscription->onTrial())->toBeTrue()
        ->and($subscription->trial_ends_at->isAfter(now()->addDays(13)))->toBeTrue();
});

it('lets a shop keep selling while it is late paying', function () {
    // A shop that cannot ring up sales cannot earn the money to pay us.
    // Locking the till over an unpaid invoice turns a slow payer into a lost
    // customer, so only suspension stops a shop.
    $subscription = $this->billing->subscribe($this->shop, $this->plan);
    $this->billing->transition($subscription, SubscriptionStatus::Active);
    $this->billing->transition($subscription, SubscriptionStatus::PastDue);

    expect($subscription->fresh()->canOperate())->toBeTrue()
        ->and($this->shop->fresh()->isSuspended())->toBeFalse();
});

it('refuses a transition that makes no sense', function () {
    $subscription = $this->billing->subscribe($this->shop, $this->plan);
    $this->billing->transition($subscription, SubscriptionStatus::Cancelled);

    // Cancelled is terminal: coming back means a new subscription, not a
    // revived one. A shop stranded in a status nothing handles is worse than
    // a loud failure here.
    expect(fn () => $this->billing->transition($subscription, SubscriptionStatus::Active))
        ->toThrow(InvalidTransition::class);
});

it('is safe to re-run a transition', function () {
    // Scheduled jobs get retried; a transition must be idempotent or a retry
    // writes a second audit entry for something that happened once.
    $subscription = $this->billing->subscribe($this->shop, $this->plan);
    $this->billing->transition($subscription, SubscriptionStatus::Active);
    $this->billing->transition($subscription, SubscriptionStatus::Active);

    expect(DB::table('central_audit_logs')->where('action', 'subscription.active')->count())->toBe(1);
});

it('suspends and restores the shop itself, not just the subscription', function () {
    // BlockSuspendedShops reads tenants.suspended_at. A subscription marked
    // suspended while the shop row says otherwise is a shop still trading.
    $subscription = $this->billing->subscribe($this->shop, $this->plan);

    $this->billing->transition($subscription, SubscriptionStatus::Suspended, reason: 'unpaid for 60 days');
    expect($this->shop->fresh()->isSuspended())->toBeTrue();

    $this->billing->transition($subscription, SubscriptionStatus::Active);
    expect($this->shop->fresh()->isSuspended())->toBeFalse();
});

it('numbers invoices sequentially without reusing one', function () {
    $subscription = $this->billing->subscribe($this->shop, $this->plan);

    $first = $this->billing->issueInvoice($subscription);
    $second = $this->billing->issueInvoice($subscription);

    // Derived from the last issued rather than a row count: counting would
    // reuse a number after a deletion, and two invoices sharing one is
    // exactly what an auditor asks about.
    expect($first->number)->toBe('LR-'.now()->format('Y').'-00001')
        ->and($second->number)->toBe('LR-'.now()->format('Y').'-00002');
});

it('does not treat a declared payment as money received', function () {
    $subscription = $this->billing->subscribe($this->shop, $this->plan);
    $invoice = $this->billing->issueInvoice($subscription);

    $attempt = $this->billing->declarePayment($invoice, ['reference' => 'BKH12345', 'method' => 'bkash']);

    // Someone saying they paid is not the money arriving.
    expect($attempt->status)->toBe(PaymentAttempt::PENDING)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Open);
});

it('settles an invoice and brings the shop back', function () {
    $subscription = $this->billing->subscribe($this->shop, $this->plan);
    $this->billing->transition($subscription, SubscriptionStatus::Active);
    $this->billing->transition($subscription, SubscriptionStatus::Suspended, reason: 'unpaid');

    $invoice = $this->billing->issueInvoice($subscription);
    $attempt = $this->billing->declarePayment($invoice, ['reference' => 'BKH999']);

    $this->billing->settle($invoice, $attempt);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->fresh()->gateway_ref)->toBe('BKH999')
        ->and($attempt->fresh()->status)->toBe(PaymentAttempt::SUCCEEDED)
        ->and($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($this->shop->fresh()->isSuspended())->toBeFalse();
});

it('refuses to void an invoice that was already paid', function () {
    $subscription = $this->billing->subscribe($this->shop, $this->plan);
    $invoice = $this->billing->issueInvoice($subscription);
    $this->billing->settle($invoice, $this->billing->declarePayment($invoice));

    // Voiding a paid invoice would erase the record of money received. The
    // correction for that is a refund, which leaves both entries standing.
    expect(fn () => $this->billing->void($invoice, 'mistake'))
        ->toThrow(InvalidTransition::class);
});

it('voids rather than deletes', function () {
    $subscription = $this->billing->subscribe($this->shop, $this->plan);
    $invoice = $this->billing->issueInvoice($subscription);

    $this->billing->void($invoice, 'issued against the wrong plan');

    // The same rule the shops themselves are held to.
    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Void)
        ->and($invoice->fresh()->note)->toBe('issued against the wrong plan')
        ->and($invoice->fresh()->exists)->toBeTrue();
});

it('records every billing event against the shop it concerns', function () {
    // An audit entry nobody can find by shop is an audit entry nobody reads.
    $subscription = $this->billing->subscribe($this->shop, $this->plan);
    $invoice = $this->billing->issueInvoice($subscription);
    $this->billing->settle($invoice, $this->billing->declarePayment($invoice));

    $entries = DB::table('central_audit_logs')->where('tenant_id', $this->shop->id)->pluck('action');

    expect($entries)->toContain('subscription.created', 'invoice.issued', 'invoice.paid');
});
