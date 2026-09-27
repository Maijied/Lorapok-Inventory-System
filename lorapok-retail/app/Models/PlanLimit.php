<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One quota on a plan — products, users, shops.
 *
 * @property int $id
 * @property int $plan_id
 * @property string $key
 * @property int|null $value null means unlimited
 */
class PlanLimit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'integer'];
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isUnlimited(): bool
    {
        return $this->value === null;
    }
}
