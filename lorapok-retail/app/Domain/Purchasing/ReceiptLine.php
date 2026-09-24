<?php

declare(strict_types=1);

namespace App\Domain\Purchasing;

/**
 * One line of a delivery: how much of an ordered line actually turned up,
 * and what it really cost.
 *
 * Cost is captured per receipt because the same order line can be delivered
 * twice at different prices, and the stock ledger needs the true cost of each
 * delivery to keep the weighted average honest.
 */
final readonly class ReceiptLine
{
    public function __construct(
        public int $purchaseItemId,
        public float $qty,
        public ?int $unitCostMinor = null,
        /** @var array<int, string> IMEIs or serials, for serialised products. */
        public array $serials = [],
    ) {
        if ($qty <= 0) {
            throw new InvalidReceipt('Received quantity must be greater than zero.');
        }

        if ($unitCostMinor !== null && $unitCostMinor < 0) {
            throw new InvalidReceipt('Unit cost cannot be negative.');
        }
    }
}
