<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sellable variant of a product (e.g. "128GB / Black").
 *
 * Present from day one even though v1's UI exposes a single default variant
 * per product: retrofitting variants onto a product-only schema later means
 * rewriting every stock and sales query.
 */
#[Fillable(['product_id', 'sku', 'name', 'attributes', 'cost_minor', 'price_minor', 'is_active'])]
class ProductVariant extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            // Money is an integer count of minor units (paisa), never a float.
            'cost_minor' => 'integer',
            'price_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }
}
