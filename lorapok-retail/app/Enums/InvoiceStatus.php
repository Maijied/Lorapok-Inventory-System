<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    /** Issued, not yet paid. */
    case Open = 'open';

    case Paid = 'paid';

    /** Withdrawn by us — an invoice is never deleted, only voided. */
    case Void = 'void';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Paid => 'positive',
            self::Open => 'attention',
            self::Void => 'neutral',
        };
    }

    public function isSettled(): bool
    {
        return $this !== self::Open;
    }
}
