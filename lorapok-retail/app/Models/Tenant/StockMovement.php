<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One immutable entry in the stock ledger.
 *
 * INSERT ONLY. Never updated, never deleted — a mistake is corrected by
 * writing an opposing entry, exactly as in double-entry bookkeeping. This is
 * what makes stock history reconstructable, and it is why deleting a sale can
 * no longer silently destroy inventory as it did in the old system.
 *
 * @property MovementType $type
 * @property string $quantity
 * @property ?int $unit_cost_minor
 */
#[Fillable([
    'location_id', 'product_variant_id', 'stock_item_id', 'quantity',
    'unit_cost_minor', 'type', 'reference_type', 'reference_id',
    'occurred_at', 'created_by', 'note',
])]
class StockMovement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'decimal:4',
            'unit_cost_minor' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The ledger's immutability is enforced here rather than left to
        // convention, because a single forgotten update would make every
        // historical figure unreliable.
        static::updating(function (): never {
            throw new LogicException(
                'Stock movements are immutable. Write a reversing movement instead of editing one.',
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Stock movements cannot be deleted. Write a reversing movement instead.',
            );
        });
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
