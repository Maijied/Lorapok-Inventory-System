<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\PaymentAttempt;
use App\Models\SubscriptionInvoice;

/**
 * Money that arrived outside any gateway.
 *
 * bKash, Nagad, a bank transfer, cash in an envelope. The shop pays however
 * they already pay their suppliers and gives us the reference; an operator
 * confirms it.
 *
 * The attempt is recorded as `pending` rather than `succeeded`, because
 * someone saying they have paid is not the same as the money arriving. It
 * becomes succeeded when `BillingService::settle()` is called by an operator
 * who has checked.
 */
final class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return PaymentAttempt::MANUAL;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /** @param  array<string, mixed>  $context */
    public function charge(SubscriptionInvoice $invoice, array $context = []): PaymentAttempt
    {
        return $invoice->attempts()->create([
            'gateway' => $this->name(),
            'status' => PaymentAttempt::PENDING,
            'amount_minor' => $invoice->amount_minor,
            'currency' => $invoice->currency,
            // Whatever the shop gave us: a bKash transaction id, a bank slip
            // number. It is what an operator checks against.
            'reference' => $context['reference'] ?? null,
            'payload' => [
                'method' => $context['method'] ?? null,
                'declared_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
