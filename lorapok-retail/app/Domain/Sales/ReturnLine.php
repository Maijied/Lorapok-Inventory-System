<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Enums\ReturnCondition;

/**
 * One line of a return: how much of a sale line came back, and in what state.
 */
final readonly class ReturnLine
{
    public function __construct(
        public int $saleItemId,
        public float $qty,
        public ReturnCondition $condition = ReturnCondition::Resellable,
        /** @var array<int, int> stock_item ids, for serialised products. */
        public array $stockItemIds = [],
    ) {
        if ($qty <= 0) {
            throw new InvalidSale('Returned quantity must be greater than zero.');
        }
    }
}
