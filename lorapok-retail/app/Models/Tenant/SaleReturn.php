<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Goods coming back.
 *
 * The old system printed a seven-day replacement policy on every invoice but
 * had no way to record a return at all; deleting the sale was the only
 * option, and that did not even restore the stock.
 *
 * @property int $refund_total_minor
 * @property-read Collection<int, SaleReturnItem> $items
 */
#[Fillable([
    'number', 'sale_id', 'location_id', 'user_id', 'reason',
    'refund_total_minor', 'currency', 'returned_at', 'note',
])]
class SaleReturn extends Model
{
    use HasFactory;

    protected $attributes = [
        'refund_total_minor' => 0,
        'currency' => 'BDT',
    ];

    protected function casts(): array
    {
        return [
            'refund_total_minor' => 'integer',
            'returned_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
