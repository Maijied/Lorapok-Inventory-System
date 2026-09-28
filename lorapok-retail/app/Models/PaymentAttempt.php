<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One try at settling an invoice.
 *
 * Every attempt is recorded, including the failed ones. A shop that says "I
 * paid on Tuesday" and a gateway that says otherwise are both answerable from
 * this table; keeping only the successful attempts makes that argument
 * unwinnable.
 *
 * @property int $id
 * @property int $subscription_invoice_id
 * @property string $gateway
 * @property string $status
 * @property int $amount_minor
 * @property string|null $reference
 * @property array<string, mixed>|null $payload
 */
class PaymentAttempt extends Model
{
    public const PENDING = 'pending';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    /** Money taken outside any gateway: bKash, Nagad, bank transfer, cash. */
    public const MANUAL = 'manual';

    protected $guarded = [];

    /** Central, always — see ShopVerification for why this is pinned. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }

    /** @var array<string, string> */
    protected $attributes = ['status' => self::PENDING, 'currency' => 'BDT'];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<SubscriptionInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }
}
