<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $qty
 * @property int $unit_cost_minor
 * @property ?PurchaseItem $purchaseItem
 */
#[Fillable(['goods_receipt_id', 'purchase_item_id', 'qty', 'unit_cost_minor'])]
class GoodsReceiptItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_cost_minor' => 'integer',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }
}
