<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One shop's standing with us.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_end
 * @property Carbon|null $cancel_at
 */
class Subscription extends Model
{
    protected $guarded = [];

    /** Central, always — see ShopVerification for why this is pinned. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }

    /** @var array<string, string> */
    protected $attributes = ['status' => 'trialing'];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<SubscriptionInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * A trial that has run out but has not yet been acted on.
     *
     * Separate from `onTrial()` because the gap between the two is where a
     * shop sits waiting for a scheduled job, and it must still be able to
     * sell while it waits.
     */
    public function trialHasLapsed(): bool
    {
        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isPast();
    }

    public function canOperate(): bool
    {
        return $this->status->canOperate();
    }
}
