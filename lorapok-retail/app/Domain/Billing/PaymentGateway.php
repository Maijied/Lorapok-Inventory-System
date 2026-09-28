<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\PaymentAttempt;
use App\Models\SubscriptionInvoice;

/**
 * How money reaches us.
 *
 * This interface exists from day one even though the only implementation is
 * manual, and that is the point: building the flow around an interface means
 * bKash or Stripe drops in behind it later without touching how invoicing
 * works. Hardcoding "an operator marks it paid" would bake a temporary
 * arrangement into the shape of the system.
 *
 * Stripe is not available in Bangladesh, so manual-first is not a shortcut
 * here — it is the only thing that works today.
 */
interface PaymentGateway
{
    /** Short identifier stored on every attempt: `manual`, `bkash`, `stripe`. */
    public function name(): string;

    /**
     * Begin settling an invoice.
     *
     * Returns the attempt. A gateway that redirects elsewhere leaves it
     * pending; one that settles immediately marks it succeeded and the
     * invoice paid.
     *
     * @param  array<string, mixed>  $context
     */
    public function charge(SubscriptionInvoice $invoice, array $context = []): PaymentAttempt;

    /** Whether this gateway can currently be used. */
    public function isAvailable(): bool;
}
