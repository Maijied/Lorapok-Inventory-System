<?php

declare(strict_types=1);

namespace App\Domain\Sales;

/**
 * One line a cashier has rung up.
 *
 * Note what is NOT here: no line total, no tax amount, no grand total. The
 * client says what was sold and how many; the server decides what it costs.
 *
 * The system this replaces computed every line total and the grand total in
 * browser JavaScript and trusted whatever was posted back, so any price could
 * be set by editing a form field.
 */
final readonly class CartLine
{
    public function __construct(
        public int $variantId,
        public float $qty = 1,
        /**
         * A cashier-applied discount in minor units. Permitted, but subject
         * to the shop's rules and recorded against the sale.
         */
        public int $discountMinor = 0,
        /**
         * An override unit price, only honoured for a user who may manage
         * prices. Ignored otherwise.
         */
        public ?int $unitPriceMinorOverride = null,
        /** @var array<int, int> stock_item ids for serialised products. */
        public array $stockItemIds = [],
    ) {
        if ($qty <= 0) {
            throw new InvalidSale('Quantity must be greater than zero.');
        }

        if ($discountMinor < 0) {
            throw new InvalidSale('A discount cannot be negative.');
        }

        if ($unitPriceMinorOverride !== null && $unitPriceMinorOverride < 0) {
            throw new InvalidSale('A price cannot be negative.');
        }
    }
}
