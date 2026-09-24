<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnCondition: string
{
    /** Goes back on the shelf. */
    case Resellable = 'resellable';
    /** Comes back into stock but is marked faulty, never resold as new. */
    case Defective = 'defective';

    public function label(): string
    {
        return match ($this) {
            self::Resellable => 'Resellable',
            self::Defective => 'Defective',
        };
    }
}
