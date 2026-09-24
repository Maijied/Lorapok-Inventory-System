<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where an individually tracked unit (an IMEI or serial) currently is.
 *
 * Transitions are enforced, so a handset cannot be sold twice or returned
 * without having been sold.
 */
enum StockItemStatus: string
{
    case InStock = 'in_stock';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Returned = 'returned';
    case Defective = 'defective';
    case WrittenOff = 'written_off';
    case InTransit = 'in_transit';

    /** @return array<int, self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::InStock => [self::Reserved, self::Sold, self::Defective, self::WrittenOff, self::InTransit],
            self::Reserved => [self::InStock, self::Sold],
            self::Sold => [self::Returned],
            // A returned unit is inspected, then either resold or written off.
            self::Returned => [self::InStock, self::Defective, self::WrittenOff],
            self::Defective => [self::WrittenOff, self::InStock],
            self::InTransit => [self::InStock],
            // Terminal.
            self::WrittenOff => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Only units physically on the shelf count towards sellable stock. */
    public function countsAsOnHand(): bool
    {
        return $this === self::InStock;
    }
}
