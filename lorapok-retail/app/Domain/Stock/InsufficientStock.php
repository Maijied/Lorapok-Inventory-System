<?php

declare(strict_types=1);

namespace App\Domain\Stock;

use DomainException;

/**
 * Raised before anything is written.
 *
 * The old system inserted the sale line first and checked stock afterwards,
 * then threw an uncaught exception — leaving a 500 page and, on the update
 * path, no check at all, so quantities could go negative.
 */
class InsufficientStock extends DomainException
{
    public function __construct(
        public readonly string $productName,
        public readonly float $requested,
        public readonly float $available,
    ) {
        parent::__construct(sprintf(
            'Not enough %s in stock: %s requested, %s available.',
            $productName,
            rtrim(rtrim(number_format($requested, 4, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($available, 4, '.', ''), '0'), '.'),
        ));
    }
}
