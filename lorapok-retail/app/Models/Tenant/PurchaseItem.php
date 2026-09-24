<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of a purchase order.
 *
 * @property ?ProductVariant $variant
 * @property string $qty_ordered
 * @property string $qty_received
 * @property int $unit_cost_minor
 */
#[Fillable([
    'purchase_order_id', 'product_variant_id', 'qty_ordered', 'qty_received',
    'unit_cost_minor', 'tax_rate_id', 'tax_rate_bps', 'tax_minor', 'line_total_minor',
])]
class PurchaseItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'decimal:4',
            'qty_received' => 'decimal:4',
            'unit_cost_minor' => 'integer',
            'tax_rate_bps' => 'integer',
            'tax_minor' => 'integer',
            'line_total_minor' => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /** How much of this line is still to come. */
    public function outstandingQty(): float
    {
        return max(0.0, (float) $this->qty_ordered - (float) $this->qty_received);
    }

    public function isFullyReceived(): bool
    {
        return $this->outstandingQty() <= 0.0;
    }
}
