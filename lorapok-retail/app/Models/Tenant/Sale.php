<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\DiscountType;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property SaleStatus $status
 * @property DiscountType $discount_type
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $tax_minor
 * @property int $rounding_minor
 * @property int $total_minor
 * @property int $paid_minor
 * @property int $due_minor
 * @property int $cogs_minor
 * @property-read Collection<int, SaleItem> $items
 */
#[Fillable([
    'number', 'location_id', 'customer_id', 'user_id', 'status',
    'subtotal_minor', 'discount_type', 'discount_minor', 'tax_minor',
    'rounding_minor', 'total_minor', 'paid_minor', 'due_minor', 'cogs_minor',
    'currency', 'sold_at', 'note', 'idempotency_key',
])]
class Sale extends Model
{
    use HasFactory;

    /**
     * Declared on the model as well as the schema: MySQL applies column
     * defaults on INSERT, but they are not reflected on the in-memory model,
     * which leaves a freshly created sale with a null status.
     */
    protected $attributes = [
        'status' => 'completed',
        'discount_type' => 'none',
        'currency' => 'BDT',
        'subtotal_minor' => 0,
        'discount_minor' => 0,
        'tax_minor' => 0,
        'rounding_minor' => 0,
        'total_minor' => 0,
        'paid_minor' => 0,
        'due_minor' => 0,
        'cogs_minor' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'discount_type' => DiscountType::class,
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'rounding_minor' => 'integer',
            'total_minor' => 'integer',
            'paid_minor' => 'integer',
            'due_minor' => 'integer',
            'cogs_minor' => 'integer',
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    /**
     * Recompute what has been paid and what is still owed.
     *
     * Derived from the payments that exist, so the two can never disagree.
     */
    public function recalculatePayments(): void
    {
        $paid = (int) $this->payments()->sum('amount_minor');

        $this->forceFill([
            'paid_minor' => $paid,
            // An overpayment is change given, not a negative debt.
            'due_minor' => max(0, $this->total_minor - $paid),
        ])->save();
    }

    /** Profit on this sale: revenue less the cost frozen at the time of sale. */
    public function grossMarginMinor(): int
    {
        return $this->subtotal_minor - $this->discount_minor - $this->cogs_minor;
    }

    public function isVoided(): bool
    {
        return $this->status === SaleStatus::Void;
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', SaleStatus::Completed);
    }
}
