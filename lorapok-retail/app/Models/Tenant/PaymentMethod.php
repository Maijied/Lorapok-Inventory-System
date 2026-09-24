<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\PaymentMethodType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property PaymentMethodType $type */
#[Fillable(['name', 'type', 'is_active', 'sort_order'])]
class PaymentMethod extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'is_active' => 'boolean',
        ];
    }
}
