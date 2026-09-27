<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription plan.
 *
 * Lives centrally: plans are the same for every shop, and the pricing page
 * renders from these rows rather than from hardcoded markup — change a row and
 * the public page changes, with no deploy.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price_minor
 * @property string $currency
 * @property string $interval
 * @property int $trial_days
 * @property bool $is_active
 * @property int $sort_order
 */
class Plan extends Model
{
    use HasFactory;

    protected $guarded = [];

    /** @var array<string, bool|int|string> */
    protected $attributes = [
        'currency' => 'BDT',
        'interval' => 'monthly',
        'trial_days' => 14,
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'trial_days' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<PlanLimit, $this> */
    public function limits(): HasMany
    {
        return $this->hasMany(PlanLimit::class);
    }

    /**
     * The value of one limit, or null when it is unlimited.
     *
     * A null in `plan_limits.value` means unlimited, deliberately — the
     * alternative is a sentinel like -1 or PHP_INT_MAX, which eventually gets
     * compared with `<` somewhere and silently caps the plan.
     */
    public function limit(string $key): ?int
    {
        $limit = $this->limits->firstWhere('key', $key);

        return $limit?->value;
    }

    public function isFree(): bool
    {
        return $this->price_minor === 0;
    }
}
