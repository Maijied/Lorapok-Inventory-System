<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\ReturnCondition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ReturnCondition $condition
 * @property bool $restock
 * @property string $qty
 * @property int $refund_minor
 */
#[Fillable([
    'sale_return_id', 'sale_item_id', 'qty', 'unit_price_minor',
    'refund_minor', 'restock', 'condition',
])]
class SaleReturnItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price_minor' => 'integer',
            'refund_minor' => 'integer',
            'restock' => 'boolean',
            'condition' => ReturnCondition::class,
        ];
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
