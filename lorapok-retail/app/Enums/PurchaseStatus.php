<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a purchase order has got to.
 *
 * Driven by how much has actually been received, not set by hand, so the
 * status cannot disagree with the goods on the shelf.
 */
enum PurchaseStatus: string
{
    case Draft = 'draft';
    case Ordered = 'ordered';
    case Partial = 'partial';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ordered => 'Ordered',
            self::Partial => 'Partially received',
            self::Received => 'Received',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Stock may only be booked in against an order that is actually live. */
    public function canReceive(): bool
    {
        return in_array($this, [self::Ordered, self::Partial], true);
    }

    /**
     * Once anything has been received the order cannot be cancelled: the
     * stock exists and the vendor is owed for it. Return it instead.
     */
    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Ordered], true);
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
}
