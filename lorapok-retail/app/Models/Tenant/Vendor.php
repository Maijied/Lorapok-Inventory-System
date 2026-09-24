<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A supplier. Entirely absent from the old system, which had no way to record
 * where stock came from.
 *
 * @property bool $is_active
 * @property int $opening_balance_minor
 */
#[Fillable([
    'name', 'code', 'phone', 'email', 'address',
    'opening_balance_minor', 'currency', 'note', 'is_active',
])]
class Vendor extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'opening_balance_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** Total still owed across every unpaid order, plus any opening balance. */
    public function outstandingMinor(): int
    {
        return $this->opening_balance_minor
            + (int) $this->purchaseOrders()->sum('due_minor');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "{$term}%")
            ->orWhere('code', 'like', "{$term}%"));
    }
}
