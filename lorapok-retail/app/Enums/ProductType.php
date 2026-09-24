<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductType: string
{
    /** Counted in bulk: cables, cases, chargers. */
    case Standard = 'standard';

    /** Every unit is individually tracked by IMEI or serial: handsets. */
    case Serialized = 'serialized';

    /** No stock at all: repairs, screen protector fitting. */
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Serialized => 'Serialised (IMEI / serial)',
            self::Service => 'Service',
        };
    }

    public function tracksStock(): bool
    {
        return $this !== self::Service;
    }
}
