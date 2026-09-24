<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Money moving, against a purchase now and a sale in the next phase.
 *
 * Several payments may exist for one document: part cash, part bKash, or a
 * deposit followed by a settlement. The old system had a single grand_total
 * and no payment records at all, so a sale was implicitly assumed paid in
 * full, in cash, immediately.
 *
 * @property int $amount_minor
 */
#[Fillable([
    'payable_type', 'payable_id', 'payment_method_id', 'amount_minor',
    'currency', 'paid_at', 'reference', 'note', 'created_by',
])]
class Payment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
