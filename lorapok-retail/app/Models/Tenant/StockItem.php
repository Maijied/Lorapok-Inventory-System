<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\StockItemStatus;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One individually tracked unit — a handset identified by its IMEI.
 *
 * In the old system an IMEI was a free-text string typed onto a sale line,
 * one per line regardless of quantity, not unique and not indexed. It was
 * therefore impossible to answer "which IMEIs are in stock", or to stop the
 * same handset being sold twice.
 *
 * @property StockItemStatus $status
 * @property int $purchase_cost_minor
 */
#[Fillable([
    'product_variant_id', 'location_id', 'serial', 'imei', 'imei2',
    'status', 'purchase_cost_minor', 'warranty_starts_at', 'warranty_ends_at', 'note',
])]
class StockItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => StockItemStatus::class,
            'purchase_cost_minor' => 'integer',
            'warranty_starts_at' => 'datetime',
            'warranty_ends_at' => 'datetime',
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

    public function events(): HasMany
    {
        return $this->hasMany(StockItemEvent::class);
    }

    /**
     * Move the unit to a new status, refusing transitions that make no sense.
     *
     * Every transition is recorded, so the life of a handset — received,
     * sold, returned, written off — can be reconstructed.
     */
    public function transitionTo(
        StockItemStatus $target,
        ?EloquentModel $reference = null,
        ?int $actorId = null,
        ?string $note = null,
    ): void {
        $from = $this->status;

        if ($from === $target) {
            return;
        }

        if (! $from->canTransitionTo($target)) {
            throw new DomainException(
                "Cannot move {$this->identifier()} from {$from->value} to {$target->value}.",
            );
        }

        $this->status = $target;
        $this->save();

        $this->events()->create([
            'from_status' => $from->value,
            'to_status' => $target->value,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'actor_id' => $actorId,
            'created_at' => now(),
            'note' => $note,
        ]);
    }

    /** Whatever identifies this unit to a human: IMEI if present, else serial. */
    public function identifier(): string
    {
        return $this->imei ?? $this->serial ?? "#{$this->id}";
    }

    public function scopeInStock($query)
    {
        return $query->where('status', StockItemStatus::InStock);
    }
}
