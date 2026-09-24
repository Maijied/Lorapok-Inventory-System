<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\PurchaseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An intention to buy stock. Writes no stock movements itself — only a
 * goods receipt does that.
 *
 * @property PurchaseStatus $status
 * @property int $total_minor
 * @property int $paid_minor
 * @property int $due_minor
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $tax_minor
 * @property-read Collection<int, PurchaseItem> $items
 */
#[Fillable([
    'number', 'vendor_id', 'location_id', 'status', 'expected_at',
    'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor',
    'paid_minor', 'due_minor', 'currency', 'note', 'created_by',
])]
class PurchaseOrder extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Column defaults are declared here as well as in the schema.
     *
     * A database default is applied by MySQL on INSERT but is not reflected
     * on the in-memory model, so a freshly created order had a null status
     * and every guard that inspected it misbehaved.
     */
    protected $attributes = [
        'status' => 'draft',
        'currency' => 'BDT',
        'subtotal_minor' => 0,
        'discount_minor' => 0,
        'tax_minor' => 0,
        'total_minor' => 0,
        'paid_minor' => 0,
        'due_minor' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'expected_at' => 'date',
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'paid_minor' => 'integer',
            'due_minor' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Recompute money totals from the lines and the recorded payments.
     *
     * `paid` and `due` are derived rather than typed in, so they cannot drift
     * away from the payments that actually exist.
     */
    public function recalculateTotals(): void
    {
        $this->loadMissing('items');

        $subtotal = 0;
        $tax = 0;

        foreach ($this->items as $item) {
            $subtotal += (int) round((float) $item->qty_ordered * $item->unit_cost_minor);
            $tax += $item->tax_minor;
        }

        $paid = (int) $this->payments()->sum('amount_minor');
        $total = $subtotal - $this->discount_minor + $tax;

        $this->forceFill([
            'subtotal_minor' => $subtotal,
            'tax_minor' => $tax,
            'total_minor' => $total,
            'paid_minor' => $paid,
            // Never negative: an overpayment is a credit, not a negative debt.
            'due_minor' => max(0, $total - $paid),
        ])->save();
    }

    /**
     * Set the status from what has actually been received.
     *
     * Derived rather than assigned, so "received" always means the stock is
     * really on the shelf.
     */
    public function refreshReceivedStatus(): void
    {
        if (in_array($this->status, [PurchaseStatus::Draft, PurchaseStatus::Cancelled], true)) {
            return;
        }

        $this->loadMissing('items');

        $anyReceived = $this->items->contains(fn (PurchaseItem $i) => (float) $i->qty_received > 0);
        $allReceived = $this->items->every(fn (PurchaseItem $i) => $i->isFullyReceived());

        $this->forceFill([
            'status' => match (true) {
                $allReceived => PurchaseStatus::Received,
                $anyReceived => PurchaseStatus::Partial,
                default => PurchaseStatus::Ordered,
            },
        ])->save();
    }

    public function isFullyPaid(): bool
    {
        return $this->due_minor <= 0;
    }
}
