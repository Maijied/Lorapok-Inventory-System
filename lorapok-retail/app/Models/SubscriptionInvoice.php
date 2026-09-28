<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A bill we issued.
 *
 * Never deleted and never edited once paid — voided instead, exactly like a
 * sale inside a shop. An invoice that can quietly change is not a record.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $number
 * @property int $amount_minor
 * @property string $currency
 * @property InvoiceStatus $status
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 * @property string|null $paid_via
 */
class SubscriptionInvoice extends Model
{
    protected $guarded = [];

    /** @var array<string, string> */
    protected $attributes = ['status' => 'open', 'currency' => 'BDT'];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'amount_minor' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<PaymentAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function amount(): Money
    {
        return Money::ofMinor($this->amount_minor, $this->currency);
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Open
            && $this->due_at !== null
            && $this->due_at->isPast();
    }
}
