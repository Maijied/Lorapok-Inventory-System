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
 * One line of a sale.
 *
 * @property string $qty
 * @property int $unit_price_minor
 * @property int $line_total_minor
 * @property int $cogs_minor
 * @property-read Collection<int, SaleReturnItem> $returnItems
 * @property-read Collection<int, SaleItemSerial> $serials
 * @property ?ProductVariant $variant
 * @property ?int $warranty_days_snapshot
 */
#[Fillable([
    'sale_id', 'product_variant_id', 'description_snapshot', 'qty',
    'unit_price_minor', 'discount_minor', 'tax_rate_bps', 'tax_minor',
    'line_total_minor', 'cogs_minor', 'warranty_days_snapshot',
])]
class SaleItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_rate_bps' => 'integer',
            'tax_minor' => 'integer',
            'line_total_minor' => 'integer',
            'cogs_minor' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(SaleItemSerial::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    /** How much of this line has not yet been returned. */
    public function returnableQty(): float
    {
        return max(0.0, (float) $this->qty - (float) $this->returnItems()->sum('qty'));
    }
}
