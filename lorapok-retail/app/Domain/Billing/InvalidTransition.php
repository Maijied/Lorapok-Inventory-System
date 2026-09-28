<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Enums\SubscriptionStatus;
use DomainException;

/**
 * A subscription was asked to move somewhere it cannot go.
 *
 * Thrown rather than silently ignored: a shop left in a status nothing else
 * handles is worse than a loud failure at the point of the mistake.
 */
final class InvalidTransition extends DomainException
{
    public static function between(SubscriptionStatus $from, SubscriptionStatus $to): self
    {
        return new self(sprintf(
            'A subscription cannot go from %s to %s.%s',
            $from->value,
            $to->value,
            $from === SubscriptionStatus::Cancelled
                ? ' Cancelled is terminal — coming back means a new subscription.'
                : '',
        ));
    }
}
