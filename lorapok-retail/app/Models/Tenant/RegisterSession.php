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
 * One cashier's shift on one till.
 *
 * @property int $opening_float_minor
 * @property ?int $expected_cash_minor
 * @property ?int $counted_cash_minor
 * @property ?int $variance_minor
 * @property-read Collection<int, RegisterMovement> $movements
 */
#[Fillable([
    'cash_register_id', 'opened_by', 'closed_by', 'opening_float_minor',
    'expected_cash_minor', 'counted_cash_minor', 'variance_minor',
    'opened_at', 'closed_at', 'note',
])]
class RegisterSession extends Model
{
    use HasFactory;

    protected $attributes = ['opening_float_minor' => 0];

    protected function casts(): array
    {
        return [
            'opening_float_minor' => 'integer',
            'expected_cash_minor' => 'integer',
            'counted_cash_minor' => 'integer',
            'variance_minor' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(RegisterMovement::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * What should be in the drawer right now: the float, plus everything
     * taken in, less everything paid out.
     */
    public function expectedCashMinor(): int
    {
        $in = (int) $this->movements()->where('direction', 'in')->sum('amount_minor');
        $out = (int) $this->movements()->where('direction', 'out')->sum('amount_minor');

        return $this->opening_float_minor + $in - $out;
    }
}
