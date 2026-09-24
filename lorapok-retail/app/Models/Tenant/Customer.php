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
 * A customer as a real record.
 *
 * The old system repeated name, phone and address as strings on every sale,
 * so there was no purchase history and no way to look a returning customer up.
 *
 * @property bool $is_walkin
 */
#[Fillable([
    'name', 'phone', 'email', 'address', 'credit_limit_minor',
    'opening_balance_minor', 'is_walkin', 'note', 'is_active',
])]
class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $attributes = [
        'credit_limit_minor' => 0,
        'opening_balance_minor' => 0,
        'is_walkin' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'credit_limit_minor' => 'integer',
            'opening_balance_minor' => 'integer',
            'is_walkin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /** The anonymous counter customer, created once per shop. */
    public static function walkIn(): self
    {
        return static::firstOrCreate(
            ['is_walkin' => true],
            ['name' => 'Walk-in customer', 'is_active' => true],
        );
    }

    /** What this customer still owes across all their sales. */
    public function outstandingMinor(): int
    {
        return $this->opening_balance_minor + (int) $this->sales()
            ->where('status', '!=', 'void')
            ->sum('due_minor');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "{$term}%"));
    }
}
