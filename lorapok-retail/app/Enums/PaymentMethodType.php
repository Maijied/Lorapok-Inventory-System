<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethodType: string
{
    case Cash = 'cash';
    case Card = 'card';
    /** bKash, Nagad, Rocket. */
    case Mobile = 'mobile';
    case Bank = 'bank';
    /** Nothing changed hands: the balance was carried on account. */
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Mobile => 'Mobile banking',
            self::Bank => 'Bank transfer',
            self::Credit => 'On account',
        };
    }
}
