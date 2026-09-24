<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cached running total per location and variant.
 *
 * Derived, not authoritative: `stock_movements` is the truth. This exists so
 * the POS does not have to SUM the whole ledger on every scan, and
 * `stock:reconcile` proves the two agree.
 *
 * @property string $quantity
 * @property int $avg_cost_minor
 */
#[Fillable(['location_id', 'product_variant_id', 'quantity', 'reserved', 'avg_cost_minor', 'value_minor'])]
class StockLevel extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'reserved' => 'decimal:4',
            'avg_cost_minor' => 'integer',
            'value_minor' => 'integer',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** Stock that is physically present but already promised to a sale. */
    public function available(): float
    {
        return (float) $this->quantity - (float) $this->reserved;
    }
}
