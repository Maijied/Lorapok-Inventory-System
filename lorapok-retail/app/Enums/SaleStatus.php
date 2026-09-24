<?php

declare(strict_types=1);

namespace App\Enums;

enum SaleStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    /**
     * Reversed, not removed.
     *
     * The old system deleted sales outright — and never restored the stock
     * when it did, so inventory silently vanished. A void keeps the record
     * and writes compensating stock movements.
     */
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Completed => 'Completed',
            self::Void => 'Voided',
        };
    }

    public function canVoid(): bool
    {
        return $this === self::Completed;
    }

    public function canReturn(): bool
    {
        return $this === self::Completed;
    }

    public function canTakePayment(): bool
    {
        return $this === self::Completed;
    }
}
