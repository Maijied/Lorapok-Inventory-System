<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a shop stands with us.
 *
 * The order matters: a shop moves forward through these, and only `Suspended`
 * stops it selling. Being late on an invoice does not — a shop that cannot
 * ring up sales cannot earn the money to pay us, and locking the till over an
 * unpaid invoice turns a slow payer into a lost customer.
 */
enum SubscriptionStatus: string
{
    /** Evaluating. Full access, no invoice yet. */
    case Trialing = 'trialing';

    /** Paid and current. */
    case Active = 'active';

    /** An invoice is past its due date. Still selling, but told. */
    case PastDue = 'past_due';

    /** Access withdrawn. The only status that blocks the till. */
    case Suspended = 'suspended';

    /** Ended by the shop. Data retained until they ask otherwise. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Trial',
            self::Active => 'Active',
            self::PastDue => 'Past due',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }

    /** The semantic tone a badge should use — see retail-tokens.css. */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'positive',
            self::Trialing => 'info',
            self::PastDue => 'attention',
            self::Suspended, self::Cancelled => 'negative',
        };
    }

    /** Whether the shop can still operate. */
    public function canOperate(): bool
    {
        return match ($this) {
            self::Trialing, self::Active, self::PastDue => true,
            self::Suspended, self::Cancelled => false,
        };
    }

    /**
     * Statuses this one may legally become.
     *
     * Enumerated rather than left open so an impossible jump — cancelled back
     * to active without a new subscription, say — fails where it happens
     * instead of leaving a shop in a state nothing else handles.
     *
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Trialing => [self::Active, self::Suspended, self::Cancelled],
            self::Active => [self::PastDue, self::Suspended, self::Cancelled],
            self::PastDue => [self::Active, self::Suspended, self::Cancelled],
            // A suspended shop can be restored, or end for good.
            self::Suspended => [self::Active, self::PastDue, self::Cancelled],
            // Terminal. Coming back means a new subscription, not a revived one.
            self::Cancelled => [],
        };
    }

    public function canBecome(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}
