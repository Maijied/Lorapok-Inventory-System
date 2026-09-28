<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Central\AuditLog;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\PaymentAttempt;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Billing.
 *
 * Two rules run through all of it:
 *
 *   - **A status change is validated, never assumed.** Every move goes through
 *     `transition()`, which refuses an illegal jump. A shop stranded in a
 *     state nothing else handles is far worse than a loud failure.
 *
 *   - **Being late does not stop a shop selling.** Only `Suspended` blocks the
 *     till. A shop that cannot ring up sales cannot earn the money to pay us,
 *     so locking the door over an unpaid invoice turns a slow payer into a
 *     lost customer. Suspension is a decision an operator makes, not something
 *     that happens on a timer.
 */
final class BillingService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * Put a shop on a plan, starting its trial.
     */
    public function subscribe(Tenant $tenant, Plan $plan, ?User $actor = null): Subscription
    {
        return DB::transaction(function () use ($tenant, $plan, $actor): Subscription {
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => now()->addDays($plan->trial_days),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays($plan->trial_days),
            ]);

            AuditLog::record(
                'subscription.created',
                $subscription,
                $actor,
                after: ['plan' => $plan->slug, 'trial_days' => $plan->trial_days],
                tenant: $tenant,
            );

            return $subscription;
        });
    }

    /**
     * Move a subscription to a new status, refusing an illegal jump.
     */
    public function transition(
        Subscription $subscription,
        SubscriptionStatus $to,
        ?User $actor = null,
        ?string $reason = null,
    ): Subscription {
        $from = $subscription->status;

        if ($from === $to) {
            return $subscription;   // idempotent; re-running a job is safe
        }

        if (! $from->canBecome($to)) {
            throw InvalidTransition::between($from, $to);
        }

        return DB::transaction(function () use ($subscription, $from, $to, $actor, $reason): Subscription {
            $subscription->status = $to;

            if ($to === SubscriptionStatus::Cancelled) {
                $subscription->cancel_at = now();
            }

            $subscription->save();

            // Suspension and restoration are the two that change what a shop
            // can do, so the shop row carries it too — BlockSuspendedShops
            // reads `tenants.suspended_at`, not the subscription.
            if ($to === SubscriptionStatus::Suspended) {
                $subscription->tenant?->forceFill(['suspended_at' => now()])->save();
            } elseif ($from === SubscriptionStatus::Suspended) {
                $subscription->tenant?->forceFill(['suspended_at' => null])->save();
            }

            AuditLog::record(
                'subscription.'.$to->value,
                $subscription,
                $actor,
                before: ['status' => $from->value],
                after: ['status' => $to->value, 'reason' => $reason],
                tenant: $subscription->tenant,
            );

            return $subscription;
        });
    }

    /**
     * Issue an invoice for the current period.
     */
    public function issueInvoice(Subscription $subscription, ?User $actor = null): SubscriptionInvoice
    {
        return DB::transaction(function () use ($subscription, $actor): SubscriptionInvoice {
            $plan = $subscription->plan;

            $invoice = $subscription->invoices()->create([
                'tenant_id' => $subscription->tenant_id,
                'number' => $this->nextInvoiceNumber(),
                'amount_minor' => $plan->price_minor,
                'currency' => $plan->currency,
                'status' => InvoiceStatus::Open,
                'period_start' => $subscription->current_period_start ?? now(),
                'period_end' => $subscription->current_period_end ?? now()->addMonth(),
                // Two weeks is deliberate rather than net-30: a shop paying by
                // bKash settles the same week or not at all, and a longer
                // window only delays finding out which.
                'due_at' => now()->addDays(14),
            ]);

            AuditLog::record(
                'invoice.issued',
                $invoice,
                $actor,
                after: ['number' => $invoice->number, 'amount_minor' => $invoice->amount_minor],
                tenant: $subscription->tenant,
            );

            return $invoice;
        });
    }

    /**
     * Record that a shop says it has paid.
     *
     * Deliberately does NOT mark the invoice paid: someone declaring payment
     * is not the money arriving. It creates the attempt an operator checks.
     *
     * @param  array<string, mixed>  $context
     */
    public function declarePayment(SubscriptionInvoice $invoice, array $context = []): PaymentAttempt
    {
        return $this->gateway->charge($invoice, $context);
    }

    /**
     * Confirm the money arrived, and settle the invoice.
     */
    public function settle(
        SubscriptionInvoice $invoice,
        ?PaymentAttempt $attempt = null,
        ?User $actor = null,
    ): SubscriptionInvoice {
        if ($invoice->status === InvoiceStatus::Paid) {
            return $invoice;   // idempotent
        }

        if ($invoice->status === InvoiceStatus::Void) {
            throw new InvalidTransition('A voided invoice cannot be paid. Issue a new one.');
        }

        return DB::transaction(function () use ($invoice, $attempt, $actor): SubscriptionInvoice {
            $attempt?->forceFill(['status' => PaymentAttempt::SUCCEEDED])->save();

            $invoice->forceFill([
                'status' => InvoiceStatus::Paid,
                'paid_at' => now(),
                'paid_via' => $attempt->gateway ?? PaymentAttempt::MANUAL,
                'gateway_ref' => $attempt?->reference,
            ])->save();

            // Paying clears a past-due state, and restores a shop that was
            // suspended for non-payment.
            $subscription = $invoice->subscription_id
                ? Subscription::find($invoice->subscription_id)
                : null;

            if ($subscription !== null && $subscription->status->canBecome(SubscriptionStatus::Active)) {
                $this->transition($subscription, SubscriptionStatus::Active, $actor, 'invoice '.$invoice->number.' paid');
            }

            AuditLog::record(
                'invoice.paid',
                $invoice,
                $actor,
                after: [
                    'number' => $invoice->number,
                    'amount_minor' => $invoice->amount_minor,
                    'via' => $invoice->paid_via,
                ],
                tenant: $invoice->tenant,
            );

            return $invoice;
        });
    }

    /**
     * Withdraw an invoice.
     *
     * Voided, never deleted — the same rule the shops themselves are held to.
     * An invoice that can disappear is not a record of anything.
     */
    public function void(SubscriptionInvoice $invoice, string $reason, ?User $actor = null): SubscriptionInvoice
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            throw new InvalidTransition('A paid invoice cannot be voided. Refund it instead.');
        }

        return DB::transaction(function () use ($invoice, $reason, $actor): SubscriptionInvoice {
            $invoice->forceFill(['status' => InvoiceStatus::Void, 'note' => $reason])->save();

            AuditLog::record(
                'invoice.voided',
                $invoice,
                $actor,
                after: ['number' => $invoice->number, 'reason' => $reason],
                tenant: $invoice->tenant,
            );

            return $invoice;
        });
    }

    /**
     * Sequential, gap-free invoice numbers.
     *
     * Formatted per year so a number carries its period, and derived from the
     * last issued rather than a count — counting rows would reuse a number
     * after a deletion, and two invoices sharing one is the kind of thing an
     * auditor asks about.
     */
    private function nextInvoiceNumber(): string
    {
        $year = now()->format('Y');

        $last = SubscriptionInvoice::query()
            ->where('number', 'like', "LR-{$year}-%")
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 1 : ((int) substr((string) $last, -5)) + 1;

        return sprintf('LR-%s-%05d', $year, $sequence);
    }
}
